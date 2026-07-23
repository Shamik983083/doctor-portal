<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A doctor's request for work out of the pool (Devin msg 2308).
 *
 * The doctor never sees the queue. They ask for a number, and this row records
 * what they asked for, what they got, and why the two differ.
 */
class CasePullRequest extends Model
{
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_GRANTED          = 'GRANTED';
    public const STATUS_DENIED           = 'DENIED';
    public const STATUS_REJECTED         = 'REJECTED';

    public const STATUS_LABELS = [
        self::STATUS_PENDING_APPROVAL => 'Waiting on Doctor Admin approval',
        self::STATUS_GRANTED          => 'Granted',
        self::STATUS_DENIED           => 'Denied by Doctor Admin',
        self::STATUS_REJECTED         => 'Blocked',
    ];

    protected $fillable = [
        'clinician_id', 'requested_count', 'granted_count', 'status',
        'blocking_reasons', 'granted_case_ids', 'shortfall_reason',
        'decided_by', 'decided_at', 'decision_note',
    ];

    protected $casts = [
        'blocking_reasons' => 'array',
        'granted_case_ids' => 'array',
        'decided_at'       => 'datetime',
    ];

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Asked for more than it got, and the shortfall was not a flat refusal. */
    public function wasShort(): bool
    {
        return $this->status === self::STATUS_GRANTED
            && $this->granted_count < $this->requested_count;
    }
}
