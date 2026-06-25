<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseQuestion extends Model
{
    protected $table = 'case_questions';

    protected $fillable = [
        'case_id', 'questionnaire_question_id', 'question', 'answer', 'type', 'sort_order',
    ];

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function questionnaireQuestion() { return $this->belongsTo(QuestionnaireQuestion::class); }
}
