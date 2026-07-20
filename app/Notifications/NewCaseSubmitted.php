<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCaseSubmitted extends Notification
{
    use Queueable;

    public function __construct(private PatientCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $patient  = $this->case->patient;
        $offering = $this->case->caseOfferings->first()?->offering?->name ?? 'Unknown offering';

        return [
            'type'     => 'new_case',
            'title'    => 'New case submitted',
            'body'     => ($patient?->full_name ?? 'A patient') . ' submitted a case for ' . $offering . '.',
            'url'      => '/admin/cases/' . $this->case->uuid,
            'triage'   => $this->case->triage,
            'case_uuid'=> $this->case->uuid,
        ];
    }
}
