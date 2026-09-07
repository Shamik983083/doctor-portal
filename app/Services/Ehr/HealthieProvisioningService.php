<?php

namespace App\Services\Ehr;

use App\Models\Clinician;
use App\Models\PartnerEhrSetting;
use App\Models\SubStorefront;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles Healthie organisational provisioning — creating user groups and
 * ensuring provider accounts exist — separately from the prescription push adapter.
 *
 * ARCHITECTURE NOTE (changed from sub-org model):
 *   Healthie sub-organisations are an enterprise-only feature and are no longer
 *   used. Patient and provider segregation is now achieved through:
 *     1. User Groups  — each sub-storefront maps to one Healthie user group.
 *        Patients are assigned to their sub-storefront's group at creation.
 *     2. Care Teams   — the prescribing clinician is added to each patient's
 *        care team at push time, allowing permission-scoped access.
 *
 *   All operations use the PARTNER's single Healthie organisation credentials
 *   (from PartnerEhrSetting). Sub-storefronts no longer carry their own API
 *   key or endpoint — only a group ID.
 */
class HealthieProvisioningService
{
    /**
     * Create a Healthie User Group for the given sub-storefront within the
     * partner's single Healthie organisation.
     *
     * Uses the partner's PartnerEhrSetting credentials.
     * Returns the new Healthie group ID, which must be stored as
     * sub_storefronts.healthie_default_group_id immediately after.
     *
     * Per Healthie docs: https://docs.gethealthie.com/reference/2024-06-01/mutations/creategroup
     */
    public function createUserGroup(SubStorefront $subStorefront, PartnerEhrSetting $partnerSettings): string
    {
        if (empty($partnerSettings->api_key) || empty($partnerSettings->endpoint)) {
            throw new RuntimeException(
                "Cannot create Healthie user group for sub-storefront [{$subStorefront->id}]: "
                . "partner [{$subStorefront->partner_id}] has no Healthie API key or endpoint configured."
            );
        }

        $mutation = <<<'GQL'
        mutation CreateGroup($input: createGroupInput!) {
            createGroup(input: $input) {
                group {
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

        $response = $this->graphql($mutation, [
            'input' => [
                'name' => $subStorefront->name,
            ],
        ], $partnerSettings->api_key, $partnerSettings->endpoint, $partnerSettings->authorization_shard);

        $json = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $json['errors']));
            throw new RuntimeException("Healthie user group creation failed for sub-storefront [{$subStorefront->name}]: {$msg}");
        }

        $groupId = $json['data']['createGroup']['group']['id'] ?? null;

        if (! $groupId) {
            $fieldErrors = $json['data']['createGroup']['messages'] ?? [];
            $detail      = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            throw new RuntimeException("Healthie returned no group ID for sub-storefront [{$subStorefront->name}]. {$detail}");
        }

        Log::info('HealthieProvisioning: user group created', [
            'sub_storefront_id'   => $subStorefront->id,
            'sub_storefront_name' => $subStorefront->name,
            'partner_id'          => $subStorefront->partner_id,
            'group_id'            => $groupId,
        ]);

        return (string) $groupId;
    }

    /**
     * Find or create a Healthie provider in the given partner's organisation.
     *
     * Uses createOrganizationMembership which creates the provider account AND
     * adds them to the org in a single call. If the provider already exists,
     * returns their ID. Returns the Healthie user ID.
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

        // Idempotent: if this provider already exists in this org return their ID.
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
     * Push current provider profile details to an already-provisioned Healthie account.
     *
     * Called after initial provisioning AND as the update path when a clinician's
     * NPI, credentials, specialty, phone, or licensed states change in this system.
     *
     * Fields synced (all best-effort — a missing value is omitted, not errored):
     *   npi_number      ← clinician.npi
     *   credentials     ← clinician.credentials  (e.g. "MD", "DO", "NP")
     *   specialty       ← clinician.specialty
     *   phone_number    ← clinician.phone
     *   first_name /
     *   last_name       ← clinician.user.name
     *   dietitian_setting.licensed_in_states
     *                   ← clinician.licensed_states[].state  (comma-separated)
     *
     * Throws on GraphQL-level errors so the caller (provisioning job) can mark the
     * mapping as failed and surface the error through the normal retry path.
     */
    public function updateProviderDetails(Clinician $clinician, PartnerEhrSetting $settings, string $healthieUserId): void
    {
        $user                      = $clinician->user;
        [$firstName, $lastName]    = $this->splitName($user->name);

        $licensedInStates = null;
        if (! empty($clinician->licensed_states)) {
            $codes = collect($clinician->licensed_states)
                ->pluck('state')
                ->filter()
                ->map(fn ($s) => strtoupper(trim($s)))
                ->unique()
                ->values()
                ->all();

            if (! empty($codes)) {
                $licensedInStates = implode(', ', $codes);
            }
        }

        $input = array_filter([
            'id'           => $healthieUserId,
            'first_name'   => $firstName ?: null,
            'last_name'    => $lastName ?: null,
            'phone_number' => $clinician->phone ?: null,
            'npi_number'   => $clinician->npi ?: null,
            'credentials'  => $clinician->credentials ?: null,
            'specialty'    => $clinician->specialty ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        // id must always be present even if somehow blank-filtered (it won't be).
        $input['id'] = $healthieUserId;

        if ($licensedInStates !== null) {
            $input['dietitian_setting'] = ['licensed_in_states' => $licensedInStates];
        }

        $mutation = <<<'GQL'
        mutation UpdateProvider($input: updateUserInput!) {
            updateUser(input: $input) {
                user {
                    id
                    npi_number
                    credentials
                    specialty
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
            ['input' => $input],
            $settings->api_key,
            $settings->endpoint,
            $settings->authorization_shard
        );

        $json = $response->json();

        if (! empty($json['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $json['errors']));
            throw new RuntimeException(
                "Healthie provider update failed for clinician [{$clinician->id}] "
                . "partner [{$settings->partner_id}]: {$msg}"
            );
        }

        $fieldErrors = $json['data']['updateUser']['messages'] ?? [];
        if (! empty($fieldErrors)) {
            $detail = implode('; ', array_map(
                fn ($m) => ($m['field'] ?? '?') . ': ' . ($m['message'] ?? '?'),
                $fieldErrors
            ));
            Log::warning('HealthieProvisioning: provider update had field errors', [
                'clinician_id'     => $clinician->id,
                'partner_id'       => $settings->partner_id,
                'healthie_user_id' => $healthieUserId,
                'errors'           => $detail,
            ]);
        }

        Log::info('HealthieProvisioning: provider details updated', [
            'clinician_id'     => $clinician->id,
            'partner_id'       => $settings->partner_id,
            'healthie_user_id' => $healthieUserId,
            'fields_sent'      => array_keys($input),
        ]);
    }

    /**
     * Look up a provider by email within a partner's Healthie org.
     * Returns their Healthie user ID or null if not found.
     */
    private function findProviderByEmail(string $email, PartnerEhrSetting $settings): ?string
    {
        return $this->findProviderByEmailWithCreds(
            $email,
            $settings->api_key,
            $settings->endpoint,
            $settings->authorization_shard,
        );
    }

    /**
     * Shared provider lookup by raw credentials.
     * type: "Provider" is required — without it Healthie returns only patients.
     */
    private function findProviderByEmailWithCreds(
        string $email,
        string $apiKey,
        string $endpoint,
        ?string $shard = null,
    ): ?string {
        $query = <<<'GQL'
        query FindProvider($keywords: String) {
            users(keywords: $keywords, offset: 0, should_paginate: false, type: "Provider") {
                id
                email
            }
        }
        GQL;

        try {
            $response = $this->graphql($query, ['keywords' => $email], $apiKey, $endpoint, $shard);

            foreach ($response->json('data.users') ?? [] as $user) {
                if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                    return (string) $user['id'];
                }
            }
        } catch (\Throwable $e) {
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
