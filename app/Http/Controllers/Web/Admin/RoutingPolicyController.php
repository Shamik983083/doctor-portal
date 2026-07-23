<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\RoutingPolicy;
use App\Services\Routing\RoutingMode;
use App\Services\Routing\RoutingWeights;
use Illuminate\Http\Request;

/**
 * Routing policy administration (super admin only).
 *
 * Routing decides which doctor sees which patient, so every change is a new
 * VERSION rather than an edit in place, and activation is explicit. The active
 * version is never edited: to change routing you draft a new version and activate
 * it, which leaves the previous rules on the record.
 */
class RoutingPolicyController extends Controller
{
    public function index()
    {
        return view('admin.routing.index', [
            'policies'   => RoutingPolicy::with(['createdBy', 'activatedBy'])->orderByDesc('version')->get(),
            'active'     => RoutingPolicy::active(),
            'modes'      => RoutingMode::LABELS,
            'modeNotes'  => RoutingMode::DESCRIPTIONS,
            'clinicians' => Clinician::with('user')->orderBy('priority')->get(),
            'weightKeys' => RoutingWeights::LABELS,
            'defaults'   => RoutingWeights::DEFAULTS,
        ]);
    }

    /**
     * Draft a new version. Never activates it: drafting and activating are
     * separate acts so a half-finished policy cannot start routing patients.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            /*
             * TWO MODES, ONE PER PATH (Devin msg 2308: "THERE ARE 2 CHECKS:
             * 1. NEW CLIENTS 2. REFILL CLIENTS. WE NEED TO HAVE CAPS AND ROUTING
             * FOR EACH"). The refill mode is only reached when continuity cannot
             * place the check-in with the patient's own doctor.
             */
            'new_mode'    => 'required|in:' . implode(',', RoutingMode::ALL),
            'refill_mode' => 'required|in:' . implode(',', RoutingMode::ALL),
            'note' => 'nullable|string|max:1000',
            // Pool eligibility (Devin msg 2313 Q5). Blank means the criterion is off.
            'pool_max_outstanding_cases' => 'nullable|integer|min:1|max:9999',
            'pool_max_overdue_cases'     => 'nullable|integer|min:1|max:9999',
            'pool_overdue_after_hours'   => 'nullable|numeric|min:1|max:720',
            'pool_max_awaiting_reply'    => 'nullable|integer|min:1|max:9999',
            'pool_max_per_request'       => 'nullable|integer|min:1|max:500',
            'pool_max_per_day'           => 'nullable|integer|min:1|max:999',
            'message_aging_hours' => 'nullable|numeric|min:0|max:720',
            'require_recorded_licensure' => 'nullable|boolean',
            // Admin-set criteria that stop NEW cases (Devin msg 2248). Optional;
            // blank leaves the criterion off. Delayed needs BOTH the count and
            // the hours to do anything, enforced below.
            'new_case_max_delayed_cases'   => 'nullable|integer|min:0|max:9999',
            'new_case_delayed_after_hours' => 'nullable|numeric|min:1|max:720',
            'new_case_max_awaiting_reply'  => 'nullable|integer|min:0|max:9999',
            'weights'   => 'nullable|array',
            'weights.*' => 'nullable|numeric',
            'provider_weights'   => 'nullable|array',
            'provider_weights.*' => 'nullable|numeric|min:0',
        ]);

        /*
         * The delayed-cases criterion is two fields that only work as a pair: a
         * count with no "delayed after" window cannot be evaluated, and a window
         * with no count blocks nobody. Persist both or neither, so a
         * half-filled form does not store a criterion that silently does
         * nothing (or, worse, one the resolver reads as zero).
         */
        $delayedCount = $data['new_case_max_delayed_cases'] ?? null;
        $delayedHours = $data['new_case_delayed_after_hours'] ?? null;
        if ($delayedCount === null || $delayedHours === null) {
            $delayedCount = null;
            $delayedHours = null;
        }

        // Only keep coefficients that are actually part of the score, so a stray
        // field cannot end up persisted as configuration nothing reads.
        $weights = [];
        foreach (array_keys(RoutingWeights::DEFAULTS) as $key) {
            if (isset($data['weights'][$key]) && is_numeric($data['weights'][$key])) {
                $weights[$key] = (float) $data['weights'][$key];
            }
        }

        $providerWeights = [];
        foreach ($data['provider_weights'] ?? [] as $clinicianId => $weight) {
            if (is_numeric($weight)) {
                $providerWeights[(string) (int) $clinicianId] = (float) $weight;
            }
        }

        /*
         * The overdue criterion is a pair, exactly like the delayed one above: a
         * count with no window cannot be evaluated and a window with no count
         * blocks nobody. Store both or neither.
         */
        $overdueCount = $data['pool_max_overdue_cases'] ?? null;
        $overdueHours = $data['pool_overdue_after_hours'] ?? null;
        if ($overdueCount === null || $overdueHours === null) {
            $overdueCount = null;
            $overdueHours = null;
        }

        $policy = RoutingPolicy::create([
            'version' => ((int) RoutingPolicy::max('version')) + 1,
            // The column stays as the fallback any path reads through to when its
            // own mode is absent, which is what keeps older versions meaningful.
            'mode'    => $data['new_mode'],
            'config'  => [
                'newMode'                    => $data['new_mode'],
                'refillMode'                 => $data['refill_mode'],
                'intelligentWeights'         => $weights,
                'providerWeights'            => $providerWeights,
                'messageAgingThresholdHours' => $data['message_aging_hours'] ?? null,
                'requireRecordedLicensure'   => $request->boolean('require_recorded_licensure'),
                'newCaseMaxDelayedCases'     => $delayedCount,
                'newCaseDelayedAfterHours'   => $delayedHours,
                'newCaseMaxAwaitingReply'    => $data['new_case_max_awaiting_reply'] ?? null,
                'poolCriteria'               => [
                    'maxOutstandingCases' => $data['pool_max_outstanding_cases'] ?? null,
                    'maxOverdueCases'     => $overdueCount,
                    'overdueAfterHours'   => $overdueHours,
                    'maxAwaitingReply'    => $data['pool_max_awaiting_reply'] ?? null,
                    'maxCasesPerRequest'  => $data['pool_max_per_request'] ?? null,
                    'maxCasesPerDay'      => $data['pool_max_per_day'] ?? null,
                ],
            ],
            'status'     => RoutingPolicy::STATUS_DRAFT,
            'created_by' => $request->user()?->id,
            'note'       => $data['note'] ?? null,
        ]);

        return redirect()->route('admin.routing.index')
            ->with('success', "Routing policy v{$policy->version} drafted. It is not live until you activate it.");
    }

    /**
     * Activate a draft.
     *
     * This is the moment routing actually changes, so the confirmation says so in
     * those terms rather than "saved".
     */
    public function activate(Request $request, int $id)
    {
        $policy = RoutingPolicy::findOrFail($id);

        if ($policy->status === RoutingPolicy::STATUS_ACTIVE) {
            return redirect()->route('admin.routing.index')->with('warning', 'That version is already active.');
        }

        $policy->activate($request->user()?->id);

        return redirect()->route('admin.routing.index')
            ->with('success', "Routing policy v{$policy->version} ({$policy->modeLabel()}) is now live. "
                . 'New cases route under these rules from now on.');
    }
}
