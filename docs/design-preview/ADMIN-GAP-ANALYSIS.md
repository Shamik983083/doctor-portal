# Doctor Admin: what MA-DOCPORTAL has that MEDAXIS does not

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

### B. Storefront workload view

Per storefront: open cases, triage mix (green/yellow/red), active holds, median decision time, and
SLA risk. MEDAXIS's Partners index has counts but not the operational picture.

All of it is derivable from data MEDAXIS already holds. This is presentation, not new modelling,
which makes it the cheapest real win on the list.

### C. Exception center

Buckets of cases needing operational follow-up, where every bucket maps to a real workflow
condition rather than a free-text status. MEDAXIS models each underlying condition already (triage
colour, holds, support flag, SLA deadline); what is missing is the single screen that counts them.

The clinician-side work-queue sidebar in the preview already does exactly this for one provider.
This is the admin-wide version.

### D. Weighted provider load and routing policy

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

### G. Integration health

A single view of each outbound integration and its status. MEDAXIS has webhooks with delivery
records, and now pharmacy dispatch, EHR records and AI assist, each with its own state. Nothing
shows them together.

Cheap, and it gets more useful with every integration added.

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

## 4. Recommended order

1. **A** (tenant scoping) — decide first, everything else inherits it
2. **B**, **C**, **G** — presentation over data MEDAXIS already has, cheapest real wins
3. **F** (config audit) — moderate build, high value given how configurable MEDAXIS now is
4. **D** load view only; routing change decided separately
5. **E** (state visit policy) — scoped as its own project
6. **H**, **I** — after clarification

---

## 5. Status

Gap analysis only. Nothing in this document has been built.

The two open questions that block a clean start are **A** (two-tier admin or not) and **H** (what a
protocol means here). Everything else can proceed once the order is agreed.
