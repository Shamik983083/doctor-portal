<?php

namespace App\Console\Commands;

use App\Models\PatientCase;
use App\Models\RoutingException;
use App\Models\User;
use App\Notifications\RoutingExceptionRaised;
use App\Services\Routing\RoutingExceptionRecorder;
use Illuminate\Console\Command;

/**
 * Escalate routing exceptions that have aged past the threshold, and close the
 * ones that fixed themselves.
 *
 * WHY A SWEEP AND NOT JUST THE RECORDER. RoutingExceptionRecorder escalates when
 * it is CALLED, and it is only called when something tries to route a case. A
 * case that failed once at 2am and is never retried would age forever without
 * anybody being told, which is the silent failure this whole feature exists to
 * remove (Devin msg 2308). This runs on the schedule and closes that gap.
 *
 * It also resolves exceptions whose case has since been assigned by any path,
 * including a manual admin assignment that never went through the router.
 */
class SweepRoutingExceptions extends Command
{
    protected $signature = 'routing:sweep-exceptions';

    protected $description = 'Notify on aged routing exceptions and close ones whose case has since been assigned';

    public function handle(RoutingExceptionRecorder $recorder): int
    {
        $open = RoutingException::open()->with('case')->get();

        if ($open->isEmpty()) {
            $this->info('No open routing exceptions.');

            return self::SUCCESS;
        }

        $resolved = 0;
        $notified = 0;

        // Fetched once: notifying per exception inside the loop would re-query
        // the admin roster for every stuck case.
        $admins = User::role(['admin', 'super_admin'])->get();

        foreach ($open as $exception) {
            $case = $exception->case;

            // Assigned since, by any route. Nothing is stuck, so nothing to say.
            if (! $case || ($case->clinician_id !== null && $case->status !== PatientCase::STATUS_WAITING)) {
                $exception->update(['resolved_at' => now()]);
                $resolved++;

                continue;
            }

            if ($exception->notified_at !== null) {
                continue;
            }

            if (! $exception->isSystemic() && $exception->ageHours() < $recorder->notifyAfterHours()) {
                continue;
            }

            if ($admins->isEmpty()) {
                continue;
            }

            $notification = new RoutingExceptionRaised($exception);
            $admins->each(fn ($admin) => $admin->notify($notification));
            $exception->update(['notified_at' => now()]);
            $notified++;
        }

        $this->info("Swept {$open->count()} open exception(s): {$resolved} resolved, {$notified} escalated.");

        return self::SUCCESS;
    }
}
