<?php

namespace App\Services\Ehr;

use RuntimeException;

/**
 * DISABLED real-EHR adapter stub (Healthie).
 *
 * Every real-effect path throws until the operator has cleared the sandbox and
 * enabled EHR push, mirroring LifeFilePharmacyAdapter exactly.
 *
 * ============================================================================
 * NON-NEGOTIABLE: TENANT SEGREGATION. Read this before writing the mutation.
 * ============================================================================
 * Healthie is independent of this system and its data MUST NOT be crossed
 * between storefronts. If the same person arrives through storefront A and later
 * through storefront B, those are TWO records. Nothing from the first may be
 * reused for the second, in any form.
 *
 * The payload carries `company.partner_id` / `company.partner_uuid`, and
 * `patient.external_id` is ALREADY namespaced to the storefront (see
 * EhrRecordService::scopedPatientKey). Whatever this adapter does:
 *
 *   - Every read, write and search MUST be scoped by the company on the payload.
 *   - Patient matching MUST use the namespaced `patient.external_id` ONLY.
 *     NEVER fall back to matching on email, phone or date of birth. Those are
 *     precisely the fields that are identical across storefronts for the same
 *     human, so a fallback match is how the two charts silently become one.
 *     `patient.source_external_id` is included for reference and diagnostics
 *     and MUST NOT be used as a matching key on its own: two storefronts can
 *     legitimately issue the same value.
 *   - If a lookup returns a record belonging to a different company, that is a
 *     bug, not a merge candidate. Fail; do not write.
 *
 * API reference: https://docs.gethealthie.com/guides/intro/
 * ============================================================================
 *
 * WHAT IS DELIBERATELY NOT WRITTEN HERE, and why it is a stub rather than a
 * half-implementation: Healthie's API is GraphQL, and the create-record call
 * depends on decisions nobody has made yet.
 *
 *   - WHICH OBJECT the note becomes. Healthie models charting as form answer
 *     groups against a custom module form, and also has notes and documents.
 *     Which one this maps to determines the whole mutation, and it is a clinical
 *     and compliance decision about where the record has to live to count as the
 *     chart, not a coding preference.
 *   - HOW THE COMPANY MAPS ON THE VENDOR SIDE. The segregation rule above is
 *     absolute, but which Healthie construct enforces it (separate organisation,
 *     separate API credential per storefront, or a scoping field) has to be
 *     confirmed against their account structure. A per-storefront credential is
 *     the strongest option because it makes a cross-tenant read impossible
 *     rather than merely incorrect, and it is worth checking first.
 *   - WHETHER THE PROVIDER MUST EXIST IN HEALTHIE. A note signed by a clinician
 *     who is not a Healthie user is likely to be rejected or mis-attributed.
 *
 * Guessing any of these would produce code that looks finished, passes review by
 * shape, and creates wrong records the first time it is switched on. The payload
 * builder in EhrRecordService is real and complete, so when those answers exist
 * this class is the only thing that has to be written.
 */
class HealthieEhrAdapter implements EhrGatewayAdapter
{
    public function key(): string { return 'healthie'; }

    public function createRecord(array $payload): array
    {
        throw new RuntimeException(
            'Healthie record creation is not enabled. It stays disabled until the record mapping is agreed '
            . '(which Healthie object the note becomes, how a patient is matched, and how the signing '
            . 'clinician maps to a Healthie user), sandbox validation is complete, and '
            . 'EHR_SANDBOX_VALIDATED=true. Use the mock adapter for staging.'
        );
    }
}
