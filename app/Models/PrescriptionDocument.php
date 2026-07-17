<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Immutable signed prescription document — one PDF rendered from the locked
 * provider-approved order snapshot, with a content hash. Append-only: once
 * created it is never updated (a correction is a new document).
 */
class PrescriptionDocument extends Model
{
    protected $fillable = [
        'uuid', 'case_prescription_id', 'case_id', 'patient_id', 'partner_id', 'clinician_id',
        'snapshot', 'document_path', 'content_hash', 'attestation', 'attested_at', 'locked_at',
    ];

    protected $casts = [
        'snapshot'    => 'array',
        'attested_at' => 'datetime',
        'locked_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
        // Enforce immutability at the model layer.
        static::updating(function () {
            throw new RuntimeException('PrescriptionDocument is immutable and cannot be updated.');
        });
        static::deleting(function () {
            throw new RuntimeException('PrescriptionDocument is immutable and cannot be deleted.');
        });
    }

    public function casePrescription() { return $this->belongsTo(CasePrescription::class); }
    public function case()             { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function patient()          { return $this->belongsTo(Patient::class); }
    public function dispatches()       { return $this->hasMany(PharmacyDispatch::class); }
}
