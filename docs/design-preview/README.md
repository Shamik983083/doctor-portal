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

## Coverage

28 screens across the three portals: 18 admin, 4 clinician, 6 partner. Detail
and create/edit screens (for example `admin/cases/show`, `clinician/cases/prescribe`,
the questionnaire builder) are **not** mocked yet.

## Verifying it

```bash
node docs/design-preview/verify.mjs                                  # local
LIVE=https://livepainfreeagain.com/medaxis-preview/ node docs/design-preview/verify.mjs
```

Requires Playwright. 20 checks: every nav item in every portal resolves to a
built screen, the rail collapses and restores, the collapsed rail still routes,
the palette matches MA's tokens, and there is no horizontal scroll at 390px.

## Status

Not merged, not deployed, not wired in. `main` and `staging` are untouched by
this branch, and no GitHub Actions workflow runs on it (both deploy workflows
trigger only on pushes to `main` and `staging`).

Next step is a decision on the look. Once it is agreed, the Blade work is a
separate change against `layouts/app.blade.php` plus the three portal layouts.
