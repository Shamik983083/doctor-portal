<?php

namespace App\Events;

use App\Models\Message;
use App\Models\PatientCase;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPatientMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $caseUuid;
    public string $patientName;
    public string $snippet;
    public string $messageUuid;
    public string $createdAt;
    public bool   $isRead;

    public function __construct(Message $message, PatientCase $case)
    {
        $this->messageUuid = $message->uuid;
        $this->caseUuid    = $case->uuid;
        $this->patientName = optional($case->patient)->full_name ?? 'Patient';
        $this->snippet     = \Illuminate\Support\Str::limit($message->body, 90);
        $this->createdAt   = $message->created_at->toISOString();
        $this->isRead      = false;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('provider-inbox')];
    }

    public function broadcastAs(): string
    {
        return 'NewPatientMessage';
    }
}
