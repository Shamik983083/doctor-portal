<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Services\CaseStateMachine;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine $stateMachine,
        private WebhookDispatcher $webhooks,
    ) {}

    public function queue(Request $request)
    {
        $clinician = Auth::user()->clinician;

        $cases = PatientCase::with(['patient', 'partner', 'caseOfferings.offering'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s), fn($q) => $q->where('status', PatientCase::STATUS_WAITING))
            ->when($request->partner_id, fn($q, $id) => $q->where('partner_id', $id))
            ->orderBy('created_at')
            ->paginate(20);

        return view('clinician.cases.queue', compact('cases', 'clinician'));
    }

    public function show(string $uuid)
    {
        $case = PatientCase::with([
            'patient', 'partner', 'clinician.user',
            'caseOfferings.offering', 'caseQuestions',
            'diseases', 'clinicalNotes.clinician.user',
            'orders.pharmacy', 'messages', 'files', 'tags',
        ])->where('uuid', $uuid)->firstOrFail();

        return view('clinician.cases.show', compact('case'));
    }

    public function assign(Request $request, string $uuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        if ($case->status !== PatientCase::STATUS_WAITING) {
            return back()->with('error', 'Case is not in waiting status.');
        }

        $this->stateMachine->assignToClinician($case, $clinician);

        return redirect()->route('clinician.cases.show', $uuid)->with('success', 'Case assigned to you.');
    }

    public function approve(Request $request, string $uuid)
    {
        $request->validate(['note' => 'nullable|string']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        $this->stateMachine->approve($case, $clinician->id);

        if ($request->note) {
            ClinicalNote::create([
                'case_id'      => $case->id,
                'clinician_id' => $clinician->id,
                'type'         => 'approval',
                'note'         => $request->note,
            ]);
        }

        $this->webhooks->dispatch($case->partner_id, 'case_approved', [
            'case_id'      => $case->uuid,
            'clinician_id' => $clinician->uuid ?? null,
            'timestamp'    => now()->timestamp,
        ]);

        return redirect()->route('clinician.cases.show', $uuid)->with('success', 'Case approved.');
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        $this->stateMachine->cancel($case, $request->reason, $clinician->id, 'clinician');

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => 'cancellation',
            'note'         => $request->reason,
        ]);

        return redirect()->route('clinician.queue')->with('success', 'Case declined.');
    }

    public function addNote(Request $request, string $uuid)
    {
        $request->validate([
            'note'       => 'required|string',
            'type'       => 'nullable|in:general,soap,progress',
            'is_private' => 'boolean',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => $request->type ?? 'general',
            'note'         => $request->note,
            'is_private'   => $request->boolean('is_private'),
        ]);

        $this->webhooks->dispatch($case->partner_id, 'clinical_note_added', [
            'case_id'   => $case->uuid,
            'timestamp' => now()->timestamp,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function escalateToSupport(Request $request, string $uuid)
    {
        $request->validate(['support_note' => 'required|string|max:1000']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        $this->stateMachine->escalateToSupport($case, $request->input('support_note'));

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => 'general',
            'note'         => 'Escalated to support: ' . $request->input('support_note'),
        ]);

        return back()->with('success', 'Case escalated to support. Partner has been notified.');
    }

    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate(['body' => 'required|string']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        Message::create([
            'case_id'      => $case->id,
            'patient_id'   => $case->patient_id,
            'clinician_id' => $clinician->id,
            'partner_id'   => $case->partner_id,
            'direction'    => 'outbound',
            'channel'      => 'portal',
            'sender_type'  => 'clinician',
            'body'         => $request->body,
        ]);

        $this->webhooks->dispatch($case->partner_id, 'message_created', [
            'case_id'    => $case->uuid,
            'sender'     => 'clinician',
            'timestamp'  => now()->timestamp,
        ]);

        return back()->with('success', 'Message sent.');
    }
}
