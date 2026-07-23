<?php

namespace App\Models;

use App\Services\Routing\RoutingMode;
use App\Services\Routing\RoutingWeights;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One version of the routing policy. Ported from MA-DOCPORTAL's RoutingPolicy.
 *
 * Exactly one version is ACTIVE at a time. Activation supersedes the previous one
 * rather than overwriting it, so the history of who routed under which rules
 * survives.
 */
class RoutingPolicy extends Model
{
    public const STATUS_DRAFT      = 'DRAFT';
    public const STATUS_ACTIVE     = 'ACTIVE';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    protected $fillable = [
        'version', 'mode', 'config', 'status',
        'activated_at', 'created_by', 'activated_by', 'note',
    ];

    protected $casts = [
        'config'       => 'array',
        'activated_at' => 'datetime',
        'version'      => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * The policy in force, or null.
     *
     * Null is a legitimate answer and callers must handle it. MA's posture: no
     * active policy means nothing is auto-assigned, rather than falling back to
     * some built-in default nobody chose.
     */
    public static function active(): ?self
    {
        return static::where('status', self::STATUS_ACTIVE)->orderByDesc('version')->first();
    }

    public function intelligentWeights(): RoutingWeights
    {
        return new RoutingWeights($this->config['intelligentWeights'] ?? []);
    }

    /** Per-doctor weights keyed by clinician id. Absent means the default, 1. */
    public function providerWeights(): array
    {
        $weights = $this->config['providerWeights'] ?? [];

        return is_array($weights) ? $weights : [];
    }

    public function messageAgingThresholdHours(): ?float
    {
        $value = $this->config['messageAgingThresholdHours'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Whether a doctor with NO recorded licensed states is blocked.
     *
     * Defaults to false, which preserves MEDAXIS's current behaviour, where blank
     * licence data reads as "licensed everywhere". Turn it on once licensed states
     * are actually populated; until then it would block every doctor whose data was
     * never filled in. See docs/integrations/ROUTING.md section 2.3.
     */
    public function requireRecordedLicensure(): bool
    {
        return (bool) ($this->config['requireRecordedLicensure'] ?? false);
    }

    /*
     * ── Admin-set criteria that stop a doctor getting NEW cases ─────────────
     *   (Devin msg 2248: "criteria to not issue new cases ie delayed or pending
     *   cases/messages".)
     *
     * All optional. Null means the criterion is off, which is the shipped
     * default, so activating a policy does not suddenly hold cases back. They
     * gate NEW cases only; a check-in still reaches its own doctor.
     */

    /** How long a non-terminal case may sit before it counts as "delayed". */
    public function delayedAfterHours(): ?float
    {
        $value = $this->config['newCaseDelayedAfterHours'] ?? null;

        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }

    /** How many delayed cases a doctor may carry before new work stops. */
    public function maxDelayedCases(): ?int
    {
        $value = $this->config['newCaseMaxDelayedCases'] ?? null;

        return is_numeric($value) && $value >= 0 ? (int) $value : null;
    }

    /**
     * How many cases awaiting a REPLY a doctor may carry before new work stops.
     *
     * Awaiting a reply, not "unread": a case where the newest inbound patient
     * message is newer than the newest outbound one. Deliberately not the
     * is_read flag, which a doctor clears just by opening the case without
     * replying (that gap is why this criterion measures reply, not attention).
     */
    public function maxAwaitingReply(): ?int
    {
        $value = $this->config['newCaseMaxAwaitingReply'] ?? null;

        return is_numeric($value) && $value >= 0 ? (int) $value : null;
    }

    public function modeLabel(): string
    {
        return RoutingMode::LABELS[$this->mode] ?? $this->mode;
    }

    /**
     * Make this version the active one.
     *
     * The supersede and the activate happen in ONE transaction. Two active
     * policies would make routing depend on which row a query happened to return
     * first, and a window with NO active policy would silently stop
     * auto-assignment; the transaction closes both gaps.
     */
    public function activate(?int $userId = null): void
    {
        DB::transaction(function () use ($userId) {
            static::where('status', self::STATUS_ACTIVE)
                ->where('id', '!=', $this->id)
                ->update(['status' => self::STATUS_SUPERSEDED]);

            $this->update([
                'status'       => self::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by' => $userId,
            ]);
        });
    }
}
