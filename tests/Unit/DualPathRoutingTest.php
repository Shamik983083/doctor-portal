<?php

namespace Tests\Unit;

use App\Models\RoutingPolicy;
use App\Services\Routing\RoutingMode;
use Tests\TestCase;

/**
 * Separate routing for new cases and check-ins (Devin msg 2308).
 *
 * "THERE ARE 2 CHECKS: 1. NEW CLIENTS 2. REFILL CLIENTS. WE NEED TO HAVE CAPS AND
 * ROUTING FOR EACH."
 *
 * The rules being pinned:
 *   - each path reads its own mode;
 *   - a version with no per-path config reads through to the `mode` column, which
 *     is what keeps v1 (PRIORITY) meaning exactly what it always meant;
 *   - INTELLIGENT is no longer offered but is still executable, so a stored
 *     policy naming it does not fail closed into assigning nobody;
 *   - pool criteria read back as numbers, with anything non-numeric as null
 *     rather than as zero, since zero is a real threshold.
 *
 * Database-free: an unsaved RoutingPolicy carries its own config.
 */
class DualPathRoutingTest extends TestCase
{
    private function policy(string $mode, array $config = []): RoutingPolicy
    {
        return new RoutingPolicy([
            'version' => 99,
            'mode'    => $mode,
            'config'  => $config,
            'status'  => RoutingPolicy::STATUS_DRAFT,
        ]);
    }

    public function test_each_path_reads_its_own_mode(): void
    {
        $policy = $this->policy(RoutingMode::PRIORITY, [
            'newMode'    => RoutingMode::ROUND_ROBIN,
            'refillMode' => RoutingMode::WEIGHTED,
        ]);

        $this->assertSame(RoutingMode::ROUND_ROBIN, $policy->modeForCase(true));
        $this->assertSame(RoutingMode::WEIGHTED, $policy->modeForCase(false));
    }

    public function test_missing_per_path_config_falls_back_to_the_column(): void
    {
        // v1 has no newMode or refillMode. Both paths must still be PRIORITY, or
        // shipping this silently re-routes every live case.
        $policy = $this->policy(RoutingMode::PRIORITY, [
            'intelligentWeights' => [],
            'providerWeights'    => [],
        ]);

        $this->assertSame(RoutingMode::PRIORITY, $policy->modeForCase(true));
        $this->assertSame(RoutingMode::PRIORITY, $policy->modeForCase(false));
    }

    public function test_empty_string_mode_falls_back_rather_than_routing_nowhere(): void
    {
        $policy = $this->policy(RoutingMode::WEIGHTED, ['newMode' => '']);

        $this->assertSame(RoutingMode::WEIGHTED, $policy->modeForCase(true));
    }

    public function test_intelligent_is_no_longer_selectable_but_is_still_executable(): void
    {
        // Retired on Devin msg 2313 Q5. Removing it from ALL takes it off the
        // admin screen; keeping it in ALL_STORED stops a stored policy naming it
        // from being read as a malformed mode that assigns nobody.
        $this->assertNotContains(RoutingMode::INTELLIGENT, RoutingMode::ALL);
        $this->assertContains(RoutingMode::INTELLIGENT, RoutingMode::ALL_STORED);
    }

    public function test_the_four_selectable_modes_are_the_ones_devin_listed(): void
    {
        $this->assertSame(
            [RoutingMode::PRIORITY, RoutingMode::ROUND_ROBIN, RoutingMode::WEIGHTED, RoutingMode::PROVIDER_POOL],
            RoutingMode::ALL,
        );
    }

    public function test_blank_licensure_blocking_is_now_the_default(): void
    {
        // Devin msg 2313. A version that says nothing about it blocks; only a
        // version explicitly setting false does not.
        $this->assertTrue($this->policy(RoutingMode::PRIORITY)->requireRecordedLicensure());
        $this->assertFalse(
            $this->policy(RoutingMode::PRIORITY, ['requireRecordedLicensure' => false])->requireRecordedLicensure()
        );
    }

    public function test_pool_criteria_read_back_as_numbers_and_nulls(): void
    {
        $policy = $this->policy(RoutingMode::PROVIDER_POOL, [
            'poolCriteria' => [
                'maxOutstandingCases' => 30,
                'maxOverdueCases'     => null,
                'overdueAfterHours'   => '48',
                'maxAwaitingReply'    => 'not a number',
            ],
        ]);

        $this->assertSame(30, $policy->poolCriterion('maxOutstandingCases'));
        $this->assertNull($policy->poolCriterion('maxOverdueCases'));
        $this->assertSame(48, $policy->poolCriterion('overdueAfterHours'));
        // Garbage reads as "off", never as 0, because 0 is a real threshold that
        // would block every doctor.
        $this->assertNull($policy->poolCriterion('maxAwaitingReply'));
        $this->assertNull($policy->poolCriterion('neverConfigured'));
    }
}
