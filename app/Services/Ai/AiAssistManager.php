<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Resolves the configured AI assist adapter and enforces the two-flag safety
 * gate before any real (non-mock) provider can be used.
 *
 * Deliberately the same shape as PharmacyGatewayManager: one place that decides
 * whether a real vendor may be touched, and it refuses rather than silently
 * falling back. A silent fallback to the mock would be worse than an error here,
 * because the caller would believe it had reached the configured provider.
 */
class AiAssistManager
{
    /** Adapters that perform no network effect and are always safe to run. */
    private const SAFE_ADAPTERS = ['mock'];

    public function resolve(): AiAssistAdapter
    {
        $key = config('ai.adapter', 'mock');

        // A non-safe adapter may only be resolved when AI assist is enabled AND
        // an operator has confirmed the BAA covering PHI sent to that provider.
        if (! in_array($key, self::SAFE_ADAPTERS, true)) {
            if (! config('ai.enabled') || ! config('ai.baa_confirmed')) {
                throw new RuntimeException(
                    "AI adapter [{$key}] requires ai.enabled AND ai.baa_confirmed. Drafting sends PHI to "
                    . 'the provider, so it stays off until the BAA is executed on the account owning the '
                    . 'API key. Falling back is not automatic — set both flags deliberately or use the mock adapter.'
                );
            }
        }

        return match ($key) {
            'mock'   => new MockAiAdapter(),
            'openai' => new OpenAiAdapter(),
            default  => throw new RuntimeException("Unknown AI adapter [{$key}]."),
        };
    }

    /** True when a real provider call should actually be made (vs composed locally). */
    public function liveDraftingEnabled(): bool
    {
        return (bool) config('ai.enabled')
            && (bool) config('ai.baa_confirmed')
            && ! in_array(config('ai.adapter', 'mock'), self::SAFE_ADAPTERS, true);
    }

    /**
     * Why live drafting is off, in words, for the provider-facing UI. Returning
     * null means it is on. The clinician should always be able to see whether a
     * draft came from the configured model or from the local composition.
     */
    public function disabledReason(): ?string
    {
        if (in_array(config('ai.adapter', 'mock'), self::SAFE_ADAPTERS, true)) {
            return 'Composed from this case\'s own record. No model is configured.';
        }
        if (! config('ai.enabled')) {
            return 'Composed from this case\'s own record. AI assist is switched off.';
        }
        if (! config('ai.baa_confirmed')) {
            return 'Composed from this case\'s own record. The AI provider BAA has not been confirmed, '
                . 'so no patient information is sent to the model.';
        }

        return null;
    }
}
