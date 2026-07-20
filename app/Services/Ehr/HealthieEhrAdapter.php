<?php

namespace App\Services\Ehr;

use App\Models\PartnerEhrSetting;
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
 * WHAT IS WIRED, AND WHAT IS DELIBERATELY NOT.
 *
 * Wired and real: per-company credential resolution, the transport, Healthie's
 * documented auth headers (Authorization: Basic <key>, AuthorizationSource: API,
 * and AuthorizationShard when the account is sharded), error normalisation, and
 * GraphQL error handling (Healthie returns HTTP 200 with an `errors` array, so
 * checking the status alone would read a failure as a success).
 *
 * NOT written, on purpose: the mutation document itself. Healthie's published
 * guides do not include the mutation names or field shapes, and their schema
 * reference needs credentials to read. Inventing `createClient`/`createFormAnswerGroup`
 * field-by-field would produce code that looks finished, passes review by shape,
 * and fails or writes wrong records the first time it is switched on. The one
 * thing left is the GraphQL document; everything around it is done.
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

        [$mutation, $variables] = $this->buildMutation($payload);

        try {
            $response = Http::withHeaders($this->settings->authHeaders())
                ->timeout((int) config('ehr.healthie.timeout', 30))
                ->acceptJson()
                ->post($this->settings->endpoint, [
                    'query'     => $mutation,
                    'variables' => $variables,
                ]);
        } catch (\Throwable $e) {
            // Never log the payload: it is PHI.
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
     * THE ONE PIECE STILL TO WRITE.
     *
     * Everything this needs is already resolved and passed in: the company's
     * organization_id, the Healthie user to attribute the note to
     * (default_provider_id), the form the note becomes (note_form_id), and the
     * namespaced patient key that must be the ONLY matching key.
     *
     * See docs/integrations/HEALTHIE-SETUP.md for what has to be confirmed
     * against the schema reference before this is filled in.
     */
    private function buildMutation(array $payload): array
    {
        throw new RuntimeException(
            'The Healthie GraphQL mutation has not been written yet. Everything around it is wired: '
            . 'per-company credentials, auth headers, transport, and error handling. What is missing is the '
            . 'mutation document itself, which depends on decisions that must be confirmed against Healthie\'s '
            . 'schema reference (which object the note becomes, how a client is created and matched, and how '
            . 'the signing clinician maps to a Healthie user). See docs/integrations/HEALTHIE-SETUP.md.'
        );
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
