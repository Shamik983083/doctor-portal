<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $clinician   = Auth::user()->clinician;
        $clinicianId = $clinician?->id;

        $myCase = fn($q) => $q->where('clinician_id', $clinicianId);

        // ── Stat card data ────────────────────────────────────────────────
        $totalMyCases  = PatientCase::where('clinician_id', $clinicianId)->count();
        $totalCompleted = PatientCase::where('clinician_id', $clinicianId)
            ->where('status', PatientCase::STATUS_COMPLETED)->count();

        $stats = [
            'queue'            => PatientCase::where('status', PatientCase::STATUS_WAITING)->count(),
            'my_active'        => PatientCase::whereIn('status', [
                                    PatientCase::STATUS_ASSIGNED,
                                    PatientCase::STATUS_APPROVED,
                                    PatientCase::STATUS_PROCESSING,
                                ])->where($myCase)->count(),
            'completed_month'  => PatientCase::where('status', PatientCase::STATUS_COMPLETED)
                                    ->whereMonth('completed_at', now()->month)
                                    ->whereYear('completed_at', now()->year)
                                    ->where($myCase)->count(),
            'completion_rate'  => $totalMyCases > 0
                                    ? round(($totalCompleted / $totalMyCases) * 100)
                                    : 0,
            'total_my_cases'   => $totalMyCases,
            'total_assigned'   => $totalMyCases,
            'total_completed'  => $totalCompleted,
        ];

        // ── 30-day dual trend: cases assigned vs cases completed ──────────
        $rawAssigned = PatientCase::where('clinician_id', $clinicianId)
            ->selectRaw('DATE(assigned_at) as date, COUNT(*) as count')
            ->where('assigned_at', '>=', now()->subDays(29)->startOfDay())
            ->whereNotNull('assigned_at')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $rawCompleted = PatientCase::where('clinician_id', $clinicianId)
            ->selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->where('status', PatientCase::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->subDays(29)->startOfDay())
            ->whereNotNull('completed_at')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $trendLabels    = [];
        $trendAssigned  = [];
        $trendCompleted = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trendLabels[]    = now()->subDays($i)->format('M j');
            $trendAssigned[]  = $rawAssigned[$day]  ?? 0;
            $trendCompleted[] = $rawCompleted[$day] ?? 0;
        }

        // ── Visit-type breakdown (this clinician's cases, top 6) ──────────
        $visitTypeRaw = PatientCase::where('clinician_id', $clinicianId)
            ->selectRaw('COALESCE(NULLIF(visit_type, ""), "unspecified") as vtype, COUNT(*) as count')
            ->groupBy('vtype')
            ->orderByDesc('count')
            ->take(6)
            ->pluck('count', 'vtype')
            ->toArray();

        $visitTypeLabels = array_map(
            fn($v) => ucwords(str_replace(['_', '-'], ' ', $v)),
            array_keys($visitTypeRaw)
        );
        $visitTypeCounts = array_values($visitTypeRaw);

        // ── SLA configuration (from admin settings) ───────────────────────
        $slaReviewHours = (int) Setting::get('sla_review_hours', 24);

        // ── Active cases table with SLA computation ───────────────────────
        $recentCases = PatientCase::where($myCase)
            ->with(['patient', 'partner', 'caseOfferings.offering'])
            ->whereIn('status', [
                PatientCase::STATUS_ASSIGNED,
                PatientCase::STATUS_APPROVED,
                PatientCase::STATUS_PROCESSING,
            ])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($case) use ($slaReviewHours) {
                $clock        = $case->assigned_at ?? $case->created_at;
                $hoursElapsed = $clock->diffInMinutes(now()) / 60;
                $pct          = round(($hoursElapsed / $slaReviewHours) * 100);
                $remaining    = max(0, round($slaReviewHours - $hoursElapsed, 1));

                $case->sla_pct       = min($pct, 999);
                $case->sla_breached  = $hoursElapsed >= $slaReviewHours;
                $case->sla_at_risk   = !$case->sla_breached && $pct >= 70;
                $case->sla_remaining = $remaining;
                $case->sla_elapsed_h = round($hoursElapsed, 1);

                return $case;
            });

        $slaBreached = $recentCases->where('sla_breached', true)->count();
        $slaAtRisk   = $recentCases->where('sla_at_risk', true)->count();

        return view('clinician.dashboard', compact(
            'stats', 'clinician',
            'trendLabels', 'trendAssigned', 'trendCompleted',
            'visitTypeLabels', 'visitTypeCounts',
            'recentCases', 'slaReviewHours', 'slaBreached', 'slaAtRisk'
        ));
    }
}
