# State Logic — Doctor Portal

---

## Part 1: Plain English

### The Problem It Solves

Medical services are regulated by state. A doctor licensed in California cannot legally prescribe to a patient in Texas. A compound medication approved for sale in New York may not be available in Florida. This system enforces those boundaries automatically so no case is ever sent to a doctor who can't legally handle it, and no medication is prescribed in a state where it isn't available.

---

### The Three Things That Have a State

**1. The Patient**
Every patient has a state — where they currently are. This comes from the address they fill in on the intake form, or from what the partner sends when creating a case via the API.

**2. The Offering (the medication or service)**
Each offering (e.g. Semaglutide 0.5mg, Anti-Aging Program) can be restricted to specific states. If no states are selected, it is available everywhere. If states are selected, only patients in those states can receive it.

**3. The Clinician (the doctor)**
Each clinician holds medical licenses in specific states. A clinician may be licensed in CA, TX, and FL — but not NY. They can only legally treat patients in states where they hold a license.

---

### What Happens at Each Stage

#### Stage 1 — Patient Fills Out the Public Form
The patient completes the intake questionnaire online. Their state is captured from their address answers.

- If their state is covered by the offering → everything proceeds normally. The case enters the queue.
- If their state is **not** covered → the case is still created (the patient sees a normal "thank you" screen), but it is placed **on hold**. A note is added internally saying the state may not be covered. An admin must review and release it manually before it goes to a doctor.

The patient never sees an error. The system handles it quietly behind the scenes.

#### Stage 2 — Partner Creates a Case via the API
A partner company sends a case through the API. They include the patient's state and the offerings they want.

- If any offering is not available in the patient's state → the API immediately rejects the request with a clear error message telling them which offering doesn't cover that state.
- This is a hard block — the partner must fix it before the case can be created.

#### Stage 3 — Case Enters the Queue and Gets Assigned to a Doctor
Once a case is in the queue, the system automatically finds an available doctor.

- It first looks for doctors licensed in the **patient's state**.
- If a licensed match is found → that doctor gets the case.
- If no licensed match is found → it assigns any available doctor anyway (and logs a warning), so the case never gets permanently stuck.

#### Stage 4 — Admin Manually Assigns a Doctor
Sometimes an admin manually picks a doctor for a case.

- The dropdown shows all doctors as usual.
- Any doctor **not licensed** in the patient's state gets a ⚠ warning label next to their name.
- The admin can still choose them — it's a warning, not a block. Admins sometimes need to override for legitimate reasons.

---

### Summary in One Sentence Each

- **Public form:** State mismatch → case goes on hold silently, admin reviews.
- **Partner API:** State mismatch → request rejected immediately with an error.
- **Auto-assignment:** Prefers licensed doctors, falls back to any doctor if none available.
- **Admin assignment:** Shows a warning badge, does not block the admin override.

---

---

## Part 2: Technical

### Data Model

**`offerings.available_states`** — `JSON`, nullable. Array of 2-letter state abbreviations e.g. `["CA","TX","NY"]`. Null or empty array means available in all states.

**`cases.patient_state`** — `VARCHAR(2)`, nullable. Set at case creation from `patient_state` payload field (API) or from the `state` keyed answer (public form). Falls back to `patients.state` if not explicitly provided via the API.

**`clinicians.licensed_states`** — `JSON`, nullable. Array of objects: `[{"state":"CA","license_number":"...","expiry_date":"..."}]`. Null or empty array means no state restrictions on this clinician.

---

### Helper Methods

**`Offering::isAvailableInState(string $state): bool`**
`app/Models/Offering.php`

```php
if (empty($this->available_states)) return true;
return in_array(strtoupper($state), array_map('strtoupper', $this->available_states));
```

**`Clinician::isLicensedInState(string $state): bool`**
`app/Models/Clinician.php`

```php
$states = $this->licensed_states ?? [];
if (empty($states)) return true;
return collect($states)->pluck('state')->contains(strtoupper($state));
```

Both helpers treat an empty/null value as "no restriction" — this is intentional so that existing records with no state data set do not accidentally become unusable.

---

### Enforcement Points

#### 1. API Case Creation
`app/Http/Controllers/Api/Partner/CaseController::store()`

Runs **before** the DB transaction. Resolves `$effectiveState` as `patient_state ?? patient.state`. Loops through each submitted offering UUID, resolves it against the partner's offerings, and calls `isAvailableInState()`. Returns `HTTP 422` with a structured error on first mismatch. No patient record is created, no case is created.

```
POST /api/partner/cases
→ resolve effectiveState
→ foreach offering: isAvailableInState(effectiveState) or return 422
→ begin transaction → create patient → create case → attach offerings
```

#### 2. Public Form Submission
`app/Http/Controllers/Web/Form/QuestionnaireFormController::submit()`

Runs **after** disqualification check, **before** the DB transaction. Loads `$questionnaire->offerings()` (the pivot relationship on `offering_questionnaire`). Calls `isAvailableInState()` for each linked offering against `$keyedAnswers['state']`. Sets `$stateHold = true` on first mismatch.

Inside the transaction, the `PatientCase` is created with:
- `hold_status = true`
- `support_note = "Patient state ({STATE}) may not be covered..."`

After the transaction, the `CaseStateMachine::transition()` to `STATUS_WAITING` is **skipped** when `$stateHold` is true. The case remains in `STATUS_CREATED` and does not enter the assignment queue until an admin releases it via the hold toggle.

```
POST /forms/{uuid}
→ process answers → check disqualification
→ load questionnaire->offerings()
→ foreach offering: isAvailableInState(patient.state) → set $stateHold
→ DB transaction → create patient → create case (hold_status=$stateHold)
→ if !$stateHold: transition to STATUS_WAITING (enters queue)
→ if $stateHold:  stay in STATUS_CREATED (admin must release)
```

#### 3. Auto-Assignment
`app/Services/CaseAutoAssigner::findNext(PatientCase $case)`

Builds a base eligible collection: `status=active`, `is_available=true`, `active_cases < max_daily_cases`, ordered by `priority ASC, id ASC`.

If `$case->patient_state` is set, filters the eligible collection with `isLicensedInState()`. If the filtered collection is non-empty, returns its first element. If the filtered collection is empty, logs a `Log::warning()` with `case_uuid` and `patient_state`, then returns the first element of the unfiltered eligible collection. This fallback ensures no case is permanently stuck in `waiting`.

```
findNext(case):
→ $eligible = active + available + under_capacity clinicians, by priority
→ if case.patient_state:
    $filtered = $eligible->filter(isLicensedInState(state))
    if $filtered not empty → return $filtered->first()
    else → Log::warning → fall through
→ return $eligible->first()   // fallback: any available clinician
```

#### 4. Admin Assignment Dropdown
`resources/views/admin/cases/show.blade.php`

Counts unlicensed clinicians before the form renders using a `@php` block and `collect($clinicians)->filter()`. Displays an `alert-warning` banner above the dropdown if count > 0. For each `<option>`, calls `$c->isLicensedInState($case->patient_state)` inline and appends `⚠ Not licensed in {STATE}` to the option text if false. Assignment is not blocked — admin override is always permitted.

---

### State Flow Decision Tree

```
Patient state known?
├── No  → skip all state checks, proceed normally
└── Yes
    ├── Entry via public form
    │   ├── Offering covers state? → proceed, case enters queue
    │   └── Offering does NOT cover state → case created on hold, admin reviews
    │
    ├── Entry via Partner API
    │   ├── Offering covers state? → proceed, case created
    │   └── Offering does NOT cover state → HTTP 422 returned, case NOT created
    │
    └── Assignment (auto or manual)
        ├── Auto: prefer clinician licensed in state → fallback to any if none
        └── Manual: show ⚠ warning on unlicensed clinicians, admin can still pick any
```

---

### Files Changed

| File | Change |
|---|---|
| `app/Models/Clinician.php` | Added `isLicensedInState()` helper |
| `app/Services/CaseAutoAssigner.php` | State-aware filtering with fallback + Log::warning |
| `app/Http/Controllers/Api/Partner/CaseController.php` | Pre-transaction offering state validation → 422 |
| `app/Http/Controllers/Web/Form/QuestionnaireFormController.php` | Soft hold on state mismatch, skip queue transition |
| `resources/views/admin/cases/show.blade.php` | Warning banner + ⚠ labels on unlicensed clinicians in dropdown |
