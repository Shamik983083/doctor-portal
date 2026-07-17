<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastAs(): string
    {
        return 'CaseMessageSent';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('case.'.$this->message->case_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'case_id' => $this->message->case_id,
            'body' => $this->message->body,
            'sender_type' => $this->message->sender_type,
            'direction' => $this->message->direction,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
