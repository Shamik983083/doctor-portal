<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerCaseCompleted extends Notification
{
    use Queueable;

    public function __construct(private PatientCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $patientName = $this->case->patient?->full_name ?? 'Unknown patient';
        $visitType   = ucfirst($this->case->visit_type ?? 'General');

        return [
            'type'      => 'case_completed',
            'title'     => 'Case completed',
            'body'      => $patientName . ' · ' . $visitType,
            'url'       => '/partner/cases/' . $this->case->uuid,
            'case_uuid' => $this->case->uuid,
        ];
    }
}
