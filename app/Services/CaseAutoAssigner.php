<?php

namespace App\Services;

use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
use App\Models\User;
use App\Notifications\RefillLoadAlert;
use App\Services\Routing\RoutingExceptionRecorder;
use App\Services\Routing\RoutingPolicyResolver;
use App\Services\Routing\RoutingStrategy;
use Illuminate\Support\Facades\Log;

/**
 * Chooses the doctor a new case is auto-assigned to.
 *
 * Now driven by the versioned routing policy ported from MA-DOCPORTAL (Devin msg
 * 2122: every routing option from MA, fully wired). All five modes run through
 * `RoutingPolicyResolver` + `RoutingStrategy`.
 *
 * NOTHING CHANGES ON THE DAY THIS SHIPS. The migration seeds an ACTIVE PRIORITY
 * policy, which is the behaviour MEDAXIS already had, so cases route exactly as
 * before until a different version is activated deliberately.
 *
 * TWO REAL BEHAVIOUR CHANGES, BOTH INTENTIONAL, BOTH IN docs/integrations/ROUTING.md:
 *
 *  1. NO MORE UNLICENSED FALLBACK. The old version, when nobody held a licence in
 *     the patient's state, logged a warning and assigned an unlicensed clinician
 *     anyway so cases would not stick. MA treats that as a hard block and so does
 *     this. A case with no licensed doctor now WAITS. A stuck case is visible and
 *     recoverable; a prescription written by someone unlicensed in the patient's
 *     state is neither.
 *  2. PROVIDER_POOL assigns nobody by design, leaving cases to be claimed.
 *     `findNext()` returning null already means "leave it in the queue", so
 *     existing callers need no change.
 */
class CaseAutoAssigner
{
    private RoutingPolicyResolver $resolver;
    private RoutingExceptionRecorder $exceptions;

    public function __construct(
        ?RoutingPolicyResolver $resolver = null,
        ?RoutingExceptionRecorder $exceptions = null,
    ) {
        $this->resolver   = $resolver ?? new RoutingPolicyResolver();
        $this->exceptions = $exceptions ?? new RoutingExceptionRecorder();
    }

    /**
     * The doctor this case should go to, or null to leave it in the queue.
     *
     * Still returns a Clinician, so this is a drop-in replacement for every
     * existing caller.
     */
    public function findNext(PatientCase $case): ?Clinician
    {
        $outcome = $this->resolver->resolve($case);

        if (($outcome['kind'] ?? null) === RoutingStrategy::ASSIGN) {
            $clinician = Clinician::find($outcome['providerId']);

            /*
             * Continuity assignments are logged and ordinary ones are not,
             * because this is the one that looks wrong from outside: the
             * rotation appears to have been skipped, and a doctor may be over
             * their cap. Ids only, no PHI.
             */
            if (($outcome['reason'] ?? null) === 'CONTINUITY_OF_CARE') {
                Log::info('CaseAutoAssigner: continuity of care', [
                    'case_uuid'    => $case->uuid,
                    'clinician_id' => $outcome['providerId'],
                    'reason'       => 'Check-in returned to the doctor who treated this patient before.',
                ]);

                if ($clinician) {
                    $this->maybeAlertRefillLoad($clinician, $case);
                }
            }

            // Anything that was stuck is not stuck any more. Closing it here
            // rather than waiting for the sweep keeps the exceptions screen
            // showing what is wrong NOW, which is the only version of it anyone
            // will keep looking at.
            $this->exceptions->resolve($case);

            return $clinician;
        }

        // Logged rather than silent: "why did nothing get assigned" is the first
        // question anyone asks, and POOL and NONE mean very different things.
        Log::info('CaseAutoAssigner: no auto-assignment', [
            'case_uuid'     => $case->uuid,
            'outcome'       => $outcome['kind'] ?? 'NONE',
            'patient_state' => $case->patient_state,
            'reason'        => ($outcome['kind'] ?? null) === RoutingStrategy::POOL
                ? 'Routing policy is PROVIDER_POOL, the case waits to be claimed.'
                : 'No eligible doctor. Check licences for the patient state, capacity caps and availability.',
        ]);

        /*
         * NO SILENT FAILURES (Devin msg 2308). A log line and an empty
         * clinician_id look identical to a case created four seconds ago, so a
         * failure to route now becomes a row an admin owns, with the reason code
         * and the per-doctor block reasons attached.
         *
         * POOL IS NOT A FAILURE and deliberately records nothing: under
         * PROVIDER_POOL a case waiting to be claimed is the design working. The
         * pool queue is surfaced with its own ages on the exceptions screen, so
         * a case rotting there is still visible without pretending it is broken.
         */
        if (($outcome['kind'] ?? null) !== RoutingStrategy::POOL) {
            $this->exceptions->record(
                $case,
                $outcome['reasonCode'] ?? \App\Models\RoutingException::NO_ELIGIBLE_PROVIDER,
                $outcome['providerReasons'] ?? [],
            );
        }

        return null;
    }

    /**
     * Alert the doctor's admins when a continuity check-in pushes them past their
     * refill alert threshold (Devin msg 2250 A).
     *
     * SOFT, BY DESIGN. The case is already assigned to this doctor and stays with
     * them. This only notifies. It counts check-ins assigned to the doctor
     * earlier today; the current one is the one crossing the line, so `>=` on the
     * prior count is "this assignment takes them over".
     *
     * Wrapped so a notification failure can never break assignment: routing a
     * patient matters, telling an admin about load does not.
     */
    private function maybeAlertRefillLoad(Clinician $clinician, PatientCase $case): void
    {
        $threshold = (int) ($clinician->daily_refill_alert_threshold ?? 0);

        if ($threshold <= 0) {
            return;
        }

        try {
            $priorRefillsToday = PatientCase::where('clinician_id', $clinician->id)
                ->where('is_refill', true)
                ->whereNotNull('assigned_at')
                ->where('assigned_at', '>=', now()->startOfDay())
                ->where('id', '!=', $case->id)
                ->count();

            if ($priorRefillsToday < $threshold) {
                return;
            }

            $admins = $clinician->admins()->get();

            // No Doctor Admin assigned: fall back to the general admin pool so the
            // alert is not silently dropped for an unmanaged doctor.
            if ($admins->isEmpty()) {
                $admins = User::role(['admin', 'super_admin'])->get();
            }

            $notification = new RefillLoadAlert($clinician, $case, $priorRefillsToday + 1, $threshold);
            $admins->each(fn ($admin) => $admin->notify($notification));
        } catch (\Throwable $e) {
            Log::warning('Refill load alert failed: ' . $e->getMessage(), [
                'clinician_id' => $clinician->id,
                'case_uuid'    => $case->uuid,
            ]);
        }
    }

    /**
     * Why each doctor was or was not selected, for the admin routing screen.
     *
     * Uses the same gather and the same rules as the real decision, so the screen
     * explains what actually happens rather than a parallel reimplementation that
     * can drift out of agreement with it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function explain(PatientCase $case): array
    {
        $policy = RoutingPolicy::active();

        if (! $policy) {
            return [];
        }

        $weights    = $policy->intelligentWeights();
        $candidates = $this->resolver->candidates($case, $policy);
        $clinicians = Clinician::with('user')
            ->whereIn('id', array_map(fn ($c) => $c->providerId, $candidates))
            ->get()->keyBy('id');

        $out = [];

        foreach ($candidates as $candidate) {
            $score = RoutingStrategy::score($candidate, $weights);

            $out[] = [
                'clinician' => $clinicians[$candidate->providerId] ?? null,
                'eligible'  => ! RoutingStrategy::isHardBlocked($candidate),
                'reasons'   => $candidate->rejectionReasons,
                'score'     => is_finite($score) ? round($score, 2) : null,
                'openCases' => $candidate->openCases,
                'today'     => $candidate->currentDailyVolume,
                'weight'    => $candidate->weightAllocation,
            ];
        }

        return $out;
    }
}
