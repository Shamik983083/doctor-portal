<?php

namespace App\Services\Ehr;

use App\Models\Clinician;
use App\Models\Partner;
use App\Models\PartnerEhrSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles Healthie organisational provisioning — creating sub-orgs and provider
 * accounts — separately from the prescription push adapter.
 *
 * WHY SEPARATE FROM HealthieEhrAdapter:
 *   The adapter is scoped to one partner's credentials and handles per-case pushes.
 *   Provisioning crosses that boundary: sub-org creation needs the PARENT org's
 *   credentials, and provider creation iterates across multiple partners. Mixing
 *   these concerns into the per-partner adapter would force callers to hold the
 *   wrong credential for the job.
 *
 * TENANT SAFETY:
 *   Every method is explicit about which credential it uses and why. Sub-org
 *   creation uses the global parent key (config). Provider provisioning uses the
 *   target partner's own key so it is authenticated as that sub-org's admin.
 */
class HealthieProvisioningService
{
    /**
     * Create a Healthie sub-organisation for the given partner.
     *
     * Authenticated as the parent org using HEALTHIE_PARENT_API_KEY.
     * Returns the new Healthie org ID, which must be stored in
     * partner_ehr_settings.organization_id immediately after.
     */
    public function createSubOrganization(Partner $partner): string
    {
        $apiKey   = config('ehr.healthie.parent_api_key');
        $endpoint = config('ehr.healthie.endpoint');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'HEALTHIE_PARENT_API_KEY is not set. Add it to .env to enable automatic '
                . 'sub-org creation. See docs/integrations/HEALTHIE-SETUP.md.'
            );
        }

        $mutation = <<<'GQL'
        mutation CreateSubOrganization($input: createOrganizationInput!) {
            createOrganization(input: $input) {
                organization {
                    id
                    name
                }
                messages {
                    field
                    message
                }
            }
        }
        GQL;

        $response = $this->graphql($mutation, ['input' => ['name' => $partner->name]], $apiKey, $endpoint);
        $json     = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $json['errors']));
            throw new RuntimeException("Healthie sub-org creation failed for [{$partner->name}]: {$msg}");
        }

        $orgId = $json['data']['createOrganization']['organization']['id'] ?? null;

        if (! $orgId) {
            $fieldErrors = $json['data']['createOrganization']['messages'] ?? [];
            $detail      = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            throw new RuntimeException("Healthie returned no organization ID for [{$partner->name}]. {$detail}");
        }

        Log::info('HealthieProvisioning: sub-org created', [
            'partner_id'   => $partner->id,
            'partner_name' => $partner->name,
            'org_id'       => $orgId,
        ]);

        return (string) $orgId;
    }

    /**
     * Find or create a Healthie provider user in the given partner's sub-org.
     *
     * Authenticated as the sub-org using the partner's own API key.
     * First checks if a user with this email already exists to stay idempotent.
     * Returns the Healthie user ID.
     */
    public function provisionClinician(Clinician $clinician, PartnerEhrSetting $settings): string
    {
        if (empty($settings->api_key) || empty($settings->endpoint)) {
            throw new RuntimeException(
                "Partner [{$settings->partner_id}] has no Healthie API key or endpoint. "
                . 'Configure those fields before provisioning providers.'
            );
        }

        $user = $clinician->user;

        // Idempotent: if this provider already exists in this sub-org return their ID.
        $existingId = $this->findProviderByEmail($user->email, $settings);
        if ($existingId) {
            return $existingId;
        }

        $mutation = <<<'GQL'
        mutation CreateProvider($input: signUpInput!) {
            signUp(input: $input) {
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

        [$firstName, $lastName] = $this->splitName($user->name);

        $input = array_filter([
            'first_name' => $firstName,
            'last_name'  => $lastName ?: null,
            'email'      => $user->email,
            'role'       => 'provider',
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->graphql(
            $mutation,
            ['input' => $input],
            $settings->api_key,
            $settings->endpoint,
            $settings->authorization_shard
        );

        $json = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $json['errors']));
            throw new RuntimeException(
                "Healthie provider creation failed for clinician [{$clinician->id}] "
                . "in partner [{$settings->partner_id}]: {$msg}"
            );
        }

        $userId = $json['data']['signUp']['user']['id'] ?? null;

        if (! $userId) {
            $fieldErrors = $json['data']['signUp']['messages'] ?? [];
            $detail      = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            throw new RuntimeException(
                "Healthie returned no user ID after signUp for clinician [{$clinician->id}]. {$detail}"
            );
        }

        return (string) $userId;
    }

    /**
     * Add an existing Healthie user to an organisation as a provider member.
     *
     * Idempotent: "already a member" responses are silently absorbed so
     * re-running provisioning never fails on an already-synced clinician.
     */
    public function addProviderToOrg(string $healthieUserId, string $orgId, PartnerEhrSetting $settings): void
    {
        $mutation = <<<'GQL'
        mutation AddProviderToOrg($input: createOrganizationMembershipInput!) {
            createOrganizationMembership(input: $input) {
                organizationMembership {
                    id
                }
                messages {
                    field
                    message
                }
            }
        }
        GQL;

        $response = $this->graphql(
            $mutation,
            ['input' => ['user_id' => $healthieUserId, 'organization_id' => $orgId, 'role' => 'provider']],
            $settings->api_key,
            $settings->endpoint,
            $settings->authorization_shard
        );

        $json = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $json['errors']));

            // "Already a member" is not a real failure — provisioning re-runs must be idempotent.
            if (str_contains(strtolower($msg), 'already')) {
                return;
            }

            throw new RuntimeException(
                "Failed to add Healthie user [{$healthieUserId}] to org [{$orgId}]: {$msg}"
            );
        }
    }

    /**
     * Look up a provider by email within this partner's Healthie sub-org.
     * Returns their Healthie user ID or null if not found.
     */
    private function findProviderByEmail(string $email, PartnerEhrSetting $settings): ?string
    {
        $query = <<<'GQL'
        query FindProvider($keywords: String) {
            users(keywords: $keywords, offset: 0, should_paginate: false) {
                id
                email
            }
        }
        GQL;

        try {
            $response = $this->graphql(
                $query,
                ['keywords' => $email],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );

            foreach ($response->json('data.users') ?? [] as $user) {
                if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                    return (string) $user['id'];
                }
            }
        } catch (\Throwable $e) {
            // Lookup failure is non-fatal: fall through to create
            Log::warning('HealthieProvisioning: provider lookup failed, will attempt create', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [$parts[0] ?? $fullName, $parts[1] ?? ''];
    }

    private function graphql(string $query, array $variables, string $apiKey, string $endpoint, ?string $shard = null): Response
    {
        $headers = [
            'Authorization'       => 'Basic ' . $apiKey,
            'AuthorizationSource' => 'API',
        ];

        if (! empty($shard)) {
            $headers['AuthorizationShard'] = $shard;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('ehr.healthie.timeout', 30))
            ->acceptJson()
            ->post($endpoint, ['query' => $query, 'variables' => $variables]);
    }
}
