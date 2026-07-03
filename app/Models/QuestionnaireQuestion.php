<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuestionnaireQuestion extends Model
{
    protected $table = 'questionnaire_questions';

    protected $fillable = [
        'questionnaire_id', 'question', 'key', 'slug', 'type', 'placeholder',
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

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate slug on create if not explicitly supplied.
        // Priority: key field → question text. Format: snake_case, max 120 chars.
        static::creating(function (self $q) {
            if (empty($q->slug)) {
                $base    = $q->key ?: Str::slug($q->question, '_');
                $q->slug = Str::limit(Str::slug($base, '_'), 120, '');
            }
        });
    }

    public function questionnaire() { return $this->belongsTo(Questionnaire::class); }

    public function dependsOn() { return $this->belongsTo(self::class, 'depends_on_question_id'); }
}
