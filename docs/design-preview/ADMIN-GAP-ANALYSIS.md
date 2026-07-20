# Doctor Admin: what MA-DOCPORTAL has that MEDAXIS does not

> ## CORRECTION, 2026-07-20, after building against it
>
> **Sections B, C, D and the operational report below were WRONG. Those features already
> exist in MEDAXIS** and have done all along, in `Admin\DashboardController`: storefront
> workload by partner and triage, weighted provider load as a percentage of
> `max_daily_cases`, the exception center, the operational report (TTFR, TTD, approval
> rate, decision throughput), triage volume, and a webhook delivery log.
>
> **How the error happened:** the first pass inferred MEDAXIS's capabilities from its
> routes, nav and controller names. The nav entry is just "Dashboard", so none of it
> surfaced, and the dashboard controller body was never read. MA's fixture-driven demo was
> compared against a list of MEDAXIS route names rather than against MEDAXIS's actual code.
>
> **What this changes:** those are not gaps and nothing needs building for them. What was
> genuinely wrong is that every one of those views was UNSCOPED, showing every admin the
> whole platform. That is fixed (see A), and it makes the scoping work more valuable than
> this document originally suggested, not less.
>
> The corrected gap list is in section 3 below. Sections B, C and D are struck through
> rather than deleted, so the mistake stays visible instead of being quietly tidied away.

Purpose: decide what a "Doctor Admin" section in MEDAXIS should contain, by comparing MEDAXIS's
existing admin against MA-DOCPORTAL's, before anything is built.

Read against Devin's instruction: same look and feel, **remove no MEDAXIS functionality**, add the
MA capabilities that are not currently available.

---

## 1. First, a correction to the premise

MA-DOCPORTAL's admin **front end barely exists**. The two components that render it are
`AdminDemo.tsx` (29 lines) and `SuperAdminDemo.tsx` (57 lines), both driven by static fixtures,
plus a single `apps/portal/app/admin/page.tsx`. There is no admin UI in MA to port screen by screen.

What MA genuinely has is the **back end**: nine admin route modules and five services
(`admin-catalog`, `admin-intake-mapping`, `admin-integrations`, `admin-protocols`,
`admin-providers`, `admin-routing-policies`, `admin-state-visit`, `admin-storefronts`,
`admin-users`), each with tests.

So "replicate the admin section" resolves to two separate things:

- **Look and feel** comes from MA's design system, which the preview already carries (palette
  lifted verbatim and asserted by `verify.mjs` so it cannot drift).
- **Capability** comes from MA's admin API surface and from what those two demos reveal about the
  operational concepts behind it.

MEDAXIS's admin is by far the more complete of the two: 18 nav destinations against MA's one page.
Nothing here proposes removing any of it.

---

## 2. What MEDAXIS already has

Dashboard, Cases, Patients, Clinicians (+ priority ordering), Partners, Offerings, Categories,
Questionnaires, Questions, Triage Rules, Webhooks, Admins, Settings, and four API guides.

Plus, relevant below: `CaseEvent` (per-case event log), `CaseAutoAssigner` (assignment by clinician
priority), and `video_required_states` (an array of two-letter states on an Offering).

---

## 3. The gaps, ranked by what they are actually worth

### A. Tenant-scoped admin access — HIGHEST VALUE

MA states it explicitly in its own UI: *"Admins see only storefronts explicitly assigned to them,
never cross-storefront metrics."* Cross-storefront reporting is reserved to a Super Admin role.

MEDAXIS admins appear to be global. Every admin sees every partner.

This is the same principle as the Healthie segregation rule already enforced in the EHR seam, but
applied to the admin UI rather than to outbound records. Worth doing first, because retrofitting
scoping onto screens built without it is far harder than building them scoped.

**Needs a decision from Devin:** does MEDAXIS want a two-tier admin (tenant admin vs super admin),
or do all admins stay global? Everything below inherits the answer.

### ~~B. Storefront workload view~~ — WRONG, ALREADY EXISTS

`DashboardController` already builds `$storefronts`: per partner, open cases and the
green/yellow/red triage mix. Nothing to build. It was unscoped; that is fixed under A.

### ~~C. Exception center~~ — WRONG, ALREADY EXISTS

`DashboardController` already builds `$exceptions`: workflow holds awaiting clearance, escalated
to support, missing identity verification, cancelled in the last 7 days. Each bucket already maps
to a real workflow condition rather than a free-text status, which was the property that mattered.
Nothing to build. It was unscoped; that is fixed under A.

### ~~D. Weighted provider load~~ — WRONG AS WRITTEN, PARTLY ALREADY EXISTS

`DashboardController` already builds `$providerLoads`: active cases against each clinician's
`max_daily_cases`, as a percentage, which is the same view MA shows.

What remains true is the **routing** half: MEDAXIS ASSIGNS by `Clinician.priority` ordering via
`CaseAutoAssigner`, not by weighted capacity, and routing policy is code rather than
configuration. See D-routing below.

### D-routing. Routing policy as configuration

MA shows each provider's load as a percentage of capacity with per-provider caps, and treats
routing policy as a configurable object.

MEDAXIS has `Clinician.priority`, an ordered rank, and `CaseAutoAssigner` picks the highest-priority
clinician with capacity. That is a real assignment mechanism but a simpler one: a strict ordering
rather than weighted distribution, and the policy is code rather than configuration.

**This is a behaviour change, not an addition.** Presenting load as a percentage is safe and useful;
switching assignment from priority-ordering to weighted capacity would change who gets which case.
Recommend showing the load view first and treating the routing change as a separate decision.

### E. State visit policy matrix

MA models this as one cell per state and scope (ALL / CATEGORY / PROGRAM / PRODUCT), each
versioned and effective-dated, active versions immutable, strictest active cell wins, activation
restricted to a Super Admin and audited, every version carrying a compliance reference.

MEDAXIS has `video_required_states`, an array of state codes on each Offering. That is the PRODUCT
scope only, with no versioning, no effective dating, no scope hierarchy, no strictest-wins
resolution, no audited activation and no compliance reference.

This is the largest genuine capability gap and the one with regulatory weight: it is the difference
between "we set a flag" and "we can show which rule applied to this case on this date, who
activated it, and under what compliance reference."

It is also the biggest build. It should be scoped on its own rather than folded into a general
admin refresh.

### F. Configuration audit trail

MA writes an immutable, PHI-free audit event for every configuration and clinical action, in the
same transaction as the change, and surfaces it as a timeline.

MEDAXIS has `CaseEvent`, which is per-case and clinical. There is no system-wide record of
configuration changes: who edited a triage rule, changed a product's states, or enabled an
integration.

Given how much of MEDAXIS's clinical behaviour is now admin-configurable (triage rules, catalog,
and the new AI instruction sets), an unaudited config change can alter clinical outcomes with no
trace. Recommend high priority, and it is a moderate build.

### G. Integration health — PARTLY EXISTS

The dashboard already shows a webhook delivery log and a failed-delivery count. What it does not
show is the newer outbound integrations: pharmacy dispatch, EHR records and AI assist, each of
which now has its own state and flags.

So this is extending an existing panel rather than building one. Small.

### H. Protocol category coverage

MA's `admin-protocols` treats clinical protocols as first-class admin objects with per-category
coverage. MEDAXIS's nearest equivalents are triage rules and the `protocolVersion` string carried
on a case.

**Needs clarification** on what a "protocol" should mean in MEDAXIS before building anything.

### I. Intake mapping

MA's `admin-intake-mapping` maps intake answers onto internal fields as configuration. MEDAXIS
wires questionnaire answers through `keyedAnswers` in code.

Lower priority: MEDAXIS's questionnaire builder already covers most of the practical need.

---

## 4. Recommended order, corrected

1. **A** (two-tier scoping) - everything inherits it, and it is what makes the dashboard panels
   that already exist actually correct per admin. **BUILT.**
2. **Integrations under super admin** - Devin msg 2117. **BUILT.**
3. **G** extend the existing integration panel to pharmacy, EHR and AI. Small.
4. **F** (config audit) - moderate build, high value given how configurable MEDAXIS now is
5. **Terminology**: Offering -> Product, plus a Program tier, user-facing language first
6. **D-routing** as a selectable policy, never a silent cutover
7. **E** (state visit policy) - its own project
8. **H**, **I** - after clarification

~~B, C, D-view~~ need no work: they already exist.

---

## 5. Status

- **A is built**: `admin_clinician` pivot, `visibleTo()` scopes on cases and clinicians, applied to
  the admin dashboard, case list, case detail, clinician list and the reassignment picker; the
  `admin` role no longer holds every permission; a super-admin-only route group; nav gated; and a
  doctor-assignment UI on the admin detail page.
- **Integrations under super admin is built**: partners (they carry Healthie credentials), webhook
  logs, API guides, settings and the triage rule set.
- Everything else above is unbuilt.

Open question remaining: **H**, what a "protocol" should mean in MEDAXIS.

**Verification note.** None of the PHP on this branch has been executed or linted: the machine it
was written on has no PHP, Composer or MySQL. Static checks only. The correction notice at the top
of this document is the reason to take that limitation seriously.
