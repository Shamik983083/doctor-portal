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

        $recentCases = PatientCase::with(['patient', 'partner', 'clinician.user'])
            ->latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'casesByStatus', 'recentCases'));
    }
}
