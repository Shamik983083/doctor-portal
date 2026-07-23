<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A case that could not be routed (Devin msg 2308: "NO SILENT FAILURES").
 *
 * One OPEN row per case. A retry updates the existing row rather than adding
 * another, so a worker retrying every few minutes cannot bury the other
 * exceptions.
 */
class RoutingException extends Model
{
    /** Routing-level codes. Provider-level ones come from EligibilityEvaluator. */
    public const NO_ELIGIBLE_PROVIDER = 'NO_ELIGIBLE_PROVIDER';
    public const NO_ACTIVE_POLICY     = 'NO_ACTIVE_POLICY';
    public const UNKNOWN_MODE         = 'UNKNOWN_MODE';
    public const NO_CATEGORY_ON_CASE  = 'NO_CATEGORY_ON_CASE';

    public const REASON_LABELS = [
        self::NO_ELIGIBLE_PROVIDER => 'No doctor could take this case',
        self::NO_ACTIVE_POLICY     => 'No active routing policy, nothing is being assigned',
        self::UNKNOWN_MODE         => 'Routing policy is malformed, nothing is being assigned',
        self::NO_CATEGORY_ON_CASE  => 'Case has no product category, so it cannot be matched to a doctor',
    ];

    /**
     * Codes that mean routing is broken SYSTEM WIDE rather than for this one
     * case. These notify immediately with no age delay: every case is affected,
     * so waiting to see whether it clears itself wastes the only useful minutes.
     */
    public const SYSTEMIC = [
        self::NO_ACTIVE_POLICY,
        self::UNKNOWN_MODE,
    ];

    protected $fillable = [
        'case_id', 'reason_code', 'detail', 'provider_reasons',
        'occurrences', 'first_seen_at', 'last_seen_at',
        'resolved_at', 'resolved_by', 'notified_at',
    ];

    protected $casts = [
        'provider_reasons' => 'array',
        'first_seen_at'    => 'datetime',
        'last_seen_at'     => 'datetime',
        'resolved_at'      => 'datetime',
        'notified_at'      => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason_code]
            ?? \App\Services\Routing\EligibilityEvaluator::REASON_LABELS[$this->reason_code]
            ?? $this->reason_code;
    }

    public function isSystemic(): bool
    {
        return in_array($this->reason_code, self::SYSTEMIC, true);
    }

    /** How long this case has been stuck, in hours. */
    public function ageHours(): float
    {
        return $this->first_seen_at ? now()->diffInMinutes($this->first_seen_at) / 60 : 0.0;
    }
}
