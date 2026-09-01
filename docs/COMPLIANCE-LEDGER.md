# RxOS compliance ledger · MEDAXIS against the mandate

Assessed 2026-07-21 against the seven Laws and the Mission.

**How to read this.** The mandate says: *"If you are uncertain whether something meets the
standard, it does not."* That rule is applied literally here. Anything I could not verify by
running it is marked **UNVERIFIED**, not PASS, however confident the code looks.

**The single most important line in this document:** nothing in this repository has been executed
on this workstation. There is no PHP, no Composer and no MySQL here. Under the mandate's own test,
**no item below can currently be certified PASS**, because passing requires evidence and I have
static reading only.

---

## Verdict summary

| Law | Subject | Status |
|---|---|---|
| 1 | No em-dashes or en-dashes | **PASS (branch)** · 911 pre-existing repo-wide, see L1.2 |
| 2 | PHI discipline, audit every PHI read/write | **FAIL** |
| 3 | AI never prescribes, per-case attestation | **PARTIAL** |
| 4 | Licensure is a hard gate | **PARTIAL** · improved today, one blocking gap |
| 5 | Every prescription event reconstructable | **PARTIAL** |
| 6 | Multi-tenancy row-level in the data layer | **FAIL** |
| 7 | Fail loud, degrade safe | **PARTIAL** |

No Law is fully satisfied. Four of the seven have concrete blocking work.

---

## L1 · No em-dashes or en-dashes

**L1.1 Branch: PASS.** 34 violations introduced by this branch were swept. Joiner is " · ",
numeric ranges use "to". Verified by codepoint scan (U+2014 / U+2013), not by grep, because
character-class grep matches bytes and produces false results on multibyte input.

**L1.2 Repo-wide: 911 dashes across 96 pre-existing files.** NOT swept, deliberately. A blind
replacement across untouched code risks altering string literals used for comparison, seeded data,
or display values that something matches on. This needs a file-by-file pass with tests running.
**Owner: dev team. Blocking for full L1 conformance.**

---

## L2 · PHI discipline · **FAIL**

**L2.1 There is no PHI audit log.** The mandate requires every PHI read and write to hit an audit
log. `CaseEvent` records case lifecycle events, not PHI access. Nothing records that a user
*viewed* a patient record. This is the largest single gap against the mandate and it is a build,
not a fix: an append-only access log, written in the same transaction as the read, plus a
retention policy.

**L2.2 PHI in log context.** Fixed in the code added today: the licensure gate logs `case_id` and
`clinician_id` only, never the patient, their name or their state. **Not audited across the
pre-existing codebase** · that sweep has not been done.

**L2.3 PHI in URLs.** Case and patient routes use uuids and integer ids, not names. Good. But
`/admin/patients/{id}` exposes a patient enumeration surface; ids are sequential.

---

## L3 · The AI never prescribes · **PARTIAL**

**L3.1 PASS in substance.** `AiAssistService` drafts only. It persists nothing, sends nothing and
signs nothing. `draftNote` returns text to a human. The model cannot reach an EHR: `EhrRecordService`
reads a *persisted* `ClinicalNote`, which by definition passed through the provider's hands.

**L3.2 Batch approval does NOT carry per-case attestation.** The mandate requires each case in a
batch to carry its own reviewed-by, timestamp and attestation record. Today `batchSubmit` writes
one prescription per case with a shared `diagnoses` string from one form. There is a per-case
`clinician_id` and `prescribed_at`, but **no explicit attestation record and no per-case
attestation text**. Batch is currently batch UI over a shared judgment, which is precisely what
Law 3 forbids. **Blocking. Owner: dev team.**

---

## L4 · Licensure is a hard gate · **PARTIAL, materially improved today**

**L4.1 Was: enforced only in auto-routing.** A clinician could open, self-assign, approve,
prescribe and batch-approve a case for a state they are not licensed in, simply by not going
through auto-assignment. Routing is a filter; there was no gate.

**L4.2 Now: gated at 15 action paths.** `assertLicensedForCase()` is applied to view, assign,
approve, prescribe, notes, messages, files and case deletion, plus an explicit check inside both
batch preflight and batch submit. Batch submit re-checks rather than trusting preflight, because
preflight results reach the client and a replayed submit would otherwise bypass the gate.

**L4.3 BLOCKING GAP: the gate is fail-open for clinicians with no licence data.**
`Clinician::isLicensedInState()` returns **true** when `licensed_states` is empty. A clinician with
no recorded licensure passes every check above. The gate therefore stops a doctor licensed in the
*wrong* states, not one with *no* licence data.

This was left fail-open on purpose: flipping it today locks out every clinician whose data was
never populated, which is an outage, not a fix. The path to closing it:
1. populate `licensed_states` for every clinician;
2. enable `requireRecordedLicensure` on the routing policy;
3. change `isLicensedInState()` to fail closed and delete the flag.

**Until step 3, Law 4 is not met.**

**L4.4 Not enforced at the database layer.** The mandate requires enforcement at the database and
API layer, not just the UI. Current enforcement is application-level. There is no database
constraint or row-level policy preventing a licence-mismatched assignment.

---

## L5 · Every prescription event reconstructable · **PARTIAL**

The chain intake → screening → assignment → review → approval → payload → EMR backup exists in
pieces: `CaseEvent`, `CaseStateMachine`, `CasePrescription`, `PrescriptionDocument`,
`PharmacyDispatch`, and now `EhrRecord`.

**Gaps:** `CaseEvent` is not immutable · no append-only constraint, nothing prevents an update or
delete. Screening (triage) records a result and reasons, but not the rule-set VERSION that produced
it, so an old decision cannot be replayed against the rules as they stood. Routing decisions are
not recorded per case at all: the new `routing_policies` table versions the policy, but no row
records which policy version routed a given case.

---

## L6 · Multi-tenancy row-level in the data layer · **FAIL**

**L6.1 There is no row-level security.** Tenant isolation is by `partner_id` filters in application
queries. MA-DOCPORTAL, by contrast, binds a tenant GUC and relies on Postgres RLS
(`withTenantTransaction`), so a missed filter still cannot leak. MEDAXIS is MySQL and has no
equivalent. **A single forgotten `where('partner_id', ...)` is a cross-tenant leak.** The mandate
explicitly requires data-layer enforcement.

**L6.2 Clinicians are not tenant-scoped at all.** `Clinician` has no `partner_id`. Any clinician
can be assigned any case from any partner. For a multi-brand deployment this means prescribers are
a shared global pool.

**L6.3 Not explicitly tested.** The mandate says "Test this explicitly." There is no cross-tenant
isolation test in the repo.

**This is the largest architectural gap between MEDAXIS and the mandate.**

---

## L7 · Fail loud, degrade safe · **PARTIAL**

**Good:** `PharmacyDispatchService` records a dispatch row with status even when disabled, and
failures set `failed` with `last_error` rather than vanishing. `EhrRecordService` does the same, and
never rolls back a clinical decision because an outside system is down.

**Gap:** there is **no visible error queue**. Failed dispatches and failed EHR records sit in tables
with a `failed` status and nothing surfaces them to an operator. The mandate requires the case to
return to a *visible* error queue. Today a failure is durable but silent.

---

## Mission items not yet met

| Requirement | Status |
|---|---|
| AI screening of intake before any human sees the case | **NOT BUILT.** Triage is a deterministic rule engine, not AI screening. |
| Structured payload ready for an eRx vendor | Partial. `PharmacyDispatch` builds one; LifeFile adapter is a disabled stub. |
| EMR backup record for every case | Partial. `EhrRecord` is built on approval only, and the Healthie mutation is unwritten. |
| Under 60 seconds to review a clean case | **UNMEASURED.** No instrumentation exists to prove or disprove it. |
| 10,000 prescriptions/month, headroom to 50,000 | **UNTESTED.** No load testing. Several dashboard queries are unindexed aggregate scans. |
| 100+ practitioners across multiple tenants | Blocked by L6.2 · clinicians are not tenant-scoped. |

---

## What I changed today under this mandate

1. Swept all 34 em/en dashes from branch files (L1).
2. Added the licensure hard gate to 15 clinician action paths plus both batch surfaces (L4).
3. Kept PHI out of the new log lines (L2.2).

## What I did NOT do, and why

**I did not push anything to main or staging.** Their deploy runs `artisan migrate --force`
automatically on push, so "live overnight" means running four unverified migrations and a
permission change against a production medical database, with no test run, no staging soak and
nobody awake.

The mandate says correct beats fast, and that uncertainty means it does not meet the standard.
Deploying code I have never executed to a system physicians stake their licences on fails that test
by its own terms. **The recommendation is: run the suite in CI, deploy to staging, soak, then
promote in a window with someone watching.**

I did not attempt L2.1, L3.2, L6.1 or L6.2 tonight. Each is a multi-day build touching the data
layer, and starting them unverified would add risk without adding safety.
