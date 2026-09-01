<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One worked example inside an instruction set: the situation, what a good draft
 * looks like, and optionally what to avoid.
 *
 * These are the part that actually changes model behaviour, which is why they
 * are first-class rows the admin can add and remove one at a time rather than
 * prose buried inside the instructions field.
 */
class AiInstructionExample extends Model
{
    protected $fillable = [
        'ai_instruction_set_id',
        'situation',
        'good_output',
        'bad_output',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function instructionSet(): BelongsTo
    {
        return $this->belongsTo(AiInstructionSet::class, 'ai_instruction_set_id');
    }
}
