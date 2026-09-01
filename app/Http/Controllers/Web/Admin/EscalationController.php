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

        // Three tabs:
        //   directed         — escalation_target = 'doctor_admin' (needs admin review)
        //   client_response  — escalation_target = 'client_response' (awaiting patient/partner reply)
        //   all              — every status=support case in the admin's scope
        $validTabs = ['directed', 'client_response', 'all'];
        $tab = in_array($request->input('tab'), $validTabs, true) ? $request->input('tab') : 'directed';

        $cases = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->with(['patient', 'clinician.user', 'partner', 'caseOfferings.offering'])
            ->when($tab === 'directed',        fn ($q) => $q->where('escalation_target', PatientCase::ESCALATION_DOCTOR_ADMIN))
            ->when($tab === 'client_response', fn ($q) => $q->where('escalation_target', PatientCase::ESCALATION_CLIENT_RESPONSE))
            // Oldest escalation first — longest-waiting cases at the top.
            // support_at may be null on cases escalated before the column existed;
            // fall back to created_at so those still sort predictably.
            ->orderByRaw('COALESCE(support_at, created_at) ASC')
            ->paginate(25)
            ->withQueryString();

        // Tab badge counts — cheap dedicated queries so counts survive pagination.
        $directedCount       = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->where('escalation_target', PatientCase::ESCALATION_DOCTOR_ADMIN)
            ->count();

        $clientResponseCount = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->where('escalation_target', PatientCase::ESCALATION_CLIENT_RESPONSE)
            ->count();

        $allCount = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->count();

        // Type breakdown for the summary strip (COALESCE handles pre-migration NULLs).
        $byTarget = PatientCase::visibleTo($user)
            ->where('status', PatientCase::STATUS_SUPPORT)
            ->selectRaw("COALESCE(escalation_target, 'unknown') as target, COUNT(*) as cnt")
            ->groupBy('target')
            ->pluck('cnt', 'target');

        return view('admin.escalations.index', compact(
            'cases', 'tab', 'directedCount', 'clientResponseCount', 'allCount', 'byTarget', 'user'
        ));
    }
}
