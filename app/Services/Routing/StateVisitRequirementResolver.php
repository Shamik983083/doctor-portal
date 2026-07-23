<?php

namespace App\Services\Routing;

use App\Models\Offering;
use App\Models\StateVisitRequirement;

/**
 * Does this case have to be a live video visit?
 *
 * Devin msg 2313 Q4: "yes MA's is [the source of truth] and we need to adjust as
 * super admin as laws change frequently. the Sync is determined by states."
 *
 * TWO SOURCES, IN ORDER.
 *
 *  1. `state_visit_requirements`, the super-admin matrix ported from
 *     MA-DOCPORTAL. Most specific scope wins: OFFERING beats CATEGORY beats ALL.
 *  2. `offerings.video_required_states`, the per-offering list MEDAXIS already
 *     had and which is already populated on real offerings. Used only when NO
 *     matrix rule matched, so existing configuration keeps working and nobody has
 *     to re-enter it. When a matrix rule does match, the matrix wins outright,
 *     including when it says a state does NOT require video: that is how a super
 *     admin turns an old per-offering flag off after a law changes.
 *
 * ANY OFFERING FORCES THE WHOLE CASE SYNCHRONOUS. A case carrying three products
 * where one requires video is a video visit. Splitting a case by product is not
 * something the rest of the system can do, and the conservative direction on a
 * legal requirement is the one that holds a live visit.
 *
 * UNKNOWN STATE RESOLVES ASYNCHRONOUS, and that is safe here only because the
 * state axis has already blocked the case by then: EligibilityEvaluator refuses
 * every provider when the patient state is missing, so nothing is routed on the
 * strength of this answer.
 */
final class StateVisitRequirementResolver
{
    /**
     * @param  int[] $offeringIds
     * @param  int[] $categoryIds
     */
    public function resolve(?string $state, array $offeringIds, array $categoryIds): string
    {
        $state = strtoupper(trim((string) $state));

        if ($state === '') {
            return VisitType::ASYNCHRONOUS;
        }

        $rules = StateVisitRequirement::query()
            ->forState($state)
            ->inForce()
            ->get();

        $winner = self::pickWinner($rules, $offeringIds, $categoryIds);

        if ($winner !== null) {
            return $winner->requires_synchronous ? VisitType::SYNCHRONOUS : VisitType::ASYNCHRONOUS;
        }

        return $this->legacyOfferingFallback($state, $offeringIds);
    }

    /**
     * Which rule governs, out of the ones already narrowed to this state.
     *
     * Pure and static so the precedence logic can be tested without a database:
     * everything else in this class is I/O, and this is the part with an opinion.
     *
     * MOST SPECIFIC WINS. On a tie of specificity, the rule REQUIRING a
     * synchronous visit wins. Two rules at the same scope disagreeing is a
     * configuration mistake, and the safe reading of a legal requirement is the
     * stricter one.
     *
     * @param  iterable<StateVisitRequirement> $rules
     * @param  int[] $offeringIds
     * @param  int[] $categoryIds
     */
    public static function pickWinner(iterable $rules, array $offeringIds, array $categoryIds): ?StateVisitRequirement
    {
        $winner = null;

        foreach ($rules as $rule) {
            if (! self::applies($rule, $offeringIds, $categoryIds)) {
                continue;
            }

            if ($winner === null
                || $rule->specificity() > $winner->specificity()
                || ($rule->specificity() === $winner->specificity()
                    && $rule->requires_synchronous
                    && ! $winner->requires_synchronous)) {
                $winner = $rule;
            }
        }

        return $winner;
    }

    /**
     * Does this rule cover the thing being prescribed?
     *
     * A CATEGORY or OFFERING rule with a null target is malformed and matches
     * nothing rather than falling back to ALL, which would silently widen a rule
     * somebody meant to narrow.
     */
    private static function applies(StateVisitRequirement $rule, array $offeringIds, array $categoryIds): bool
    {
        return match ($rule->scope_type) {
            StateVisitRequirement::SCOPE_ALL      => true,
            StateVisitRequirement::SCOPE_CATEGORY => $rule->offering_category_id !== null
                && in_array((int) $rule->offering_category_id, $categoryIds, true),
            StateVisitRequirement::SCOPE_OFFERING => $rule->offering_id !== null
                && in_array((int) $rule->offering_id, $offeringIds, true),
            default => false,
        };
    }

    /**
     * The pre-matrix behaviour: `offerings.video_required_states`.
     *
     * Kept so the video flags already set on real offerings keep working the day
     * the matrix ships empty. This is the only reason nothing changes on deploy.
     */
    private function legacyOfferingFallback(string $state, array $offeringIds): string
    {
        if ($offeringIds === []) {
            return VisitType::ASYNCHRONOUS;
        }

        $offerings = Offering::whereIn('id', $offeringIds)->get();

        foreach ($offerings as $offering) {
            if ($offering->isVideoRequiredInState($state)) {
                return VisitType::SYNCHRONOUS;
            }
        }

        return VisitType::ASYNCHRONOUS;
    }
}
