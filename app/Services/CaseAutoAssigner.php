<?php

namespace App\Services;

use App\Models\Clinician;
use App\Models\PatientCase;
use App\Models\RoutingPolicy;
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

    public function __construct(?RoutingPolicyResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new RoutingPolicyResolver();
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
            return Clinician::find($outcome['providerId']);
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

        return null;
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
