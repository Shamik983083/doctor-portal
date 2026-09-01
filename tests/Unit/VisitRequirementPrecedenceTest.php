<?php

namespace Tests\Unit;

use App\Models\StateVisitRequirement;
use App\Services\Routing\StateVisitRequirementResolver;
use Tests\TestCase;

/**
 * Which rule in the state matrix governs a case (Devin msg 2313 Q4).
 *
 * The rules being pinned:
 *   - OFFERING beats CATEGORY beats ALL;
 *   - a scoped rule with no target matches NOTHING rather than widening to ALL;
 *   - a tie at the same specificity resolves to the stricter rule;
 *   - a rule for another category or product does not leak onto this case.
 *
 * Database-free: pickWinner() is pure, and the rules are unsaved models with the
 * state filtering already applied by the caller.
 */
class VisitRequirementPrecedenceTest extends TestCase
{
    private function rule(string $scope, bool $sync, ?int $categoryId = null, ?int $offeringId = null): StateVisitRequirement
    {
        return new StateVisitRequirement([
            'scope_type'           => $scope,
            'offering_category_id' => $categoryId,
            'offering_id'          => $offeringId,
            'state'                => 'TN',
            'requires_synchronous' => $sync,
        ]);
    }

    public function test_offering_rule_beats_category_rule(): void
    {
        $winner = StateVisitRequirementResolver::pickWinner(
            [
                $this->rule(StateVisitRequirement::SCOPE_CATEGORY, true, categoryId: 1),
                $this->rule(StateVisitRequirement::SCOPE_OFFERING, false, offeringId: 10),
            ],
            [10],
            [1],
        );

        $this->assertNotNull($winner);
        $this->assertSame(StateVisitRequirement::SCOPE_OFFERING, $winner->scope_type);
        $this->assertFalse((bool) $winner->requires_synchronous);
    }

    public function test_category_rule_beats_blanket_rule(): void
    {
        $winner = StateVisitRequirementResolver::pickWinner(
            [
                $this->rule(StateVisitRequirement::SCOPE_ALL, true),
                $this->rule(StateVisitRequirement::SCOPE_CATEGORY, false, categoryId: 1),
            ],
            [10],
            [1],
        );

        $this->assertSame(StateVisitRequirement::SCOPE_CATEGORY, $winner->scope_type);
        $this->assertFalse((bool) $winner->requires_synchronous);
    }

    public function test_rule_for_another_category_does_not_apply(): void
    {
        $winner = StateVisitRequirementResolver::pickWinner(
            [$this->rule(StateVisitRequirement::SCOPE_CATEGORY, true, categoryId: 99)],
            [10],
            [1],
        );

        $this->assertNull($winner);
    }

    public function test_scoped_rule_with_no_target_matches_nothing(): void
    {
        // Malformed: a CATEGORY rule with no category. It must not fall back to
        // behaving like a blanket rule, which would silently widen something
        // somebody meant to narrow.
        $winner = StateVisitRequirementResolver::pickWinner(
            [$this->rule(StateVisitRequirement::SCOPE_CATEGORY, true, categoryId: null)],
            [10],
            [1],
        );

        $this->assertNull($winner);
    }

    public function test_tie_resolves_to_the_stricter_rule(): void
    {
        // Two blanket rules disagreeing is a configuration mistake, and the safe
        // reading of a legal requirement is the one that holds a live visit.
        $winner = StateVisitRequirementResolver::pickWinner(
            [
                $this->rule(StateVisitRequirement::SCOPE_ALL, false),
                $this->rule(StateVisitRequirement::SCOPE_ALL, true),
            ],
            [10],
            [1],
        );

        $this->assertTrue((bool) $winner->requires_synchronous);
    }

    public function test_tie_is_order_independent(): void
    {
        $winner = StateVisitRequirementResolver::pickWinner(
            [
                $this->rule(StateVisitRequirement::SCOPE_ALL, true),
                $this->rule(StateVisitRequirement::SCOPE_ALL, false),
            ],
            [10],
            [1],
        );

        $this->assertTrue((bool) $winner->requires_synchronous);
    }

    public function test_no_rules_means_no_winner(): void
    {
        // The caller then falls back to the product's own video states, which is
        // what keeps existing configuration working on the day the matrix ships
        // empty.
        $this->assertNull(StateVisitRequirementResolver::pickWinner([], [10], [1]));
    }
}
