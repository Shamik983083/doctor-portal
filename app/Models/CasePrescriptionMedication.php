<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasePrescriptionMedication extends Model
{
    protected $table = 'case_prescription_medications';

    public $timestamps = false;

    protected $fillable = [
        'case_prescription_id', 'offering_id', 'name',
        'compound_formula', 'refills', 'quantity',
        'days_supply', 'dispense_unit', 'days_until_dispense',
    ];

    public function prescription() { return $this->belongsTo(CasePrescription::class, 'case_prescription_id'); }
    public function offering()     { return $this->belongsTo(Offering::class); }
}
