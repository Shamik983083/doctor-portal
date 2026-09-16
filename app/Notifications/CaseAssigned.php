<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseAssigned extends Notification
{
    use Queueable;

    public function __construct(private PatientCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $patient   = $this->case->patient;
        $clinician = $this->case->clinician?->full_name ?? 'a clinician';

        return [
            'type'     => 'case_assigned',
            'title'    => 'Case assigned',
            'body'     => ($patient?->full_name ?? 'A case') . ' was assigned to ' . $clinician . '.',
            'url'      => '/admin/cases/' . $this->case->uuid,
            'triage'   => $this->case->triage,
            'case_uuid'=> $this->case->uuid,
        ];
    }
}
