<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseCompleted extends Notification
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
            'type'     => 'case_completed',
            'title'    => 'Case completed',
            'body'     => ($patient?->full_name ?? 'A case') . ' has been completed — ' . $offering . '.',
            'url'      => '/admin/cases/' . $this->case->uuid,
            'triage'   => $this->case->triage,
            'case_uuid'=> $this->case->uuid,
        ];
    }
}
