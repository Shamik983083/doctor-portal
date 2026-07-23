<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Events\CaseMessageSent;
use App\Http\Controllers\Controller;
use App\Models\CasePrescription;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\Offering;
use App\Models\PatientCase;
use App\Models\PatientFile;
use App\Models\User;
use App\Notifications\NewCaseMessage;
use App\Services\AiAssistService;
use App\Services\CaseStateMachine;
use App\Services\EhrRecordService;
use App\Services\FileUploadService;
use App\Services\PharmacyDispatchService;
use App\Services\PrescriptionDocumentService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    public function __construct(
        private CaseStateMachine            $stateMachine,
        private WebhookDispatcher           $webhooks,
        private FileUploadService           $fileUploader,
        private PrescriptionDocumentService $prescriptionDocuments,
        private PharmacyDispatchService     $pharmacyDispatch,
        private EhrRecordService            $ehrRecords,
        private AiAssistService             $aiAssist,
    ) {}

    public function queue(Request $request)
    {
        $clinician = Auth::user()->clinician;

        // caseQuestions + questionnaire answers are eager-loaded so the quick
        // review's "View source answers" can show the full intake (Devin msg
        // 2271) without a query per case.
        $cases = PatientCase::with([
                'patient', 'partner', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
            ])
            ->withCount(['messages as unread_messages_count' => fn ($q) => $q->where('direction', 'inbound')->where('is_read', false),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('state'), fn ($q) => $q->where(
                fn ($q) => $q->where('patient_state', $request->state)
                    ->orWhereHas('patient', fn ($p) => $p->where('state', $request->state))
            ))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('patient', fn ($p) => $p->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ['%'.$request->search.'%'])
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

        // Quick-review panel · top case drives summary, intake, and triage findings
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
            $bullets = ['Triage classification: ' . $topCase->triageLabel() . ' · ' . $topCase->triageMeaning()];
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
        $messages    = \App\Models\Message::with(['patient', 'case'])
            ->where('direction', 'inbound')
            ->latest()
            ->get()
            ->unique(fn($m) => $m->patient_id ?? $m->case?->patient_id)
            ->take(6)
            ->values();
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
            'heldCases', 'messages', 'reasonCodes'
        ));
    }

    public function myCases(Request $request)
    {
        $clinician   = Auth::user()->clinician;
        $tab         = $request->get('tab', 'active');

        $activeStatuses    = ['assigned', 'support', 'processing'];
        $completedStatuses = ['approved'];
        $cancelledStatuses = ['cancelled'];

        // Same eager loads as the queue, so My Cases can render the identical
        // review grid + quick review (Devin msg 2283).
        $base = PatientCase::with([
                'patient', 'partner', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
            ])
            ->where('clinician_id', $clinician->id)
            ->withCount(['messages as unread_messages_count' => fn ($q) =>
                $q->where('direction', 'inbound')->where('is_read', false)
            ])
            ->when($request->filled('search'), fn ($q) =>
                $q->whereHas('patient', fn ($p) =>
                    $p->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ['%'.$request->search.'%'])
                )
            )
            ->when($request->filled('triage'), fn ($q) => $q->where('triage', $request->triage));

        $counts = [
            'active'      => (clone $base)->whereIn('status', $activeStatuses)->count(),
            'escalations' => (clone $base)->where('status', 'support')->count(),
            'support'     => (clone $base)->where('status', 'support')
                ->whereHas('messages', fn ($q) => $q->where('direction', 'inbound')->where('is_read', false))
                ->count(),
            'completed'   => (clone $base)->whereIn('status', $completedStatuses)->count(),
            'cancelled'   => (clone $base)->whereIn('status', $cancelledStatuses)->count(),
            'all'         => (clone $base)->count(),
        ];

        $cases = (clone $base)
            ->when($tab === 'active',      fn ($q) => $q->whereIn('status', $activeStatuses))
            ->when($tab === 'escalations', fn ($q) => $q->where('status', 'support'))
            ->when($tab === 'support',     fn ($q) => $q->where('status', 'support')
                ->whereHas('messages', fn ($q) => $q->where('direction', 'inbound')->where('is_read', false)))
            ->when($tab === 'completed',   fn ($q) => $q->whereIn('status', $completedStatuses))
            ->when($tab === 'cancelled',   fn ($q) => $q->whereIn('status', $cancelledStatuses))
            ->orderByRaw("FIELD(status, 'waiting','support','assigned','approved','processing','completed','cancelled')")
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('clinician.cases.my-cases', compact('cases', 'clinician', 'counts', 'tab'));
    }

    /**
     * Refills (Devin msg 2285): the same review grid as the queue, filtered to
     * check-ins from patients this clinician has already seen. A refill is a
     * re-bill / check-in (is_refill), and continuity routes it back to the doctor
     * who treated the patient before, so this is that doctor's returning book.
     *
     * A case shows here when it is a refill AND either it is already assigned to
     * this clinician (continuity did its job) or its patient has a prior
     * completed case with this clinician (their last visit was with me).
     */
    public function refills(Request $request)
    {
        $clinician = Auth::user()->clinician;

        $seenPatientIds = PatientCase::where('clinician_id', $clinician->id)
            ->where('status', 'completed')
            ->pluck('patient_id')
            ->filter()
            ->unique()
            ->values();

        $cases = PatientCase::with([
                'patient', 'partner', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
            ])
            ->withCount(['messages as unread_messages_count' => fn ($q) =>
                $q->where('direction', 'inbound')->where('is_read', false)
            ])
            ->where('is_refill', true)
            ->where(function ($q) use ($clinician, $seenPatientIds) {
                $q->where('clinician_id', $clinician->id);
                if ($seenPatientIds->isNotEmpty()) {
                    $q->orWhereIn('patient_id', $seenPatientIds);
                }
            })
            ->when($request->filled('triage'), fn ($q) => $q->where('triage', $request->triage))
            ->orderByRaw("FIELD(triage, 'red', 'yellow', 'green') DESC")
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('clinician.cases.refills', compact('cases', 'clinician'));
    }

    /**
     * Messages For Provider (Devin msg 2256), the inbox screen from the design
     * preview. Lists this clinician's cases that have a conversation, most
     * recent message first, with the unread count and the last message preview.
     *
     * The thread itself lives on the case screen (with the existing send/poll
     * endpoints), so each row links there. Real data throughout: a doctor with
     * no messages sees an empty state, not a mock.
     */
    public function messagesInbox(Request $request)
    {
        $clinician = Auth::user()->clinician;

        $cases = PatientCase::with(['patient', 'partner'])
            ->where('clinician_id', $clinician->id)
            ->whereHas('messages')
            ->withCount(['messages as unread_messages_count' => fn ($q) =>
                $q->where('direction', 'inbound')->where('is_read', false)
            ])
            ->withMax('messages as last_message_at', 'created_at')
            ->when($request->filled('unread'), fn ($q) =>
                $q->whereHas('messages', fn ($m) =>
                    $m->where('direction', 'inbound')->where('is_read', false)
                )
            )
            ->orderByDesc('last_message_at')
            ->paginate(25)
            ->withQueryString();

        // The last message body per case, for the preview line. Loaded in one
        // pass keyed by case id rather than a query per row.
        $latest = \App\Models\Message::whereIn('case_id', $cases->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('case_id')
            ->map(fn ($group) => $group->first());

        // The conversation open in the right pane (Devin msg 2294): the one named
        // in ?case=, else the most recent. Its full thread is loaded and its
        // inbound messages are marked read now that the provider is looking.
        $selected = null;
        $thread = collect();
        if ($cases->isNotEmpty()) {
            $selected = $request->filled('case')
                ? $cases->firstWhere('uuid', $request->get('case'))
                : null;
            $selected = $selected ?: $cases->first();

            $selected->loadMissing('patient', 'partner');
            $thread = $selected->messages()->orderBy('created_at')->get();

            $selected->messages()
                ->where('direction', 'inbound')->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return view('clinician.messages.index', compact('cases', 'clinician', 'latest', 'selected', 'thread'));
    }

    /**
     * LAW 4: licensure is a hard gate, not a filter.
     *
     * A case for a patient in state X may only ever be viewed, assigned,
     * approved or prescribed by a clinician holding a licence in state X. Before
     * this, licensure was checked ONLY during auto-routing, which meant a doctor
     * could still open, self-assign, approve and prescribe a case for a state
     * they are not licensed in by going at it directly. Routing is a filter;
     * this is the gate.
     *
     * Admins are exempt on purpose: the clinician routes are shared with the
     * admin role, and an admin is not the prescriber. The gate exists to stop a
     * PRESCRIBER acting outside their licence.
     *
     * Throws 403 rather than 404: unlike cross-tenant access, the case legitimately
     * exists and the clinician needs to know why they are being stopped, otherwise
     * this reads as a broken link and generates a support ticket.
     */
    private function assertLicensedForCase(PatientCase $case): void
    {
        $user = Auth::user();
        $clinician = $user?->clinician;

        if (! $clinician) {
            return;   // admin or support acting on the shared routes, not a prescriber
        }

        $state = $case->patient_state ?: $case->patient?->state;

        if ($clinician->canPracticeIn($state)) {
            return;
        }

        // LAW 2: no PHI in logs. The case id and clinician id are internal
        // identifiers; the patient, their name and their state are not recorded.
        Log::warning('Licensure gate blocked a case action', [
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
        ]);

        abort(403, 'You are not licensed in this patient\'s state, so this case cannot be opened or actioned by you.');
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

        // LAW 4: licensure gates viewing, not only assignment.
        $this->assertLicensedForCase($case);

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

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
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
            ->when($categoryIds->count(), fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->orderBy('name')
            ->get(['id', 'name', 'internal_name', 'compound_formula', 'refills',
                'quantity', 'days_supply', 'dispense_unit', 'days_until_dispense', 'directions', 'levels']);

        return view('clinician.cases.prescribe', compact('case', 'offerings'));
    }

    public function prescribe(Request $request, string $uuid)
    {
        $request->validate([
            'diagnoses' => 'required|string',
            'directions' => 'nullable|string',
            'medical_necessity' => 'nullable|string',
            'medications' => 'nullable|array',
            'medications.*.offering_id' => 'nullable|exists:offerings,id',
            'medications.*.name' => 'required_with:medications|string|max:255',
            'medications.*.compound_formula' => 'nullable|string',
            'medications.*.refills' => 'nullable|integer|min:0',
            'medications.*.quantity' => 'nullable|numeric|min:0',
            'medications.*.days_supply' => 'nullable|integer|min:0',
            'medications.*.dispense_unit' => 'nullable|string|max:100',
            'medications.*.days_until_dispense' => 'nullable|integer|min:0',
            // Rich dosing from the full review model (Devin msg 2279): frequency,
            // term, and a dose per month of the term. Optional, stored alongside
            // the flat columns in the `dosing` json.
            'medications.*.frequency' => 'nullable|string|max:60',
            'medications.*.term' => 'nullable|string|max:20',
            'medications.*.months' => 'nullable|array',
            'medications.*.months.*' => 'nullable|string|max:60',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $prescription = null;

        DB::transaction(function () use ($request, $case, $clinician, &$prescription) {
            $prescription = CasePrescription::create([
                'case_id' => $case->id,
                'clinician_id' => $clinician->id,
                'diagnoses' => $request->input('diagnoses'),
                'directions' => $request->input('directions'),
                'medical_necessity' => $request->input('medical_necessity'),
                'prescribed_at' => now(),
            ]);

            foreach ($request->input('medications', []) as $med) {
                // Keep only the real month doses, so a 3M ladder with a blank
                // month is not stored as an empty step.
                $months = array_values(array_filter($med['months'] ?? [], fn ($m) => filled($m)));
                $dosing = null;
                if (filled($med['frequency'] ?? null) || filled($med['term'] ?? null) || $months !== []) {
                    $dosing = [
                        'medication' => $med['name'],
                        'frequency'  => $med['frequency'] ?? null,
                        'term'       => $med['term'] ?? null,
                        'months'     => $months,
                    ];
                }

                $prescription->medications()->create([
                    'offering_id' => $med['offering_id'] ?? null,
                    'name' => $med['name'],
                    'compound_formula' => $med['compound_formula'] ?? null,
                    'dosing' => $dosing,
                    'refills' => $med['refills'] ?? null,
                    'quantity' => $med['quantity'] ?? null,
                    'days_supply' => $med['days_supply'] ?? null,
                    'dispense_unit' => $med['dispense_unit'] ?? null,
                    'days_until_dispense' => $med['days_until_dispense'] ?? null,
                ]);
            }

            $this->stateMachine->approve($case, $clinician->id);
        });

        // Complete immediately · no manual pharmacy step required.
        $this->stateMachine->complete($case);

        // Generate the signed prescription PDF and queue it for pharmacy dispatch.
        // Best-effort and fully feature-flagged · a failure here must never break case completion.
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
            'case_id' => $case->uuid,
            'external_id' => $case->external_id,
            'patient_id' => $case->patient->uuid ?? null,
            'clinician_name' => $clinician->full_name,
            'clinician_npi' => $clinician->npi,
            'diagnoses' => $prescription->diagnoses,
            'meds_prescribed' => $prescription->load('medications')->medications->map(fn ($m) => [
                'name' => $m->name,
                'compound_formula' => $m->compound_formula,
                'refills' => (string) $m->refills,
                'quantity' => (string) $m->quantity,
                'days_supply' => (string) $m->days_supply,
                'dispense_unit' => $m->dispense_unit,
            ])->toArray(),
            'timestamp' => now()->timestamp,
        ]);

        return redirect()->route('clinician.cases.show', $uuid)
            ->with('success', 'Prescription submitted · case completed.');
    }

    public function approve(Request $request, string $uuid)
    {
        $request->validate([
            'note'                  => 'nullable|string',
            'decisions'             => 'nullable|array',
            'decisions.*.name'      => 'required_with:decisions|string|max:255',
            'decisions.*.decision'  => 'required_with:decisions|in:approve,deny,none',
            'decisions.*.term'      => 'nullable|string|max:60',
            'decisions.*.frequency' => 'nullable|string|max:60',
            'decisions.*.months'    => 'nullable|array',
            'decisions.*.months.*'  => 'nullable|string|max:60',
            'decisions.*.refills'   => 'nullable|string|max:10',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;
        $decisions = $request->input('decisions', []);

        $this->stateMachine->approve($case, $clinician->id);

        $note = null;

        if ($request->note) {
            $note = ClinicalNote::create([
                'case_id' => $case->id,
                'clinician_id' => $clinician->id,
                'type' => 'approval',
                'note' => $request->note,
            ]);
        }

        // case_approved webhook is fired by the state machine transition above.

        /*
         * Hand the approved case and the provider's OWN note to the EHR seam.
         *
         * Best-effort and fully feature-flagged, exactly like the pharmacy
         * dispatch above it: with the shipped defaults this builds the payload
         * and stores it as a preview without sending anything. A failure here
         * must never undo a clinical decision the provider has already made, so
         * it is caught and logged rather than allowed to break the approval.
         *
         * The note passed on is the persisted ClinicalNote, which by definition
         * has been through the provider's hands. An AI draft they never accepted
         * cannot reach an EHR by this path.
         */
        try {
            $this->ehrRecords->recordApproval($case->fresh(), $note, $decisions);
        } catch (\Throwable $e) {
            Log::error('EHR record build/push failed', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return redirect()->route('clinician.cases.show', $uuid)->with('success', 'Case approved.');
    }

    /**
     * Draft the clinical note for this case with AI assist.
     *
     * Returns the draft to the caller and persists NOTHING. The provider edits
     * it and submits it through approve() like any other note, which is what
     * keeps a human between the model and the chart.
     */
    public function draftNote(Request $request, string $uuid)
    {
        $request->validate([
            'provider_text' => 'nullable|string',
            'decisions'     => 'nullable|array',
        ]);

        $case = PatientCase::with(['patient', 'partner'])->where('uuid', $uuid)->firstOrFail();

        $draft = $this->aiAssist->draftClinicalNote(
            $case,
            $request->input('decisions', []),
            $request->input('provider_text')
        );

        return response()->json([
            'text'   => $draft['text'],
            'source' => $draft['source'],   // 'model' or 'local', so the UI can be honest about which
            'notice' => $draft['notice'],
        ]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $this->stateMachine->cancel($case, $request->reason, $clinician->id, 'clinician');

        ClinicalNote::create([
            'case_id' => $case->id,
            'clinician_id' => $clinician->id,
            'type' => 'cancellation',
            'note' => $request->reason,
        ]);

        return redirect()->route('clinician.queue')->with('success', 'Case declined.');
    }

    public function addNote(Request $request, string $uuid)
    {
        $type = $request->input('type', 'general');

        $rules = ['type' => 'nullable|in:general,soap,progress', 'is_private' => 'boolean'];

        if ($type === 'soap') {
            $rules += ['soap_s' => 'required|string', 'soap_o' => 'required|string',
                       'soap_a' => 'required|string', 'soap_p' => 'required|string'];
        } elseif ($type === 'progress') {
            $rules += ['prog_status'   => 'required|string', 'prog_changes'  => 'required|string',
                       'prog_response' => 'required|string', 'prog_next'     => 'required|string'];
        } else {
            $rules['note'] = 'required|string';
        }

        $request->validate($rules);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        if ($type === 'soap') {
            $noteContent = json_encode([
                's' => $request->input('soap_s'),
                'o' => $request->input('soap_o'),
                'a' => $request->input('soap_a'),
                'p' => $request->input('soap_p'),
            ]);
        } elseif ($type === 'progress') {
            $noteContent = json_encode([
                'status'   => $request->input('prog_status'),
                'changes'  => $request->input('prog_changes'),
                'response' => $request->input('prog_response'),
                'next'     => $request->input('prog_next'),
            ]);
        } else {
            $noteContent = $request->input('note');
        }

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => $type,
            'note'         => $noteContent,
            'is_private'   => $request->boolean('is_private'),
        ]);

        $this->webhooks->dispatch($case->partner_id, 'clinical_note_added', [
            'case_id' => $case->uuid,
            'timestamp' => now()->timestamp,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function escalateToSupport(Request $request, string $uuid)
    {
        $request->validate(['support_note' => 'required|string|max:1000']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $this->stateMachine->escalateToSupport($case, $request->input('support_note'));

        ClinicalNote::create([
            'case_id' => $case->id,
            'clinician_id' => $clinician->id,
            'type' => 'general',
            'note' => 'Escalated to support: '.$request->input('support_note'),
        ]);

        return back()->with('success', 'Case escalated to support. Partner has been notified.');
    }

    public function pollMessages(Request $request, string $uuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $afterId = (int) $request->query('after', 0);

        $messages = $case->messages()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get(['id', 'body', 'sender_type', 'created_at'])
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'body' => $msg->body,
                'sender_type' => $msg->sender_type,
                'time' => $msg->created_at->format('H:i'),
                'date' => $msg->created_at->format('Y-m-d'),
                'date_label' => $msg->created_at->isToday()
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

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $message = Message::create([
            'case_id' => $case->id,
            'patient_id' => $case->patient_id,
            'clinician_id' => $clinician->id,
            'partner_id' => $case->partner_id,
            'direction' => 'outbound',
            'channel' => 'portal',
            'sender_type' => 'clinician',
            'body' => $request->body,
        ]);

        try {
            broadcast(new CaseMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Reverb broadcast failed for message '.$message->id.': '.$e->getMessage());
        }

        $this->webhooks->dispatch($case->partner_id, 'message_created', [
            'case_id' => $case->uuid,
            'sender' => 'clinician',
            'timestamp' => now()->timestamp,
        ]);

        try {
            $message->load(['case.clinician.user', 'patient']);
            User::role(['admin', 'super_admin'])->each(
                fn ($admin) => $admin->notify(new NewCaseMessage($message))
            );
        } catch (\Throwable $e) {
            Log::warning('Message notification failed: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $message->id,
                'body' => $message->body,
                'sender_type' => $message->sender_type,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
                'date_label' => 'Today',
            ]);
        }

        return back()->with('success', 'Message sent.');
    }

    public function uploadFile(Request $request, string $uuid)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.FileUploadService::MAX_SIZE_KB,
            'type' => 'nullable|in:lab_result,id_doc,consent,medical_necessity,intake,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

        $this->fileUploader->store(
            $request->file('file'),
            $request->input('type', 'other'),
            caseId: $case->id,
            patientId: $case->patient_id,
            partnerId: $case->partner_id,
            notes: $request->input('notes'),
        );

        return back()->with('success', 'File uploaded successfully.');
    }

    public function downloadPrescriptionDocument(string $uuid, string $documentUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

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

    public function downloadFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function previewFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

        $file = PatientFile::where('uuid', $fileUuid)
            ->where('case_id', $case->id)
            ->firstOrFail();

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    public function deleteFile(string $uuid, string $fileUuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

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

            /*
             * LAW 4 in the batch surface. Batch is batch UI, not batch judgment,
             * so the licensure gate applies to every case in the batch exactly as
             * it does to a single case. Failing here rather than at submit means
             * the clinician sees WHY before they attest to anything.
             */
            if (! $clinician->canPracticeIn($case->patient_state ?: $case->patient?->state)) {
                $results[$uuid] = ['pass' => false, 'reason' => 'You are not licensed in this patient\'s state.'];
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
                'pass'      => true,
                'patient'   => $case->patient?->full_name ?? 'Patient',
                'triage'    => $case->triage,
                'status'    => $case->status,
                'state'     => $state ?: ' · ',
                'offerings' => $case->caseOfferings->map(fn($co) => $co->offering ? [
                    'id'                  => $co->offering->id,
                    'name'                => $co->offering->name,
                    'internal_name'       => $co->offering->internal_name ?? '',
                    'compound_formula'    => $co->offering->compound_formula ?? '',
                    'refills'             => $co->offering->refills ?? '',
                    'quantity'            => $co->offering->quantity ?? '',
                    'days_supply'         => $co->offering->days_supply ?? '',
                    'dispense_unit'       => $co->offering->dispense_unit ?? '',
                    'days_until_dispense' => $co->offering->days_until_dispense ?? '',
                    'directions'          => $co->offering->directions ?? '',
                ] : null)->filter()->values()->toArray(),
            ];
        }

        return response()->json($results);
    }

    public function batchSubmit(Request $request)
    {
        $request->validate([
            'uuids'                             => 'required|array|min:1|max:20',
            'uuids.*'                           => 'string',
            'diagnoses'                         => 'required|string',
            'directions'                        => 'nullable|string',
            'medical_necessity'                 => 'nullable|string',
            'medications'                       => 'nullable|array',
            'medications.*.offering_id'         => 'nullable|exists:offerings,id',
            'medications.*.name'                => 'required_with:medications|string|max:255',
            'medications.*.compound_formula'    => 'nullable|string',
            'medications.*.refills'             => 'nullable|integer|min:0',
            'medications.*.quantity'            => 'nullable|numeric|min:0',
            'medications.*.days_supply'         => 'nullable|integer|min:0',
            'medications.*.dispense_unit'       => 'nullable|string|max:100',
            'medications.*.days_until_dispense' => 'nullable|integer|min:0',
        ]);

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

            // Re-run preflight guards · never trust client-side pass list
            if ($case->triage !== PatientCase::TRIAGE_GREEN || $case->hold_status) {
                $results[$uuid] = ['success' => false, 'error' => 'Failed re-validation (triage/hold changed).'];
                continue;
            }

            /*
             * LAW 4, re-checked at submit and not only at preflight. Preflight is
             * advisory and its result reaches the client, so trusting it here
             * would make the gate bypassable by replaying a submit with a uuid
             * that never passed. This is the enforcing check.
             */
            if (! $clinician->canPracticeIn($case->patient_state ?: $case->patient?->state)) {
                Log::warning('Licensure gate blocked a batch approval', [
                    'case_id'      => $case->id,
                    'clinician_id' => $clinician->id,
                ]);
                $results[$uuid] = ['success' => false, 'error' => 'Not licensed in this patient\'s state.'];
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

            $prescription = null;

            try {
                DB::transaction(function () use ($request, $case, $clinician, &$prescription) {
                    if ($case->status === PatientCase::STATUS_WAITING) {
                        $this->stateMachine->assignToClinician($case, $clinician);
                        $case->refresh();
                    }

                    $prescription = CasePrescription::create([
                        'case_id'           => $case->id,
                        'clinician_id'      => $clinician->id,
                        'diagnoses'         => $request->input('diagnoses'),
                        'directions'        => $request->input('directions'),
                        'medical_necessity' => $request->input('medical_necessity'),
                        'prescribed_at'     => now(),
                    ]);

                    foreach ($request->input('medications', []) as $med) {
                        $prescription->medications()->create([
                            'offering_id'         => $med['offering_id'] ?? null,
                            'name'                => $med['name'],
                            'compound_formula'    => $med['compound_formula'] ?? null,
                            'refills'             => $med['refills'] ?? null,
                            'quantity'            => $med['quantity'] ?? null,
                            'days_supply'         => $med['days_supply'] ?? null,
                            'dispense_unit'       => $med['dispense_unit'] ?? null,
                            'days_until_dispense' => $med['days_until_dispense'] ?? null,
                        ]);
                    }

                    $this->stateMachine->approve($case, $clinician->id);
                });

                $this->stateMachine->complete($case);

                try {
                    $document = $this->prescriptionDocuments->generate($case, $prescription);
                    $this->pharmacyDispatch->queue($document);
                } catch (\Throwable $e) {
                    Log::error('Batch prescription document/dispatch failed', [
                        'uuid'  => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->webhooks->dispatch($case->partner_id, 'prescription_written', [
                    'case_id'         => $case->uuid,
                    'external_id'     => $case->external_id,
                    'patient_id'      => $case->patient->uuid ?? null,
                    'clinician_name'  => $clinician->full_name,
                    'clinician_npi'   => $clinician->npi,
                    'diagnoses'       => $prescription->diagnoses,
                    'meds_prescribed' => $prescription->load('medications')->medications->map(fn($m) => [
                        'name'             => $m->name,
                        'compound_formula' => $m->compound_formula,
                        'refills'          => (string) $m->refills,
                        'quantity'         => (string) $m->quantity,
                        'days_supply'      => (string) $m->days_supply,
                        'dispense_unit'    => $m->dispense_unit,
                    ])->toArray(),
                    'timestamp'       => now()->timestamp,
                ]);

                $results[$uuid] = ['success' => true, 'patient' => $case->patient?->full_name ?? 'Patient'];
            } catch (\Throwable $e) {
                Log::error('Batch submit failed for case', [
                    'uuid'  => $uuid,
                    'error' => $e->getMessage(),
                ]);
                $results[$uuid] = ['success' => false, 'error' => 'Transition failed: ' . $e->getMessage()];
            }
        }

        return response()->json($results);
    }
}
