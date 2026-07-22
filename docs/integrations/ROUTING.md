# Case routing: MA-DOCPORTAL's routing, ported to MEDAXIS

Audience: the MEDAXIS dev team, and whoever activates a policy.
Status: **fully wired.** Live behaviour is unchanged until a new version is activated.

---

## 1. What was brought over

Everything in MA's routing surface (`packages/domain/src/routing.ts`,
`packages/domain/src/eligibility.ts`, `apps/api/src/services/routing-policy-resolver.ts`,
and the `RoutingPolicy` model), plus MEDAXIS's own existing behaviour kept as a fifth mode.

### The five modes

| Mode | Behaviour |
|---|---|
| `PRIORITY` | **MEDAXIS's existing rule.** Lowest priority rank with capacity. The seeded default. |
| `ROUND_ROBIN` | Strict rotation, deterministic given the cursor, wraps at the tail. |
| `WEIGHTED` | The doctor furthest below their allocation share (lowest volume ÷ weight). Weight `0` excludes. |
| `INTELLIGENT` | Configurable weighted-workload score. Lowest wins. |
| `PROVIDER_POOL` | No auto-assignment; cases wait to be claimed. |

### The eight intelligent coefficients

Ported with MA's exact defaults, all overridable per version:

| Coefficient | Default |
|---|---|
| Open green case | 1 |
| Open yellow case | 3 |
| Open red case | 2 |
| Unanswered message | 2 |
| Message older than 12 hours | 25 |
| Per minute of median decision time | 0.02 |
| Per case already assigned today | 0.25 |
| Allocation penalty multiplier | 5 |

Score = weighted workload + allocation penalty, where the penalty is
`(volume ÷ weight) × allocationPenalty`, and a doctor with **no** weight takes `penalty × 10` so
they do not absorb volume meant for weighted peers.

### The hard blocks

A blocked doctor is never scored and never selected, by any mode:

- not active
- marked unavailable
- **no licence in the patient's state**
- daily volume cap reached (at the cap counts as full)
- open case cap reached
- oldest unanswered message past the configured threshold (off unless set)

### The properties that make it safe

- **Deterministic.** Identical inputs always give the identical answer, with `providerId` as the
  final tie-break everywhere. No order-dependent or random picks.
- **Fails closed.** An unknown mode from a malformed stored policy assigns *nobody* rather than
  falling through to a default. A cap that cannot be evaluated blocks rather than passes.
- **`null` cap means uncapped**, which is distinct from a cap of `0`. Conflating them would either
  block everyone or nobody.
- **Ranking only.** The strategy chooses; the caller writes. It cannot authorise something the
  write path would refuse.

---

## 2. Two behaviour changes. Read these.

### 2.1 The unlicensed fallback is gone

The old `CaseAutoAssigner` did this: if no clinician held a licence in the patient's state, it
logged a warning and **assigned an unlicensed clinician anyway**, so cases would not stick in the
queue.

MA treats a missing state licence as a hard block, and so does this port. **A case with no licensed
doctor now waits.**

This is deliberate. A stuck case is visible and recoverable; a prescription written by someone not
licensed in the patient's state is neither. But it is a real change: if licence coverage has gaps,
cases that previously got assigned will now sit in the queue instead. **Check licensed-state
coverage before activating anything, and watch the queue after.**

A missing or unknown patient state also blocks, for the same reason: we cannot show the doctor is
licensed to treat someone whose location we do not know.

### 2.3 The licence check is currently vacuous for doctors with no licence data. Read this one twice.

`Clinician::isLicensedInState()` returns **true** when a clinician has **no** licensed states
recorded. An empty list is treated as "licensed everywhere".

That is fail-open, and it means the state-licence hard block above **does nothing at all** for any
doctor whose licence data was never filled in, which is precisely the population most likely to be
wrong.

That helper is used elsewhere in the app, so this port does not quietly change it. Instead the gap
is made visible and closable, via a per-version flag:

- **`requireRecordedLicensure: false`** (the default) keeps today's behaviour, so activating a
  routing policy does not suddenly block every doctor with blank licence data.
- **`requireRecordedLicensure: true`** treats blank licensure as a hard block
  (`LICENSURE_NOT_RECORDED`), which is the compliance-grade reading.

**What to do:** populate licensed states for every clinician, then turn this on. Until you do,
understand that 2.1 protects you only against doctors who have licence data that does not include
the patient's state, not against doctors who have none at all.

This is surfaced on the routing admin screen with the same warning, not buried here.

### 2.2 Nothing changes on the day this ships

The migration seeds an **ACTIVE `PRIORITY` policy** reproducing MEDAXIS's existing behaviour, so
routing is identical until someone activates a different version. Except for 2.1, which applies to
every mode including `PRIORITY`.

---

## 3. Versioning

Routing decides which doctor sees which patient. When someone asks in six months why a case went
where it did, a mutable settings row cannot answer, so:

- every change is a **new version**; the active one is never edited in place
- activation supersedes the previous version inside one transaction, so there is never a moment
  with two active policies or none
- each version records who created it, who activated it, when, and why

Drafting and activating are separate actions. A half-finished policy cannot start routing patients.

---

## 4. Where MEDAXIS does more than MA

MA's resolver hard-codes `unansweredMessages: 0` and `medianDecisionMinutes: 0`, with a comment
that no message store or decision-timing rollup exists there yet. Two of its eight coefficients
therefore score zero for everyone and do nothing.

MEDAXIS has both: a `messages` table with direction and read state, and `created_at` / `approved_at`
on cases. Both signals are wired to real data here, so `INTELLIGENT` mode is **more complete in
MEDAXIS than in the system it came from**.

Median decision time is currently approximated by the mean. If the distribution turns out to be
skewed enough to matter, that is the thing to revisit.

---

## 5. Going live

1. Check licensed-state coverage first (see 2.1).
2. Draft a version and set the weights.
3. Activate it. The confirmation states that it changes which doctor new cases go to.
4. Watch the queue for cases that stop being assigned, which is the signal for a licence gap.
5. To roll back, activate the previous version. Nothing is destroyed.

---

## 6. Tests and verification

`tests/Unit/RoutingStrategyTest.php` covers every mode, the hard blocks, the cap semantics,
determinism under reordered input, the zero-weight exclusion, and the unknown-mode fail-closed path.
Database-free, because this repo has one model factory.

> **Never executed.** Written on a machine with no PHP, Composer or MySQL. Static checks only:
> braces balance, imports resolve, and every column referenced was read out of the migrations
> first. Run the suite before trusting any of it, and treat 2.1 as something to verify in staging
> rather than assume.

---

## 7. Continuity of care: check-ins go back to the same doctor

Added 2026-07-22 (Devin msg 2244, decisions in 2246).

### 7.1 The word "refill" here does not mean a pharmacy refill

Two different things share the word, and confusing them will cost someone an afternoon:

| Thing | Where it lives | Meaning |
|---|---|---|
| `refills` (integer) | offerings, prescriptions, case_offerings | How many times a dispensed script may be filled again. **Untouched by any of this.** |
| `is_refill` (boolean) | `cases` | The patient submitted a **check-in** and a doctor prescribes again. A re-bill on an existing course of treatment. |

Devin's words: "I'm calling it refill it's not the same as a clinical refill. On a rebill/refill the
client submits a check in and the doctor prescribes again."

### 7.2 What happens

A check-in is routed to the doctor who treated that patient before, if they can still take it.
The check runs **before** the routing strategy, because all five modes answer "who is least loaded
right now", and for a returning patient that is the wrong question.

It returns "not applicable" and falls straight through to normal routing whenever:

- the case is not a check-in (so **every first visit routes exactly as it did before**);
- the patient has no qualifying prior case;
- the prior doctor is blocked by a gate continuity may not override.

`PROVIDER_POOL` is honoured over continuity, deliberately. That mode means "push nothing, every
case is claimed", and quietly pushing check-ins would make the pool not a pool.

### 7.3 How a check-in is recognised

1. **`is_refill` on the case.** The real signal. Partners set it on `POST /api/partner/cases`.
2. **`visit_type` fallback.** A short list of unambiguous substrings (`refill`, `rebill`,
   `re-bill`, `check-in`, `checkin`, `check in`), case-insensitive, so a partner already sending a
   sensible `visit_type` works without changing their integration.
3. **Never inferred from patient history.** A returning patient with a genuinely new complaint is a
   first visit for that complaint. Guessing otherwise hands a new problem to a doctor on the
   strength of a history that does not apply, which is the more expensive mistake.

The fallback is intentionally narrow. `visit_type` is free text, and a loose matcher would start
classifying first visits as check-ins.

### 7.4 Which gates continuity may override

The rule: **continuity beats WORKLOAD gates and never beats LEGAL or ACCESS ones.**

| Gate | Overridden? | Why |
|---|---|---|
| Doctor not active | **No** | They are gone from the portal. |
| Doctor unavailable | **No** | They switched themselves off, which reads the same from the patient's side. |
| No licence in the patient's state | **No** | A legal gate. Overriding it produces a prescription written by someone who cannot lawfully write it. |
| No storefront grant | **No** | An access gate. See 7.6. |
| Daily volume cap reached | **Yes** | Devin msg 2246, explicitly. |
| Open cases cap reached | **Yes** | Same family, same reasoning. |
| Message aging block | **Yes** | Also a workload gate. **Called out because it was not ruled on:** it fires when a doctor has an unanswered patient message older than the configured limit. If it should block instead, move the constant out of `ContinuityResolver::OVERRIDABLE`. |

`OVERRIDABLE` is an inverse allow-list on purpose: a new reason code added to `EligibilityEvaluator`
blocks by default rather than silently becoming overridable because nobody updated a list.

### 7.5 Which prior case decides "the original provider"

The most recent **completed** case, for the **same patient and the same partner**, that actually
**produced a prescription**.

- Completed, because a case still in flight has not established anything.
- With a prescription, because that is the evidence a clinical decision was made. A cancelled or
  abandoned case should not create a claim on the patient.
- Same partner, because a patient can exist under two storefronts and a doctor's history with one
  is not a reason to hand them a case belonging to another.

### 7.6 What is not finished

- **A cap on NEW cases is needed and is not built** (Devin msg 2246: "We need a cap on new cases and
  a way to separate and track them"). Since continuity overrides the daily cap, `max_daily_cases`
  now governs first visits while check-ins flow past it, so the existing single cap means less than
  it used to. `is_refill` is the column that cap will need; the cap itself is still open.
- **The storefront grant check is dormant.** `Clinician::hasAccessToPartner()` arrives with
  `compliance/rxos-laws`. `ContinuityResolver` checks for the method rather than hard-calling it, so
  the gate starts applying the moment that branch merges. Until then no grant model exists in the
  system, so there is nothing being bypassed.

### 7.7 Reporting

`PatientCase::refills()` and `PatientCase::firstVisits()` read the **column**, never the
`visit_type` fallback, so the two always sum to the case total. A substring match over free text
would not add up, and a reporting figure that does not add up is worse than no figure. Partners who
want to appear in these send `is_refill`.

Surfaced as two dashboard metrics (First Visits, Check-ins), each linking to the case list filtered
by `?case_type=new` / `?case_type=refill`.

### 7.8 Tests

`tests/Unit/RefillDetectionTest.php` (flag wins, fallback catches, fallback does not over-match,
scopes read the column) and `tests/Unit/ContinuityGateTest.php` (workload gates overridable, legal
and access gates never, new reason codes default to blocking).

> **Never executed.** Same standing caveat as section 6: written on a machine with no PHP. Static
> checks only. Run the suite before trusting it.
