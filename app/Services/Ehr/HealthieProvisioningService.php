<?php

namespace App\Services\Ehr;

use App\Models\Clinician;
use App\Models\Partner;
use App\Models\PartnerEhrSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        // createOrganizationInput has no top-level `name` field.
        // The org name lives inside organization_info (PrimaryOrganizationInfoInput).
        // The mutation also creates an admin user for the sub-org, so email/name/password are required.
        $email = $partner->email ?: ('partner-' . $partner->id . '@axismd.io');
        [$firstName, $lastName] = $this->splitName($partner->name);

        $response = $this->graphql($mutation, [
            'input' => [
                'email'                     => $email,
                'first_name'                => $firstName,
                'last_name'                 => $lastName ?: 'Admin',
                'password'                  => Str::random(12) . 'A1!',
                'create_as_suborganization' => true,
                'organization_email'        => $email,
                'organization_info'         => [
                    'name' => $partner->name,
                ],
            ],
        ], $apiKey, $endpoint);
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
     * Find or create a Healthie provider in the given partner's sub-org.
     *
     * Uses createOrganizationMembership which creates the provider account AND
     * adds them to the org in a single call — signUp alone cannot target a specific
     * sub-org. If the provider already exists in this sub-org, returns their ID.
     * Returns the Healthie user ID.
     */
    public function provisionClinician(Clinician $clinician, PartnerEhrSetting $settings): string
    {
        if (empty($settings->api_key) || empty($settings->endpoint) || empty($settings->organization_id)) {
            throw new RuntimeException(
                "Partner [{$settings->partner_id}] is missing Healthie API key, endpoint, or organization_id. "
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
        mutation CreateOrgMember($input: createOrganizationMembershipInput!) {
            createOrganizationMembership(input: $input) {
                organizationMembership {
                    id
                    user {
                        id
                    }
                }
                messages {
                    field
                    message
                }
            }
        }
        GQL;

        [$firstName, $lastName] = $this->splitName($user->name);

        $response = $this->graphql(
            $mutation,
            ['input' => array_filter([
                'email'             => $user->email,
                'first_name'        => $firstName,
                'last_name'         => $lastName ?: null,
                'password'          => Str::random(12) . 'A1!',
                'phone_number'      => $clinician->phone ?: '0000000000',
                'organization_id'   => $settings->organization_id,
                'send_invite_email' => false,
            ], fn ($v) => $v !== null && $v !== '')],
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

        $membership  = $json['data']['createOrganizationMembership']['organizationMembership'] ?? null;
        $userId      = $membership['user']['id'] ?? null;
        $fieldErrors = $json['data']['createOrganizationMembership']['messages'] ?? [];

        if (! $userId) {
            // "Already a member" arrives as a field error — look them up instead of failing.
            foreach ($fieldErrors as $fe) {
                if (str_contains(strtolower($fe['message'] ?? ''), 'already')) {
                    $found = $this->findProviderByEmail($user->email, $settings);
                    if ($found) {
                        return $found;
                    }
                }
            }

            $detail = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            throw new RuntimeException(
                "Healthie returned no user ID after createOrganizationMembership for clinician [{$clinician->id}]. {$detail}"
            );
        }

        Log::info('HealthieProvisioning: clinician provisioned into org', [
            'clinician_id' => $clinician->id,
            'partner_id'   => $settings->partner_id,
            'healthie_id'  => $userId,
            'org_id'       => $settings->organization_id,
        ]);

        return (string) $userId;
    }

    /**
     * Look up a provider by email within this partner's Healthie sub-org.
     * Returns their Healthie user ID or null if not found.
     */
    private function findProviderByEmail(string $email, PartnerEhrSetting $settings): ?string
    {
        // type: "Provider" is required — without it Healthie returns only patients.
        $query = <<<'GQL'
        query FindProvider($keywords: String) {
            users(keywords: $keywords, offset: 0, should_paginate: false, type: "Provider") {
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
