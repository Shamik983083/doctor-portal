<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    private function partner(Request $request)
    {
        return $request->attributes->get('partner');
    }

    public function index(Request $request, string $caseId)
    {
        $case = $this->partner($request)->cases()->where('uuid', $caseId)->firstOrFail();

        $messages = $case->messages()
            ->orderBy('created_at')
            ->get(['uuid', 'direction', 'sender_type', 'body', 'is_read', 'read_at', 'created_at']);

        return response()->json($messages);
    }

    public function store(Request $request, string $caseId)
    {
        $data = $request->validate([
            'body'        => 'required|string|max:10000',
            'sender_name' => 'nullable|string|max:100',
        ]);

        $partner = $this->partner($request);
        $case    = $partner->cases()->where('uuid', $caseId)->firstOrFail();

        $message = Message::create([
            'case_id'     => $case->id,
            'patient_id'  => $case->patient_id,
            'partner_id'  => $partner->id,
            'direction'   => 'inbound',
            'channel'     => 'portal',
            'sender_type' => 'patient',
            'body'        => $data['body'],
            'is_read'     => false,
        ]);

        // Notify any other partner webhooks subscribed to this event
        $this->webhooks->dispatch($partner->id, 'patient_message_received', [
            'case_id'    => $case->uuid,
            'message_id' => $message->uuid,
            'timestamp'  => now()->timestamp,
        ]);

        return response()->json($message, 201);
    }
}
