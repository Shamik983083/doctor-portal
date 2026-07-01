# Doctor Portal — Product Requirements Document

**Last Updated:** 2026-07-01
**Status:** In Development
**Stack:** Laravel 12, PHP 8.2, Bootstrap 5, MySQL (XAMPP), Laravel Passport 13.7, Spatie Permission 6.25

---

## Overview

A Telehealth & E-Prescribing Integration Platform. Healthcare partners embed a public questionnaire form on their own patient portal. When a patient submits the form, a case is automatically created and enters the clinician queue for auto-assignment. Clinicians review, approve, and prescribe medications. Admins oversee the entire system.

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
                  ↘         ↗ ↑
                  SUPPORT ──┘  (partner returns to same clinician with note)
(CANCELLED is reachable from any state except COMPLETED)
```

| Transition | Who Triggers |
|-----------|-------------|
| Created → Waiting | Auto on form submit (system) |
| Waiting → Assigned | **Auto-assigner** (priority queue) or Admin (manual assign) or Clinician (self-claim) |
| Assigned → Approved | Clinician (via Approve & Prescribe flow) |
| Assigned → Support | Clinician (with a note explaining what is needed) |
| Support → Assigned | **Partner** (writes a response note; case returns to the **same clinician**) |
| Approved → Processing | **Clinician** (Send to Pharmacy) |
| Processing → Completed | System / Admin |
| Any → Cancelled | Admin / Clinician / Partner |

**Key rules:**
- Partners only see cases where `support_at IS NOT NULL` — i.e. cases the clinician has explicitly escalated to support.
- When a partner returns a support case, it goes directly back to the **assigned clinician** (not back to the waiting queue). The `clinician_id` is preserved.
- Partners cannot move cases to Processing or Completed. Clinicians control the pharmacy handoff.

---

## How Cases Enter the System

Cases originate exclusively from the **public questionnaire form** hosted on this portal and embedded in the partner's patient-facing website via iFrame. There is no manual admin step to create a case.

### Flow

1. Partner embeds the form URL in their portal:
   ```
   GET /forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={their_patient_id}
   ```
2. Patient fills the form on the embedded page.
3. On submit:
   - All answers are saved as a `QuestionnaireResponse`
   - If **disqualified** (any answer with `is_disqualify = true`): response saved, no case created, `disqualified: true` sent via `postMessage`
   - If **qualified**: `Patient::firstOrCreate([email, partner_id])` runs, a `PatientCase` is created in `CREATED` status, then immediately transitioned to `WAITING`
4. Auto-assigner fires: the highest-priority available clinician under their max daily case load is assigned automatically
5. The form result page fires a `window.postMessage` to the parent frame:
   ```json
   {
     "event": "questionnaire_completed",
     "response_token": "abc123",
     "disqualified": false,
     "disqualified_on": null
   }
   ```

### Partner Embed Snippet

```html
<iframe
  src="https://yourdomain.com/forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={PATIENT_ID}"
  width="100%"
  height="680"
  frameborder="0"
  allow="camera">
</iframe>
```

Replace `PATIENT_ID` dynamically with the partner's internal patient ID. On the partner's side, listen for `postMessage` to detect completion.

### Patient Field Extraction from Form Answers

Questions whose `key` field matches a patient model column are automatically used to populate the patient record on submission:

| Field Key | Patient Column | Required for Case Creation? |
|---|---|---|
| `email` | `email` | **Yes** — without this no case is created |
| `first_name` | `first_name` | Recommended |
| `last_name` | `last_name` | Recommended |
| `phone` | `phone` | Optional |
| `date_of_birth` | `date_of_birth` | Optional |
| `age` | `age` | Optional |
| `height` | `height` | Optional (decimal, inches) |
| `weight` | `weight` | Optional (decimal, lbs) |
| `bmi` | `bmi` | Optional (decimal) |
| `gender` | `gender` | Optional |
| `address` | `address` | Optional |
| `address2` | `address2` | Optional |
| `city` | `city` | Optional |
| `state` | `state` | Optional (2-letter) |
| `zip` | `zip` | Optional |
| `country` | `country` | Optional (2-letter, default US) |

The questionnaire builder **auto-populates the field key** as the admin types the question label (e.g. typing "What is your Email?" auto-fills `email`). The auto-suggested key has a yellow background and is overridable.

---

## Module 1: Admin Flows

### Partners (`/admin/partners`)
- Create partner → auto-generates OAuth2 client ID + secret
- Add portal users to a partner (role: `partner`) — visible in **Portal Users** card on partner detail page
- Regenerate API credentials
- Suspend / reactivate partners

### Clinicians (`/admin/clinicians`)
- Create clinician (creates user, assigns role `clinician`)
- Set specialty, credentials (MD / DO / NP / PA), licensed states
- Toggle availability and max daily case load

### Clinician Assignment Priority (`/admin/clinicians/priority`)
- Drag-and-drop table to set the auto-assignment priority order (lower rank = assigned first)
- Editable max daily case load per clinician (inline save)
- Live capacity badge — turns red when clinician is at or over capacity
- Changes take effect immediately for new cases entering the waiting queue

### Cases (`/admin/cases`)
- List all cases across all partners/clinicians with filters (status, partner, clinician)
- **Assign** clinician to any `created` or `waiting` case
  - `created` → auto-advances to `waiting` with `skip_auto_assign: true`, then assigns directly
  - `waiting` → assigns directly
- **Reassign** clinician to an already-`assigned` case (no status change, logged as `clinician_reassigned` event)
- Case detail tabs: **Intake, Questionnaires, Prescriptions, Clinical Notes, Messages, Files, Timeline**
- Read-only Prescriptions tab: all prescriptions submitted by clinicians (diagnoses, medications, dispensing details)
- Read-only Questionnaires tab: all linked questionnaire responses with full Q&A

> **Note:** Admin cannot assign offerings to a case. Only clinicians assign offerings when creating a prescription.

### Patients (`/admin/patients`)
- Browse all patients with case counts, filter by partner/status/search
- View patient detail: demographics, all cases, QA responses
- **Read-only** — patients are created automatically when form submissions qualify

### Offerings (`/admin/offerings`)
- Full CRUD on offerings on behalf of any partner
- Toggle Active/Inactive per offering
- Delete offering (soft-delete with confirm dialog)

### Questionnaires (`/admin/questionnaires`)
- Create dynamic questionnaire forms with a visual question builder
- Supported field types (14 total): Hidden, Input, Email, Textarea, Date, Select, Multi Select, Radio, Checkbox, File, Number, Height, Weight, BMI
- Per-question configuration: label, field key (auto-populated from label keywords), placeholder, is_required, is_readonly
- Option-based types (Select, Multi Select, Radio, Checkbox) support a **Disqualify** toggle per option
- **Drag-and-drop question reordering** — grip handle on each question card; order is preserved on save via `sort_order` column
- **Share & Embed panel** on questionnaire detail page: partner selector generates the ready-to-use iFrame embed code with correct `partner_token`; includes postMessage listener snippet for the partner

### Question Bank (`/admin/questions`)
- Standalone library of all questions across all questionnaires
- Filters: keyword search, field type, questionnaire, active/inactive status
- Inline status toggle, view modal, standalone edit form, bulk delete

---

## Module 2: Clinician Flows

### Queue (`/clinician/cases/queue`)
- See all `waiting` cases; claim one to self-assign (→ `assigned`)
- Auto-assigner may have already assigned the case; clinicians can still self-claim from the queue

### Case Detail Tabs
Prescriptions, Questionnaires, Clinical Notes, Messages, Files

### Assigned Case Actions

| Action | Route | Result |
|--------|-------|--------|
| **Approve & Prescribe** | `GET /clinician/cases/{uuid}/prescribe` | Opens full-page prescription form |
| Submit Prescription | `POST /clinician/cases/{uuid}/prescribe` | Saves prescription → transitions `assigned → approved`; webhook fired |
| Escalate to Support | `POST /clinician/cases/{uuid}/support` | → `support`; `support_at` stamped; partner gains visibility |
| Cancel / Decline | `POST /clinician/cases/{uuid}/cancel` | → `cancelled`; reason logged as clinical note |
| Add Clinical Note | `POST /clinician/cases/{uuid}/notes` | Note attached (general / SOAP / progress); webhook fired |
| Send Message | `POST /clinician/cases/{uuid}/messages` | Outbound portal message; webhook fired |

---

## Module 3: Prescriptions

### Flow
When a clinician clicks **"Approve & Prescribe"** on an assigned case, they are routed to a dedicated full-page prescription form: `GET /clinician/cases/{uuid}/prescribe`

### Prescription Form Fields

| Field | Type | Required |
|-------|------|----------|
| Diagnoses | Textarea | Yes |
| Medications | Dynamic search/select | No |
| Directions | Textarea | No |
| Medical Necessity | Textarea | No |

### Medication Search
- Offerings pre-loaded as JSON; filtered client-side by name
- Category filtering: only offerings whose `category_id` matches offerings already on the case
- Selecting an offering auto-populates an editable medication card (compound formula, refills, quantity, days supply, dispense unit, days until dispense)
- Multiple medications supported; each card has an individual Remove button

### Submission (`POST /clinician/cases/{uuid}/prescribe`)
- DB transaction: creates `case_prescriptions` record + `case_prescription_medications` rows + transitions case to `approved`
- Dispatches `case_approved` webhook to partner

### Prescription Visibility
- **Clinician**: Prescriptions tab on case detail — read-only
- **Admin**: Identical read-only Prescriptions tab on admin case detail

> `case_prescriptions` / `case_prescription_medications` are separate from the DoseSpot `prescriptions` table.

---

## Module 4: Offerings

### Offering Form Fields (Admin & Partner)

| Field | Section | Notes |
|-------|---------|-------|
| Offering Name | Basic | Required |
| Internal Name | Basic | Optional |
| Type | Basic | medication / compound / supply |
| Category | Basic | Links to `OfferingCategory` |
| Partner | Basic | Admin only; required |
| Pharmacy Type | Pharmacy & Integration | boothwyn / curexa / custom |
| DoseSpot Medication ID | Pharmacy & Integration | |
| Boothwyn Compound ID | Pharmacy & Integration | |
| Compound Formula | Prescription & Dispensing | |
| Refills | Prescription & Dispensing | Integer |
| Quantity | Prescription & Dispensing | Decimal |
| Days Supply | Prescription & Dispensing | Optional |
| Dispense Unit | Prescription & Dispensing | |
| Days Until Dispense | Prescription & Dispensing | Optional |
| Directions | Prescription & Dispensing | Sent to pharmacy |
| Pharmacy Name | Prescription & Dispensing | |
| Pharmacy Notes | Prescription & Dispensing | |
| State Availability | State Availability | Multi-checkbox; empty = all states |
| Active | Flags | Toggle |
| Controlled Substance | Flags | DEA compliance flag |

---

## Module 5: Questionnaires

### Public Form Flow (Primary Case Entry Path)
See **"How Cases Enter the System"** section above for the complete flow.

### Disqualification Logic
If any selected answer has `is_disqualify = true`:
- `QuestionnaireResponse.is_disqualified = true`
- `disqualified_on` set to the question key that triggered it
- No case or patient is created
- `disqualified: true` sent in the `postMessage` event to the parent frame

### QA Visibility on Cases
- **Admin** case detail → Questionnaires tab: all linked responses, qualified/disqualified badge, all Q&A pairs, disqualifying answers highlighted
- **Clinician** case detail → Questionnaires tab: identical read-only view

### Question Builder Features
- Drag-and-drop reordering via SortableJS (grip handle on each card)
- Field key auto-population from question label (keyword detection: "email" → `email`, "first name" → `first_name`, "state" → `state`, etc.)
- Auto-suggested key shown with yellow background; admin can override manually
- 14 field types including Height, Weight, BMI (render as number inputs)

---

## Module 6: Partner Flows

### Web Portal
- **Offerings** — full CRUD on own offerings
- **Patients** — read-only list and detail
- **Cases** — view support-escalated cases only; read the clinician's support note; write a response note and return the case to the assigned clinician; cancel with reason
- **Credentials** — view client ID / secret / webhook list

### Patient Portal Integration (Hosted Form)

The primary integration path. No API credentials needed.

**Partner receives:**
1. The iFrame embed code from Admin → Questionnaires → [questionnaire] → Share & Embed
2. Their `partner_token` (partner UUID) and the `questionnaire_uuid`

**Partner embeds:**
```html
<iframe src="/forms/{uuid}?partner_token={partner_uuid}&external_id={PATIENT_ID}"
        width="100%" height="680" frameborder="0" allow="camera"></iframe>
```

**Partner listens for completion:**
```js
window.addEventListener('message', function(event) {
    if (event.data.event === 'questionnaire_completed') {
        if (event.data.disqualified) {
            // patient didn't qualify
        } else {
            // case created — show success or redirect
        }
    }
});
```

### API (Alternative Integration Path)

Used when the partner has their own intake form UI and wants to push data programmatically.

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
  patient: { first_name, last_name, email, phone, date_of_birth, gender, state, external_id },
  external_id,
  hold_status,
  patient_state,
  offerings: [{ offering_id, quantity }],
  questionnaire_responses: [{ questionnaire_id, answers: [{ question_id, answer }] }]
}
```
- Patient deduplication: `external_id` → `email` → create new
- Case auto-advances to `waiting` (and auto-assigns) unless `hold_status: true`
- Returns `409` if `external_id` already exists

**Other Case Endpoints**
```
GET    /api/partner/cases
GET    /api/partner/cases/{uuid}
GET    /api/partner/cases/by-external-id/{id}
POST   /api/partner/cases/{uuid}/cancel        { reason }
POST   /api/partner/cases/{uuid}/hold          { hold: bool }
POST   /api/partner/cases/{uuid}/support       { note }
GET    /api/partner/cases/{uuid}/events
```

> `POST /api/partner/cases/{uuid}/processing` has been removed. Processing is now initiated by the clinician via the web portal (Send to Pharmacy).

**Patients (read-only)**
```
GET    /api/partner/patients
GET    /api/partner/patients/{id}
GET    /api/partner/patients/by-external-id/{id}
```

---

## Module 7: Webhooks

All webhooks signed with HMAC-SHA256. Up to 5 retry attempts with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case auto-created from form submission |
| `case_waiting` | Case enters waiting queue |
| `case_support` | Clinician escalates to support |
| `case_assigned_to_clinician` | Clinician assigned (auto or manual) |
| `case_approved` | Clinician submits prescription |
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
| Submit case via API | — | — | — | ✓ |
| View all cases | ✓ | ✓ (queue) | ✓ (support-only) | ✓ (own) |
| Assign clinician to case | ✓ | ✓ (self) | — | — |
| Reassign clinician to assigned case | ✓ | — | — | — |
| Approve case (via prescription flow) | — | ✓ | — | — |
| Submit prescription | — | ✓ | — | — |
| Assign offerings to case | — | ✓ (via prescription) | — | — |
| View prescriptions | ✓ | ✓ | — | — |
| View questionnaire responses | ✓ | ✓ | — | — |
| Escalate to support | — | ✓ | — | ✓ |
| Return support case to clinician (with note) | — | — | ✓ | — |
| Cancel case | ✓ | ✓ | ✓ | ✓ |
| Move to processing (Send to Pharmacy) | — | ✓ | — | — |
| Add clinical note / message | — | ✓ | — | — |
| Update order / tracking | — | — | — | ✓ |
| Manage webhooks | — | — | ✓ | ✓ |
| View patients | ✓ | — | ✓ (own) | ✓ (own) |
| Manage questionnaires / question bank | ✓ | — | — | — |
| Set clinician assignment priority | ✓ | — | — | — |

---

## Data Model — Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Auth for all roles (admin, clinician, partner) |
| `partners` | Partner organisations |
| `clinicians` | Clinician profiles linked to users; specialty, credentials, licensed states, priority (for auto-assignment), max_daily_cases |
| `patients` | Patient records, scoped to partner; deduplicated by email+partner_id; fields: first_name, last_name, email, phone, date_of_birth, age, height, weight, bmi, gender, address, address2, city, state, zip, country |
| `cases` | Core case record with state-machine status column |
| `case_notes` | Clinical notes (general/SOAP/progress) |
| `case_messages` | Portal messages between clinician and partner |
| `case_events` | Immutable audit trail for every state change |
| `case_prescriptions` | Doctor-submitted prescriptions created on case approval |
| `case_prescription_medications` | Individual medications within a prescription |
| `offerings` | Product/medication catalogue per partner; includes all prescription/dispensing fields |
| `offering_categories` | Category taxonomy; used to filter the prescription medication search |
| `questionnaires` | Form containers (name, description, partner, is_active, mode) |
| `questionnaire_questions` | Questions: type, key, placeholder, is_required, is_readonly, is_active, options (JSON), sort_order |
| `questionnaire_responses` | One per form submission; `case_id` set immediately on qualified submission (never NULL after auto-case creation) |
| `questionnaire_answers` | One per Q&A pair; question_text frozen at submission time |
| `orders` | Fulfillment orders linked to cases |
| `webhooks` | Registered webhook endpoints per partner |
| `webhook_deliveries` | Delivery log with retry state |
| `oauth_clients` | Passport client credentials per partner |
| `prescriptions` | DoseSpot pharmacy prescriptions (separate system) |

---

## Key Architectural Decisions

1. **Auto-case creation on form submit**: Cases are created automatically when a patient submits a qualified public questionnaire form. There is no manual "Form Submissions → Convert to Case" step. `QuestionnaireFormController` creates the patient (via `firstOrCreate`), creates the case, then calls `CaseStateMachine::transition()` outside the DB transaction so webhooks fire cleanly after commit.

2. **Auto-assignment priority system**: `CaseAutoAssigner` service selects the highest-priority (`ORDER BY priority ASC`) clinician who is active, available, and below their `max_daily_cases` limit. Auto-assignment fires automatically when a case transitions to `waiting`. Admin can override with manual assignment at any time.

3. **`skip_auto_assign` context flag**: When admin manually assigns a clinician, `transition(waiting, skip_auto_assign: true)` is passed so the auto-assigner does not race the admin's choice and create a double-assignment conflict.

4. **Reassign without state change**: `CaseStateMachine::reassign()` bypasses the state transition graph for `assigned → assigned` (which is not a valid transition). It directly updates `clinician_id` and logs a `clinician_reassigned` event.

5. **Offerings assigned only by clinicians**: Admin cannot assign offerings to a case. Offerings are selected by the clinician when filling out the prescription form. This removes the risk of pre-attaching incorrect offerings before clinical review.

6. **`case_prescriptions` vs `prescriptions`**: Doctor-submitted prescriptions use a `case_` prefix to avoid collision with the existing DoseSpot `prescriptions` table. Both systems coexist independently.

7. **Patient field extraction via question keys**: The `key` field on each `questionnaire_question` row acts as a mapping to the patient model column. During form submission, the controller builds a `$keyedAnswers` map and uses it to populate `Patient::firstOrCreate`. Keys must match patient column names exactly.

8. **Questionnaire question sort order**: Questions are rendered in `sort_order ASC` order. The builder uses SortableJS drag-and-drop; after each drag, a `reindexCards()` JS function renames all `name="questions[idx]..."` attributes to match the new DOM order. The backend's `syncQuestions()` uses `array_values()` and assigns `sort_order = $i` from the submitted array.

9. **Patient deduplication**: `Patient::firstOrCreate([email, partner_id])` prevents duplicate records when the same patient submits multiple times.

10. **No Vite/npm**: All frontend uses Bootstrap 5 + Bootstrap Icons + SortableJS via CDN. JavaScript is vanilla, written inline in Blade views.

11. **Soft deletes**: Patients, cases, offerings, and files are logically deleted (not removed from DB).

12. **Clinician scope**: Clinicians only action cases in their queue; they cannot see unrelated partner data.
