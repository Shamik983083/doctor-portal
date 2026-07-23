<?php

namespace App\Services\Routing;

use App\Models\Clinician;
use App\Models\PatientCase;
use Illuminate\Support\Facades\Log;

/**
 * Continuity of care: send a check-in back to the doctor who treated the patient.
 *
 * THE REQUIREMENT (Devin msg 2244, clarified 2246). "If a refill comes in it
 * should go to the original provider once they're still active in the portal."
 * Refill here means a re-bill / check-in: the patient submits a check-in and a
 * doctor prescribes again. It is not a pharmacy refill.
 *
 * WHY THIS IS NOT JUST A ROUTING MODE. The five routing modes all answer "who is
 * least loaded right now". Continuity answers a different question, "who already
 * knows this patient", and it has to win over the first question when it applies.
 * So it runs BEFORE the strategy and returns null the moment it does not apply,
 * which leaves every existing route untouched for first visits.
 *
 * WHICH GATES IT MAY OVERRIDE (Devin msg 2246: "Block the first 2 override the
 * daily case cap"). The rule underneath his answer, and the one this class
 * encodes: continuity beats WORKLOAD gates and never beats LEGAL or ACCESS ones.
 *
 *   NEVER overridden:
 *     - not active           they are gone from the portal
 *     - unavailable          they switched themselves off, which is the same
 *                            answer as "not active" from the patient's side
 *     - no state licence     a legal gate; overriding it would produce a
 *                            prescription written by someone who cannot lawfully
 *                            write it, which is the whole point of Law 4
 *     - no storefront grant  an access gate (see the note on this below)
 *
 *   Overridden for a check-in:
 *     - daily volume cap     Devin's explicit call
 *     - open cases cap       same family, same reasoning
 *     - message aging block  also a workload gate. Called out because he did not
 *                            rule on it: it fires when a doctor has an unanswered
 *                            patient message older than the configured limit. It
 *                            is treated as workload, so continuity overrides it.
 *                            If it should instead block, move the constant.
 *
 * The caps being overridable is exactly why a separate cap on NEW cases is
 * needed and is on Devin's list (msg 2246). Without it, a doctor's daily cap now
 * only governs first visits while check-ins flow past it, so the single existing
 * `max_daily_cases` means less than it used to. That cap is NOT built here.
 */
final class ContinuityResolver
{
    /**
     * Block reasons continuity will NOT override.
     *
     * Deliberately an allow-list of what may be overridden's inverse: a new
     * reason code added to EligibilityEvaluator lands here by default and blocks,
     * rather than silently becoming overridable because nobody updated a list.
     */
    public const OVERRIDABLE = [
        EligibilityEvaluator::DAILY_VOLUME_CAP_REACHED,
        EligibilityEvaluator::OPEN_CASES_CAP_REACHED,
        EligibilityEvaluator::MESSAGE_AGING_BLOCK,
    ];

    /*
     * THE TWO NEW AXES ARE ABSENT FROM THAT LIST ON PURPOSE (Devin msg 2313 Q3:
     * "go with your rec, we can always adjust later, in that case if both blocked
     * then route them to a new provider").
     *
     * CATEGORY_NOT_ACCEPTED, VISIT_TYPE_NOT_ACCEPTED and SCHEDULING_LINK_MISSING
     * are clinical scope, not workload, so continuity does not beat them. A
     * doctor who has stopped taking peptides does not keep receiving peptide
     * check-ins from their own patients; that check-in falls through to the
     * refill-path mode and gets a new provider, which is exactly what Devin
     * asked for. The inverse allow-list above is what makes that automatic: a new
     * reason code blocks by default rather than becoming overridable because
     * nobody updated a list.
     */

    /**
     * The doctor this check-in should go back to, or null.
     *
     * Null means "continuity does not apply, route normally" in every case: not a
     * check-in, no prior doctor, or that doctor can no longer take it.
     */
    public function resolve(
        PatientCase $case,
        bool $requireRecordedLicensure = true,
        ?CaseRequirements $requirements = null,
    ): ?Clinician {
        if (! $case->isRefillRequest()) {
            return null;
        }

        $previous = $this->previousCase($case);

        if (! $previous || ! $previous->clinician) {
            return null;
        }

        $clinician = $previous->clinician;
        $clinician->loadMissing('acceptedCategories');
        $blocking  = $this->blockingReasons($clinician, $case, $requireRecordedLicensure, $requirements);

        if ($blocking !== []) {
            // Logged because "why did my check-in go to a different doctor" is
            // the first question this feature will generate. Ids only, no PHI.
            Log::info('Continuity of care not applied', [
                'case_id'          => $case->id,
                'prior_case_id'    => $previous->id,
                'clinician_id'     => $clinician->id,
                'blocking_reasons' => $blocking,
            ]);

            return null;
        }

        return $clinician;
    }

    /**
     * The case that establishes who "the original provider" is.
     *
     * COMPLETED, AND IT MUST HAVE PRODUCED A PRESCRIPTION (Devin msg 2246 agreed
     * this). Not merely the most recent case: a cancelled or abandoned one should
     * not create a claim on the patient, and neither should one still in flight.
     * The prescription is the evidence that this doctor actually made a clinical
     * decision for this patient.
     *
     * Scoped to the same PARTNER as well as the same patient. A patient can exist
     * under two storefronts, and a doctor's history with one of them is not a
     * reason to hand them a case belonging to another.
     */
    private function previousCase(PatientCase $case): ?PatientCase
    {
        if (! $case->patient_id) {
            return null;
        }

        return PatientCase::with('clinician')
            ->where('patient_id', $case->patient_id)
            ->where('partner_id', $case->partner_id)
            ->where('id', '!=', $case->id)
            ->where('status', PatientCase::STATUS_COMPLETED)
            ->whereNotNull('clinician_id')
            ->whereHas('casePrescriptions')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Reasons this doctor cannot take the case, minus the ones continuity may
     * override.
     *
     * Reuses EligibilityEvaluator rather than re-deriving the rules, so the
     * continuity path and the normal path cannot drift into disagreeing about
     * what "licensed" means. Workload is passed as uncapped on purpose: the caps
     * are the thing being overridden, so evaluating them here only to discard the
     * result would be theatre.
     *
     * @return string[]
     */
    private function blockingReasons(
        Clinician $clinician,
        PatientCase $case,
        bool $requireRecordedLicensure,
        ?CaseRequirements $requirements = null,
    ): array {
        $state = $case->patient_state ?: $case->patient?->state;

        $reasons = EligibilityEvaluator::evaluate(
            $clinician,
            $state,
            [
                'currentDailyVolume' => 0,
                'currentOpenCases'   => 0,
                'maxDailyVolume'     => null,
                'maxOpenCases'       => null,
                'oldestUnansweredMessageAgeHours' => null,
            ],
            null,
            $requireRecordedLicensure,
            // Category and visit type are evaluated here too, and are NOT in
            // OVERRIDABLE, so they block a check-in exactly as they block a first
            // visit. Passing null would silently exempt continuity from the two
            // new axes, which is the opposite of Devin's Q3 answer.
            $requirements,
        );

        $reasons = array_values(array_diff($reasons, self::OVERRIDABLE));

        /*
         * STOREFRONT GRANT. `hasAccessToPartner()` arrives with the
         * compliance/rxos-laws branch and does not exist here yet, so this checks
         * for it rather than hard-calling it. When that branch merges the gate
         * starts applying with no further change; until then there is no grant
         * model in the system for it to consult.
         *
         * Not a silent skip: if the method is missing, no grant model exists at
         * all, so there is nothing to enforce and nothing being bypassed.
         */
        if (method_exists($clinician, 'hasAccessToPartner')
            && ! $clinician->hasAccessToPartner($case->partner_id)) {
            $reasons[] = 'PARTNER_ACCESS_NOT_GRANTED';
        }

        return $reasons;
    }
}
