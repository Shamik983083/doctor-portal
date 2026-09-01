<?php

namespace App\Services;

use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\PatientCase;
use App\Models\Questionnaire;

/**
 * Resolves which questionnaire should be used for a refill (check-in) case.
 *
 * Resolution order (most-specific wins):
 *   1. Per-offering check-in questionnaire — an offering attached to the case
 *      has a linked questionnaire whose purpose is 'check_in'.
 *   2. Category default — the offering's category has check_in_questionnaire_id set.
 *   3. Initial intake fallback — the offering's own clinical questionnaire (same
 *      as a first visit). Refills never break if nothing is configured.
 *
 * Returns null when no questionnaire can be resolved at all (partner has no
 * questionnaires attached to the case's offerings).
 */
class CheckInQuestionnaireResolver
{
    /**
     * Resolve the check-in questionnaire for the given refill case.
     *
     * Callers should only invoke this when $case->isRefillRequest() is true.
     * Calling it on a first-visit case is harmless but returns the clinical
     * questionnaire, which is the same as the normal path.
     */
    public function resolve(PatientCase $case): ?Questionnaire
    {
        $case->loadMissing([
            'caseOfferings.offering.questionnaires',
            'caseOfferings.offering.category.checkInQuestionnaire',
        ]);

        foreach ($case->caseOfferings as $caseOffering) {
            $offering = $caseOffering->offering;
            if (! $offering) {
                continue;
            }

            // 1. Per-offering check-in questionnaire (linked via offering_questionnaire pivot).
            $perOffering = $offering->questionnaires
                ->firstWhere('purpose', 'check_in');

            if ($perOffering && $perOffering->is_active) {
                return $perOffering;
            }

            // 2. Category default check-in questionnaire.
            $categoryQ = $offering->category?->checkInQuestionnaire;
            if ($categoryQ && $categoryQ->is_active) {
                return $categoryQ;
            }

            // 3. Fallback: clinical questionnaire (initial intake).
            $fallback = $offering->questionnaires
                ->where('purpose', 'clinical')
                ->where('is_active', true)
                ->first();

            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }
}
