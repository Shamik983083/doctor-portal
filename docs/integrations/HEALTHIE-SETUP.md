# Healthie integration: setup and what is left to build

Audience: the MEDAXIS dev team.
Status: **wired end to end except the GraphQL mutation document.** Disabled by default.

---

## 1. The rule that drives the whole design

**Healthie data must never cross between storefronts.** If the same person comes in through
storefront A and later through storefront B, those are two separate records, and nothing from
the first may be reused for the second.

This is not a preference. Treat any change that weakens it as a defect, however convenient.

Three things already enforce it. Please do not remove any of them without a replacement:

1. **Every company pushes with its own credential.** `partner_ehr_settings` holds one row per
   company. There is no global API key and `EhrGatewayManager::resolve()` **requires** a partner
   id, so it is not possible to obtain a real adapter without saying which company it is for.
2. **The patient key sent to Healthie is namespaced**, `{partner_uuid}:{local_id}`
   (`EhrRecordService::scopedPatientKey`). Never the bare storefront customer id: two storefronts
   can legitimately issue the same one.
3. **Three refusals.** `buildPayload()` refuses a case with no company, and refuses any case whose
   patient belongs to a different company. `HealthieEhrAdapter` refuses a payload whose company
   does not match the credential it holds.

### The rule that matters most when you write the mutation

> Match patients on the namespaced `patient.external_id` **only**.
> **Never** fall back to email, phone or date of birth.

Those are precisely the fields that are identical across storefronts for the same human. A
"helpful" fallback match is exactly how two charts silently become one, and it will look like it
is working right up until it has merged two companies' patients.

`patient.source_external_id` is included for diagnostics. It must **not** be a matching key.

If a lookup returns a record belonging to another company, that is a bug. Fail; do not write.

---

## 2. Creating a new company

The admin create-partner form now captures the Healthie values, so a new storefront is configured
at the moment it exists rather than being wired up later.

| Field | What it is | Where to get it |
|---|---|---|
| `healthie_api_key` | API key for **this company**. Encrypted at rest. | Healthie account for that company |
| `healthie_endpoint` | GraphQL endpoint. Staging and production differ. | Healthie (`hello@gethealthie.com`) |
| `healthie_authorization_shard` | Only if the account is sharded | Healthie |
| `healthie_organization_id` | The Healthie org this company's data belongs to | Healthie account |
| `healthie_default_provider_id` | Healthie user the note is attributed to | Healthie account |
| `healthie_note_form_id` | The form/object the note becomes | Depends on §4 below |

Notes:

- The settings row is **always** created, even if fields are left blank, so a half-configured
  company shows as incomplete rather than missing. The admin sees a warning naming exactly which
  values are absent.
- A new company starts **disabled**. Turning it on is deliberate, via `healthie_is_enabled` and
  `healthie_sandbox_validated` on the edit screen, after its sandbox has actually been checked.
- Editing a company **without** retyping the API key does not wipe the stored key.

---

## 3. What happens on approval today

1. Provider approves a case and their clinical note is saved (`ClinicalNote`).
2. `EhrRecordService::recordApproval()` builds the payload from the case, the patient, the note
   and the per-medication decisions.
3. **With the shipped defaults nothing is sent.** The `ehr_records` row is stored with status
   `disabled` and the full payload, so you can inspect exactly what *would* go to Healthie.
4. A `CaseEvent` (`ehr_record_built`) records that it happened, so "nothing happened" and
   "deliberately not sent" are distinguishable later.

Approving twice does not create two records: it is idempotent on case + note, backed by a unique
constraint rather than only a check.

A failure never rolls back the approval. An EHR being down must not undo a clinical decision.

---

## 4. The one piece left to build

`HealthieEhrAdapter::buildMutation()` throws. Everything around it is done: per-company credential
resolution, transport, Healthie's documented auth headers, GraphQL error handling (they return
HTTP 200 with an `errors` array, so status alone is not enough), and reference extraction.

It was left unwritten deliberately. Healthie's public guides do not publish the mutation names or
field shapes, and their schema reference needs credentials. Guessing would produce code that looks
complete, passes review by shape, and writes wrong records the day it is enabled.

**Confirm against the schema reference, then write it:**

- **Which object the note becomes.** Healthie models charting as form answer groups against a
  custom module form, and also has notes and documents. This decides the whole mutation and is a
  clinical/compliance question about what counts as the chart, not a coding preference.
- **How a client is created and matched**, honouring §1.
- **How the signing clinician maps to a Healthie user.** A note attributed to someone who is not a
  Healthie user is likely to be rejected or mis-attributed.
- **Whether `organization_id` is a field on the mutation or implied by the credential.** If it is
  implied by the credential, that is the stronger position and the field becomes a cross-check.

Auth headers, already implemented from their docs:

```
Authorization: Basic <api_key>
AuthorizationSource: API
AuthorizationShard: <shard>      # only when the account is sharded
```

### Turning it on

1. Fill the company's values, keep it disabled.
2. Point `EHR_ADAPTER=healthie` at a **staging** endpoint.
3. Set `EHR_ENABLED=true` and `EHR_SANDBOX_VALIDATED=true` (platform level).
4. Enable the one pilot company. Confirm the record lands in the right org.
5. **Verify the segregation case explicitly**: run the same person through two storefronts and
   confirm two separate Healthie records. Do not skip this. It is the failure with real
   consequences and it is invisible until it has already happened.

---

## 5. Tests

`tests/Unit/EhrTenantSegregationTest.php` covers the rules above, including the nasty case: same
email, same customer id, two storefronts, two distinct keys.

These tests are deliberately **database-free**. This repo has one model factory (`UserFactory`),
so anything relying on `Partner::factory()` could not run at all. If you add factories later,
please keep a version of these assertions that does not depend on them.

> **These tests have never been executed.** They were written on a machine with no PHP, Composer
> or MySQL. Static checks only. Run them before trusting them.
