<?php

namespace App\Console\Commands;

use App\Models\EhrRecord;
use App\Services\EhrRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry failed EHR records that have not yet reached the attempt ceiling.
 *
 * The command is safe to schedule and safe to run manually:
 *   - A global `EHR_ENABLED` guard stops it from burning the retry budget
 *     when the integration is switched off.
 *   - A `max_attempts` guard stops retries once the ceiling is reached so a
 *     permanently-broken record does not loop forever.
 *   - The scheduler gate (withoutOverlapping) in routes/console.php prevents
 *     two instances from running at the same time.
 *   - Each record is processed individually; one failure never stops the rest.
 *
 * PHI: only UUIDs, partner IDs, attempt counts and error messages are logged,
 * never the payload.
 */
class EhrRetry extends Command
{
    protected $signature   = 'ehr:retry';
    protected $description = 'Retry failed EHR records that have not exceeded the attempt ceiling';

    public function handle(EhrRecordService $ehrRecords): int
    {
        // Guard 1: do not burn the retry budget when the feature is globally off.
        // Records stored while disabled carry status=disabled and are never
        // retried; records that were in-flight when the switch was turned off may
        // carry status=failed. Attempting to push them would fail immediately
        // (EhrGatewayManager throws) and decrement their remaining attempts.
        if (! config('ehr.enabled')) {
            $this->line('EHR push is globally disabled (EHR_ENABLED). Nothing retried.');
            return self::SUCCESS;
        }

        // Guard 2: a zero or negative ceiling means retries are intentionally
        // disabled, even with the feature on.
        $maxAttempts = (int) config('ehr.max_attempts', 5);

        if ($maxAttempts <= 0) {
            $this->line('ehr.max_attempts is 0 — retry is disabled by configuration.');
            return self::SUCCESS;
        }

        $sent    = 0;
        $failed  = 0;
        $skipped = 0;

        // lazy() streams in chunks of 1000 so a large backlog of failed records
        // does not load the full result set into memory at once.
        EhrRecord::where('status', EhrRecord::STATUS_FAILED)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->lazy()
            ->each(function (EhrRecord $record) use (
                $ehrRecords, $maxAttempts, &$sent, &$failed, &$skipped
            ) {
                // Re-check after the lazy load: another process (or a previous
                // iteration of this same sweep) may have updated this row between
                // when the query ran and when we reached it in the stream.
                if (
                    $record->status !== EhrRecord::STATUS_FAILED
                    || $record->attempts >= $maxAttempts
                ) {
                    $skipped++;
                    return;
                }

                try {
                    $updated = $ehrRecords->push($record);

                    if ($updated->status === EhrRecord::STATUS_SENT) {
                        $sent++;
                        Log::info('EHR retry succeeded', [
                            'ehr_record_uuid' => $record->uuid,
                            'partner_id'      => $record->partner_id,
                            'attempts'        => $updated->attempts,
                            'reference'       => $updated->reference,
                        ]);
                    } else {
                        $failed++;
                        Log::warning('EHR retry attempt did not succeed', [
                            'ehr_record_uuid' => $record->uuid,
                            'partner_id'      => $record->partner_id,
                            'attempts'        => $updated->attempts,
                            'exhausted'       => $updated->attempts >= $maxAttempts,
                            'last_error'      => $updated->last_error,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // push() catches Throwable internally and updates the record,
                    // so this block fires only for truly unexpected failures such
                    // as a lost DB connection mid-sweep.
                    $failed++;
                    Log::error('EHR retry threw unexpectedly', [
                        'ehr_record_uuid' => $record->uuid,
                        'partner_id'      => $record->partner_id,
                        'error'           => $e->getMessage(),
                    ]);
                }
            });

        $total = $sent + $failed + $skipped;

        $this->info(sprintf(
            'EHR retry complete — %d processed (%d sent, %d failed, %d skipped).',
            $total, $sent, $failed, $skipped
        ));

        Log::info('EHR retry sweep complete', compact('sent', 'failed', 'skipped'));

        return self::SUCCESS;
    }
}
