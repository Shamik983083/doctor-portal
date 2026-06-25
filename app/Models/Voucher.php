<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'partner_id', 'patient_id', 'code', 'status',
        'offerings', 'diseases', 'pharmacy_id', 'value', 'notes',
        'expires_at', 'used_at', 'metadata',
    ];

    protected $casts = [
        'offerings' => 'array',
        'diseases' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($m) {
            $m->uuid = $m->uuid ?? (string) Str::uuid();
            $m->code = $m->code ?? strtoupper(Str::random(8));
        });
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function patient() { return $this->belongsTo(Patient::class); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
