<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-owned instruction guidance the AI assist draws on for one context.
 *
 * "Educating the bot" is editing these, not training a model. Every edit bumps
 * the version so a draft can be traced back to the instructions that produced
 * it, which is the difference between a prompt you can audit and one you cannot.
 */
class AiInstructionSet extends Model
{
    protected $fillable = [
        'context',
        'name',
        'instructions',
        'tone',
        'version',
        'is_active',
        'sort_order',
        'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'version'    => 'integer',
        'sort_order' => 'integer',
    ];

    public function examples(): HasMany
    {
        return $this->hasMany(AiInstructionExample::class)->orderBy('sort_order');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contextLabel(): string
    {
        return config('ai.contexts')[$this->context] ?? $this->context;
    }

    /**
     * The active set for a context, or null.
     *
     * Null is a legitimate answer and callers must handle it: an admin can
     * deactivate every set for a context, and the service treats that as
     * "compose locally, do not call the model with no guidance" rather than
     * sending an unguided prompt about a patient.
     */
    public static function activeFor(string $context): ?self
    {
        return static::with('examples')
            ->where('context', $context)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Render this set, and its worked examples, as the instruction string sent
     * to the provider. Examples are what actually move a model's output, so
     * they are included rather than kept as documentation for humans.
     */
    public function toPromptInstructions(): string
    {
        $parts = [trim($this->instructions)];

        if (! empty($this->tone)) {
            $parts[] = 'Tone and style: ' . trim($this->tone);
        }

        $examples = $this->relationLoaded('examples') ? $this->examples : $this->examples()->get();

        foreach ($examples as $i => $example) {
            $block = 'Example ' . ($i + 1) . '.' . "\n"
                . 'Situation: ' . trim($example->situation) . "\n"
                . 'Good: ' . trim($example->good_output);

            if (! empty($example->bad_output)) {
                $block .= "\n" . 'Avoid: ' . trim($example->bad_output);
            }

            $parts[] = $block;
        }

        return implode("\n\n", $parts);
    }
}
