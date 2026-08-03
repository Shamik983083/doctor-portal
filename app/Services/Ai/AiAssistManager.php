<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Resolves the configured AI assist adapter and enforces the two-flag safety
 * gate before any real (non-mock) provider can be used.
 *
 * E-b: `resolveForContext()` / `liveDraftingEnabledForContext()` /
 * `disabledReasonForContext()` are the context-aware variants that read from
 * the per-integration config block. The original `resolve()` / `liveDraftingEnabled()`
 * methods are kept intact — they still use the global top-level keys so no
 * existing caller breaks.
 */
class AiAssistManager
{
    /** Adapters that perform no network effect and are always safe to run. */
    private const SAFE_ADAPTERS = ['mock'];

    // ── legacy global-scope methods (backward compat) ─────────────────────────

    public function resolve(): AiAssistAdapter
    {
        $key = config('ai.adapter', 'mock');

        if (! in_array($key, self::SAFE_ADAPTERS, true)) {
            if (! config('ai.enabled') || ! config('ai.baa_confirmed')) {
                throw new RuntimeException(
                    "AI adapter [{$key}] requires ai.enabled AND ai.baa_confirmed. Drafting sends PHI to "
                    . 'the provider, so it stays off until the BAA is executed on the account owning the '
                    . 'API key. Falling back is not automatic · set both flags deliberately or use the mock adapter.'
                );
            }
        }

        return match ($key) {
            'mock'   => new MockAiAdapter(),
            'openai' => new OpenAiAdapter(),
            default  => throw new RuntimeException("Unknown AI adapter [{$key}]."),
        };
    }

    public function liveDraftingEnabled(): bool
    {
        return (bool) config('ai.enabled')
            && (bool) config('ai.baa_confirmed')
            && ! in_array(config('ai.adapter', 'mock'), self::SAFE_ADAPTERS, true);
    }

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

    // ── context-aware integration methods (E-b) ───────────────────────────────

    /**
     * Resolve the adapter for a specific context, using that context's integration
     * config block and its own enabled/baa gates.
     */
    public function resolveForContext(string $context): AiAssistAdapter
    {
        [$adapter, $cfg] = $this->integrationFor($context);

        if (! in_array($adapter, self::SAFE_ADAPTERS, true)) {
            if (! ($cfg['enabled'] ?? false) || ! ($cfg['baa_confirmed'] ?? false)) {
                throw new RuntimeException(
                    "AI adapter [{$adapter}] for context [{$context}] requires integration.enabled AND "
                    . 'integration.baa_confirmed. Drafting sends PHI to the provider — set both flags '
                    . 'deliberately in the integration config or use the mock adapter.'
                );
            }
        }

        return match ($adapter) {
            'mock'   => new MockAiAdapter(),
            'openai' => new OpenAiAdapter($cfg),
            default  => throw new RuntimeException("Unknown AI adapter [{$adapter}]."),
        };
    }

    public function liveDraftingEnabledForContext(string $context): bool
    {
        [$adapter, $cfg] = $this->integrationFor($context);

        return (bool) ($cfg['enabled'] ?? false)
            && (bool) ($cfg['baa_confirmed'] ?? false)
            && ! in_array($adapter, self::SAFE_ADAPTERS, true);
    }

    public function disabledReasonForContext(string $context): ?string
    {
        [$adapter, $cfg] = $this->integrationFor($context);

        if (in_array($adapter, self::SAFE_ADAPTERS, true)) {
            return 'Composed from this case\'s own record. No model is configured.';
        }
        if (! ($cfg['enabled'] ?? false)) {
            return 'Composed from this case\'s own record. AI assist is switched off.';
        }
        if (! ($cfg['baa_confirmed'] ?? false)) {
            return 'Composed from this case\'s own record. The AI provider BAA has not been confirmed, '
                . 'so no patient information is sent to the model.';
        }

        return null;
    }

    /**
     * Returns [$adapterKey, $integrationConfig] for the named context.
     * Falls back to the global adapter/config so a missing integration key
     * degrades gracefully rather than crashing.
     */
    private function integrationFor(string $context): array
    {
        $integrationKey = config('ai.context_integrations.' . $context, 'clinical');
        $cfg            = config('ai.integrations.' . $integrationKey, []);

        // Fallback: if the integration block is absent, read the legacy global keys.
        if (empty($cfg)) {
            $cfg = [
                'adapter'       => config('ai.adapter', 'mock'),
                'enabled'       => config('ai.enabled', false),
                'baa_confirmed' => config('ai.baa_confirmed', false),
            ];
        }

        $adapter = $cfg['adapter'] ?? config('ai.adapter', 'mock');

        return [$adapter, $cfg];
    }
}
