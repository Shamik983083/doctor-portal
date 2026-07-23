<?php

namespace App\Notifications;

use App\Models\RoutingException;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A case could not be routed and nobody has fixed it (Devin msg 2308: "ANY
 * FAILURE NEEDS TO BE LOUD ... NO SILENT FAILURES").
 *
 * TWO TIMINGS, ON PURPOSE.
 *
 *  - A SYSTEMIC failure (no active policy, malformed policy) notifies
 *    IMMEDIATELY. Every case in the system is affected, so the minutes spent
 *    waiting to see whether it clears are the only useful ones there are.
 *  - A per-case failure notifies once the case has been stuck for the configured
 *    age. Raising it the instant routing returns NONE would page an admin for
 *    every case that a doctor coming online in ten minutes would have taken, and
 *    an alert stream nobody trusts is the same as no alert at all.
 *
 * The exception row appears on the exceptions screen the moment it is created
 * either way. This notification is the escalation, not the record.
 */
class RoutingExceptionRaised extends Notification
{
    use Queueable;

    public function __construct(private RoutingException $exception) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->exception->case;

        return [
            'type'         => 'routing_exception',
            'title'        => $this->exception->isSystemic()
                ? 'Routing is not assigning any cases'
                : 'A case could not be assigned',
            'body'         => $this->body(),
            'url'          => '/admin/routing/exceptions',
            'case_uuid'    => $case?->uuid,
            'reason_code'  => $this->exception->reason_code,
            'age_hours'    => round($this->exception->ageHours(), 1),
            'systemic'     => $this->exception->isSystemic(),
        ];
    }

    private function body(): string
    {
        $reason = $this->exception->reasonLabel();

        if ($this->exception->isSystemic()) {
            return $reason . '. No case is being auto-assigned until this is fixed.';
        }

        $hours = round($this->exception->ageHours(), 1);

        return $reason . '. The case has been waiting ' . $hours . ' hours with nobody able to take it.';
    }
}
