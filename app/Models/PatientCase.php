<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PatientCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cases';

    protected $fillable = [
        'uuid', 'partner_id', 'patient_id', 'clinician_id', 'external_id',
        'status', 'hold_status', 'is_chargeable', 'charge_amount',
        'support_note', 'support_at', 'cancellation_reason', 'patient_state', 'visit_type',
        'assigned_at', 'approved_at', 'processing_at', 'completed_at', 'cancelled_at',
        'metadata',
        'triage', 'triage_reasons', 'triage_ruleset', 'triaged_at',
    ];

    protected $casts = [
        'hold_status' => 'boolean',
        'is_chargeable' => 'boolean',
        'support_at' => 'datetime',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'triage_reasons' => 'array',
        'triaged_at' => 'datetime',
    ];

    // Status constants
    const STATUS_CREATED    = 'created';
    const STATUS_WAITING    = 'waiting';
    const STATUS_SUPPORT    = 'support';
    const STATUS_ASSIGNED   = 'assigned';
    const STATUS_APPROVED   = 'approved';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';

    // Triage constants · review-priority band, separate from status
    const TRIAGE_GREEN  = 'green';
    const TRIAGE_YELLOW = 'yellow';
    const TRIAGE_RED    = 'red';

    public function triageLabel(): string
    {
        return match ($this->triage) {
            self::TRIAGE_GREEN  => 'Green',
            self::TRIAGE_YELLOW => 'Yellow',
            self::TRIAGE_RED    => 'Red',
            default             => 'Unclassified',
        };
    }

    public function triageMeaning(): string
    {
        return match ($this->triage) {
            self::TRIAGE_GREEN  => 'Routine · no elevated-risk signals detected.',
            self::TRIAGE_YELLOW => 'Elevated · closer clinician review advised.',
            self::TRIAGE_RED    => 'High-attention · do not fast-track; review carefully.',
            default             => 'Not yet classified.',
        };
    }

    public function scopeTriage($query, string $level)
    {
        return $query->where('triage', $level);
    }

    /**
     * Restrict to what this admin is allowed to see (Devin msg 2117).
     *
     * A super admin sees everything. A Doctor Admin sees the cases belonging to
     * the doctors they are over.
     *
     * THREE OUTCOMES, and the middle one changed:
     *
     * 1. A null id list is a super admin or a console context. No restriction.
     *
     * 2. An admin assigned NO doctors sees NOTHING, not everything. An empty
     *    list is a real restriction, and `whereIn('clinician_id', [])` correctly
     *    matches zero rows. The tempting `if (! $ids) return $query;` would open
     *    the whole system to a half-configured admin, which is the exact
     *    opposite of what scoping is for. UNCHANGED.
     *
     * 3. A real id list means that admin's doctors PLUS every UNASSIGNED case.
     *
     * ON POINT 3, WHICH THIS METHOD PREVIOUSLY REFUSED TO DO. The earlier version
     * excluded unassigned cases and said so, calling any change a deliberate
     * extension rather than something to slip in via a null check. This IS that
     * deliberate extension: Devin msg 2234, "Doctor admin needs to see waiting
     * cases so fix that bug first."
     *
     * The reason it had to change: a waiting case has `clinician_id` NULL by
     * definition, and `whereIn` never matches NULL, so a Doctor Admin could not
     * see a single case in the intake queue. They could see work already handed
     * to their doctors but not the work waiting to be handed out, which made the
     * assign flow unreachable for the very role that performs it.
     *
     * THE GROUPING IS LOAD-BEARING, DO NOT FLATTEN IT. Callers chain further
     * conditions (`->where('status', 'support')`, `->whereNotNull('approved_at')`).
     * Without the closure, `whereIn(...) or whereNull(...)` followed by `and
     * status = ?` binds as `whereIn OR (whereNull AND status = ?)`, which leaks
     * every other admin's cases. Inside the closure it is `(mine OR unassigned)
     * AND status = ?`, which is the intent.
     *
     * An admin over nobody still sees nothing, INCLUDING unassigned cases: the
     * empty-list branch returns before the NULL clause is ever added, so a
     * half-configured admin is not handed the intake queue as a consolation.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $ids = $user?->visibleClinicianIds();

        if ($ids === null) {
            return $query;              // super admin, or no user context
        }

        if ($ids === []) {
            return $query->whereIn('clinician_id', $ids);   // resolves to 0 = 1
        }

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('clinician_id', $ids)
              ->orWhereNull('clinician_id');
        });
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function clinician() { return $this->belongsTo(Clinician::class); }
    public function caseOfferings() { return $this->hasMany(CaseOffering::class, 'case_id'); }
    public function offerings() { return $this->belongsToMany(Offering::class, 'case_offerings', 'case_id', 'offering_id')->withPivot('status', 'quantity', 'price', 'dosage', 'frequency', 'refills'); }
    public function caseQuestions() { return $this->hasMany(CaseQuestion::class, 'case_id'); }
    public function diseases() { return $this->belongsToMany(Disease::class, 'case_diseases', 'case_id', 'disease_id')->withPivot('is_primary'); }
    public function clinicalNotes() { return $this->hasMany(ClinicalNote::class, 'case_id'); }
    public function orders() { return $this->hasMany(Order::class, 'case_id'); }
    public function messages() { return $this->hasMany(Message::class, 'case_id'); }
    public function files() { return $this->hasMany(PatientFile::class, 'case_id'); }
    public function tags() { return $this->belongsToMany(Tag::class, 'case_tags', 'case_id', 'tag_id')->withPivot('notes'); }
    public function events() { return $this->hasMany(CaseEvent::class, 'case_id'); }
    public function questionnaireResponses() { return $this->hasMany(QuestionnaireResponse::class, 'case_id'); }
    public function casePrescriptions()      { return $this->hasMany(CasePrescription::class, 'case_id'); }

    public function isInStatus(string $status): bool { return $this->status === $status; }
    public function canTransitionTo(string $status): bool { return in_array($status, $this->getAllowedTransitions()); }

    public function getAllowedTransitions(): array
    {
        return match($this->status) {
            self::STATUS_CREATED    => [self::STATUS_WAITING, self::STATUS_SUPPORT, self::STATUS_CANCELLED],
            self::STATUS_WAITING    => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
            self::STATUS_SUPPORT    => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
            self::STATUS_ASSIGNED   => [self::STATUS_APPROVED, self::STATUS_SUPPORT, self::STATUS_CANCELLED],
            self::STATUS_APPROVED   => [self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED],
            default                 => [],
        };
    }
}
