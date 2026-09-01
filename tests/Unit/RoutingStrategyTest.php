<?php

namespace Tests\Unit;

use App\Services\Routing\ProviderCandidate;
use App\Services\Routing\RoutingMode;
use App\Services\Routing\RoutingStrategy;
use App\Services\Routing\RoutingWeights;
use Tests\TestCase;

/**
 * The routing strategy, ported from MA-DOCPORTAL. Pure, so fully testable with no
 * database, which matters here because this repo has one model factory.
 *
 * This is the code that decides which doctor sees which patient. A subtle error is
 * not a display bug, so the properties MA relies on are pinned explicitly:
 * determinism, hard blocks never being selected, and failing closed.
 */
class RoutingStrategyTest extends TestCase
{
    private function candidate(int $id, array $overrides = []): ProviderCandidate
    {
        return new ProviderCandidate(
            providerId:            $id,
            eligible:              $overrides['eligible'] ?? true,
            rejectionReasons:      $overrides['reasons'] ?? [],
            openGreenCases:        $overrides['green'] ?? 0,
            openYellowCases:       $overrides['yellow'] ?? 0,
            openRedCases:          $overrides['red'] ?? 0,
            unansweredMessages:    $overrides['msgs'] ?? 0,
            messagesOver12Hours:   $overrides['old'] ?? 0,
            medianDecisionMinutes: $overrides['median'] ?? 0.0,
            currentDailyVolume:    $overrides['today'] ?? 0,
            maxDailyVolume:        $overrides['maxDaily'] ?? null,
            maxOpenCases:          $overrides['maxOpen'] ?? null,
            openCases:             $overrides['open'] ?? 0,
            weightAllocation:      $overrides['weight'] ?? 1.0,
            priority:              $overrides['priority'] ?? 0,
        );
    }

    private function weights(): RoutingWeights
    {
        return new RoutingWeights();
    }

    /* ---------------------------------------------------------- hard blocks */

    public function test_a_provider_at_their_daily_cap_is_hard_blocked(): void
    {
        $atCap = $this->candidate(1, ['maxDaily' => 5, 'today' => 5]);
        $under = $this->candidate(2, ['maxDaily' => 5, 'today' => 4]);

        $this->assertTrue(RoutingStrategy::isHardBlocked($atCap), 'at the cap means full, not one more is fine');
        $this->assertFalse(RoutingStrategy::isHardBlocked($under));
    }

    public function test_a_null_cap_means_uncapped_not_zero(): void
    {
        $busy = $this->candidate(1, ['maxDaily' => null, 'today' => 9999, 'maxOpen' => null, 'open' => 9999]);

        $this->assertFalse(RoutingStrategy::isHardBlocked($busy),
            'null must mean uncapped; conflating it with a cap of 0 would block everyone');
    }

    public function test_a_hard_blocked_provider_is_never_selected_by_any_mode(): void
    {
        $blocked = $this->candidate(1, ['eligible' => false, 'reasons' => ['NO_LICENSE']]);

        foreach ([RoutingMode::PRIORITY, RoutingMode::ROUND_ROBIN, RoutingMode::WEIGHTED, RoutingMode::INTELLIGENT] as $mode) {
            $out = RoutingStrategy::select($mode, [$blocked], $this->weights(), ['roundRobinCursor' => null]);
            $this->assertSame(RoutingStrategy::NONE, $out['kind'], "mode {$mode} must not select a hard-blocked provider");
        }
    }

    public function test_a_blocked_provider_scores_infinity(): void
    {
        $blocked = $this->candidate(1, ['eligible' => false]);

        $this->assertFalse(is_finite(RoutingStrategy::score($blocked, $this->weights())));
    }

    /* --------------------------------------------------------------- modes */

    public function test_provider_pool_never_assigns_even_when_everyone_is_eligible(): void
    {
        $out = RoutingStrategy::select(
            RoutingMode::PROVIDER_POOL,
            [$this->candidate(1), $this->candidate(2)],
            $this->weights(),
        );

        $this->assertSame(RoutingStrategy::POOL, $out['kind']);
        $this->assertArrayNotHasKey('providerId', $out);
    }

    public function test_round_robin_rotates_and_wraps(): void
    {
        $c = [$this->candidate(1), $this->candidate(2), $this->candidate(3)];
        $w = $this->weights();

        $this->assertSame(1, RoutingStrategy::select(RoutingMode::ROUND_ROBIN, $c, $w, ['roundRobinCursor' => null])['providerId'],
            'no cursor starts at the head');
        $this->assertSame(2, RoutingStrategy::select(RoutingMode::ROUND_ROBIN, $c, $w, ['roundRobinCursor' => 1])['providerId']);
        $this->assertSame(3, RoutingStrategy::select(RoutingMode::ROUND_ROBIN, $c, $w, ['roundRobinCursor' => 2])['providerId']);
        $this->assertSame(1, RoutingStrategy::select(RoutingMode::ROUND_ROBIN, $c, $w, ['roundRobinCursor' => 3])['providerId'],
            'past the tail wraps to the head');
        $this->assertSame(1, RoutingStrategy::select(RoutingMode::ROUND_ROBIN, $c, $w, ['roundRobinCursor' => 99])['providerId'],
            'a cursor pointing at someone no longer eligible still wraps rather than stalling');
    }

    public function test_weighted_picks_the_provider_furthest_below_their_share(): void
    {
        // Same volume, different weights: the one with more headroom wins.
        $a = $this->candidate(1, ['weight' => 1.0, 'today' => 4]);   // ratio 4.0
        $b = $this->candidate(2, ['weight' => 4.0, 'today' => 4]);   // ratio 1.0

        $this->assertSame(2, RoutingStrategy::select(RoutingMode::WEIGHTED, [$a, $b], $this->weights())['providerId']);
    }

    public function test_a_zero_weight_provider_is_excluded_from_weighted_routing(): void
    {
        $zero = $this->candidate(1, ['weight' => 0.0]);
        $some = $this->candidate(2, ['weight' => 1.0, 'today' => 50]);

        $this->assertSame(2, RoutingStrategy::select(RoutingMode::WEIGHTED, [$zero, $some], $this->weights())['providerId'],
            'a zero weight must be excluded even when the alternative is far busier');

        $out = RoutingStrategy::select(RoutingMode::WEIGHTED, [$zero], $this->weights());
        $this->assertSame(RoutingStrategy::NONE, $out['kind'],
            'if zero weight is the only candidate, nobody is assigned rather than falling back');
    }

    public function test_intelligent_prefers_the_lighter_workload(): void
    {
        $busy  = $this->candidate(1, ['yellow' => 10]);
        $light = $this->candidate(2, ['green' => 1]);

        $this->assertSame(2, RoutingStrategy::select(RoutingMode::INTELLIGENT, [$busy, $light], $this->weights())['providerId']);
    }

    public function test_intelligent_weights_a_yellow_case_above_a_green_one(): void
    {
        // MA's defaults: green 1, yellow 3, red 2.
        $green  = $this->candidate(1, ['green' => 3]);
        $yellow = $this->candidate(2, ['yellow' => 3]);

        $this->assertLessThan(
            RoutingStrategy::score($yellow, $this->weights()),
            RoutingStrategy::score($green, $this->weights()),
        );
    }

    public function test_an_overdue_message_is_penalised_heavily(): void
    {
        // messageOver12HoursPenalty is 25, far above any single case weight, so one
        // overdue message should outweigh a meaningful pile of open work.
        $overdue = $this->candidate(1, ['old' => 1]);
        $loaded  = $this->candidate(2, ['green' => 10]);

        $this->assertGreaterThan(
            RoutingStrategy::score($loaded, $this->weights()),
            RoutingStrategy::score($overdue, $this->weights()),
        );
    }

    public function test_priority_mode_keeps_medaxis_existing_behaviour(): void
    {
        $low  = $this->candidate(7, ['priority' => 1]);
        $high = $this->candidate(2, ['priority' => 9]);

        $this->assertSame(7, RoutingStrategy::select(RoutingMode::PRIORITY, [$high, $low], $this->weights())['providerId'],
            'lowest priority rank wins regardless of id order');
    }

    /* ------------------------------------------------- determinism + safety */

    public function test_selection_is_deterministic_regardless_of_input_order(): void
    {
        $a = $this->candidate(1, ['green' => 2]);
        $b = $this->candidate(2, ['green' => 2]);   // identical score, tie-break on id

        foreach ([RoutingMode::INTELLIGENT, RoutingMode::WEIGHTED, RoutingMode::PRIORITY] as $mode) {
            $first  = RoutingStrategy::select($mode, [$a, $b], $this->weights())['providerId'];
            $second = RoutingStrategy::select($mode, [$b, $a], $this->weights())['providerId'];

            $this->assertSame($first, $second, "mode {$mode} must not depend on candidate order");
            $this->assertSame(1, $first, 'ties break on the lower providerId');
        }
    }

    public function test_an_unknown_mode_fails_closed(): void
    {
        $out = RoutingStrategy::select('SOMETHING_ELSE', [$this->candidate(1)], $this->weights());

        $this->assertSame(RoutingStrategy::NONE, $out['kind'],
            'a malformed persisted policy must assign nobody rather than silently picking a default');
    }

    public function test_no_candidates_yields_none(): void
    {
        $this->assertSame(RoutingStrategy::NONE,
            RoutingStrategy::select(RoutingMode::INTELLIGENT, [], $this->weights())['kind']);
    }

    /**
     * The fail-open licensure gap, pinned so it cannot regress silently and so the
     * flag that closes it is proven to work.
     */
    public function test_blank_licensure_passes_by_default_and_blocks_when_required(): void
    {
        $doctor = new \App\Models\Clinician([
            'status' => 'active', 'is_available' => true, 'licensed_states' => [],
        ]);

        $workload = [
            'maxDailyVolume' => null, 'currentDailyVolume' => 0,
            'maxOpenCases' => null, 'currentOpenCases' => 0,
            'oldestUnansweredMessageAgeHours' => null,
        ];

        $permissive = \App\Services\Routing\EligibilityEvaluator::evaluate($doctor, 'TN', $workload, null, false);
        $this->assertSame([], $permissive,
            'default keeps current behaviour: blank licensure reads as licensed everywhere');

        $strict = \App\Services\Routing\EligibilityEvaluator::evaluate($doctor, 'TN', $workload, null, true);
        $this->assertContains(\App\Services\Routing\EligibilityEvaluator::LICENSURE_NOT_RECORDED, $strict,
            'with the flag on, blank licensure is a hard block');
    }

    public function test_an_unknown_patient_state_always_blocks(): void
    {
        $doctor = new \App\Models\Clinician([
            'status' => 'active', 'is_available' => true,
            'licensed_states' => [['state' => 'TN']],
        ]);

        $workload = [
            'maxDailyVolume' => null, 'currentDailyVolume' => 0,
            'maxOpenCases' => null, 'currentOpenCases' => 0,
            'oldestUnansweredMessageAgeHours' => null,
        ];

        $reasons = \App\Services\Routing\EligibilityEvaluator::evaluate($doctor, null, $workload);

        $this->assertContains(
            \App\Services\Routing\EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING,
            $reasons,
            'not knowing where the patient is must block, not pass'
        );
    }

    public function test_weight_overrides_are_taken_but_unknown_keys_are_ignored(): void
    {
        $w = new RoutingWeights(['greenCase' => 10, 'notARealWeight' => 999]);

        $this->assertSame(10.0, $w->get('greenCase'));
        $this->assertSame(3.0, $w->get('yellowCase'), 'unspecified coefficients keep MA\'s default');
        $this->assertArrayNotHasKey('notARealWeight', $w->toArray(),
            'a typo in a stored policy must not introduce a coefficient nothing reads');
    }
}
