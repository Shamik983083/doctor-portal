<?php

namespace App\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $partner = Auth::user()->partner;

        $visibleCases = $partner->cases()
            ->where(fn($q) => $q->whereNotNull('support_at')
                ->orWhereIn('status', ['completed', 'cancelled']));

        $stats = [
            'offerings'  => $partner->offerings()->where('is_active', true)->count(),
            'patients'   => $partner->patients()->count(),
            'open_cases' => (clone $visibleCases)
                ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed'  => (clone $visibleCases)
                ->where('status', 'completed')->count(),
        ];

        $recentCases = (clone $visibleCases)
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->latest()->take(10)->get();

        $casesByStatus = (clone $visibleCases)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('partner.dashboard', compact('partner', 'stats', 'recentCases', 'casesByStatus'));
    }
}
