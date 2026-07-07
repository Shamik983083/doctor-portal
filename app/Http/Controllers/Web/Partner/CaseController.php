<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    private function partner() { return Auth::user()->partner; }

    public function index(Request $request)
    {
        $cases = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);

        return view('partner.cases.index', compact('cases'));
    }

    public function show(string $uuid)
    {
        $case = $this->partner()->cases()
            ->whereNotNull('support_at')
            ->with(['patient', 'clinician.user', 'caseOfferings.offering',
                    'caseQuestions', 'diseases', 'orders.pharmacy',
                    'clinicalNotes', 'messages', 'files', 'events',
                    'questionnaireResponses.questionnaire',
                    'questionnaireResponses.answers',
                    'casePrescriptions.clinician.user',
                    'casePrescriptions.medications'])
            ->where('uuid', $uuid)->firstOrFail();

        return view('partner.cases.show', compact('case'));
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

        // Save as a clinical note so the assigned clinician can see it in their Notes tab
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
}
