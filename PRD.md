# Doctor Portal — Product Requirements Document

**Last Updated:** 2026-07-09 (rev 4)
**Status:** In Development
**Stack:** Laravel 12, PHP 8.2, Bootstrap 5, MySQL (XAMPP), Laravel Passport 13.7, Spatie Permission 6.25

---

## Overview

A Telehealth & E-Prescribing Integration Platform. Healthcare partners can submit patient cases either by embedding a public questionnaire form on their patient portal (hosted form path) or by calling the Partner REST API directly (API path). Cases enter a clinician queue for auto-assignment. Clinicians review, approve, and prescribe medications. Admins oversee the entire system.

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
| Created → Waiting | Auto on form submit or API case creation (system) |
| Waiting → Assigned | **Auto-assigner** (priority queue) or Admin (manual assign) or Clinician (self-claim) |
| Assigned → Approved | Clinician (via Approve & Prescribe flow) |
| Assigned → Support | Clinician (with a note explaining what is needed) |
| Support → Assigned | **Partner** (writes a response note; case returns to the **same clinician**) |
| Approved → Processing | **Clinician** (Send to Pharmacy — triggers both `processing` and `completed` in sequence) |
| Processing → Completed | **Auto** — fires immediately after `processing` when Clinician clicks "Send to Pharmacy" |
| Any → Cancelled | Admin / Clinician / Partner |

> **Auto-completion on Send to Pharmacy**: When a clinician clicks "Send to Pharmacy", `CaseStateMachine::startProcessing()` and `CaseStateMachine::complete()` are called in sequence. Both the `case_processing` and `case_completed` webhooks fire automatically. The clinician is redirected to the final `completed` case view.

**Key rules:**
- Partners only see cases where `support_at IS NOT NULL` — i.e. cases the clinician has explicitly escalated to support.
- When a partner returns a support case, it goes directly back to the **assigned clinician** (not back to the waiting queue). The `clinician_id` is preserved.
- Partners cannot move cases to Processing or Completed. Clinicians control the pharmacy handoff.

---

## How Cases Enter the System

Two paths exist. Both ultimately create a `PatientCase` in the database.

### Path A — Hosted Form (iFrame Embed)

1. Partner embeds the form URL in their portal:
   ```
   GET /forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={their_patient_id}
   ```
2. Patient fills the form on the embedded page.
3. On submit:
   - All answers saved as a `QuestionnaireResponse`
   - If **disqualified** (any answer with `is_disqualify = true`): response saved, no case created, `disqualified: true` sent via `postMessage`
   - If **qualified**: `Patient::firstOrCreate([email, partner_id])` runs, a `PatientCase` is created in `CREATED` status, then immediately transitioned to `WAITING`
4. Auto-assigner fires: highest-priority available clinician under their max daily case load is assigned automatically
5. The form fires a `window.postMessage` to the parent frame:
   ```json
   {
     "event": "questionnaire_completed",
     "response_token": "abc123",
     "disqualified": false,
     "disqualified_on": null
   }
   ```

**Partner Embed Snippet:**
```html
<iframe
  src="https://yourdomain.com/forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={PATIENT_ID}"
  width="100%"
  height="680"
  frameborder="0"
  allow="camera">
</iframe>
```

### Path B — Partner REST API

The partner submits patient data and questionnaire answers programmatically. A single `POST /api/partner/cases` call atomically creates the patient, case, and all questionnaire answers. See **Module 6: Partner API** for the full payload format.

For MWL Weight Loss cases, **two questionnaires must be submitted** in a single call:
1. **Standard Intake 1** — shared baseline health intake (all programs)
2. **MWL – Weight Loss** — program-specific questions (GLP-1 history, medical conditions, prescription image)

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

The questionnaire builder **auto-populates the field key** as the admin types the question label. The auto-suggested key has a yellow background and is overridable.

---

## Module 1: Admin Flows

### Partners (`/admin/partners`)
- Create partner → auto-generates OAuth2 client ID + secret (Passport 13 client_credentials grant)
- Add portal users to a partner (role: `partner`)
- Regenerate API credentials
- Suspend / reactivate partners

### Clinicians (`/admin/clinicians`)
- Create clinician (creates user, assigns role `clinician`)
- Set specialty, credentials (MD / DO / NP / PA), licensed states (multi-checkbox US state grid)
- Toggle availability and max daily case load

### Clinician Assignment Priority (`/admin/clinicians/priority`)
- Drag-and-drop table to set the auto-assignment priority order (lower rank = assigned first)
- Editable max daily case load per clinician (inline save)
- Live capacity badge — turns red when clinician is at or over capacity
- Changes take effect immediately for new cases entering the waiting queue

### Cases (`/admin/cases`)
- List all cases across all partners/clinicians with filters (status, partner, clinician)
- **Assign** clinician to any `created` or `waiting` case
- **Reassign** clinician to an already-`assigned` case (no status change, logged as `clinician_reassigned` event)
- Case detail tabs: **Intake, Questionnaires, Prescriptions, Clinical Notes, Messages, Files, Timeline**
- Upload / delete files on a case (prescription images, lab results, etc.)

### Patients (`/admin/patients`)
- Browse all patients with case counts, filter by partner/status/search
- View patient detail: demographics, all cases, QA responses
- **Read-only** — patients are created automatically from form submissions or API case creation

### Offerings (`/admin/offerings`)
- Full CRUD on offerings on behalf of any partner
- **Approval workflow**: partner-created offerings start as `pending`; admin approves or rejects with an optional rejection note
  - Pending badge counter shown in sidebar
  - Admin uses `POST /admin/offerings/{id}/approve` and `POST /admin/offerings/{id}/reject`
- Toggle Active/Inactive per offering
- Delete offering (soft-delete with confirm dialog)
- State availability: multi-checkbox US state grid (empty = all states)

### Questionnaires (`/admin/questionnaires`)
- Create dynamic questionnaire forms with a visual question builder
- Supported field types (**16 total**): Hidden, Input, Email, Textarea, Date, Select, Multi Select, Radio, Checkbox, File, Number, Height, Weight, BMI, **Radio (Choice)**, **Checkbox (Multi)**
  - `choice` — single-select radio rendered as a choice list (alias for `radio`, distinguishable in conditional logic)
  - `multi` — multi-select checkbox rendered as a choice list (alias for `checkbox multiselect`)
- Per-question configuration: label (textarea for long consent texts), field key (auto-populated), placeholder, is_required, is_readonly
- Option-based types support a **Disqualify** toggle per option
- **Drag-and-drop question reordering** via SortableJS
- **Conditional logic** — each question can show/hide based on a prior question's answer
  - Amber pill badge on the questionnaire show page: `↳ Shows only if: "[parent question]" [operator] "[value]"`
- **Multi-step mode** — questions grouped by `step_number`; renders as paginated steps on the patient form; conditional logic evaluated across steps
- **API Integration panel** on questionnaire detail page: step-by-step guide for partners integrating via API (question ID reference table, annotated JSON payload)

### Question Bank (`/admin/questions`)
- Standalone library of all questions across all questionnaires
- Filters: keyword search, field type, questionnaire, active/inactive status
- Inline status toggle, view modal, standalone edit form, bulk delete

### Webhook Deliveries (`/admin/webhooks`)
- View all outbound webhook delivery attempts across all partners
- Failed count badge in sidebar
- Manually resend failed deliveries

### Developer Guides (`/admin/guide/*`)
Three built-in integration guides for sharing with partner developers:

- **Guide: Messaging API** (`/admin/guide/messaging`) — explains how to send and receive case messages via the API
- **Guide: Weight Loss API** (`/admin/guide/weightloss-api`) — comprehensive 8-section guide:
  1. Authentication (OAuth2 token)
  2. Discover Question IDs (both Standard Intake 1 and MWL questionnaires)
  3. Upload Prescription Image (file_token flow)
  4. Create Case — full annotated JSON payload with live question IDs pulled from DB
  5. Question Reference table for both questionnaires
  6. Error Responses
  7. What Gets Created in the Database
  8. Integration Checklist
  - Dynamic — question IDs auto-update when questionnaire is edited
  - Print-optimized: `@media print` hides admin chrome; content fills full page width
- **Guide: Anti-Aging API** (`/admin/guide/antiaging-api`) — equivalent guide for the Anti-Aging program (Metformin, NAD+, Glutathione), same structure as the Weight Loss guide with live question IDs from the Anti-Aging questionnaire

### Admin Dashboard (`/admin/dashboard`)
Professional analytics dashboard with Chart.js 4.4 charts:
- **Stat cards** — color-tinted icon circles with left-border accent for total cases, waiting, active, and completed
- **Doughnut chart** — cases by status; 72% cutout with total count in center; clickable legend
- **Area/line chart** — 30-day case creation trend with gradient fill
- **Horizontal bar chart** — top 10 clinician active case workload (`indexAxis: 'y'`); indigo-to-violet palette
- **Recent cases table** — avatar initials, clinician name or "Unassigned" fallback, status badge

### SLA Configuration (`/admin/settings`)
Admin-editable Service Level Agreement deadlines — no code deploy needed:

| Setting Key | Default | Description |
|-------------|---------|-------------|
| `sla_pickup_hours` | 4h | Time from case creation to assignment |
| `sla_review_hours` | 24h | Time from assignment to clinician action (primary SLA) |
| `sla_total_hours` | 48h | End-to-end from creation to completion |

- Changes are cached (1-hour TTL) and invalidated immediately on save
- Sidebar link under **Configuration → Settings** with `bi-sliders` icon
- Validation: pickup/review max 168h; total max 720h
- Info panel on the settings page explains the three clocks and the green/amber/red thresholds

---

## Module 2: Clinician Flows

### Dashboard (`/clinician/dashboard`)
Analytics dashboard scoped to the logged-in clinician:

**Stat cards (5):**
| Card | Description |
|------|-------------|
| Waiting Queue | Global count of unassigned cases in the waiting queue |
| My Active Cases | Cases in `assigned`, `approved`, or `processing` status assigned to this clinician |
| Completed This Month | Cases completed by this clinician in the current calendar month |
| SLA Status | Green / Amber / Red based on breached / at-risk counts across active cases |
| Completion Rate | SVG radial ring showing lifetime completion rate percentage |

**Charts:**
- **Dual-line trend** (30 days) — blue line = cases assigned per day; green line = cases completed per day; both with gradient fill
- **Visit type horizontal bar** — top 6 visit types for this clinician's cases; indigo-to-teal palette

**Active cases table** — with SLA progress bar column per case:
- Green bar: under 70% of review deadline elapsed
- Amber bar: 70–99% elapsed (at risk)
- Red bar: past deadline (breached) — shows "Breached +X.Xh"
- Label shows "X.Xh left" while on track

### Queue (`/clinician/cases/queue`)
- See all `waiting` cases; claim one to self-assign (→ `assigned`)

### Case Detail Tabs
Questionnaires, Prescriptions, Clinical Notes, Messages, Files, Timeline

**Professional chat UI** on the Messages tab:
- Avatar circle with initials (indigo for clinician "You", green for patient)
- Shaped bubbles: clinician side rounded `16px 4px 16px 16px`, patient side `4px 16px 16px 16px`
- Date separators with HR lines between day groups
- `#f8f9fc` chat background

### Assigned Case Actions

| Action | Route | Result |
|--------|-------|--------|
| **Approve & Prescribe** | `GET /clinician/cases/{uuid}/prescribe` | Opens full-page prescription form |
| Submit Prescription | `POST /clinician/cases/{uuid}/prescribe` | Saves prescription → `assigned → approved`; webhook fired |
| Escalate to Support | `POST /clinician/cases/{uuid}/support` | → `support`; `support_at` stamped; partner gains visibility |
| Cancel / Decline | `POST /clinician/cases/{uuid}/cancel` | → `cancelled`; reason logged |
| Add Clinical Note | `POST /clinician/cases/{uuid}/notes` | Note attached; webhook fired |
| Send Message | `POST /clinician/cases/{uuid}/messages` | Outbound portal message; webhook fired |
| **Send to Pharmacy** | `POST /clinician/cases/{uuid}/processing` | → `processing` then **→ `completed` automatically**; both webhooks fire |
| Upload File | `POST /clinician/cases/{uuid}/files` | File saved to storage; virus scan queued |
| Delete File | `DELETE /clinician/cases/{uuid}/files/{fileUuid}` | File removed from storage and DB |

---

## Module 3: Prescriptions

### Flow
Clinician clicks **"Approve & Prescribe"** → full-page form → submit → case transitions to `approved`.

### Prescription Form Fields

| Field | Type | Required |
|-------|------|----------|
| Diagnoses | Textarea | Yes |
| Medications | Dynamic search/select from offerings | No |
| Directions | Textarea | No |
| Medical Necessity | Textarea | No |

### Medication Search
- Offerings pre-loaded as JSON; filtered client-side by name
- Category filtering: only offerings whose `category_id` matches case offerings
- Selecting an offering auto-populates an editable medication card
- Multiple medications supported; each card has an individual Remove button

---

## Module 4: Offerings

### Approval Workflow
Partner-created offerings require admin approval before they are active:

| Status | Meaning |
|--------|---------|
| `pending` | Just created by partner; not yet visible to clinicians |
| `approved` | Admin approved; active and available |
| `rejected` | Admin rejected with a rejection note; partner can revise |

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
| State Availability | State Availability | Multi-checkbox US state grid; empty = all states |
| Active | Flags | Toggle |
| Controlled Substance | Flags | DEA compliance flag |

---

## Module 5: Questionnaires

### Seeded Questionnaires

| Name | Mode | Steps | Purpose |
|------|------|-------|---------|
| Standard Intake 1 | single | 1 | Shared baseline for all programs: general health, medications, allergies, conditions, telehealth consent |
| MWL – Weight Loss | multi | 2 | Step 1: medical history, GLP-1 history, prescription image upload. Step 2: conditional consents (gallbladder, thyroid) + GLP-1 informed consent |
| Anti-Aging | multi | 2 | Step 1: prior treatments, symptoms, contraindications. Step 2: truthfulness + informed consent |

### Public Form Features
- **Height** renders as ft + in inputs; total inches stored in hidden field
- **BMI** auto-calculates from height + weight; auto-filled with yellow highlight
- **Conditional questions** hide/show in real time across all steps
- **Multi-step navigation**: Next/Back buttons; `showStep()` re-evaluates all conditions on every step change
- **File upload** questions rendered as file picker; prescription images saved via `FileUploadService`

### Disqualification Logic
If any selected answer has `is_disqualify = true`:
- `QuestionnaireResponse.is_disqualified = true`
- `disqualified_on` set to the question key that triggered it
- No case or patient is created (form path) / case is flagged (API path)
- `disqualified: true` sent in the `postMessage` event

---

## Module 6: Partner Flows

### Web Portal
- **Offerings** — full CRUD on own offerings (submitted as `pending` for admin approval)
- **Patients** — read-only list and detail
- **Cases** — view support-escalated cases only; read the clinician's support note; write a response note and return the case to the assigned clinician; cancel with reason
- **Credentials** — view client ID / secret / webhook list

### Partner REST API

**Authentication**
```
POST /api/partner/auth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=…&client_secret=…
→ { token_type, expires_in, access_token }
```

**File Upload** (before case creation, if patient has a prescription image)
```
POST /api/partner/files
Authorization: Bearer <token>
Content-Type: multipart/form-data

file=<binary — JPG, PNG, or PDF, max 10 MB>

→ 201 { file_token, original_name, size, mime_type }
```
Use the returned `file_token` (UUID) as the answer value for `file`-type questions in the case payload.

**Submit a Case**
```
POST /api/partner/cases
{
  "patient": { first_name, last_name, email, phone, date_of_birth, gender, state, external_id },
  "external_id": "order-ref-001",
  "patient_state": "TX",
  "hold_status": false,
  "is_chargeable": true,
  "offerings": [{ "offering_id": "uuid", "quantity": 1 }],
  "questionnaire_responses": [
    {
      "questionnaire_id": "<standard-intake-1-uuid>",
      "answers": [{ "question_id": 1, "answer": "no" }, ...]
    },
    {
      "questionnaire_id": "<mwl-weight-loss-uuid>",
      "answers": [
        { "question_id": 252, "answer": ["hypertension"] },
        { "question_id": 264, "answer": "3f2a1b4c-..." }  // file_token for prescription image
      ]
    }
  ]
}
```
- Patient deduplication: `external_id` → `email` → create new
- `file`-type question answers must be `file_token` UUIDs from `POST /api/partner/files`
- `multi` / `checkbox` type answers must be JSON arrays
- Case auto-advances to `waiting` (and auto-assigns) unless `hold_status: true`
- Returns `409` if `external_id` already exists for this partner

**Case Endpoints**
```
GET    /api/partner/cases
GET    /api/partner/cases/{uuid}
GET    /api/partner/cases/by-external-id/{id}
POST   /api/partner/cases/{uuid}/cancel          { reason }
POST   /api/partner/cases/{uuid}/hold            { hold: bool }
POST   /api/partner/cases/{uuid}/support         { note }
GET    /api/partner/cases/{uuid}/events
GET    /api/partner/cases/{uuid}/messages
POST   /api/partner/cases/{uuid}/messages        { body }
```

**Patient Endpoints (read-only)**
```
GET    /api/partner/patients
GET    /api/partner/patients/{id}
GET    /api/partner/patients/by-external-id/{id}
```

**Questionnaire Endpoints (read-only — question ID discovery)**
```
GET    /api/partner/questionnaires/{uuid}
```
Returns the questionnaire with all active questions (id, question, key, type, is_required, placeholder, options).

**Offering Endpoints**
```
GET    /api/partner/offerings
POST   /api/partner/offerings
GET    /api/partner/offerings/{id}
PUT    /api/partner/offerings/{id}
DELETE /api/partner/offerings/{id}
GET    /api/partner/offerings/{id}/questionnaires
```

**Webhook Management**
```
GET    /api/partner/webhooks
POST   /api/partner/webhooks
GET    /api/partner/webhooks/{id}
PUT    /api/partner/webhooks/{id}
DELETE /api/partner/webhooks/{id}
POST   /api/partner/webhooks/deliveries/{id}/resend
```

---

## Module 7: Webhooks

All webhooks signed with HMAC-SHA256 (`X-Webhook-Signature: sha256=<digest>`). Up to 5 retry attempts with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case auto-created from form submission |
| `case_waiting` | Case enters waiting queue |
| `case_support` | Clinician escalates to support |
| `case_assigned_to_clinician` | Clinician assigned (auto or manual) |
| `case_approved` | Clinician submits prescription |
| `case_processing` | Clinician sends to pharmacy |
| `case_completed` | Case completed |
| `case_cancelled` | Any cancellation |
| `clinical_note_added` | Clinician adds a note |
| `message_created` | Clinician sends a message |
| `order_status_changed` | Order status updated |
| `tracking_number_changed` | Tracking number set |

---

## File Uploads

### FileUploadService
Central service used by both the web form and the Partner API.

- **Allowed types**: JPG, JPEG, PNG, PDF
- **Max size**: 10 MB
- **Storage**: `FILESYSTEM_DISK` env var (default `local` → `storage/app/private/patient-files/YYYY/MM/`)
- **Naming**: UUID-based filename (`{uuid}.{ext}`) — no original filename in storage
- **Virus scan**: `ScanUploadedFileJob` dispatched to the `default` queue after every upload; uses ClamAV via `clamscan`; degrades gracefully if ClamAV not installed (logged + treated as clean)

### PatientFile Model (`files` table)

| Column | Purpose |
|--------|---------|
| `uuid` | Public identifier; used as `file_token` in Partner API |
| `case_id` | Nullable until linked at case creation |
| `patient_id` | Nullable until linked at case creation |
| `partner_id` | Set at upload time for API uploads |
| `path` | Storage path relative to disk root |
| `disk` | Storage disk name (`local`, `s3`, etc.) |
| `mime_type` | MIME type at upload time |
| `size` | File size in bytes |
| `original_name` | Original filename from the upload |
| `type` | Category: `prescription`, `lab`, `other`, etc. |
| `status` | `uploaded`, `clean`, `infected` |
| `notes` | Optional clinician/admin note |

---

## Permissions Matrix

| Action | Admin | Clinician | Partner Web | Partner API |
|--------|:-----:|:---------:|:-----------:|:-----------:|
| Create partner / clinician | ✓ | — | — | — |
| Create / edit offering | ✓ | — | ✓ | ✓ |
| Approve / reject offering | ✓ | — | — | — |
| Delete / toggle offering active | ✓ | — | ✓ | — |
| Submit case via API | — | — | — | ✓ |
| Upload file via API | — | — | — | ✓ |
| View all cases | ✓ | ✓ (queue) | ✓ (support-only) | ✓ (own) |
| Assign clinician to case | ✓ | ✓ (self) | — | — |
| Reassign clinician to assigned case | ✓ | — | — | — |
| Approve case (via prescription flow) | — | ✓ | — | — |
| Submit prescription | — | ✓ | — | — |
| Assign offerings to case | — | ✓ (via prescription) | — | — |
| View prescriptions | ✓ | ✓ | — | — |
| View questionnaire responses | ✓ | ✓ | — | — |
| Escalate to support | — | ✓ | — | ✓ |
| Return support case to clinician | — | — | ✓ | — |
| Cancel case | ✓ | ✓ | ✓ | ✓ |
| Send to pharmacy (Processing) | — | ✓ | — | — |
| Add clinical note / message | — | ✓ | — | — |
| Send / read messages via API | — | — | — | ✓ |
| Upload / delete case files | ✓ | ✓ | — | — |
| Update order / tracking | — | — | — | ✓ |
| Manage webhooks | — | — | ✓ | ✓ |
| View webhook delivery log | ✓ | — | — | — |
| View patients | ✓ | — | ✓ (own) | ✓ (own) |
| Manage questionnaires / question bank | ✓ | — | — | — |
| Set clinician assignment priority | ✓ | — | — | — |
| View developer guides | ✓ | — | — | — |

---

## Data Model — Key Tables

| Table | Purpose |
|-------|---------|
| `settings` | Key-value store for configurable system settings (SLA deadlines, etc.); columns: `key` (unique), `value`, `label`, `group`, `type`, `description` |
| `users` | Auth for all roles (admin, clinician, partner) |
| `partners` | Partner organisations |
| `clinicians` | Clinician profiles; specialty, credentials, licensed states, priority, max_daily_cases |
| `patients` | Patient records, scoped to partner; deduplicated by email+partner_id |
| `cases` | Core case record with state-machine status column |
| `case_notes` | Clinical notes (general/SOAP/progress) |
| `case_messages` | Portal messages between clinician and partner |
| `case_events` | Immutable audit trail for every state change |
| `case_prescriptions` | Doctor-submitted prescriptions created on case approval |
| `case_prescription_medications` | Individual medications within a prescription |
| `files` | Uploaded files (`PatientFile` model); linked to case, patient, and/or partner |
| `offerings` | Product/medication catalogue per partner; includes `approval_status`, `rejection_note` |
| `offering_categories` | Category taxonomy; filters the prescription medication search |
| `questionnaires` | Form containers (name, description, mode: single/multi, is_active) |
| `questionnaire_questions` | Questions: type, key, placeholder, is_required, is_readonly, is_active, options (JSON), sort_order, step_number, depends_on_question_id |
| `questionnaire_responses` | One per form submission or API case submission |
| `questionnaire_answers` | One per Q&A pair; question_text frozen at submission time |
| `orders` | Fulfillment orders linked to cases |
| `webhooks` | Registered webhook endpoints per partner |
| `webhook_deliveries` | Delivery log with retry state |
| `oauth_clients` | Passport client credentials per partner (client_credentials grant) |
| `jobs` | Laravel queue jobs table (webhooks, virus scans) |
| `failed_jobs` | Failed job log |
| `sessions` | Database-backed sessions |

---

## Key Architectural Decisions

1. **Two case entry paths**: Form submission (iFrame embed) and Partner REST API. Both ultimately call the same `CaseStateMachine::transition()` flow and create the same DB records. The API path supports programmatic patient portals that have their own intake UI.

2. **Two-questionnaire requirement for MWL (API path)**: Partners submitting Weight Loss cases via API must include both Standard Intake 1 and MWL – Weight Loss questionnaire responses in a single `POST /api/partner/cases` call. The API validates that required questionnaires are present for attached offerings.

3. **File token flow for API uploads**: Partners cannot embed raw binary files in the case creation JSON. Instead, they call `POST /api/partner/files` first to upload the file and receive a `file_token` (UUID). This token is submitted as the answer to `file`-type questions in the case payload. `CaseController` resolves the token within the DB transaction, links the `PatientFile` record to the case and patient, and stores the original filename as the displayed answer.

4. **Auto-case creation on form submit**: `QuestionnaireFormController` creates the patient, creates the case, then calls `CaseStateMachine::transition()` outside the DB transaction so webhooks fire after commit.

5. **Auto-assignment priority system**: `CaseAutoAssigner` selects the highest-priority (`ORDER BY priority ASC`) clinician who is active, available, and below their `max_daily_cases` limit. Fires automatically when a case transitions to `waiting`.

6. **`skip_auto_assign` context flag**: Admin manual assignment passes this flag so the auto-assigner does not race and create a double-assignment conflict.

7. **Reassign without state change**: `CaseStateMachine::reassign()` bypasses the state transition graph for `assigned → assigned`. It directly updates `clinician_id` and logs a `clinician_reassigned` event.

8. **Offerings approval workflow**: Partner-created offerings are `pending` by default. Admin must explicitly approve before they can be attached to cases or visible to clinicians. Admins can reject with a note so the partner can revise.

9. **`FileUploadService`**: Central file handling used by both the web (clinician/admin case file uploads) and the API (partner prescription image uploads). Uses `config('filesystems.default')` for disk, generates UUID filenames, dispatches `ScanUploadedFileJob` to the `default` queue after every upload.

10. **`VirusScanService`**: Wraps `clamscan` CLI. Degrades gracefully if ClamAV is not installed — logs an info message and treats the file as clean. This prevents blocking legitimate uploads in environments without ClamAV. In a strict security posture, the `return true` on scan-unavailable should be changed to `return false`.

11. **Questionnaire question sort order**: Questions rendered in `sort_order ASC` order. SortableJS drag-and-drop; after each drag, `reindexCards()` renames all `name="questions[idx]..."` attributes to match the new DOM order. Backend's `syncQuestions()` uses `array_values()` and assigns `sort_order = $i` from the submitted array.

12. **Two-pass `syncQuestions()`**: Conditional logic uses a self-referencing FK (`depends_on_question_id`). Pass 1 creates all questions capturing `idx → DB id` map; Pass 2 resolves and writes the FK. Questionnaire `update()` does `delete()` then re-creates all questions — question IDs change on every save.

13. **Cross-step conditional logic (form)**: All conditional logic runs in a single JS IIFE. `showStep()` calls `evaluateConditions()` on every step change so conditional questions re-evaluate correctly across step boundaries.

14. **Dynamic developer guide**: The Weight Loss API guide page pulls live question IDs directly from the DB via the `$questionnaire` and `$standardIntake` models passed from the route. If questions are recreated (e.g. after an admin edit), the guide self-corrects automatically.

15. **`case_prescriptions` vs `prescriptions`**: Doctor-submitted prescriptions use the `case_` prefix to coexist with the DoseSpot `prescriptions` table.

16. **Patient deduplication**: `Patient::firstOrCreate([email, partner_id])` on form submit; API path also deduplicates by `external_id` first, then `email`.

17. **Laravel Passport 13 compatibility**: `createClientCredentialsGrantClient()` no longer accepts `$userId` — only the name string is passed. Client IDs are ULIDs, so `partners.oauth_client_id` is `string(100)`. Schema uses `owner_type`, `owner_id`, `grant_types` columns instead of the Passport 12 booleans.

18. **No Vite/npm**: All frontend uses Bootstrap 5 + Bootstrap Icons + SortableJS via CDN. JavaScript is vanilla, written inline in Blade `@section('scripts')` blocks.

19. **Queue-backed async work**: Webhook delivery (`SendWebhookJob` on `webhooks` queue) and virus scanning (`ScanUploadedFileJob` on `default` queue) are dispatched to the `database` queue. A queue worker must be running on production (`php artisan queue:work --queue=webhooks,default`) for these to execute. On local/dev, jobs sit in the `jobs` table until processed.
