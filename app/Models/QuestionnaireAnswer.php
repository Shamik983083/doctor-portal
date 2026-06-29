<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'response_id', 'question_id', 'question_text', 'answer', 'is_disqualified',
    ];

    protected $casts = [
        'is_disqualified' => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function response() { return $this->belongsTo(QuestionnaireResponse::class, 'response_id'); }
    public function question() { return $this->belongsTo(QuestionnaireQuestion::class, 'question_id'); }
}
