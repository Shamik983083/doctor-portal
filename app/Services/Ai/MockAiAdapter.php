<?php

namespace App\Services\Ai;

/**
 * No-network AI adapter (the safe default).
 *
 * It returns the deterministic composition the service already built from the
 * case record, which is exactly what the design preview does today. Nothing is
 * sent anywhere and no PHI leaves the system.
 *
 * This is the reason the composition lives in AiAssistService and not in the
 * OpenAI adapter: with the mock selected the product still produces a usable,
 * grounded draft, so the whole approval flow can be exercised end to end in
 * staging without a model, a key or a BAA. Turning the real provider on changes
 * where the words come from, not the shape of anything around it.
 */
class MockAiAdapter implements AiAssistAdapter
{
    public function key(): string { return 'mock'; }

    public function draft(array $prompt): array
    {
        $fallback = trim($prompt['fallback'] ?? '');

        if ($fallback === '') {
            return [
                'ok'    => false,
                'code'  => 422,
                'text'  => '',
                'error' => 'Nothing to compose: the case record produced no statements.',
                'model' => null,
                'usage' => null,
            ];
        }

        return [
            'ok'    => true,
            'code'  => 200,
            'text'  => $fallback,
            'error' => null,
            'model' => 'mock-local-composition',
            'usage' => null,
        ];
    }
}
