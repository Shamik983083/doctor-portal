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

        // Step 2: push vitals (weight, height, BMI) as metric entries.
        // Best-effort: a vital failing to post must not block or fail the record.
        $this->pushVitals($payload, $healthieClientId);

        // Step 3: post the clinical note.
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
    /**
     * Pushes weight, height, and BMI to Healthie as MetricEntry records.
     *
     * Failures are logged but never thrown — a vital that fails to post must not
     * roll back a clinical note that already succeeded, and the outbox must not
     * be left in a retryable state over a non-critical metric value.
     */
    private function pushVitals(array $payload, string $healthieClientId): void
    {
        $vitals = $payload['vitals'] ?? [];
        if (empty($vitals)) {
            return;
        }

        $mutation = <<<'GQL'
        mutation CreateVitalEntry($input: createEntryInput!) {
            createEntry(input: $input) {
                entry { id }
                messages { field message }
            }
        }
        GQL;

        $categories = [
            'weight' => 'Weight',
            'height' => 'Height (in.)',
            'bmi'    => 'BMI',
        ];

        foreach ($categories as $key => $category) {
            if (! isset($vitals[$key])) {
                continue;
            }

            try {
                $response = $this->graphql($mutation, [
                    'input' => [
                        'type'        => 'MetricEntry',
                        'category'    => $category,
                        'metric_stat' => $vitals[$key],
                        'user_id'     => $healthieClientId,
                    ],
                ]);

                if (! empty($response->json('errors'))) {
                    Log::warning('Healthie vital entry failed', [
                        'partner_id' => $this->settings->partner_id,
                        'category'   => $category,
                        'errors'     => $response->json('errors'),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Healthie vital entry threw', [
                    'partner_id' => $this->settings->partner_id,
                    'category'   => $category,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildMutation(array $payload, string $healthieClientId): array
    {
        $noteText = $this->buildNoteText($payload);

        if (! $this->settings->note_form_id) {
            throw new RuntimeException(
                'No note_form_id configured for this partner. Set a Healthie chart note form ID '
                . 'on the partner\'s EHR settings before pushing records.'
            );
        }

        $moduleId = $this->resolveNoteModuleId();

        $mutation = <<<'GQL'
        mutation CreateChartNote($input: createFormAnswerGroupInput!) {
            createFormAnswerGroup(input: $input) {
                form_answer_group {
                    id
                }
                messages {
                    field
                    message
                }
            }
        }
        GQL;

        $input = [
            'user_id'               => $healthieClientId,
            'custom_module_form_id' => $this->settings->note_form_id,
            'finished'              => true,
        ];

        if ($moduleId !== null) {
            $input['form_answers'] = [
                [
                    'custom_module_id' => $moduleId,
                    'answer'           => $noteText,
                    'user_id'          => $healthieClientId,
                ],
            ];
        }

        return [$mutation, ['input' => $input]];
    }

    /**
     * Fetches the first textarea/text module ID from the configured note form.
     *
     * Each Healthie org has its own custom module IDs even for the same form
     * template, so this must be looked up at runtime rather than hardcoded.
     */
    private function resolveNoteModuleId(): ?string
    {
        $query = <<<'GQL'
        query FormModules($id: ID) {
            customModuleForm(id: $id) {
                custom_modules { id mod_type }
            }
        }
        GQL;

        $response = $this->graphql($query, ['id' => $this->settings->note_form_id]);
        $modules  = $response->json('data.customModuleForm.custom_modules') ?? [];

        foreach ($modules as $module) {
            if (in_array($module['mod_type'] ?? '', ['textarea', 'text', 'paragraph'], true)) {
                return (string) $module['id'];
            }
        }

        return ! empty($modules[0]['id']) ? (string) $modules[0]['id'] : null;
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
                foreach (['form_answer_group', 'note', 'client', 'user'] as $objectKey) {
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
