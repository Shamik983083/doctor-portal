<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseEscalatedToDoctorAdmin extends Notification
{
    use Queueable;

    public function __construct(private PatientCase $case, private string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $patientName   = $this->case->patient?->full_name ?? 'Unknown patient';
        $clinicianName = $this->case->clinician?->user?->name ?? 'A provider';
        $body          = $clinicianName . ' escalated ' . $patientName . "'s case to you"
            . ($this->reason ? ': ' . \Illuminate\Support\Str::limit($this->reason, 80) : '.');

        return [
            'type'      => 'case_escalated_doctor_admin',
            'title'     => 'Case escalated to Doctor Admin',
            'body'      => $body,
            'url'       => '/admin/escalations',
            'case_uuid' => $this->case->uuid,
        ];
    }
}
