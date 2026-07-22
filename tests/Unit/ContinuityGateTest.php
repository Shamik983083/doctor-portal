<?php

namespace Tests\Unit;

use App\Models\Clinician;
use App\Services\Routing\EligibilityEvaluator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Which gates continuity of care may override (Devin msg 2246: "Block the first
 * 2 override the daily case cap").
 *
 * The rule underneath his answer: continuity beats WORKLOAD gates and never
 * beats LEGAL or ACCESS ones. These tests pin that split, because the whole
 * feature turns on it and getting it backwards means a prescription written by
 * a doctor who cannot lawfully write it.
 *
 * Database-free like the other routing suites. The eligibility evaluator is
 * pure, and the override list is a constant, so both can be asserted directly.
 */
class ContinuityGateTest extends TestCase
{
    /** The override policy. Public on the resolver because it IS the policy. */
    private function overridable(): array
    {
        return \App\Services\Routing\ContinuityResolver::OVERRIDABLE;
    }

    public function test_workload_gates_are_overridable(): void
    {
        $overridable = $this->overridable();

        $this->assertContains(EligibilityEvaluator::DAILY_VOLUME_CAP_REACHED, $overridable,
            'Devin msg 2246 called this one explicitly: continuity beats the daily cap');
        $this->assertContains(EligibilityEvaluator::OPEN_CASES_CAP_REACHED, $overridable);
        $this->assertContains(EligibilityEvaluator::MESSAGE_AGING_BLOCK, $overridable);
    }

    /**
     * THE ONE THAT MUST NEVER CHANGE WITHOUT A DELIBERATE DECISION.
     *
     * Licensure is a legal gate. A check-in from a patient whose doctor has since
     * lost their licence in that state routes to somebody else or waits; it does
     * not go back to the original doctor on the grounds that they know the
     * patient.
     */
    public function test_legal_and_access_gates_are_never_overridable(): void
    {
        $overridable = $this->overridable();

        $mustBlock = [
            EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING,
            EligibilityEvaluator::LICENSURE_NOT_RECORDED,
            EligibilityEvaluator::PROVIDER_NOT_ACTIVE,
            EligibilityEvaluator::PROVIDER_UNAVAILABLE,
        ];

        foreach ($mustBlock as $reason) {
            $this->assertNotContains($reason, $overridable,
                "{$reason} is a legal or access gate and continuity must never override it");
        }
    }

    /**
     * The list is an inverse allow-list on purpose: a NEW reason code added to
     * EligibilityEvaluator must default to blocking, not to being overridable
     * because nobody remembered to update a list.
     */
    public function test_a_new_reason_code_defaults_to_blocking(): void
    {
        $all = (new ReflectionClass(EligibilityEvaluator::class))->getConstants();

        $reasonCodes = array_filter(
            $all,
            fn ($v, $k) => is_string($v) && $v === $k,
            ARRAY_FILTER_USE_BOTH
        );

        $this->assertNotEmpty($reasonCodes);

        $unknown = array_diff(array_values($reasonCodes), $this->overridable());

        $this->assertNotEmpty($unknown,
            'blocking must be the default; if every reason is overridable the gate does nothing');
    }

    /**
     * An unlicensed doctor is blocked even with every cap removed, which is the
     * exact shape of the call ContinuityResolver makes (uncapped workload, so
     * only the non-workload gates can fire).
     */
    public function test_uncapped_workload_still_blocks_on_licence(): void
    {
        $clinician = new Clinician([
            'status'          => 'active',
            'is_available'    => true,
            'licensed_states' => [['state' => 'CA']],
        ]);

        $reasons = EligibilityEvaluator::evaluate(
            $clinician,
            'NY',
            [
                'currentDailyVolume' => 0,
                'currentOpenCases'   => 0,
                'maxDailyVolume'     => null,
                'maxOpenCases'       => null,
                'oldestUnansweredMessageAgeHours' => null,
            ],
        );

        $this->assertContains(EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING, $reasons);
        $this->assertEmpty(array_intersect($reasons, [
            EligibilityEvaluator::DAILY_VOLUME_CAP_REACHED,
            EligibilityEvaluator::OPEN_CASES_CAP_REACHED,
        ]), 'uncapped workload must not itself produce a cap block');
    }

    /** An unknown patient state blocks, rather than passing as "probably fine". */
    public function test_an_unknown_patient_state_blocks(): void
    {
        $clinician = new Clinician([
            'status'          => 'active',
            'is_available'    => true,
            'licensed_states' => [['state' => 'CA']],
        ]);

        foreach ([null, '', '  '] as $state) {
            $reasons = EligibilityEvaluator::evaluate($clinician, $state, [
                'currentDailyVolume' => 0,
                'currentOpenCases'   => 0,
                'maxDailyVolume'     => null,
                'maxOpenCases'       => null,
                'oldestUnansweredMessageAgeHours' => null,
            ]);

            $this->assertContains(EligibilityEvaluator::RESIDENCE_STATE_LICENSE_MISSING, $reasons);
        }
    }
}
