<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseOffering extends Model
{
    protected $table = 'case_offerings';

    protected $fillable = [
        'case_id', 'offering_id', 'status', 'quantity', 'price',
        'dosage', 'frequency', 'refills', 'clinician_notes', 'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function offering() { return $this->belongsTo(Offering::class); }
    public function prescription() { return $this->hasOne(Prescription::class); }
}
