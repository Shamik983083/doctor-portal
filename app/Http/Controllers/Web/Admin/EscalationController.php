<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use Illuminate\Http\Request;

class EscalationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Two tabs:
        //   directed — escalation_target = 'doctor_admin' (needs admin review)
        //   all      — every status=support case in the admin's scope
        $tab = in_array($request->input('tab'), ['directed', 'all']) ? $request->input('tab') : 'directed';

        $cases = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->with(['patient', 'clinician.user', 'partner', 'caseOfferings.offering'])
            ->when(
                $tab === 'directed',
                fn ($q) => $q->where('escalation_target', PatientCase::ESCALATION_DOCTOR_ADMIN)
            )
            // Oldest escalation first — longest-waiting cases at the top.
            // support_at may be null on cases escalated before the column existed;
            // fall back to created_at so those still sort predictably.
            ->orderByRaw('COALESCE(support_at, created_at) ASC')
            ->paginate(25)
            ->withQueryString();

        // Tab badge counts (two cheap queries; not computed from the paginated result
        // so the counts survive pagination and tab-switching without another page load).
        $directedCount = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->where('escalation_target', PatientCase::ESCALATION_DOCTOR_ADMIN)
            ->count();

        $allCount = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->count();

        // Type breakdown for the "All" tab summary strip.
        // COALESCE handles rows where escalation_target is NULL (pre-migration data).
        $byTarget = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->selectRaw("COALESCE(escalation_target, 'unknown') as target, COUNT(*) as cnt")
            ->groupBy('target')
            ->pluck('cnt', 'target');

        return view('admin.escalations.index', compact(
            'cases', 'tab', 'directedCount', 'allCount', 'byTarget', 'user'
        ));
    }
}
