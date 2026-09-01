<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real OpenAI adapter, reachable only through AiAssistManager's two-flag gate.
 *
 * Talks to the Responses API. Two modes, decided by config:
 *
 *  1. STORED PROMPT (`prompt_id` set) · the "GPT agent / skill set we set up"
 *     case. The prompt, its tools and its style live on the OpenAI side and are
 *     versioned there; this app just references the id and sends the case material
 *     as input.
 *
 *  2. INLINE INSTRUCTIONS (no prompt id) · the instruction set stored in this
 *     app is sent as `instructions`. This is the mode the admin screen drives,
 *     and it is the one that lets the product be re-taught without a deploy.
 *
 * E-b: the constructor now accepts an optional integration config array. When
 * provided (by `AiAssistManager::resolveForContext()`), it overrides the legacy
 * `config('ai.openai.*')` keys, allowing each integration to have its own API
 * key, model, and base URI. When omitted the adapter falls back to the global
 * `ai.openai` block, keeping the existing `resolve()` path unchanged.
 *
 * PHI POSTURE: `store` defaults to false so the provider retains nothing. This
 * is a request-level control and it does NOT substitute for the BAA; the gate in
 * AiAssistManager is what enforces that. Never log the input or the output here,
 * both contain patient information. Errors log the status code and nothing else.
 */
class OpenAiAdapter implements AiAssistAdapter
{
    public function __construct(private array $cfg = []) {}

    public function key(): string { return 'openai'; }

    public function draft(array $prompt): array
    {
        $key = $this->c('api_key');
        if (empty($key)) {
            throw new RuntimeException('OpenAI API key is not set for this integration.');
        }

        $body = [
            'store' => (bool) $this->c('store', false),
            'input' => $prompt['input'] ?? '',
        ];

        $promptId = $prompt['prompt_id'] ?? $this->c('prompt_id');

        if (! empty($promptId)) {
            $body['prompt'] = array_filter([
                'id'      => $promptId,
                'version' => $this->c('prompt_version'),
            ]);
        } else {
            $body['model']        = $this->c('model');
            $body['instructions'] = $prompt['instructions'] ?? '';
        }

        /*
         * Attach the uploaded criteria so the model retrieves from them rather
         * than relying on what is in the prompt. This is what makes the skill
         * set extensible by uploading a document instead of editing code.
         *
         * Only attached when a stored prompt is NOT in use: a stored prompt
         * carries its own tool configuration on the OpenAI side, and attaching
         * here as well would override what was configured there.
         */
        $vectorStoreIds = $this->c('vector_store_ids', []);

        if (empty($promptId) && ! empty($vectorStoreIds)) {
            $body['tools'] = [[
                'type'             => 'file_search',
                'vector_store_ids' => array_values($vectorStoreIds),
            ]];
        }

        try {
            $response = Http::withToken($key)
                ->timeout((int) $this->c('timeout', 30))
                ->acceptJson()
                ->post(rtrim((string) $this->c('base_uri', 'https://api.openai.com/v1'), '/') . '/responses', $body);
        } catch (\Throwable $e) {
            // Deliberately does not include the request body: it is PHI.
            Log::warning('AI assist request failed to complete', [
                'context' => $prompt['context'] ?? null,
                'error'   => $e->getMessage(),
            ]);

            return [
                'ok' => false, 'code' => 0, 'text' => '',
                'error' => 'The AI provider could not be reached.', 'model' => null, 'usage' => null,
            ];
        }

        if (! $response->successful()) {
            Log::warning('AI assist returned a non-success status', [
                'context' => $prompt['context'] ?? null,
                'status'  => $response->status(),
            ]);

            return [
                'ok' => false, 'code' => $response->status(), 'text' => '',
                'error' => 'The AI provider returned an error.', 'model' => null, 'usage' => null,
            ];
        }

        $json = $response->json();
        $text = $this->extractText($json);

        if ($text === '') {
            return [
                'ok' => false, 'code' => $response->status(), 'text' => '',
                'error' => 'The AI provider returned no usable text.', 'model' => $json['model'] ?? null,
                'usage' => $json['usage'] ?? null,
            ];
        }

        return [
            'ok'    => true,
            'code'  => $response->status(),
            'text'  => $text,
            'error' => null,
            'model' => $json['model'] ?? null,
            'usage' => $json['usage'] ?? null,
        ];
    }

    /**
     * Read from the injected integration config, falling back to the legacy
     * `ai.openai.*` config key. This lets the adapter work identically whether
     * constructed with an integration config (E-b path) or without (legacy path).
     */
    private function c(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->cfg)
            ? $this->cfg[$key]
            : config('ai.openai.' . $key, $default);
    }

    /**
     * Pull the text out of a Responses API payload.
     *
     * `output_text` is the convenience field and is preferred when present. The
     * walk over `output[].content[].text` is the documented structure and is
     * kept as the fallback so a payload without the convenience field still
     * works. Returning '' rather than guessing is intentional: an empty draft
     * surfaces as an error the provider sees, whereas a partial one would look
     * like a real note.
     */
    private function extractText(?array $json): string
    {
        if (! is_array($json)) {
            return '';
        }

        if (is_string($json['output_text'] ?? null) && trim($json['output_text']) !== '') {
            return trim($json['output_text']);
        }

        $parts = [];
        foreach ($json['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $chunk) {
                if (is_string($chunk['text'] ?? null)) {
                    $parts[] = $chunk['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
