<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuestionnaireResponse extends Model
{
    protected $fillable = [
        'token', 'questionnaire_id', 'patient_id', 'partner_id',
        'case_id', 'external_patient_id',
        'is_disqualified', 'disqualified_on', 'completed_at',
    ];

    protected $casts = [
        'is_disqualified' => 'boolean',
        'completed_at'    => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->token = $m->token ?? (string) Str::uuid());
    }

    public function questionnaire() { return $this->belongsTo(Questionnaire::class); }
    public function patient()       { return $this->belongsTo(Patient::class); }
    public function partner()       { return $this->belongsTo(Partner::class); }
    public function patientCase()   { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function answers()       { return $this->hasMany(QuestionnaireAnswer::class, 'response_id'); }
}
