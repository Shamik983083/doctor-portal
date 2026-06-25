<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseEvent extends Model
{
    protected $table = 'case_events';

    protected $fillable = [
        'case_id', 'event_type', 'actor_type', 'actor_id', 'payload', 'notes',
    ];

    protected $casts = ['payload' => 'array'];

    public function case() { return $this->belongsTo(PatientCase::class, 'case_id'); }
}
