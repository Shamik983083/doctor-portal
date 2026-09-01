<?php

namespace App\Http\Controllers\Web\Partner;

use App\Events\CaseMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\PatientCase;
use App\Notifications\ClinicianNewMessage;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaseController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $cases = $this->partner()->cases()
            ->where(fn($q) => $q->whereNotNull('support_at')
                ->orWhereIn('status', ['completed', 'cancelled']))
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);

        return view('partner.cases.index', compact('cases'));
    }

    public function show(string $uuid)
    {
        $case = $this->partner()->cases()
            ->where(fn($q) => $q->whereNotNull('support_at')
                ->orWhereIn('status', ['completed', 'cancelled']))
            ->with(['patient', 'clinician.user', 'caseOfferings.offering',
                    'caseQuestions', 'diseases', 'orders.pharmacy',
                    'clinicalNotes', 'files', 'events',
                    'questionnaireResponses.questionnaire',
                    'questionnaireResponses.answers',
                    'casePrescriptions.clinician.user',
                    'casePrescriptions.medications'])
            ->where('uuid', $uuid)->firstOrFail();

        // Load portal and escalation messages separately for the two thread panels.
        $portalMessages    = $case->messages()->where('channel', 'portal')->orderBy('created_at')->get();
        $escalationMessages = $case->messages()->where('channel', 'escalation')->orderBy('created_at')->get();

        return view('partner.cases.show', compact('case', 'portalMessages', 'escalationMessages'));
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $case = $this->partner()->cases()->where('uuid', $uuid)->firstOrFail();

        $this->stateMachine->cancel($case, $request->input('reason', ''), null, 'partner');

        return redirect()->route('partner.cases.index')
            ->with('success', 'Case cancelled.');
    }

    public function returnToClinician(Request $request, string $uuid)
    {
        $request->validate(['partner_note' => 'required|string|max:1000']);

        $case = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->where('status', 'support')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $partnerNote = $request->input('partner_note');

        $this->stateMachine->returnToClinicianFromSupport($case, $partnerNote);

        // Save as a clinical note so the assigned clinician can see it in their Notes tab.
        if ($case->clinician_id) {
            ClinicalNote::create([
                'case_id'      => $case->id,
                'clinician_id' => $case->clinician_id,
                'type'         => 'general',
                'note'         => 'Support response: ' . $partnerNote,
                'is_private'   => false,
            ]);
        }

        return back()->with('success', 'Case returned to clinician.');
    }

    /**
     * Close a parallel support thread (case NOT in STATUS_SUPPORT).
     * Clears escalation_target so compose forms lock on both portals.
     */
    public function closeThread(Request $request, string $uuid)
    {
        $request->validate(['partner_note' => 'nullable|string|max:1000']);

        $partner = $this->partner();
        $case = $partner->cases()
            ->whereNotNull('support_at')
            ->where('escalation_target', PatientCase::ESCALATION_SUPPORT)
            ->where('uuid', $uuid)
            ->whereNotIn('status', [PatientCase::STATUS_SUPPORT])
            ->firstOrFail();

        $partnerNote = $request->input('partner_note', '');
        $this->stateMachine->closeParallelSupportThread($case, $partnerNote);

        if ($partnerNote && $case->clinician_id) {
            ClinicalNote::create([
                'case_id'      => $case->id,
                'clinician_id' => $case->clinician_id,
                'type'         => 'general',
                'note'         => 'Support thread closed: ' . $partnerNote,
                'is_private'   => false,
            ]);
        }

        return back()->with('success', 'Support thread closed.');
    }

    /**
     * Partner sends a message to the assigned clinician on an active escalation.
     */
    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $partner = $this->partner();

        $case = $partner->cases()
            ->whereNotNull('support_at')
            ->where('escalation_target', PatientCase::ESCALATION_SUPPORT)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $message = Message::create([
            'uuid'        => (string) Str::uuid(),
            'case_id'     => $case->id,
            'partner_id'  => $partner->id,
            'direction'   => 'inbound',   // inbound = new unread on the clinician side
            'channel'     => 'escalation',
            'sender_type' => 'partner',
            'body'        => $request->body,
            'is_read'     => false,
        ]);

        // Real-time: broadcast so clinician sees the message immediately if online.
        try {
            broadcast(new CaseMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Reverb broadcast failed for escalation message ' . $message->id . ': ' . $e->getMessage());
        }

        // Notify the assigned clinician.
        try {
            $message->setRelation('case', $case);
            if ($case->clinician?->user) {
                $case->clinician->user->notify(new ClinicianNewMessage($message));
            }
        } catch (\Throwable $e) {
            Log::warning('Escalation clinician notification failed: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id'          => $message->id,
                'body'        => $message->body,
                'sender_type' => 'partner',
                'channel'     => 'escalation',
                'time'        => $message->created_at->format('H:i'),
                'created_at'  => $message->created_at->toIso8601String(),
            ], 201);
        }

        return back()->with('success', 'Message sent to clinician.');
    }

    /**
     * Lightweight poll endpoint: returns escalation messages newer than ?after={id}.
     * Used by the partner portal JS to check for clinician replies without WebSockets.
     */
    public function pollMessages(Request $request, string $uuid)
    {
        $case = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $messages = $case->messages()
            ->where('channel', 'escalation')
            ->where('id', '>', $request->integer('after', 0))
            ->orderBy('id')
            ->get(['id', 'body', 'sender_type', 'direction', 'created_at']);

        return response()->json($messages);
    }
}
