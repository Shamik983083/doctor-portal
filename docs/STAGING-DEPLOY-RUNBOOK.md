# Staging deploy runbook

**Target:** `staging` branch only, which deploys to `staging.axismd.io`.
**Never main.** Devin, msg 2234: "We're not pushing to main only to staging."

Written 2026-07-22 for the merge of `design/ma-portal-shell-preview` (and, later,
`compliance/rxos-laws`) into `staging`.

---

## 0. What you are deploying

| Branch | Ahead of staging | Size | Fast-forward? |
|---|---|---|---|
| `design/ma-portal-shell-preview` | 23 commits | 72 files, +10,332 / -151 | Yes, 0 behind |
| `compliance/rxos-laws` | 1 commit | 4 files, +420 / -2 | Yes, 0 behind |

Both branches contain all of `staging`, so neither needs a rebase and neither
conflicts with it. They have been test-merged against each other in a scratch
worktree: clean, no conflicts, no duplicate methods.

The only commit `staging` lacks against `main` is `a316bb5` (DemoDataSeeder,
AmeriLean tenant products). Neither branch touches it.

### Honest status of verification

**None of this has been executed.** There is no PHP, Composer or MySQL on the
machine that wrote it. The unit tests in `tests/Unit/` have never been run,
including the scoping tests cited below. Migrations have never been applied
anywhere. The only automated check performed was a delimiter-balance scan.

Section 4 is therefore not a formality. It is the first time this code runs.

---

## 1. Before you touch anything

1. **Dump the staging database.** Migrations ship a working `down()`, but a
   `down()` is not a restore. This is the only real undo.
   ```bash
   mysqldump -u USER -p STAGING_DB > staging-pre-merge-$(date +%F).sql
   ```
2. **Record the rollback target.** `staging` is currently `b01f414`.
3. **Count who is about to be affected.** Every one of these users gets an empty
   admin console until step 5:
   ```sql
   SELECT u.id, u.name, u.email
   FROM users u
   JOIN model_has_roles r ON r.model_id = u.id
   JOIN roles ro ON ro.id = r.role_id
   WHERE ro.name = 'admin';
   ```
4. **Decide the answer to step 5 now, not later.** Either you are adopting the
   two-tier admin (populate `admin_clinician`) or you are not (give those users
   `super_admin` instead). Deciding after the deploy means a window where the
   console looks broken.

---

## 2. Merge and push

```bash
git fetch origin
git checkout staging
git merge --ff-only origin/design/ma-portal-shell-preview
git push origin staging
```

`--ff-only` is deliberate: if it refuses, something has changed since this was
written and the assumptions in this document need rechecking before you force it.

Pushing triggers `.github/workflows/deploy-staging.yml`, which pulls, runs
`composer install --no-dev`, runs `artisan migrate --force`, and rebuilds the
config, route and view caches.

**Merge the design branch alone.** Do not bundle `compliance/rxos-laws` in the
same push. See section 7.

### Migrations that will run

Seven, all additive, all with a working `down()`. No column drops, no data
rewrites.

| Migration | Note |
|---|---|
| `create_ai_instruction_sets_table` | new |
| `create_ehr_records_table` | new |
| `create_partner_ehr_settings_table` | new |
| `create_admin_clinician_table` | **no backfill, see section 5** |
| `create_routing_policies_table` | new |
| `add_is_active_to_users_table` | defaults TRUE, nobody is locked out |
| `create_clinician_partner_table` | compliance branch only; backfills every active clinician x active partner |

The two that could have locked people out were checked specifically:
`add_is_active_to_users` defaults to `true` and `LoginController` reads
`is_active ?? true`; `clinician_partner` backfills to today's behaviour.

---

## 3. Immediately after the deploy

**Restart the queue workers by hand.**

```bash
php artisan queue:restart
```

The staging workflow does not do this, though the production one does. Until it
runs, workers keep executing the pre-merge code against the post-merge schema.
This is a gap in `deploy-staging.yml` and should be fixed there separately.

---

## 4. Smoke test

Nothing here has ever run. Work down the list in order.

**Access control, the part that matters most:**

- [ ] A **partner** login hits `/ma-portal/practitioner` and `/ma-portal/super-admin`. Both must **403**. This is the cross-tenant PHI leak closing, and it is worth confirming by hand rather than trusting.
- [ ] An **admin** login hits `/clinician/dashboard`. Must **redirect** to the admin dashboard with a message, not throw a 500.
- [ ] An **admin** hits `/admin/partners`. Must **403** (super admin only now).
- [ ] A **super_admin** hits `/admin/partners`. Must load.

**Each role loads its own surfaces:**

- [ ] `super_admin`: dashboard, cases, clinicians, partners, offerings, routing
- [ ] `admin`: dashboard, cases, clinicians, patients
- [ ] `clinician`: dashboard, queue, my-cases, open a case, add a note
- [ ] `partner`: dashboard, cases, patients

**The waiting-case fix (section 6):**

- [ ] Log in as the Doctor Admin. The case list shows **waiting cases** as well as their own doctor's cases.
- [ ] The same list shows **nothing** belonging to the doctor they are not over.
- [ ] Open a waiting case from that list. The patient record must open, not 404.

**End to end:**

- [ ] Submit the public intake form. A case appears in the clinician queue.

---

## 5. Populate `admin_clinician`, or the console stays empty

`admin_clinician` ships with **no backfill**, and a Doctor Admin over no doctors
sees nothing by design. On a fresh deploy that is every existing `admin` user, so
until this step runs the admin console shows zeros everywhere. It is working as
designed and it looks exactly like a broken merge.

Note this is the opposite posture to `clinician_partner` in the compliance
branch, which backfills to preserve behaviour. The inconsistency is real and
worth settling as a product decision.

Two ways forward, pick one:

- **Adopting two tiers:** insert a row per admin per doctor they are over, or use
  the seeder in section 6.
- **Not adopting yet:** give those users `super_admin` and leave the tier unused.
  Nothing else in the branch depends on it.

---

## 6. Temporary staging data

```bash
php artisan db:seed --class=Database\\Seeders\\StagingPreviewSeeder
```

Idempotent, safe to re-run, and it refuses to run when `APP_ENV=production`.

**This is a seeder and not a migration on purpose.** The deploy workflow runs
migrations automatically but never seeders, so putting fixtures in a migration
would have made them land without anyone asking. The same migration set runs on
production when this eventually reaches `main`, which would mean demo doctors and
fake patients in a live clinical system. Fixtures get run deliberately, by a
person, on a box they chose.

It requires `RolesAndPermissionsSeeder` to have run first, since it assigns roles.

| Account | Password | Role |
|---|---|---|
| `doctor.admin@staging.axismd.io` | `staging-preview-2026` | `admin`, over Dr. Alvarez only |
| `dr.alvarez@staging.axismd.io` | `staging-preview-2026` | `clinician`, licensed TN/CA/NY |
| `dr.okafor@staging.axismd.io` | `staging-preview-2026` | `clinician`, licensed TN/TX/FL |

It creates 3 waiting (unassigned) cases, 2 assigned to Alvarez, 2 assigned to
Okafor, and an ACTIVE v1 routing policy. Logged in as the Doctor Admin you should
see the 3 waiting plus Alvarez's 2, and none of Okafor's.

Weak passwords are deliberate: `axismd.io` staging is demo mode with no real
patients. **If that ever stops being true, delete this seeder rather than
hardening it.**

### A fixture bug fixed in the same commit

`DemoDataSeeder` wrote `licensed_states` as a flat `['CA','NY','TX','FL','WA']`.
Every reader of that column does `collect($states)->pluck('state')`, which on a
flat list yields nulls and matches no state at all, so the demo clinician has
always been licensed **nowhere** by `isLicensedInState()`. It went unnoticed
because `CaseAutoAssigner` falls back to an unlicensed clinician when nobody
matches. Once the licence gate is sealed (section 7) that fallback goes and this
doctor would have been blocked from every case, reading as a broken seal rather
than a bad fixture. Now written in the shape the admin UI produces.

---

## 7. The compliance branch, only after 1 to 6 are stable

`compliance/rxos-laws` seals the licence gate: blank `licensed_states` changes
from "licensed everywhere" to "licensed nowhere". That is correct, and it will
take a doctor's whole queue away if their licence data was never filled in.

```bash
php artisan licensure:audit
```

Exits 1 while there are gaps and lists every clinician who would stop being
assignable. **Fill every gap. Only merge when it exits 0.**

Then merge and push as in section 2.

### Merging it does not finish the job

`EligibilityEvaluator` checks `$hasRecordedLicensure` before it ever reaches the
sealed method, and falls through as licensed unless `requireRecordedLicensure` is
set. That flag defaults to **false** (`app/Models/RoutingPolicy.php`).

So after both merges, every path blocks an unlicensed clinician **except case
routing**, which is the path that decides which doctor gets which patient. To
actually close it, flip `require_recorded_licensure` to true on the active
routing policy after the audit passes.

Also note `mayWorkCase()` and `hasAccessToPartner()` on that branch have **zero
call sites**. The storefront grant model exists but gates nothing yet.

---

## 8. Confirm nothing calls out

The AI and pharmacy-dispatch integrations both default to no-network mock
adapters behind two flags each. Confirm on staging:

```
AI_ASSIST_ENABLED=false            AI_ASSIST_BAA_CONFIRMED=false
PHARMACY_DISPATCH_ENABLED=false    PHARMACY_DISPATCH_SANDBOX_VALIDATED=false
```

**Do not set `OPENAI_API_KEY` on staging at all.** Every clinical-note draft
sends PHI to the provider; what makes that lawful is an executed BAA on the
account owning the key. That is a legal signoff, not an engineering one.

---

## 9. Rollback

```bash
git checkout staging
git revert -m 1 <merge-commit>
git push origin staging
```

The workflow does `git reset --hard`, so the code reverts on the next deploy.

**Migrations do not reverse themselves.** Either `php artisan migrate:rollback`
the batch, or restore the dump from section 1. The new tables are additive and
harmless if left in place; the one column added to an existing table
(`users.is_active`) defaults true and is ignored by pre-merge code.

---

## Known issues carried into this deploy

Present on `staging` today and **not** fixed by either branch:

1. **No case-ownership check on any clinician action.** Any clinician can open,
   prescribe on, approve, cancel and download files from a case assigned to a
   different doctor, and the prescription is written under the acting clinician.
2. **Forgeable webhook signatures.** `SendWebhookJob` signs with
   `$partner->webhook_secret ?? ''`, so a partner with no secret gets a signature
   over an empty key that anyone can reproduce. Also `tries = 1`, so a delivery
   marked "retrying" never retries.
3. **Deploy token stored in plaintext.** Both workflows run
   `git remote set-url origin https://user:${DEPLOY_TOKEN}@github.com/...`, which
   writes the PAT into `.git/config` on the server permanently.
4. **Intake state-hold logic is inverted.** `QuestionnaireFormController` holds a
   case when ANY linked offering does not cover the patient's state; the comment,
   and the intent, is to hold when NONE does.
5. **`deploy-staging.yml` does not run `queue:restart`.** See section 3.
