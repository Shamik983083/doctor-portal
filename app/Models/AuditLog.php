<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // Audit entries are immutable — we manage created_at only.
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'diff',
        'context',
        'created_at',
    ];

    protected $casts = [
        'diff'       => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForType(Builder $q, string $type): Builder
    {
        return $q->where('auditable_type', $type);
    }

    public function scopeForAction(Builder $q, string $action): Builder
    {
        return $q->where('action', $action);
    }

    public function scopeForActor(Builder $q, int $actorId): Builder
    {
        return $q->where('actor_id', $actorId);
    }

    public function scopeFrom(Builder $q, string $date): Builder
    {
        return $q->whereDate('created_at', '>=', $date);
    }

    public function scopeTo(Builder $q, string $date): Builder
    {
        return $q->whereDate('created_at', '<=', $date);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function actions(): array
    {
        return ['created', 'updated', 'deleted', 'restored', 'force_deleted'];
    }

    public static function auditableTypes(): array
    {
        return [
            'PatientCase', 'Clinician', 'Partner', 'User', 'Offering',
            'OfferingCategory', 'Setting', 'StateVisitRequirement',
            'SlaPolicy', 'RoutingPolicy',
        ];
    }
}
