# OpenLoop Parity — Benchmark & Phased Roadmap (MEDAXIS‑DOCTOR)

**Target platform:** MEDAXIS‑DOCTOR (Laravel 12 / MySQL / Bootstrap).
**North star:** [OpenLoop](https://openloophealth.com) — white‑label telehealth
enablement: a shared clinician network + async/sync visits + e‑prescribing +
labs + pharmacy fulfillment, sold to many partner brands from one platform.
**Scope requested:** full parity across four dimensions — (1) feature/capability,
(2) white‑label multi‑tenant, (3) clinician network + routing, (4)
pharmacy/fulfillment.

This document is the **map**: an honest current‑state benchmark, then a phased,
contract‑first roadmap. It is planning only — no behavior changes. Each roadmap
item is scoped the way the rest of this repo's backlog is (schema → endpoints →
validation → RBAC → audit → tests → exit criteria) so it can be lifted into
`docs/` backlog entries and worked one branch at a time.

Legend: ✅ present · ⚠️ partial · ❌ absent.

---

## 0a. Reference implementation — build from MA‑DOCPORTAL, don't reinvent

**MA‑DOCPORTAL ("MA") is the reference build for both look/feel and
functionality.** Its design system, role views, and feature modules are the
template; OpenLoop is the capability target. Wherever a roadmap item below
already exists in MA, we **port it** into MEDAXIS (adapt the React/Prisma
implementation to Blade/MySQL) rather than design something new — the visual
language, workflow, and data shapes should match MA.

MEDAXIS already carries MA's design system in the `/ma-portal` area (the
`.ma-surface` system + practitioner/admin/super‑admin role views, on branch
`feature/ma-portal-design-and-triage`), and the immutable signed Rx document +
pharmacy dispatch outbox (branch `feature/ma-prescription-dispatch-core`). Those
are the first two paved roads; the roadmap extends them.

**MA → MEDAXIS port map:**

| MA module (reference) | MEDAXIS target | Roadmap item |
|---|---|---|
| `.ma-surface` design system + role views | Adopt as the standard MEDAXIS shell (beyond `/ma-portal`) | O0 (foundation) |
| Consent capture + e‑sign | `consents` + gating | O1.1 / O1.3 |
| Patient external identity / verification | identity verification | O1.2 |
| Tenant / TenantMembership isolation | global partner scope + tests | O2.1 |
| Per‑tenant config + feature flags | per‑partner config | O2.3 |
| `ProviderLicense` (encrypted) + credentialing | `provider_credentials` | O3.1 |
| Provider availability / scheduling | availability + appointments | O3.2 |
| `SynchronousVideoVisitRecord` | sync video visit | O4.1 |
| `PrescriptionOrder` / snapshot / dispatch | signed Rx + dispatch (shipped) | O4.2 / O5 |
| `RoutingPolicy` / `RoutingDispatchDecision` | pharmacy routing rules | O5.1 |
| `FulfillmentStatusEvent` | inbound fulfillment status | O5.2 |
| `ChartNote` / `ChartNoteRevision` | structured charting | O4 (charting) |
| `AuditEvent` immutable trail | `CaseEvent` (already aligned) | cross‑cutting |

---

## 0. What MEDAXIS is today (accurate inventory)

- **Roles:** admin, clinician, partner, patient (Spatie Permission).
- **Case workflow:** 8‑state machine (created → waiting → assigned → approved →
  processing → completed, plus support/cancelled) with immutable `CaseEvent`
  audit and partner **webhook outbox** (retry/backoff on `WebhookDelivery`).
- **Triage:** Green/Yellow/Red classifier + workflow holds (recent slice).
- **Routing:** auto‑assign by `Clinician.licensed_states` + `max_daily_cases` +
  `priority`.
- **Clinical:** dynamic questionnaires, offerings/categories catalog, patient
  files (virus‑scanned uploads), clinical notes, in‑portal messaging.
- **Prescribing:** `CasePrescription` + meds; **just added** (separate branch)
  an immutable signed Rx document + feature‑flagged mock pharmacy dispatch
  outbox.
- **Partner integration:** Passport‑token partner API (patients, cases,
  offerings, orders, webhooks) + per‑partner webhook secrets.
- **Provider fields:** `npi`, `license_number`, `license_state`,
  `licensed_states[]`.

Solid transactional spine. The gaps below are the difference between "a doctor
network case tool" and "an OpenLoop‑class telehealth platform."

---

## 1. Dimension 1 — Feature / capability parity

| Capability | OpenLoop | MEDAXIS | Gap |
|---|---|---|---|
| Async (store‑and‑forward) visits | ✅ | ✅ | — |
| **Synchronous video visits** (scheduling, room, recording, consent) | ✅ | ⚠️ `visit_type` field only | **Major** |
| Phone visits | ✅ | ❌ | Medium |
| Dynamic patient intake / questionnaires | ✅ | ✅ | — |
| **Certified e‑prescribing** (Surescripts / DoseSpot network) | ✅ | ⚠️ mock pharmacy dispatch only | **Major** |
| **EPCS** (controlled substances, 2‑factor + DEA) | ✅ | ❌ | **Major (regulatory)** |
| **PDMP** check integration | ✅ | ❌ | **Major (regulatory)** |
| **Lab ordering + results** (Labcorp / Quest) | ✅ | ❌ | **Major** |
| Care messaging / async follow‑ups | ✅ | ✅ | — |
| Charting / clinical notes | ✅ | ⚠️ freeform notes, no structured SOAP/coded | Medium |
| **Consent capture** (telehealth, treatment, e‑sign) | ✅ | ⚠️ file‑type only, no Consent model | **Major (regulatory)** |
| **Patient identity verification** (KYC / ID) | ✅ | ❌ | **Major (regulatory)** |
| Refills / renewals / care plans | ✅ | ⚠️ refills field, no lifecycle | Medium |

**Priority order:** consent + identity (regulatory substrate) → certified e‑Rx →
sync video → labs → EPCS/PDMP (needed before any controlled‑substance use).

---

## 2. Dimension 2 — White‑label / multi‑tenant

| Capability | OpenLoop | MEDAXIS | Gap |
|---|---|---|---|
| Multi‑partner data model | ✅ | ✅ `partner_id` scoping | — |
| **Tenant isolation guarantee** | ✅ (defense‑in‑depth) | ⚠️ app‑level `where partner_id` only, no DB enforcement | **Major** |
| Per‑tenant **branding / theming** (logo, colors, copy) | ✅ | ❌ | **Major** |
| **Custom domains** per partner | ✅ | ❌ | Medium |
| **Embeddable intake / SDK / widget** | ✅ | ❌ | Medium |
| Per‑tenant configuration (offerings, allowed states, workflow) | ✅ | ⚠️ offerings scoped; states/workflow global | Medium |
| API‑first partner integration | ✅ | ✅ Passport partner API | — |
| Per‑tenant feature flags | ✅ | ⚠️ global `Setting` only | Medium |

**Priority order:** tenant‑isolation hardening (global partner scope + tests) →
per‑tenant branding config → per‑tenant feature flags → custom domains/embeds.

---

## 3. Dimension 3 — Clinician network + routing

| Capability | OpenLoop | MEDAXIS | Gap |
|---|---|---|---|
| Provider roster | ✅ | ✅ `Clinician` | — |
| State licensure matching | ✅ | ✅ `licensed_states[]` | — |
| Capacity / load routing | ✅ | ✅ `max_daily_cases` + `priority` | — |
| **Credentialing / PSV** (primary‑source verification, malpractice, DEA, expirables) | ✅ | ❌ | **Major (regulatory)** |
| **License expiration tracking + auto‑suspend** | ✅ | ❌ | **Major** |
| Provider **onboarding** workflow | ✅ | ❌ | Medium |
| Provider **availability / scheduling** (for sync visits) | ✅ | ❌ | **Major (blocks sync video)** |
| Multi‑state coverage dashboard | ✅ | ⚠️ implied by roster | Low |
| 1099/W2 classification + **payouts / earnings** | ✅ | ❌ | Medium |
| Provider performance / SLA metrics | ✅ | ⚠️ basic queue counts | Low |

**Priority order:** credentialing + license‑expiry (gates who can practice) →
availability/scheduling (unblocks sync) → onboarding → payouts.

---

## 4. Dimension 4 — Pharmacy / fulfillment

| Capability | OpenLoop | MEDAXIS | Gap |
|---|---|---|---|
| Order → pharmacy dispatch | ✅ | ✅ (new, feature‑flagged mock) | — |
| **Pharmacy network + routing** (choose pharmacy by drug/state/partner) | ✅ | ⚠️ `pharmacy_id` nullable, no routing rules | **Major** |
| Compounding pharmacy support | ✅ | ⚠️ `compound_formula` on meds | Medium |
| **Fulfillment status tracking** (accepted → filled → shipped → delivered) | ✅ | ❌ inbound status events not modeled | **Major** |
| **Shipment / tracking** (carrier, tracking #) | ✅ | ❌ | Medium |
| Refill management / auto‑refill | ✅ | ❌ | Medium |
| Formulary / inventory | ✅ | ⚠️ offerings catalog | Low |
| Real gateway adapter (LifeFile / partner Rx API) | ✅ | ⚠️ disabled stub until sandbox | Planned |

**Priority order:** pharmacy routing rules → inbound fulfillment status events
(close the loop) → shipment tracking → refills → real adapter certification.

---

## 5. Phased roadmap (contract‑first, one branch per item)

Sequenced so regulatory substrate lands before the clinical features that
legally depend on it, and provider/tenant plumbing lands before the features
that consume it.

### Phase O0 — Adopt MA's design system as the MEDAXIS standard (foundation)
The look/feel we're standardizing on. Promote the `.ma-surface` design system
and role‑view layout from `/ma-portal` to the base MEDAXIS shell so every
subsequent feature is built in MA's visual language.

- **O0.1 Design‑system base layer.** Extract `.ma-surface` (tokens, cards,
  pills, tables, buttons) into a shared layout/partial set usable by all
  authenticated portals, not just `/ma-portal`.
- **O0.2 Role‑view parity shells.** Align the clinician/admin/partner shells
  with MA's practitioner/admin/super‑admin structure (nav, headers, grid
  patterns) so new modules drop into a consistent frame.

### Phase O1 — Regulatory substrate (must land first)
Mirrors MA's production‑readiness P0s. Nothing controlled‑substance or
identity‑bearing ships without these.

- **O1.1 Consent model & capture.** `consents` table (patient_id, partner_id,
  type: telehealth|treatment|privacy|controlled_substance, version, text_hash,
  granted_at, ip, user_agent, revoked_at). Block case progression past intake
  until required consents for the visit type are present. Audit
  `consent.granted` / `consent.revoked`.
- **O1.2 Patient identity verification.** `patient_identity_verifications`
  (method, status, verified_at, evidence_ref). Gate prescribing on a verified
  identity when partner policy requires it. Vendor adapter behind a flag
  (mock default) — same posture as the pharmacy adapter.
- **O1.3 E‑signature substrate.** Signed, hashed, versioned attestations reused
  by consent, Rx (already hashed via the dispatch branch), and provider
  attestations.

### Phase O2 — Tenant hardening & white‑label
- **O2.1 Global tenant scope + isolation tests.** A global Eloquent scope /
  middleware enforcing `partner_id` on every partner‑owned model, with a test
  suite proving cross‑tenant reads/writes fail. (MySQL analog of MA's Postgres
  FORCE‑RLS.)
- **O2.2 Per‑tenant branding.** `partner_branding` (logo, palette, product
  name, support contact, legal footer) surfaced through a theming layer on the
  patient‑facing intake + emails.
- **O2.3 Per‑tenant feature flags & config.** Move visit‑type availability,
  allowed states, and workflow toggles from global `Setting` to per‑partner
  config.

### Phase O3 — Clinician network depth
- **O3.1 Credentialing & PSV.** `provider_credentials` (type: state_license |
  dea | malpractice | board_cert, number_encrypted, state, issued_at,
  expires_at, verification_status, verified_at). License‑expiry job that
  auto‑suspends a provider from routing when a required credential lapses.
  Encrypt sensitive numbers (mirror MA's `licenseNumberEncrypted`).
- **O3.2 Provider availability & scheduling.** `provider_availability` +
  `appointments` for synchronous visits (slots, timezone, booking, holds).
- **O3.3 Provider onboarding workflow** (application → credentialing →
  activation) and basic earnings/payout ledger.

### Phase O4 — Clinical feature parity
- **O4.1 Synchronous video visit.** Room provider adapter (flagged), scheduled
  appointment, consent‑gated join, immutable visit record with true clinical
  timestamps (only external delivery schedulable — matches the existing rule).
- **O4.2 Certified e‑prescribing.** Promote the pharmacy dispatch to a certified
  network adapter (DoseSpot/Surescripts) behind the existing two‑flag gate;
  keep mock as staging.
- **O4.3 EPCS + PDMP.** Two‑factor provider auth for controlled substances +
  PDMP query before controlled Rx. Hard‑blocks controlled prescribing until both
  are live.
- **O4.4 Lab ordering & results.** `lab_orders` + `lab_results` with a
  Labcorp/Quest adapter (flagged), results attached to the case.

### Phase O5 — Fulfillment loop
- **O5.1 Pharmacy routing rules.** Choose the pharmacy by drug class / patient
  state / partner contract; `pharmacy_routing_rules`.
- **O5.2 Inbound fulfillment status.** Model `fulfillment_status_events`
  (accepted → filled → shipped → delivered) ingested from the gateway webhook;
  reflect on the case + partner webhook out.
- **O5.3 Shipment tracking + refills.** Carrier/tracking capture and a refill
  lifecycle (remaining refills, renewal request → provider re‑approval →
  re‑dispatch reusing the signed‑document pipeline).

---

## 6. Sequencing rationale (one‑screen summary)

```
O1 substrate ─▶ O2 tenant/white-label ─▶ O3 provider network ─▶ O4 clinical ─▶ O5 fulfillment
(consent,        (isolation, branding,     (credentialing,        (video, e-Rx,   (routing,
 identity,        per-tenant config)        availability)          EPCS, labs)     status, refills)
 e-sign)
        │                                          │                    │
        └── gates identity-bearing features        └── unblocks sync    └── e-Rx builds on the
            (e-Rx, controlled subs)                    video (O4.1)          dispatch branch already shipped
```

**Already shipped toward this** (separate branch
`feature/ma-prescription-dispatch-core`): the immutable signed Rx document +
feature‑flagged pharmacy dispatch outbox + audit — the foundation O4.2 / O5
build on. See `docs/MA-ARCHITECTURE-NOTE.md`.

---

## 7. How to consume this

Each `O#.#` item becomes a backlog entry with: MySQL schema delta, endpoint(s),
Form‑Request validation, RBAC action, `CaseEvent` audit code(s), test‑first
cases, and exit criteria — then one branch → PR → review → merge, same
discipline as the rest of the repo. Every item is built in MA's visual language
and, where MA already implements it, ported from MA rather than redesigned.

**Recommended start:** **O0.1** (promote MA's design system to the MEDAXIS
standard) — it's the "look/feel" foundation everything else sits on and is the
most direct expression of "use what we built in MA." Then **O1.1 (Consent)** as
the first feature, since it blocks the most downstream regulated work.
