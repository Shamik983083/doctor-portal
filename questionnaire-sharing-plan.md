# Questionnaire Sharing & Rendering Plan

## Overview

The current questionnaire module covers the **build** side — admins can create dynamic forms with 14 field types, per-option disqualify logic, and a question bank. This document covers the **serve** side: how forms are rendered for patients, how they work in single-step and multi-step mode, and how external systems (partner websites, offer flows, third-party apps) integrate with them.

---

## Telehealth Form Use Cases

| Use Case | Examples | Shape |
|---|---|---|
| Eligibility Screening | Covered state check, contraindication flags | Short, disqualify-heavy, gate-keeping |
| Clinical Intake | Chief complaint, symptoms, medications, allergies | Long, section-based, clinician reads it |
| Standardised Assessments | PHQ-9, GAD-7, AUDIT, STOP-BANG | Fixed questions with scored numeric answers |

The current builder supports all three via field types and the disqualify toggle. What is missing is the **rendering and submission layer** — right now forms can be built but not served to patients or external systems.

---

## Single-Step vs Multi-Step

### Concept

Questions are grouped into **steps**. A `step_number` integer on each question controls which page of the form it appears on. The questionnaire itself has a `mode` field:

- `single` — ignore step numbers, render all questions on one page
- `multi` — group questions by step number and show one group at a time

### Schema Changes (small)

```sql
-- questionnaires table
ALTER TABLE questionnaires ADD COLUMN mode ENUM('single','multi') NOT NULL DEFAULT 'single';

-- questionnaire_questions table
ALTER TABLE questionnaire_questions ADD COLUMN step_number TINYINT UNSIGNED NOT NULL DEFAULT 1;
```

### Multi-Step UX (browser-side, no page reloads)

The full form is rendered server-side into the HTML. JavaScript shows and hides step sections, drives a progress bar, and validates the visible section before advancing. On the final step, one POST carries all answers. The backend stays simple — it receives one request regardless of how many steps there were.

```
[Step 1 of 3] ──────────────── 33%
About You
  ┌─────────────────┐
  │ Full Name       │
  │ Date of Birth   │
  │ State           │
  └─────────────────┘
              [Next →]
```

### Admin Builder Changes

The question builder gets a "Step" number input per question card (defaults to 1). The questionnaire create/edit form gets a Mode toggle (Single / Multi-Step). No other changes to the builder are needed.

---

## Submission Storage

Before any rendering is built, these two tables must exist.

### `questionnaire_responses`

One row per form submission.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| questionnaire_id | FK | which form was filled |
| patient_id | FK nullable | linked patient (if known) |
| partner_id | FK nullable | which partner collected it |
| case_id | FK nullable | linked case (set post-submission) |
| external_patient_id | varchar nullable | partner's patient ID passed via URL |
| token | varchar unique | random token for public URL |
| completed_at | timestamp nullable | null = abandoned/in-progress |
| created_at / updated_at | timestamps | |

### `questionnaire_answers`

One row per answered question within a response.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| response_id | FK | parent response |
| question_id | FK | which question |
| answer | text nullable | plain text, JSON array for multi-select |
| is_disqualified | boolean | true if chosen option has is_disqualify flag |
| created_at | timestamp | |

---

## Integration Patterns

### Pattern 1 — Shareable Public URL

A public route renders the form for anyone with the link. No login required.

```
GET /forms/{questionnaire_uuid}
GET /forms/{questionnaire_uuid}?external_id={partner_patient_id}&partner={partner_token}
```

- Admin generates the link from the questionnaire show page (copy button)
- Patient fills the form in the portal UI
- On submit, a `questionnaire_responses` row is created along with all `questionnaire_answers`
- A webhook fires to the partner with the response payload
- Disqualified responses get a "not eligible" screen; passing responses get a "thank you / next steps" screen

**Best for:** Patient self-service, email campaigns, standalone eligibility screening.

---

### Pattern 2 — iFrame Embed

The partner embeds the form in their own website or app inside an `<iframe>`. The admin questionnaire show page generates the embed snippet.

```html
<iframe
  src="https://portal.example.com/forms/{uuid}?partner_token={token}&external_id={patient_id}"
  width="100%"
  height="640"
  frameborder="0"
  allow="camera">
</iframe>
```

- `partner_token` authenticates which partner is collecting the response
- `external_id` pre-links the response to the partner's own patient record
- On form completion the renderer posts a `postMessage` event to the parent window so the partner site can react (advance a checkout step, unlock a button, redirect, etc.)

```javascript
// Message the partner's parent window on completion
window.parent.postMessage({
  event: 'questionnaire_completed',
  response_token: 'abc123',
  disqualified: false
}, '*');
```

**Best for:** Offer checkout flows — a partner's product page shows "Complete health screening" and embeds the form inline before allowing purchase.

---

### Pattern 3 — Headless API (Partner Renders Own UI)

The partner fetches questions as JSON, renders their own branded form, and POSTs answers back. Uses the existing Passport client credentials token.

```
GET  /api/partner/questionnaires/{id}
     → { id, name, mode, questions: [{key, label, type, step, is_required, options}] }

POST /api/partner/questionnaires/{id}/submit
     → { external_patient_id, answers: [{question_id, answer}] }
     ← { response_token, disqualified: false, disqualified_on: null }
```

**Best for:** Partners with their own app or brand identity who cannot use an iFrame.

---

### Pattern 4 — Offer Flow Integration

An offering gets one or more questionnaires attached to it. When a partner submits a case that includes that offering, the system knows which questionnaire the patient must complete before or alongside the case.

#### Schema

```sql
CREATE TABLE offering_questionnaire (
  offering_id       BIGINT UNSIGNED NOT NULL,
  questionnaire_id  BIGINT UNSIGNED NOT NULL,
  is_required       BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (offering_id, questionnaire_id)
);
```

#### Flow

```
Partner selects offering
        ↓
API returns offering detail including required questionnaire IDs
        ↓
Partner renders questionnaire (via Pattern 2 or 3)
        ↓
Patient completes form → response_token returned
        ↓
Partner submits case, including response_token(s)
        ↓
Case is created; responses are linked to the case (case_id set on questionnaire_responses)
        ↓
Clinician sees completed intake form alongside the case detail
```

#### Case Submission Change

```json
POST /api/partner/cases
{
  "patient": { ... },
  "external_id": "order-999",
  "offerings": [{ "offering_id": 4, "quantity": 1 }],
  "questionnaire_responses": ["resp_token_abc"],
  "questions": [{ "question": "Chief complaint", "answer": "Weight gain" }]
}
```

`questionnaire_responses` is an array of tokens from previously submitted form responses. The server validates that each required questionnaire for each offering has a corresponding completed response.

---

## Webhook on Completion

When a response is submitted, a `questionnaire_completed` webhook fires to the partner.

```json
{
  "event": "questionnaire_completed",
  "questionnaire_id": 3,
  "questionnaire_name": "Weight Loss Eligibility",
  "response_token": "resp_abc123",
  "external_patient_id": "patient-456",
  "disqualified": false,
  "disqualified_on": null,
  "completed_at": "2026-06-29T10:45:00Z"
}
```

If `disqualified: true`, `disqualified_on` contains the question key where the patient hit the exclusion rule.

---

## Build Priority

| # | What | Tables / Files | Effort |
|---|---|---|---|
| 1 | Response + answer tables | 2 migrations | Small |
| 2 | `mode` + `step_number` on existing tables | 2 migrations, admin builder update | Small |
| 3 | Public form renderer (single-step first) | `/forms/{uuid}` route + Blade view | Medium |
| 4 | Multi-step JS layer | Builder JS + renderer JS | Medium |
| 5 | Shareable link + embed snippet in admin UI | Questionnaire show page addition | Small |
| 6 | iFrame postMessage on completion | Renderer JS | Small |
| 7 | `offering_questionnaire` pivot + admin linkage | 1 migration + offering edit view | Medium |
| 8 | Case submission accepts `questionnaire_responses` | API CaseController update | Small |
| 9 | Headless API endpoints (Pattern 3) | 2 API routes + controller | Medium |
| 10 | `questionnaire_completed` webhook | Webhook dispatch on response save | Medium |

**Recommended sprint order:** 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8. Items 9 and 10 only after partners need custom-branded forms or automated webhook triggers.

---

## Key Rules

1. **Disqualify is evaluated server-side** — the browser may show an early "not eligible" message but the server always re-validates on submit to prevent manipulation.
2. **Responses are immutable once completed** — `completed_at` is set once and never changed; edits require a new response.
3. **A questionnaire can be shared across multiple offerings** — the pivot table is many-to-many.
4. **A case can have multiple questionnaire responses** — one per required offering questionnaire.
5. **Public URL requires no authentication** — the `questionnaire_uuid` in the URL is unguessable (UUID v4); no login gate.
6. **iFrame embed passes `partner_token` and `external_id` as query params** — the renderer validates the token against the `oauth_clients` table, same as the API.
