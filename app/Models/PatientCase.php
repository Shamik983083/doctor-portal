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
        'uuid', 'partner_id', 'sub_storefront_id', 'patient_id', 'clinician_id', 'external_id',
        'status', 'hold_status', 'is_chargeable', 'charge_amount',
        'support_note', 'support_at', 'cancellation_reason', 'patient_state', 'visit_type',
        'is_refill',
        'assigned_at', 'approved_at', 'processing_at', 'completed_at', 'cancelled_at',
        'metadata', 'clinical_intake',
        'triage', 'triage_reasons', 'triage_ruleset', 'triaged_at',
        // Phase 1c: unified escalation model (B10 + D15)
        'escalation_target', 'escalation_reason',
        // B3: per-pull completion deadline
        'completion_deadline_at', 'deadline_warned',
    ];

    protected $casts = [
        'hold_status' => 'boolean',
        'is_chargeable' => 'boolean',
        'is_refill' => 'boolean',
        'clinical_intake' => 'array',
        'support_at' => 'datetime',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'triage_reasons' => 'array',
        'triaged_at' => 'datetime',
        'completion_deadline_at' => 'datetime',
        'deadline_warned' => 'boolean',
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

    // Escalation target constants (Phase 1c — unified escalation model)
    const ESCALATION_SUPPORT         = 'support';        // storefront → portal support team
    const ESCALATION_DOCTOR_ADMIN    = 'doctor_admin';   // provider → their Doctor Admin (B10)
    const ESCALATION_CLIENT_RESPONSE = 'client_response'; // awaiting patient/client reply (D15/D16)

    /*
     * ── Re-bill / check-in cases ──────────────────────────────────────────
     *
     * NOT a pharmacy refill. The `refills` integer elsewhere in this codebase
     * means "how many times may this script be dispensed again" and is a
     * different thing entirely. THIS means the patient submitted a check-in on
     * an existing course of treatment and a doctor prescribes again
     * (Devin msg 2246).
     *
     * Two consumers: routing sends these back to the doctor who treated the
     * patient before, and reporting splits first visits from check-ins.
     */

    /**
     * `visit_type` values accepted as a check-in when the explicit flag is absent.
     *
     * THE FALLBACK, AND ITS LIMIT (Devin msg 2246: "b should be what we build,
     * with a as a fallback"). `is_refill` on the API is the real signal. This
     * exists so a partner already sending a sensible `visit_type` gets the
     * behaviour without changing their integration first.
     *
     * Matching is substring, case-insensitive, so "Refill", "refill request"
     * and "Monthly check-in" all land. It is deliberately a SHORT list of
     * unambiguous words: `visit_type` is free text (nullable, max 100, set by
     * the partner), and a loose matcher would start classifying first visits as
     * check-ins, which is the failure that hands a new patient to a doctor on
     * the strength of a history they do not have.
     */
    public const REFILL_VISIT_TYPE_HINTS = ['refill', 'rebill', 're-bill', 'check-in', 'checkin', 'check in'];

    /**
     * Is this case a check-in / re-bill?
     *
     * Explicit flag first, `visit_type` hint second. Never infers from patient
     * history: a returning patient with a genuinely new complaint is a first
     * visit for that complaint, and guessing otherwise routes on a history that
     * does not apply.
     */
    public function isRefillRequest(): bool
    {
        if ($this->is_refill) {
            return true;
        }

        $visitType = strtolower(trim((string) $this->visit_type));

        if ($visitType === '') {
            return false;
        }

        foreach (self::REFILL_VISIT_TYPE_HINTS as $hint) {
            if (str_contains($visitType, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reporting scopes. These read the COLUMN only, never the visit_type
     * fallback, because a report has to be one SQL query and a substring match
     * over free text is not a number anyone should plan against. Partners who
     * want to appear in these send `is_refill`.
     */
    /**
     * The clinical-intake columns for the provider review queue, in the exact
     * shape the design preview renders (Devin msg 2258).
     *
     * Storefront data first, real case data as the fallback, and a dash when
     * neither has it. The requested MEDICATION falls back to the case's offering,
     * which the app always has; ID / sex / age / BMI fall back to the patient.
     * Nothing is invented: an absent value is a dash, never a guess.
     *
     * @return array<string,mixed>
     */
    public function queueClinical(): array
    {
        $ci = $this->clinical_intake ?? [];
        $dash = '-';

        $offerings = $this->caseOfferings->pluck('offering.name')->filter()->values();

        $val = fn ($k, $fallback = null) => (isset($ci[$k]) && $ci[$k] !== '' && $ci[$k] !== null)
            ? $ci[$k]
            : $fallback;

        return [
            'product'       => $val('product', $offerings->get(0) ?? $dash),
            'dose'          => $val('dose', $dash),
            'term'          => $val('term', $dash),
            'plan'          => $val('plan', $dash),
            'med2'          => $val('med2', $offerings->get(1) ?? $dash),
            'med3'          => $val('med3', $offerings->get(2) ?? $dash),
            'med4'          => $val('med4', $offerings->get(3) ?? $dash),
            'onGlp'         => $val('onGlp', $dash),
            'zofran'        => $val('zofran', $dash),
            'allergy'       => $val('allergy', $dash),
            'allergyDetail' => $val('allergyDetail'),
            'video'         => $val('video', $this->offeringRequiresVideoLabel($dash)),
        ];
    }

    /** Video-visit label fallback from the case offerings when intake is silent. */
    private function offeringRequiresVideoLabel(string $dash): string
    {
        // Without storefront intake we cannot assert a video requirement, so this
        // stays a dash rather than guessing "Clear".
        return $dash;
    }

    public function scopeRefills($query)
    {
        return $query->where('is_refill', true);
    }

    public function scopeFirstVisits($query)
    {
        return $query->where('is_refill', false);
    }

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
    public function subStorefront() { return $this->belongsTo(SubStorefront::class); }
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
    public function casePrescription()       { return $this->hasOne(CasePrescription::class, 'case_id')->where('review_status', '!=', 'draft')->latestOfMany('prescribed_at'); }

    /**
     * The most recent completed case that produced a prescription, scoped to the
     * same patient and — when the current case belongs to a sub-storefront — to
     * that same sub-storefront. Falls back to partner-level scope when no
     * sub-storefront is set, preserving the original behaviour.
     *
     * Shared by ContinuityResolver (routing) and the prior-visit panel shown to
     * clinicians on refill cases. Eager-loads everything the panel needs so callers
     * do not trigger N+1 queries.
     */
    public static function priorCompletedCase(self $case): ?self
    {
        if (! $case->patient_id) {
            return null;
        }

        return self::with([
                'clinician.user',
                'casePrescription.medications',
                'caseQuestions',
                'clinicalNotes' => fn ($q) => $q->orderByDesc('created_at')->limit(1),
            ])
            ->where('patient_id', $case->patient_id)
            ->when(
                $case->sub_storefront_id,
                fn ($q) => $q->where('sub_storefront_id', $case->sub_storefront_id),
                fn ($q) => $q->where('partner_id', $case->partner_id),
            )
            ->where('id', '!=', $case->id)
            ->where('status', self::STATUS_COMPLETED)
            ->whereNotNull('clinician_id')
            ->whereHas('casePrescriptions')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Human-readable label for the escalation sub-category, or null when none is set.
     * Used by the clinician case-show screen and the My Escalations sub-filter chips.
     */
    public function escalationLabel(): ?string
    {
        return match($this->escalation_target) {
            self::ESCALATION_SUPPORT         => 'Storefront Support',
            self::ESCALATION_DOCTOR_ADMIN    => 'Doctor Admin',
            self::ESCALATION_CLIENT_RESPONSE => 'Client Response',
            default                          => null,
        };
    }

    public function isInStatus(string $status): bool { return $this->status === $status; }
    public function canTransitionTo(string $status): bool { return in_array($status, $this->getAllowedTransitions()); }

    public function getAllowedTransitions(): array
    {
        return match($this->status) {
            self::STATUS_CREATED    => [self::STATUS_WAITING, self::STATUS_SUPPORT, self::STATUS_CANCELLED],
            self::STATUS_WAITING    => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
            self::STATUS_SUPPORT    => [self::STATUS_ASSIGNED, self::STATUS_APPROVED, self::STATUS_CANCELLED],
            self::STATUS_ASSIGNED   => [self::STATUS_APPROVED, self::STATUS_SUPPORT, self::STATUS_CANCELLED],
            self::STATUS_APPROVED   => [self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED],
            default                 => [],
        };
    }
}
