<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerCaseCancelled extends Notification
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
        $reason      = $this->case->cancellation_reason
            ? ': ' . \Illuminate\Support\Str::limit($this->case->cancellation_reason, 60)
            : '';

        return [
            'type'      => 'case_cancelled',
            'title'     => 'Case cancelled',
            'body'      => $patientName . $reason,
            'url'       => '/partner/cases/' . $this->case->uuid,
            'case_uuid' => $this->case->uuid,
        ];
    }
}
