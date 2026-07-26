<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasePrescriptionDiagnosis extends Model
{
    protected $table = 'case_prescription_diagnoses';

    protected $fillable = [
        'case_prescription_id', 'icd_code', 'description', 'sort_order',
    ];

    public function prescription()
    {
        return $this->belongsTo(CasePrescription::class, 'case_prescription_id');
    }
}
