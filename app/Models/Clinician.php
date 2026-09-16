<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Clinician extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'npi', 'license_number', 'license_state',
        'specialty', 'credentials', 'status', 'is_available',
        'max_daily_cases', 'priority', 'licensed_states',
        // Capacity controls (Devin msgs 2248/2250). accepting_new_cases and
        // max_daily_new_cases block NEW cases only; daily_refill_alert_threshold
        // is a soft alert that never blocks. See the migration.
        'accepting_new_cases', 'max_daily_new_cases', 'max_open_cases',
        'daily_refill_alert_threshold',
        // Visit-type acceptance and the booking link (Devin msg 2313 Q4).
        'accepts_async_visits', 'accepts_sync_visits', 'scheduling_link',
        // E17: timestamp of the last time this clinician viewed the case queue,
        // used to count how many new cases have arrived since their last visit.
        'cases_last_viewed_at',
        // B3: set when a case auto-releases due to a missed deadline; blocks pool pulls until it clears.
        'pool_cooldown_until',
        // Healthie provisioning: global clinicians are synced into every enabled sub-org automatically.
        'is_global',
        // Required by Healthie signUp/createOrganizationMembership for provider accounts.
        'phone',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'accepting_new_cases' => 'boolean',
        'licensed_states' => 'array',
        'accepts_async_visits' => 'boolean',
        'accepts_sync_visits' => 'boolean',
        'pool_cooldown_until' => 'datetime',
        'cases_last_viewed_at' => 'datetime',
        'is_global' => 'boolean',
    ];

    /**
     * The product categories this doctor accepts (Devin msg 2313 Q2).
     *
     * The taxonomy is the existing global `offering_categories` table, which
     * already has an admin screen that can add and remove categories, so a new
     * category is tickable the moment it is created with no code change.
     */
    public function acceptedCategories()
    {
        return $this->belongsToMany(OfferingCategory::class, 'clinician_offering_category');
    }

    /**
     * Does this doctor accept this product category?
     *
     * FAIL CLOSED. An empty accepted list means this doctor accepts NOTHING, not
     * everything. The migration backfilled every existing doctor with every
     * active category precisely so that this default cannot strand anyone: an
     * unticked category is now an admin's decision rather than an absence of
     * data. This is the same reasoning Devin applied to blank licensure in msg
     * 2313 ("empty should not show licensed everywhere it needs to reject").
     */
    public function acceptsCategory(?int $categoryId): bool
    {
        if ($categoryId === null) {
            return false;
        }

        return $this->acceptedCategories->contains('id', $categoryId);
    }

    /**
     * Does this doctor take this kind of visit?
     *
     * A synchronous visit ALSO requires a booking link. Assigning a live-video
     * case to a doctor a patient cannot book produces a case that looks routed
     * and is not, and the patient is the one who discovers it. See
     * EligibilityEvaluator::SCHEDULING_LINK_MISSING.
     */
    public function acceptsVisitType(string $visitType): bool
    {
        if ($visitType === \App\Services\Routing\VisitType::SYNCHRONOUS) {
            return (bool) $this->accepts_sync_visits && $this->hasSchedulingLink();
        }

        return (bool) $this->accepts_async_visits;
    }

    public function hasSchedulingLink(): bool
    {
        return trim((string) $this->scheduling_link) !== '';
    }

    /** A daily new-case cap of null means uncapped, matching max_daily_cases. */
    public function maxDailyNewCasesOrNull(): ?int
    {
        return $this->max_daily_new_cases > 0 ? (int) $this->max_daily_new_cases : null;
    }

    /** A null open-case cap means uncapped. */
    public function maxOpenCasesOrNull(): ?int
    {
        return $this->max_open_cases > 0 ? (int) $this->max_open_cases : null;
    }

    /**
     * Restrict to the doctors this admin is over (Devin msg 2117).
     * Super admin sees all; a Doctor Admin assigned nobody sees nobody.
     * See PatientCase::scopeVisibleTo for why an empty list must stay empty.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $ids = $user?->visibleClinicianIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids);
    }

    /** The admins who are over this doctor. */
    public function admins()
    {
        return $this->belongsToMany(User::class, 'admin_clinician');
    }

    /**
     * May this clinician practise on a patient in this state?
     *
     * LAW 4: licensure is a hard gate, not a filter. A case for a patient in
     * state X can only ever be assigned to, viewed by, or approved by a
     * prescriber with an active licence in state X.
     *
     * Two deliberate positions:
     *
     *  - An UNKNOWN patient state returns false. We cannot show a licence covers
     *     a state we do not know, and this is an authorization surface.
     *  - BLANK LICENSURE NOW RETURNS FALSE TOO (Devin msg 2313: "empty should not
     *     show licensed everywhere it needs to reject"). See isLicensedInState().
     *     This closes the item docs/COMPLIANCE-LEDGER.md tracked as blocking full
     *     Law 4 conformance.
     */
    public function canPracticeIn(?string $state): bool
    {
        $state = strtoupper(trim((string) $state));

        if ($state === '') {
            return false;
        }

        return $this->isLicensedInState($state);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function cases() { return $this->hasMany(PatientCase::class); }
    public function clinicalNotes() { return $this->hasMany(ClinicalNote::class); }
    public function messages() { return $this->hasMany(Message::class); }
    public function healthieMappings() { return $this->hasMany(ClinicianHealthieMapping::class); }
    public function supervisorAssignments() { return $this->hasMany(ClinicianSupervisorAssignment::class); }

    /** Sub-storefronts this clinician is explicitly assigned to for routing. */
    public function subStorefronts() { return $this->belongsToMany(SubStorefront::class, 'clinician_sub_storefront')->withTimestamps(); }

    public function getFullNameAttribute(): string
    {
        return trim(($this->credentials ? $this->credentials . ' ' : '') . $this->user->name);
    }

    /**
     * Is this doctor licensed in this state?
     *
     * FAIL CLOSED ON BLANK LICENSURE, changed 2026-07-23 on Devin's instruction
     * (msg 2313): "empty should not show licensed everywhere it needs to reject".
     *
     * WHAT THIS USED TO DO AND WHY IT MATTERED. An empty `licensed_states`
     * returned TRUE, so a doctor whose licence data was never filled in read as
     * licensed in all fifty states. That made the state hard block vacuous for
     * exactly the population most likely to be wrong, and it made
     * `canPracticeIn()` pass for them on the prescribe and approve paths as well.
     *
     * WHAT FLIPPING IT COSTS, STATED PLAINLY. Any clinician with no recorded
     * licensed states stops being routed cases, and stops being able to prescribe
     * or approve, the moment this deploys. That is the intended reading of the
     * instruction, and it is recoverable by populating their licences. Run
     * `php artisan licensure:audit` first: it lists every clinician this will
     * affect and exits non-zero while any remain.
     */
    public function isLicensedInState(string $state): bool
    {
        $states = $this->licensed_states ?? [];

        if (empty($states)) {
            return false;
        }

        $today = now()->toDateString();

        return collect($states)
            ->filter(fn ($s) => empty($s['expiry_date']) || $s['expiry_date'] >= $today)
            ->pluck('state')
            ->contains(strtoupper($state));
    }

    /**
     * Is there a license for this state that has passed its expiry_date?
     *
     * Distinct from isLicensedInState() returning false (state never licensed).
     * Used by EligibilityEvaluator to surface LICENSE_EXPIRED_IN_STATE rather
     * than RESIDENCE_STATE_LICENSE_MISSING, so admins know the fix is a renewal
     * rather than recruiting in that state.
     */
    public function isLicenseExpiredInState(string $state): bool
    {
        $states = $this->licensed_states ?? [];

        if (empty($states)) {
            return false;
        }

        $state = strtoupper($state);
        $today = now()->toDateString();

        return collect($states)->contains(
            fn ($s) => strtoupper($s['state']) === $state
                && ! empty($s['expiry_date'])
                && $s['expiry_date'] < $today
        );
    }
}
