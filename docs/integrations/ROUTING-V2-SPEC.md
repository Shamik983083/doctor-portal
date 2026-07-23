# Case routing v2: the eligibility gate, dual-path routing, and the provider pull queue

Status: **BUILT on `staging` for MEDAXIS. Not yet ported to MA-DOCPORTAL.**
Never executed: this repo has no PHP runtime on the machine it was written on. See section 12.

Source: Devin's breakout (msg 2308) and his answers to Q1 to Q7 (msg 2313). Every question that was
open in the draft of this file is now closed, and the answer is recorded inline at the rule it
governs rather than in a list at the end.

Companion document: `ROUTING.md` describes v1 (the five modes, continuity of care, capacity
controls). Everything there still holds except where this file says otherwise, and the three places
it does are called out in section 11.

---

## 1. The one sentence version

Nothing gets ranked until the case and the doctor agree on three facts (**state**, **product
category**, **visit type**), every case travels one of two paths (**new** or **check-in**) with its
own mode and caps, the provider pool became a **pull queue**, and **nothing fails silently**.

---

## 2. The eligibility gate

Three axes, evaluated before continuity and before any mode, in this order. All three are hard
blocks: no mode can score past them, and per Devin's Q3 answer continuity does not override them
either.

| Axis | The case supplies | The doctor must supply | Reason code when it blocks |
|---|---|---|---|
| **State** | The state the prescription is needed in | A recorded licence in that state | `RESIDENCE_STATE_LICENSE_MISSING`, or `LICENSURE_NOT_RECORDED` when they have none at all |
| **Product category** | Every category on the case | All of them ticked on their accepted list | `CATEGORY_NOT_ACCEPTED` |
| **Visit type** | Asynchronous or synchronous | That type accepted, plus a booking link if synchronous | `VISIT_TYPE_NOT_ACCEPTED`, or `SCHEDULING_LINK_MISSING` |

Order matters only for how a human reads the exceptions screen, since every reason that fires is
returned. The most legally consequential is listed first.

Code: `app/Services/Routing/EligibilityEvaluator.php`, facts gathered once per decision in
`app/Services/Routing/CaseRequirements.php`.

### 2.1 State: blank licensure now REJECTS

Devin msg 2313: *"empty should not show licensed everywhere it needs to reject."*

`Clinician::isLicensedInState()` returned **true** for a doctor with no recorded licensed states, so
an empty list read as licensed in all fifty. That made the licence gate vacuous for exactly the
population most likely to be wrong, and it passed on the prescribe and approve paths too, not only
in routing.

It is fail-closed now, and `RoutingPolicy::requireRecordedLicensure()` defaults **true**, so it
applies under v1 as well as any new version.

> **THIS IS THE ONE CHANGE THAT BITES ON DEPLOY.** Every clinician with no recorded licensed states
> stops receiving cases and stops being able to prescribe or approve, the moment this ships. Run
> `php artisan licensure:audit` first. It lists exactly who is affected and exits non-zero while any
> remain, so it works as a deploy gate. Fixing one takes about thirty seconds on their admin screen.

### 2.2 Category: the taxonomy already existed, and it is already editable

Devin msg 2313 Q2: *"Currently we have GLP, NAD, Anti-Aging, Peptides, ED. We want to be able to add
and remove as we go. Do your recommendation with it."*

**Correction to the draft of this file.** It said MEDAXIS's categories were per-partner and proposed
importing MA's fixed six-value enum. That was wrong. `offering_categories` is a **global** table
(id, name, description, is_active) with an admin CRUD screen at `/admin/categories` that can already
add, deactivate and delete. It is exactly the editable shared taxonomy the answer asked for, so
nothing was imported and no enum was introduced: a category added next month is tickable
immediately, with no code change and no migration.

MA-DOCPORTAL's `ClinicalCategory` **is** a locked enum, so the port in the other direction is the
harder half. See section 10.

- A case's categories come from `case_offerings -> offerings.category_id`.
- A doctor must accept **every** category on the case. A case with two products is one prescribing
  decision and a doctor who takes GLP1 but not peptides cannot take half of it.
- An empty accepted list means the doctor accepts **nothing**. That is fail-closed, matching 2.1, and
  it is safe only because the migration **backfilled every existing doctor with every active
  category**. After the backfill, an unticked category is an admin's decision rather than an absence
  of data.
- A case with **no** category at all is reported as `NO_CATEGORY_ON_CASE`, not as "nobody was
  eligible": the fix is on the offering, and conflating the two sends whoever reads the exceptions
  screen to the wrong page.

Table: `clinician_offering_category`. UI: the clinician edit screen.

### 2.3 Visit type: the state decides, the doctor opts in, the link is part of the gate

Devin msg 2313 Q4: *"yes MA's is [the source of truth] and we need to adjust as super admin as laws
change frequently. the Sync is determined by states. we'll need to be able to integrate a calendly
link for the provider if its a sync visit so they can book on there."*

**The case half** is `state_visit_requirements`, ported from MA-DOCPORTAL and super-admin owned:

- Scoped ALL, CATEGORY or OFFERING, and **most specific wins** (OFFERING beats CATEGORY beats ALL),
  so a blanket state rule can be narrowed for one product without rewriting it.
- **Effective dated.** Telehealth law changes on a date and cases routed last month were routed
  under last month's rule; an edited row cannot answer what was required at the time, a dated one
  can. `effective_to` null means still in force. Ending a rule that has been in force sets an end
  date rather than deleting it.
- On a tie at the same specificity, the rule REQUIRING video wins. Two rules at one scope disagreeing
  is a configuration mistake, and the safe reading of a legal requirement is the stricter one.
- **Ships empty**, and when no rule matches, the existing per-offering `video_required_states` list
  is still honoured. That is what makes this change inert on deploy: everything already configured on
  offerings keeps working, and nothing has to be re-entered.

**The doctor half** is three new columns: `accepts_async_visits` (default true, because every case
today is asynchronous), `accepts_sync_visits` (default false), and `scheduling_link`.

**The booking link is part of the gate, not decoration.** A synchronous case assigned to a doctor
with no link produces a case the patient cannot act on, which looks like a successful assignment from
the routing side and is discovered by the patient. It blocks, under its own reason code, because the
fix is different from a refusal: that doctor said yes and simply cannot be booked yet.

Column named `scheduling_link` rather than `calendly_url`: the column outlives the vendor.

UI: `/admin/routing/visit-requirements` (super admin), plus the two switches and the link on the
clinician edit screen.

---

## 3. Two paths, each with its own mode and caps

Devin msg 2308: *"THERE ARE 2 CHECKS: 1. NEW CLIENTS 2. REFILL CLIENTS. WE NEED TO HAVE CAPS AND
ROUTING FOR EACH."*

Every case is classified once, at intake, and the classification selects a whole routing
configuration rather than only a cap. Classification is unchanged and ratified: the `is_refill` flag
is the real signal, a narrow `visit_type` substring list is the fallback, and it is never inferred
from patient history.

**New path.** Eligibility gate, no continuity, the new-case capacity controls
(`accepting_new_cases`, `max_daily_new_cases`, `max_open_cases`, `max_daily_cases`, plus the
policy-level delayed and awaiting-reply criteria), then `newMode` ranks the survivors.

**Check-in path.** Eligibility gate, then **continuity first**: the patient's own doctor takes it if
they can. If they cannot, `refillMode` picks a new one. That second mode is the piece that did not
exist before, and it is Devin's Q3 answer made concrete: *"in that case if both blocked then route
them to a new provider."* Check-ins remain exempt from every new-case control and are never withheld
on volume.

A policy version carries both modes, and an older version with neither reads through to its `mode`
column. That is what keeps v1 (PRIORITY) meaning exactly what it always meant.

Code: `RoutingPolicy::modeForCase()`, `RoutingPolicyResolver::resolve()`.

---

## 4. The modes

Four selectable, one retired.

**PRIORITY.** Saturate then spill: the top-ranked doctor takes every case until they hit a cap, then
the next rank. Unchanged.

**ROUND_ROBIN.** Even spread by strict rotation, deterministic given the cursor. Devin msg 2313 Q1:
*"okay keep the rotations."* His breakout said "randomly"; the rotation was kept because a rotation
can reproduce its own answer months later and a random pick cannot, and reproducibility is the
reason policies are versioned at all.

**WEIGHTED.** Split by admin-set share, lowest volume-to-weight ratio wins, weight 0 excludes
entirely. Unchanged.

**PROVIDER_POOL.** Rebuilt. Section 5.

**INTELLIGENT: retired.** Devin msg 2313 Q5: *"adjust it for pool eligibility. I think there was
confusion on the initial build but that was meant to be the logic behind the pool."* Its eight
coefficients now describe workload for pool eligibility instead of picking a doctor for a case. The
constant and the strategy branch survive so a stored policy naming it still routes rather than
fail-closing to nobody, but it is off the admin screen. `RoutingMode::ALL` is what is offered;
`RoutingMode::ALL_STORED` is what is executable, and the difference is how "no doctor was eligible"
is told apart from "this policy names a mode that does not exist".

---

## 5. The provider pull queue

Devin msg 2308: *"A PROVIDER CAN REQUEST CASES (A NUMBER, THE DON'T SEE WHAT'S AVAILABLE) ... THEN
TRANSFER CASES TO THEM WITH THE OLDEST IN THE SYSTEM FIRST."*

### 5.1 The sequence

1. **Check the doctor**, against the policy's pool criteria and their SLA (5.2, 5.3).
2. **Walk the queue oldest first**, and for each case run the **same** eligibility gate the push path
   uses. Reusing it is the point: a parallel implementation here would be free to drift into letting
   a pull do what a push refuses.
3. **Stop at the smallest of**: what they asked for, what they are eligible for, the per-request
   ceiling, and their remaining headroom under their own caps.
4. **Claim atomically.**

**The workload counters run as the grant proceeds.** Each case granted increments the doctor's open,
daily and new-case counts before the next is evaluated, so a doctor two cases from their cap gets two
and not twenty.

**A pull never overrides a cap.** Continuity overrides caps because that is the patient's own doctor.
Nothing about a doctor asking for more work is.

**The claim is a conditional UPDATE** (`whereNull('clinician_id')`), so exactly one of two
simultaneous requests can take a row and the loser moves on. A read-then-write would hand the same
case to both under any real concurrency. The state transition runs inside the same transaction, so a
case that cannot legally move to assigned releases the claim instead of sitting owned by a doctor
whose queue never shows it.

**The doctor never sees the queue.** Their screen is a number box and their own history. A doctor who
can see the queue can cherry-pick it, and the oldest-first guarantee that keeps the tail from rotting
only holds if nobody chooses.

### 5.2 Pool criteria: a flat refusal, set on the policy

Versioned with the policy, all optional, null means off: max open cases, max overdue cases (with the
hours that count as overdue), max patients awaiting a reply, max cases per request, max pulled per
day.

A new criterion added later **blocks by default**, the same inverse allow-list rule as
`ContinuityResolver::OVERRIDABLE`, so forgetting to wire one up leaves the pool stricter rather than
quietly permissive.

"Overdue" is measured on case age from `assigned_at` (falling back to `created_at`), never on a
message read flag, so a doctor cannot clear an overdue case by opening it. "Awaiting a reply" means
the newest inbound message is newer than the newest outbound one, which measures whether the patient
got an answer rather than whether the doctor looked.

### 5.3 SLA: set by the Doctor Admin, and it either bypasses or asks

Devin msg 2313 Q6: *"SLA is going to be adjusted by doctor admin and pushed down so we need a node
for that. it should bypass, or seek approval from Dr Admin."*

`sla_policies` is owned by a Doctor Admin and applies to the doctors they are over, through the
existing `admin_clinician` pivot. Measures: max open cases, max overdue cases (with its window), and
max decision time. A super admin's policy acts as the house default for doctors whose own admin has
not set one.

On a breach, `on_violation` decides:

- **BYPASS**: the pull goes through and the admin is told afterwards. Bypass must not mean nobody
  finds out.
- **REQUIRE_APPROVAL** (the default): the request is held at `PENDING_APPROVAL` and the admin decides.
  A doctor taking on more work while behind an SLA their admin set is the admin's call unless they
  deliberately chose otherwise.

**A doctor under two admins takes the stricter policy**, resolved field by field, and needs approval
if either asked for it. An SLA is a floor, and the looser of two floors is not a floor.

**This gates one thing only**: a doctor asking the pool for more work. It never blocks a case being
pushed to them and never blocks a check-in reaching its own doctor. Care routes; requests for more of
it are what get held.

Not to be confused with the existing `/admin/settings` SLA, which sets house targets for how fast a
case should be picked up and reviewed. Those measure cases. This measures a doctor, and only when
they ask.

"SLA performance" was left open in Q6 and is implemented as **mean time from case creation to
approval over a rolling 30 days**. If the distribution turns out skewed enough to matter, computing
a true median is the thing to revisit.

### 5.4 Every refusal is explained

A doctor who cannot see the queue can only learn what happened from their own request row. So the
request is a persisted record with the outcome on it: `REJECTED` with the criterion that closed,
`PENDING_APPROVAL` with what is over SLA, or `GRANTED` with a shortfall reason when they got fewer
than they asked for. "Nothing was transferred" with no reason is exactly the failure this was asked
to remove.

Code: `PoolPullService`, `PoolEligibilityEvaluator`, `PoolEligibility`. UI: `/clinician/pool` and
`/admin/routing/pull-requests`.

---

## 6. No silent failures

Devin msg 2308: *"ANY FAILURE NEEDS TO BE LOUD, WE NEED THE DOCTOR ADMIN AND SUPER ADMIN TO SEE
CASES THAT AREN'T ASSIGNED OR HAVE AN ISSUE OR AN ERROR NO SILENT FAILURES."*

Before this, a case nobody could take produced a log line and a null `clinician_id`. That is
indistinguishable from a case created four seconds ago, and the evidence of the failure was an
**absence**, which is precisely what nobody notices.

- Every failure to route writes a `routing_exceptions` row **the moment routing gives up**, with the
  specific reason code and the per-doctor block reasons attached. Never a generic "unassigned": the
  screen can say "all four doctors blocked, three of them on licence".
- **One open row per case.** A retry updates it and bumps `occurrences` rather than stacking, so a
  worker retrying every few minutes cannot bury everything else. `first_seen_at` never moves, so the
  age of the problem is the age of the problem.
- **Notification timing** (Devin msg 2313 Q7, taking the recommendation): into the queue immediately;
  notify at a configurable age (`config/routing.php`, default 2 hours) so the alerts do not become a
  firehose; **no delay at all** for systemic failures (`NO_ACTIVE_POLICY`, `UNKNOWN_MODE`), where
  every case in the system is affected and the minutes spent waiting are the only useful ones. Guarded
  by `notified_at` so one exception escalates once.
- **Exceptions close themselves** when the case routes, by any path. A screen that needs manual
  clearing fills with things that are already fine, which is the same as an empty one.
- **A scheduled sweep** (`routing:sweep-exceptions`, every 15 minutes) escalates aged exceptions and
  closes ones whose case has since been assigned. The recorder only escalates when something tries to
  route; a case that failed once at 2am and is never retried would otherwise age unnoticed, which is
  the silent failure this exists to remove.
- **The pool queue is shown alongside, in its own list.** A case waiting to be claimed under
  PROVIDER_POOL is not an error, but it is an unassigned case and Devin asked to see unassigned
  cases. Keeping the lists apart is what stops "working as designed" and "broken" from looking the
  same.

Visible to **Doctor Admins as well as super admins**: msg 2308 named the Doctor Admin first, so these
screens sit outside the super-admin-only nav section, with each one scoping its data to the doctors
that admin is over.

---

## 7. Unchanged and ratified

Devin pasted these back verbatim in msg 2308, so they are settled, not merely inherited.

**Hard blocks**: not active; unavailable; no licence in the patient's state; daily volume cap
reached; open case cap reached; oldest unanswered message past threshold. Caps use `>=`; `null` means
uncapped, distinct from a cap of 0. The three axes in section 2 join this list.

**The three safety properties**: deterministic (provider id is the final tie-break everywhere);
fails closed (an unknown mode assigns nobody; a cap that cannot be evaluated blocks); ranking only
(the strategy chooses, the caller writes and re-validates).

**Continuity**: runs before the modes for check-ins, beats workload gates, never beats legal or
access ones. `PROVIDER_POOL` is honoured over continuity.

**Capacity controls**: `max_daily_cases`, `max_open_cases`, `accepting_new_cases`,
`max_daily_new_cases`, and the soft `daily_refill_alert_threshold` that alerts and never withholds.

---

## 8. What ships inert, and what does not

Everything here is additive and behaviour-neutral on deploy **except section 2.1**.

| Change | Effect on day one |
|---|---|
| Category gate | None. Every doctor was backfilled with every active category. |
| Visit-type gate | None. The matrix ships empty, no case resolves synchronous, and `accepts_async_visits` defaults true. |
| Dual-path modes | None. v1 has no per-path config and reads through to its `mode` column. |
| Pool criteria and SLA | None. All null, and nothing is configured until someone sets it. |
| Exceptions | New rows and a new screen. No routing decision changes. |
| INTELLIGENT retired | None. No stored policy uses it, and it stays executable if one did. |
| **Blank licensure blocks** | **Real.** Any clinician with empty `licensed_states` loses their queue and their prescribe and approve rights. Run `licensure:audit` first. |

---

## 9. What was built

**Migrations** (7, all additive, all with working `down()`):
`clinician_offering_category` (with backfill), clinician visit-type columns,
`state_visit_requirements`, `sla_policies`, `case_pull_requests`, `routing_exceptions`, and a DRAFT
routing policy v2 carrying the dual-path config.

**Services**: `CaseRequirements`, `StateVisitRequirementResolver`, `VisitType`,
`PoolEligibilityEvaluator`, `PoolEligibility`, `PoolPullService`, `RoutingExceptionRecorder`, plus
changes to `EligibilityEvaluator`, `RoutingPolicyResolver`, `ContinuityResolver`, `RoutingMode`,
`CaseAutoAssigner`.

**Models**: `StateVisitRequirement`, `SlaPolicy`, `CasePullRequest`, `RoutingException`, plus changes
to `Clinician` and `RoutingPolicy`.

**Commands**: `licensure:audit` (the deploy gate for 2.1), `routing:sweep-exceptions` (scheduled).

**UI**: routing exceptions, pull requests, doctor SLA, the state visit matrix, the doctor's request
screen, dual-mode and pool-criteria fields on the routing policy form, and accepted categories,
visit types and booking link on the clinician edit screen. Also a `warning` flash block in
`layouts/app.blade.php`: controllers have been flashing `warning` since the routing screen shipped
and there was no block rendering it, so those messages were being dropped.

---

## 10. Still to do: the MA-DOCPORTAL port

Devin msg 2313: *"this will be the source on both when done."* MEDAXIS is the reference
implementation; MA-DOCPORTAL follows.

The two systems start from opposite ends, so this is not a copy:

- **MEDAXIS was ahead on the doctor side**: priority, weights, licensed states, four capacity caps,
  and now accepted categories, visit-type acceptance and the booking link. MA's `ProviderProfile` is
  thin (id, npi, status, licences, tenant access), so all of that is net new there.
- **MA was ahead on the case side**: it already has the state synchronous-video requirement policy
  with scopes and effective dates, and a provider queue and batch specification already written.
- **The category taxonomy is the real work.** MEDAXIS uses an editable `offering_categories` table;
  MA uses a locked `ClinicalCategory` enum (GLP1, NAD, ANTI_AGING, ED, PEPTIDES, ENCLOMIPHENE).
  Devin asked to add and remove categories as we go, which an enum cannot do without a migration per
  change. MA needs the enum replaced by a table before its half of the category axis can exist.

MA-DOCPORTAL has **no staging branch**, only `main` plus feature branches, so that work opens a
branch rather than touching `main`.

---

## 11. Where this supersedes ROUTING.md

Three places. Everything else in that document still holds.

1. **Section 2.3** ("the licence check is currently vacuous"): closed. Blank licensure blocks.
2. **The five modes**: four now, INTELLIGENT retired as a mode (section 4 here).
3. **PROVIDER_POOL** as "cases wait to be claimed": now a pull queue with a defined mechanism
   (section 5 here).

---

## 12. Tests, and the standing caveat

New: `EligibilityGateTest` (the three axes, the every-category rule, the booking-link rule,
fail-closed licensure, and that none of the new codes is overridable by continuity),
`VisitRequirementPrecedenceTest` (scope precedence, malformed rules matching nothing, order-independent
tie-breaking), `DualPathRoutingTest` (per-path modes, fallback to the column, INTELLIGENT selectable
versus executable, pool criteria reading garbage as off rather than as zero).

Existing suites unchanged: `RoutingStrategyTest`, `ContinuityGateTest`, `RefillDetectionTest`,
`CapacityControlsTest`.

> **Never executed.** The same standing caveat as `ROUTING.md` sections 6, 7 and 8: written on a
> machine with no PHP, Composer or MySQL. Static checks only, which here means every referenced
> column was read out of the migrations first, every route name and helper was verified against the
> files that define them, and all 40 changed PHP files were checked for balanced delimiters.
>
> **Run the suite before trusting any of it.** Then, in staging and in this order:
> 1. `php artisan licensure:audit` and fix everyone it lists, BEFORE deploying (section 2.1).
> 2. Confirm the category backfill populated `clinician_offering_category` for every active doctor.
> 3. Create a case and confirm it still routes exactly as it did.
> 4. Add one state visit rule and confirm a case in that state resolves synchronous and blocks
>    doctors with no booking link.
> 5. Switch a policy to PROVIDER_POOL and run a pull, including two at once, to confirm the atomic
>    claim.
> 6. Confirm the two SQL count helpers in `PoolEligibilityEvaluator` against real data.
