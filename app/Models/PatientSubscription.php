<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PatientSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_subscriptions';

    protected $fillable = [
        'uuid', 'partner_id', 'patient_id', 'status', 'renew_period',
        'encounter_period', 'billing_amount', 'billing_info', 'products',
        'next_renewal_at', 'cancelled_at', 'metadata',
    ];

    protected $casts = [
        'billing_amount' => 'decimal:2',
        'billing_info' => 'array',
        'products' => 'array',
        'metadata' => 'array',
        'next_renewal_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
}
