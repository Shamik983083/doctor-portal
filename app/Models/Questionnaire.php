<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Questionnaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['uuid', 'partner_id', 'name', 'description', 'is_active', 'mode', 'linked_questionnaire_id', 'purpose'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function partner()              { return $this->belongsTo(Partner::class); }
    public function questions()            { return $this->hasMany(QuestionnaireQuestion::class)->orderBy('step_number')->orderBy('sort_order'); }
    public function responses()            { return $this->hasMany(QuestionnaireResponse::class); }
    public function linkedQuestionnaire()  { return $this->belongsTo(Questionnaire::class, 'linked_questionnaire_id'); }

    public function offerings()
    {
        return $this->belongsToMany(Offering::class, 'offering_questionnaire')
                    ->withPivot('is_required', 'sort_order');
    }
}
