<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasePrescription extends Model
{
    protected $table = 'case_prescriptions';

    protected $fillable = [
        'case_id', 'clinician_id', 'diagnoses',
        'directions', 'medical_necessity', 'prescribed_at',
    ];

    protected $casts = [
        'prescribed_at' => 'datetime',
    ];

    public function patientCase()  { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function clinician()    { return $this->belongsTo(Clinician::class); }
    public function medications()  { return $this->hasMany(CasePrescriptionMedication::class, 'case_prescription_id'); }
}
