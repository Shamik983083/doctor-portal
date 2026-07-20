<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerCaseSupport extends Notification
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
        $note        = $this->case->support_note
            ? ': ' . \Illuminate\Support\Str::limit($this->case->support_note, 60)
            : '';

        return [
            'type'      => 'case_support',
            'title'     => 'Case needs support',
            'body'      => $patientName . $note,
            'url'       => '/partner/cases/' . $this->case->uuid,
            'case_uuid' => $this->case->uuid,
        ];
    }
}
