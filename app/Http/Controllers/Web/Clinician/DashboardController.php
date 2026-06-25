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
        $clinician = Auth::user()->clinician;

        $stats = [
            'waiting'    => PatientCase::where('status', PatientCase::STATUS_WAITING)->count(),
            'assigned'   => PatientCase::where('clinician_id', $clinician?->id)->where('status', PatientCase::STATUS_ASSIGNED)->count(),
            'approved'   => PatientCase::where('clinician_id', $clinician?->id)->where('status', PatientCase::STATUS_APPROVED)->count(),
            'completed_today' => PatientCase::where('clinician_id', $clinician?->id)
                ->where('status', PatientCase::STATUS_COMPLETED)
                ->whereDate('completed_at', today())
                ->count(),
        ];

        $recentCases = PatientCase::where('clinician_id', $clinician?->id)
            ->with(['patient', 'partner', 'caseOfferings.offering'])
            ->whereIn('status', [PatientCase::STATUS_ASSIGNED, PatientCase::STATUS_APPROVED])
            ->latest()
            ->take(10)
            ->get();

        return view('clinician.dashboard', compact('stats', 'recentCases', 'clinician'));
    }
}
