<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('actor')->latest('created_at');

        if ($type = $request->input('auditable_type')) {
            $query->forType($type);
        }

        if ($action = $request->input('action')) {
            $query->forAction($action);
        }

        if ($actorId = $request->input('actor_id')) {
            $query->forActor((int) $actorId);
        }

        if ($from = $request->input('date_from')) {
            $query->from($from);
        }

        if ($to = $request->input('date_to')) {
            $query->to($to);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Populate actor dropdown with users who have at least one audit entry.
        $actors = User::whereIn('id', AuditLog::distinct()->pluck('actor_id')->filter())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.audit-log.index', [
            'logs'           => $logs,
            'actors'         => $actors,
            'types'          => AuditLog::auditableTypes(),
            'actions'        => AuditLog::actions(),
            'filterType'     => $request->input('auditable_type', ''),
            'filterAction'   => $request->input('action', ''),
            'filterActorId'  => $request->input('actor_id', ''),
            'filterDateFrom' => $request->input('date_from', ''),
            'filterDateTo'   => $request->input('date_to', ''),
        ]);
    }
}
