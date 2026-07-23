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
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'accepting_new_cases' => 'boolean',
        'licensed_states' => 'array',
    ];

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
     *  - Blank licensure on the clinician currently returns TRUE, because
     *     `isLicensedInState()` treats an empty list as licensed everywhere. That
     *     is fail-open and it is a KNOWN, LOGGED GAP, not an accident: changing
     *     that helper today would lock out every clinician whose licence data was
     *     never populated. It is tracked in docs/COMPLIANCE-LEDGER.md as the
     *     blocking item for full Law 4 conformance, and closed by populating
     *     licensed_states and then enabling requireRecordedLicensure.
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

    public function getFullNameAttribute(): string
    {
        return trim(($this->credentials ? $this->credentials . ' ' : '') . $this->user->name);
    }

    public function isLicensedInState(string $state): bool
    {
        $states = $this->licensed_states ?? [];
        if (empty($states)) return true;
        return collect($states)->pluck('state')->contains(strtoupper($state));
    }
}
