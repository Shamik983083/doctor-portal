<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCaseMessage extends Notification
{
    use Queueable;

    public function __construct(private Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isInbound  = $this->message->direction === 'inbound';
        $sender     = $isInbound
            ? ($this->message->patient?->full_name ?? 'Patient')
            : ($this->message->case?->clinician?->user?->name ?? 'Clinician');
        $receiver   = $isInbound ? 'clinician' : 'patient';
        $caseUuid   = $this->message->case?->uuid;
        $snippet    = \Illuminate\Support\Str::limit($this->message->body, 80);

        return [
            'type'      => 'new_message',
            'title'     => $isInbound ? 'New patient message' : 'Clinician sent a message',
            'body'      => $sender . ' → ' . $receiver . ': "' . $snippet . '"',
            'url'       => $caseUuid ? '/admin/cases/' . $caseUuid : '/admin/cases',
            'case_uuid' => $caseUuid,
            'direction' => $this->message->direction,
        ];
    }
}
