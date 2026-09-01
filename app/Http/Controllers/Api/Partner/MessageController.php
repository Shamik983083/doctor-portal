<?php

namespace App\Http\Controllers\Api\Partner;

use App\Events\CaseMessageSent;
use App\Events\NewPatientMessage;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\PatientCase;
use App\Models\User;
use App\Notifications\ClinicianNewMessage;
use App\Notifications\NewCaseMessage;
use App\Notifications\PartnerNewCaseMessage;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            ->when($request->channel, fn($q) => $q->where('channel', $request->channel))
            ->orderBy('created_at')
            ->get(['uuid', 'direction', 'channel', 'sender_type', 'body', 'is_read', 'read_at', 'created_at']);

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

        // When the case is in active support status directed at this partner, treat
        // the POST as a partner-to-clinician escalation message rather than a
        // simulated patient message. Same endpoint, context-sensitive behaviour.
        $isEscalation = $case->escalation_target === PatientCase::ESCALATION_SUPPORT
            && $case->support_at !== null;

        if ($isEscalation) {
            $message = Message::create([
                'uuid'        => (string) Str::uuid(),
                'case_id'     => $case->id,
                'partner_id'  => $partner->id,
                'direction'   => 'inbound',     // inbound = new unread on clinician side
                'channel'     => 'escalation',
                'sender_type' => 'partner',
                'body'        => $data['body'],
                'is_read'     => false,
            ]);

            broadcast(new CaseMessageSent($message));

            // Notify the assigned clinician.
            try {
                $message->setRelation('case', $case);
                if ($case->clinician?->user) {
                    $case->clinician->user->notify(new ClinicianNewMessage($message));
                }
            } catch (\Throwable $e) {
                Log::warning('Escalation clinician notification failed (API): ' . $e->getMessage());
            }

            return response()->json([
                'uuid'        => $message->uuid,
                'case_id'     => $case->uuid,
                'direction'   => 'inbound',
                'channel'     => 'escalation',
                'sender_type' => 'partner',
                'body'        => $message->body,
                'is_read'     => false,
                'created_at'  => $message->created_at,
            ], 201);
        }

        // Default: simulate a patient-originated inbound message.
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

        broadcast(new CaseMessageSent($message));

        $this->webhooks->dispatch($partner->id, 'patient_message_received', [
            'case_id'    => $case->uuid,
            'message_id' => $message->uuid,
            'timestamp'  => now()->timestamp,
        ]);

        broadcast(new NewPatientMessage($message, $case));

        try {
            $message->load(['case.clinician.user', 'patient']);
            User::role(['admin', 'super_admin'])->each(
                fn ($admin) => $admin->notify(new NewCaseMessage($message))
            );
            if ($case->clinician?->user) {
                $case->clinician->user->notify(new ClinicianNewMessage($message));
            }
        } catch (\Throwable $e) {
            Log::warning('Inbound message notification failed: ' . $e->getMessage());
        }

        return response()->json($message, 201);
    }
}
