<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Static helper for writing audit entries from controllers and services
 * where no Eloquent model event fires (e.g. permission toggles, bulk ops).
 *
 * Model-level changes (create / update / delete) are captured automatically
 * by AuditObserver — only use this class for events that have no model event.
 */
class AuditLogger
{
    public static function log(
        string     $action,
        string     $auditableType,
        int|string $auditableId,
        string     $auditableLabel,
        ?array     $diff = null,
        ?string    $context = null,
    ): void {
        try {
            AuditLog::create([
                'actor_id'        => Auth::id(),
                'actor_name'      => Auth::user()?->name,
                'action'          => $action,
                'auditable_type'  => $auditableType,
                'auditable_id'    => $auditableId,
                'auditable_label' => $auditableLabel,
                'diff'            => $diff,
                'context'         => $context ?? (request()?->path() ?? 'console'),
                'created_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Audit failures must never break the main request.
        }
    }
}
