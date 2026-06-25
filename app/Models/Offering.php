<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Offering extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'partner_id', 'name', 'type', 'description', 'sku',
        'price', 'dosespot_medication_id', 'boothwyn_compound_id',
        'pharmacy_type', 'available_states', 'dispense_units', 'images',
        'faqs', 'is_active', 'is_controlled_substance', 'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_controlled_substance' => 'boolean',
        'available_states' => 'array',
        'dispense_units' => 'array',
        'images' => 'array',
        'faqs' => 'array',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function caseOfferings() { return $this->hasMany(CaseOffering::class); }
    public function cases() { return $this->belongsToMany(PatientCase::class, 'case_offerings', 'offering_id', 'case_id'); }

    public function isAvailableInState(string $state): bool
    {
        if (empty($this->available_states)) return true;
        return in_array(strtoupper($state), array_map('strtoupper', $this->available_states));
    }
}
