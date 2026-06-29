# Doctor Portal — Product Requirements Document

**Last Updated:** 2026-06-29
**Status:** In Development
**Stack:** Laravel 12, PHP 8.2, Bootstrap 5, MySQL (XAMPP), Laravel Passport 13.7, Spatie Permission 6.25

---

## Overview

A Telehealth & E-Prescribing Integration Platform that allows healthcare partners to submit patient cases through an API or public questionnaire forms. Clinicians review, approve, and prescribe medications. Admins oversee the entire system.

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
| Assigned → Approved | Clinician (via Approve & Prescribe flow) |
| Assigned → Support | Clinician (with a note) |
| Support → Waiting | Returns to queue after support review |
| Approved → Processing | Partner (web or API) |
| Processing → Completed | Partner (API order update) |
| Any → Cancelled | Admin / Clinician / Partner |

**Key rule:** Partners only see cases where `support_at IS NOT NULL` — i.e. cases the clinician has explicitly escalated to support.

---

## Module 1: Admin Flows

### Partners
- Create partner → auto-generates OAuth2 client ID + secret
- Add portal users to a partner (role: `partner`)
- Regenerate API credentials
- Suspend / reactivate partners

### Clinicians
- Create clinician (creates user, assigns role `clinician`)
- Set specialty, credentials (MD / DO / NP / PA), licensed states
- Toggle availability and max daily case load

### Cases (`/admin/cases`)
- List all cases across all partners/clinicians with filters (status, partner, clinician)
- Assign a clinician to any `created` or `waiting` case (auto-advances `created → waiting` if needed)
- Case detail tabs: **Intake, Questionnaires, Prescriptions, Clinical Notes, Messages, Files, Timeline**
- Read-only view of all prescriptions submitted by clinicians (Prescriptions tab)
- Read-only view of linked questionnaire responses with Q&A details (Questionnaires tab)

### Patients (`/admin/patients`)
- Browse all patients with case counts, filter by partner/status/search
- View patient detail: demographics, all cases, QA responses
- **Read-only** — patients are created automatically when partners submit cases via API or when admin converts a form submission

### Offerings (`/admin/offerings`)
- Full CRUD on offerings on behalf of any partner
- See **Module 4: Offerings** for the full field list
- Toggle Active/Inactive per offering (inline badge click)
- Delete offering (soft-delete with confirm dialog)

### Questionnaires (`/admin/questionnaires`)
- Create dynamic questionnaire forms with a visual question builder
- Supported field types (14 total): Hidden, Input, Email, Textarea, Date, Select, Multi Select, Radio, Checkbox, File, Number, Height, Weight, BMI
- Per-question configuration: label, field key (auto-slugified if blank), placeholder, is_required, is_readonly
- Option-based types (Select, Multi Select, Radio, Checkbox) support a **Disqualify** toggle per option
- Questions ordered by drag-handle sort order

### Question Bank
- Standalone library of all questions across all questionnaires
- Filters: keyword search, field type, questionnaire, active/inactive status
- Inline status toggle per question, view modal, standalone edit form, bulk delete

### Form Submissions (`/admin/form-submissions`)
- Lists all `QuestionnaireResponse` records where `case_id IS NULL` (standalone public form submissions not yet tied to a case)
- Filters: Partner, Status (qualified / disqualified)
- Detail view: full Q&A answers; disqualifying answers highlighted; "Create Case & Assign Doctor" CTA (qualified submissions only)
- **Create Case flow** (`GET/POST /admin/form-submissions/{id}/create-case`):
  - Patient Details form (first_name, last_name, email required; phone, DOB, gender, state optional)
  - Patient fields pre-populated from questionnaire answers when a question's `key` matches a patient field name (e.g. `key='email'`)
  - Attach Offerings (checkbox list)
  - Assign Doctor (optional; can be assigned later)
  - On submit: `Patient::firstOrCreate([email, partner_id])`, creates case, attaches offerings, links `QuestionnaireResponse.case_id`, optionally transitions to `assigned`

---

## Module 2: Clinician Flows

### Queue (`/clinician/cases/queue`)
- See all `waiting` cases; claim one to self-assign (→ `assigned`)

### Case Detail Tabs
Offerings, Prescriptions, Questionnaires, Clinical Notes, Messages, Files

### Assigned Case Actions

| Action | Route | Result |
|--------|-------|--------|
| **Approve & Prescribe** | `GET /clinician/cases/{uuid}/prescribe` | Opens full-page prescription form (replaces old Approve modal) |
| Submit Prescription | `POST /clinician/cases/{uuid}/prescribe` | Saves prescription → transitions `assigned → approved`; webhook fired |
| Escalate to Support | `POST /clinician/cases/{uuid}/support` | → `support`; `support_at` stamped; partner gains visibility |
| Cancel / Decline | `POST /clinician/cases/{uuid}/cancel` | → `cancelled`; reason logged as clinical note |
| Add Clinical Note | `POST /clinician/cases/{uuid}/notes` | Note attached (general / SOAP / progress); webhook fired |
| Send Message | `POST /clinician/cases/{uuid}/messages` | Outbound portal message; webhook fired |

### Questionnaires Tab (Case Detail)
- Shows all linked `QuestionnaireResponse` records for the case
- Qualified / Disqualified badge per response
- Full Q&A listing; disqualifying answers highlighted in amber

---

## Module 3: Prescriptions (NEW)

### Flow
When a clinician clicks **"Approve & Prescribe"** on an assigned case, they are routed to a dedicated full-page prescription form:

`GET /clinician/cases/{uuid}/prescribe`

### Prescription Form Fields

| Field | Type | Required |
|-------|------|----------|
| Diagnoses | Textarea | Yes |
| Medications | Dynamic search/select | No |
| Directions | Textarea | No |
| Medical Necessity | Textarea | No |

### Medication Search
- Offerings are pre-loaded as JSON in the Blade view; filtered client-side by name or internal name via vanilla JS
- **Category filtering**: Only offerings whose `category_id` matches the categories of offerings already attached to the case are shown (e.g. a weight-loss case shows only weight-loss offerings). Falls back to all active offerings when no categories are set.
- Selecting an offering auto-populates an editable medication card:
  - Compound Formula
  - Refills
  - Quantity
  - Days Supply (optional)
  - Dispense Unit
  - Days Until Dispense (optional)
- Multiple medications can be added; each card has an individual Remove button
- All dispensing fields remain editable before final submission

### Submission (`POST /clinician/cases/{uuid}/prescribe`)
- Validates: `diagnoses` required; medication fields validated only if `medications` array is present
- DB transaction:
  1. Creates `case_prescriptions` record
  2. Creates `case_prescription_medications` rows
  3. Calls `CaseStateMachine::approve()` — atomically transitions case to `approved`
- Dispatches `case_approved` webhook to partner
- Redirects to case detail page with success flash message

### Prescription Visibility
- **Clinician**: Prescriptions tab on case detail — read-only list of all prescriptions on the case
- **Admin**: Identical read-only Prescriptions tab on admin case detail

> Note: `case_prescriptions` and `case_prescription_medications` are separate from the existing `prescriptions` table used for DoseSpot pharmacy integration.

### Database Tables

**`case_prescriptions`**
`id, case_id, clinician_id, diagnoses, directions, medical_necessity, prescribed_at, timestamps`

**`case_prescription_medications`**
`id, case_prescription_id, offering_id (nullable FK), name, compound_formula, refills, quantity, days_supply, dispense_unit, days_until_dispense`
(no timestamps)

---

## Module 4: Offerings

### Overview
Offerings are medications, compounds, or supplies that partners make available. Admin creates offerings on behalf of any partner; partners manage their own via the partner portal.

### Offering Form Fields (Admin & Partner)

| Field | Section | Notes |
|-------|---------|-------|
| Offering Name | Basic | Required |
| Internal Name | Basic | Optional internal label |
| Type | Basic | medication / compound / supply |
| Category | Basic | Links to `OfferingCategory` |
| Partner | Basic | Admin only; required |
| Pharmacy Type | Pharmacy & Integration | boothwyn / curexa / custom |
| DoseSpot Medication ID | Pharmacy & Integration | |
| Boothwyn Compound ID | Pharmacy & Integration | |
| Compound Formula | Prescription & Dispensing | e.g. "NAD+ liquid – Olympia – 100mg/ml 10ml Vial" |
| Refills | Prescription & Dispensing | Integer |
| Quantity | Prescription & Dispensing | Decimal |
| Days Supply | Prescription & Dispensing | Optional |
| Dispense Unit | Prescription & Dispensing | e.g. "Each", "Vial", "mL" |
| Days Until Dispense | Prescription & Dispensing | Optional |
| Directions | Prescription & Dispensing | Sent to pharmacy; included in medication label |
| Pharmacy Name | Prescription & Dispensing | e.g. "THE PHARMACY HUB LLC (271328)" |
| Pharmacy Notes | Prescription & Dispensing | e.g. "Bill to partner, Ship to Patient" |
| State Availability | State Availability | Multi-checkbox; empty = all states |
| Active | Flags | Toggle |
| Controlled Substance | Flags | DEA compliance flag |

> **Removed from form**: Price, SKU, Description, Required Questionnaires — all removed; price/sku columns remain in DB but are not surfaced in the UI.

### Offerings List (Admin & Partner)

**Columns:** Name, Internal Name, Type, Category, States, Active (clickable toggle badge), Actions (Edit, Delete)

**Filters:** Search by name, Partner (admin only), Type, Active/Inactive; "Clear" link when filters are active

**Row actions:**
- Edit → offering detail/edit page
- Delete (with JS confirm dialog) → soft-delete
- Active badge → click to toggle in-place via `PATCH /admin/offerings/{id}/toggle-status` or `PATCH /partner/offerings/{id}/toggle-status`

---

## Module 5: Questionnaires & Form Submissions

### Two Binding Paths for QA → Cases

**Path 1: API submission** (`POST /api/partner/cases`)
- Partner creates a case via API; `QuestionnaireResponse.case_id` is set immediately
- QA is directly bound to the case from creation

**Path 2: Public form submission** (standalone)
- Patient fills `GET /forms/{uuid}`; `case_id = NULL`, only `partner_id` and `external_patient_id` are set
- Appears in admin **Form Submissions** list
- Admin manually converts to a case via the Create Case flow

### Disqualification Logic
If any selected answer has `is_disqualify = true`, `QuestionnaireResponse.is_disqualified` is set to `true`. Admin sees a "Disqualified" badge; the offending answers are highlighted. The "Create Case" CTA is hidden for disqualified submissions.

### QA Visibility on Cases
- **Admin** case detail → Questionnaires tab: all linked responses, qualified/disqualified badge, all Q&A pairs, disqualifying answers highlighted
- **Clinician** case detail → Questionnaires tab: identical read-only view

---

## Module 6: Partner Flows

### Web Portal (read + limited actions)
- **Offerings** — full CRUD on own offerings (see Module 4)
- **Patients** — read-only list and detail
- **Cases** — view support-escalated cases; move `approved → processing`; cancel with reason
- **Credentials** — view client ID / secret / webhook list

### API (primary integration surface)

**Authentication**
```
POST /api/partner/auth/token
{ grant_type, client_id, client_secret }
→ Bearer token
```

**Submit a Case**
```
POST /api/partner/cases
{
  patient: { first_name, last_name, email, phone, dob, gender, state, external_id },
  external_id,
  hold_status,
  offerings: [{ offering_id, quantity }],
  questions:  [{ question, answer }]
}
```
- Patient lookup: `external_id` → `email` → create new
- Case auto-advances to `waiting` unless `hold_status: true`

**Other Case Endpoints**
```
GET    /api/partner/cases
GET    /api/partner/cases/{id}
GET    /api/partner/cases/by-external-id/{id}
POST   /api/partner/cases/{id}/cancel       { reason }
POST   /api/partner/cases/{id}/processing
POST   /api/partner/cases/{id}/hold         { hold: bool }
POST   /api/partner/cases/{id}/support      { note }
GET    /api/partner/cases/{id}/events
```

**Patients (read-only)**
```
GET    /api/partner/patients
GET    /api/partner/patients/{id}
GET    /api/partner/patients/by-external-id/{id}
```

**Offerings**
```
GET/POST           /api/partner/offerings
GET/PUT/DELETE     /api/partner/offerings/{id}
```

**Orders**
```
GET                /api/partner/orders
GET/PUT            /api/partner/orders/{id}
POST               /api/partner/orders/{id}/cancel
```

**Webhooks**
```
GET/POST           /api/partner/webhooks
GET/PUT/DELETE     /api/partner/webhooks/{id}
POST               /api/partner/webhooks/deliveries/{id}/resend
```

---

## Module 7: Webhooks

All webhooks signed with HMAC-SHA256. Up to 5 retry attempts with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case submitted via API |
| `case_waiting` | Case released from hold |
| `case_support` | Clinician escalates to support |
| `case_assigned_to_clinician` | Clinician assigned |
| `case_approved` | Clinician submits prescription and case is approved |
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
| Create / edit offering | ✓ | — | ✓ | ✓ |
| Delete / toggle offering active | ✓ | — | ✓ | — |
| Submit case (with inline patient) | — | — | — | ✓ |
| Convert form submission to case | ✓ | — | — | — |
| View all cases | ✓ | ✓ (queue) | ✓ (support-only) | ✓ (own) |
| Assign clinician | ✓ | ✓ (self) | — | — |
| Approve case (via prescription flow) | — | ✓ | — | — |
| Submit prescription | — | ✓ | — | — |
| View prescriptions | ✓ | ✓ | — | — |
| View questionnaire responses on case | ✓ | ✓ | — | — |
| Escalate to support | — | ✓ | — | ✓ |
| Cancel case | ✓ | ✓ | ✓ | ✓ |
| Move to processing | — | — | ✓ | ✓ |
| Add clinical note / message | — | ✓ | — | — |
| Update order / tracking | — | — | — | ✓ |
| Manage webhooks | — | — | ✓ | ✓ |
| View patients | ✓ | — | ✓ (own) | ✓ (own) |
| Manage questionnaires / question bank | ✓ | — | — | — |

---

## Data Model — Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Auth for all roles (admin, clinician, partner) |
| `partners` | Partner organisations |
| `clinicians` | Clinician profiles linked to users; specialty, credentials, licensed states |
| `patients` | Patient records, scoped to partner; deduplicated by email+partner_id |
| `cases` | Core case record with state-machine status column |
| `case_notes` | Clinical notes (general/SOAP/progress); `is_private` flag |
| `case_messages` | Portal messages between clinician and partner |
| `case_events` | Immutable audit trail for every state change |
| `case_offerings` | M2M: offerings attached to a case |
| `case_prescriptions` | Doctor-submitted prescriptions created on case approval (NEW) |
| `case_prescription_medications` | Individual medications within a prescription (NEW) |
| `offerings` | Product/medication catalogue per partner; includes all prescription/dispensing fields (UPDATED) |
| `offering_categories` | Category taxonomy; used to filter the prescription medication search |
| `questionnaires` | Form containers (name, description, partner, is_active) |
| `questionnaire_questions` | Questions: type, key, placeholder, is_required, is_readonly, is_active, options (JSON) |
| `questionnaire_responses` | One per form submission; `case_id` nullable (NULL = standalone submission) |
| `questionnaire_answers` | One per Q&A pair; question_text frozen at submission time |
| `orders` | Fulfillment orders linked to cases |
| `webhooks` | Registered webhook endpoints per partner |
| `webhook_deliveries` | Delivery log with retry state |
| `oauth_clients` | Passport client credentials per partner |
| `prescriptions` | DoseSpot pharmacy prescriptions (separate system — unrelated to `case_prescriptions`) |

---

## Key Architectural Decisions

1. **`case_prescriptions` vs `prescriptions`**: Doctor-submitted prescriptions use a `case_` prefix to avoid collision with the existing `prescriptions` table used by DoseSpot pharmacy integration. Both systems coexist without touching each other.

2. **Approve & Prescribe replaces Approve modal**: The old "Approve" action was a Bootstrap modal on the case detail page. It has been replaced by a dedicated full-page prescription form. Case approval only occurs when the prescription is submitted, inside a DB transaction that atomically saves the prescription and transitions the case status.

3. **Medication category filtering**: The prescription form's medication search filters offerings by the `category_id` values of offerings already attached to the case — not by questionnaire answers directly. This is a practical proxy for "weight-loss case → weight-loss medications." Falls back to all active offerings when no categories are set.

4. **Standalone form submissions**: When a patient submits a public questionnaire form without a partner-API-created case, `case_id = NULL`. Admin manually converts these through the Form Submissions module at `/admin/form-submissions`.

5. **Patient deduplication**: `Patient::firstOrCreate([email, partner_id])` prevents duplicate patient records when converting form submissions to cases or when the same patient submits multiple times.

6. **Price/SKU/Description removed from offering forms**: These fields exist in the DB but are not surfaced in the current UI. The offering form focuses entirely on clinical/dispensing information relevant to prescribing.

7. **No Vite/npm**: All frontend uses Bootstrap 5 + Bootstrap Icons via CDN. JavaScript is vanilla, written inline in Blade views. The prescription medication search uses a pre-loaded JSON constant (`const OFFERINGS = @json(...)`) with client-side filtering and dynamic DOM card generation.

8. **Questionnaire disqualify**: If any option marked `is_disqualify` is selected, `QuestionnaireResponse.is_disqualified = true`. Clinicians and admins see this flag and the offending answers highlighted on the case detail Questionnaires tab.

9. **Soft deletes**: Patients, cases, offerings, and files are logically deleted (not removed from DB).

10. **Clinician scope**: Clinicians only action cases in their queue; they cannot see unrelated partner data.
