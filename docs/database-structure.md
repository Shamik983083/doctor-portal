# Database Structure Reference

**Doctor Portal** · Laravel 12 / MySQL 8  
Last compiled: 2026-08-07

---

## Contents

1. [Overview](#1-overview)
2. [Domain Groups](#2-domain-groups)
3. [Table Reference](#3-table-reference)
4. [Pivot / Bridge Tables](#4-pivot--bridge-tables)
5. [Laravel / Package Tables](#5-laravel--package-tables)
6. [Soft Deletes](#6-soft-deletes)
7. [Enum-Like Columns](#7-enum-like-columns)
8. [JSON Columns & Shapes](#8-json-columns--shapes)
9. [Relationship Map](#9-relationship-map)
10. [Indexing Rules](#10-indexing-rules)
11. [Design Notes](#11-design-notes)

---

## 1. Overview

| Metric | Value |
|--------|-------|
| Total application tables | ~50 |
| Laravel framework tables | 9 (cache, sessions, jobs, oauth_*, notifications) |
| Package tables (Spatie RBAC) | 5 (permissions, roles, pivot tables) |
| Soft-deleted tables | 11 |
| JSON columns | ~30+ across 16 tables |
| True SQL ENUM columns | 1 (`questionnaires.mode`) |
| All other status columns | varchar enforced at application layer |

**Authentication:** Laravel Passport (OAuth2, `client_credentials` grant for partner API).  
**Authorization:** Spatie Laravel Permission (`admin`, `super_admin`, `clinician` roles).  
**Audit trail:** `audit_logs` table (immutable, append-only).

---

## 2. Domain Groups

```
Identity & Auth
  users · password_reset_tokens · sessions
  oauth_auth_codes · oauth_access_tokens · oauth_refresh_tokens
  oauth_clients · oauth_device_codes
  permissions · roles · model_has_roles · model_has_permissions · role_has_permissions

Partners & Configuration
  partners · webhooks · webhook_deliveries
  partner_ehr_settings · settings

Clinicians
  clinicians · admin_clinician (pivot)
  sla_policies · routing_policies · routing_exceptions
  case_pull_requests · state_visit_requirements

Patients
  patients · patient_preferred_pharmacies (pivot)
  patient_subscriptions · vouchers
  files (model: PatientFile)

Offerings / Catalog
  offerings · offering_categories
  offering_questionnaire (pivot) · offering_partner (pivot)
  partner_product_plans

Questionnaires
  questionnaires · questionnaire_questions
  questionnaire_responses · questionnaire_answers

Cases (core workflow)
  cases (model: PatientCase)
  case_offerings · case_questions
  case_diseases (pivot) · case_tags (pivot)
  case_events · case_pause_intervals
  case_pull_requests · routing_exceptions

Prescriptions
  case_prescriptions · case_prescription_medications
  case_prescription_diagnoses
  prescription_documents · pharmacy_dispatches
  prescriptions (legacy DoseSpot)

Supporting
  clinical_notes · orders · messages
  diseases · tags · patient_tags (pivot)
  pharmacies
  ai_instruction_sets · ai_instruction_examples
  ehr_records
  triage_rules (currently empty)
  notifications · audit_logs
```

---

## 3. Table Reference

### `users`

**Migrations:** `0001_01_01_000000` · `2024_01_01_000023` · `2026_07_21_030000`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL |
| name | varchar(255) | NO | | |
| email | varchar(255) | NO | | |
| email_verified_at | timestamp | YES | NULL | |
| password | varchar(255) | NO | | cast: hashed |
| is_active | boolean | NO | true | |
| remember_token | varchar(100) | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(email) · INDEX(partner_id)  
**Soft deletes:** NO  
**Model relationships:** `hasOne(Clinician)` · `belongsTo(Partner)` · `belongsToMany(Clinician, admin_clinician)` [managedClinicians]  
**Roles:** `HasRoles` trait (Spatie) · `HasApiTokens` (Passport)

---

### `partners`

**Migrations:** `2024_01_01_000001` · `2024_01_01_000021` · `2026_07_01_132701` · `2026_08_05_000001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| name | varchar(255) | NO | | |
| slug | varchar(255) | NO | | UNIQUE |
| email | varchar(255) | NO | | UNIQUE |
| phone | varchar(255) | YES | NULL | |
| website | varchar(255) | YES | NULL | |
| logo | varchar(255) | YES | NULL | |
| description | text | YES | NULL | |
| status | varchar(255) | NO | 'active' | active \| suspended \| inactive |
| webhook_secret | varchar(255) | YES | NULL | |
| oauth_client_id | varchar(100) | YES | NULL | links to oauth_clients |
| client_id | varchar(255) | YES | NULL | |
| client_secret | varchar(255) | YES | NULL | hidden in model |
| settings | json | YES | NULL | arbitrary config blob |
| collaborating_clinician_id | bigint unsigned | YES | NULL | FK → clinicians(id) SET NULL |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · UNIQUE(slug) · UNIQUE(email) · INDEX(collaborating_clinician_id)  
**Soft deletes:** YES

**Model relationships:**
- `belongsTo(Clinician, collaborating_clinician_id)` [collaboratingClinician]
- `hasMany(User)` · `hasMany(Patient)` · `hasMany(PatientCase)` · `hasMany(Offering)` [ownership]
- `belongsToMany(Offering, offering_partner)` [accessibleOfferings] — pivot: `sig_override`, `is_active`; filtered by `is_active=true`
- `hasMany(Webhook)` · `hasMany(Voucher)` · `hasMany(PartnerEhrSetting)`
- `hasOne(PartnerEhrSetting)` [healthieSettings] where provider='healthie'
- `hasMany(PatientSubscription)` · `hasMany(Order)` · `hasMany(Tag)` · `hasMany(Questionnaire)` · `hasMany(PartnerProductPlan)`

---

### `clinicians`

**Migrations:** `2024_01_01_000002` + 5 alter migrations through `2026_07_26`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| user_id | bigint unsigned | NO | | FK → users(id) CASCADE |
| npi | varchar(255) | YES | NULL | UNIQUE |
| license_number | varchar(255) | YES | NULL | |
| license_state | varchar(255) | YES | NULL | |
| specialty | varchar(255) | YES | NULL | |
| credentials | varchar(255) | YES | NULL | MD \| DO \| NP \| PA |
| status | varchar(255) | NO | 'active' | active \| inactive \| suspended |
| is_available | boolean | NO | true | |
| max_daily_cases | integer | NO | 20 | |
| priority | int unsigned | NO | 0 | routing priority (lower = higher priority) |
| accepting_new_cases | boolean | NO | true | |
| max_daily_new_cases | smallint unsigned | YES | NULL | capacity cap |
| max_open_cases | smallint unsigned | YES | NULL | capacity cap |
| daily_refill_alert_threshold | smallint unsigned | YES | NULL | |
| accepts_async_visits | boolean | NO | true | |
| accepts_sync_visits | boolean | NO | false | |
| scheduling_link | varchar(500) | YES | NULL | video-visit booking URL |
| cases_last_viewed_at | timestamp | YES | NULL | |
| pool_cooldown_until | timestamp | YES | NULL | routing pool cooldown |
| licensed_states | json | YES | NULL | `[{state:"CA", ...}]` |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · UNIQUE(npi)  
**Soft deletes:** YES

**Model relationships:**
- `belongsTo(User)` · `hasMany(PatientCase)` · `hasMany(ClinicalNote)` · `hasMany(Message)`
- `belongsToMany(OfferingCategory, clinician_offering_category)` [acceptedCategories]
- `belongsToMany(User, admin_clinician)` [admins]

**Scopes:** `scopeVisibleTo(?User $user)` — filters by admin's visible clinician IDs

---

### `patients`

**Migrations:** `2024_01_01_000004` + 3 alter migrations

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| partner_id | bigint unsigned | NO | | FK → partners(id) CASCADE |
| collaborating_clinician_id | bigint unsigned | YES | NULL | FK → clinicians(id) SET NULL |
| user_id | bigint unsigned | YES | NULL | FK → users(id) SET NULL |
| external_id | varchar(255) | YES | NULL | partner's patient ID |
| first_name / last_name | varchar(255) | NO | | |
| email | varchar(255) | NO | | |
| phone | varchar(255) | YES | NULL | |
| date_of_birth | date | YES | NULL | |
| age | smallint unsigned | YES | NULL | stored; also computed from DOB |
| height | decimal(5,2) | YES | NULL | inches |
| weight | decimal(5,2) | YES | NULL | lbs |
| bmi | decimal(4,2) | YES | NULL | |
| gender | varchar(255) | YES | NULL | male \| female \| other |
| address / address2 / city / state / zip | varchar | YES | NULL | |
| country | varchar(2) | NO | 'US' | |
| status | varchar(255) | NO | 'active' | active \| inactive \| deleted |
| dosespot_patient_id | varchar(255) | YES | NULL | |
| email_opt_in / sms_opt_in | boolean | NO | true | |
| id_verified_status | varchar(255) | YES | NULL | pending \| verified \| failed |
| id_verified_at | timestamp | YES | NULL | |
| settings | json | YES | NULL | arbitrary blob |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · UNIQUE(partner_id, external_id) · INDEX(external_id) · INDEX(partner_id) · INDEX(collaborating_clinician_id)  
**Soft deletes:** YES

**Model relationships:**
- `belongsTo(Partner)` · `belongsTo(User)` · `hasMany(PatientCase)` · `hasMany(PatientSubscription)` · `hasMany(Voucher)` · `hasMany(Message)` · `hasMany(Order)` · `hasMany(PatientFile)` [files]
- `belongsTo(Clinician, collaborating_clinician_id)` [collaboratingClinician]
- `belongsToMany(Pharmacy, patient_preferred_pharmacies)` — pivot: `is_primary`
- `belongsToMany(Tag, patient_tags)` — pivot: `notes`

---

### `offerings`

**Migrations:** `2024_01_01_000005` + 8 alter migrations through `2026_07_26`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL (was CASCADE; made nullable 2026-07-26) |
| category_id | bigint unsigned | YES | NULL | FK → offering_categories(id) SET NULL |
| name | varchar(255) | NO | | |
| internal_name | varchar(255) | YES | NULL | admin-only label |
| type | varchar(255) | NO | 'medication' | medication \| compound \| supply |
| description | text | YES | NULL | |
| sku | varchar(255) | YES | NULL | |
| price | decimal(10,2) | YES | NULL | |
| dosespot_medication_id | varchar(255) | YES | NULL | legacy, unused in dispatch |
| boothwyn_compound_id | varchar(255) | YES | NULL | legacy, unused in dispatch |
| pharmacy_type | varchar(255) | YES | NULL | boothwyn \| curexa \| custom |
| pharmacy_id | bigint unsigned | YES | NULL | FK → pharmacies(id) SET NULL |
| pharmacy_name | varchar(255) | YES | NULL | free-text legacy, superseded by pharmacy_id |
| pharmacy_notes | text | YES | NULL | legacy |
| compound_formula | text | YES | NULL | |
| refills | smallint unsigned | YES | NULL | |
| quantity | decimal(8,2) | YES | NULL | |
| days_supply | smallint unsigned | YES | NULL | |
| dispense_unit | varchar(255) | YES | NULL | |
| dispense_units | json | YES | NULL | array of unit options |
| days_until_dispense | smallint unsigned | YES | NULL | |
| directions | text | YES | NULL | patient-facing copy |
| sig | text | YES | NULL | global default SIG |
| available_states | json | YES | NULL | `["CA","TX",...]` |
| video_required_states | json | YES | NULL | states requiring video visit |
| images | json | YES | NULL | array of image paths |
| faqs | json | YES | NULL | array of FAQ objects |
| is_active | boolean | NO | true | |
| is_controlled_substance | boolean | NO | false | |
| approval_status | varchar(255) | NO | 'pending' | pending \| approved \| rejected |
| approved_by | bigint unsigned | YES | NULL | FK → users(id) SET NULL |
| approved_at | timestamp | YES | NULL | |
| rejection_note | text | YES | NULL | |
| metadata | json | YES | NULL | unstructured |
| levels | json | YES | NULL | dose-level objects (see §8) |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(partner_id) · INDEX(category_id) · INDEX(pharmacy_id)  
**Soft deletes:** YES

**Key model methods:**
- `effectiveSig(?Partner $partner)` — returns `offering_partner.sig_override` or falls back to `offerings.sig`
- `isAvailableInState(string $state)` · `isVideoRequiredInState(string $state)`
- Scope: `scopeApproved($query)`

---

### `offering_categories`

**Migrations:** `2026_06_29_000005` · `2026_07_27_000001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| name | varchar(150) | NO | | |
| description | text | YES | NULL | |
| is_active | boolean | NO | true | |
| check_in_questionnaire_id | bigint unsigned | YES | NULL | FK → questionnaires(id) SET NULL |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(check_in_questionnaire_id)  
**Soft deletes:** NO

---

### `partner_product_plans`

**Migrations:** `2026_07_27_100000` · `2026_07_27_100002` (dropped unique index)

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| partner_id | bigint unsigned | NO | | FK → partners(id) CASCADE |
| product_key | varchar(255) | NO | | partner's product identifier |
| offering_id | bigint unsigned | NO | | FK → offerings(id) CASCADE |
| month_frequency | tinyint unsigned | NO | | 1 \| 3 \| 6 \| 12 |
| label | varchar(255) | YES | NULL | display label override |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(partner_id, product_key)  
**Uniqueness:** Enforced at application layer on (partner_id, product_key, month_frequency, offering_id). The DB unique index was dropped in migration `_100002`.  
**Soft deletes:** NO

---

### `questionnaires`

**Migrations:** `2024_01_01_000006` + 3 alter migrations

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL |
| name | varchar(255) | NO | | |
| description | text | YES | NULL | |
| is_active | boolean | NO | true | |
| mode | enum('single','multi') | NO | 'single' | only true ENUM in schema |
| purpose | varchar(30) | NO | 'clinical' | clinical \| standard_intake |
| linked_questionnaire_id | bigint unsigned | YES | NULL | FK → questionnaires(id) SET NULL; self-referential |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(partner_id) · INDEX(linked_questionnaire_id)  
**Soft deletes:** YES

> **`purpose = 'standard_intake'`** marks sub-forms that are embedded in other questionnaires via `linked_questionnaire_id`. They cannot be directly attached to offerings.

---

### `questionnaire_questions`

**Migrations:** `2024_01_01_000006` + 5 alter migrations

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| questionnaire_id | bigint unsigned | NO | | FK → questionnaires(id) CASCADE |
| question | text | NO | | |
| key | varchar(255) | YES | NULL | machine key |
| slug | varchar(120) | YES | NULL | auto-generated from key or question text |
| type | varchar(255) | NO | 'text' | text \| choice \| multi \| boolean \| date |
| placeholder | varchar(255) | YES | NULL | |
| options | json | YES | NULL | choices for type=choice/multi |
| is_required | boolean | NO | false | |
| is_readonly | boolean | NO | false | |
| is_active | boolean | NO | true | |
| sort_order | integer | NO | 0 | |
| step_number | tinyint unsigned | NO | 1 | for multi-step (mode=multi) |
| depends_on_question_id | bigint unsigned | YES | NULL | FK → questionnaire_questions(id) SET NULL; self-referential |
| depends_on_operator | varchar(20) | YES | NULL | gte \| lte \| gt \| lt \| contains |
| depends_on_value | varchar(500) | YES | NULL | threshold value for conditional display |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(questionnaire_id) · INDEX(depends_on_question_id)  
**Soft deletes:** NO

---

### `questionnaire_responses`

**Migration:** `2026_06_29_000001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| token | varchar(64) | NO | | UNIQUE; used as public session key |
| questionnaire_id | bigint unsigned | NO | | FK → questionnaires(id) CASCADE |
| patient_id | bigint unsigned | YES | NULL | FK → patients(id) SET NULL |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL |
| case_id | bigint unsigned | YES | NULL | FK → cases(id) SET NULL |
| external_patient_id | varchar(255) | YES | NULL | |
| is_disqualified | boolean | NO | false | |
| disqualified_on | varchar(255) | YES | NULL | slug of disqualifying question |
| completed_at | timestamp | YES | NULL | null = in progress |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(token) · INDEX(questionnaire_id) · INDEX(patient_id) · INDEX(partner_id) · INDEX(case_id)  
**Soft deletes:** NO

---

### `questionnaire_answers`

**Migrations:** `2026_06_29_000002` · `2026_07_05_000001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| response_id | bigint unsigned | NO | | FK → questionnaire_responses(id) CASCADE |
| question_id | bigint unsigned | NO | | FK → questionnaire_questions(id) CASCADE |
| question_text | text | NO | | frozen snapshot at submission time |
| answer | text | YES | NULL | |
| is_disqualified | boolean | NO | false | |
| created_at | timestamp | NO | CURRENT_TIMESTAMP | no updated_at; `$timestamps = false` |

**Indexes:** PK(id) · INDEX(response_id) · INDEX(question_id)  
**Soft deletes:** NO

---

### `cases` (model: `PatientCase`)

**Migrations:** `2024_01_01_000007` + 7 alter migrations

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| partner_id | bigint unsigned | NO | | FK → partners(id) CASCADE |
| patient_id | bigint unsigned | NO | | FK → patients(id) CASCADE |
| clinician_id | bigint unsigned | YES | NULL | FK → clinicians(id) SET NULL |
| external_id | varchar(255) | YES | NULL | partner's case ID |
| status | varchar(255) | NO | 'created' | see enum table §7 |
| hold_status | boolean | NO | false | |
| triage | varchar(12) | YES | NULL | green \| yellow \| red |
| triage_reasons | json | YES | NULL | array of reason codes |
| triage_ruleset | varchar(32) | YES | NULL | ruleset version tag |
| triaged_at | timestamp | YES | NULL | |
| is_chargeable | boolean | NO | true | |
| charge_amount | decimal(10,2) | YES | NULL | |
| support_note | text | YES | NULL | |
| escalation_target | varchar(32) | YES | NULL | support \| doctor_admin \| client_response |
| escalation_reason | text | YES | NULL | |
| support_at | timestamp | YES | NULL | |
| completion_deadline_at | timestamp | YES | NULL | |
| deadline_warned | boolean | NO | false | |
| cancellation_reason | text | YES | NULL | |
| patient_state | varchar(2) | YES | NULL | 2-letter state code at submission |
| visit_type | varchar(100) | YES | NULL | 'async' \| 'sync' or storefront-supplied |
| is_refill | boolean | NO | false | |
| assigned_at | timestamp | YES | NULL | |
| approved_at | timestamp | YES | NULL | |
| processing_at | timestamp | YES | NULL | |
| completed_at | timestamp | YES | NULL | |
| cancelled_at | timestamp | YES | NULL | |
| metadata | json | YES | NULL | unstructured |
| clinical_intake | json | YES | NULL | structured storefront data (see §8) |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:**
- PK(id) · UNIQUE(uuid) · UNIQUE(partner_id, external_id)
- INDEX(external_id) · INDEX(status) · INDEX(status, triage)
- INDEX(is_refill, status) · INDEX(clinician_id) · INDEX(patient_id)

**Soft deletes:** YES

**Model relationships:**
- `belongsTo(Partner)` · `belongsTo(Patient)` · `belongsTo(Clinician)`
- `hasMany(CaseOffering)` · `belongsToMany(Offering, case_offerings)` — pivot: status, quantity, price, dosage, frequency, refills
- `hasMany(CaseQuestion)` · `hasMany(ClinicalNote)` · `hasMany(Order)` · `hasMany(Message)` · `hasMany(PatientFile)` [files]
- `belongsToMany(Disease, case_diseases)` — pivot: is_primary
- `belongsToMany(Tag, case_tags)` — pivot: notes
- `hasMany(CaseEvent)` · `hasMany(QuestionnaireResponse)` · `hasMany(CasePrescription)`
- `hasOne(CasePrescription)` [casePrescription] — latest confirmed, via `latestOfMany('prescribed_at')`
- `hasMany(RoutingException)` · `hasMany(CasePauseInterval)`

**Scopes:** `scopeVisibleTo(?User $user)` · `scopeTriage(string $level)` · `scopeRefills()` · `scopeFirstVisits()`

---

### `case_offerings`

**Migrations:** `2024_01_01_000008` · `2026_07_27_100001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| case_id | bigint unsigned | NO | | FK → cases(id) CASCADE |
| offering_id | bigint unsigned | NO | | FK → offerings(id) CASCADE |
| status | varchar(255) | NO | 'pending' | pending \| submitted \| approved \| declined \| cancelled |
| quantity | integer | NO | 1 | |
| month_frequency | tinyint unsigned | YES | NULL | snapshotted from product plan |
| product_key | varchar(255) | YES | NULL | snapshotted from product plan |
| price | decimal(10,2) | YES | NULL | |
| dosage | varchar(255) | YES | NULL | |
| frequency | varchar(255) | YES | NULL | |
| refills | integer | NO | 0 | |
| clinician_notes | text | YES | NULL | |
| metadata | json | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(case_id) · INDEX(offering_id)  
**Soft deletes:** NO

> `product_key` and `month_frequency` are **snapshotted at case creation** — plan changes after the fact do not affect existing cases.

---

### `case_prescriptions`

**Migrations:** `2026_06_29_132017` · `2026_07_26_000009`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| case_id | bigint unsigned | NO | | FK → cases(id) CASCADE |
| clinician_id | bigint unsigned | NO | | FK → clinicians(id) CASCADE |
| diagnoses | text | NO | | legacy free-text; structured data in `case_prescription_diagnoses` |
| directions | text | YES | NULL | legacy; new directions go to `clinical_notes` type='internal' |
| medical_necessity | text | YES | NULL | |
| prescribed_at | timestamp | NO | CURRENT_TIMESTAMP | |
| review_status | varchar(20) | NO | 'confirmed' | draft \| confirmed |
| charting_note | text | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(case_id) · INDEX(clinician_id)  
**Soft deletes:** NO

---

### `case_prescription_medications`

**Migrations:** `2026_06_29_132018` + 2 alter migrations

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| case_prescription_id | bigint unsigned | NO | | FK → case_prescriptions(id) CASCADE |
| offering_id | bigint unsigned | YES | NULL | FK → offerings(id) SET NULL |
| name | varchar(255) | NO | | |
| compound_formula | text | YES | NULL | |
| dosing | json | YES | NULL | titration ladder (see §8) |
| refills | smallint unsigned | YES | NULL | |
| quantity | decimal(8,2) | YES | NULL | |
| days_supply | smallint unsigned | YES | NULL | |
| dispense_unit | varchar(255) | YES | NULL | |
| days_until_dispense | smallint unsigned | YES | NULL | |
| sig | text | YES | NULL | |

**No timestamps** (`$timestamps = false`)  
**Indexes:** PK(id) · INDEX(case_prescription_id) · INDEX(offering_id)  
**Soft deletes:** NO

---

### `case_prescription_diagnoses`

**Migration:** `2026_07_26_000008`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| case_prescription_id | bigint unsigned | NO | | FK → case_prescriptions(id) CASCADE |
| icd_code | varchar(30) | NO | | |
| description | varchar(255) | NO | | |
| sort_order | smallint unsigned | NO | 0 | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(case_prescription_id)  
**Soft deletes:** NO

---

### `prescription_documents` *(immutable)*

**Migration:** `2026_07_15_004046`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| case_prescription_id | bigint unsigned | NO | | FK → case_prescriptions(id) CASCADE |
| case_id / patient_id / partner_id | bigint unsigned | NO | | FK → respective tables CASCADE |
| clinician_id | bigint unsigned | YES | NULL | FK → clinicians(id) SET NULL |
| snapshot | json | NO | | locked order payload at PDF render time |
| document_path | varchar(255) | NO | | path on `documents` disk |
| content_hash | varchar(64) | NO | | sha256 of PDF bytes |
| attestation | text | NO | | |
| attested_at / locked_at | timestamp | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(case_id, created_at) · INDEX(case_prescription_id) · INDEX(patient_id) · INDEX(partner_id) · INDEX(clinician_id)  
**Soft deletes:** NO  
**Immutability:** The model throws `RuntimeException` on any `update()` or `delete()` call.

---

### `pharmacy_dispatches`

**Migration:** `2026_07_15_004046`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| prescription_document_id | bigint unsigned | NO | | FK → prescription_documents(id) CASCADE |
| case_id / partner_id | bigint unsigned | NO | | FK → respective tables CASCADE |
| pharmacy_id | bigint unsigned | YES | NULL | FK → pharmacies(id) SET NULL |
| adapter | varchar(255) | NO | | mock \| lifefile \| … |
| status | varchar(255) | NO | 'pending' | disabled \| pending \| sending \| sent \| failed \| dead_letter |
| attempts | tinyint unsigned | NO | 0 | |
| max_attempts | tinyint unsigned | NO | 5 | |
| payload | json | YES | NULL | constructed POST /order body |
| response_code | smallint unsigned | YES | NULL | |
| response_body | text | YES | NULL | |
| external_ref | varchar(255) | YES | NULL | pharmacy order ID on success |
| last_attempted_at / next_retry_at / dispatched_at | timestamp | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(status, next_retry_at) · INDEX(case_id, created_at)  
**Soft deletes:** NO

---

### `clinical_notes`

**Migration:** `2024_01_01_000011`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| case_id | bigint unsigned | NO | | FK → cases(id) CASCADE |
| clinician_id | bigint unsigned | NO | | FK → clinicians(id) CASCADE |
| type | varchar(255) | NO | 'general' | general \| soap \| progress \| approval \| cancellation \| internal |
| note | text | NO | | |
| is_private | boolean | NO | false | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(case_id) · INDEX(clinician_id)  
**Soft deletes:** NO

> Type `'internal'` was added when prescription directions were migrated from `case_prescriptions.directions` to this table (migration `2026_07_26_000007`).

---

### `case_events`

**Migrations:** `2024_01_01_000022` · `2026_07_24_000300`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| case_id | bigint unsigned | NO | | FK → cases(id) CASCADE |
| event_type | varchar(255) | NO | | e.g. 'case.assigned', 'case.approved' |
| actor_type | varchar(255) | YES | NULL | user \| system \| clinician \| partner |
| actor_id | bigint unsigned | YES | NULL | polymorphic, no DB FK constraint |
| actor_service | varchar(255) | YES | NULL | e.g. 'karen' for automated service |
| payload | json | YES | NULL | event-specific data |
| notes | text | YES | NULL | |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · INDEX(case_id, event_type)  
**Soft deletes:** NO

---

### `orders`

**Migration:** `2024_01_01_000012`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| case_id / patient_id / partner_id | bigint unsigned | NO | | FK → respective tables CASCADE |
| pharmacy_id | bigint unsigned | YES | NULL | FK → pharmacies(id) SET NULL |
| status | varchar(255) | NO | 'pending' | pending \| submitted \| processing \| shipped \| delivered \| cancelled \| returned |
| tracking_number / tracking_carrier | varchar(255) | YES | NULL | |
| payment_status | varchar(255) | NO | 'pending' | pending \| paid \| refunded \| failed |
| amount | decimal(10,2) | YES | NULL | |
| notes | text | YES | NULL | |
| fulfillment_data | json | YES | NULL | pharmacy response blob |
| shipped_at / delivered_at | timestamp | YES | NULL | |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(status) · INDEX(case_id) · INDEX(patient_id) · INDEX(partner_id) · INDEX(pharmacy_id)  
**Soft deletes:** YES

---

### `messages`

**Migrations:** `2024_01_01_000017` · `2026_07_28_000001`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| case_id | bigint unsigned | YES | NULL | FK → cases(id) SET NULL |
| patient_id | bigint unsigned | YES | NULL | FK → patients(id) SET NULL |
| clinician_id | bigint unsigned | YES | NULL | FK → clinicians(id) SET NULL |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL |
| user_id | bigint unsigned | YES | NULL | FK → users(id) SET NULL |
| direction | varchar(255) | NO | 'outbound' | inbound \| outbound |
| channel | varchar(255) | NO | 'portal' | portal \| sms \| email |
| sender_type | varchar(255) | YES | NULL | patient \| clinician \| system |
| body | text | NO | | |
| is_read | boolean | NO | false | |
| read_at | timestamp | YES | NULL | |
| attachments | json | YES | NULL | array of attachment refs |
| created_at / updated_at | timestamp | YES | NULL | |

**Indexes:** PK(id) · UNIQUE(uuid) · INDEX(case_id, created_at) · INDEX(patient_id) · INDEX(clinician_id) · INDEX(partner_id) · INDEX(user_id)  
**Soft deletes:** NO

---

### `pharmacies`

**Migration:** `2024_01_01_000003`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| uuid | varchar(255) | NO | | UNIQUE |
| name / npi | varchar | YES/NO | | |
| type | varchar(255) | NO | 'custom' | boothwyn \| curexa \| custom |
| address / city / state / zip | varchar | YES | NULL | |
| phone / fax / email | varchar | YES | NULL | |
| is_active | boolean | NO | true | |
| metadata | json | YES | NULL | |
| created_at / updated_at / deleted_at | timestamp | YES | NULL | |

**Soft deletes:** YES

---

### `diseases`

**Migration:** `2024_01_01_000010`

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| id | bigint unsigned | NO | auto |
| icd_code | varchar(255) | NO | | UNIQUE |
| name | varchar(255) | NO | | |
| description | text | YES | NULL | |

**Soft deletes:** NO  
Many-to-many with `cases` via `case_diseases` pivot (pivot column: `is_primary`).

---

### `tags`

**Migration:** `2024_01_01_000018`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint unsigned | NO | auto | PK |
| name | varchar(255) | NO | | |
| slug | varchar(255) | NO | | UNIQUE |
| partner_id | bigint unsigned | YES | NULL | FK → partners(id) SET NULL |
| type | varchar(255) | NO | 'global' | global \| partner \| case \| patient |
| color | varchar(255) | NO | '#6c757d' | hex color |
| description | text | YES | NULL | |

**Soft deletes:** NO  
Many-to-many with `cases` via `case_tags` and with `patients` via `patient_tags` (both pivot columns include `notes`).

---

### `webhooks` & `webhook_deliveries`

**`webhooks`** — one record per partner endpoint subscription.  
**`webhook_deliveries`** — one record per outbound attempt (retry queue).

**Key columns on `webhook_deliveries`:**  
`status` (pending \| delivered \| failed \| retrying) · `attempts` · `max_attempts` (5) · `next_retry_at` · `response_code` · `response_body` (utf8mb4)

**Indexes on `webhook_deliveries`:** INDEX(status, next_retry_at) — used by the retry job.

---

### `vouchers`

**Migration:** `2024_01_01_000014`

> Note: `pharmacy_id` on this table is a **varchar**, not a proper FK. It was a free-text field and has no referential constraint.

**Soft deletes:** YES

---

### `files` (model: `PatientFile`)

**Migration:** `2024_01_01_000019`

`type` values: lab_result \| id_doc \| consent \| medical_necessity \| intake \| other  
`status` values: uploaded \| processing \| processed \| failed  
`disk` values: local (default), or any configured Laravel disk

**Soft deletes:** YES

---

### `settings`

**Migrations:** `2026_07_07_134934` · `2026_07_30_135932`

Key-value store with grouping and type metadata. Cached with 1-hour TTL.

| Seeded key | Group | Default |
|------------|-------|---------|
| sla_pickup_hours | sla | 4 |
| sla_review_hours | sla | 24 |
| sla_total_hours | sla | 48 |

---

### `routing_policies`

**Migration:** `2026_07_21_000100`

Stores versioned routing configurations. Only one row has `status = 'ACTIVE'` at a time. The `config` JSON contains all routing parameters (see §8 for shape).

`mode` values: PRIORITY \| ROUND_ROBIN \| WEIGHTED \| INTELLIGENT \| PROVIDER_POOL

---

### `triage_rules`

**Migration:** `2026_07_16_000001`

> **Currently empty and unused.** The table was seeded with 25 rules in its creation migration, then truncated by `2026_07_24_000001`. Triage v2 derives disqualifiers from `questionnaire_questions.options` (the `is_disqualify` flag on option objects), not from this table.

---

### `ai_instruction_sets` & `ai_instruction_examples`

AI prompt instruction sets for three contexts: `clinical_note` \| `patient_message` \| `storefront_message`.  
`ai_instruction_examples` stores few-shot examples (situation, good_output, bad_output) for each set.

---

### `ehr_records`

Outbound push records to EHR systems (Healthie). One record per `(case_id, clinical_note_id)` pair (UNIQUE constraint). Retry-capable via `status` and `attempts`.

---

### `partner_ehr_settings`

Per-partner EHR configuration. `api_key` is stored **encrypted** (Laravel `encrypted` cast). UNIQUE on `(partner_id, provider)`.

---

### `sla_policies`

Per-admin-user SLA capacity rules. Applied to clinicians managed by that admin. `forClinician()` static method merges and returns the strictest applicable policy.

---

### `case_pull_requests`

When a clinician pulls new cases, a record is created. If SLA or pool rules require admin approval, `status = 'PENDING_APPROVAL'` and an admin must decide. `granted_case_ids` holds the array of case IDs transferred.

---

### `routing_exceptions`

Created when the routing engine gives up (no eligible provider found). Tracks `occurrences`, `first_seen_at`/`last_seen_at`. Resolved when a super admin manually assigns.

---

### `case_pause_intervals`

Records each time a case's hold clock is paused. `resumed_at = NULL` means currently paused. Used by SLA elapsed-time calculations. No dedicated Eloquent model.

---

### `state_visit_requirements`

Admin-configurable rules for which states require a synchronous video visit before prescribing. Scoped to ALL, a specific category, or a specific offering. Supports date ranges via `effective_from` / `effective_to`.

---

### `audit_logs`

Immutable append-only audit trail. `$timestamps = false`; `created_at` is the only timestamp and is set to `CURRENT_TIMESTAMP` by the DB. `diff` stores only changed fields for `updated` events, or full attributes for `created`.

---

### `prescriptions` *(legacy)*

**Migration:** `2024_01_01_000013`  
Original DoseSpot-era prescription table. Kept for historical data. **Not used in new prescribe flow.** New prescriptions use `case_prescriptions` + `case_prescription_medications`.

---

### `case_questions`

Snapshot of questionnaire answers at the time the case was submitted. `questionnaire_question_id` is nullable (SET NULL) so the snapshot survives question deletion.

---

## 4. Pivot / Bridge Tables

| Table | FKs | Extra pivot columns |
|-------|-----|---------------------|
| `offering_questionnaire` | offerings ↔ questionnaires | `is_required` (bool, default true), `sort_order` (tinyint, default 0) |
| `offering_partner` | offerings ↔ partners | `sig_override` (text, nullable), `is_active` (bool, default true) |
| `case_offerings` | cases ↔ offerings | `status`, `quantity`, `price`, `dosage`, `frequency`, `refills`, `month_frequency`, `product_key`, `clinician_notes`, `metadata` (has its own PK — not a pure pivot) |
| `case_diseases` | cases ↔ diseases | `is_primary` (bool, default false) |
| `case_tags` | cases ↔ tags | `notes` (text, nullable) |
| `patient_tags` | patients ↔ tags | `notes` (text, nullable) |
| `patient_preferred_pharmacies` | patients ↔ pharmacies | `is_primary` (bool, default false) |
| `admin_clinician` | users ↔ clinicians | none |
| `clinician_offering_category` | clinicians ↔ offering_categories | none |

---

## 5. Laravel / Package Tables

| Table | Purpose |
|-------|---------|
| `password_reset_tokens` | Password reset links |
| `sessions` | Database session driver storage |
| `cache` / `cache_locks` | Laravel cache driver |
| `jobs` / `job_batches` / `failed_jobs` | Laravel queue |
| `oauth_auth_codes` | Passport auth codes |
| `oauth_access_tokens` | Passport access tokens |
| `oauth_refresh_tokens` | Passport refresh tokens |
| `oauth_clients` | Passport OAuth client registry |
| `oauth_device_codes` | Passport device flow |
| `notifications` | Laravel polymorphic notifications |
| `permissions` / `roles` | Spatie RBAC |
| `model_has_permissions` / `model_has_roles` / `role_has_permissions` | Spatie RBAC pivots |

**Roles in use:** `super_admin` · `admin` · `clinician`

---

## 6. Soft Deletes

The following tables use `deleted_at` (SoftDeletes trait). Queries against these tables **must use Eloquent** or manually scope to `WHERE deleted_at IS NULL`, otherwise deleted records are included:

| Table | Model |
|-------|-------|
| partners | Partner |
| clinicians | Clinician |
| pharmacies | Pharmacy |
| patients | Patient |
| offerings | Offering |
| questionnaires | Questionnaire |
| orders | Order |
| vouchers | Voucher |
| patient_subscriptions | PatientSubscription |
| webhooks | Webhook |
| files | PatientFile |

> `cases` also has `deleted_at` (soft delete). Do not hard-delete cases.

---

## 7. Enum-Like Columns

### True SQL ENUM

| Table.Column | Values |
|-------------|--------|
| `questionnaires.mode` | `single` \| `multi` |

### String columns with fixed value sets

| Table.Column | Allowed values |
|-------------|----------------|
| `partners.status` | active · suspended · inactive |
| `clinicians.status` | active · inactive · suspended |
| `clinicians.credentials` | MD · DO · NP · PA |
| `pharmacies.type` | boothwyn · curexa · custom |
| `patients.status` | active · inactive · deleted |
| `patients.gender` | male · female · other |
| `patients.id_verified_status` | pending · verified · failed |
| `offerings.type` | medication · compound · supply |
| `offerings.pharmacy_type` | boothwyn · curexa · custom |
| `offerings.approval_status` | pending · approved · rejected |
| `questionnaires.purpose` | clinical · standard_intake |
| `questionnaire_questions.type` | text · choice · multi · boolean · date |
| **`cases.status`** | **created · waiting · support · assigned · approved · processing · completed · cancelled** |
| `cases.triage` | green · yellow · red *(nullable = unclassified)* |
| `cases.escalation_target` | support · doctor_admin · client_response *(nullable)* |
| `case_offerings.status` | pending · submitted · approved · declined · cancelled |
| `clinical_notes.type` | general · soap · progress · approval · cancellation · internal |
| `orders.status` | pending · submitted · processing · shipped · delivered · cancelled · returned |
| `orders.payment_status` | pending · paid · refunded · failed |
| `prescriptions.status` | pending · sent · approved · denied · cancelled · expired |
| `case_prescriptions.review_status` | draft · confirmed |
| `vouchers.status` | active · used · expired · cancelled |
| `patient_subscriptions.status` | active · paused · cancelled · expired · pending |
| `patient_subscriptions.renew_period` | weekly · monthly · quarterly · yearly |
| `webhooks.status` | active · inactive |
| `webhook_deliveries.status` | pending · delivered · failed · retrying |
| `messages.direction` | inbound · outbound |
| `messages.channel` | portal · sms · email |
| `messages.sender_type` | patient · clinician · system |
| `files.type` | lab_result · id_doc · consent · medical_necessity · intake · other |
| `files.status` | uploaded · processing · processed · failed |
| `tags.type` | global · partner · case · patient |
| `ai_instruction_sets.context` | clinical_note · patient_message · storefront_message |
| `ehr_records.adapter` | mock · healthie |
| `ehr_records.status` | disabled · pending · sent · failed |
| `pharmacy_dispatches.adapter` | mock · lifefile · … |
| `pharmacy_dispatches.status` | disabled · pending · sending · sent · failed · dead_letter |
| `routing_policies.mode` | PRIORITY · ROUND_ROBIN · WEIGHTED · INTELLIGENT · PROVIDER_POOL |
| `routing_policies.status` | DRAFT · ACTIVE · SUPERSEDED |
| `state_visit_requirements.scope_type` | ALL · CATEGORY · OFFERING |
| `sla_policies.on_violation` | BYPASS · REQUIRE_APPROVAL |
| `case_pull_requests.status` | PENDING_APPROVAL · GRANTED · DENIED · REJECTED |
| `audit_logs.action` | created · updated · deleted · restored · force_deleted |
| `triage_rules.type` | bmi_threshold · age_threshold · keyword · offering |
| `triage_rules.operator` | gte · lte · gt · lt · contains |
| `triage_rules.triage_result` | red · yellow |
| `questionnaire_questions.depends_on_operator` | gte · lte · gt · lt · contains |

---

## 8. JSON Columns & Shapes

### `clinicians.licensed_states`
```json
[{"state": "CA"}, {"state": "TX"}]
```
Read via: `collect($states)->pluck('state')->contains('CA')`

### `offerings.available_states` / `offerings.video_required_states`
```json
["CA", "TX", "NY"]
```
2-letter uppercase state codes. Empty array or null = all states allowed.

### `offerings.levels`
```json
[
  {"label": "LVL1 - 1MG (0.25mg/wk)", "formula": "0.25mg/0.5mg/0.5mL (2mL)", "sig": "Inject 0.25 mL once weekly"},
  {"label": "LVL2 - 2MG (0.5mg/wk)",  "formula": "0.5mg/0.5mg/0.5mL (2mL)",  "sig": "Inject 0.50 mL once weekly"}
]
```
Used by the prescribe form to auto-fill SIG when a clinician selects a level.  
`Offering::effectiveSig(?Partner)` reads `sig_override` from `offering_partner` pivot first, falls back to `offerings.sig`.

### `cases.clinical_intake`
```json
{
  "product": "semaglutide-tablet",
  "dose": "0.5mg",
  "term": "3M",
  "plan": "standard",
  "med2": null,
  "onGlp": false,
  "zofran": true,
  "allergy": false,
  "allergyDetail": "",
  "video": false,
  "protocolVersion": "v2",
  "findings": "...",
  "summary": "...",
  "sourceAnswers": {}
}
```
All keys optional. Populated by the partner API at case creation.

### `cases.triage_reasons`
```json
["bmi_over_threshold", "age_under_18", "keyword_match:allergy"]
```

### `case_prescription_medications.dosing`
```json
{
  "medication": "Semaglutide",
  "frequency": "Weekly",
  "term": "3M",
  "months": ["L1 · 2.5 mg", "L2 · 5 mg", "L3 · 7.5 mg"]
}
```

### `routing_policies.config`
```json
{
  "newMode": "PROVIDER_POOL",
  "refillMode": "PRIORITY",
  "intelligentWeights": {},
  "providerWeights": {},
  "messageAgingThresholdHours": 24,
  "requireRecordedLicensure": true,
  "poolCriteria": {
    "maxOutstandingCases": 50,
    "maxOverdueCases": 5,
    "overdueAfterHours": 48,
    "maxAwaitingReply": 10,
    "maxCasesPerRequest": 5,
    "maxCasesPerDay": 20
  },
  "newCaseDelayedAfterHours": 2,
  "newCaseMaxDelayedCases": 3,
  "newCaseMaxAwaitingReply": 5
}
```

### `audit_logs.diff`
```json
{
  "status": {"old": "waiting", "new": "assigned"},
  "clinician_id": {"old": null, "new": 7}
}
```
Only dirty fields for `updated` actions. Full attribute snapshot for `created`.

### `prescription_documents.snapshot`
Locked full order payload at PDF generation time. Shape mirrors the prescribe form output — do not rely on a fixed structure; read `PrescriptionDocument` model.

### `routing_exceptions.provider_reasons`
```json
{"12": "NO_LICENSED_STATE", "7": "CAPACITY_EXCEEDED"}
```
Keys are clinician IDs (as strings).

---

## 9. Relationship Map

```
Partner
  ├─ hasMany ──────────────────── User
  ├─ hasMany ──────────────────── Patient
  ├─ hasMany ──────────────────── PatientCase
  ├─ hasMany ──────────────────── Offering (owns; partner_id FK)
  ├─ belongsToMany ─────────────── Offering via offering_partner (accessible catalog)
  ├─ hasMany ──────────────────── Webhook → WebhookDelivery
  ├─ hasMany ──────────────────── Voucher
  ├─ hasMany ──────────────────── PartnerProductPlan
  ├─ hasMany ──────────────────── PartnerEhrSetting
  ├─ hasMany ──────────────────── Questionnaire
  └─ belongsTo ─────────────────── Clinician (collaboratingClinician)

User (admin / super_admin)
  ├─ hasOne ───────────────────── Clinician
  ├─ belongsTo ────────────────── Partner (doctor admin only)
  └─ belongsToMany ─────────────── Clinician via admin_clinician (managedClinicians)

Clinician
  ├─ belongsTo ────────────────── User
  ├─ hasMany ──────────────────── PatientCase
  ├─ hasMany ──────────────────── ClinicalNote
  ├─ hasMany ──────────────────── Message
  ├─ belongsToMany ─────────────── User via admin_clinician (admins)
  └─ belongsToMany ─────────────── OfferingCategory via clinician_offering_category

Patient
  ├─ belongsTo ────────────────── Partner
  ├─ hasMany ──────────────────── PatientCase
  ├─ hasMany ──────────────────── PatientSubscription
  ├─ hasMany ──────────────────── Voucher
  ├─ hasMany ──────────────────── Message
  ├─ hasMany ──────────────────── Order
  ├─ hasMany ──────────────────── PatientFile
  ├─ belongsToMany ─────────────── Pharmacy via patient_preferred_pharmacies
  └─ belongsToMany ─────────────── Tag via patient_tags

PatientCase
  ├─ belongsTo ────────────────── Partner / Patient / Clinician
  ├─ hasMany ──────────────────── CaseOffering → Prescription (legacy)
  ├─ hasMany ──────────────────── CaseQuestion
  ├─ hasMany ──────────────────── ClinicalNote
  ├─ hasMany ──────────────────── Order
  ├─ hasMany ──────────────────── Message
  ├─ hasMany ──────────────────── PatientFile
  ├─ hasMany ──────────────────── CaseEvent
  ├─ hasMany ──────────────────── QuestionnaireResponse
  ├─ hasMany ──────────────────── CasePrescription
  │    └─ hasMany ──────────────── CasePrescriptionMedication
  │    └─ hasMany ──────────────── CasePrescriptionDiagnosis
  │    └─ hasMany ──────────────── PrescriptionDocument
  │         └─ hasMany ─────────── PharmacyDispatch
  ├─ hasMany ──────────────────── RoutingException
  ├─ hasMany ──────────────────── CasePauseInterval
  ├─ belongsToMany ─────────────── Offering via case_offerings
  ├─ belongsToMany ─────────────── Disease via case_diseases
  └─ belongsToMany ─────────────── Tag via case_tags

Offering
  ├─ belongsTo ────────────────── Partner (owner) / OfferingCategory / Pharmacy
  ├─ belongsToMany ─────────────── Partner via offering_partner (access grants)
  ├─ belongsToMany ─────────────── Questionnaire via offering_questionnaire
  └─ hasMany ──────────────────── CaseOffering / PartnerProductPlan
```

---

## 10. Indexing Rules

### Composite indexes (query-critical)

| Table | Index | Purpose |
|-------|-------|---------|
| `cases` | `(status, triage)` | Dashboard triage filter |
| `cases` | `(is_refill, status)` | Refill vs first-visit split |
| `cases` | `(partner_id, external_id)` UNIQUE | Partner case dedup |
| `patients` | `(partner_id, external_id)` UNIQUE | Partner patient dedup |
| `webhook_deliveries` | `(status, next_retry_at)` | Retry queue scan |
| `pharmacy_dispatches` | `(status, next_retry_at)` | Dispatch retry queue |
| `prescription_documents` | `(case_id, created_at)` | Latest document per case |
| `messages` | `(case_id, created_at)` | Message thread load |
| `case_events` | `(case_id, event_type)` | Event type filter per case |
| `ehr_records` | `(status, created_at)` | EHR retry queue |
| `ehr_records` | `(case_id, clinical_note_id)` UNIQUE | One EHR record per note per case |
| `offering_partner` | `(partner_id, is_active)` | Accessible offering lookup |
| `routing_policies` | `(status, version)` | Active policy lookup |
| `ai_instruction_sets` | `(context, is_active)` | Active instruction set per context |
| `sla_policies` | `(owner_user_id, is_active)` | Active SLA per admin |
| `case_pull_requests` | `(clinician_id, status)` | Pending pull requests per clinician |
| `state_visit_requirements` | `(state, scope_type)` | State rule lookup |

### Uniqueness rules enforced at application layer only

- `partner_product_plans` — uniqueness on `(partner_id, product_key, month_frequency, offering_id)` is checked in `PartnerProductPlanController`, not by a DB unique index (the DB index was dropped in `2026_07_27_100002`).

### No FK constraints (denormalized)

- `vouchers.pharmacy_id` — stored as varchar, no FK.
- `case_events.actor_id` — polymorphic, no FK constraint (actor may be deleted).

---

## 11. Design Notes

### Two-tier offering access model

Offerings have **two** partner-to-offering relationships:

1. **Ownership** (`offerings.partner_id`) — the partner that created/owns the offering.
2. **Access** (`offering_partner` pivot, `is_active = true`) — partners that can prescribe the offering. An offering can be accessible to many partners.

The `Partner::accessibleOfferings()` relationship queries the pivot with `wherePivot('is_active', true)`. When copying product plans to a new partner, `offering_partner` rows must be upserted first — otherwise the new partner has no accessible offerings.

### Prescription flow (new, not legacy)

```
PatientCase
  └─ CasePrescription (clinician signs off)
       ├─ CasePrescriptionMedication (one per medication)
       ├─ CasePrescriptionDiagnosis (ICD codes)
       └─ PrescriptionDocument (immutable PDF snapshot)
            └─ PharmacyDispatch (outbound to pharmacy gateway, retry-capable)
```

The old `prescriptions` table (linked from `case_offerings`) is **legacy** — kept for historical data only.

### Case status lifecycle

```
created → waiting → assigned → approved → processing → completed
                ↘              ↗
                  support ────┘
                     ↓
                  (any) → cancelled
```

Only `waiting`, `assigned`, `support` are "open" (used by dashboard workload counts).

### Routing policy versioning

Only one `routing_policies` row has `status = 'ACTIVE'` at a time. When a new policy is activated, the previous one is flipped to `SUPERSEDED`. `RoutingPolicy::active()` returns the current active row.

### `prescription_documents` immutability

The model overrides `update()` and `delete()` to throw `RuntimeException`. Snapshots are write-once. If a prescription needs correction, a new `CasePrescription` (and new document) must be created.

### `settings` caching

`Setting::get($key)` uses Laravel's cache with a 1-hour TTL. If you update a setting row directly in the database (not via the model), you must flush the cache for the change to take effect: `Cache::forget("setting:{$key}")`.

### `triage_rules` table status

This table is currently **empty and has no active code reading from it**. Triage v2 (since `2026_07_24`) derives RED/YELLOW classification from `questionnaire_questions.options[*].is_disqualify` flags, not from this table.
