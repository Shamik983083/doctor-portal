<?php

namespace Tests\Unit;

use App\Models\PatientCase;
use Tests\TestCase;

/**
 * How a case is recognised as a re-bill / check-in (Devin msgs 2244, 2246).
 *
 * The decision was: build the explicit flag, keep visit_type as a fallback, and
 * never infer from patient history. These pin all three.
 *
 * Database-free. `isRefillRequest()` reads two attributes and does no I/O, so
 * unsaved models are enough and no factory is needed.
 */
class RefillDetectionTest extends TestCase
{
    private function case(array $attributes): PatientCase
    {
        return new PatientCase($attributes);
    }

    public function test_a_plain_case_is_not_a_check_in(): void
    {
        $this->assertFalse($this->case([])->isRefillRequest(),
            'the default must be first visit, since that is the direction that fails safely');

        $this->assertFalse($this->case(['visit_type' => 'Initial consultation'])->isRefillRequest());
    }

    public function test_the_explicit_flag_wins(): void
    {
        $this->assertTrue($this->case(['is_refill' => true])->isRefillRequest());

        // Flag set, visit_type saying something else entirely: the flag is the
        // contract, the text is only a fallback for partners not sending it.
        $this->assertTrue($this->case([
            'is_refill'  => true,
            'visit_type' => 'Initial consultation',
        ])->isRefillRequest());
    }

    public function test_the_visit_type_fallback_catches_partners_not_sending_the_flag(): void
    {
        foreach (['refill', 'Refill', 'REFILL REQUEST', 'monthly check-in', 'Check In', 'rebill', 're-bill'] as $text) {
            $this->assertTrue(
                $this->case(['visit_type' => $text])->isRefillRequest(),
                "visit_type '{$text}' should be read as a check-in"
            );
        }
    }

    /**
     * THE FAILURE THAT MATTERS. A first visit misread as a check-in gets handed
     * to a doctor on the strength of a history that does not apply to it. So the
     * fallback stays a short list of unambiguous words rather than anything
     * clever.
     */
    public function test_the_fallback_does_not_over_match(): void
    {
        foreach (['new patient', 'initial', 'consultation', 'follow up', 'urgent', ''] as $text) {
            $this->assertFalse(
                $this->case(['visit_type' => $text])->isRefillRequest(),
                "visit_type '{$text}' must NOT be read as a check-in"
            );
        }
    }

    public function test_a_null_visit_type_is_handled(): void
    {
        $this->assertFalse($this->case(['visit_type' => null])->isRefillRequest());
    }

    /**
     * Reporting reads the column, never the fallback, so the two scopes always
     * sum to the total. A substring match over free text would not add up, and a
     * reporting number that does not add up is worse than no number.
     */
    public function test_the_reporting_scopes_read_the_column_not_the_text(): void
    {
        $refills = strtolower(PatientCase::refills()->toSql());
        $first   = strtolower(PatientCase::firstVisits()->toSql());

        $this->assertStringContainsString('is_refill', $refills);
        $this->assertStringContainsString('is_refill', $first);

        foreach ([$refills, $first] as $sql) {
            $this->assertStringNotContainsString('visit_type', $sql,
                'the reporting scopes must not consult the free-text fallback');
            $this->assertStringNotContainsString('like', $sql);
        }
    }
}
