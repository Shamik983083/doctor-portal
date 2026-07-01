<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireQuestion extends Model
{
    protected $table = 'questionnaire_questions';

    protected $fillable = [
        'questionnaire_id', 'question', 'key', 'type', 'placeholder',
        'options', 'is_required', 'is_readonly', 'is_active', 'sort_order', 'step_number',
        'depends_on_question_id', 'depends_on_operator', 'depends_on_value',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'is_active'   => 'boolean',
        'step_number' => 'integer',
    ];

    public function questionnaire() { return $this->belongsTo(Questionnaire::class); }

    public function dependsOn() { return $this->belongsTo(self::class, 'depends_on_question_id'); }
}
