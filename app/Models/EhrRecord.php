<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EhrRecord extends Model
{
    public const STATUS_DISABLED = 'disabled';   // Built and stored, deliberately not sent
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_FAILED   = 'failed';

    protected $fillable = [
        'uuid', 'case_id', 'clinical_note_id', 'partner_id',
        'adapter', 'status', 'attempts', 'payload',
        'reference', 'response_code', 'response_body', 'last_error', 'sent_at',
    ];

    protected $casts = [
        'payload'  => 'array',
        'attempts' => 'integer',
        'sent_at'  => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($record) {
            $record->uuid = $record->uuid ?: (string) Str::uuid();
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class, 'clinical_note_id');
    }

    /** True when this row is a preview of what would be sent, not a real push. */
    public function isPreview(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }
}
