<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClinicalNote extends Model
{
    protected $table = 'clinical_notes';

    protected $fillable = [
        'uuid', 'case_id', 'clinician_id', 'type', 'note', 'is_private',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function clinician() { return $this->belongsTo(Clinician::class); }
}
