# Doctor Portal — Product Requirements Document

**Last Updated:** 2026-07-15 (rev 5)
**Status:** In Development
**Stack:** Laravel 12, PHP 8.2, Bootstrap 5, MySQL (XAMPP), Laravel Passport 13.7, Spatie Permission 6.25

---

## Overview

A Telehealth & E-Prescribing Integration Platform. Healthcare partners can submit patient cases either by embedding a public questionnaire form on their patient portal (hosted form path) or by calling the Partner REST API directly (API path). Cases enter a clinician queue for auto-assignment. Clinicians review, approve, and prescribe medications. Admins oversee the entire system.

---

## Roles & Authentication

| Role | Login | Entry Point | Notes |
|------|-------|-------------|-------|
| **Super Admin** | Email + password | `/admin/dashboard` | Full access to everything including Admin Users management |
| **Admin** | Email + password | `/admin/dashboard` | All admin features except Admin Users management |
| **Clinician** | Email + password | `/clinician/dashboard` | |
| **Partner (web)** | Email + password | `/partner/dashboard` | |
| **Partner (API)** | OAuth2 client credentials | `POST /api/partner/auth/token` | |

### Spatie Role Names
| Role | Name | Middleware |
|------|------|-----------|
| Super Admin | `super_admin` | `role:admin\|super_admin` (admin routes) + `role:super_admin` (admin users routes) |
| Admin | `admin` | `role:admin\|super_admin` |
| Clinician | `clinician` | `role:clinician` |
| Partner (web) | `partner` | `role:partner` |

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

### Admin Users — Super Admin Only (`/admin/admins`)
Visible only to users with the `super_admin` role (sidebar section hidden from regular admins).

- **List** all admin and super admin users with search (name/email) and role filter
- **Create** new admin or super admin account (name, email, password, role)
- **Promote** an Admin to Super Admin
- **Demote** a Super Admin to Admin
- **Delete** any admin user (cannot delete own account)
- **"You" badge** on the logged-in user's row; all destructive actions blocked on self
- All actions in the table use icon buttons (`bi-eye`, `bi-arrow-up-circle`, `bi-arrow-down-circle`, `bi-trash`) with tooltip titles

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
Redesigned using the MA Portal design system. Full case list:
- Filters: patient name search, triage dropdown (red/yellow/green), status, partner, clinician
- Table has a **Triage** column with `ma-pill` (green/yellow/red); offerings use `ma-pill neutral`
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
Fully redesigned using the MA Portal design system (`<x-ma-styles />`). Professional analytics dashboard with Chart.js 4.4 charts:

**Metric grid (6 clickable stat cards):** Total Cases, Waiting, Assigned, Completed, Approval Rate, Avg Review Time

**Storefront workload table:** Per-partner row with open / green / yellow / red case counts

**Provider load bars:** Active cases vs cap with ≥ 85% warning threshold; 2-column layout with exception center panel showing:
- Workflow holds (yellow)
- Support escalations (red)
- Missing IDV (yellow)
- Cancellations in last 7 days (neutral)

**Operational report (2-column):**
- TTFR (time-to-first-response), TTD (time-to-decision), approval rate, throughput metrics
- Triage volume bar chart (using `FIELD(triage,...)` ordering)

**Charts (Chart.js 4.4.0 from CDN):**
- **Doughnut** — cases by status; 72% cutout with total count in center
- **Area/line trend** — 30-day case creation trend with gradient fill
- **Horizontal bar** — top 10 clinician active case workload (`indexAxis: 'y'`); teal accent (`#0d9488`)

**Webhook delivery log:** Latest 6 deliveries with status badges + failed count

**Recent cases table:** With triage `ma-pill` badges (green/yellow/red)

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

---

## Module 1b: Triage Classification

Every case is automatically classified into a triage band as it enters the `waiting` queue. This is a **review-priority signal for clinicians, not a clinical decision**.

### Bands

| Band | Meaning | Display |
|------|---------|---------|
| `red` | Critical — high BMI, multiple contraindications, or complex history | Red pill — "review carefully" |
| `yellow` | Borderline — one or more flags worth a closer look | Yellow pill — "closer look" |
| `green` | Routine — no significant flags raised | Green pill — "routine" |
| `null` | Not yet classified (cases created before triage was deployed) | Greyed out — "Not yet classified" |

### How It Works

1. `CaseStateMachine::transition()` detects `$toStatus === STATUS_WAITING`
2. Calls `TriageClassifier::apply($case)` — runs before auto-assignment
3. `TriageClassifier` evaluates the case against rules in `config/triage.php`:
   - BMI thresholds
   - Questionnaire answer flags
   - Medical conditions
   - Offering-specific contraindications
4. Writes `triage`, `triage_reasons` (JSON array of signal strings), `triage_ruleset` label, and `triaged_at` to the case record
5. Case is then auto-assigned by `CaseAutoAssigner` (triage band already set)

### Triage Backfill

Existing cases created before triage deployment have `triage = NULL`. Run once to classify them:
```bash
php artisan triage:backfill
```

### Demo Seeder

```bash
php artisan db:seed --class=TriageDemoCasesSeeder
```

Creates sample cases across all three triage bands for UI testing.

---

## Module 1c: Pharmacy Dispatch

When a clinician clicks **"Send to Pharmacy"**, the `PharmacyDispatchService` and `PrescriptionDocumentService` are invoked alongside the case state transition.

### Pharmacy Gateways

| Adapter | Used For |
|---------|---------|
| `LifeFilePharmacyAdapter` | LifeFile pharmacy integration |
| `MockPharmacyAdapter` | Local/staging testing without a real gateway |

Gateway selection is driven by `config/dispatch.php` and the offering's `pharmacy_type` field.

### Dispatch Flow

1. Clinician submits prescription → case transitions `approved`
2. Clinician clicks "Send to Pharmacy" → `POST /clinician/cases/{uuid}/processing`
3. `PrescriptionDocumentService` generates a `PrescriptionDocument` record (PDF-ready)
4. `PharmacyDispatchService` dispatches `DispatchPharmacyOrderJob` to the queue
5. Job calls the configured gateway adapter; result stored in `pharmacy_dispatches` table
6. Case transitions `processing → completed` automatically; both webhooks fire

### New Tables

| Table | Purpose |
|-------|---------|
| `prescription_documents` | Generated prescription documents linked to case/prescription |
| `pharmacy_dispatches` | Dispatch attempt log per case (status, gateway response, timestamps) |

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
Redesigned using the MA Portal design system. Full-featured provider review queue:

**Triage metric cards (4):** Open in Queue, Red, Yellow, Green — live counts from open-status cases only (`waiting`, `assigned`, `support`)

**Card header:**
- Eyebrow: "Provider review queue"
- Title: "Fast review, full context one click away"
- Subtitle: "Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision."
- Legend pills: Red · review carefully, Yellow · closer look, Green · routine

**Filters:** Patient name search, All Triage dropdown (red/yellow/green), All States dropdown (50 US states), All Statuses dropdown

**Table columns:** Checkbox (batch), Triage pill, Time, Patient (with unread message badge), IDV, Sex, Age, BMI, Offerings, Video Visit, Company, Status, Actions

**Triage sort order:** Red first, then yellow, then green, then NULL (existing cases) — via `FIELD(triage,'red','yellow','green') DESC`

**Batch Review:**
- Checkboxes on eligible rows (Green triage + waiting/assigned + no hold + not support)
- Select-all checkbox in header
- Batch card shows case count badge, step indicators (Select → Preflight → Attest → Approve)
- "Run preflight" button POSTs to `clinician.cases.batch.preflight` — server revalidates each case (state availability, offerings, hold status) and returns pass/fail table
- Attestation checkbox: "I have reviewed each case above and attest that each prescription is clinically appropriate"
- Submit POSTs to `clinician.cases.batch.submit` — wraps all case transitions in `DB::transaction()`, calls `CaseStateMachine` for each; shows results and reloads after 2.5 s
- Webhooks fire automatically per case via `CaseStateMachine`

### Case Detail (`/clinician/cases/{uuid}`)
**Triage banner** at top of page (before the main content grid):
- Eyebrow: "Triage classification"
- `<x-triage-pill>` component showing the case's band
- `$case->triageMeaning()` text: "Critical — review carefully before approving" / "Borderline — closer look recommended" / "Routine — no flags raised" / "Not yet classified"
- Triage signals panel (right side): shows `triage_ruleset` label + each signal as a `ma-pill neutral` badge
- NULL triage (existing cases) shows graceful "Not yet classified" fallback — no PHP error

**Tabs:** Questionnaires, Prescriptions, Clinical Notes, Messages, Files, Timeline

**Professional chat UI** on the Messages tab:
- Avatar circle with initials (indigo for clinician "You", green for patient)
- Shaped bubbles: clinician side rounded `16px 4px 16px 16px`, patient side `4px 16px 16px 16px`
- Date separators with HR lines between day groups
- `#f8f9fc` chat background
- Real-time polling: JS polls `GET /clinician/cases/{uuid}/messages/poll` every 5 seconds; new messages appended without page reload

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

| Action | Super Admin | Admin | Clinician | Partner Web | Partner API |
|--------|:-----------:|:-----:|:---------:|:-----------:|:-----------:|
| Manage admin users (create/promote/demote/delete) | ✓ | — | — | — | — |
| Create partner / clinician | ✓ | ✓ | — | — | — |
| Create / edit offering | ✓ | ✓ | — | ✓ | ✓ |
| Approve / reject offering | ✓ | ✓ | — | — | — |
| Delete / toggle offering active | ✓ | ✓ | — | ✓ | — |
| Submit case via API | — | — | — | — | ✓ |
| Upload file via API | — | — | — | — | ✓ |
| View all cases | ✓ | ✓ | ✓ (queue) | ✓ (support-only) | ✓ (own) |
| Assign clinician to case | ✓ | ✓ | ✓ (self) | — | — |
| Reassign clinician to assigned case | ✓ | ✓ | — | — | — |
| Batch review cases | — | — | ✓ (green triage only) | — | — |
| Approve case (via prescription flow) | — | — | ✓ | — | — |
| Submit prescription | — | — | ✓ | — | — |
| Assign offerings to case | — | — | ✓ (via prescription) | — | — |
| View prescriptions | ✓ | ✓ | ✓ | — | — |
| View questionnaire responses | ✓ | ✓ | ✓ | — | — |
| Escalate to support | — | — | ✓ | — | ✓ |
| Return support case to clinician | — | — | — | ✓ | — |
| Cancel case | ✓ | ✓ | ✓ | ✓ | ✓ |
| Send to pharmacy (Processing) | — | — | ✓ | — | — |
| Add clinical note / message | — | — | ✓ | — | — |
| Send / read messages via API | — | — | — | — | ✓ |
| Upload / delete case files | ✓ | ✓ | ✓ | — | — |
| Update order / tracking | — | — | — | — | ✓ |
| Manage webhooks | — | — | — | ✓ | ✓ |
| View webhook delivery log | ✓ | ✓ | — | — | — |
| View patients | ✓ | ✓ | — | ✓ (own) | ✓ (own) |
| Manage questionnaires / question bank | ✓ | ✓ | — | — | — |
| Set clinician assignment priority | ✓ | ✓ | — | — | — |
| View developer guides | ✓ | ✓ | — | — | — |
| Run triage backfill | ✓ | ✓ | — | — | — |

---

## Data Model — Key Tables

| Table | Purpose |
|-------|---------|
| `settings` | Key-value store for configurable system settings (SLA deadlines, etc.); columns: `key` (unique), `value`, `label`, `group`, `type`, `description` |
| `users` | Auth for all roles (super_admin, admin, clinician, partner) |
| `partners` | Partner organisations |
| `clinicians` | Clinician profiles; specialty, credentials, licensed states, priority, max_daily_cases |
| `patients` | Patient records, scoped to partner; deduplicated by email+partner_id |
| `cases` | Core case record with state-machine status column; includes `triage`, `triage_reasons` (JSON), `triage_ruleset`, `triaged_at` columns |
| `case_notes` | Clinical notes (general/SOAP/progress) |
| `case_messages` | Portal messages between clinician and partner |
| `case_events` | Immutable audit trail for every state change |
| `case_prescriptions` | Doctor-submitted prescriptions created on case approval |
| `case_prescription_medications` | Individual medications within a prescription |
| `prescription_documents` | Generated prescription documents linked to case/prescription (PDF-ready) |
| `pharmacy_dispatches` | Pharmacy dispatch attempt log per case (gateway, status, response, timestamps) |
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
| `jobs` | Laravel queue jobs table (webhooks, virus scans, pharmacy dispatch) |
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

19. **Queue-backed async work**: Webhook delivery (`SendWebhookJob` on `webhooks` queue), virus scanning (`ScanUploadedFileJob` on `default` queue), and pharmacy dispatch (`DispatchPharmacyOrderJob` on `default` queue) are dispatched to the `database` queue. A queue worker must be running on production (`php artisan queue:work --queue=webhooks,default`) for these to execute. On local/dev, jobs sit in the `jobs` table until processed.

20. **Super Admin role isolation**: All admin routes use `role:admin|super_admin` (Spatie OR syntax). The Admin Users sub-routes additionally gate to `role:super_admin` only. The `super_admin` role is seeded with `Permission::all()` — it inherits every permission automatically. The sidebar Admin Users section is wrapped in `@role('super_admin')` so regular admins never see it.

21. **Flash message ownership**: `layouts/app.blade.php` renders `session('success')` and `session('error')` globally. Individual views must never add their own flash blocks — doing so causes double flash rendering because the layout already handles it.

22. **Triage is non-blocking**: `TriageClassifier::apply()` is called after the DB transaction commits and after the webhook fires. If classification fails (exception), it is caught internally and logged — the case still enters the queue with `triage = NULL`. The queue and case show handle NULL gracefully.

23. **Batch review pre-flight**: The batch preflight endpoint (`POST clinician.cases.batch.preflight`) revalidates every selected case server-side (state, offering availability for patient's state, hold status) before showing the attestation step. The batch submit wraps all state machine calls in a single `DB::transaction()`. Webhooks fire per case from within `CaseStateMachine`.

24. **MA Portal design system**: A suite of CSS utility classes loaded via `<x-ma-styles />` Blade component. Used on admin dashboard, admin cases index, clinician queue, and clinician case show. Classes: `ma-surface`, `ma-metric-grid`, `ma-metric`, `ma-pill` (red/yellow/green/neutral), `ma-dot`, `ma-eyebrow`, `ma-title`, `ma-sub`, `ma-legend`, `ma-provider-load`, `ma-barchart`, `ma-stat-grid`. The MA Portal preview views (`/ma-portal/practitioner`, `/ma-portal/admin`, `/ma-portal/super-admin`) use the `layouts/ma-portal.blade.php` layout.

25. **Login redirect by role**: `LoginController::redirectAfterLogin()` checks roles in this order: `super_admin` → `admin` → `clinician` → `partner` → fallback `/login`. The super_admin check must come first because Spatie's `hasRole('admin')` returns `false` for super_admin (they are separate roles). Missing this order causes an infinite redirect loop for super admin logins.
