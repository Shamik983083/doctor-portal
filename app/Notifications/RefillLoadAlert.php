<?php

namespace App\Notifications;

use App\Models\Clinician;
use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a Doctor Admin that one of their doctors is carrying a lot of check-ins.
 *
 * The soft half of the refill controls (Devin msg 2250 A: "Let it run and alert
 * the doctor admin but don't withhold"). The check-in has ALREADY been routed to
 * the doctor by continuity; this notification does not gate anything, it just
 * surfaces the load so an admin can rebalance by hand if they want to.
 */
class RefillLoadAlert extends Notification
{
    use Queueable;

    public function __construct(
        private Clinician $clinician,
        private PatientCase $case,
        private int $refillsToday,
        private int $threshold,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $name = $this->clinician->user?->name ?? 'A doctor';

        return [
            'type'          => 'refill_load_alert',
            'title'         => 'Check-in load high',
            'body'          => $name . ' has taken ' . $this->refillsToday
                                . ' check-ins today, over the alert threshold of ' . $this->threshold
                                . '. Continuity kept the case with them; nothing was withheld.',
            'url'           => '/admin/clinicians/' . $this->clinician->id,
            'clinician_id'  => $this->clinician->id,
            'case_uuid'     => $this->case->uuid,
            'refills_today' => $this->refillsToday,
            'threshold'     => $this->threshold,
        ];
    }
}
