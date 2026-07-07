<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\Clinician;
use App\Models\Order;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'partners'        => Partner::count(),
            'patients'        => Patient::count(),
            'active_cases'    => PatientCase::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'clinicians'      => Clinician::where('status', 'active')->count(),
            'orders_today'    => Order::whereDate('created_at', today())->count(),
            'completed_today' => PatientCase::where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];

        $casesByStatus = PatientCase::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Build a full 30-day series (fill missing dates with 0)
        $rawTrend = PatientCase::selectRaw('DATE(created_at) as date, COUNT(*) as count')
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

        // Active case load per clinician (non-completed/cancelled), top 10
        $casesByClinician = Clinician::with('user')
            ->withCount(['cases as active_count' => fn($q) =>
                $q->whereNotIn('status', ['completed', 'cancelled'])
            ])
            ->where('status', 'active')
            ->having('active_count', '>', 0)
            ->orderByDesc('active_count')
            ->take(10)
            ->get()
            ->map(fn($c) => [
                'name'  => $c->full_name,
                'count' => $c->active_count,
            ])
            ->values();

        $recentCases = PatientCase::with(['patient', 'partner', 'clinician.user'])
            ->latest()->take(10)->get();

        return view('admin.dashboard', compact(
            'stats', 'casesByStatus',
            'trendLabels', 'trendCounts',
            'casesByClinician',
            'recentCases'
        ));
    }
}
