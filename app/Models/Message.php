<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Message extends Model
{
    protected $fillable = [
        'uuid', 'case_id', 'patient_id', 'clinician_id', 'partner_id',
        'direction', 'channel', 'sender_type', 'body',
        'is_read', 'read_at', 'attachments',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'attachments' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function clinician() { return $this->belongsTo(Clinician::class); }
    public function partner() { return $this->belongsTo(Partner::class); }
}
