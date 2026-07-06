<?php

namespace App\Http\Controllers\Web\Clinician;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $clinician   = Auth::user()->clinician;
        $clinicianId = $clinician?->id;

        $scopeClinician = fn($q) => $clinicianId ? $q->where('clinician_id', $clinicianId) : $q;

        $stats = [
            'waiting'    => PatientCase::where('status', PatientCase::STATUS_WAITING)->count(),
            'assigned'   => PatientCase::where('status', PatientCase::STATUS_ASSIGNED)->where($scopeClinician)->count(),
            'approved'   => PatientCase::where('status', PatientCase::STATUS_APPROVED)->where($scopeClinician)->count(),
            'completed_today' => PatientCase::where('status', PatientCase::STATUS_COMPLETED)
                ->whereDate('completed_at', today())
                ->where($scopeClinician)
                ->count(),
        ];

        $recentCases = PatientCase::where($scopeClinician)
            ->with(['patient', 'partner', 'caseOfferings.offering'])
            ->whereIn('status', [PatientCase::STATUS_ASSIGNED, PatientCase::STATUS_APPROVED])
            ->latest()
            ->take(10)
            ->get();

        return view('clinician.dashboard', compact('stats', 'recentCases', 'clinician'));
    }
}
