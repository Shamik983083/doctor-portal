<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasePrescription extends Model
{
    protected $table = 'case_prescriptions';

    public const REVIEW_DRAFT     = 'draft';
    public const REVIEW_CONFIRMED = 'confirmed';

    protected $fillable = [
        'case_id', 'clinician_id', 'diagnoses',
        'directions', 'medical_necessity', 'prescribed_at',
        'review_status', 'charting_note',
    ];

    protected $casts = [
        'prescribed_at' => 'datetime',
    ];

    public function patientCase()  { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function clinician()    { return $this->belongsTo(Clinician::class); }
    public function medications()  { return $this->hasMany(CasePrescriptionMedication::class, 'case_prescription_id'); }
    public function diagnosesCodes() { return $this->hasMany(CasePrescriptionDiagnosis::class, 'case_prescription_id'); }
}
