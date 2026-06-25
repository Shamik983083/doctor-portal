<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Services\CaseStateMachine;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function __construct(private CaseStateMachine $stateMachine) {}

    public function index(Request $request)
    {
        $cases = PatientCase::with(['patient', 'partner', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->input('partner_id'), fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->input('clinician_id'), fn($q, $id) => $q->where('clinician_id', $id))
            ->latest()
            ->paginate(25);

        $partners   = Partner::orderBy('name')->get(['id', 'name']);
        $clinicians = Clinician::with('user')->get();

        return view('admin.cases.index', compact('cases', 'partners', 'clinicians'));
    }

    public function show(string $uuid)
    {
        $case = PatientCase::with([
            'patient', 'partner', 'clinician.user',
            'caseOfferings.offering', 'caseQuestions',
            'diseases', 'clinicalNotes.clinician.user',
            'orders.pharmacy', 'messages', 'files', 'events',
        ])->where('uuid', $uuid)->firstOrFail();

        $clinicians = Clinician::with('user')->get();

        return view('admin.cases.show', compact('case', 'clinicians'));
    }

    public function assign(Request $request, string $uuid)
    {
        $request->validate(['clinician_id' => 'required|exists:clinicians,id']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        if (!in_array($case->status, [PatientCase::STATUS_CREATED, PatientCase::STATUS_WAITING])) {
            return back()->with('error', 'Case must be in created or waiting status to assign a clinician.');
        }

        // Auto-advance created → waiting before assigning
        if ($case->status === PatientCase::STATUS_CREATED) {
            $this->stateMachine->transition($case, PatientCase::STATUS_WAITING, ['actor_type' => 'admin']);
        }

        $clinician = Clinician::findOrFail($request->input('clinician_id'));
        $this->stateMachine->assignToClinician($case, $clinician);

        return back()->with('success', "Case assigned to {$clinician->full_name}.");
    }
}
