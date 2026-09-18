<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Events\CaseMessageSent;
use App\Http\Controllers\Controller;
use App\Models\CaseOffering;
use App\Models\CasePrescription;
use App\Models\CasePrescriptionDiagnosis;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\Offering;
use App\Services\Icd10Ruleset;
use App\Models\PatientCase;
use App\Models\PatientFile;
use App\Models\User;
use App\Notifications\CaseEscalatedToDoctorAdmin;
use App\Notifications\ClinicianNewMessage;
use App\Notifications\NewCaseMessage;
use App\Notifications\PartnerNewCaseMessage;
use App\Services\AiAssistService;
use App\Services\CaseStateMachine;
use App\Services\EhrRecordService;
use App\Services\FileUploadService;
use App\Services\PharmacyDispatchService;
use App\Services\PrescriptionDocumentService;
use App\Contracts\SmsAdapter;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Mail\PrescriptionApprovalMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        private SmsAdapter                  $sms,
    ) {}

    public function queue(Request $request)
    {
        $clinician = Auth::user()->clinician;

        // E17: stamp when this clinician last visited the queue so the dashboard
        // can show how many new cases arrived since their last look.
        if ($clinician) {
            $clinician->updateQuietly(['cases_last_viewed_at' => now()]);
        }

        // caseQuestions + questionnaire answers are eager-loaded so the quick
        // review's "View source answers" can show the full intake (Devin msg
        // 2271) without a query per case.
        $cases = PatientCase::with([
                'patient', 'partner', 'subStorefront', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
                'partner.accessibleOfferings',
            ])
            ->withCount(['messages as unread_messages_count' => fn ($q) => $q->where('direction', 'inbound')->where('is_read', false),
            ])
            ->when(!$request->filled('status'), fn ($q) => $q->whereNotIn('status', [PatientCase::STATUS_COMPLETED, PatientCase::STATUS_CANCELLED]))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('state'), fn ($q) => $q->where(
                fn ($q) => $q->where('patient_state', $request->state)
                    ->orWhereHas('patient', fn ($p) => $p->where('state', $request->state))
            ))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('patient', fn ($p) => $p->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ['%'.$request->search.'%'])
            ))
            ->when($request->filled('partner_id'), fn($q) => $q->where('partner_id', $request->partner_id))
            ->when($request->filled('triage'), fn($q) => $q->where('triage', $request->triage))
            ->when($request->boolean('mine') && $clinician, fn($q) => $q->where('clinician_id', $clinician->id))
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
                ->filter(fn($q) => filled($q->question) && filled($q->answer))
                ->map(fn($q) => ['q' => $q->question, 'a' => $q->answer]);
            $intake = $fromQuestions->isNotEmpty()
                ? $fromQuestions->values()
                : $topCase->questionnaireResponses->flatMap->answers
                    ->map(fn($a) => ['q' => $a->question_text, 'a' => $a->answer])
                    ->filter(fn($r) => filled($r['q']))->values();
        }

        $aiSummary = null;
        if ($topCase) {
            try {
                $aiSummary = $this->aiAssist->draftCaseSummary($topCase);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::debug('AI case summary skipped', ['error' => $e->getMessage()]);
            }
        }

        // Prior-visit map for refill cases on this page: uuid → priorCase.
        // At most one query per refill case (paginator max 20). Used by the
        // quick-review panel and _review-grid to show the prior visit panel.
        $priorCasesMap = [];
        $cases->getCollection()->filter(fn($c) => $c->isRefillRequest())->each(function ($rc) use (&$priorCasesMap) {
            $prior = PatientCase::priorCompletedCase($rc);
            if ($prior) {
                $priorCasesMap[$rc->uuid] = $prior;
            }
        });

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
            'heldCases', 'messages', 'reasonCodes', 'priorCasesMap'
        ));
    }

    public function myCases(Request $request)
    {
        $clinician   = Auth::user()->clinician;
        $tab         = $request->get('tab', 'active');

        // D15: sub-filter for the escalations tab — whitelist prevents injection.
        $validEscalationSubs = [
            PatientCase::ESCALATION_SUPPORT,
            PatientCase::ESCALATION_DOCTOR_ADMIN,
            PatientCase::ESCALATION_CLIENT_RESPONSE,
        ];
        $escalationSub = ($tab === 'escalations' && in_array($request->get('escalation_sub'), $validEscalationSubs, true))
            ? $request->get('escalation_sub')
            : null;

        $activeStatuses    = ['assigned', 'support', 'processing'];
        $completedStatuses = ['approved', 'completed'];
        $cancelledStatuses = ['cancelled'];

        // Same eager loads as the queue, so My Cases can render the identical
        // review grid + quick review (Devin msg 2283).
        $base = PatientCase::with([
                'patient', 'partner', 'subStorefront', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
                'casePrescription.medications',
                'partner.accessibleOfferings',
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

        // D15: per-sub-category counts for the escalations tab filter chips.
        // Always computed so the chip counts stay accurate even when filtered.
        $escalationCounts = [
            PatientCase::ESCALATION_SUPPORT         => (clone $base)->where('status', 'support')->where('escalation_target', PatientCase::ESCALATION_SUPPORT)->count(),
            PatientCase::ESCALATION_DOCTOR_ADMIN    => (clone $base)->where('status', 'support')->where('escalation_target', PatientCase::ESCALATION_DOCTOR_ADMIN)->count(),
            PatientCase::ESCALATION_CLIENT_RESPONSE => (clone $base)->where('status', 'support')->where('escalation_target', PatientCase::ESCALATION_CLIENT_RESPONSE)->count(),
        ];

        $cases = (clone $base)
            ->when($tab === 'active',      fn ($q) => $q->whereIn('status', $activeStatuses))
            ->when($tab === 'escalations', fn ($q) => $q->where('status', 'support')
                ->when($escalationSub, fn ($q) => $q->where('escalation_target', $escalationSub)))
            ->when($tab === 'support',     fn ($q) => $q->where('status', 'support')
                ->whereHas('messages', fn ($q) => $q->where('direction', 'inbound')->where('is_read', false)))
            ->when($tab === 'completed',   fn ($q) => $q->whereIn('status', $completedStatuses))
            ->when($tab === 'cancelled',   fn ($q) => $q->whereIn('status', $cancelledStatuses))
            ->orderByRaw("FIELD(status, 'waiting','support','assigned','approved','processing','completed','cancelled')")
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $priorCasesMap = [];
        $cases->getCollection()->filter(fn($c) => $c->isRefillRequest())->each(function ($rc) use (&$priorCasesMap) {
            $prior = PatientCase::priorCompletedCase($rc);
            if ($prior) {
                $priorCasesMap[$rc->uuid] = $prior;
            }
        });

        return view('clinician.cases.my-cases', compact(
            'cases', 'clinician', 'counts', 'tab', 'priorCasesMap',
            'escalationSub', 'escalationCounts',
        ));
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
                'patient', 'partner', 'subStorefront', 'caseOfferings.offering',
                'caseQuestions', 'questionnaireResponses.answers',
                'casePrescription.medications',
                'partner.accessibleOfferings',
            ])
            ->withCount(['messages as unread_messages_count' => fn ($q) =>
                $q->where('direction', 'inbound')->where('is_read', false)
            ])
            ->where('is_refill', true)
            ->whereNotIn('status', ['approved', 'completed', 'cancelled'])
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

        $priorCasesMap = [];
        $cases->getCollection()->each(function ($rc) use (&$priorCasesMap) {
            $prior = PatientCase::priorCompletedCase($rc);
            if ($prior) {
                $priorCasesMap[$rc->uuid] = $prior;
            }
        });

        return view('clinician.cases.refills', compact('cases', 'clinician', 'priorCasesMap'));
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
            ->when($request->filter === 'unread', fn ($q) =>
                $q->whereHas('messages', fn ($m) =>
                    $m->where('direction', 'inbound')->where('is_read', false)
                )
            )
            ->when($request->filter === 'waiting', fn ($q) =>
                $q->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('messages as mw')
                        ->whereColumn('mw.case_id', 'cases.id')
                        ->where('mw.direction', 'inbound')
                        ->whereRaw('mw.created_at = (SELECT MAX(m2.created_at) FROM messages m2 WHERE m2.case_id = cases.id)');
                })
            )
            ->when($request->filter === 'read', fn ($q) =>
                $q->whereDoesntHave('messages', fn ($m) =>
                    $m->where('direction', 'inbound')->where('is_read', false)
                )
            )
            ->when($request->filled('search'), fn ($q) =>
                $q->whereHas('patient', fn ($p) =>
                    $p->where('first_name', 'like', '%' . $request->search . '%')
                      ->orWhere('last_name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%')
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
        // in ?case=, else the most recent. Thread loaded; mark-as-read is deferred
        // to a JS fetch() once the conversation is visually focused, so the sidebar
        // badge reflects the true unread count at page render time.
        $selected = null;
        $thread = collect();
        if ($cases->isNotEmpty()) {
            $selected = $request->filled('case')
                ? $cases->firstWhere('uuid', $request->get('case'))
                : null;
            $selected = $selected ?: $cases->first();

            $selected->loadMissing('patient', 'partner');
            $thread = $selected->messages()->orderBy('created_at')->get();
        }

        return view('clinician.messages.index', compact('cases', 'clinician', 'latest', 'selected', 'thread'));
    }

    /**
     * Mark all unread inbound messages in a conversation as read.
     * Called via fetch() from the messages inbox JS once the thread is visually
     * focused — keeps the sidebar badge accurate at page render time.
     */
    public function markConversationRead(string $uuid): \Illuminate\Http\JsonResponse
    {
        $clinician = Auth::user()->clinician;

        $case = PatientCase::where('uuid', $uuid)
            ->where('clinician_id', $clinician->id)
            ->firstOrFail();

        $updated = $case->messages()
            ->where('direction', 'inbound')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['marked_read' => $updated]);
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
    /**
     * True when any medication name contains a GLP-1 drug family identifier.
     * Mirrors the JS family() logic: semaglutide or tirzepatide substring match.
     *
     * @param string[] $medNames
     */
    private function isGlpPrescription(array $medNames): bool
    {
        foreach ($medNames as $name) {
            $n = strtolower((string) $name);
            if (str_contains($n, 'semaglutide') || str_contains($n, 'tirzepatide')) {
                return true;
            }
        }
        return false;
    }

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
            'patient', 'partner', 'subStorefront', 'clinician.user',
            'caseOfferings.offering',
            'diseases', 'clinicalNotes.clinician.user',
            'orders.pharmacy', 'messages', 'files', 'tags',
            'questionnaireResponses.questionnaire',
            'questionnaireResponses.answers',
            'casePrescriptions.clinician.user',
            'casePrescriptions.medications',
            'casePrescriptions.diagnosesCodes',
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
            'patient', 'partner', 'subStorefront', 'clinician.user',
            'caseOfferings.offering.category',
        ])->where('uuid', $uuid)->firstOrFail();

        // C12: if there is already a draft prescription, send the provider straight
        // to the review page rather than letting them start a duplicate.
        $draft = CasePrescription::where('case_id', $case->id)
            ->where('review_status', CasePrescription::REVIEW_DRAFT)
            ->latest()
            ->first();

        if ($draft) {
            $target = route('clinician.cases.prescribe.review', $case->uuid)
                . (request()->boolean('modal') ? '?modal=1' : '');
            return redirect($target)
                ->with('info', 'A draft prescription is waiting for your review.');
        }

        // Filter offerings by categories already on the case; if none, show all.
        // Always include offerings directly on the case even if their category_id is
        // null — a bundle's second medication would otherwise be excluded from the
        // dropdown and render with no pre-selected value.
        $categoryIds      = $case->caseOfferings
            ->pluck('offering.category_id')
            ->filter()
            ->unique()
            ->values();
        $caseOfferingIds  = $case->caseOfferings
            ->pluck('offering_id')
            ->filter()
            ->unique()
            ->values();

        $offeringColumns = ['offerings.id', 'offerings.name', 'offerings.internal_name', 'offerings.compound_formula',
            'offerings.refills', 'offerings.quantity', 'offerings.days_supply', 'offerings.dispense_unit',
            'offerings.days_until_dispense', 'offerings.directions', 'offerings.levels', 'offerings.sig',
            'offerings.category_id', 'offerings.formulation_type'];

        $offerings = $case->partner
            ->accessibleOfferings()
            ->with('category')
            ->where('offerings.is_active', true)
            ->approved()
            ->where(function ($q) use ($categoryIds, $caseOfferingIds) {
                if ($categoryIds->count()) {
                    $q->whereIn('offerings.category_id', $categoryIds);
                }
                if ($caseOfferingIds->count()) {
                    $q->orWhereIn('offerings.id', $caseOfferingIds);
                }
            })
            ->orderBy('offerings.name')
            ->get($offeringColumns);

        // The accessibleOfferings() JOIN excludes any offering not in the partner's pivot
        // (e.g. NAD+, Tesamorelin on a bundle case whose catalog changed after submission).
        // Fetch those missing offerings directly so the dropdown always pre-selects correctly.
        $missingIds = $caseOfferingIds->diff($offerings->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $missing = Offering::with('category')
                ->whereIn('id', $missingIds)
                ->get($offeringColumns);
            $offerings = $offerings->merge($missing)->sortBy('name')->values();
        }

        $medicalNecessityPreset = \App\Models\Setting::get('medical_necessity_preset', '');

        // C9: auto-populate ICD-10 suggestions from the case's clinical intake
        $icd10Suggestions = Icd10Ruleset::for($case);

        // Prior-visit panel: load the most recent completed case for this patient
        // (same partner) so the provider can reference past prescription + intake.
        // Only runs for refill cases; null-safe to never crash if no prior exists.
        $priorCase = $case->isRefillRequest() ? PatientCase::priorCompletedCase($case) : null;

        // 4.3: Check-in answers for the panel display — only for detected refill cases.
        $checkInResponses = collect();
        if ($case->isRefillRequest()) {
            $case->loadMissing([
                'questionnaireResponses.questionnaire',
                'questionnaireResponses.answers',
            ]);
            $checkInResponses = $case->questionnaireResponses
                ->filter(fn ($r) => $r->completed_at !== null
                    && $r->questionnaire?->purpose === 'check_in')
                ->sortByDesc('completed_at')
                ->values();
        }

        // Extract dose hint so the prescribe form can auto-select medication + month levels.
        // Searches ALL completed questionnaire responses (any purpose) so it works for both
        // new cases (intake form may include prior-dose questions) and refill cases.
        // loadMissing is idempotent — free when already loaded for refill cases above.
        $checkInDoseHint = ['last_dose' => null, 'continuation' => null];
        $case->loadMissing([
            'questionnaireResponses.questionnaire',
            'questionnaireResponses.answers',
        ]);
        foreach ($case->questionnaireResponses->filter(fn ($r) => $r->completed_at !== null) as $qResp) {
            foreach ($qResp->answers as $ans) {
                $qt = strtolower($ans->question_text ?? '');
                if (!$checkInDoseHint['last_dose'] && str_contains($qt, 'last dose')) {
                    $checkInDoseHint['last_dose'] = $ans->answer;
                }
                if (!$checkInDoseHint['continuation'] && str_contains($qt, 'how would you like to continue')) {
                    $checkInDoseHint['continuation'] = $ans->answer;
                }
                if ($checkInDoseHint['last_dose'] && $checkInDoseHint['continuation']) {
                    break 2;
                }
            }
        }

        // Pass the requested month_frequency so the prescribe form can pre-select the
        // duration dropdown.
        // Fallback chain (first non-null wins):
        //   1. case_offerings.month_frequency (integer, set by the partner API)
        //   2. case_offerings.frequency string ("12 month" → 12, legacy field)
        //   3. clinical_intake.term ("12M" → 12, tenant's intake payload)
        $requestedMonthFrequency = $case->caseOfferings->first()?->month_frequency;
        if ($requestedMonthFrequency === null) {
            $freqStr = $case->caseOfferings->first()?->frequency ?? '';
            if (preg_match('/\b(\d+)\b/', $freqStr, $freqMatch)) {
                $requestedMonthFrequency = (int) $freqMatch[1];
            }
        }
        if ($requestedMonthFrequency === null) {
            $clinTerm = $case->clinical_intake['term'] ?? '';
            if (is_string($clinTerm) && preg_match('/\b(\d+)\b/', $clinTerm, $termMatch)) {
                $requestedMonthFrequency = (int) $termMatch[1];
            }
        }

        // Build per-offering metadata for the prescribe form JS so it can render
        // bundle-aware rows (filtered dropdowns, atomic remove) vs standalone rows.
        $caseOfferingsData = $case->caseOfferings->map(fn ($co) => [
            'offering_id'  => $co->offering_id,
            'bundle_group' => $co->bundle_group,
            'product_key'  => $co->product_key,
        ])->values();

        // Formulation hint from the partner API payload — drives offering auto-select
        // on new cases where no check-in questionnaire hint is available yet.
        $formulationHint = $case->caseOfferings->first()?->formulation;

        return view('clinician.cases.prescribe', compact(
            'case', 'offerings', 'medicalNecessityPreset', 'icd10Suggestions',
            'priorCase', 'checkInResponses', 'checkInDoseHint', 'requestedMonthFrequency',
            'caseOfferingsData', 'formulationHint'
        ));
    }

    public function prescribe(Request $request, string $uuid)
    {
        $request->validate([
            // C9: structured ICD-10 codes instead of a free-text string
            'diagnoses'                         => 'required|array|min:1',
            'diagnoses.*.code'                  => 'required|string|max:30',
            'diagnoses.*.description'           => 'required|string|max:255',
            // C8: directions become an internal ClinicalNote
            'directions'                        => 'nullable|string',
            'medical_necessity'                 => 'nullable|string',
            'visit_type'                        => 'nullable|in:asynchronous,synchronous',
            'medications'                       => 'nullable|array',
            'medications.*.offering_id'         => 'nullable|exists:offerings,id',
            'medications.*.name'                => 'required_with:medications|string|max:255',
            'medications.*.compound_formula'    => 'nullable|string',
            'medications.*.refills'             => 'nullable|integer|min:0',
            'medications.*.quantity'            => 'nullable|numeric|min:0',
            'medications.*.days_supply'         => 'nullable|integer|min:0',
            'medications.*.dispense_unit'       => 'nullable|string|max:100',
            'medications.*.days_until_dispense' => 'nullable|integer|min:0',
            'medications.*.frequency'           => 'nullable|string|max:60',
            'medications.*.term'                => 'nullable|string|max:20',
            'medications.*.months'              => 'nullable|array',
            'medications.*.months.*'            => 'nullable|string|max:60',
            'medications.*.sigs'                => 'nullable|array',
            'medications.*.sigs.*'              => 'nullable|string|max:500',
            'medications.*.formulas'            => 'nullable|array',
            'medications.*.formulas.*'          => 'nullable|string|max:120',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        // C10: persist the provider's visit-type choice on the case.
        if ($request->filled('visit_type')) {
            $case->update(['visit_type' => $request->input('visit_type')]);
        }

        // C8: pre-load offerings so we can resolve the effective SIG per partner
        // without an N+1 query inside the medication loop.
        $offeringIds = collect($request->input('medications', []))
            ->pluck('offering_id')->filter()->unique()->values()->all();

        // Second-layer category guard: the prescribeForm() dropdown already restricts
        // to the case's category client-side; this check prevents a direct POST bypass.
        if ($offeringIds) {
            $case->loadMissing('caseOfferings.offering');
            $allowedCategoryIds = $case->caseOfferings
                ->pluck('offering.category_id')
                ->filter()
                ->unique()
                ->values();

            if ($allowedCategoryIds->isNotEmpty()) {
                $invalid = Offering::whereIn('id', $offeringIds)
                    ->whereNotIn('category_id', $allowedCategoryIds)
                    ->count();

                if ($invalid > 0) {
                    return back()->withErrors([
                        'medications' => 'The selected medication does not match the intake category for this case.',
                    ]);
                }
            }
        }

        $offeringsMap = Offering::with(['partners' => fn ($q) => $q->where('partners.id', $case->partner_id)])
            ->whereIn('id', $offeringIds)
            ->get()
            ->keyBy('id');

        // C9: build a comma-joined string for the legacy diagnoses column.
        $diagnosesArray = $request->input('diagnoses', []);
        $diagnosesLegacy = collect($diagnosesArray)->map(fn ($d) => $d['code'])->implode(', ');

        // C12: discard any existing draft for this case before creating a fresh one.
        CasePrescription::where('case_id', $case->id)
            ->where('review_status', CasePrescription::REVIEW_DRAFT)
            ->delete();

        $prescription = DB::transaction(function () use (
            $request, $case, $clinician, $diagnosesArray, $diagnosesLegacy, $offeringsMap
        ): CasePrescription {
            // C12: status='draft' — approve()/complete() fire only after the provider
            // confirms on the review page.
            $prescription = CasePrescription::create([
                'case_id'          => $case->id,
                'clinician_id'     => $clinician->id,
                'diagnoses'        => $diagnosesLegacy,
                'medical_necessity' => $request->input('medical_necessity'),
                'prescribed_at'    => now(),
                'review_status'    => CasePrescription::REVIEW_DRAFT,
            ]);

            // C9: save structured ICD-10 codes
            foreach ($diagnosesArray as $i => $diag) {
                CasePrescriptionDiagnosis::create([
                    'case_prescription_id' => $prescription->id,
                    'icd_code'             => $diag['code'],
                    'description'          => $diag['description'],
                    'sort_order'           => $i,
                ]);
            }

            foreach ($request->input('medications', []) as $med) {
                $months = array_values(array_filter($med['months'] ?? [], fn ($m) => filled($m)));
                // Per-month SIG overrides submitted alongside each dose slot.
                // Preserve only non-empty values; null the whole array when all are blank
                // so old prescriptions without sigs are unaffected.
                $rawSigs = $med['sigs'] ?? [];
                $sigs = array_values(array_map(fn ($s) => trim((string) $s), $rawSigs));
                $sigsHaveContent = collect($sigs)->contains(fn ($s) => $s !== '');

                $rawFormulas = $med['formulas'] ?? [];
                $formulas = array_values(array_map(fn ($f) => trim((string) $f), $rawFormulas));
                $formulasHaveContent = collect($formulas)->contains(fn ($f) => $f !== '');

                $dosing = null;
                if (filled($med['frequency'] ?? null) || filled($med['term'] ?? null) || $months !== []) {
                    $dosing = [
                        'medication' => $med['name'],
                        'frequency'  => $med['frequency'] ?? null,
                        'term'       => $med['term'] ?? null,
                        'months'     => $months,
                        'sigs'       => $sigsHaveContent ? $sigs : null,
                        'formulas'   => $formulasHaveContent ? $formulas : null,
                    ];
                }

                // C8: resolve the effective SIG for this partner at prescription time.
                $offering = $offeringsMap->get($med['offering_id'] ?? '');
                $sig = $offering ? $offering->effectiveSig($case->partner) : null;

                $prescription->medications()->create([
                    'offering_id'         => $med['offering_id'] ?? null,
                    'name'                => $med['name'],
                    'compound_formula'    => $med['compound_formula'] ?? null,
                    'dosing'              => $dosing,
                    'refills'             => $med['refills'] ?? null,
                    'quantity'            => $med['quantity'] ?? null,
                    'days_supply'         => $med['days_supply'] ?? null,
                    'dispense_unit'       => $med['dispense_unit'] ?? null,
                    'days_until_dispense' => $med['days_until_dispense'] ?? null,
                    'sig'                 => $sig,
                ]);
            }

            // C8: charting note (provider's clinical rationale) → ClinicalNote, not directions column.
            // GLP-1 prescriptions always get a "Diagnosis:" line appended; the guard
            // str_contains(..., 'Diagnosis:') prevents a double-append if the provider
            // typed their own diagnosis block or clicked save twice.
            $medNames = collect($request->input('medications', []))->pluck('name')->filter()->all();
            $noteText = trim((string) $request->input('directions', ''));
            if ($this->isGlpPrescription($medNames) && ! str_contains($noteText, 'Diagnosis:')) {
                $block    = Icd10Ruleset::glpNoteBlock($case);
                $noteText = $noteText !== '' ? $noteText . "\n\n" . $block : $block;
            }
            if ($noteText !== '') {
                ClinicalNote::create([
                    'case_id'      => $case->id,
                    'clinician_id' => $clinician->id,
                    'type'         => 'charting',
                    'note'         => $noteText,
                    'is_private'   => true,
                ]);
            }

            return $prescription;
        });

        // C12: redirect to review page — approve()/complete()/webhook fire on confirmation.
        $reviewUrl = route('clinician.cases.prescribe.review', $case->uuid)
            . (request()->boolean('modal') ? '?modal=1' : '');
        return redirect($reviewUrl);
    }

    // C12: show the draft prescription for review before the provider confirms.
    public function prescribeReview(string $uuid)
    {
        $case = PatientCase::with(['patient', 'partner', 'clinician.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->assertLicensedForCase($case);

        $prescription = CasePrescription::where('case_id', $case->id)
            ->where('review_status', CasePrescription::REVIEW_DRAFT)
            ->with(['medications', 'diagnosesCodes'])
            ->latest()
            ->firstOrFail();

        // C12: draft the patient-facing approval message so the provider can edit it
        // before confirming. Always non-fatal — the deterministic fallback ensures the
        // textarea is never empty even when AI is disabled or the model fails.
        $approvalDraft = ['text' => '', 'source' => 'local', 'notice' => null];
        try {
            $approvalDraft = $this->aiAssist->draftApprovalMessage($case, $prescription);
        } catch (\Throwable $e) {
            Log::debug('C12: AI approval message draft skipped', ['error' => $e->getMessage()]);
            // Compose a minimal deterministic fallback so the field is never blank.
            $meds    = $prescription->medications->pluck('name')->filter()->implode(', ');
            $partner = $case->partner?->name ?? 'our clinic';
            $approvalDraft['text'] = 'Hi ' . ($case->patient?->first_name ?? 'there') . ",\n\n"
                . 'Your prescription' . ($meds ? " for {$meds}" : '') . ' has been approved and is being processed. '
                . "You can expect shipping details shortly. Please reply here with any questions.\n\n"
                . "— The {$partner} Care Team";
        }

        return view('clinician.cases.prescribe-review', compact('case', 'prescription', 'approvalDraft'));
    }

    // C12: provider has reviewed and confirmed — approve(), complete(), fire webhooks.
    public function prescribeConfirm(Request $request, string $uuid)
    {
        $request->validate([
            'charting_note' => 'nullable|string',
            'message_body'  => 'required|string|max:5000',
        ]);

        $case = PatientCase::with(['patient', 'partner'])->where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $prescription = CasePrescription::where('case_id', $case->id)
            ->where('review_status', CasePrescription::REVIEW_DRAFT)
            ->with(['medications', 'diagnosesCodes'])
            ->latest()
            ->firstOrFail();

        // Append the GLP-1 diagnosis block before persisting the charting note.
        // The guard on 'Diagnosis:' prevents double-append when the provider
        // already typed a diagnosis block on the initial prescribe form (step 1).
        $medNames  = $prescription->medications->pluck('name')->filter()->all();
        $noteText  = trim((string) $request->input('charting_note', ''));
        if ($this->isGlpPrescription($medNames) && ! str_contains($noteText, 'Diagnosis:')) {
            $block    = Icd10Ruleset::glpNoteBlock($case);
            $noteText = $noteText !== '' ? $noteText . "\n\n" . $block : $block;
        }

        $clinicalNote = null;
        DB::transaction(function () use ($noteText, $case, $clinician, $prescription, &$clinicalNote) {
            $prescription->update([
                'review_status' => CasePrescription::REVIEW_CONFIRMED,
                'charting_note' => $noteText ?: null,
            ]);

            // C12: persist the charting note as a ClinicalNote on confirmation.
            if ($noteText !== '') {
                $clinicalNote = ClinicalNote::create([
                    'case_id'      => $case->id,
                    'clinician_id' => $clinician->id,
                    'type'         => 'charting',
                    'note'         => $noteText,
                    'is_private'   => true,
                ]);
            }

            $this->stateMachine->approve($case, $clinician->id);
            CaseOffering::where('case_id', $case->id)->update(['status' => 'prescribed']);
        });

        // C8: include SIG per medication; C9: send structured codes
        // Fire prescription_written before case_completed so partners have
        // prescription data before the case is marked done.
        $case->loadMissing('caseOfferings');
        $prescription->loadMissing('medications.offering');
        $coByOffering = $case->caseOfferings->keyBy('offering_id');
        $this->webhooks->dispatch($case->partner_id, 'prescription_written', [
            'case_id'                  => $case->uuid,
            'external_id'              => $case->external_id,
            'patient_id'               => $case->patient->uuid ?? null,
            'clinician_name'           => $clinician->full_name,
            'clinician_npi'            => $clinician->npi,
            'clinician_license_state'  => $case->patient_state,
            'clinician_license_number' => collect($clinician->licensed_states ?? [])
                ->firstWhere('state', $case->patient_state)['license_number'] ?? $clinician->license_number,
            'clinician_phone'          => $clinician->phone,
            'clinician_email'          => Auth::user()->email,
            'diagnoses'                => $prescription->diagnosesCodes->map(fn ($d) => [
                'code'        => $d->icd_code,
                'description' => $d->description,
            ])->toArray() ?: $prescription->diagnoses,
            'meds_prescribed' => $prescription->medications->map(function ($m) use ($coByOffering) {
                $co = $coByOffering->get($m->offering_id);
                return [
                    'name'                => $m->name,
                    'offering_id'         => $m->offering?->uuid,
                    'product_key'         => $co?->product_key ?: null,
                    'month_frequency'     => $co?->month_frequency ?: null,
                    'compound_formula'    => $m->compound_formula,
                    'sig'                 => $m->sig,
                    'refills'             => (string) $m->refills,
                    'quantity'            => (string) $m->quantity,
                    'days_supply'         => (string) $m->days_supply,
                    'dispense_unit'       => $m->dispense_unit,
                    'days_until_dispense' => $m->days_until_dispense,
                    'dosing'              => $m->dosing,
                ];
            })->toArray(),
            'timestamp' => now()->timestamp,
        ]);

        $this->stateMachine->complete($case);

        // C12: create the patient-facing approval message the provider just edited
        // and send it via the portal + webhook. Null-safe on patient_id: if the case
        // somehow has no patient, we log and skip rather than block the approval.
        if ($case->patient_id) {
            $message = Message::create([
                'case_id'      => $case->id,
                'patient_id'   => $case->patient_id,
                'clinician_id' => $clinician->id,
                'partner_id'   => $case->partner_id,
                'direction'    => 'outbound',
                'channel'      => 'portal',
                'sender_type'  => 'clinician',
                'body'         => $request->input('message_body'),
            ]);

            try {
                broadcast(new CaseMessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('C12: Reverb broadcast failed for approval message.', [
                    'case_id'    => $case->id,
                    'message_id' => $message->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $this->webhooks->dispatch($case->partner_id, 'message_created', [
                'case_id'   => $case->uuid,
                'sender'    => 'clinician',
                'timestamp' => now()->timestamp,
            ]);

            // PAUSED by client request — re-enable by uncommenting the block below.
            // Email copy to patient — non-fatal, queued, skipped when no address or opt-out.
            // $patient = $case->patient;
            // if ($patient?->email && $patient->email_opt_in) {
            //     try {
            //         Mail::to($patient->email)->queue(new PrescriptionApprovalMail($case, $message));
            //     } catch (\Throwable $e) {
            //         Log::warning('C12: Prescription approval email failed to queue.', [
            //             'case_id' => $case->id,
            //             'error'   => $e->getMessage(),
            //         ]);
            //     }
            // }
        } else {
            Log::warning('C12: prescribeConfirm skipped approval message — case has no patient_id.', [
                'case_id' => $case->id,
            ]);
        }

        try {
            $document = $this->prescriptionDocuments->generate($case, $prescription);
            $this->pharmacyDispatch->queue($document);
        } catch (\Throwable $e) {
            Log::error('Prescription document/dispatch generation failed', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
        }

        /*
         * EHR: push the approved prescription to the partner's EHR.
         *
         * Best-effort, identical posture to pharmacy dispatch above: a failure
         * here must never undo a clinical decision the provider has already made.
         * With the shipped defaults (EHR_ENABLED=false) this stores a preview row
         * and nothing leaves the system.
         *
         * All medications on the confirmed prescription are "approve" decisions;
         * there is no explicit decline in the C12 prescribe flow (declining is a
         * separate rejectConfirm path that does not reach here).
         */
        try {
            $prescription->loadMissing('medications');
            $ehrDecisions = $prescription->medications->map(fn ($med) => [
                'name'      => $med->name,
                'decision'  => 'approve',
                'term'      => data_get($med->dosing, 'term'),
                'frequency' => data_get($med->dosing, 'frequency'),
                'months'    => data_get($med->dosing, 'months') ?? [],
                'refills'   => $med->refills,
            ])->toArray();

            $this->ehrRecords->recordApproval($case->fresh(), $clinicalNote, $ehrDecisions);
        } catch (\Throwable $e) {
            Log::error('EHR record build/push failed', [
                'case_id' => $case->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return redirect()->route('clinician.cases.show', $uuid)
            ->with('success', 'Prescription confirmed · case completed.');
    }

    // C12: discard the draft prescription and return to the prescribe form.
    public function prescribeDiscard(string $uuid)
    {
        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);

        CasePrescription::where('case_id', $case->id)
            ->where('review_status', CasePrescription::REVIEW_DRAFT)
            ->delete();

        $target = route('clinician.cases.prescribe.form', $uuid)
            . (request()->boolean('modal') ? '?modal=1' : '');
        return redirect($target)
            ->with('info', 'Draft discarded. Start a new prescription below.');
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

        $case = PatientCase::with(['patient', 'partner', 'clinician.user', 'clinician.supervisorAssignments.supervisorPhysician.user'])
            ->where('uuid', $uuid)->firstOrFail();

        $draft = $this->aiAssist->draftClinicalNote(
            $case,
            $request->input('decisions', []),
            $request->input('provider_text')
        );

        // Use the logged-in clinician as the prescriber (case may not be formally
        // assigned yet at the prescribe-review stage).
        $authClinician  = auth()->user()?->clinician;
        $clinicianName  = $authClinician?->full_name ?? $case->clinician?->full_name ?? '';
        $patientState   = strtoupper(trim((string) ($case->patient?->state ?? '')));
        $supervisorName = '';
        $activeClinician = $authClinician ?? $case->clinician;
        if ($patientState && $activeClinician) {
            $activeClinician->load('supervisorAssignments.supervisorPhysician.user');
            $assignment = $activeClinician->supervisorAssignments
                ->where('state', $patientState)->first();
            if ($assignment?->supervisorPhysician?->user) {
                $supervisorName = $assignment->supervisorPhysician->user->name;
            }
        }

        return response()->json([
            'text'            => $draft['text'],
            'source'          => $draft['source'],
            'notice'          => $draft['notice'],
            'clinician_name'  => $clinicianName,
            'supervisor_name' => $supervisorName,
        ]);
    }

    public function draftRejection(Request $request, string $uuid)
    {
        $case = PatientCase::with(['patient', 'caseOfferings.offering'])->where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);

        $clin    = $case->queueClinical();
        $name    = $case->patient?->full_name ?? 'the patient';
        $product = $clin['product'] ?? null;
        $productText = $product && $product !== '-' ? "for {$product}" : '';

        $text = "After a thorough clinical review, the prescription request {$productText} cannot be approved at this time. "
              . "The submitted information does not meet the clinical criteria required to safely prescribe the requested medication. "
              . "Please consult with a licensed healthcare provider for alternative treatment options.";

        $notice = 'Draft composed from case data. Edit and personalise before submitting.';

        return response()->json(['text' => $text, 'notice' => $notice]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        $case = PatientCase::with(['patient', 'caseOfferings.offering', 'partner'])->where('uuid', $uuid)->firstOrFail();

        // LAW 4: licensure gates every action, not only auto-routing.
        $this->assertLicensedForCase($case);

        // A9: Generate patient-facing rejection draft, then redirect to review screen
        // before the case is actually cancelled — provider must confirm the message first.
        $draft = $this->aiAssist->draftRejectionMessage($case, $request->reason);

        session()->flash('reject_draft_text',   $draft['text']);
        session()->flash('reject_draft_notice', $draft['notice']);
        session()->flash('reject_reason',       $request->reason);

        return redirect()->route('clinician.cases.reject-draft', $case->uuid);
    }

    public function rejectDraft(string $uuid)
    {
        $case = PatientCase::with(['patient', 'partner', 'caseOfferings.offering'])
            ->where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);

        // Guard: only reachable via the cancel flow redirect (session must carry the draft).
        if (! session()->has('reject_draft_text')) {
            return redirect()->route('clinician.cases.show', $uuid)
                ->with('error', 'Rejection session expired. Please try again.');
        }

        $draftText   = session('reject_draft_text');
        $draftNotice = session('reject_draft_notice');
        $reason      = session('reject_reason');

        return view('clinician.cases.reject-draft', compact('case', 'draftText', 'draftNotice', 'reason'));
    }

    public function rejectConfirm(Request $request, string $uuid)
    {
        $request->validate([
            'message_body' => 'required|string|max:5000',
            'reason'       => 'required|string|max:2000',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $this->stateMachine->cancel($case, $request->reason, $clinician->id, 'clinician');

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => 'cancellation',
            'note'         => $request->reason,
        ]);

        $message = Message::create([
            'case_id'      => $case->id,
            'patient_id'   => $case->patient_id,
            'clinician_id' => $clinician->id,
            'partner_id'   => $case->partner_id,
            'direction'    => 'outbound',
            'channel'      => 'portal',
            'sender_type'  => 'clinician',
            'body'         => $request->message_body,
        ]);

        // Notify partner: case_cancelled fires automatically from the state machine above.
        // message_created must be dispatched separately so partners can read the rejection body.
        $this->webhooks->dispatch($case->partner_id, 'message_created', [
            'case_id'   => $case->uuid,
            'sender'    => 'clinician',
            'body'      => $request->message_body,
            'reason'    => 'case_declined',
            'timestamp' => now()->timestamp,
        ]);

        return redirect()->route('clinician.queue')->with('success', 'Case declined and patient notified.');
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

    /**
     * Forward a patient message to the partner support team WITHOUT changing
     * the case status. Opens a parallel escalation thread so the case continues
     * its normal workflow (assigned/processing) while the chat runs alongside.
     */
    public function forwardToSupport(Request $request, string $uuid)
    {
        $request->validate([
            'note'           => 'nullable|string|max:1000',
            'quoted_message' => 'nullable|string|max:10000',
        ]);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();
        $this->assertLicensedForCase($case);

        if ($case->escalation_target === PatientCase::ESCALATION_SUPPORT) {
            return back()->with('error', 'A support thread is already open for this case. Use the Support Thread tab to send messages.');
        }

        $note = trim($request->input('note', ''));
        $this->stateMachine->startSupportThread($case, $note);

        $quotedMessage = trim($request->input('quoted_message', ''));
        if ($quotedMessage) {
            $clinician     = Auth::user()->clinician;
            $clinicianName = Auth::user()->name ?? 'Clinician';
            $body          = ($note ? $note . "\n\n" : '') . "Forwarded patient message:\n\"{$quotedMessage}\"";

            $message = Message::create([
                'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                'case_id'      => $case->id,
                'clinician_id' => $clinician?->id,
                'partner_id'   => $case->partner_id,
                'direction'    => 'outbound',
                'channel'      => 'escalation',
                'sender_type'  => 'clinician',
                'body'         => $body,
                'is_read'      => true,
            ]);

            try {
                broadcast(new CaseMessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('Forward-to-support broadcast failed: ' . $e->getMessage());
            }

            try {
                $case->loadMissing('partner.users');
                $case->partner?->users->each(
                    fn ($u) => $u->notify(new PartnerNewCaseMessage($case, $body, $clinicianName))
                );
            } catch (\Throwable $e) {
                Log::warning('Forward-to-support partner notification failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Support thread opened. The partner team has been notified.');
    }

    public function escalateToDoctorAdmin(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $case = PatientCase::where('uuid', $uuid)->firstOrFail();

        $this->assertLicensedForCase($case);
        $clinician = Auth::user()->clinician;

        $this->stateMachine->escalateToDoctorAdmin($case, $request->input('reason'));

        ClinicalNote::create([
            'case_id'      => $case->id,
            'clinician_id' => $clinician->id,
            'type'         => 'general',
            'note'         => 'Escalated to Doctor Admin: ' . $request->input('reason'),
        ]);

        try {
            $clinician->admins->each(
                fn ($admin) => $admin->notify(new CaseEscalatedToDoctorAdmin($case, $request->input('reason')))
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Doctor Admin escalation notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Case escalated to Doctor Admin.');
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

        // Escalation context: when the case has an active support thread directed at
        // the partner (regardless of case status), route into the 'escalation' channel.
        $isEscalationReply = $case->escalation_target === PatientCase::ESCALATION_SUPPORT
            && $case->support_at !== null;

        $message = Message::create([
            'case_id'      => $case->id,
            'patient_id'   => $case->patient_id,
            'clinician_id' => $clinician->id,
            'partner_id'   => $case->partner_id,
            'direction'    => 'outbound',
            'channel'      => $isEscalationReply ? 'escalation' : 'portal',
            'sender_type'  => 'clinician',
            'body'         => $request->body,
        ]);

        try {
            broadcast(new CaseMessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Reverb broadcast failed for message '.$message->id.': '.$e->getMessage());
        }

        if ($isEscalationReply) {
            // Fire dedicated escalation webhook so partner systems know to poll.
            $this->webhooks->dispatch($case->partner_id, 'escalation_message_sent', [
                'case_id'    => $case->uuid,
                'patient_id' => $case->patient?->uuid,
                'sender'     => 'clinician',
                'timestamp'  => now()->timestamp,
            ]);

            // Notify all partner users so they see the reply in their portal.
            try {
                $case->loadMissing('partner.users');
                $clinicianName = $clinician->user?->name ?? 'Clinician';
                $case->partner?->users->each(
                    fn ($u) => $u->notify(new PartnerNewCaseMessage($case, $request->body, $clinicianName))
                );
            } catch (\Throwable $e) {
                Log::warning('Escalation partner notification failed: ' . $e->getMessage());
            }
        } else {
            $this->webhooks->dispatch($case->partner_id, 'message_created', [
                'case_id'   => $case->uuid,
                'sender'    => 'clinician',
                'timestamp' => now()->timestamp,
            ]);
        }

        try {
            $message->load(['case.clinician.user', 'patient']);
            User::role(['admin', 'super_admin'])->each(
                fn ($admin) => $admin->notify(new NewCaseMessage($message))
            );
        } catch (\Throwable $e) {
            Log::warning('Message notification failed: ' . $e->getMessage());
        }

        // A2: SMS nudge to patient when a clinician sends a portal message.
        // Guards: patient must exist, have a phone number, and have opted in.
        // Debounce: skip if an outbound portal message was already sent on this
        // case within the configured window (default 30 min) to prevent a
        // chatty thread from spamming the patient.
        // PHI rule: the body is a fixed generic string — no name, no case data.
        try {
            $patient = $case->patient;
            if ($patient && $patient->phone && $patient->sms_opt_in) {
                $debounceMinutes = (int) config('sms.debounce_minutes', 30);
                $lastOutbound = Message::where('case_id', $case->id)
                    ->where('direction', 'outbound')
                    ->where('channel', 'portal')
                    ->where('id', '!=', $message->id)
                    ->max('created_at');

                $debounced = $lastOutbound
                    && now()->diffInMinutes(\Carbon\Carbon::parse($lastOutbound)) < $debounceMinutes;

                if (!$debounced) {
                    $this->sms->send(
                        $patient->phone,
                        'You have a new message in your patient portal. Please log in to view and reply.'
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SMS notification failed for case ' . $case->id . ': ' . $e->getMessage());
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $fs */
        $fs = Storage::disk($disk);

        return $fs->download(
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $fs */
        $fs = Storage::disk($file->disk);

        return $fs->download($file->path, $file->original_name);
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
                    CaseOffering::where('case_id', $case->id)->update(['status' => 'prescribed']);
                });

                // Fire prescription_written before case_completed so partners have
                // prescription data before the case is marked done.
                $case->loadMissing('caseOfferings');
                $prescription->load('medications.offering');
                $coByOffering = $case->caseOfferings->keyBy('offering_id');
                $this->webhooks->dispatch($case->partner_id, 'prescription_written', [
                    'case_id'                  => $case->uuid,
                    'external_id'              => $case->external_id,
                    'patient_id'               => $case->patient->uuid ?? null,
                    'clinician_name'           => $clinician->full_name,
                    'clinician_npi'            => $clinician->npi,
                    'clinician_license_state'  => $case->patient_state,
                    'clinician_license_number' => collect($clinician->licensed_states ?? [])
                        ->firstWhere('state', $case->patient_state)['license_number'] ?? $clinician->license_number,
                    'clinician_phone'          => $clinician->phone,
                    'clinician_email'          => Auth::user()->email,
                    'diagnoses'                => $prescription->diagnoses,
                    'meds_prescribed' => $prescription->medications->map(function ($m) use ($coByOffering) {
                        $co = $coByOffering->get($m->offering_id);
                        return [
                            'name'                => $m->name,
                            'offering_id'         => $m->offering?->uuid,
                            'product_key'         => $co?->product_key ?: null,
                            'month_frequency'     => $co?->month_frequency ?: null,
                            'compound_formula'    => $m->compound_formula,
                            'sig'                 => $m->sig,
                            'refills'             => (string) $m->refills,
                            'quantity'            => (string) $m->quantity,
                            'days_supply'         => (string) $m->days_supply,
                            'dispense_unit'       => $m->dispense_unit,
                            'days_until_dispense' => $m->days_until_dispense,
                            'dosing'              => $m->dosing,
                        ];
                    })->toArray(),
                    'timestamp'       => now()->timestamp,
                ]);

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

                /*
                 * EHR: record each batch approval. No clinical note is created in
                 * the batch flow (directions are stored on the prescription, not as
                 * a ClinicalNote), so null is passed for the note. All medications
                 * are approved decisions — batch has no per-medication decline step.
                 * Non-fatal: a failure records the error and the batch row still
                 * reports success, matching the pharmacy dispatch posture above.
                 */
                try {
                    $ehrDecisions = $prescription->medications->map(fn ($med) => [
                        'name'      => $med->name,
                        'decision'  => 'approve',
                        'term'      => data_get($med->dosing, 'term'),
                        'frequency' => data_get($med->dosing, 'frequency'),
                        'months'    => data_get($med->dosing, 'months') ?? [],
                        'refills'   => $med->refills,
                    ])->toArray();

                    $this->ehrRecords->recordApproval($case->fresh(), null, $ehrDecisions);
                } catch (\Throwable $e) {
                    Log::error('EHR record build/push failed (batch)', [
                        'uuid'  => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                }

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
