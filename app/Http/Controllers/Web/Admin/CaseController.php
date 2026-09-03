<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Models\PatientFile;
use App\Models\Setting;
use App\Services\CaseStateMachine;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine  $stateMachine,
        private FileUploadService $fileUploader,
    ) {}

    public function index(Request $request)
    {
        // A Doctor Admin sees only their own doctors' cases; a super admin sees
        // all of them (Devin msg 2117). Applied first so no later filter can
        // widen it, only narrow it.
        $slaRiskMinutes = (int) Setting::get('sla_review_hours', 24) * 60 * 0.7;

        $cases = PatientCase::visibleTo($request->user())
            ->with(['patient', 'partner', 'subStorefront', 'clinician.user', 'caseOfferings.offering'])
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->boolean('active'), fn($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($request->input('triage'), fn($q, $t) => $q->where('triage', $t))
            ->when($request->input('partner_id'), fn($q, $id) => $q->where('partner_id', $id))
            ->when($request->input('clinician_id'), fn($q, $id) => $q->where('clinician_id', $id))
            /*
             * First visit vs check-in. Matches the two dashboard metrics, so the
             * cards link somewhere that shows the cases they counted. Reads the
             * column, like the metrics do, not the visit_type fallback.
             */
            ->when($request->input('case_type') === 'refill', fn($q) => $q->refills())
            ->when($request->input('case_type') === 'new', fn($q) => $q->firstVisits())
            ->when($request->boolean('sla_risk'), fn($q) => $q
                ->whereIn('status', ['assigned', 'approved', 'processing'])
                ->whereRaw('
                    TIMESTAMPDIFF(MINUTE, COALESCE(assigned_at, created_at), NOW()) >=
                    LEAST(
                        ?,
                        COALESCE(
                            (SELECT MIN(sp.overdue_after_hours * 60 * 0.7)
                             FROM admin_clinician ac
                             JOIN sla_policies sp
                               ON sp.owner_user_id = ac.user_id
                              AND sp.is_active = 1
                              AND sp.overdue_after_hours IS NOT NULL
                             WHERE ac.clinician_id = cases.clinician_id),
                            ?
                        )
                    )
                ', [$slaRiskMinutes, $slaRiskMinutes])
            )
            ->when($request->input('search'), fn($q, $s) =>
                $q->whereHas('patient', fn($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate(25)->withQueryString();

        $partners   = Partner::orderBy('name')->get(['id', 'name']);

        // The doctor filter offers only doctors this admin is over. Listing all
        // of them would leak the roster and invite filtering by someone else's.
        $clinicians = Clinician::visibleTo($request->user())->with('user')->get();

        return view('admin.cases.index', compact('cases', 'partners', 'clinicians'));
    }

    public function show(Request $request, string $uuid)
    {
        /*
         * Scoped here too, not only on the list. A scoped index with an unscoped
         * detail view is not access control: the case is one guessed or shared
         * URL away. Out-of-scope reads 404 rather than 403, so a Doctor Admin
         * cannot confirm a case exists outside their doctors either.
         */
        $case = PatientCase::visibleTo($request->user())
            ->with([
                'patient', 'partner', 'clinician.user',
                'diseases',
                'clinicalNotes.clinician.user',
                'orders.pharmacy', 'messages.user', 'files', 'events',
                'questionnaireResponses.questionnaire',
                'questionnaireResponses.answers',
                'casePrescriptions.clinician.user',
                'casePrescriptions.medications',
            ])->where('uuid', $uuid)->firstOrFail();

        // Reassignment targets are scoped as well, so an admin cannot hand a
        // case to a doctor they are not over.
        $clinicians = Clinician::visibleTo($request->user())->with('user')->get();

        return view('admin.cases.show', compact('case', 'clinicians'));
    }

    public function assign(Request $request, string $uuid)
    {
        $request->validate(['clinician_id' => 'required|exists:clinicians,id']);

        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();

        // The TARGET is scoped too, not just the case. Scoping only the case
        // would let a Doctor Admin push work onto a doctor they are not over,
        // which crosses the same boundary from the other direction.
        $clinician = Clinician::visibleTo(auth()->user())->findOrFail($request->input('clinician_id'));

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

        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();

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

    public function downloadFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function previewFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    public function deleteFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        $this->fileUploader->delete($file);

        return back()->with('success', 'File deleted.');
    }

    public function destroy(string $uuid)
    {
        $case = PatientCase::visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();
        $case->delete();

        return redirect()->route('admin.cases.index')
            ->with('success', "Case #{$case->uuid} has been deleted.");
    }
}
