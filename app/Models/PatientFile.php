<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PatientFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'uuid', 'case_id', 'patient_id', 'partner_id',
        'name', 'original_name', 'path', 'disk', 'mime_type', 'size',
        'type', 'status', 'notes', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function partner() { return $this->belongsTo(Partner::class); }
}
