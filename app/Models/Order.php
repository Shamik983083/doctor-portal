<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'case_id', 'patient_id', 'partner_id', 'pharmacy_id',
        'status', 'tracking_number', 'tracking_carrier', 'payment_status',
        'amount', 'notes', 'fulfillment_data', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fulfillment_data' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_SUBMITTED  = 'submitted';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED    = 'shipped';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_RETURNED   = 'returned';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function partner() { return $this->belongsTo(Partner::class); }
    public function pharmacy() { return $this->belongsTo(Pharmacy::class); }
    public function prescriptions() { return $this->hasMany(Prescription::class); }
}
