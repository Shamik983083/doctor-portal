<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ClinicianNewMessage extends Notification
{
    use Queueable;

    public function __construct(private Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $senderName = $this->message->patient?->full_name ?? 'Patient';
        $caseUuid   = $this->message->case?->uuid;
        $snippet    = Str::limit($this->message->body, 80);

        return [
            'type'      => 'new_message',
            'title'     => 'New message from patient',
            'body'      => $senderName . ': "' . $snippet . '"',
            'url'       => $caseUuid ? '/clinician/cases/' . $caseUuid : '/clinician/cases',
            'case_uuid' => $caseUuid,
        ];
    }
}
