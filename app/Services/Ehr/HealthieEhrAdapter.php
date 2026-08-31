<?php

namespace App\Services\Ehr;

use App\Models\PartnerEhrSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Healthie EHR adapter, scoped to ONE company's credentials.
 *
 * ============================================================================
 * NON-NEGOTIABLE: TENANT SEGREGATION.
 * ============================================================================
 * Healthie is independent of this system and its data MUST NOT cross between
 * storefronts. The same human arriving through storefront A and later through
 * storefront B is TWO records. Nothing from the first may be reused for the
 * second.
 *
 * This adapter is constructed WITH a PartnerEhrSetting and cannot be built
 * without one. There is no global credential and no fallback. That is the
 * mechanism, not a convention: storefront A's approval is pushed using
 * storefront A's own API key, so it cannot reach storefront B's data even if a
 * downstream bug asked it to.
 *
 * Rules for whoever completes the mutation below:
 *   - Patient matching MUST use the namespaced `patient.external_id` ONLY (see
 *     EhrRecordService::scopedPatientKey). NEVER fall back to email, phone or
 *     date of birth. Those are exactly the fields that are identical across
 *     storefronts for the same human, so a fallback match is how two charts
 *     silently become one.
 *   - `patient.source_external_id` is for diagnostics. It MUST NOT be a matching
 *     key on its own: two storefronts can legitimately issue the same value.
 *   - If a lookup returns a record belonging to another company, that is a bug,
 *     not a merge candidate. Fail; do not write.
 *
 * Setup and the remaining decisions: docs/integrations/HEALTHIE-SETUP.md
 * API reference: https://docs.gethealthie.com/guides/intro/
 * ============================================================================
 *
 * WHAT IS WIRED:
 *
 * Per-company credential resolution, the transport, Healthie's documented auth
 * headers (Authorization: Basic <key>, AuthorizationSource: API, and
 * AuthorizationShard when the account is sharded), error normalisation, GraphQL
 * error handling (Healthie returns HTTP 200 with an `errors` array), tenant
 * segregation check, and the full two-step flow:
 *   1. findOrCreateClient — looks up the Healthie client by namespaced patient
 *      key; creates the client if not found.
 *   2. buildMutation — posts a createFormAnswerGroup (when note_form_id is
 *      configured) or createNote (the simpler fallback).
 */
class HealthieEhrAdapter implements EhrGatewayAdapter
{
    public function __construct(private PartnerEhrSetting $settings) {}

    public function key(): string { return 'healthie'; }

    public function createRecord(array $payload): array
    {
        $this->assertPayloadBelongsToThisCompany($payload);

        if (! $this->settings->isPushable()) {
            throw new RuntimeException(
                'Healthie push is not configured for this company. Missing or disabled: '
                . implode(', ', $this->settings->missingValues() ?: ['is_enabled/sandbox_validated'])
                . '. See docs/integrations/HEALTHIE-SETUP.md.'
            );
        }

        // Step 1: resolve or create the Healthie client for this patient.
        // Throws RuntimeException on client-creation failure (retryable — the
        // outbox will try again up to max_attempts).
        $healthieClientId = $this->findOrCreateClient($payload);

        // Step 2: post the clinical note.
        [$mutation, $variables] = $this->buildMutation($payload, $healthieClientId);

        try {
            $response = $this->graphql($mutation, $variables);
        } catch (\Throwable $e) {
            Log::warning('Healthie request failed to complete', [
                'partner_id' => $this->settings->partner_id,
                'error'      => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 0, 'body' => 'Healthie could not be reached.', 'reference' => null];
        }

        $json = $response->json();

        /*
         * GraphQL returns HTTP 200 for application-level failures and reports
         * them in `errors`. Treating a 200 as success is the classic way an
         * integration reports green while writing nothing.
         */
        if (! empty($json['errors'])) {
            $messages = implode('; ', array_map(
                fn ($e) => (string) ($e['message'] ?? 'unknown'),
                $json['errors']
            ));

            Log::warning('Healthie returned GraphQL errors', [
                'partner_id' => $this->settings->partner_id,
                'errors'     => $messages,
                'full_errors' => $json['errors'] ?? [],
            ]);

            return ['ok' => false, 'code' => $response->status(), 'body' => $messages, 'reference' => null];
        }

        if (! $response->successful()) {
            return [
                'ok' => false, 'code' => $response->status(),
                'body' => 'Healthie returned HTTP ' . $response->status(), 'reference' => null,
            ];
        }

        $reference = $this->extractReference($json);

        if ($reference === null) {
            return [
                'ok' => false, 'code' => $response->status(),
                'body' => 'Healthie accepted the request but returned no record id.', 'reference' => null,
            ];
        }

        return ['ok' => true, 'code' => $response->status(), 'body' => json_encode($json['data'] ?? []), 'reference' => $reference];
    }

    /**
     * Last line of defence before anything is sent.
     *
     * The payload names the company it was built for. This adapter holds one
     * company's credential. If they ever disagree, something upstream resolved
     * the wrong settings and we are one HTTP call away from writing into the
     * wrong company's chart. Refuse.
     */
    private function assertPayloadBelongsToThisCompany(array $payload): void
    {
        $payloadPartnerId = $payload['company']['partner_id'] ?? null;

        if ((int) $payloadPartnerId !== (int) $this->settings->partner_id) {
            throw new RuntimeException(
                "Refusing to push: payload belongs to partner [{$payloadPartnerId}] but these Healthie "
                . "credentials belong to partner [{$this->settings->partner_id}]. This would cross one "
                . "storefront's data into another."
            );
        }
    }

    /**
     * Finds an existing Healthie client by namespaced patient key, or creates one.
     *
     * Search strategy: query by email within this partner's Healthie org (safe
     * because each partner credential is org-scoped, so there is no cross-tenant
     * risk in the search itself), then verify the returned user's
     * record_identifier matches our namespaced key. Email is only the initial
     * filter; the namespaced key is the authoritative match.
     *
     * On create: stores the namespaced key in record_identifier so future
     * lookups never rely on email alone.
     */
    private function findOrCreateClient(array $payload): string
    {
        $namespacedKey = $payload['patient']['external_id'];

        $lookupQuery = <<<'GQL'
        query FindHealthieClient($keywords: String) {
            users(keywords: $keywords, offset: 0, should_paginate: false) {
                id
                record_identifier
            }
        }
        GQL;

        $lookupResponse = $this->graphql($lookupQuery, [
            'keywords' => $payload['patient']['email'],
        ]);

        if ($lookupResponse->successful() && empty($lookupResponse->json('errors'))) {
            foreach ($lookupResponse->json('data.users') ?? [] as $user) {
                if (($user['record_identifier'] ?? null) === $namespacedKey) {
                    return (string) $user['id'];
                }
            }
        }

        // Client not found — create.
        $createMutation = <<<'GQL'
        mutation CreateHealthieClient($input: createClientInput!) {
            createClient(input: $input) {
                user {
                    id
                }
                messages {
                    field
                    message
                }
            }
        }
        GQL;

        // Build input and strip nulls/empty-strings. dont_send_welcome is kept
        // explicitly — it is a boolean and must not be stripped by the filter.
        $input = array_filter([
            'first_name'        => $payload['patient']['first_name'] ?? null,
            'last_name'         => $payload['patient']['last_name'] ?? null,
            'email'             => $payload['patient']['email'] ?? null,
            'phone_number'      => $payload['patient']['phone'] ?? null,
            'dob'               => $payload['patient']['date_of_birth'] ?? null,
            'gender'            => $payload['patient']['gender'] ?? null,
            'record_identifier' => $namespacedKey,
            'dietitian_id'      => $this->settings->default_provider_id ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $input['dont_send_welcome'] = true;

        $createResponse = $this->graphql($createMutation, ['input' => $input]);
        $createJson     = $createResponse->json();

        if (! empty($createJson['errors'])) {
            $messages = implode('; ', array_map(
                fn ($e) => (string) ($e['message'] ?? 'unknown'),
                $createJson['errors']
            ));
            throw new RuntimeException("Healthie client creation failed: {$messages}");
        }

        $clientId = $createJson['data']['createClient']['user']['id'] ?? null;

        if (! $clientId) {
            $fieldErrors = $createJson['data']['createClient']['messages'] ?? [];
            $detail      = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            throw new RuntimeException("Healthie returned no client ID after createClient. {$detail}");
        }

        return (string) $clientId;
    }

    /**
     * Builds the note creation mutation for the resolved Healthie client ID.
     *
     * Uses createFormAnswerGroup (a proper charting record tied to a form
     * template) when note_form_id is configured. Falls back to createNote
     * (a plain text note on the patient's timeline) when it is not. Either
     * path produces a record the extractReference helper can parse.
     */
    private function buildMutation(array $payload, string $healthieClientId): array
    {
        $noteText = $this->buildNoteText($payload);

        if ($this->settings->note_form_id) {
            $mutation = <<<'GQL'
            mutation CreateChartNote($input: createFormAnswerGroupInput!) {
                createFormAnswerGroup(input: $input) {
                    formAnswerGroup {
                        id
                    }
                    messages {
                        field
                        message
                    }
                }
            }
            GQL;

            $variables = [
                'input' => [
                    'user_id'               => $healthieClientId,
                    'custom_module_form_id' => $this->settings->note_form_id,
                    'external_id'           => $payload['patient']['external_id'],
                    'name'                  => $noteText,
                    'finished'              => true,
                    'marked_locked'         => true,
                    'created_at'            => $payload['encounter']['approved_at']
                        ?? now()->toIso8601String(),
                ],
            ];
        } else {
            $mutation = <<<'GQL'
            mutation CreateNote($input: createNoteInput!) {
                createNote(input: $input) {
                    note {
                        id
                    }
                    messages {
                        field
                        message
                    }
                }
            }
            GQL;

            $approvedAt  = $payload['encounter']['approved_at'] ?? now()->toIso8601String();
            $entryDate   = substr($approvedAt, 0, 10); // YYYY-MM-DD only

            $variables = [
                'input' => [
                    'user_id'    => $healthieClientId,
                    'content'    => $noteText,
                    'entry_date' => $entryDate,
                ],
            ];
        }

        return [$mutation, $variables];
    }

    /**
     * Assembles the clinical note text from the approval payload.
     *
     * Explicit field-by-field: a whitelist that cannot accidentally include
     * fields added later. This text is PHI — do not log it.
     */
    private function buildNoteText(array $payload): string
    {
        $lines = [];

        $lines[] = 'MEDAXIS CLINICAL NOTE';
        $lines[] = 'Case: ' . ($payload['source']['case_id'] ?? 'unknown');
        $lines[] = 'Approved: ' . ($payload['encounter']['approved_at'] ?? 'unknown');

        $clinician = $payload['encounter']['clinician']['name'] ?? null;
        if ($clinician) {
            $npi   = $payload['encounter']['clinician']['npi'] ?? null;
            $lines[] = 'Clinician: ' . $clinician . ($npi ? " (NPI: {$npi})" : '');
        }

        if (! empty($payload['medications'])) {
            $lines[] = '';
            $lines[] = 'APPROVED MEDICATIONS:';
            foreach ($payload['medications'] as $med) {
                $months = ! empty($med['months']) ? implode(', ', $med['months']) : null;
                $term   = $months ?? ($med['term'] ?? null);
                $freq   = $med['frequency'] ?? null;
                $line   = '  ' . ($med['name'] ?? '?');
                if ($term) {
                    $line .= " [{$term}]";
                }
                if ($freq) {
                    $line .= ' — ' . $freq;
                }
                $lines[] = $line;
            }
        }

        if (! empty($payload['declined'])) {
            $lines[] = '';
            $lines[] = 'DECLINED:';
            foreach ($payload['declined'] as $med) {
                $lines[] = '  ' . ($med['name'] ?? '?');
            }
        }

        $providerNote = trim((string) ($payload['note']['text'] ?? ''));
        if ($providerNote !== '') {
            $lines[] = '';
            $lines[] = 'PROVIDER NOTE:';
            $lines[] = $providerNote;
        }

        return implode("\n", $lines);
    }

    /**
     * Thin wrapper around the Healthie GraphQL endpoint with shared auth headers.
     *
     * Callers catch \Throwable for network failures; application-level errors
     * arrive as HTTP 200 with a populated `errors` array and are checked there.
     */
    private function graphql(string $query, array $variables = []): Response
    {
        return Http::withHeaders($this->settings->authHeaders())
            ->timeout((int) config('ehr.healthie.timeout', 30))
            ->acceptJson()
            ->post($this->settings->endpoint, [
                'query'     => $query,
                'variables' => $variables,
            ]);
    }

    /**
     * Pull the created record id out of the response.
     *
     * Returns null rather than guessing when the shape is unfamiliar, so an
     * unrecognised success is reported as a failure and retried, instead of
     * being marked sent with no vendor reference to point at later.
     */
    private function extractReference(?array $json): ?string
    {
        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $result) {
            if (is_array($result)) {
                foreach (['formAnswerGroup', 'note', 'client', 'user'] as $objectKey) {
                    if (! empty($result[$objectKey]['id'])) {
                        return (string) $result[$objectKey]['id'];
                    }
                }
                if (! empty($result['id'])) {
                    return (string) $result['id'];
                }
            }
        }

        return null;
    }
}
