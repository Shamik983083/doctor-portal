<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Prescription extends Model
{
    protected $fillable = [
        'uuid', 'case_offering_id', 'order_id', 'dosespot_prescription_id',
        'status', 'drug_name', 'strength', 'quantity', 'days_supply',
        'refills', 'directions', 'pharmacy_notes', 'is_daw',
        'written_at', 'expires_at',
    ];

    protected $casts = [
        'is_daw' => 'boolean',
        'written_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function caseOffering() { return $this->belongsTo(CaseOffering::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
