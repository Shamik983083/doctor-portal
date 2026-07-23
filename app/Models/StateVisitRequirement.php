<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rule in the state synchronous-visit matrix (Devin msg 2313 Q4).
 *
 * Whether a case must be seen on live video is a function of the patient's state
 * and what is being prescribed. This model holds one such rule; the resolution
 * order across rules lives in StateVisitRequirementResolver.
 *
 * Super admin owned, because it encodes telehealth law and Devin's requirement
 * was explicit that it be adjustable "as laws change frequently".
 */
class StateVisitRequirement extends Model
{
    public const SCOPE_ALL      = 'ALL';
    public const SCOPE_CATEGORY = 'CATEGORY';
    public const SCOPE_OFFERING = 'OFFERING';

    /**
     * Specificity, high wins. A rule for one offering beats a rule for its
     * category, which beats a blanket rule for the state.
     */
    public const SCOPE_SPECIFICITY = [
        self::SCOPE_ALL      => 0,
        self::SCOPE_CATEGORY => 1,
        self::SCOPE_OFFERING => 2,
    ];

    public const SCOPE_LABELS = [
        self::SCOPE_ALL      => 'Every product in this state',
        self::SCOPE_CATEGORY => 'One category in this state',
        self::SCOPE_OFFERING => 'One product in this state',
    ];

    protected $fillable = [
        'scope_type', 'offering_category_id', 'offering_id',
        'state', 'requires_synchronous',
        'effective_from', 'effective_to', 'created_by', 'note',
    ];

    protected $casts = [
        'requires_synchronous' => 'boolean',
        'effective_from'       => 'date',
        'effective_to'         => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(OfferingCategory::class, 'offering_category_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Rules in force on a given day.
     *
     * A null `effective_from` means "always has been", and a null `effective_to`
     * means "still is". Both nulls is the ordinary case for a rule someone typed
     * in today without thinking about dates, and it behaves the obvious way.
     */
    public function scopeInForce($query, ?\DateTimeInterface $on = null)
    {
        $on = $on ?? now();

        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $on));
    }

    public function scopeForState($query, string $state)
    {
        return $query->where('state', strtoupper(trim($state)));
    }

    public function specificity(): int
    {
        return self::SCOPE_SPECIFICITY[$this->scope_type] ?? 0;
    }

    public function scopeLabel(): string
    {
        return self::SCOPE_LABELS[$this->scope_type] ?? $this->scope_type;
    }
}
