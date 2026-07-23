<?php

namespace App\Services\Routing;

use App\Models\PatientCase;
use App\Models\RoutingException;
use App\Models\User;
use App\Notifications\RoutingExceptionRaised;
use Illuminate\Support\Facades\Log;

/**
 * Turns a failed routing decision into something somebody owns
 * (Devin msg 2308: "NO SILENT FAILURES").
 *
 * THE PROBLEM THIS SOLVES. Before it, a case nobody could take produced a log
 * line and a null `clinician_id`. That is indistinguishable from a case created
 * four seconds ago that has not been routed yet, and the evidence of the failure
 * was an ABSENCE, which is precisely what nobody notices. Every case that fails
 * now has a row, a reason code, an age, and an admin audience.
 *
 * ONE OPEN ROW PER CASE. A retry updates the existing row and bumps
 * `occurrences` rather than adding another, so a worker retrying every few
 * minutes cannot bury every other exception on the screen. `first_seen_at` never
 * moves, so the age of the problem is the age of the problem.
 *
 * RESOLUTION IS AUTOMATIC WHEN THE CASE ROUTES. `resolve()` is called on every
 * successful assignment, so an exception that fixed itself (a doctor came online,
 * a licence was added) closes without anybody clicking anything. An exceptions
 * screen that needs manual clearing becomes a screen full of things that are
 * already fine, which is the same as an empty one.
 */
final class RoutingExceptionRecorder
{
    /**
     * How long a case sits unroutable before the escalation notification fires.
     *
     * Devin msg 2313 Q7 took the recommendation: into the queue immediately,
     * notify at a configurable age, no delay on the malformed-policy path. The
     * value lives in config/routing.php so it can be tuned without a deploy.
     */
    public function notifyAfterHours(): float
    {
        $value = config('routing.exception_notify_after_hours', 2);

        return is_numeric($value) ? (float) $value : 2.0;
    }

    /**
     * Record that this case could not be routed.
     *
     * @param  array<int, string[]> $providerReasons per-doctor block reasons
     */
    public function record(PatientCase $case, string $reasonCode, array $providerReasons = [], ?string $detail = null): ?RoutingException
    {
        try {
            $exception = RoutingException::open()->where('case_id', $case->id)->first();

            if ($exception) {
                $exception->update([
                    'reason_code'      => $reasonCode,
                    'detail'           => $detail,
                    'provider_reasons' => $providerReasons ?: $exception->provider_reasons,
                    'occurrences'      => $exception->occurrences + 1,
                    'last_seen_at'     => now(),
                ]);
            } else {
                $exception = RoutingException::create([
                    'case_id'          => $case->id,
                    'reason_code'      => $reasonCode,
                    'detail'           => $detail,
                    'provider_reasons' => $providerReasons ?: null,
                    'occurrences'      => 1,
                    'first_seen_at'    => now(),
                    'last_seen_at'     => now(),
                ]);
            }

            $this->maybeNotify($exception);

            return $exception;
        } catch (\Throwable $e) {
            /*
             * The recorder failing must never break case creation. It is the
             * thing that reports failures, so if it is itself broken the log is
             * the last line, and losing the case would be a far worse outcome
             * than losing the record of why it did not route.
             */
            Log::error('Could not record routing exception: ' . $e->getMessage(), [
                'case_id'     => $case->id,
                'reason_code' => $reasonCode,
            ]);

            return null;
        }
    }

    /**
     * Close any open exception for this case, because it routed.
     *
     * Called on every successful assignment, including a pool pull and a manual
     * admin assignment, so the screen reflects what is actually stuck now rather
     * than what was ever stuck.
     */
    public function resolve(PatientCase $case, ?User $actor = null): void
    {
        try {
            RoutingException::open()
                ->where('case_id', $case->id)
                ->update([
                    'resolved_at' => now(),
                    'resolved_by' => $actor?->id,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Could not resolve routing exception: ' . $e->getMessage(), [
                'case_id' => $case->id,
            ]);
        }
    }

    /**
     * Escalate, once.
     *
     * `notified_at` is the guard. Without it a case retried every five minutes
     * for a day would send an admin 288 identical notifications, and the second
     * one is already worse than useless.
     */
    private function maybeNotify(RoutingException $exception): void
    {
        if ($exception->notified_at !== null) {
            return;
        }

        $due = $exception->isSystemic() || $exception->ageHours() >= $this->notifyAfterHours();

        if (! $due) {
            return;
        }

        try {
            $admins = User::role(['admin', 'super_admin'])->get();

            if ($admins->isEmpty()) {
                return;
            }

            $notification = new RoutingExceptionRaised($exception);
            $admins->each(fn ($admin) => $admin->notify($notification));

            $exception->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Routing exception notification failed: ' . $e->getMessage(), [
                'routing_exception_id' => $exception->id,
            ]);
        }
    }
}
