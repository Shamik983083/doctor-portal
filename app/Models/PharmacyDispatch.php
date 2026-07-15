<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Outbox record for pushing a provider-approved order to a pharmacy gateway.
 * Retry/backoff/dead-letter, modeled on the existing webhook_deliveries.
 */
class PharmacyDispatch extends Model
{
    protected $fillable = [
        'uuid', 'prescription_document_id', 'case_id', 'partner_id', 'pharmacy_id',
        'adapter', 'status', 'attempts', 'max_attempts', 'payload',
        'response_code', 'response_body', 'external_ref',
        'last_attempted_at', 'next_retry_at', 'dispatched_at',
    ];

    protected $casts = [
        'payload'           => 'array',
        'last_attempted_at' => 'datetime',
        'next_retry_at'     => 'datetime',
        'dispatched_at'     => 'datetime',
    ];

    // Feature-flagged off — recorded but never pushed (preview).
    const STATUS_DISABLED   = 'disabled';
    const STATUS_PENDING    = 'pending';
    const STATUS_SENDING    = 'sending';
    const STATUS_SENT       = 'sent';
    const STATUS_FAILED     = 'failed';
    const STATUS_DEAD_LETTER = 'dead_letter';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function document() { return $this->belongsTo(PrescriptionDocument::class, 'prescription_document_id'); }
    public function case()     { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function pharmacy() { return $this->belongsTo(Pharmacy::class); }
}
