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
        'support_note', 'support_at', 'cancellation_reason', 'patient_state',
        'assigned_at', 'approved_at', 'processing_at', 'completed_at', 'cancelled_at',
        'metadata',
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

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function clinician() { return $this->belongsTo(Clinician::class); }
    public function caseOfferings() { return $this->hasMany(CaseOffering::class, 'case_id'); }
    public function offerings() { return $this->belongsToMany(Offering::class, 'case_offerings')->withPivot('status', 'quantity', 'price', 'dosage', 'frequency', 'refills'); }
    public function caseQuestions() { return $this->hasMany(CaseQuestion::class, 'case_id'); }
    public function diseases() { return $this->belongsToMany(Disease::class, 'case_diseases')->withPivot('is_primary'); }
    public function clinicalNotes() { return $this->hasMany(ClinicalNote::class, 'case_id'); }
    public function orders() { return $this->hasMany(Order::class, 'case_id'); }
    public function messages() { return $this->hasMany(Message::class, 'case_id'); }
    public function files() { return $this->hasMany(PatientFile::class, 'case_id'); }
    public function tags() { return $this->belongsToMany(Tag::class, 'case_tags')->withPivot('notes'); }
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
            self::STATUS_SUPPORT    => [self::STATUS_WAITING, self::STATUS_CANCELLED],
            self::STATUS_ASSIGNED   => [self::STATUS_APPROVED, self::STATUS_SUPPORT, self::STATUS_CANCELLED],
            self::STATUS_APPROVED   => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED],
            default                 => [],
        };
    }
}
