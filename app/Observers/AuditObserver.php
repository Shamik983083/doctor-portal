<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Clinician;
use App\Models\Offering;
use App\Models\OfferingCategory;
use App\Models\Partner;
use App\Models\PatientCase;
use App\Models\Setting;
use App\Models\SlaPolicy;
use App\Models\StateVisitRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    /**
     * Reentrancy guard. Writing to audit_logs fires Eloquent events on AuditLog
     * itself; without this flag we would recurse until the stack overflows.
     */
    private static bool $recording = false;

    /**
     * Fields stripped from every diff before storage.
     * Covers security-sensitive values across all observed models.
     */
    private const MASKED = [
        'password',
        'remember_token',
        'client_secret',
        'webhook_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Fields omitted from diffs because they are large opaque JSON blobs
     * whose byte-for-byte changes are not actionable in an audit view.
     * State changes that matter (status, assigned_at, etc.) are kept.
     */
    private const OMIT_FROM_DIFF = [
        'metadata',
        'clinical_intake',
        'triage_ruleset',
        'settings',
        'faqs',
        'images',
        'levels',
    ];

    // ── Event handlers ───────────────────────────────────────────────────────

    public function created(Model $model): void
    {
        $this->record($model, 'created', function (Model $m): ?array {
            $attrs = $this->clean($m->getAttributes());
            return $attrs ?: null;
        });
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated', function (Model $m): ?array {
            $changed = array_keys($m->getChanges());

            if (empty($changed)) {
                return null;
            }

            $old = array_intersect_key($m->getOriginal(), array_flip($changed));
            $new = $m->getChanges();

            return [
                'old' => $this->clean($old),
                'new' => $this->clean($new),
            ];
        });
    }

    public function deleted(Model $model): void
    {
        // SoftDeletes fires 'deleted' for a soft-delete. The record is still
        // retrievable via withTrashed(), so we log it as a soft action.
        $this->record($model, 'deleted', fn() => null);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', fn() => null);
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, 'force_deleted', fn() => null);
    }

    // ── Core ─────────────────────────────────────────────────────────────────

    private function record(Model $model, string $action, callable $diffResolver): void
    {
        // Guard: skip if we are already inside a record() call (reentrancy).
        if (self::$recording) {
            return;
        }

        self::$recording = true;

        try {
            $diff = $diffResolver($model);

            // Skip 'updated' events that produce an empty diff after cleaning
            // (e.g. only masked or omitted columns changed — touching_timestamps only).
            if ($action === 'updated' && $diff !== null) {
                $hasOld = !empty($diff['old']);
                $hasNew = !empty($diff['new']);
                if (!$hasOld && !$hasNew) {
                    return;
                }
            }

            AuditLog::create([
                'actor_id'       => Auth::id(),
                'actor_name'     => Auth::user()?->name,
                'action'         => $action,
                'auditable_type' => class_basename($model),
                'auditable_id'   => $model->getKey(),
                'auditable_label'=> $this->resolveLabel($model),
                'diff'           => $diff,
                'context'        => $this->resolveContext(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Audit failures must never break the main request.
            // Errors are swallowed silently; the observer is a side-effect.
        } finally {
            self::$recording = false;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Strip masked and omitted fields, return only non-null meaningful values. */
    private function clean(array $attrs): array
    {
        $exclude = array_merge(self::MASKED, self::OMIT_FROM_DIFF);

        return array_filter(
            array_diff_key($attrs, array_flip($exclude)),
            fn($v) => $v !== null
        );
    }

    /** Human-readable label for the audited record at the moment of the event. */
    private function resolveLabel(Model $model): string
    {
        return match (true) {
            $model instanceof PatientCase          => 'Case ' . ($model->uuid ?? "#{$model->id}"),
            $model instanceof Clinician            => $model->user?->name ?? "Clinician #{$model->id}",
            $model instanceof Partner              => $model->name ?? "Partner #{$model->id}",
            $model instanceof User                 => trim("{$model->name} ({$model->email})"),
            $model instanceof Offering             => $model->name ?? "Offering #{$model->id}",
            $model instanceof OfferingCategory     => $model->name ?? "Category #{$model->id}",
            $model instanceof Setting              => $model->label ?? $model->key ?? "Setting #{$model->id}",
            $model instanceof StateVisitRequirement=> "State rule #{$model->id}",
            $model instanceof SlaPolicy            => $model->name ?? "SlaPolicy #{$model->id}",
            default                                => class_basename($model) . " #{$model->id}",
        };
    }

    /**
     * Best-effort request context. Returns the URL path for web requests,
     * "console" for Artisan/queue, never throws.
     */
    private function resolveContext(): string
    {
        try {
            return request()?->path() ?? 'console';
        } catch (\Throwable) {
            return 'console';
        }
    }
}
