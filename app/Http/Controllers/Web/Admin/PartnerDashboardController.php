<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Models\SubStorefront;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    private const OPEN_STATUSES = ['waiting', 'assigned', 'support', 'approved', 'processing'];

    public function index(Request $request)
    {
        $user = $request->user();

        // Sub-storefront cards — only those with at least one visible case.
        $visibleSubIds = PatientCase::visibleTo($user)
            ->whereNotNull('sub_storefront_id')
            ->distinct()
            ->pluck('sub_storefront_id');

        $subStorefronts = SubStorefront::with('partner')
            ->whereIn('id', $visibleSubIds)
            ->orderBy('name')
            ->get();

        $openCounts = PatientCase::visibleTo($user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('sub_storefront_id')
            ->selectRaw('sub_storefront_id, COUNT(*) as total')
            ->groupBy('sub_storefront_id')
            ->pluck('total', 'sub_storefront_id');

        $supportCounts = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->whereNotNull('sub_storefront_id')
            ->selectRaw('sub_storefront_id, COUNT(*) as total')
            ->groupBy('sub_storefront_id')
            ->pluck('total', 'sub_storefront_id');

        // "Direct" cards — partners with visible cases that have no sub-storefront.
        $directPartnerIds = PatientCase::visibleTo($user)
            ->whereNull('sub_storefront_id')
            ->distinct()
            ->pluck('partner_id');

        $directPartners = Partner::whereIn('id', $directPartnerIds)->orderBy('name')->get();

        $directOpenCounts = PatientCase::visibleTo($user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNull('sub_storefront_id')
            ->selectRaw('partner_id, COUNT(*) as total')
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        $directSupportCounts = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->whereNull('sub_storefront_id')
            ->selectRaw('partner_id, COUNT(*) as total')
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        return view('admin.partner-dashboard.index', compact(
            'subStorefronts', 'openCounts', 'supportCounts',
            'directPartners', 'directOpenCounts', 'directSupportCounts'
        ));
    }

    public function showSubStorefront(Request $request, int $id)
    {
        $user          = $request->user();
        $subStorefront = SubStorefront::with('partner')->findOrFail($id);

        $scope = fn () => PatientCase::visibleTo($user)->where('sub_storefront_id', $id);

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

        $byStatus = $scope()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

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

        $recentCases = $scope()
            ->with(['patient', 'clinician.user', 'caseOfferings.offering'])
            ->latest()
            ->take(15)
            ->get();

        return view('admin.partner-dashboard.show-sub', compact(
            'subStorefront', 'stats', 'byStatus', 'providerLoads', 'recentCases', 'user'
        ));
    }

    public function show(Request $request, int $id)
    {
        $user    = $request->user();
        $partner = Partner::findOrFail($id);
        $direct  = $request->boolean('direct');

        // Base scope: cases for this partner visible to the current admin.
        // When $direct is true, only cases with no sub-storefront are included.
        $scope = fn () => PatientCase::visibleTo($user)
            ->where('partner_id', $id)
            ->when($direct, fn ($q) => $q->whereNull('sub_storefront_id'));

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
            'partner', 'stats', 'byStatus', 'providerLoads', 'recentCases', 'user', 'direct'
        ));
    }
}
