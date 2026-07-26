<?php

namespace App\Notifications;

use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseDeadlineWarning extends Notification
{
    use Queueable;

    public function __construct(private readonly PatientCase $case) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'case_deadline_warning',
            'case_id'    => $this->case->uuid,
            'case_label' => $this->case->external_id ?? \Illuminate\Support\Str::limit($this->case->uuid, 8, ''),
            'deadline'   => $this->case->completion_deadline_at?->toIso8601String(),
            'message'    => 'Case ' . ($this->case->external_id ?? $this->case->uuid) . ' must be completed within 15 minutes or it will be auto-released.',
        ];
    }
}
