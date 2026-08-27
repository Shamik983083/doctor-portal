<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PartnerNewCaseMessage extends Notification
{
    use Queueable;

    public function __construct(
        private PatientCase $case,
        private string $body,
        private string $clinicianName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'escalation_message',
            'title'     => 'New message from clinician',
            'body'      => 'Dr. ' . $this->clinicianName . ': "' . Str::limit($this->body, 80) . '"',
            'url'       => '/partner/cases/' . $this->case->uuid,
            'case_uuid' => $this->case->uuid,
        ];
    }
}
