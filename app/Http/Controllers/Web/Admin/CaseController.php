<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Models\PatientFile;
use App\Services\CaseStateMachine;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine  $stateMachine,
        private FileUploadService $fileUploader,
    ) {}

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
            'caseQuestions', 'diseases',
            'clinicalNotes.clinician.user',
            'orders.pharmacy', 'messages', 'files', 'events',
            'questionnaireResponses.questionnaire',
            'questionnaireResponses.answers',
            'casePrescriptions.clinician.user',
            'casePrescriptions.medications',
        ])->where('uuid', $uuid)->firstOrFail();

        $clinicians = Clinician::with('user')->get();

        return view('admin.cases.show', compact('case', 'clinicians'));
    }

    public function assign(Request $request, string $uuid)
    {
        $request->validate(['clinician_id' => 'required|exists:clinicians,id']);

        $case      = PatientCase::where('uuid', $uuid)->firstOrFail();
        $clinician = Clinician::findOrFail($request->input('clinician_id'));

        // Reassign an already-assigned case without a status change
        if ($case->status === PatientCase::STATUS_ASSIGNED) {
            $this->stateMachine->reassign($case, $clinician);
            return back()->with('success', "Case reassigned to {$clinician->full_name}.");
        }

        if (!in_array($case->status, [PatientCase::STATUS_CREATED, PatientCase::STATUS_WAITING])) {
            return back()->with('error', 'Case cannot be assigned in its current status.');
        }

        // Auto-advance created → waiting; pass skip_auto_assign so the auto-assigner
        // does not immediately grab the case before the admin's choice can be applied.
        if ($case->status === PatientCase::STATUS_CREATED) {
            $this->stateMachine->transition($case, PatientCase::STATUS_WAITING, [
                'actor_type'       => 'admin',
                'skip_auto_assign' => true,
            ]);
            $case->refresh();
        }

        $this->stateMachine->assignToClinician($case, $clinician);

        return back()->with('success', "Case assigned to {$clinician->full_name}.");
    }

    public function uploadFile(Request $request, string $uuid)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . FileUploadService::MAX_SIZE_KB,
            'type' => 'nullable|in:lab_result,id_doc,consent,medical_necessity,intake,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $this->fileUploader->store(
            $request->file('file'),
            $request->input('type', 'other'),
            caseId:    $case->id,
            patientId: $case->patient_id,
            partnerId: $case->partner_id,
            notes:     $request->input('notes'),
        );

        return back()->with('success', 'File uploaded successfully.');
    }

    public function deleteFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        $this->fileUploader->delete($file);

        return back()->with('success', 'File deleted.');
    }
}
