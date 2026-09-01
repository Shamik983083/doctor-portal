<?php

namespace Tests\Unit;

use App\Models\PatientCase;
use App\Services\CaseElapsedTime;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies that CaseElapsedTime::active() correctly subtracts pause intervals
 * from wall-clock elapsed time.
 *
 * Uses SQLite in-memory via RefreshDatabase, which the base TestCase bootstraps.
 * The case_pause_intervals table must exist (Phase 1b migration).
 */
class CaseElapsedTimeTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /** A freshly-created case with no pauses returns raw elapsed time. */
    public function test_zero_pauses_returns_raw_elapsed(): void
    {
        $case = $this->makeCase(createdAt: now()->subMinutes(60));

        // No rows in case_pause_intervals → active = total
        $elapsed = CaseElapsedTime::active($case);

        // Allow 1-minute tolerance for test execution time
        $this->assertEqualsWithDelta(60, $elapsed, 1);
    }

    /** A case currently paused accumulates zero active time during the pause. */
    public function test_open_pause_stops_active_clock(): void
    {
        // Case was assigned 90 minutes ago; paused 30 minutes ago → 60 min active
        $case = $this->makeCase(assignedAt: now()->subMinutes(90));

        DB::table('case_pause_intervals')->insert([
            'case_id'    => $case->id,
            'paused_at'  => now()->subMinutes(30),
            'resumed_at' => null,
            'reason'     => 'awaiting client response',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $elapsed = CaseElapsedTime::active($case);

        $this->assertEqualsWithDelta(60, $elapsed, 1);
    }

    /** A closed pause interval is fully subtracted from active time. */
    public function test_closed_pause_is_subtracted(): void
    {
        // Case assigned 120 min ago; paused for 20 min (closed) → 100 min active
        $case = $this->makeCase(assignedAt: now()->subMinutes(120));

        DB::table('case_pause_intervals')->insert([
            'case_id'    => $case->id,
            'paused_at'  => now()->subMinutes(50),
            'resumed_at' => now()->subMinutes(30),  // 20-minute pause
            'reason'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $elapsed = CaseElapsedTime::active($case);

        $this->assertEqualsWithDelta(100, $elapsed, 1);
    }

    /** Multiple pause/resume cycles are each subtracted. */
    public function test_multiple_pauses_are_all_subtracted(): void
    {
        // Case assigned 200 min ago
        // Pause 1: 180–150 min ago → 30 min
        // Pause 2: 100–80 min ago  → 20 min
        // Total paused: 50 min → active = 150 min
        $case = $this->makeCase(assignedAt: now()->subMinutes(200));

        DB::table('case_pause_intervals')->insert([
            [
                'case_id'    => $case->id,
                'paused_at'  => now()->subMinutes(180),
                'resumed_at' => now()->subMinutes(150),
                'reason'     => 'first pause',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'case_id'    => $case->id,
                'paused_at'  => now()->subMinutes(100),
                'resumed_at' => now()->subMinutes(80),
                'reason'     => 'second pause',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $elapsed = CaseElapsedTime::active($case);

        $this->assertEqualsWithDelta(150, $elapsed, 2);
    }

    /** Helper: create a bare PatientCase row with timestamp overrides. */
    private function makeCase(?\Carbon\Carbon $createdAt = null, ?\Carbon\Carbon $assignedAt = null): PatientCase
    {
        $case = new PatientCase();
        $case->id         = DB::table('cases')->insertGetId([
            'uuid'         => \Illuminate\Support\Str::uuid(),
            'partner_id'   => 1,
            'patient_id'   => 1,
            'status'       => 'assigned',
            'hold_status'  => 0,
            'is_chargeable'=> 1,
            'is_refill'    => 0,
            'created_at'   => $createdAt ?? now()->subHour(),
            'updated_at'   => now(),
            'assigned_at'  => $assignedAt,
        ]);
        $case->created_at  = $createdAt ?? now()->subHour();
        $case->assigned_at = $assignedAt;

        return $case;
    }
}
