<?php

namespace App\Notifications;

use App\Models\Clinician;
use App\Models\PatientCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CaseDeadlineExceeded extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PatientCase $case,
        private readonly ?Clinician  $clinician,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $caseLabel = $this->case->external_id ?? Str::limit($this->case->uuid, 8, '');
        $clinName  = $this->clinician?->user?->name ?? 'A provider';

        // Clinician whose case was auto-released
        if ($this->clinician && $notifiable->id === $this->clinician->user_id) {
            return [
                'type'      => 'case_deadline_exceeded',
                'title'     => 'Case returned to queue',
                'body'      => "Case {$caseLabel} was auto-released — the completion deadline passed. It has been returned to the waiting pool and another provider may claim it.",
                'url'       => '/clinician/queue',
                'case_uuid' => $this->case->uuid,
            ];
        }

        // Doctor Admin(s) over that clinician
        return [
            'type'      => 'case_deadline_exceeded',
            'title'     => 'Case auto-released: deadline exceeded',
            'body'      => "{$clinName}'s case {$caseLabel} was auto-released after the completion deadline passed and returned to the waiting queue.",
            'url'       => '/admin/escalations',
            'case_uuid' => $this->case->uuid,
        ];
    }
}
