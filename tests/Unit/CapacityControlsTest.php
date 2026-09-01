<?php

namespace Tests\Unit;

use App\Models\Clinician;
use App\Services\Routing\EligibilityEvaluator;
use Tests\TestCase;

/**
 * Per-doctor and admin-set capacity controls (Devin msgs 2248/2250).
 *
 * The rules being pinned:
 *   - the open-case cap is its OWN limit, not the daily cap reused (finding 1);
 *   - "books full", the new-case cap and the two policy criteria block NEW cases
 *     and NEVER a check-in;
 *   - a check-in is exempt from all of them, so continuity is never held back.
 *
 * Database-free: EligibilityEvaluator is pure, so an unsaved Clinician plus a
 * workload array is the whole fixture.
 */
class CapacityControlsTest extends TestCase
{
    private function clinician(array $attrs = []): Clinician
    {
        return new Clinician(array_merge([
            'status'          => 'active',
            'is_available'    => true,
            'licensed_states' => [['state' => 'CA']],
        ], $attrs));
    }

    /** A workload bag that passes every gate unless a test overrides a field. */
    private function workload(array $over = []): array
    {
        return array_merge([
            'maxDailyVolume'                  => null,
            'currentDailyVolume'              => 0,
            'maxOpenCases'                    => null,
            'currentOpenCases'                => 0,
            'oldestUnansweredMessageAgeHours' => null,
            'isNewCase'                       => true,
            'acceptingNewCases'               => true,
            'maxDailyNewCases'                => null,
            'currentDailyNewCases'            => 0,
            'maxDelayedCases'                 => null,
            'currentDelayedCases'             => 0,
            'maxAwaitingReply'                => null,
            'currentAwaitingReply'            => 0,
        ], $over);
    }

    private function evaluate(array $workloadOver): array
    {
        return EligibilityEvaluator::evaluate($this->clinician(), 'CA', $this->workload($workloadOver));
    }

    /**
     * FINDING 1. The open-case cap and the daily cap are independent now. A
     * doctor at their open-case limit but who has taken nothing today is blocked
     * on OPEN, not on daily, which is the bug that used to permanently strand a
     * doctor whose one number gated both.
     */
    public function test_open_cap_and_daily_cap_are_independent(): void
    {
        $onlyOpen = $this->evaluate([
            'maxOpenCases'       => 10,
            'currentOpenCases'   => 10,
            'maxDailyVolume'     => 20,
            'currentDailyVolume' => 0,
        ]);
        $this->assertContains(EligibilityEvaluator::OPEN_CASES_CAP_REACHED, $onlyOpen);
        $this->assertNotContains(EligibilityEvaluator::DAILY_VOLUME_CAP_REACHED, $onlyOpen);

        $onlyDaily = $this->evaluate([
            'maxOpenCases'       => 10,
            'currentOpenCases'   => 0,
            'maxDailyVolume'     => 20,
            'currentDailyVolume' => 20,
        ]);
        $this->assertContains(EligibilityEvaluator::DAILY_VOLUME_CAP_REACHED, $onlyDaily);
        $this->assertNotContains(EligibilityEvaluator::OPEN_CASES_CAP_REACHED, $onlyDaily);
    }

    public function test_books_full_blocks_a_new_case(): void
    {
        $this->assertContains(
            EligibilityEvaluator::NOT_ACCEPTING_NEW_CASES,
            $this->evaluate(['acceptingNewCases' => false])
        );
    }

    /**
     * THE POINT OF THE WHOLE FEATURE (Devin msg 2250 B). Books full stops new
     * patients but a returning patient still reaches their doctor. With
     * isNewCase=false, none of the new-case blocks may fire.
     */
    public function test_a_check_in_is_exempt_from_every_new_case_block(): void
    {
        $reasons = $this->evaluate([
            'isNewCase'            => false,
            'acceptingNewCases'    => false,
            'maxDailyNewCases'     => 5,
            'currentDailyNewCases' => 99,
            'maxDelayedCases'      => 1,
            'currentDelayedCases'  => 99,
            'maxAwaitingReply'     => 1,
            'currentAwaitingReply' => 99,
        ]);

        foreach ([
            EligibilityEvaluator::NOT_ACCEPTING_NEW_CASES,
            EligibilityEvaluator::DAILY_NEW_CASE_CAP_REACHED,
            EligibilityEvaluator::DELAYED_CASES_THRESHOLD,
            EligibilityEvaluator::AWAITING_REPLY_THRESHOLD,
        ] as $reason) {
            $this->assertNotContains($reason, $reasons,
                "a check-in must never carry {$reason}");
        }

        $this->assertSame([], $reasons, 'an otherwise-clean check-in passes every gate');
    }

    /**
     * A check-in is still subject to the LEGAL gates. Exempt from capacity is not
     * exempt from licensure.
     */
    public function test_a_check_in_is_still_blocked_on_licence(): void
    {
        $reasons = EligibilityEvaluator::evaluate(
            $this->clinician(['licensed_states' => [['state' => 'CA']]]),
            'NY',
            $this->workload(['isNewCase' => false])
        );

        $this->assertContains(EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING, $reasons);
    }

    public function test_the_daily_new_case_cap_blocks_a_new_case(): void
    {
        $this->assertContains(
            EligibilityEvaluator::DAILY_NEW_CASE_CAP_REACHED,
            $this->evaluate(['maxDailyNewCases' => 5, 'currentDailyNewCases' => 5])
        );
    }

    public function test_the_delayed_and_awaiting_reply_criteria_block_a_new_case(): void
    {
        $this->assertContains(
            EligibilityEvaluator::DELAYED_CASES_THRESHOLD,
            $this->evaluate(['maxDelayedCases' => 3, 'currentDelayedCases' => 3])
        );

        $this->assertContains(
            EligibilityEvaluator::AWAITING_REPLY_THRESHOLD,
            $this->evaluate(['maxAwaitingReply' => 2, 'currentAwaitingReply' => 2])
        );
    }

    /** An unset criterion (null max) does nothing, even with a high current count. */
    public function test_unset_criteria_do_not_block(): void
    {
        $this->assertSame([], $this->evaluate([
            'maxDailyNewCases'     => null,
            'currentDailyNewCases' => 50,
            'maxDelayedCases'      => null,
            'currentDelayedCases'  => 50,
            'maxAwaitingReply'     => null,
            'currentAwaitingReply' => 50,
        ]));
    }
}
