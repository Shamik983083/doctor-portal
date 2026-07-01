# Doctor Portal — Telehealth & E-Prescribing Integration Platform

A multi-role telehealth platform where healthcare partners embed a public intake questionnaire into their patient portal. On submission, a case is auto-created and routed to a clinician via priority queue for review, approval, and e-prescribing.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12, PHP 8.2 |
| Database | MySQL (XAMPP) |
| Auth | Laravel Passport 13.7 (OAuth2 for API), session auth for web |
| Roles | Spatie Permission 6.25 |
| PDF | barryvdh/laravel-dompdf |
| Frontend | Bootstrap 5 + Bootstrap Icons (CDN), SortableJS, vanilla JS — no Vite build step |

---

## Roles

| Role | Login URL | Auth Method |
|------|-----------|-------------|
| Admin | `/admin/dashboard` | Email + password |
| Clinician | `/clinician/dashboard` | Email + password |
| Partner (web) | `/partner/dashboard` | Email + password |
| Partner (API) | `POST /api/partner/auth/token` | OAuth2 client credentials |

---

## Local Setup

### Prerequisites
- PHP 8.2+
- MySQL running via XAMPP (or equivalent)
- Composer

### Install

```bash
git clone <repo-url> doctor-portal
cd doctor-portal
composer install
cp .env.example .env
php artisan key:generate
```

Configure your `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doctor_portal
DB_USERNAME=root
DB_PASSWORD=
```

### Database

```bash
php artisan migrate
php artisan db:seed       # optional — seeds roles, admin user
```

### Passport (API auth)

```bash
php artisan passport:install
```

### Run

Using XAMPP, point the virtual host at the `public/` directory, or:

```bash
php artisan serve
```

---

## How Cases Enter the System

Cases originate **exclusively** from the public questionnaire form. There is no manual case creation step.

1. Admin creates a Questionnaire and copies the iFrame embed code from the **Share & Embed** panel
2. Partner embeds the iFrame on their patient portal:
   ```html
   <iframe
     src="https://yourdomain.com/forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={PATIENT_ID}"
     width="100%" height="680" frameborder="0" allow="camera">
   </iframe>
   ```
3. Patient submits the form → `Patient::firstOrCreate` runs → `PatientCase` created → auto-assigned to the highest-priority available clinician
4. The form page fires a `postMessage` to the parent frame on completion:
   ```js
   window.addEventListener('message', function(event) {
       if (event.data.event === 'questionnaire_completed') {
           if (event.data.disqualified) {
               // patient didn't qualify — no case created
           } else {
               // case is live in the queue
           }
       }
   });
   ```

---

## Case Lifecycle

```
CREATED → WAITING → ASSIGNED → APPROVED → PROCESSING → COMPLETED
                 ↘          ↗ ↑
                 SUPPORT ───┘  (partner returns to same clinician with note)
(CANCELLED reachable from any state except COMPLETED)
```

| Transition | Triggered By |
|-----------|-------------|
| Created → Waiting | Auto on form submit |
| Waiting → Assigned | Auto-assigner, admin manual assign, or clinician self-claim |
| Assigned → Approved | Clinician (via Approve & Prescribe) |
| Assigned → Support | Clinician (with a note explaining what info is needed) |
| **Support → Assigned** | **Partner** — writes a response note; case returns to the **same clinician** (not re-queued) |
| **Approved → Processing** | **Clinician** — Send to Pharmacy button |
| Processing → Completed | System / Admin |
| Any → Cancelled | Admin / Clinician / Partner |

**Partner role in the lifecycle:**
- Partners only see cases escalated to support (`support_at IS NOT NULL`)
- They read the clinician's support note, write a response, and click **Return to Clinician**
- The case goes directly back to the assigned clinician — `clinician_id` is preserved, no re-queueing
- Partners have no control over Processing or Completion — those are clinician-owned

---

## Modules

### Admin

| Module | URL | Description |
|--------|-----|-------------|
| Partners | `/admin/partners` | Create partners; generates OAuth2 client ID + secret; manage portal users |
| Clinicians | `/admin/clinicians` | Create clinicians; set specialty, credentials, licensed states, availability |
| Priority Queue | `/admin/clinicians/priority` | Drag-and-drop assignment priority; inline max daily case load; live capacity badge |
| Cases | `/admin/cases` | Full case list with filters; assign / reassign clinicians; view all tabs |
| Patients | `/admin/patients` | Read-only patient list and detail (created automatically from form submissions) |
| Offerings | `/admin/offerings` | Full CRUD on medications/compounds/supplies; state availability; dispensing fields |
| Questionnaires | `/admin/questionnaires` | Visual question builder; 14 field types; drag-and-drop reorder; conditional logic; share & embed |
| Question Bank | `/admin/questions` | Standalone question library across all questionnaires; bulk delete; inline toggle |

### Clinician

| Module | URL | Description |
|--------|-----|-------------|
| Queue | `/clinician/cases/queue` | See waiting cases; self-claim |
| Case Detail | `/clinician/cases/{uuid}` | Tabs: Intake, Questionnaires, Prescriptions, Notes, Messages, Files, Timeline |
| Prescribe | `/clinician/cases/{uuid}/prescribe` | Full prescription form with medication search; transitions case to `approved` |

### Partner (Web)

| Module | URL | Description |
|--------|-----|-------------|
| Offerings | `/partner/offerings` | CRUD on own offerings |
| Patients | `/partner/patients` | Read-only |
| Cases | `/partner/cases` | View support-escalated cases only; read clinician's support note; return to clinician with a response note; cancel |
| Credentials | `/partner/credentials` | View client ID / secret / webhook list |

---

## Questionnaire Builder

The question builder at `/admin/questionnaires/create` (and `/edit`) supports:

- **14 field types**: Hidden, Input, Email, Textarea, Date, Select, Multi Select, Radio, Checkbox, File, Number, Height, Weight, BMI
- **Drag-and-drop reorder** via SortableJS
- **Field key auto-population** — typing "Email Address" auto-fills key `email` with yellow background (overridable)
- **Disqualify toggle** per option on Select / Radio / Checkbox types — sets `is_disqualify = true`; triggers disqualification on submission
- **Conditional logic** — each question can be set to "Show only if [prior question] [operator] [value]"
  - Operators: `equals`, `does not equal`, `contains`, `is answered`
  - The questionnaire **show page** displays an amber pill badge (`↳ Shows only if: ...`) for each conditional question so the dependency is visible at a glance
- **Multi-step mode** — questions grouped by step number; renders as paginated steps on the patient form

### Patient Form Features

- **Height** renders as two inputs (ft + in); combined total inches stored in the hidden field
- **BMI** auto-calculates from height + weight using the imperial formula: `(weight_lbs ÷ height_in²) × 703`; auto-filled with yellow highlight
- **Conditional questions** hide/show in real time; required validation skips hidden questions

---

## Patient Field Mapping

Questions whose `key` matches a patient column are automatically written to the patient record on form submission:

| Key | Patient Column |
|-----|---------------|
| `email` | `email` (required) |
| `first_name` | `first_name` |
| `last_name` | `last_name` |
| `phone` | `phone` |
| `date_of_birth` | `date_of_birth` |
| `age` | `age` |
| `height` | `height` (decimal, inches) |
| `weight` | `weight` (decimal, lbs) |
| `bmi` | `bmi` (decimal) |
| `gender` | `gender` |
| `address`, `address2`, `city`, `state`, `zip`, `country` | address fields |

---

## Webhooks

All webhooks are signed with HMAC-SHA256 and retried up to 5 times with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case auto-created from form submission |
| `case_waiting` | Case enters waiting queue |
| `case_assigned_to_clinician` | Clinician assigned (auto or manual) |
| `case_support` | Clinician escalates to support |
| `case_approved` | Clinician submits prescription |
| `case_processing` | Partner moves to processing |
| `case_completed` | Case completed |
| `case_cancelled` | Any cancellation |
| `clinical_note_added` | Clinician adds a note |
| `message_created` | Clinician sends a message |

---

## Partner API — Quick Reference

```
POST   /api/partner/auth/token                   Obtain bearer token (client_credentials grant)
GET    /api/partner/questionnaires/{uuid}        Discover question IDs before submitting cases
POST   /api/partner/cases                        Submit a new case
GET    /api/partner/cases                        List own cases
GET    /api/partner/cases/{uuid}                 Case detail
GET    /api/partner/cases/by-external-id/{id}    Lookup by partner's own reference
POST   /api/partner/cases/{uuid}/cancel          Cancel a case
POST   /api/partner/cases/{uuid}/hold            Toggle hold status
POST   /api/partner/cases/{uuid}/support         Escalate with a note
GET    /api/partner/cases/{uuid}/events          Audit trail
GET    /api/partner/patients                     List own patients
GET    /api/partner/patients/{id}                Patient detail
GET    /api/partner/patients/by-external-id/{id} Lookup by partner's patient ID
```

The questionnaire detail page (`/admin/questionnaires/{id}`) has an **API Integration panel** with a step-by-step guide (auth payload, annotated case JSON, question ID reference table) ready to share with partners.

---

## Key Architectural Notes

- **Auto-assignment**: `CaseAutoAssigner` selects the highest-priority (`ORDER BY priority ASC`) active, available clinician below their `max_daily_cases` limit. Fires automatically on transition to `waiting`.
- **`skip_auto_assign` flag**: Admin manual assignment passes this context flag so the auto-assigner does not race and double-assign.
- **Two-pass `syncQuestions()`**: Conditional logic uses a self-referencing FK (`depends_on_question_id`). Pass 1 creates all questions and captures `idx → DB id` map; Pass 2 resolves and writes the FK.
- **Patient deduplication**: `Patient::firstOrCreate([email, partner_id])` — same patient submitting again updates their record rather than creating a duplicate.
- **`case_prescriptions` vs `prescriptions`**: Doctor-submitted prescriptions use the `case_` prefix to coexist independently alongside the DoseSpot `prescriptions` table.
- **Passport 13 compatibility**: `createClientCredentialsGrantClient()` no longer accepts `$userId` as the first param — only the name string is passed. Client IDs are now ULIDs, so `partners.oauth_client_id` is `string(100)`.
- **No Vite**: All assets loaded from CDN. JS is vanilla, written inline in Blade `@section('scripts')` blocks.

---

## Running Artisan Commands

```bash
# Clear caches after changes
php artisan route:clear && php artisan view:clear && php artisan config:clear

# Run pending migrations
php artisan migrate

# Run tests
php artisan test
```
