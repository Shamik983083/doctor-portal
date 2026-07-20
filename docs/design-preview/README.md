# Portal shell design preview

A standalone, self-contained mock of the proposed portal shell. Open
`index.html` in any browser, or view the hosted copy (noindex):

<https://livepainfreeagain.com/medaxis-preview/>

## What this is

A **design proposal only**. It is one HTML file with no build step, no
dependencies, and no connection to the Laravel application. Nothing here is
wired to a controller, a route, or the database.

It exists so the look can be agreed **before** anyone edits 56 Blade views.

## What it proposes

1. **The MA-DOCPORTAL design language.** The CSS custom properties at the top of
   `index.html` are lifted verbatim from `MA-DOCPORTAL apps/portal/app/globals.css`
   (`--accent:#2563eb`, `--ink:#172033`, `--bg:#f5f7fb`, and the rest) so the two
   products read as one system. `verify.mjs` asserts those three tokens match, so
   the preview cannot drift from MA's palette without failing.
2. **The sidebar stays, and collapses.** MA-DOCPORTAL uses a top nav and no
   sidebar. This keeps the existing sidebar and adds a rail mode: the toggle in
   the top bar shrinks it from 264px to a 68px icon rail, and on screens under
   820px it becomes an off-canvas drawer instead of crushing the content.
3. **No functional change.** Navigation structure, section grouping, table
   columns and role separation all mirror what the app already does.

## Fidelity: what is real and what is not

**Taken from the real app**, so the shell matches production:

- Sidebar structure and grouping from `resources/views/layouts/{admin,clinician,partner}.blade.php`
  (Management / API & Integrations / Configuration / Super Admin, including which
  items are sub-items).
- Table columns read off the `<th>` rows of each real index view. The clinician
  Case Queue really does carry Triage, Time, Patient, IDV, Sex, Age, BMI,
  Offerings, Video visit, Company, Batch Eligibility, Status.
- Clinician dashboard metric labels from `clinician/dashboard.blade.php`
  (Waiting Queue, My Active Cases, Completed This Month, SLA Status).
- Endpoint paths on the API guide screens from `routes/api.php`.

**Invented for the mock**, and safe to ignore:

- Every row of data. All patient names, emails, phone numbers and case IDs are
  fictional. No real or production data appears anywhere in this file.
- Metric values.

## Clinician Case Queue: a port of MA's practitioner surface

The clinician **Case Queue** is not a restyle of the current queue. It is a port
of MA-DOCPORTAL's practitioner view, from
`apps/portal/components/demo/PractitionerDemo.tsx` and the `queueRows` fixtures
in `apps/portal/lib/demo-fixtures.ts`. Look and behaviour both carry over:

- **The same 20-column quick-look grid**, headers verbatim, with the first four
  columns (select, Triage, Time in Queue, Full Name) pinned so triage and identity
  stay on screen while the rest scrolls sideways.
- **Visible batch blocking with reasons.** Only batch-eligible rows can be
  selected. Yellow, Red and workflow-held rows render a disabled checkbox whose
  tooltip and aria-label state *why*, and the reason also prints under the Batch
  Eligibility pill. Blocking is never silent.
- **Triage and workflow holds as separate axes**, which is MA's design. `demo-005`
  is the case that proves it: Green triage, still blocked, because a
  `SYNCHRONOUS_VIDEO_VISIT_REQUIRED` hold is active.
- **Quick review drawer**: AI draft summary explicitly labelled provider-assist
  only, each statement carrying the intake answer keys it was composed from, a
  "View source answers" toggle, triage findings, active workflow holds, and the
  three provider actions with Approve disabled (and the reason shown) when the
  case is not eligible.
- **Batch preflight** counts the selection and names the real endpoint,
  `POST /v1/cases/batch/preflight`, while submitting nothing.

Selection is guarded twice: the handler refuses any non-eligible id, and
`onlyEligible()` filters the resulting set. Either guard alone is sufficient,
which is deliberate. `verify.mjs` pins the *combination* by stripping the
disabled attribute at runtime and clicking a blocked row for real.

## Coverage

28 screens across the three portals: 18 admin, 4 clinician, 6 partner. Detail
and create/edit screens (for example `admin/cases/show`, `clinician/cases/prescribe`,
the questionnaire builder) are **not** mocked yet.

## Verifying it

```bash
node docs/design-preview/verify.mjs                                  # local
LIVE=https://livepainfreeagain.com/medaxis-preview/ node docs/design-preview/verify.mjs
```

Requires Playwright. 49 checks: every nav item in every portal resolves to a
built screen, the rail collapses and restores, the collapsed rail still routes,
the palette matches MA's tokens, there is no horizontal scroll at 390px, and the
clinician queue behaves like MA's practitioner surface (column headers verbatim,
pinned columns genuinely sticky, blocked rows unselectable, select-all taking only
the eligible rows, preflight counting and disabling correctly, the drawer opening
from any row including blocked ones, source answers toggling, and holds rendering).

Four planted breaks were each proven to turn it red before being restored:
dropping the disabled attribute, and removing both selection guards together
(each guard alone is redundant by design, so only the pair fails the suite).

## Status

Not merged, not deployed, not wired in. `main` and `staging` are untouched by
this branch, and no GitHub Actions workflow runs on it (both deploy workflows
trigger only on pushes to `main` and `staging`).

Next step is a decision on the look. Once it is agreed, the Blade work is a
separate change against `layouts/app.blade.php` plus the three portal layouts.
