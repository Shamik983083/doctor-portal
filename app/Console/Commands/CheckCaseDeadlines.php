<?php

namespace App\Console\Commands;

use App\Models\CaseEvent;
use App\Models\PatientCase;
use App\Notifications\CaseDeadlineExceeded;
use App\Notifications\CaseDeadlineWarning;
use App\Services\CaseStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B3: sweep for cases approaching or past their pull-assigned completion deadline.
 *
 * Two passes per run:
 *  1. 15-minute warning — notify the clinician once, then set deadline_warned=true.
 *  2. Auto-release — cases past deadline are returned to WAITING and a cooldown is
 *     applied to the clinician so they cannot immediately re-pull.
 *
 * Scheduled every 5 minutes so the warning fires promptly without tight polling.
 */
class CheckCaseDeadlines extends Command
{
    protected $signature   = 'case:check-deadlines';
    protected $description = 'Warn on upcoming case deadlines and auto-release overdue cases (B3)';

    public function handle(CaseStateMachine $stateMachine): int
    {
        $openStatuses = [
            PatientCase::STATUS_ASSIGNED,
            PatientCase::STATUS_SUPPORT,
            PatientCase::STATUS_APPROVED,
            PatientCase::STATUS_PROCESSING,
        ];

        // ── Pass 1: 15-minute warning ─────────────────────────────────────────
        $warnWindow = now()->addMinutes(15);

        PatientCase::whereNotNull('completion_deadline_at')
            ->where('completion_deadline_at', '<=', $warnWindow)
            ->where('completion_deadline_at', '>', now())
            ->where('deadline_warned', false)
            ->whereIn('status', $openStatuses)
            ->with('clinician.user')
            ->each(function (PatientCase $case) {
                try {
                    $case->updateQuietly(['deadline_warned' => true]);

                    if ($case->clinician?->user) {
                        $case->clinician->user->notify(new CaseDeadlineWarning($case));
                    }

                    Log::info('B3: deadline warning sent', ['case_id' => $case->id]);
                } catch (\Throwable $e) {
                    Log::warning('B3: deadline warning failed', [
                        'case_id' => $case->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            });

        // ── Pass 2: auto-release past-deadline cases ──────────────────────────
        PatientCase::whereNotNull('completion_deadline_at')
            ->where('completion_deadline_at', '<', now())
            ->whereIn('status', $openStatuses)
            ->with(['clinician.user', 'clinician.admins'])
            ->each(function (PatientCase $case) use ($stateMachine) {
                try {
                    $clinician = $case->clinician;

                    // Return the case to the waiting queue.
                    $case->updateQuietly([
                        'clinician_id'          => null,
                        'completion_deadline_at' => null,
                        'deadline_warned'        => false,
                    ]);

                    // Transition back to WAITING manually (bypass the guard that
                    // would normally require waiting→assigned sequence).
                    $case->update(['status' => PatientCase::STATUS_WAITING]);

                    // Apply continuity-of-care cooldown to the releasing clinician
                    // so they cannot immediately re-pull the same (or any) case.
                    if ($clinician) {
                        $cooldownHours = (int) config('routing.deadline_cooldown_hours', 4);
                        $clinician->updateQuietly(['pool_cooldown_until' => now()->addHours($cooldownHours)]);
                    }

                    CaseEvent::create([
                        'case_id'    => $case->id,
                        'event_type' => 'deadline_exceeded',
                        'actor_type' => 'system',
                        'actor_id'   => null,
                        'notes'      => 'Completion deadline passed; case auto-released to waiting queue.',
                    ]);

                    $notification = new CaseDeadlineExceeded($case, $clinician);

                    if ($clinician?->user) {
                        $clinician->user->notify($notification);
                    }

                    $clinician?->admins?->each(fn ($admin) => $admin->notify($notification));

                    Log::info('B3: case auto-released past deadline', [
                        'case_id'      => $case->id,
                        'clinician_id' => $clinician?->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('B3: auto-release failed', [
                        'case_id' => $case->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
