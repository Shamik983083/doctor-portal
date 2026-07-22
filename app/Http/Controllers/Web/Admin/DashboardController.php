<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Offering;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\Setting;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support'];

    public function index(Request $request)
    {
        // ── Core stat cards ──────────────────────────────────────────────
        $slaReviewHours  = (int) Setting::get('sla_review_hours', 24);
        $slaRiskMinutes  = $slaReviewHours * 60 * 0.7; // ≥70% elapsed = at risk or breached

        /*
         * The dashboard is where an admin lands, so it is the first place scoping
         * has to hold (Devin msg 2117). A Doctor Admin sees the numbers for their
         * own doctors; a super admin sees everything.
         *
         * `$user` is threaded through every case query below rather than filtered
         * once at the end, because these are COUNT and GROUP BY queries: they
         * aggregate in the database, so a post-hoc filter would come too late.
         */
        $user      = $request->user();
        $isSuper   = $user?->isSuperAdmin() ?? true;
        $caseScope = fn () => PatientCase::visibleTo($user);

        $stats = [
            // Partners and total patients are platform-wide figures and belong to
            // the super admin. A Doctor Admin gets their own doctors instead of a
            // number that includes storefronts they have nothing to do with.
            'partners'        => $isSuper ? Partner::count() : null,
            'patients'        => $isSuper ? Patient::count() : null,
            'active_cases'    => $caseScope()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'clinicians'      => Clinician::visibleTo($user)->where('status', 'active')->count(),
            'sla_at_risk'     => $caseScope()->whereIn('status', ['assigned', 'approved', 'processing'])
                                    ->whereRaw(
                                        'TIMESTAMPDIFF(MINUTE, COALESCE(assigned_at, created_at), NOW()) >= ?',
                                        [$slaRiskMinutes]
                                    )->count(),
            'completed_today' => $caseScope()->where('status', 'completed')->count(),
        ];

        // ── Cases by status (doughnut) ───────────────────────────────────
        $casesByStatus = PatientCase::visibleTo($user)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // ── 30-day trend line ────────────────────────────────────────────
        $rawTrend = PatientCase::visibleTo($user)->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $trendLabels = [];
        $trendCounts = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trendLabels[] = now()->subDays($i)->format('M j');
            $trendCounts[] = $rawTrend[$day] ?? 0;
        }

        // ── Recent cases ─────────────────────────────────────────────────
        $recentCases = PatientCase::visibleTo($user)->with(['patient', 'partner', 'clinician.user'])
            ->latest()->take(10)->get();

        // ── Storefront workload (partner × triage) ───────────────────────
        $partners = Partner::orderBy('name')->get();
        $openByPartner = PatientCase::visibleTo($user)->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('partner_id, triage, COUNT(*) as c')
            ->groupBy('partner_id', 'triage')
            ->get()
            ->groupBy('partner_id');

        $storefronts = $partners->map(function ($p) use ($openByPartner) {
            $rows = $openByPartner->get($p->id) ?? collect();
            return [
                'name'   => $p->name,
                'status' => $p->status,
                'green'  => (int) ($rows->firstWhere('triage', 'green')?->c ?? 0),
                'yellow' => (int) ($rows->firstWhere('triage', 'yellow')?->c ?? 0),
                'red'    => (int) ($rows->firstWhere('triage', 'red')?->c ?? 0),
                'open'   => (int) $rows->sum('c'),
            ];
        });

        // ── Weighted provider load ───────────────────────────────────────
        $providerLoads = Clinician::visibleTo($user)->with('user')->get()->map(function ($c) use ($user) {
            $active = PatientCase::visibleTo($user)->where('clinician_id', $c->id)
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

        // ── Exception center ─────────────────────────────────────────────
        $exceptions = [
            [
                'count' => PatientCase::visibleTo($user)->where('hold_status', true)->whereIn('status', self::OPEN_STATUSES)->count(),
                'label' => 'Workflow hold awaiting clearance',
                'tone'  => 'yellow',
            ],
            [
                'count' => PatientCase::visibleTo($user)->where('status', 'support')->count(),
                'label' => 'Escalated to support',
                'tone'  => 'red',
            ],
            [
                'count' => PatientCase::visibleTo($user)->whereIn('status', self::OPEN_STATUSES)
                    ->whereHas('patient', fn($q) =>
                        $q->where(fn($w) =>
                            $w->where('id_verified_status', '!=', 'verified')->orWhereNull('id_verified_status')
                        )
                    )->count(),
                'label' => 'Missing identity verification',
                'tone'  => 'yellow',
            ],
            [
                'count' => PatientCase::visibleTo($user)->where('status', 'cancelled')
                    ->where('cancelled_at', '>=', now()->subDays(7))->count(),
                'label' => 'Cancelled in last 7 days',
                'tone'  => 'neutral',
            ],
        ];

        // ── Operational report ───────────────────────────────────────────
        $ttfr = PatientCase::visibleTo($user)->whereNotNull('assigned_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, assigned_at)) a')->value('a');
        $ttd = PatientCase::visibleTo($user)->whereNotNull('approved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at)) a')->value('a');
        $approvedCount  = PatientCase::visibleTo($user)->whereNotNull('approved_at')->count();
        $cancelledCount = PatientCase::visibleTo($user)->where('status', 'cancelled')->count();
        $decisions      = $approvedCount + $cancelledCount;
        $approvalRate   = $decisions > 0 ? round($approvedCount / $decisions * 100) : null;
        $recentDecisions = PatientCase::visibleTo($user)->where('updated_at', '>=', now()->subDays(7))
            ->where(fn($q) => $q->whereNotNull('approved_at')->orWhere('status', 'cancelled'))
            ->count();

        $fmt = fn ($min) => $min === null ? ' · ' : (function ($m) {
            $m = (int) round($m);
            $h = intdiv($m, 60);
            $r = $m % 60;
            return $h > 0 ? "{$h}h {$r}m" : "{$r}m";
        })($min);

        $report = [
            ['value' => $fmt($ttfr),                                         'label' => 'TTFR · time to first review'],
            ['value' => $fmt($ttd),                                          'label' => 'TTD · time to decision'],
            ['value' => $approvalRate === null ? ' · ' : $approvalRate . '%',  'label' => 'Approval rate'],
            ['value' => round($recentDecisions / 7, 1) . '/day',             'label' => 'Decision throughput (7d)'],
        ];

        // ── Triage volume bar chart ──────────────────────────────────────
        $triageCounts = PatientCase::visibleTo($user)->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('triage, COUNT(*) as total')
            ->groupBy('triage')
            ->pluck('total', 'triage');

        $triageVolume = [
            ['label' => 'Green',  'tone' => 'green',  'count' => (int) $triageCounts->get('green', 0)],
            ['label' => 'Yellow', 'tone' => 'yellow', 'count' => (int) $triageCounts->get('yellow', 0)],
            ['label' => 'Red',    'tone' => 'red',    'count' => (int) $triageCounts->get('red', 0)],
        ];
        $triageVolumeMax = max(1, ...$triageCounts->values()->toArray() ?: [1]);

        // ── Webhook delivery log ─────────────────────────────────────────
        $webhookDeliveries = WebhookDelivery::latest()->limit(6)->get();
        $webhookFailedCount = WebhookDelivery::where('status', 'failed')->count();

        return view('admin.dashboard', compact(
            'stats', 'casesByStatus',
            'trendLabels', 'trendCounts',
            'recentCases',
            'storefronts',
            'providerLoads',
            'exceptions',
            'report',
            'triageVolume', 'triageVolumeMax',
            'webhookDeliveries', 'webhookFailedCount'
        ));
    }
}
