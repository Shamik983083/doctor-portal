<?php

namespace App\Services;

use App\Models\PatientCase;
use Illuminate\Support\Facades\DB;

/**
 * Active (non-paused) elapsed time for a case.
 *
 * WHY THIS EXISTS: B3 deadline sweeps, SLA overdue checks, and the
 * routing pool evaluator all independently computed raw wall-clock elapsed
 * time. D16 requires those checks to exclude any periods the case was
 * legitimately paused waiting on a client response. This single helper
 * replaces every raw date-diff call so the pause logic only lives in one place.
 */
final class CaseElapsedTime
{
    /**
     * Active elapsed minutes for a case — wall-clock time minus any paused intervals.
     *
     * Starting point: `assigned_at` when set (the case is actively in a
     * clinician's queue), falling back to `created_at`. Using `assigned_at`
     * matches the existing overdue definition in PoolEligibilityEvaluator.
     *
     * Open pause (resumed_at IS NULL): the pause is still running, so its
     * elapsed portion is subtracted using now() as the notional close.
     *
     * Returns 0 if the computed value would be negative (e.g., clocks skew
     * or a migration creates an impossible interval).
     */
    public static function active(PatientCase $case): int
    {
        $origin = $case->assigned_at ?? $case->created_at;

        if (! $origin) {
            return 0;
        }

        $totalMinutes = (int) $origin->diffInMinutes(now());

        // Sum all pause intervals for this case in one query.
        $pausedMinutes = (int) DB::table('case_pause_intervals')
            ->where('case_id', $case->id)
            ->selectRaw(
                'SUM(TIMESTAMPDIFF(MINUTE, paused_at, COALESCE(resumed_at, NOW()))) AS total'
            )
            ->value('total');

        return max(0, $totalMinutes - $pausedMinutes);
    }

    /**
     * Open a new pause interval for a case.
     * No-op if there is already an open (un-resumed) interval.
     */
    public static function pause(PatientCase $case, string $reason = ''): void
    {
        $alreadyPaused = DB::table('case_pause_intervals')
            ->where('case_id', $case->id)
            ->whereNull('resumed_at')
            ->exists();

        if ($alreadyPaused) {
            return;
        }

        DB::table('case_pause_intervals')->insert([
            'case_id'    => $case->id,
            'paused_at'  => now(),
            'resumed_at' => null,
            'reason'     => $reason ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Close the most-recent open pause interval for a case.
     * No-op if the case is not currently paused.
     */
    public static function resume(PatientCase $case): void
    {
        DB::table('case_pause_intervals')
            ->where('case_id', $case->id)
            ->whereNull('resumed_at')
            ->update([
                'resumed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** Whether the case currently has an open (un-resumed) pause interval. */
    public static function isPaused(PatientCase $case): bool
    {
        return DB::table('case_pause_intervals')
            ->where('case_id', $case->id)
            ->whereNull('resumed_at')
            ->exists();
    }
}
