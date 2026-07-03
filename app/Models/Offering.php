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
        'uuid', 'partner_id', 'category_id', 'name', 'internal_name', 'type', 'description', 'sku',
        'price', 'dosespot_medication_id', 'boothwyn_compound_id',
        'pharmacy_type', 'pharmacy_name', 'pharmacy_notes',
        'compound_formula', 'refills', 'quantity', 'days_supply',
        'dispense_unit', 'dispense_units', 'days_until_dispense', 'directions',
        'available_states', 'images', 'faqs', 'is_active', 'is_controlled_substance', 'metadata',
        'approval_status', 'approved_by', 'approved_at', 'rejection_note',
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
        'approved_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function partner()       { return $this->belongsTo(Partner::class); }
    public function category()      { return $this->belongsTo(OfferingCategory::class, 'category_id'); }
    public function caseOfferings() { return $this->hasMany(CaseOffering::class); }
    public function cases()         { return $this->belongsToMany(PatientCase::class, 'case_offerings', 'offering_id', 'case_id'); }
    public function approvedBy()    { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeApproved($query) { return $query->where('approval_status', 'approved'); }

    public function questionnaires()
    {
        return $this->belongsToMany(Questionnaire::class, 'offering_questionnaire')
                    ->withPivot('is_required', 'sort_order')
                    ->orderByPivot('sort_order');
    }

    public function isAvailableInState(string $state): bool
    {
        if (empty($this->available_states)) return true;
        return \in_array(strtoupper($state), array_map('strtoupper', $this->available_states));
    }
}
