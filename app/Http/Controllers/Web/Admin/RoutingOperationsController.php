<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CasePullRequest;
use App\Models\Clinician;
use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\PatientCase;
use App\Models\RoutingException;
use App\Models\SlaPolicy;
use App\Models\StateVisitRequirement;
use App\Services\Routing\PoolPullService;
use Illuminate\Http\Request;

/**
 * The operational side of routing: what broke, who is asking for work, and the
 * two configuration surfaces added in v2.
 *
 * Split from RoutingPolicyController on purpose. That one is about the versioned
 * policy and is super-admin only. This one is what a Doctor Admin looks at during
 * the day, so most of it is open to `admin` as well, with the state visit matrix
 * held back to super admin because it encodes law.
 */
class RoutingOperationsController extends Controller
{
    /*
     * ── Exceptions: cases that did not route ─────────────────────────────────
     *   Devin msg 2308: "ANY FAILURE NEEDS TO BE LOUD, WE NEED THE DOCTOR ADMIN
     *   AND SUPER ADMIN TO SEE CASES THAT AREN'T ASSIGNED OR HAVE AN ISSUE OR AN
     *   ERROR NO SILENT FAILURES"
     */

    public function exceptions()
    {
        $exceptions = RoutingException::open()
            ->with(['case.patient', 'case.partner', 'case.subStorefront'])
            ->orderByDesc('first_seen_at')
            ->get();

        /*
         * The pool queue is shown alongside, because a case sitting unclaimed in
         * PROVIDER_POOL mode is not an error but IS an unassigned case, and Devin
         * asked to see unassigned cases. Keeping them in separate lists is what
         * stops "working as designed" and "broken" from looking the same.
         */
        $pooled = PatientCase::whereNull('clinician_id')
            ->where('status', PatientCase::STATUS_WAITING)
            ->with(['patient', 'partner', 'subStorefront'])
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return view('admin.routing.exceptions', [
            'exceptions'  => $exceptions,
            'pooled'      => $pooled,
            'pooledTotal' => PatientCase::whereNull('clinician_id')
                ->where('status', PatientCase::STATUS_WAITING)
                ->count(),
        ]);
    }

    /**
     * Clear an exception by hand.
     *
     * Rarely the right button: an exception whose case routes closes itself. This
     * exists for the case an admin has dealt with another way (cancelled it,
     * assigned it manually before the sweep noticed), so the screen can be got
     * back to zero. A screen that cannot reach zero stops being read.
     */
    public function resolveException(Request $request, int $id)
    {
        $exception = RoutingException::findOrFail($id);

        $exception->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Exception cleared.');
    }

    /*
     * ── Pull requests: doctors asking the pool for work ──────────────────────
     */

    public function pullRequests()
    {
        // Scoped to the doctors this admin is over, same rule as every other
        // clinician-facing admin screen. A Doctor Admin approving a pull for
        // somebody else's doctor would be an authorisation hole, not a shortcut.
        $visible = Clinician::visibleTo(auth()->user())->pluck('id');

        return view('admin.routing.pull-requests', [
            'pending' => CasePullRequest::pending()
                ->whereIn('clinician_id', $visible)
                ->with('clinician.user')
                ->orderBy('created_at')
                ->get(),
            'recent' => CasePullRequest::whereIn('clinician_id', $visible)
                ->where('status', '!=', CasePullRequest::STATUS_PENDING_APPROVAL)
                ->with(['clinician.user', 'decidedBy'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function approvePull(Request $request, int $id, PoolPullService $pool)
    {
        $pullRequest = CasePullRequest::whereIn('clinician_id', Clinician::visibleTo(auth()->user())->pluck('id'))
            ->with('clinician')
            ->findOrFail($id);

        $data = $request->validate(['decision_note' => 'nullable|string|max:500']);

        $granted = $pool->approve($pullRequest, $request->user(), $data['decision_note'] ?? null);

        return back()->with('success', "Approved. {$granted->granted_count} case(s) transferred.");
    }

    public function denyPull(Request $request, int $id, PoolPullService $pool)
    {
        $pullRequest = CasePullRequest::whereIn('clinician_id', Clinician::visibleTo(auth()->user())->pluck('id'))
            ->findOrFail($id);

        $data = $request->validate(['decision_note' => 'nullable|string|max:500']);

        $pool->deny($pullRequest, $request->user(), $data['decision_note'] ?? null);

        return back()->with('success', 'Request denied. Nothing was transferred.');
    }

    /*
     * ── The state synchronous-visit matrix (super admin) ─────────────────────
     *   Devin msg 2313 Q4: "we need to adjust as super admin as laws change
     *   frequently. the Sync is determined by states."
     */

    public function visitRequirements()
    {
        return view('admin.routing.visit-requirements', [
            'rules'      => StateVisitRequirement::with(['category', 'offering', 'createdBy'])
                ->orderBy('state')
                ->orderByDesc('scope_type')
                ->get(),
            'categories' => OfferingCategory::where('is_active', true)->orderBy('name')->get(),
            'offerings'  => Offering::where('is_active', true)->orderBy('name')->get(),
            'scopes'     => StateVisitRequirement::SCOPE_LABELS,
        ]);
    }

    public function storeVisitRequirement(Request $request)
    {
        $data = $request->validate([
            'scope_type'           => 'required|in:ALL,CATEGORY,OFFERING',
            'offering_category_id' => 'nullable|integer|exists:offering_categories,id',
            'offering_id'          => 'nullable|integer|exists:offerings,id',
            'state'                => 'required|string|size:2',
            'requires_synchronous' => 'nullable|boolean',
            'effective_from'       => 'nullable|date',
            'effective_to'         => 'nullable|date|after_or_equal:effective_from',
            'note'                 => 'nullable|string|max:500',
        ]);

        /*
         * A scoped rule with no target matches nothing (see
         * StateVisitRequirementResolver::applies), so refuse it here rather than
         * store a rule that silently does nothing. Somebody would set it, watch
         * it not apply, and conclude the matrix is broken.
         */
        if ($data['scope_type'] === StateVisitRequirement::SCOPE_CATEGORY && empty($data['offering_category_id'])) {
            return back()->withInput()->with('error', 'Pick a category, or change the scope to every product.');
        }

        if ($data['scope_type'] === StateVisitRequirement::SCOPE_OFFERING && empty($data['offering_id'])) {
            return back()->withInput()->with('error', 'Pick a product, or change the scope to every product.');
        }

        StateVisitRequirement::create([
            'scope_type'           => $data['scope_type'],
            // Cleared for scopes that do not use them, so a rule narrowed and
            // then widened again cannot keep a stale target.
            'offering_category_id' => $data['scope_type'] === StateVisitRequirement::SCOPE_CATEGORY
                ? $data['offering_category_id'] : null,
            'offering_id'          => $data['scope_type'] === StateVisitRequirement::SCOPE_OFFERING
                ? $data['offering_id'] : null,
            'state'                => strtoupper($data['state']),
            'requires_synchronous' => $request->boolean('requires_synchronous'),
            'effective_from'       => $data['effective_from'] ?? null,
            'effective_to'         => $data['effective_to'] ?? null,
            'created_by'           => $request->user()?->id,
            'note'                 => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Rule saved. It applies to cases created from now on.');
    }

    /**
     * End a rule.
     *
     * Deleting a rule that has already routed cases erases the reason those cases
     * went where they did, so a rule that has been in force is CLOSED with an
     * effective_to date instead. Only a rule that never took effect is deleted
     * outright.
     */
    public function destroyVisitRequirement(int $id)
    {
        $rule = StateVisitRequirement::findOrFail($id);

        $hasApplied = $rule->effective_from === null || $rule->effective_from->isPast();

        if ($hasApplied) {
            $rule->update(['effective_to' => now()->subDay()]);

            return back()->with('success', 'Rule ended. It stays on the record for cases already routed under it.');
        }

        $rule->delete();

        return back()->with('success', 'Rule deleted. It had not taken effect yet.');
    }

    /*
     * ── SLA policies, owned by a Doctor Admin ────────────────────────────────
     *   Devin msg 2313 Q6: "SLA is going to be adjusted by doctor admin and
     *   pushed down so we need a node for that."
     */

    public function slaIndex()
    {
        $user = auth()->user();

        // A super admin sees every policy; a Doctor Admin sees and edits only
        // their own. Their policy governs their doctors, so someone else's is
        // neither their business nor theirs to change.
        $policies = $user?->isSuperAdmin()
            ? SlaPolicy::with('owner')->orderBy('name')->get()
            : SlaPolicy::with('owner')->where('owner_user_id', $user?->id)->get();

        return view('admin.routing.sla', [
            'policies'  => $policies,
            'mine'      => SlaPolicy::where('owner_user_id', $user?->id)->first(),
            'onActions' => SlaPolicy::ON_VIOLATION_LABELS,
            'doctors'   => Clinician::visibleTo($user)->with('user')->get(),
        ]);
    }

    public function storeSla(Request $request)
    {
        $data = $request->validate([
            'name'                        => 'nullable|string|max:150',
            'max_outstanding_cases'       => 'nullable|integer|min:1|max:9999',
            'max_overdue_cases'           => 'nullable|integer|min:1|max:9999',
            'overdue_after_hours'         => 'nullable|integer|min:1|max:720',
            'max_median_decision_minutes' => 'nullable|integer|min:1|max:100000',
            'on_violation'                => 'required|in:BYPASS,REQUIRE_APPROVAL',
            'is_active'                   => 'nullable|boolean',
            'note'                        => 'nullable|string|max:500',
        ]);

        // Same pairing rule as everywhere else: a count with no window cannot be
        // evaluated, so store both or neither.
        $overdueCount = $data['max_overdue_cases'] ?? null;
        $overdueHours = $data['overdue_after_hours'] ?? null;
        if ($overdueCount === null || $overdueHours === null) {
            $overdueCount = null;
            $overdueHours = null;
        }

        SlaPolicy::updateOrCreate(
            ['owner_user_id' => $request->user()->id],
            [
                'name'                        => $data['name'] ?: 'SLA policy',
                'max_outstanding_cases'       => $data['max_outstanding_cases'] ?? null,
                'max_overdue_cases'           => $overdueCount,
                'overdue_after_hours'         => $overdueHours,
                'max_median_decision_minutes' => $data['max_median_decision_minutes'] ?? null,
                'on_violation'                => $data['on_violation'],
                'is_active'                   => $request->boolean('is_active'),
                'note'                        => $data['note'] ?? null,
            ],
        );

        return back()->with('success', 'SLA saved. It applies to the doctors you are over, on their next pull request.');
    }
}
