<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\CasePrescription;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\Offering;
use App\Models\PatientCase;
use App\Services\CaseStateMachine;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'questionnaireResponses.questionnaire',
            'questionnaireResponses.answers',
            'casePrescriptions.clinician.user',
            'casePrescriptions.medications',
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

    public function prescribeForm(string $uuid)
    {
        $case = PatientCase::with([
            'patient', 'partner', 'clinician.user',
            'caseOfferings.offering.category',
        ])->where('uuid', $uuid)->firstOrFail();

        // Filter offerings by categories already on the case; if none, show all
        $categoryIds = $case->caseOfferings
            ->pluck('offering.category_id')
            ->filter()
            ->unique()
            ->values();

        $offerings = Offering::with('category')
            ->where('is_active', true)
            ->when($categoryIds->count(), fn($q) => $q->whereIn('category_id', $categoryIds))
            ->orderBy('name')
            ->get(['id', 'name', 'internal_name', 'compound_formula', 'refills',
                   'quantity', 'days_supply', 'dispense_unit', 'days_until_dispense']);

        return view('clinician.cases.prescribe', compact('case', 'offerings'));
    }

    public function prescribe(Request $request, string $uuid)
    {
        $request->validate([
            'diagnoses'                              => 'required|string',
            'directions'                             => 'nullable|string',
            'medical_necessity'                      => 'nullable|string',
            'medications'                            => 'nullable|array',
            'medications.*.offering_id'              => 'nullable|exists:offerings,id',
            'medications.*.name'                     => 'required_with:medications|string|max:255',
            'medications.*.compound_formula'         => 'nullable|string',
            'medications.*.refills'                  => 'nullable|integer|min:0',
            'medications.*.quantity'                 => 'nullable|numeric|min:0',
            'medications.*.days_supply'              => 'nullable|integer|min:0',
            'medications.*.dispense_unit'            => 'nullable|string|max:100',
            'medications.*.days_until_dispense'      => 'nullable|integer|min:0',
        ]);

        $case      = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Auth::user()->clinician;

        DB::transaction(function () use ($request, $case, $clinician) {
            $prescription = CasePrescription::create([
                'case_id'          => $case->id,
                'clinician_id'     => $clinician->id,
                'diagnoses'        => $request->input('diagnoses'),
                'directions'       => $request->input('directions'),
                'medical_necessity'=> $request->input('medical_necessity'),
                'prescribed_at'    => now(),
            ]);

            foreach ($request->input('medications', []) as $med) {
                $prescription->medications()->create([
                    'offering_id'        => $med['offering_id'] ?? null,
                    'name'               => $med['name'],
                    'compound_formula'   => $med['compound_formula'] ?? null,
                    'refills'            => $med['refills'] ?? null,
                    'quantity'           => $med['quantity'] ?? null,
                    'days_supply'        => $med['days_supply'] ?? null,
                    'dispense_unit'      => $med['dispense_unit'] ?? null,
                    'days_until_dispense'=> $med['days_until_dispense'] ?? null,
                ]);
            }

            $this->stateMachine->approve($case, $clinician->id);
        });

        $this->webhooks->dispatch($case->partner_id, 'case_approved', [
            'case_id'      => $case->uuid,
            'clinician_id' => $clinician->uuid ?? null,
            'timestamp'    => now()->timestamp,
        ]);

        return redirect()->route('clinician.cases.show', $uuid)
            ->with('success', 'Case approved and prescription submitted.');
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

    public function sendToPharmacy(string $uuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        if ($case->status !== PatientCase::STATUS_APPROVED) {
            return back()->with('error', 'Case must be approved before sending to pharmacy.');
        }

        $this->stateMachine->startProcessing($case);

        return back()->with('success', 'Case sent to pharmacy for processing.');
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
