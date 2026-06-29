# Doctor Portal — Product Requirements Document

## Overview

A multi-role telehealth case management platform. An external partner system submits patient cases via API; a medical admin routes them to clinicians; clinicians review and approve; the partner fulfills the order.

---

## Roles & Authentication

| Role | Login | Entry Point |
|------|-------|-------------|
| **Admin** | Email + password | `/admin/dashboard` |
| **Clinician** | Email + password | `/clinician/dashboard` |
| **Partner (web)** | Email + password | `/partner/dashboard` |
| **Partner (API)** | OAuth2 client credentials | `POST /api/partner/auth/token` |

---

## Case Lifecycle

```
CREATED → WAITING → ASSIGNED → APPROVED → PROCESSING → COMPLETED
                  ↘                     ↗
                  SUPPORT → WAITING
(CANCELLED is reachable from any state except COMPLETED)
```

| Transition | Who Triggers |
|-----------|-------------|
| Created → Waiting | Auto (no hold) or admin release |
| Waiting → Assigned | Clinician (self-claim) or Admin (assigns specific clinician) |
| Assigned → Approved | Clinician |
| Assigned → Support | Clinician (with a note) |
| Support → Waiting | Returns to queue after support review |
| Approved → Processing | Partner (web or API) |
| Processing → Completed | Partner (API order update) |
| Any → Cancelled | Admin / Clinician / Partner |

**Key rule:** Partners only see cases where `support_at IS NOT NULL` — i.e. cases the clinician has explicitly escalated to support.

---

## Admin Flows

### Partners
- Create partner → auto-generates OAuth2 client ID + secret
- Add portal users to a partner (role: `partner`)
- Regenerate API credentials
- Suspend / reactivate partners

### Clinicians
- Create clinician (creates user, assigns role `clinician`)
- Set specialty, credentials (MD / DO / NP / PA), licensed states
- Toggle availability and max daily case load

### Cases
- View all cases across all partners/clinicians with filters (status, partner, clinician)
- Assign a clinician to any `created` or `waiting` case (auto-advances `created → waiting` if needed)
- Full case detail: intake questions, offerings, clinical notes, messages, timeline

### Patients
- Browse all patients with case counts, filter by partner/status/search
- View patient detail: demographics, all cases, orders, tags
- **Read-only** — patients are created automatically when partners submit cases via API; admin cannot create patients directly

### Offerings
- Create / edit offerings on behalf of any partner
- Fields: name, type (medication/compound/supply), price, pharmacy type, available US states, controlled-substance flag

### Questionnaires
- Create dynamic questionnaire forms with a visual question builder
- Each questionnaire has a name, description, optional partner assignment, and active/inactive status
- Questions are built inline with the questionnaire (not shared between questionnaires here — see Question Bank)
- Supported field types (14 total): Hidden, Input, Email, Textarea, Date, Select, Multi Select, Radio, Checkbox, File, Number, Height, Weight, BMI
- Per-question configuration: label, field key (auto-slugified if blank), placeholder (text types), is_required, is_readonly
- Option-based types (Select, Multi Select, Radio, Checkbox) support adding multiple options; each option has a **Disqualify** toggle — if a patient selects a disqualified option, they are excluded from eligibility
- Questions are ordered by drag-handle sort order

### Question Bank
- Standalone library of all questions across all questionnaires
- Filters: keyword search, field type, questionnaire, active/inactive status
- Inline **status toggle** (active/inactive) per question without a page reload
- **View** modal: shows full question details — label, key, type, placeholder, is_required, is_readonly, options with disqualify flags, assigned questionnaire, and date added
- **Edit** standalone form: change any question property including reassigning to a different questionnaire
- **Bulk delete** with checkbox selection
- Delete individual question

---

## Clinician Flows

### Queue
- See all `waiting` cases; claim one to self-assign (→ `assigned`)

### Assigned Case Actions
| Action | Route | Result |
|--------|-------|--------|
| Approve | `POST /clinician/cases/{uuid}/approve` | → `approved`; webhook fired |
| Escalate to Support | `POST /clinician/cases/{uuid}/support` | → `support`; `support_at` stamped; partner gains visibility |
| Cancel | `POST /clinician/cases/{uuid}/cancel` | → `cancelled`; reason logged |
| Add Clinical Note | `POST /clinician/cases/{uuid}/notes` | Note attached (general/SOAP/progress); webhook fired |
| Send Message | `POST /clinician/cases/{uuid}/messages` | Outbound portal message; webhook fired |

---

## Partner Flows

### Web Portal (read + limited actions)
- **Offerings** — full CRUD on own offerings
- **Patients** — read-only list and detail (patients are created server-side when cases are submitted)
- **Cases** — view only support-escalated cases
  - Move `approved → processing`
  - Cancel a case (with reason)
- **Credentials** — view client ID / secret / webhook list

### API (primary integration surface)

**Authentication**
```
POST /api/partner/auth/token
{ grant_type, client_id, client_secret }
→ Bearer token
```

**Submit a Case (single call — creates or finds patient + opens case)**
```
POST /api/partner/cases
{
  patient: {
    first_name, last_name, email, phone, dob,
    gender, state, external_id   ← optional dedup key
  },
  external_id,       ← case external ID
  hold_status,       ← true = stay in CREATED until released
  offerings: [{ offering_id, quantity }],
  questions:  [{ question, answer }]
}
```
- Patient lookup order: `external_id` → `email` → create new
- Case auto-advances to `waiting` unless `hold_status: true`
- **Partners cannot create patients via a separate endpoint** — patient data must be submitted inline with the case

**Other Case Endpoints**
```
GET    /api/partner/cases                   list (filter: status, patient_id)
GET    /api/partner/cases/{id}
GET    /api/partner/cases/by-external-id/{id}
POST   /api/partner/cases/{id}/cancel       { reason }
POST   /api/partner/cases/{id}/processing
POST   /api/partner/cases/{id}/hold         { hold: bool }
POST   /api/partner/cases/{id}/support      { note }
GET    /api/partner/cases/{id}/events       full audit trail
```

**Patients (read-only — no create/update/delete)**
```
GET    /api/partner/patients
GET    /api/partner/patients/{id}
GET    /api/partner/patients/by-external-id/{id}
```

**Offerings**
```
GET/POST              /api/partner/offerings
GET/PUT/DELETE        /api/partner/offerings/{id}
```

**Orders**
```
GET                   /api/partner/orders
GET/PUT               /api/partner/orders/{id}   (status, tracking, payment_status)
POST                  /api/partner/orders/{id}/cancel
```

**Webhooks**
```
GET/POST              /api/partner/webhooks
GET/PUT/DELETE        /api/partner/webhooks/{id}
POST                  /api/partner/webhooks/deliveries/{id}/resend
```

---

## Webhooks

All webhooks signed with `HMAC-SHA256`. Up to 5 retry attempts with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case submitted via API |
| `case_waiting` | Case released from hold |
| `case_support` | Clinician escalates to support |
| `case_assigned_to_clinician` | Clinician assigned |
| `case_approved` | Clinician approves |
| `case_processing` | Partner moves to processing |
| `case_completed` | Case completed |
| `case_cancelled` | Any cancellation |
| `clinical_note_added` | Clinician adds a note |
| `message_created` | Clinician sends a message |
| `order_status_changed` | Order status updated |
| `tracking_number_changed` | Tracking number set |

---

## Permissions Matrix

| Action | Admin | Clinician | Partner Web | Partner API |
|--------|:-----:|:---------:|:-----------:|:-----------:|
| Create partner / clinician | ✓ | — | — | — |
| Create offering | ✓ | — | ✓ | ✓ |
| Submit case (with inline patient) | — | — | — | ✓ |
| View all cases | ✓ | ✓ (queue) | ✓ (support-only) | ✓ (own) |
| Assign clinician | ✓ | ✓ (self) | — | — |
| Approve case | — | ✓ | — | — |
| Escalate to support | — | ✓ | — | ✓ |
| Cancel case | ✓ | ✓ | ✓ | ✓ |
| Move to processing | — | — | ✓ | ✓ |
| Add clinical note / message | — | ✓ | — | — |
| Update order / tracking | — | — | — | ✓ |
| Manage webhooks | — | — | ✓ | ✓ |
| View patients | ✓ | — | ✓ (own) | ✓ (own) |
| Create / update / delete patients | — | — | — | — |
| Manage questionnaires | ✓ | — | — | — |
| Manage question bank | ✓ | — | — | — |

---

## Data Model — Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Auth for all roles (admin, clinician, partner) |
| `partners` | Partner organisations |
| `clinicians` | Clinician profiles linked to users |
| `patients` | Patient records, scoped to partner |
| `cases` | Core case record with state machine column |
| `case_notes` | Clinical notes (general/SOAP/progress) |
| `case_messages` | Portal messages between clinician and partner |
| `case_events` | Immutable audit trail for every state change |
| `offerings` | Product/medication catalogue per partner |
| `case_offerings` | M2M: offerings attached to a case |
| `questionnaires` | Form containers (name, description, partner, is_active) |
| `questionnaire_questions` | Individual questions: type, key, placeholder, is_required, is_readonly, is_active, options (JSON) |
| `orders` | Fulfillment orders (linked to cases) |
| `webhooks` | Registered webhook endpoints per partner |
| `webhook_deliveries` | Delivery log with retry state |
| `oauth_clients` | Passport client credentials per partner |

---

## Key Business Rules

1. **Patient visibility gate** — Partners only see cases after a clinician escalates them to `support` (`support_at` is set once and never overwritten).
2. **Hold queue** — A case submitted with `hold_status: true` stays in `created` until the partner releases it.
3. **Patient idempotency** — Case submission matches an existing patient by `external_id` first, then `email`; only creates a new patient if neither matches.
4. **Patients are API-created only** — No standalone patient creation endpoint exists for partners. Patient data travels with the case payload.
5. **Cancellation is terminal** — Cancelled cases have no forward transitions.
6. **Soft deletes** — Patients, cases, offerings, and files are logically deleted (not removed from DB).
7. **Clinician scope** — Clinicians can only action cases in their queue; they cannot see other partners' unrelated data.
8. **Questionnaire disqualify** — If any option marked `is_disqualify` is selected by the patient, the submission is flagged for exclusion from eligibility consideration.
9. **Question is_active** — Inactive questions are hidden from rendered questionnaire forms but remain in the database and visible in the admin Question Bank.
