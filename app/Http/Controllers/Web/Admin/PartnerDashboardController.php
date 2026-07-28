<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PatientCase;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support', 'approved', 'processing'];

    public function index(Request $request)
    {
        $user = $request->user();

        // Fetch partners that have at least one case visible to this admin.
        // Super admin sees all partners; Doctor Admin only sees partners where
        // their doctors have worked on at least one case.
        $partnerIds = PatientCase::visibleTo($user)
            ->distinct()
            ->pluck('partner_id');

        $partners = Partner::whereIn('id', $partnerIds)
            ->orderBy('name')
            ->get();

        // Open case counts per partner, scoped to visibleTo() — used for the
        // summary cards on the index page.
        $openCounts = PatientCase::visibleTo($user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('partner_id, COUNT(*) as total')
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        $supportCounts = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->selectRaw('partner_id, COUNT(*) as total')
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        return view('admin.partner-dashboard.index', compact(
            'partners', 'openCounts', 'supportCounts'
        ));
    }

    public function show(Request $request, int $id)
    {
        $user    = $request->user();
        $partner = Partner::findOrFail($id);

        // Base scope: cases for this partner visible to the current admin.
        $scope = fn () => PatientCase::visibleTo($user)->where('partner_id', $id);

        // If Doctor Admin has no cases for this partner at all, they should still
        // see the screen (empty state) rather than a 403 — the data just shows 0.

        $stats = [
            'open'        => $scope()->whereIn('status', self::OPEN_STATUSES)->count(),
            'waiting'     => $scope()->where('status', PatientCase::STATUS_WAITING)->count(),
            'assigned'    => $scope()->where('status', PatientCase::STATUS_ASSIGNED)->count(),
            'support'     => $scope()->where('status', PatientCase::STATUS_SUPPORT)->count(),
            'completed'   => $scope()->where('status', PatientCase::STATUS_COMPLETED)->count(),
            'cancelled'   => $scope()->where('status', PatientCase::STATUS_CANCELLED)->count(),
            'first_visits'=> $scope()->firstVisits()->count(),
            'refills'     => $scope()->refills()->count(),
        ];

        // Cases by status for the mini doughnut / breakdown bar
        $byStatus = $scope()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Provider workload for this partner — clinicians with at least one
        // active case from this partner within the admin's scope.
        $providerLoads = $scope()
            ->whereIn('status', ['assigned', 'support', 'processing'])
            ->with('clinician.user')
            ->selectRaw('clinician_id, COUNT(*) as active_count')
            ->groupBy('clinician_id')
            ->get()
            ->filter(fn ($row) => $row->clinician)
            ->map(fn ($row) => [
                'name'   => $row->clinician->user?->name ?? 'Unknown',
                'active' => (int) $row->active_count,
                'cap'    => (int) ($row->clinician->max_daily_cases ?: 0),
            ])
            ->sortByDesc('active')
            ->values();

        // Recent cases for this partner, scoped to visibleTo()
        $recentCases = $scope()
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->latest()
            ->take(15)
            ->get();

        return view('admin.partner-dashboard.show', compact(
            'partner', 'stats', 'byStatus', 'providerLoads', 'recentCases', 'user'
        ));
    }
}
