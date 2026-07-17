<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaseEvent;
use App\Models\Clinician;
use App\Models\ClinicalNote;
use App\Models\Message;
use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * MA-DOCPORTAL role-view preview area (/ma-portal/*).
 *
 * A SELF-CONTAINED, additive showcase of how MA-DOCPORTAL presents the
 * Practitioner / Admin / Super Admin surfaces, driven by real MEDAXIS data
 * where feasible and clearly-labeled "MA preview" where the capability is
 * backend-heavy in MA (routing engine, multi-tenancy, credentialing PSV,
 * outbox dispatch). It mirrors MA's /demo/{role} and the canonical coverage
 * contract in docs/MA-PORTAL-ROLE-VIEWS.md.
 *
 * Non-breaking by construction: new routes, new controller, new views. It reads
 * existing data read-only and mutates nothing; the host clinician/admin/partner
 * flows are untouched.
 */
class MaPortalController extends Controller
{
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support'];

    /** States that require a synchronous video visit (in-view policy stand-in). */
    private const VIDEO_STATES = ['CA', 'NY', 'TX'];

    public function practitioner()
    {
        $cases = PatientCase::with(['patient', 'partner', 'caseOfferings.offering'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderByRaw("FIELD(triage, 'red', 'yellow', 'green') DESC")
            ->orderBy('created_at')
            ->limit(8)
            ->get();

        $triageMetrics = $this->triageMetrics();
        $note = ClinicalNote::with(['clinician.user', 'case.patient'])->latest()->first();

        // Top case drives the quick-review / AI-draft / intake panels.
        $topCase = $cases->first();
        if ($topCase) {
            $topCase->load(['caseQuestions', 'questionnaireResponses.answers', 'clinician.user']);
        }
        $intake    = $this->intakeAnswers($topCase);
        $aiSummary = $this->aiSummary($topCase, $intake);

        // Workflow holds across the visible queue (hold flag or support escalation).
        $heldCases = $cases->filter(fn ($c) => $c->hold_status || $c->status === 'support')->values();

        // Recent patient messages — MEDAXIS has a real Message model, so this is functional.
        $messages = Message::with(['patient', 'case.patient'])->latest()->limit(6)->get();

        $videoStates = self::VIDEO_STATES;

        $reasonCodes = [
            'Dose exceeds protocol titration step',
            'Active workflow hold not cleared',
            'Identity verification incomplete',
            'Allergy conflict requires clinician review',
            'Out-of-catalog request for patient state',
        ];

        return view('ma-portal.practitioner', compact(
            'cases', 'triageMetrics', 'note', 'topCase', 'intake', 'aiSummary',
            'heldCases', 'messages', 'videoStates', 'reasonCodes'
        ));
    }

    public function admin()
    {
        // Storefront workload — partners with open-case counts + triage mix (functional).
        $partners = Partner::orderBy('name')->get();
        $openByPartner = PatientCase::whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('partner_id, triage, COUNT(*) as c')
            ->groupBy('partner_id', 'triage')
            ->get()
            ->groupBy('partner_id');

        $storefronts = $partners->map(function ($p) use ($openByPartner) {
            $rows = $openByPartner->get($p->id) ?? collect();
            return [
                'name'   => $p->name,
                'status' => $p->status,
                'green'  => (int) ($rows->firstWhere('triage', 'green')->c ?? 0),
                'yellow' => (int) ($rows->firstWhere('triage', 'yellow')->c ?? 0),
                'red'    => (int) ($rows->firstWhere('triage', 'red')->c ?? 0),
                'open'   => (int) $rows->sum('c'),
            ];
        });

        $clinicians = Clinician::with('user')->orderByDesc('is_available')->get();
        $users      = User::with('roles')->orderBy('name')->limit(8)->get();
        $offerings  = Offering::with('category')->latest()->limit(6)->get();
        $webhooks   = Webhook::with('partner')->get();
        $deliveries = WebhookDelivery::latest()->limit(6)->get();

        // Weighted provider load — active cases vs each clinician's daily cap (functional).
        $providerLoads = Clinician::with('user')->get()->map(function ($c) {
            $active = PatientCase::where('clinician_id', $c->id)
                ->whereIn('status', ['assigned', 'support', 'processing'])
                ->count();
            $cap = (int) ($c->max_daily_cases ?: 0);
            return [
                'name'    => optional($c->user)->name ?? 'Clinician',
                'active'  => $active,
                'cap'     => $cap,
                'percent' => $cap > 0 ? min(100, (int) round($active / $cap * 100)) : 0,
            ];
        });

        // Exception center — each bucket is a real workflow condition (functional).
        $exceptions = [
            [
                'count' => PatientCase::where('hold_status', true)->whereIn('status', self::OPEN_STATUSES)->count(),
                'label' => 'Workflow hold awaiting clearance',
            ],
            [
                'count' => PatientCase::where('status', 'support')->count(),
                'label' => 'Escalated to support',
            ],
            [
                'count' => PatientCase::whereIn('status', self::OPEN_STATUSES)
                    ->whereHas('patient', function ($q) {
                        $q->where(function ($w) {
                            $w->where('id_verified_status', '!=', 'verified')->orWhereNull('id_verified_status');
                        });
                    })->count(),
                'label' => 'Missing identity verification',
            ],
            [
                'count' => PatientCase::where('status', 'cancelled')
                    ->where('cancelled_at', '>=', now()->subDays(7))->count(),
                'label' => 'Cancelled in last 7 days',
            ],
        ];

        // Operational report — derived timings + rates (functional; "—" when no data yet).
        $ttfr = PatientCase::whereNotNull('assigned_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, assigned_at)) a')->value('a');
        $ttd = PatientCase::whereNotNull('approved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) a')->value('a');
        $approvedCount  = PatientCase::whereNotNull('approved_at')->count();
        $cancelledCount = PatientCase::where('status', 'cancelled')->count();
        $decisions      = $approvedCount + $cancelledCount;
        $approvalRate   = $decisions > 0 ? round($approvedCount / $decisions * 100) : null;
        $recentDecisions = PatientCase::where('updated_at', '>=', now()->subDays(7))
            ->where(function ($q) {
                $q->whereNotNull('approved_at')->orWhere('status', 'cancelled');
            })->count();

        $fmt = fn ($min) => $min === null ? '—' : (function ($m) {
            $m = (int) round($m);
            $h = intdiv($m, 60);
            $r = $m % 60;
            return $h > 0 ? "{$h}h {$r}m" : "{$r}m";
        })($min);

        $report = [
            ['value' => $fmt($ttfr),                                      'label' => 'TTFR · time to first review'],
            ['value' => $fmt($ttd),                                       'label' => 'TTD · time to decision'],
            ['value' => $approvalRate === null ? '—' : $approvalRate . '%', 'label' => 'Approval rate'],
            ['value' => round($recentDecisions / 7, 1) . '/day',          'label' => 'Decision throughput (7d)'],
        ];

        // Triage-volume mini chart — open cases by band (functional).
        $tm = $this->triageMetrics();
        $triageVolume = [
            ['label' => 'Green',  'tone' => 'green',  'count' => $tm['green']],
            ['label' => 'Yellow', 'tone' => 'yellow', 'count' => $tm['yellow']],
            ['label' => 'Red',    'tone' => 'red',    'count' => $tm['red']],
        ];
        $triageVolumeMax = max(1, $tm['green'], $tm['yellow'], $tm['red']);

        return view('ma-portal.admin', compact(
            'storefronts', 'clinicians', 'users', 'offerings', 'webhooks', 'deliveries',
            'providerLoads', 'exceptions', 'report', 'triageVolume', 'triageVolumeMax'
        ));
    }

    public function superAdmin()
    {
        $partners = Partner::withCount('offerings')->orderBy('name')->get();

        $tenants = $partners->map(fn ($p) => [
            'name'       => $p->name,
            'status'     => $p->status,
            'offerings'  => $p->offerings_count,
            'clinicians' => Clinician::count(), // single-tenant install — shared roster
        ]);

        $auditEvents = CaseEvent::with('case')->latest()->limit(8)->get();

        // A couple of genuinely-functional reliability signals from the webhook queue.
        $observability = [
            'dlq'      => WebhookDelivery::where('status', 'failed')->count(),
            'pending'  => WebhookDelivery::where('status', 'pending')->count(),
            'partners' => $partners->count(),
        ];

        // Protocol category coverage — from the real offering catalog (functional).
        $categoryCoverage = OfferingCategory::withCount('offerings')->orderBy('name')->get()
            ->map(fn ($c) => [
                'name'   => $c->name,
                'count'  => $c->offerings_count,
                'status' => $c->offerings_count > 0 ? 'Configured' : 'Needs review',
            ]);

        // Global user / role administration — every user + Spatie roles (functional).
        $allUsers = User::with('roles')->orderBy('name')->get();

        // State visit requirement matrix — static preview (MA's versioned policy engine).
        $stateMatrix = [
            ['state' => 'California', 'scope' => 'CATEGORY', 'detail' => 'GLP-1 · all storefronts',        'version' => 'v3', 'status' => 'ACTIVE', 'video' => true,  'effective' => 'Effective Jul 1, 2026',  'ref' => 'CA-TELE-2026-04'],
            ['state' => 'New York',   'scope' => 'PROGRAM',  'detail' => 'Peptides program · Northstar',    'version' => 'v2', 'status' => 'ACTIVE', 'video' => true,  'effective' => 'Effective Jul 1, 2026',  'ref' => 'NY-SYNC-2026-02'],
            ['state' => 'Tennessee',  'scope' => 'ALL',      'detail' => 'All categories and programs',     'version' => 'v1', 'status' => 'ACTIVE', 'video' => false, 'effective' => 'Effective Jun 15, 2026', 'ref' => 'TN-BASE-2026-01'],
            ['state' => 'Texas',      'scope' => 'CATEGORY', 'detail' => 'Enclomiphene · all storefronts',  'version' => 'v1', 'status' => 'DRAFT',  'video' => true,  'effective' => 'Not yet effective',      'ref' => 'TX-ENC-2026-01'],
        ];

        return view('ma-portal.super-admin', compact(
            'tenants', 'auditEvents', 'observability',
            'categoryCoverage', 'allUsers', 'stateMatrix'
        ));
    }

    private function triageMetrics(): array
    {
        $counts = PatientCase::whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('triage, COUNT(*) as total')
            ->groupBy('triage')
            ->pluck('total', 'triage');

        return [
            'open'   => (int) $counts->sum(),
            'red'    => (int) $counts->get('red', 0),
            'yellow' => (int) $counts->get('yellow', 0),
            'green'  => (int) $counts->get('green', 0),
        ];
    }

    /**
     * Recorded intake answers for a case, preferring flat case_questions and
     * falling back to questionnaire response answers. Returns [['q'=>,'a'=>], …].
     */
    private function intakeAnswers(?PatientCase $case)
    {
        if (! $case) {
            return collect();
        }

        $fromQuestions = $case->caseQuestions
            ->map(fn ($q) => ['q' => $q->question, 'a' => $q->answer])
            ->filter(fn ($r) => filled($r['q']));

        if ($fromQuestions->isNotEmpty()) {
            return $fromQuestions->values();
        }

        return $case->questionnaireResponses
            ->flatMap->answers
            ->map(fn ($a) => ['q' => $a->question_text, 'a' => $a->answer])
            ->filter(fn ($r) => filled($r['q']))
            ->values();
    }

    /**
     * Deterministic bullet summary assembled from recorded intake + case data.
     * No model runs — this is honest "assembled from recorded intake" text.
     */
    private function aiSummary(?PatientCase $case, $answers): array
    {
        if (! $case) {
            return [];
        }

        $bullets = [];
        $bullets[] = 'Triage classification: ' . $case->triageLabel() . ' — ' . $case->triageMeaning();

        $p = $case->patient;
        if ($p) {
            $demo = [];
            if ($p->gender)     { $demo[] = ucfirst($p->gender); }
            if ($p->age)        { $demo[] = $p->age . ' yrs'; }
            if ($p->bmi)        { $demo[] = 'BMI ' . number_format($p->bmi, 1); }
            if (! empty($demo)) { $bullets[] = 'Patient: ' . implode(' · ', $demo) . '.'; }
            $bullets[] = 'Identity verification: '
                . (strtolower($p->id_verified_status ?? '') === 'verified' ? 'verified.' : 'not verified.');
        }

        $offerings = $case->caseOfferings
            ->map(fn ($co) => optional($co->offering)->name)
            ->filter()
            ->implode(', ');
        if ($offerings !== '') {
            $bullets[] = 'Requested offerings: ' . $offerings . '.';
        }

        $reasons = collect($case->triage_reasons ?? []);
        if ($reasons->isNotEmpty()) {
            $bullets[] = 'Triage signals: ' . $reasons->take(3)->implode('; ') . '.';
        }

        foreach (collect($answers)->take(4) as $a) {
            $bullets[] = $a['q'] . ': ' . Str::limit((string) $a['a'], 80);
        }

        return $bullets;
    }
}
