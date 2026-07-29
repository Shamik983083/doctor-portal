# Partner API Integration Guide

Base URL: `https://staging.axismd.io/api` (staging) · `https://axismd.io/api` (production)

---

## Authentication

All requests require an OAuth 2.0 bearer token obtained via the client credentials flow.

```
POST /api/partner/auth/token
Content-Type: application/json

{
  "grant_type":    "client_credentials",
  "client_id":     "<your_client_id>",
  "client_secret": "<your_client_secret>"
}
```

Response:
```json
{ "access_token": "...", "token_type": "Bearer", "expires_in": 86400 }
```

Pass the token on every subsequent request:
```
Authorization: Bearer <access_token>
```

Tokens expire after **24 hours**. Request a new one before or immediately after expiry.

---

## Create a Case

```
POST /api/partner/cases
Authorization: Bearer <access_token>
Content-Type: application/json
```

### Full Payload

```json
{
  "patient": {
    "first_name":         "Jane",
    "last_name":          "Doe",
    "email":              "jane.doe@example.com",
    "phone":              "+15551234567",
    "date_of_birth":      "1985-06-15",
    "gender":             "female",
    "height":             70.5,
    "weight":             185.0,
    "bmi":                26.2,
    "address":            "123 Main St",
    "city":               "Austin",
    "state":              "TX",
    "zip":                "78701",
    "external_id":        "portal-user-9001",
    "id_verified_status": "verified",
    "id_verified_at":     "2026-07-16T10:30:00Z"
  },

  "patient_state":  "TX",
  "external_id":    "order-wl-20240701-001",
  "visit_type":     "asynchronous",
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      false,
  "metadata":       { "source": "patient-portal" },

  "offerings": [
    { "product_key": "semaglutide", "month_frequency": 12, "quantity": 1 }
  ],

  "clinical_intake": {
    "term":            "12M",
    "dose":            "L1 · 2.5 mg",
    "plan":            "Titration",
    "onGlp":          "N",
    "allergy":         "N",
    "allergyDetail":   null,
    "zofran":          "N",
    "video":           "not required",
    "protocolVersion": "GLP-1 protocol v8"
  },

  "answers": [
    { "slug": "glp1_allergies",                              "answer": "No" },
    { "slug": "current_glucose_medications",                 "answer": "None" },
    { "slug": "weight_loss_medications",                     "answer": "None" },
    { "slug": "gastric_bypass_6_months",                     "answer": "No" },
    { "slug": "glp_consent",                                 "answer": "Yes" },
    { "slug": "contact_agree",                               "answer": "Yes" },
    { "slug": "sms_consent",                                 "answer": "Yes" }
  ]
}
```

---

### Field Reference

#### `patient` object — required

| Field | Required | Type | Notes |
|---|---|---|---|
| `first_name` | ✓ | string | |
| `last_name` | ✓ | string | |
| `email` | ✓ | email | De-duplicated on `external_id` first, then `email` |
| `phone` | — | string | |
| `date_of_birth` | — | date | ISO 8601 date |
| `gender` | — | string | `male` · `female` · `other` |
| `height` | ✓ | number | Inches (70.5 = 5′10.5″) |
| `weight` | ✓ | number | Pounds |
| `bmi` | ✓ | number | Send pre-calculated |
| `address` | — | string | |
| `city` | — | string | |
| `state` | — | string | 2-letter US state code |
| `zip` | — | string | |
| `external_id` | — | string | Your portal's user ID — used to match returning patients |
| `id_verified_status` | — | string | `verified` · `failed` · `pending` |
| `id_verified_at` | — | datetime | ISO 8601 |

#### Top-level case fields

| Field | Required | Type | Notes |
|---|---|---|---|
| `patient_state` | — | string | 2-letter state where the patient is located. Falls back to `patient.state` if omitted. Used for licensure routing. |
| `external_id` | — | string | Your order / case ID. Must be unique per partner. Duplicate returns 409. |
| `visit_type` | — | string | `asynchronous` (default for GLP-1 weight loss) or `synchronous` (video required). Do **not** pass `"weightloss"` — use `"asynchronous"`. |
| `is_chargeable` | — | boolean | Default `true` |
| `hold_status` | — | boolean | `true` = case sits on hold until you release it via `POST /{uuid}/hold`. Default `false`. |
| `is_refill` | — | boolean | `true` = refill / check-in. See [Refill Cases](#refill--check-in-cases). Default `false`. |
| `metadata` | — | object | Free-form JSON stored verbatim on the case. Not used clinically. |

---

### `offerings` — what the patient is requesting

Two mutually exclusive options:

**Option A — direct offering UUID (legacy)**
```json
{ "offering_id": "YOUR_OFFERING_UUID", "quantity": 1 }
```

**Option B — product key + duration (recommended)**
```json
{ "product_key": "semaglutide", "month_frequency": 12, "quantity": 1 }
```

Option B resolves internally to all offering SKUs configured under that `product_key` / `month_frequency` pair (e.g. Semaglutide Tablet SNAC + B12 + B6). The clinician sees the primary variant on their review screen.

| Field | Type | Notes |
|---|---|---|
| `product_key` | string | Key configured in the partner product plan (e.g. `"semaglutide"`, `"tirzepatide"`) |
| `month_frequency` | integer | **Must be an integer** — `1`, `3`, `4`, `6`, or `12`. Not a string. Must match a configured plan row or the API returns 422. |
| `quantity` | integer | Default `1` |

> **Common mistake:** sending `"frequency": "12 month"` is silently ignored. Use `"month_frequency": 12`.

---

### `clinical_intake` — clinician review panel *(previously undocumented)*

This block populates the left-hand summary panel the clinician sees when reviewing and approving a case (Requested Term, Requested Dose, On GLP-1, Plan, etc.). Without it, every field shows as `—`.

All fields are **optional strings**. Send only what you collect.

```json
"clinical_intake": {
  "term":            "12M",
  "dose":            "L1 · 2.5 mg",
  "plan":            "Titration",
  "onGlp":          "N",
  "allergy":         "N",
  "allergyDetail":   null,
  "zofran":          "N",
  "video":           "not required",
  "protocolVersion": "GLP-1 protocol v8"
}
```

| Field | Renders as | Typical values |
|---|---|---|
| `term` | Requested Term | `"1M"` · `"3M"` · `"4M"` · `"6M"` · `"12M"` — should match `month_frequency` |
| `dose` | Requested Dose | `"L1 · 2.5 mg"` · `"L2 · 5 mg"` etc. |
| `plan` | Plan | `"Titration"` · `"Starter"` · `"Maintenance"` |
| `onGlp` | On GLP-1 | `"Y"` · `"N"` |
| `allergy` | Allergies | `"Y"` · `"N"` |
| `allergyDetail` | (shown if allergy Y) | Patient's own words |
| `zofran` | Zofran requested | `"Y"` · `"N"` |
| `video` | Video | `"not required"` · `"required"` · `"Clear"` |
| `protocolVersion` | Protocol | e.g. `"GLP-1 protocol v8"` |

**Duration dropdown pre-selection:** `term` must match `month_frequency` for the duration dropdown on the prescription form to auto-select correctly (e.g. `month_frequency: 12` → `term: "12M"`).

**Update after creation:** if you don't have all clinical data at create time, push it separately:
```
POST /api/partner/cases/{uuid}/clinical
Authorization: Bearer <token>
Content-Type: application/json

{ "clinical_intake": { "term": "12M", "dose": "L1 · 2.5 mg", ... } }
```
This **replaces** the block wholesale. See [Case Endpoints](#other-case-endpoints).

---

### `answers` — questionnaire intake

Use `answers` (slug-based) for the simplified path — the portal resolves which questionnaire each slug belongs to automatically.

```json
"answers": [
  { "slug": "glp1_allergies",              "answer": "No" },
  { "slug": "current_glucose_medications", "answer": "None" },
  { "slug": "weight_loss_medications",     "answer": "None" },
  { "slug": "gastric_bypass_6_months",     "answer": "No" },
  { "slug": "glp_consent",                 "answer": "Yes" },
  { "slug": "contact_agree",               "answer": "Yes" },
  { "slug": "sms_consent",                 "answer": "Yes" }
]
```

**Conditional fields** — only include when relevant:

| Condition | Extra slugs to include |
|---|---|
| `weight_loss_medications` = `"Semaglutide"` | `previous_semaglutide_medication_last_dose`, `previous_semaglutide_medication_last_dose_date`, `previous_semaglutide_medication_experience` |
| `weight_loss_medications` = `"Tirzepatide"` | `previous_tirzepatide_medication_last_dose`, `previous_tirzepatide_medication_last_dose_date`, `previous_tirzepatide_medication_experience` |

For multi-select answers, pass an array: `"answer": ["Option A", "Option B"]`.

---

### Refill / Check-in Cases

Set `"is_refill": true` for a returning patient's check-in visit. The system routes the case back to the clinician who treated this patient previously and flags it as a check-in (not a first visit) in reporting.

```json
{
  "is_refill": true,
  "patient": { "email": "jane.doe@example.com", ... },
  ...
}
```

The patient is matched by `email` or `patient.external_id`. The prior case and prescription are surfaced to the clinician automatically.

---

### Response

**201 Created** — case accepted:
```json
{
  "id": "2d86c6da-1c81-41fe-9d1c-ac1c363fe469",
  "status": "waiting",
  "patient": { ... },
  "case_offerings": [ ... ]
}
```

**409 Conflict** — `external_id` already exists for this partner.

**422 Unprocessable** — validation failed (see `errors` object):
```json
{
  "message": "No product plan found for product_key \"semaglutide\" with month_frequency 12.",
  "errors": { "offerings": ["..."] }
}
```

---

## Case Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/cases` | List cases. Supports `?status=waiting&page=1` |
| POST | `/api/partner/cases` | Create a case (see above) |
| GET | `/api/partner/cases/{uuid}` | Get a single case |
| GET | `/api/partner/cases/by-external-id/{id}` | Look up by your `external_id` |
| POST | `/api/partner/cases/{uuid}/cancel` | Cancel case — body: `{ "reason": "..." }` |
| POST | `/api/partner/cases/{uuid}/hold` | Hold or release — body: `{ "hold": true/false, "reason": "..." }` |
| POST | `/api/partner/cases/{uuid}/clinical` | Replace `clinical_intake` wholesale |
| POST | `/api/partner/cases/{uuid}/support` | Escalate to support — body: `{ "support_note": "..." }` |
| POST | `/api/partner/cases/{uuid}/return-to-clinician` | Push a completed case back for clinician review |
| GET | `/api/partner/cases/{uuid}/events` | Case event / audit log |
| GET | `/api/partner/cases/{uuid}/messages` | List patient messages on case |
| POST | `/api/partner/cases/{uuid}/messages` | Send a message — body: `{ "body": "..." }` |

---

## Patient Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/patients` | List patients |
| POST | `/api/partner/patients` | Create a patient (same `patient` object as case creation) |
| GET | `/api/partner/patients/{uuid}` | Get patient |
| GET | `/api/partner/patients/by-external-id/{id}` | Look up by your `external_id` |
| PATCH | `/api/partner/patients/{uuid}` | Update patient fields |
| DELETE | `/api/partner/patients/{uuid}` | Delete patient |

---

## Offering Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/offerings` | List available offerings |
| POST | `/api/partner/offerings` | Create an offering |
| GET | `/api/partner/offerings/{uuid}` | Get offering |
| GET | `/api/partner/offerings/{uuid}/questionnaires` | Get questionnaires linked to this offering |
| PUT | `/api/partner/offerings/{uuid}` | Update offering |
| DELETE | `/api/partner/offerings/{uuid}` | Delete offering |

---

## Order Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/orders` | List orders |
| GET | `/api/partner/orders/{uuid}` | Get order |
| PUT | `/api/partner/orders/{uuid}` | Update order (e.g. tracking) |
| POST | `/api/partner/orders/{uuid}/cancel` | Cancel order |

---

## Questionnaire Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/questionnaires/{uuid}` | Get questionnaire and its questions (for rendering intake forms) |

---

## Webhooks

### Managing webhook endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/partner/webhooks` | List registered webhooks |
| POST | `/api/partner/webhooks` | Register a new endpoint |
| GET | `/api/partner/webhooks/{id}` | Get webhook |
| PUT | `/api/partner/webhooks/{id}` | Update webhook URL or event filter |
| DELETE | `/api/partner/webhooks/{id}` | Remove webhook |
| POST | `/api/partner/webhooks/deliveries/{deliveryId}/resend` | Retry a failed delivery |

### Events

The portal sends a `POST` to your URL with `Content-Type: application/json` for each event.

| Event | When it fires |
|---|---|
| `case_created` | Case is first created |
| `case_waiting` | Case enters the clinician queue |
| `case_assigned_to_clinician` | A clinician claims the case |
| `case_approved` | Clinician approves the case |
| `case_processing` | Prescription is being processed |
| `case_completed` | Case fully completed |
| `case_cancelled` | Case cancelled (by any party) |
| `case_support` | Case escalated to support |
| `prescription_written` | Clinician writes a prescription — includes full medication list, ICD-10 codes, NPI |
| `clinical_note_added` | Clinician adds an internal note |
| `message_created` | Clinician sends a patient message |
| `patient_message_received` | Patient sends a message through the portal |
| `patient_created` | New patient record created |
| `patient_modified` | Patient record updated |
| `patient_deleted` | Patient record deleted |
| `order_status_changed` | Order status updated |
| `tracking_number_changed` | Shipping tracking number added |

All payloads include at minimum: `case_id` (UUID), `patient_id` (UUID), `status`, and `timestamp` (Unix).

The `prescription_written` payload additionally includes:
```json
{
  "case_id":         "...",
  "clinician_name":  "Dr. Jane Smith",
  "clinician_npi":   "1234567890",
  "diagnoses":       [{ "code": "E66.09", "description": "..." }],
  "meds_prescribed": [{
    "name":                "Semaglutide Tablet (SNAC)",
    "product_key":         "semaglutide",
    "month_frequency":     12,
    "compound_formula":    "...",
    "sig":                 "...",
    "refills":             "0",
    "quantity":            "1",
    "days_supply":         "30",
    "dispense_unit":       "vial",
    "days_until_dispense": 0,
    "dosing":              { "frequency": "Weekly", "term": "12M", "months": ["L1 · 2.5 mg", ...] }
  }]
}
```

---

## File Upload (Prescription Images)

Upload a file first, get a `file_token`, then reference it in an answer:

```
POST /api/partner/files
Authorization: Bearer <token>
Content-Type: multipart/form-data

file=<binary>  type=lab_result
```

Response: `{ "uuid": "file-token-uuid", ... }`

Then in `answers`:
```json
{ "slug": "lab_result_upload", "answer": "file-token-uuid" }
```

---

## Common Errors

| HTTP | Cause | Fix |
|---|---|---|
| 401 | Invalid or expired token | Re-authenticate |
| 409 | Duplicate `external_id` | Use a unique value per case |
| 422 | Validation failed | Check `errors` object — common cause: `month_frequency` not matching a configured product plan |
| 403 | Offering not available in patient state | Check `patient_state` and offering state availability |

---

*Full clinical intake field reference: `docs/integrations/STOREFRONT-INTAKE.md`*
*Routing configuration: `docs/integrations/ROUTING.md`*
