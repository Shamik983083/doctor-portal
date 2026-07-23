<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SLA a Doctor Admin sets and pushes down to their doctors
 * (Devin msg 2313 Q6).
 *
 * It gates ONE thing: whether a doctor may pull more cases out of the pool while
 * behind on the work they already hold. It never blocks a push assignment and
 * never blocks a check-in reaching its own doctor, because those are the
 * patient's route to care rather than the doctor asking for more of it.
 */
class SlaPolicy extends Model
{
    public const ON_VIOLATION_BYPASS           = 'BYPASS';
    public const ON_VIOLATION_REQUIRE_APPROVAL = 'REQUIRE_APPROVAL';

    public const ON_VIOLATION_LABELS = [
        self::ON_VIOLATION_BYPASS           => 'Allow the pull, alert the Doctor Admin',
        self::ON_VIOLATION_REQUIRE_APPROVAL => 'Hold the pull until a Doctor Admin approves',
    ];

    protected $fillable = [
        'owner_user_id', 'name',
        'max_outstanding_cases', 'max_overdue_cases', 'overdue_after_hours',
        'max_median_decision_minutes', 'on_violation', 'is_active', 'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * The policy governing one doctor, or null when nobody has set one.
     *
     * A doctor under two admins gets the STRICTER of their policies. An SLA is a
     * floor, and taking the looser of two floors would let a doctor pick their
     * least demanding admin by being under both. "Stricter" is decided per field,
     * so the effective policy can be a composite of two rows: the lowest non-null
     * ceiling of each, and REQUIRE_APPROVAL if any owner asked for it.
     *
     * Returns an unsaved SlaPolicy carrying the merged values, never persisted,
     * so callers can read it exactly like a stored one.
     */
    public static function forClinician(Clinician $clinician): ?self
    {
        $ownerIds = $clinician->admins()->pluck('users.id');

        $policies = static::where('is_active', true)
            ->whereIn('owner_user_id', $ownerIds)
            ->get();

        if ($policies->isEmpty()) {
            // Fall back to a house default: an active policy owned by a super
            // admin, for doctors whose own admin has not set one.
            $policies = static::where('is_active', true)
                ->whereHas('owner', fn ($q) => $q->role('super_admin'))
                ->get();
        }

        if ($policies->isEmpty()) {
            return null;
        }

        if ($policies->count() === 1) {
            return $policies->first();
        }

        $merged = new self([
            'owner_user_id' => $policies->first()->owner_user_id,
            'name'          => 'Strictest of ' . $policies->count() . ' policies',
            'on_violation'  => $policies->contains('on_violation', self::ON_VIOLATION_REQUIRE_APPROVAL)
                ? self::ON_VIOLATION_REQUIRE_APPROVAL
                : self::ON_VIOLATION_BYPASS,
            'is_active'     => true,
        ]);

        foreach (['max_outstanding_cases', 'max_overdue_cases', 'overdue_after_hours', 'max_median_decision_minutes'] as $field) {
            $values = $policies->pluck($field)->filter(fn ($v) => $v !== null);
            $merged->{$field} = $values->isEmpty() ? null : (int) $values->min();
        }

        return $merged;
    }

    public function requiresApproval(): bool
    {
        return $this->on_violation === self::ON_VIOLATION_REQUIRE_APPROVAL;
    }

    /** Does this policy actually constrain anything? */
    public function isConfigured(): bool
    {
        return $this->max_outstanding_cases !== null
            || $this->max_overdue_cases !== null
            || $this->max_median_decision_minutes !== null;
    }
}
