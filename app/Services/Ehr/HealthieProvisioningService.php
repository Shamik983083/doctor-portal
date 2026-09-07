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
                user_group {
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

        $groupId = $json['data']['createGroup']['user_group']['id'] ?? null;

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
        // is_provider and professional fields are set by updateProviderDetails which
        // always runs after provisionClinician in the job.
        $existing = $this->findProviderMembership($user->email, $settings);
        if ($existing) {
            return $existing['user_id'];
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

        // phone_number is not a field on createOrganizationMembershipInput —
        // it belongs on updateUser (called in updateProviderDetails after provisioning).
        $response = $this->graphql(
            $mutation,
            ['input' => array_filter([
                'email'             => $user->email,
                'first_name'        => $firstName,
                'last_name'         => $lastName ?: null,
                'password'          => Str::random(12) . 'A1!',
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
                    $found = $this->findProviderMembership($user->email, $settings);
                    if ($found) {
                        return $found['user_id'];
                    }

                    throw new RuntimeException(
                        "Clinician [{$user->email}] is already in the Healthie org but could not be located. "
                        . "If the account is deactivated in Healthie, re-activate it at Organization → Members, then re-sync."
                    );
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
     * Two calls are made:
     *   1. updateUser       — basic identity (first_name, last_name, phone_number)
     *   2. updateOrganizationMember — professional fields (npi, qualifications/credentials,
     *                                 state_licenses) and is_provider flag
     *
     * Throws on GraphQL-level errors so the caller (provisioning job) can mark the
     * mapping as failed and surface the error through the normal retry path.
     */
    public function updateProviderDetails(Clinician $clinician, PartnerEhrSetting $settings, string $healthieUserId): void
    {
        $user                   = $clinician->user;
        [$firstName, $lastName] = $this->splitName($user->name);

        // ── Step 1: basic identity via updateUser ──────────────────────────────
        $userInput = array_filter([
            'id'           => $healthieUserId,
            'first_name'   => $firstName ?: null,
            'last_name'    => $lastName ?: null,
            'phone_number' => $clinician->phone ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $userInput['id'] = $healthieUserId;

        $userMutation = <<<'GQL'
        mutation UpdateProvider($input: updateUserInput!) {
            updateUser(input: $input) {
                user { id }
                messages { field message }
            }
        }
        GQL;

        $userResponse = $this->graphql(
            $userMutation,
            ['input' => $userInput],
            $settings->api_key,
            $settings->endpoint,
            $settings->authorization_shard
        );

        $userJson = $userResponse->json();

        if (! empty($userJson['errors'])) {
            $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $userJson['errors']));
            throw new RuntimeException(
                "Healthie provider update failed for clinician [{$clinician->id}] "
                . "partner [{$settings->partner_id}]: {$msg}"
            );
        }

        $userFieldErrors = $userJson['data']['updateUser']['messages'] ?? [];
        if (! empty($userFieldErrors)) {
            Log::warning('HealthieProvisioning: provider updateUser had field errors', [
                'clinician_id'     => $clinician->id,
                'partner_id'       => $settings->partner_id,
                'healthie_user_id' => $healthieUserId,
                'errors'           => $userFieldErrors,
            ]);
        }

        Log::info('HealthieProvisioning: provider details updated', [
            'clinician_id'     => $clinician->id,
            'partner_id'       => $settings->partner_id,
            'healthie_user_id' => $healthieUserId,
            'fields_sent'      => array_keys($userInput),
        ]);

        // ── Step 2: professional fields via updateOrganizationMember ──────────────
        // is_provider is NOT on updateOrganizationMemberInput — handled in step 3.
        $memberInput = ['id' => $healthieUserId];

        if (! empty($clinician->npi)) {
            $memberInput['npi'] = $clinician->npi;
        }

        if (! empty($clinician->credentials)) {
            $memberInput['qualifications'] = $clinician->credentials;
        }

        // Healthie appends state_licenses on each call — we must destroy all
        // existing records by ID then re-add the current set to avoid duplicates.
        $existingLicenseIds = $this->fetchExistingStateLicenseIds($healthieUserId, $settings);
        $licensedStates     = $clinician->licensed_states ?? [];

        if (! empty($existingLicenseIds) || ! empty($licensedStates)) {
            $stateLicensesInput = [];

            foreach ($existingLicenseIds as $licenseId) {
                $stateLicensesInput[] = ['id' => $licenseId, '_destroy' => true];
            }

            foreach ($licensedStates as $s) {
                $stateLicensesInput[] = ['state' => strtoupper((string) ($s['state'] ?? $s))];
            }

            $memberInput['state_licenses'] = $stateLicensesInput;
        }

        $memberMutation = <<<'GQL'
        mutation UpdateOrgMember($input: updateOrganizationMemberInput!) {
            updateOrganizationMember(input: $input) {
                user { id }
                messages { field message }
            }
        }
        GQL;

        try {
            $memberResponse = $this->graphql(
                $memberMutation,
                ['input' => $memberInput],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );

            $memberJson = $memberResponse->json();

            if (! empty($memberJson['errors'])) {
                $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $memberJson['errors']));
                Log::warning('HealthieProvisioning: updateOrganizationMember failed', [
                    'clinician_id'     => $clinician->id,
                    'partner_id'       => $settings->partner_id,
                    'healthie_user_id' => $healthieUserId,
                    'errors'           => $msg,
                ]);
            } else {
                $memberFieldErrors = $memberJson['data']['updateOrganizationMember']['messages'] ?? [];
                if (! empty($memberFieldErrors)) {
                    Log::warning('HealthieProvisioning: updateOrganizationMember had field messages', [
                        'clinician_id'     => $clinician->id,
                        'partner_id'       => $settings->partner_id,
                        'healthie_user_id' => $healthieUserId,
                        'messages'         => $memberFieldErrors,
                    ]);
                }

                Log::info('HealthieProvisioning: org member professional fields updated', [
                    'clinician_id'     => $clinician->id,
                    'partner_id'       => $settings->partner_id,
                    'healthie_user_id' => $healthieUserId,
                    'fields_sent'      => array_keys($memberInput),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: updateOrganizationMember threw', [
                'clinician_id' => $clinician->id,
                'partner_id'   => $settings->partner_id,
                'error'        => $e->getMessage(),
            ]);
        }

        // ── Step 3: is_provider flag via updateOrganizationMembership ─────────────
        // Requires the membership record ID (not user ID). Look it up first.
        $membershipId = $this->lookupMembershipIdForUser($healthieUserId, $settings);

        if (! $membershipId) {
            Log::warning('HealthieProvisioning: setIsProvider skipped — could not resolve membership ID', [
                'clinician_id'     => $clinician->id,
                'partner_id'       => $settings->partner_id,
                'healthie_user_id' => $healthieUserId,
            ]);
            return;
        }

        $membershipMutation = <<<'GQL'
        mutation SetIsProvider($input: updateOrganizationMembershipInput!) {
            updateOrganizationMembership(input: $input) {
                organizationMembership { is_provider }
                messages { field message }
            }
        }
        GQL;

        // All four flags are set in a single updateOrganizationMembership call:
        //   is_provider                      — "Should appear as a provider?" toggle
        //   auto_create_convo_for_care_team  — "Has Chat conversation automatically created with client"
        //   allow_self_scheduling_in_care_team — "Clients can schedule sessions with this org member"
        //                                        (requires is_provider=true; we set both together)
        //   notify_any_client_activity       — "Is notified of any client activity"
        $membershipInput = [
            'id'                               => $membershipId,
            'is_provider'                      => true,
            'auto_create_convo_for_care_team'  => true,
            'allow_self_scheduling_in_care_team' => true,
            'notify_any_client_activity'       => true,
        ];

        try {
            $membershipResponse = $this->graphql(
                $membershipMutation,
                ['input' => $membershipInput],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );

            $membershipJson = $membershipResponse->json();

            if (! empty($membershipJson['errors'])) {
                $msg = implode('; ', array_map(fn ($e) => $e['message'] ?? 'unknown', $membershipJson['errors']));
                Log::warning('HealthieProvisioning: setMembershipFlags failed', [
                    'clinician_id'     => $clinician->id,
                    'partner_id'       => $settings->partner_id,
                    'healthie_user_id' => $healthieUserId,
                    'membership_id'    => $membershipId,
                    'errors'           => $msg,
                ]);
            } else {
                $membershipFieldErrors = $membershipJson['data']['updateOrganizationMembership']['messages'] ?? [];
                if (! empty($membershipFieldErrors)) {
                    Log::warning('HealthieProvisioning: setMembershipFlags had field messages', [
                        'clinician_id'  => $clinician->id,
                        'partner_id'    => $settings->partner_id,
                        'membership_id' => $membershipId,
                        'messages'      => $membershipFieldErrors,
                    ]);
                }

                $isProvider = $membershipJson['data']['updateOrganizationMembership']['organizationMembership']['is_provider'] ?? null;
                Log::info('HealthieProvisioning: setMembershipFlags succeeded', [
                    'clinician_id'     => $clinician->id,
                    'partner_id'       => $settings->partner_id,
                    'healthie_user_id' => $healthieUserId,
                    'membership_id'    => $membershipId,
                    'flags_sent'       => array_keys($membershipInput),
                    'is_provider'      => $isProvider,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: setMembershipFlags threw', [
                'clinician_id' => $clinician->id,
                'partner_id'   => $settings->partner_id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Look up a provider by email within a partner's Healthie org.
     * Returns their Healthie user ID or null if not found.
     */
    private function findProviderByEmail(string $email, PartnerEhrSetting $settings): ?string
    {
        return $this->findProviderMembership($email, $settings)['user_id'] ?? null;
    }

    /**
     * Look up a provider by email and return their Healthie user ID.
     *
     * organizationMemberships (top-level) is a POINT-LOOKUP — it requires `id` or
     * `user_ids` and cannot be used to list all members. Instead we fetch the org by
     * its known ID and walk its organization_memberships relationship, filtering by
     * email client-side.
     */
    private function findProviderMembership(string $email, PartnerEhrSetting $settings): ?array
    {
        $query = <<<'GQL'
        query GetOrgMembers($id: ID!) {
            organization(id: $id) {
                organization_memberships {
                    user {
                        id
                        email
                    }
                }
            }
        }
        GQL;

        try {
            $response = $this->graphql(
                $query,
                ['id' => $settings->organization_id],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );
            $json = $response->json();

            if (! empty($json['errors'])) {
                Log::warning('HealthieProvisioning: org member lookup returned errors', [
                    'email'  => $email,
                    'errors' => $json['errors'],
                ]);
                return null;
            }

            foreach ($json['data']['organization']['organization_memberships'] ?? [] as $membership) {
                $user = $membership['user'] ?? null;
                if ($user && isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                    return ['user_id' => (string) $user['id']];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: org member lookup failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Look up the OrganizationMembership record ID for a given Healthie user.
     *
     * The top-level organizationMemberships query accepts user_ids and returns
     * each membership's own `id` — which is what updateOrganizationMembership needs.
     * Returns null when the membership cannot be found or the query fails.
     */
    private function lookupMembershipIdForUser(string $userId, PartnerEhrSetting $settings): ?string
    {
        $query = <<<'GQL'
        query GetMembershipByUser($user_ids: [ID]) {
            organizationMemberships(user_ids: $user_ids) {
                id
            }
        }
        GQL;

        try {
            $response = $this->graphql(
                $query,
                ['user_ids' => [$userId]],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );

            $json = $response->json();

            if (! empty($json['errors'])) {
                Log::warning('HealthieProvisioning: membership ID lookup returned errors', [
                    'user_id' => $userId,
                    'errors'  => $json['errors'],
                ]);
                return null;
            }

            $memberships = $json['data']['organizationMemberships'] ?? [];
            $id = $memberships[0]['id'] ?? null;
            return $id ? (string) $id : null;
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: membership ID lookup threw', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch the IDs of all existing state_license records for a Healthie org member.
     *
     * Healthie's updateOrganizationMember appends new state_license rows rather than
     * replacing them. Fetching the existing IDs lets us mark them _destroy=true in the
     * same mutation call, avoiding duplicates on every sync.
     */
    private function fetchExistingStateLicenseIds(string $userId, PartnerEhrSetting $settings): array
    {
        $query = <<<'GQL'
        query GetMemberLicenses($id: ID!) {
            organizationMember(id: $id) {
                state_licenses {
                    id
                    state
                }
            }
        }
        GQL;

        try {
            $response = $this->graphql(
                $query,
                ['id' => $userId],
                $settings->api_key,
                $settings->endpoint,
                $settings->authorization_shard
            );

            $json = $response->json();

            if (! empty($json['errors'])) {
                Log::warning('HealthieProvisioning: failed to fetch existing state licenses', [
                    'user_id' => $userId,
                    'errors'  => $json['errors'],
                ]);
                return [];
            }

            $licenses = $json['data']['organizationMember']['state_licenses'] ?? [];
            return array_column($licenses, 'id');
        } catch (\Throwable $e) {
            Log::warning('HealthieProvisioning: fetchExistingStateLicenseIds threw', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
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
