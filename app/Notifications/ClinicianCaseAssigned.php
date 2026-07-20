<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClinicianCaseAssigned extends Notification
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
        $visitType   = $this->case->visit_type ?? 'General';

        return [
            'type'      => 'case_assigned',
            'title'     => 'New case assigned to you',
            'body'      => $patientName . ' · ' . ucfirst($visitType),
            'url'       => '/clinician/cases/' . $this->case->uuid,
            'case_uuid' => $this->case->uuid,
        ];
    }
}
