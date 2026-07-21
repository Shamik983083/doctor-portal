# Merge readiness: `design/ma-portal-shell-preview` and `compliance/rxos-laws`

Written 2026-07-21 for the MEDAXIS dev team, at Devin's request.

## Read this first: what has and has not been verified

**Nothing in either branch has been executed, tested or even linted.** This workstation has no
PHP, no Composer and no MySQL. Every statement below comes from reading the code and from git,
not from running it. Anything described as "should" is a reading, not an observation.

What IS verified, by command and not by assumption:

| Check | Result |
|---|---|
| Both branches merge into `main` without conflict | Yes, `git merge-tree` reports no conflict markers |
| Both branches merge into **each other** without conflict | Yes, zero conflict markers |
| `origin/main` and `origin/staging` moved during this work | No, both still `b01f414` |
| Any workflow run fired from either branch | No |
| Migrations that auto-run on merge | 6, all additive, `dropIfExists` only in `down()` |

## Branch summary

| | `design/ma-portal-shell-preview` | `compliance/rxos-laws` |
|---|---|---|
| Commits ahead of main | 19 | 1 (`0549856`) |
| Files changed | 62 | 4 |
| Behind main | 0 | 0 |
| New migrations | 5 | 1 |
| Changes live behaviour | Yes, see below | Yes, significantly |

Only one file is touched by both: `app/Models/Clinician.php`. Git merges it cleanly because the
design branch never modified the method the compliance branch rewrote. **Merging in either order
produces the same file.** That was checked, not assumed.

---

## THE ONE THING MOST LIKELY TO BITE

**Merging both branches does NOT seal the licence gate on the routing path, even though it looks
like it does.**

`compliance/rxos-laws` rewrites `Clinician::isLicensedInState()` so an empty `licensed_states`
returns `false` instead of `true`. That is the Law 4 seal, and it is correct.

But `app/Services/Routing/EligibilityEvaluator.php` on the design branch never calls that method
when licensure is blank. It branches first:

```php
$hasRecordedLicensure = ! empty($clinician->licensed_states);

if (! $hasRecordedLicensure) {
    if ($requireRecordedLicensure) {          // defaults to FALSE
        $reasons[] = self::LICENSURE_NOT_RECORDED;
    }
    // else: falls through as licensed
} elseif (! $clinician->isLicensedInState($state)) {
    $reasons[] = self::RESIDENCE_STATE_LICENSE_MISSING;
}
```

So after merging both branches:

- Everywhere else in the system, a clinician with no recorded licensure is blocked.
- In **case routing**, which decides which doctor receives which patient, that same clinician is
  still treated as licensed, unless someone turns on `requireRecordedLicensure` on the routing
  policy. It defaults to `false`.

The flag was deliberate and is documented in `docs/integrations/ROUTING.md` 2.3: it exists so
activating a routing policy does not instantly block every doctor with blank licence data. That
reasoning was sound when the model helper was still fail-open. Once the compliance branch lands,
the two halves disagree, and the safe-looking default is the one that leaves the hole open on the
most important path.

**Recommendation:** after both branches are merged and `licensure:audit` is clean, flip
`requireRecordedLicensure` to `true` on the active routing policy and consider changing the
default. Do not merge and assume Law 4 is closed platform-wide, because it is not.

---

## Changes that alter live behaviour on merge

These are the ones to merge deliberately, not incidentally.

### 1. `RolesAndPermissionsSeeder` demotes every existing admin
`admin` previously received `Permission::all()`, making it identical to `super_admin`. It now
receives a scoped list. `syncPermissions()` **replaces** a role's permissions, so re-running the
seeder is a live permission change: every existing admin loses partners, webhooks, system config
and admin management. That is the intended design (Devin msg 2117), but it should be a decision,
not a surprise. **The seeder does not run itself on deploy.** It changes nothing until someone
runs it.

### 2. The licence gate fails closed
`isLicensedInState()` returns `false` for empty licensure and for a blank patient state.
**`php artisan licensure:audit` must be run and must exit 0 BEFORE this reaches an environment
with real data.** It reports which clinicians stop being assignable and which open cases stop
being actionable. Skipping it is how a clinic discovers the change at 8am, from a doctor who
cannot open their own queue.

### 3. The unlicensed-assignment fallback is gone
`CaseAutoAssigner` previously had a path that could assign a case to a clinician not licensed in
the patient's state. It was removed. Correct, and it means some cases that would previously have
been auto-assigned will now sit unassigned instead. That is the right failure, but it is visible.

### 4. Six migrations run automatically
`artisan migrate --force` fires on push to `main` and `staging`. All six are additive: five
`create table` (`ai_instruction_sets`, `ehr_records`, `partner_ehr_settings`, `admin_clinician`,
`routing_policies`) plus `clinician_partner`. No column is dropped or altered outside `down()`.

`clinician_partner` also **backfills**: every active clinician is granted every active partner,
reproducing today's behaviour exactly so nothing stops working the moment it runs. The insert is
chunked. Tightening access from there is a deliberate admin action.

---

## Open defects found while reviewing, not fixed

| # | Where | What |
|---|---|---|
| 1 | `routes/web.php:52` | `/ma-portal/*` is behind `auth` with **no role gate**. Any authenticated user, including a partner login, can open `/ma-portal/super-admin` (every user's name, email and role) and `/ma-portal/practitioner` (case data, patient demographics, intake answers, clinical notes). Deliberate when it was a static showcase; it now reads real records. One line. |
| 2 | `AdminUserController::toggleActive()` | Writes `is_active` to `users`. That column does not exist and is not in `User::$fillable`, so mass assignment drops it silently and the UI reports "Admin account status updated." A deactivate button that does nothing. |
| 3 | `Web/Clinician/CaseController.php` | Route allows `role:clinician|admin`, but the controller does `Auth::user()->clinician` throughout and an admin has no clinician record. Most call sites then hit `->id` on null. Not a security hole; 500s. Line 772 already uses `?->`. |
| 4 | Offering routes | No offering route sits behind `role:super_admin`, so the read-only-catalog split shown in the design preview has no server-side enforcement yet. |

Item 1 is the only one worth treating as urgent.

---

## Suggested merge order

1. **`compliance/rxos-laws` first.** One commit, four files, no dependency on the design branch.
   Run `licensure:audit` before it reaches real data.
2. **`design/ma-portal-shell-preview` second.** Larger, and its routing code is what needs the
   follow-up in the section above.
3. **Then** flip `requireRecordedLicensure`, and decide separately when to re-run the seeder.

Reversing 1 and 2 produces an identical tree. The order above is about what you have to think
about at each step, not about correctness.

## What is NOT in either branch

The design preview at `docs/design-preview/` is a static HTML file. **No Blade has been written
for any of it.** The two-tier admin screens, the Medications catalog, the clinician dashboard
queue and the routing screen exist as a proposal for sign-off, not as an implementation. The
drug/variant/partner model shown there needs a `drugs` table, a `drug_variants` table and a
`drug_partner` pivot that do not exist; `offerings.partner_id` is currently a non-nullable FK,
which is exactly the duplication that model is meant to remove.
