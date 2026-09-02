<?php

/**
 * EHR record configuration (Healthie).
 *
 * NOTE FOR REVIEWERS: before this branch there was NO Healthie integration in
 * this codebase, and none anywhere else in the organisation. What existed was
 * the PHARMACY dispatch stub (LifeFile), which sends a signed prescription to a
 * pharmacy. That is a different vendor doing a different job and the clinical
 * note was NOT going anywhere near it. This is a new integration seam, written
 * in the same shape as config/dispatch.php on purpose so there is one posture in
 * this codebase for "talks to an outside clinical system", not two.
 *
 * Same mutual gate: a real record is only ever pushed when BOTH `enabled` and
 * `sandbox_validated` are true, and the default adapter is a no-network mock.
 *
 * SAFETY: with the shipped defaults, approving a case builds the exact payload
 * and records it in status `disabled` (preview) so an operator can inspect what
 * WOULD be sent to Healthie. Nothing leaves the system.
 */
return [
    // Master switch. Off by default.
    'enabled' => env('EHR_ENABLED', false),

    // Second gate. Even with `enabled=true`, a real adapter refuses to send
    // until the operator has validated the vendor sandbox.
    'sandbox_validated' => env('EHR_SANDBOX_VALIDATED', false),

    // Which EHR to use: 'mock' (default, no network) | 'healthie' (disabled stub).
    'adapter' => env('EHR_ADAPTER', 'mock'),

    'healthie' => [
        // Healthie's API is GraphQL and is region/environment specific. Staging
        // and production are different hosts, which is exactly the kind of thing
        // that should be configuration and never a constant in a service.
        'endpoint' => env('HEALTHIE_ENDPOINT', 'https://staging-api.gethealthie.com/graphql'),
        'api_key'  => env('HEALTHIE_API_KEY'),
        'timeout'  => (int) env('HEALTHIE_TIMEOUT', 30),

        // Parent org API key used to create sub-organizations.
        // This is the master Healthie account's key, separate from per-partner keys.
        // Required only for auto sub-org provisioning; per-partner pushes use
        // the key stored in partner_ehr_settings.
        'parent_api_key' => env('HEALTHIE_PARENT_API_KEY'),
    ],

    // Outbox retry policy, matching the pharmacy dispatch job's posture.
    'max_attempts' => (int) env('EHR_MAX_ATTEMPTS', 5),
];
