<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\CasePrescription;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\Offering;
use App\Models\PatientCase;
use App\Models\PatientFile;
use App\Services\CaseStateMachine;
use App\Services\FileUploadService;
use App\Services\PharmacyDispatchService;
use App\Services\PrescriptionDocumentService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine            $stateMachine,
        private WebhookDispatcher           $webhooks,
        private FileUploadService           $fileUploader,
        private PrescriptionDocumentService $prescriptionDocuments,
        private PharmacyDispatchService     $pharmacyDispatch,
    ) {}

    public function queue(Request $request)
    {
        $clinician = Auth::user()->clinician;

        $cases = PatientCase::with(['patient', 'partner', 'caseOfferings.offering'])
            ->withCount(['messages as unread_messages_count' => fn($q) =>
                $q->where('direction', 'inbound')->where('is_read', false)
            ])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('state'), fn($q) => $q->where(
                fn($q) => $q->where('patient_state', $request->state)
                             ->orWhereHas('patient', fn($p) => $p->where('state', $request->state))
            ))
            ->when($request->filled('search'), fn($q) => $q->whereHas('patient', fn($p) =>
                $p->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ['%' . $request->search . '%'])
            ))
            ->when($request->filled('partner_id'), fn($q) => $q->where('partner_id', $request->partner_id))
            ->when($request->filled('triage'), fn($q) => $q->where('triage', $request->triage))
            ->orderByRaw("FIELD(triage, 'red', 'yellow', 'green') DESC")
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        $openStatuses = [
            PatientCase::STATUS_WAITING,
            PatientCase::STATUS_ASSIGNED,
            PatientCase::STATUS_SUPPORT,
        ];
        $triageCounts = PatientCase::whereIn('status', $openStatuses)
            ->selectRaw('triage, COUNT(*) as total')
            ->groupBy('triage')
            ->pluck('total', 'triage');

        $triageMetrics = [
            'open'   => (int) $triageCounts->sum(),
            'red'    => (int) $triageCounts->get(PatientCase::TRIAGE_RED, 0),
            'yellow' => (int) $triageCounts->get(PatientCase::TRIAGE_YELLOW, 0),
            'green'  => (int) $triageCounts->get(PatientCase::TRIAGE_GREEN, 0),
        ];

        // Quick-review panel — top case drives summary, intake, and triage findings
        $topCase = $cases->first();
        if ($topCase) {
            $topCase->load(['caseQuestions', 'questionnaireResponses.answers', 'clinician.user']);
        }

        $intake = collect();
        if ($topCase) {
            $fromQuestions = $topCase->caseQuestions
                ->map(fn($q) => ['q' => $q->question, 'a' => $q->answer])
                ->filter(fn($r) => filled($r['q']));
            $intake = $fromQuestions->isNotEmpty()
                ? $fromQuestions->values()
                : $topCase->questionnaireResponses->flatMap->answers
                    ->map(fn($a) => ['q' => $a->question_text, 'a' => $a->answer])
                    ->filter(fn($r) => filled($r['q']))->values();
        }

        $aiSummary = [];
        if ($topCase) {
            $bullets = ['Triage classification: ' . $topCase->triageLabel() . ' — ' . $topCase->triageMeaning()];
            $p = $topCase->patient;
            if ($p) {
                $demo = array_filter([
                    $p->gender ? ucfirst($p->gender) : null,
                    $p->age    ? $p->age . ' yrs'   : null,
                    !is_null($p->bmi) ? 'BMI ' . number_format((float) $p->bmi, 1) : null,
                ]);
                if ($demo) { $bullets[] = 'Patient: ' . implode(' · ', $demo) . '.'; }
                $bullets[] = 'Identity verification: ' . (strtolower($p->id_verified_status ?? '') === 'verified' ? 'verified.' : 'not verified.');
            }
            $offerings = $topCase->caseOfferings->map(fn($co) => optional($co->offering)->name)->filter()->implode(', ');
            if ($offerings) { $bullets[] = 'Requested offerings: ' . $offerings . '.'; }
            $reasons = collect($topCase->triage_reasons ?? []);
            if ($reasons->isNotEmpty()) { $bullets[] = 'Triage signals: ' . $reasons->take(3)->implode('; ') . '.'; }
            foreach (collect($intake)->take(4) as $a) {
                $bullets[] = $a['q'] . ': ' . \Illuminate\Support\Str::limit((string) $a['a'], 80);
            }
            $aiSummary = $bullets;
        }

        $heldCases   = $cases->getCollection()->filter(fn($c) => $c->hold_status || $c->status === 'support')->values();
        $messages    = \App\Models\Message::with(['patient', 'case.patient'])->latest()->limit(6)->get();
        $note        = \App\Models\ClinicalNote::with(['clinician.user', 'case.patient'])->latest()->first();
        $reasonCodes = [
            'Dose exceeds protocol titration step',
            'Active workflow hold not cleared',
            'Identity verification incomplete',
            'Allergy conflict requires clinician review',
            'Out-of-catalog request for patient state',
        ];

        return view('clinician.cases.queue', compact(
            'cases', 'clinician', 'triageMetrics',
            'topCase', 'intake', 'aiSummary',
            'heldCases', 'messages', 'note', 'reasonCodes'
        ));
    }

    public function show(string $uuid)
    {
        $case = PatientCase::with([
            'patient', 'partner', 'clinician.user',
            'caseOfferings.offering',
            'diseases', 'clinicalNotes.clinician.user',
            'orders.pharmacy', 'messages', 'files', 'tags',
            'questionnaireResponses.questionnaire',
            'questionnaireResponses.answers',
            'casePrescriptions.clinician.user',
            'casePrescriptions.medications',
        ])->where('uuid', $uuid)->firstOrFail();

        // Count unread patient messages before marking them read
        $unreadMessageCount = $case->messages
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->count();

        if ($unreadMessageCount > 0) {
            $case->messages()
                ->where('direction', 'inbound')
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return view('clinician.cases.show', compact('case', 'unreadMessageCount'));
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
            ->approved()
            ->when($categoryIds->count(), fn($q) => $q->whereIn('category_id', $categoryIds))
            ->orderBy('name')
            ->get(['id', 'name', 'internal_name', 'compound_formula', 'refills',
                   'quantity', 'days_supply', 'dispense_unit', 'days_until_dispense', 'directions']);

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

        $prescription = null;

        DB::transaction(function () use ($request, $case, $clinician, &$prescription) {
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

        // Complete immediately — no manual pharmacy step required.
        $this->stateMachine->complete($case);

        // Generate the signed prescription PDF and queue it for pharmacy dispatch.
        // Best-effort and fully feature-flagged — a failure here must never break case completion.
        try {
            $document = $this->prescriptionDocuments->generate($case, $prescription);
            $this->pharmacyDispatch->queue($document);
        } catch (\Throwable $e) {
            Log::error('Prescription document/dispatch generation failed', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Fire prescription_written webhook alongside case_approved + case_completed.
        $this->webhooks->dispatch($case->partner_id, 'prescription_written', [
            'case_id'          => $case->uuid,
            'external_id'      => $case->external_id,
            'patient_id'       => $case->patient->uuid ?? null,
            'clinician_name'   => $clinician->full_name,
            'clinician_npi'    => $clinician->npi,
            'diagnoses'        => $prescription->diagnoses,
            'meds_prescribed'  => $prescription->load('medications')->medications->map(fn($m) => [
                'name'             => $m->name,
                'compound_formula' => $m->compound_formula,
                'refills'          => (string) $m->refills,
                'quantity'         => (string) $m->quantity,
                'days_supply'      => (string) $m->days_supply,
                'dispense_unit'    => $m->dispense_unit,
            ])->toArray(),
            'timestamp'        => now()->timestamp,
        ]);

        return redirect()->route('clinician.cases.show', $uuid)
            ->with('success', 'Prescription submitted — case completed.');
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

        // case_approved webhook is fired by the state machine transition above.

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

    public function pollMessages(Request $request, string $uuid)
    {
        $case    = PatientCase::where('uuid', $uuid)->firstOrFail();
        $afterId = (int) $request->query('after', 0);

        $messages = $case->messages()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get(['id', 'body', 'sender_type', 'created_at'])
            ->map(fn($msg) => [
                'id'          => $msg->id,
                'body'        => $msg->body,
                'sender_type' => $msg->sender_type,
                'time'        => $msg->created_at->format('H:i'),
                'date'        => $msg->created_at->format('Y-m-d'),
                'date_label'  => $msg->created_at->isToday()
                                    ? 'Today'
                                    : ($msg->created_at->isYesterday()
                                        ? 'Yesterday'
                                        : $msg->created_at->format('M j, Y')),
            ]);

        return response()->json(['messages' => $messages]);
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

    public function uploadFile(Request $request, string $uuid)
    {
        $request->validate([
            'file'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . FileUploadService::MAX_SIZE_KB,
            'type'  => 'nullable|in:lab_result,id_doc,consent,medical_necessity,intake,other',
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

    public function downloadPrescriptionDocument(string $uuid, string $documentUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $document = \App\Models\PrescriptionDocument::where('uuid', $documentUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        $disk = config('dispatch.documents_disk', 'local');

        abort_unless(
            \Illuminate\Support\Facades\Storage::disk($disk)->exists($document->document_path),
            404,
            'Prescription document is no longer available.'
        );

        return \Illuminate\Support\Facades\Storage::disk($disk)->download(
            $document->document_path,
            "prescription-{$case->uuid}.pdf",
            ['Content-Type' => 'application/pdf']
        );
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

    public function batchPreflight(Request $request)
    {
        $request->validate(['uuids' => 'required|array|min:1|max:20', 'uuids.*' => 'string']);

        $clinician = Auth::user()->clinician;
        $results   = [];

        $cases = PatientCase::with(['patient', 'caseOfferings.offering'])
            ->whereIn('uuid', $request->uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($request->uuids as $uuid) {
            $case = $cases->get($uuid);

            if (!$case) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Case not found.'];
                continue;
            }

            if ($case->triage !== PatientCase::TRIAGE_GREEN) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Only Green-triage cases are batch-eligible.'];
                continue;
            }

            if ($case->hold_status) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Case has an active workflow hold.'];
                continue;
            }

            if ($case->status === PatientCase::STATUS_SUPPORT) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Case is escalated to support.'];
                continue;
            }

            if (!in_array($case->status, [PatientCase::STATUS_WAITING, PatientCase::STATUS_ASSIGNED])) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Case is not in a reviewable status.'];
                continue;
            }

            if ($case->status === PatientCase::STATUS_ASSIGNED && $case->clinician_id !== $clinician?->id) {
                $results[$uuid] = ['pass' => false, 'reason' => 'Case is assigned to another clinician.'];
                continue;
            }

            $idv = strtolower($case->patient?->id_verified_status ?? '');
            if ($idv !== 'verified') {
                $results[$uuid] = ['pass' => false, 'reason' => 'Patient identity not verified.'];
                continue;
            }

            $state = strtoupper($case->patient_state ?? $case->patient?->state ?? '');
            if ($state) {
                foreach ($case->caseOfferings as $co) {
                    if ($co->offering && !$co->offering->isAvailableInState($state)) {
                        $results[$uuid] = ['pass' => false, 'reason' => "Offering \"{$co->offering->name}\" not available in {$state}."];
                        continue 2;
                    }
                }
            }

            $results[$uuid] = [
                'pass'    => true,
                'patient' => $case->patient?->full_name ?? 'Patient',
                'triage'  => $case->triage,
                'status'  => $case->status,
                'state'   => $state ?: '—',
            ];
        }

        return response()->json($results);
    }

    public function batchSubmit(Request $request)
    {
        $request->validate(['uuids' => 'required|array|min:1|max:20', 'uuids.*' => 'string']);

        $clinician = Auth::user()->clinician;

        if (!$clinician) {
            return response()->json(['error' => 'No clinician profile found for this user.'], 403);
        }

        $results = [];

        $cases = PatientCase::with(['patient', 'caseOfferings.offering'])
            ->whereIn('uuid', $request->uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($request->uuids as $uuid) {
            $case = $cases->get($uuid);

            if (!$case) {
                $results[$uuid] = ['success' => false, 'error' => 'Case not found.'];
                continue;
            }

            // Re-run preflight guards — never trust client-side pass list
            if ($case->triage !== PatientCase::TRIAGE_GREEN || $case->hold_status) {
                $results[$uuid] = ['success' => false, 'error' => 'Failed re-validation (triage/hold changed).'];
                continue;
            }

            if ($case->status === PatientCase::STATUS_ASSIGNED && $case->clinician_id !== $clinician->id) {
                $results[$uuid] = ['success' => false, 'error' => 'Case reassigned since preflight.'];
                continue;
            }

            if (!in_array($case->status, [PatientCase::STATUS_WAITING, PatientCase::STATUS_ASSIGNED])) {
                $results[$uuid] = ['success' => false, 'error' => 'Case status changed since preflight.'];
                continue;
            }

            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($case, $clinician) {
                    if ($case->status === PatientCase::STATUS_WAITING) {
                        $this->stateMachine->assignToClinician($case, $clinician);
                        $case->refresh();
                    }
                    $this->stateMachine->approve($case, $clinician->id);
                });

                $results[$uuid] = ['success' => true, 'patient' => $case->patient?->full_name ?? 'Patient'];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Batch submit failed for case', [
                    'uuid'  => $uuid,
                    'error' => $e->getMessage(),
                ]);
                $results[$uuid] = ['success' => false, 'error' => 'Transition failed: ' . $e->getMessage()];
            }
        }

        return response()->json($results);
    }
}
