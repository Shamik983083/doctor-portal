<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireQuestion extends Model
{
    protected $table = 'questionnaire_questions';

    protected $fillable = [
        'questionnaire_id', 'question', 'type', 'options', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function questionnaire() { return $this->belongsTo(Questionnaire::class); }
}
