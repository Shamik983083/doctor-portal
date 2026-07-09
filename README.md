# Doctor Portal — Telehealth & E-Prescribing Integration Platform

A multi-role telehealth platform where healthcare partners submit patient cases either by embedding a public intake questionnaire (iFrame) or calling the Partner REST API directly. Cases enter a clinician priority queue, are reviewed and e-prescribed, then dispatched to pharmacy.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12, PHP 8.2 |
| Database | MySQL (XAMPP locally) |
| Auth | Laravel Passport 13.7 (OAuth2 for API), session auth for web |
| Roles | Spatie Permission 6.25 |
| Frontend | Bootstrap 5 + Bootstrap Icons (CDN), SortableJS, vanilla JS — no Vite/npm build |
| File Storage | Local (`storage/app/private`) or S3 (swap via `FILESYSTEM_DISK` env var) |
| Virus Scan | ClamAV via `clamscan` CLI (optional — degrades gracefully if not installed) |
| Queue | Laravel database queue (`jobs` table) |

---

## Roles

| Role | Login URL | Auth Method |
|------|-----------|-------------|
| Admin | `/admin/dashboard` | Email + password |
| Clinician | `/clinician/dashboard` | Email + password |
| Partner (web) | `/partner/dashboard` | Email + password |
| Partner (API) | `POST /api/partner/auth/token` | OAuth2 client_credentials |

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
APP_URL=http://localhost/doctor-portal/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doctor_portal
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

### Database

```bash
php artisan migrate
php artisan db:seed       # seeds roles, admin user, and sample questionnaires
```

### Passport (API auth)

```bash
php artisan passport:install
```

This generates `storage/oauth-private.key` and `storage/oauth-public.key` and creates the personal access client. Partner clients are created automatically when an admin creates a partner via the web portal.

### Storage symlink

```bash
php artisan storage:link
```

Required for any uploaded files to be publicly accessible.

### Queue Worker (webhooks + virus scan)

```bash
php artisan queue:work --queue=webhooks,default --sleep=3 --tries=3
```

Without this, webhook deliveries and ClamAV virus scans will queue up in the `jobs` table but never run. On local dev this is optional — API calls and case creation still work; webhooks just don't fire.

### Run

Point XAMPP virtual host at the `public/` directory, or:

```bash
php artisan serve
```

---

## Deployment to a Production Server

Update `.env`:
```env
APP_ENV=production
APP_DEBUG=false          # CRITICAL — must be false in production
APP_URL=https://yourdomain.com

DB_HOST=your-db-host
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=you@domain.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

Run on server after upload:
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan passport:keys       # generate fresh OAuth keys for this server
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point web server document root at the `public/` directory.

Run a queue worker as a daemon (supervisor or systemd):
```bash
php artisan queue:work --queue=webhooks,default --sleep=3 --tries=3 --max-time=3600
```

---

## How Cases Enter the System

### Path A — Hosted Form (iFrame Embed)

1. Admin creates a Questionnaire; copies the embed code from the **API Integration panel**
2. Partner embeds the iFrame:
   ```html
   <iframe
     src="https://yourdomain.com/forms/{questionnaire_uuid}?partner_token={partner_uuid}&external_id={PATIENT_ID}"
     width="100%" height="680" frameborder="0" allow="camera">
   </iframe>
   ```
3. Patient submits → `Patient::firstOrCreate` runs → `PatientCase` created → auto-assigned to highest-priority available clinician
4. Form fires `postMessage` on completion:
   ```js
   window.addEventListener('message', function(event) {
       if (event.data.event === 'questionnaire_completed') {
           if (event.data.disqualified) { /* no case created */ }
           else { /* case is live in the queue */ }
       }
   });
   ```

### Path B — Partner REST API

Partners with their own intake UI push data programmatically. See **Partner API** section below.

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
| Created → Waiting | Auto on form submit or API case creation |
| Waiting → Assigned | Auto-assigner, admin manual assign, or clinician self-claim |
| Assigned → Approved | Clinician (via Approve & Prescribe) |
| Assigned → Support | Clinician (with a note) |
| Support → Assigned | Partner — writes response note; case returns to **same clinician** |
| Approved → Processing | Clinician — Send to Pharmacy (auto-advances to Completed immediately) |
| Processing → Completed | Auto — fires in the same request as Send to Pharmacy |
| Any → Cancelled | Admin / Clinician / Partner |

---

## Modules

### Admin

| Module | URL | Description |
|--------|-----|-------------|
| Dashboard | `/admin/dashboard` | Chart.js analytics: doughnut by status, 30-day area trend, clinician workload bar, recent cases table |
| Partners | `/admin/partners` | Create partners; auto-generates OAuth2 client credentials; manage portal users |
| Clinicians | `/admin/clinicians` | Create clinicians; specialty, credentials, licensed states (US state grid), availability |
| Priority Queue | `/admin/clinicians/priority` | Drag-and-drop assignment priority; inline max daily case load; live capacity badge |
| Cases | `/admin/cases` | Full case list with filters; assign/reassign clinicians; professional chat UI on Messages tab |
| Patients | `/admin/patients` | Read-only patient list and detail |
| Offerings | `/admin/offerings` | Full CRUD; approve/reject partner offerings; state availability; dispensing fields |
| Questionnaires | `/admin/questionnaires` | Visual question builder (16 field types); multi-step; conditional logic; drag-and-drop |
| Question Bank | `/admin/questions` | Standalone question library; bulk delete; inline toggle |
| Webhook Log | `/admin/webhooks` | View all delivery attempts; resend failed; sidebar badge for failed count |
| Guide: Messaging API | `/admin/guide/messaging` | Integration guide for the messaging API |
| Guide: Weight Loss API | `/admin/guide/weightloss-api` | Full integration guide for MWL cases (dynamic live question IDs, print-friendly) |
| Guide: Anti-Aging API | `/admin/guide/antiaging-api` | Integration guide for Anti-Aging program cases |
| Settings | `/admin/settings` | Configurable SLA deadlines (pickup, review, end-to-end hours); changes take effect immediately |

### Clinician

| Module | URL | Description |
|--------|-----|-------------|
| Dashboard | `/clinician/dashboard` | 5 stat cards; dual-line 30-day trend; visit type bar; SVG completion rate ring; SLA status card; active cases table with per-case SLA progress bar |
| Queue | `/clinician/cases/queue` | See waiting cases; self-claim |
| Case Detail | `/clinician/cases/{uuid}` | Tabs: Questionnaires, Prescriptions, Notes, Messages (professional chat UI), Files, Timeline |
| Prescribe | `/clinician/cases/{uuid}/prescribe` | Prescription form with medication search; transitions to `approved` |

### Partner (Web)

| Module | URL | Description |
|--------|-----|-------------|
| Offerings | `/partner/offerings` | CRUD on own offerings (submitted as `pending` for admin approval) |
| Patients | `/partner/patients` | Read-only |
| Cases | `/partner/cases` | View support-escalated cases; return to clinician with response; cancel |
| Credentials | `/partner/credentials` | View client ID / secret / webhook list |

---

## Questionnaire Builder

The question builder supports **16 field types**:

| Type | Rendered As |
|------|-------------|
| `hidden` | Hidden input |
| `input` | Text input |
| `email` | Email input |
| `textarea` | Multi-line text |
| `date` | Date picker |
| `number` | Number input |
| `height` | ft + in split inputs; stores total inches |
| `weight` | Number input (lbs) |
| `bmi` | Auto-calculated from height + weight |
| `file` | File picker (JPG/PNG/PDF) |
| `select` | Single-select dropdown |
| `multiselect` | Multi-select dropdown |
| `radio` | Radio buttons |
| `checkbox` | Checkboxes |
| `choice` | Single-select radio list (alias for radio) |
| `multi` | Multi-select checkbox list |

**Features:**
- Drag-and-drop reorder via SortableJS
- Field key auto-population from question label (yellow background, overridable)
- Disqualify toggle per option on choice-based types
- Conditional logic: show/hide based on a prior question's answer (operators: `equals`, `not_equals`, `contains`, `is_answered`)
- Multi-step mode: questions grouped by `step_number`; rendered as paginated steps
- Long question text supported (consent text): question label uses `<textarea>`, max 5000 characters
- Conditional logic re-evaluated on every step navigation

---

## Seeded Questionnaires

```bash
php artisan db:seed --class=IntakeQuestionnairesSeeder
```

| Questionnaire | Mode | Steps | Used For |
|---------------|------|-------|----------|
| Standard Intake 1 | single | 1 | Shared baseline — all programs (MWL, Anti-Aging, TRT) |
| MWL – Weight Loss | multi | 2 | Weight loss program (GLP-1, prescription image) |
| Anti-Aging | multi | 2 | Anti-aging program (Metformin, NAD+, Glutathione) |

---

## File Uploads

Files are stored via `FileUploadService` which:
1. Validates MIME type (JPG/PNG/PDF) and size (max 10 MB)
2. Generates a UUID filename and stores to `FILESYSTEM_DISK`
3. Creates a `PatientFile` (`files` table) record with `uuid`, `path`, `disk`, `mime_type`, `size`, `original_name`, `type`, `status`
4. Dispatches `ScanUploadedFileJob` to the `default` queue (ClamAV scan)

**Partner API file flow:**
```
POST /api/partner/files        → { file_token }
POST /api/partner/cases   (include file_token as answer to file-type question)
```

---

## Partner API — Quick Reference

```
POST   /api/partner/auth/token                    Obtain bearer token
GET    /api/partner/questionnaires/{uuid}         Discover question IDs
POST   /api/partner/files                         Upload file; get file_token
POST   /api/partner/cases                         Submit a new case
GET    /api/partner/cases                         List own cases
GET    /api/partner/cases/{uuid}                  Case detail
GET    /api/partner/cases/by-external-id/{id}     Lookup by partner reference
POST   /api/partner/cases/{uuid}/cancel           Cancel a case
POST   /api/partner/cases/{uuid}/hold             Toggle hold status
POST   /api/partner/cases/{uuid}/support          Escalate with a note
GET    /api/partner/cases/{uuid}/events           Audit trail
GET    /api/partner/cases/{uuid}/messages         List messages
POST   /api/partner/cases/{uuid}/messages         Send a message
GET    /api/partner/patients                      List own patients
GET    /api/partner/patients/{id}                 Patient detail
GET    /api/partner/patients/by-external-id/{id}  Lookup by partner patient ID
GET    /api/partner/offerings                     List own offerings
POST   /api/partner/offerings                     Create offering
PUT    /api/partner/offerings/{id}                Update offering
DELETE /api/partner/offerings/{id}                Delete offering
POST   /api/partner/webhooks                      Register webhook
PUT    /api/partner/webhooks/{id}                 Update webhook
DELETE /api/partner/webhooks/{id}                 Delete webhook
```

**MWL case submission requires two questionnaire_responses:**
```json
"questionnaire_responses": [
  { "questionnaire_id": "<standard-intake-1-uuid>", "answers": [...] },
  { "questionnaire_id": "<mwl-weight-loss-uuid>",   "answers": [...] }
]
```

The full annotated payload with live question IDs is at `/admin/guide/weightloss-api`.

---

## Webhooks

All webhooks signed with `X-Webhook-Signature: sha256=<hmac>`. Retried up to 5 times with exponential backoff.

| Event | Fired When |
|-------|-----------|
| `case_created` | Case created from form submission |
| `case_waiting` | Case enters waiting queue |
| `case_assigned_to_clinician` | Clinician assigned |
| `case_support` | Clinician escalates to support |
| `case_approved` | Clinician submits prescription |
| `case_processing` | Clinician clicks "Send to Pharmacy" |
| `case_completed` | Fires automatically in the same request as `case_processing` (auto-completion) |
| `case_cancelled` | Any cancellation |
| `clinical_note_added` | Clinician adds a note |
| `message_created` | Clinician sends a message |

Verify signature:
```php
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);
hash_equals($expected, $request->header('X-Webhook-Signature'));
```

---

## SLA System

Admin-configurable Service Level Agreement deadlines stored in the `settings` table:

| Setting | Default | Meaning |
|---------|---------|---------|
| `sla_pickup_hours` | 4h | Time from case creation to first assignment |
| `sla_review_hours` | 24h | Time from assignment to clinician action — the primary SLA shown on clinician dashboard |
| `sla_total_hours` | 48h | End-to-end turnaround from creation to completion |

Admin changes these at `/admin/settings`. Values are cached for 1 hour and invalidated immediately on save. The clinician dashboard reads `sla_review_hours` and computes per-case SLA progress bars in green (< 70%), amber (70–99%), and red (≥ 100% — breached). The SLA stat card on the dashboard turns green/amber/red based on breached and at-risk case counts.

---

## Key Architectural Notes

- **Auto-assignment**: `CaseAutoAssigner` selects highest-priority active, available clinician below their `max_daily_cases`. Fires on transition to `waiting`.
- **`skip_auto_assign` flag**: Admin manual assignment passes this so the auto-assigner doesn't race and double-assign.
- **File token flow**: Partners upload files before case creation; `file_token` UUID links the `PatientFile` to the case inside the `POST /api/partner/cases` DB transaction.
- **Two-pass `syncQuestions()`**: Pass 1 creates all questions; Pass 2 resolves self-referencing `depends_on_question_id` FK. All question IDs change on each questionnaire edit (delete + recreate).
- **Offerings approval**: Partner offerings start `pending`; admin approves/rejects. Pending count badge visible in admin sidebar.
- **Queue dependency**: Webhook delivery and virus scans are async. Production requires a running queue worker.
- **Passport 13**: Client IDs are ULIDs; `createClientCredentialsGrantClient()` takes only a name string (no `$userId`). Schema uses `owner_type`, `owner_id`, `grant_types` columns.
- **No Vite**: All assets via CDN. JS is vanilla, inline in Blade `@section('scripts')`.

---

## Artisan Reference

```bash
# Cache management
php artisan route:clear && php artisan view:clear && php artisan config:clear

# Migrations
php artisan migrate
php artisan migrate:fresh --seed     # WARNING: destroys all data

# Seed questionnaires only
php artisan db:seed --class=IntakeQuestionnairesSeeder

# Passport
php artisan passport:install         # first time setup
php artisan passport:keys            # regenerate keys (invalidates existing tokens)

# Queue
php artisan queue:work --queue=webhooks,default
php artisan queue:failed             # list failed jobs
php artisan queue:retry all          # retry all failed jobs

# Storage
php artisan storage:link             # create public/storage symlink
```
