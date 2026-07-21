# Branch handover: `design/ma-portal-shell-preview`

**For the MEDAXIS dev team. Read this before reviewing or merging.**

58 files changed, ~8,200 insertions. `main` and `staging` are untouched and have been re-verified
after every push (both still at `b01f4143`, Shamik's 2026-07-20 10:02 commit). No GitHub Actions
workflow has ever run from this branch: both deploy workflows trigger only on pushes to `main` and
`staging`.

---

## 0. Read this first: what has and has not been verified

**None of the PHP on this branch has ever been executed, or even linted.** It was written on a
Windows workstation with no PHP, no Composer and no MySQL.

What *was* done:

- every class import resolved against a real file
- braces balanced in every PHP file, Blade directives balanced in every view
- every column, table and method referenced was read out of the migrations and models first,
  not assumed
- vendor specifics (Healthie's auth headers, MA's routing coefficients) taken from their
  documentation and source rather than invented

What that does **not** cover: runtime behaviour, Eloquent semantics, migrations actually applying,
Blade rendering, or the test suite passing. **Your CI is the gate, not this branch.**

Two bugs were caught by that read-the-source discipline and are worth knowing about because they
show the failure mode: `CaseEvent` takes `event_type`/`notes` and not `type`/`note` (the first
version would have silently dropped audit rows), and an early test file used model factories that
do not exist in this repo, so it could never have run.

A third, more serious one is in §4.

---

## 1. What is on this branch, in one line each

| Area | What it does | Risk |
|---|---|---|
| **Design preview** (`docs/design-preview/`) | A standalone HTML mock of the portal, 28 screens. No app code. | None. Not wired to anything. |
| **AI assist** (`config/ai.php`, `app/Services/Ai*`) | Drafts clinical notes and patient replies behind a two-flag gate. | Off by default. No model is called. |
| **AI instruction library** (`ai_instruction_sets`) | Admin-editable prompt guidance and worked examples. | New tables only. |
| **EHR / Healthie** (`config/ehr.php`, `app/Services/Ehr*`) | Builds a chart-record payload on approval, per-company credentials. | Off by default. Nothing is sent. |
| **Two-tier admin** (`admin_clinician`) | Super admin vs Doctor Admin scoped to specific doctors. | **Changes permissions.** See §3. |
| **Case routing** (`app/Services/Routing/*`) | MA's five routing modes, versioned. | **Changes assignment.** See §4. |

---

## 2. Migrations (they run automatically on merge)

`.github/workflows` runs `artisan migrate --force` on any push to `main` or `staging`, so these
execute the moment this is merged. All four are **additive**: new tables only, no changes to
existing ones, no data destroyed.

| Migration | Creates |
|---|---|
| `..._create_ai_instruction_sets_table` | `ai_instruction_sets`, `ai_instruction_examples` (+ seeds 3 default sets) |
| `..._create_ehr_records_table` | `ehr_records` |
| `..._create_partner_ehr_settings_table` | `partner_ehr_settings` |
| `..._create_admin_clinician_table` | `admin_clinician` |
| `..._create_routing_policies_table` | `routing_policies` (+ seeds v1 = current behaviour, ACTIVE) |

---

## 3. The permission change. Review this deliberately.

**Before this branch, `admin` and `super_admin` were identical.** `RolesAndPermissionsSeeder`
granted `admin` `Permission::all()`, so the two roles existed in name only.

Now `admin` is a **Doctor Admin**: operational permissions over the doctors they are assigned, and
**not** `manage partners`, `manage webhooks`, `manage system` or `manage admins`.

**Re-running the seeder demotes every existing admin.** That is the intent, but it is a live
permission change, so run it knowingly.

Scoping is by **doctor**, not by storefront. This is a deliberate divergence from MA (which scopes
admins to storefronts) because the ask was an admin "over specific doctors".

Applied to: the admin dashboard (every stat and aggregate), case list, **case detail**, clinician
list, the case filter dropdown, and the reassignment picker.

Two design points worth preserving if you refactor:

- **The case detail page is scoped, not just the list.** A scoped index with an open detail page is
  one shared URL away from being no access control at all. Out-of-scope returns 404, not 403, so
  existence is not confirmed either.
- **An admin assigned no doctors sees nothing, not everything.** `whereIn('clinician_id', [])`
  matching zero rows is intentional. The natural-looking `if (! $ids) return $query;` would hand a
  half-configured admin the entire platform.

Integrations moved to super admin only: partners (they carry Healthie credentials), webhook logs,
API guides, SLA settings, triage rules, and case routing. Grouped as one route middleware group so
anything added there inherits the restriction.

---

## 4. The routing change. This is the highest-risk item on the branch.

Full detail in `docs/integrations/ROUTING.md`. The three things that matter:

**4.1 Nothing changes on merge.** The migration seeds an ACTIVE `PRIORITY` policy reproducing
MEDAXIS's existing behaviour. Cases route exactly as before until someone activates another
version.

**4.2 The unlicensed fallback is gone.** The old `CaseAutoAssigner`, on finding nobody licensed in
the patient's state, logged a warning and **assigned an unlicensed clinician anyway** so cases
would not stick. MA hard-blocks that, and so does this. A case with no licensed doctor now waits.
If licence coverage has gaps, cases that previously got assigned will now sit in the queue.
**Check licensed-state coverage and watch the queue.**

**4.3 The licence check is currently vacuous for doctors with no licence data.**
`Clinician::isLicensedInState()` returns **true** when a clinician has no licensed states recorded:
an empty list reads as "licensed everywhere". That is fail-open, and it means 4.2 protects you only
against a doctor licensed in the *wrong* states, not against one with *no* licence data at all,
which is the population most likely to be wrong.

That helper is used elsewhere, so this branch does **not** silently change it. Instead there is a
per-version `requireRecordedLicensure` flag, **defaulting to today's behaviour** so activating a
policy cannot suddenly block every doctor with blank data. The routing admin screen carries this
warning on the page itself.

**Recommended:** populate `licensed_states` for every clinician, then enable the flag.

---

## 5. What is deliberately unfinished

Left as clearly-marked stubs rather than guessed at, because guessing here produces code that
reviews cleanly and does the wrong thing in production:

- **`HealthieEhrAdapter::buildMutation()` throws.** Everything around it is real: per-company
  credentials, transport, Healthie's documented auth headers, GraphQL error handling (they return
  HTTP 200 with an `errors` array, so checking status alone reads failures as successes). Only the
  mutation document is missing, because Healthie's public guides do not publish mutation names or
  field shapes. What must be decided first is listed in `docs/integrations/HEALTHIE-SETUP.md`.
- **No OpenAI credentials are wired.** The account, BAA, stored prompt and vector store are set up
  on OpenAI's side; see `docs/integrations/OPENAI-SETUP.md`. Note that a "custom GPT" built in the
  ChatGPT UI has no API and cannot be called from an application.

---

## 6. Tenant segregation (Healthie)

A hard requirement from the business: **Healthie data must never cross between storefronts.** The
same person arriving through two storefronts is two records, and nothing from the first may be
reused for the second.

Enforced structurally, not by convention:

- every company pushes with its **own** credential; `EhrGatewayManager::resolve()` requires a
  partner id and there is no global key
- the patient identifier sent to Healthie is namespaced `{partner_uuid}:{local_id}`, never the raw
  storefront customer id (two storefronts can legitimately issue the same one)
- three refusals: no company, case/patient company mismatch, and a payload whose company does not
  match the credential in hand

**If you write the Healthie mutation: match on the namespaced external id ONLY. Never fall back to
email, phone or date of birth.** Those are exactly the fields that are identical across storefronts
for the same human, and a fallback match is how two charts silently become one.

---

## 7. Suggested review order

1. `database/migrations/` · four additive migrations, plus the seeded routing v1
2. `RolesAndPermissionsSeeder` · the permission change in §3
3. `app/Models/User.php` + `PatientCase::scopeVisibleTo` · the scoping primitive
4. `app/Services/Routing/RoutingStrategy.php` · pure, no I/O, fully unit-tested
5. `app/Services/Routing/EligibilityEvaluator.php` · §4.3 lives here
6. `app/Services/EhrRecordService.php` · payload construction and the segregation guards
7. Everything else

## 8. Suggested merge order

The two config surfaces (AI, EHR) are inert with shipped defaults and can merge whenever.
The permission change (§3) and the routing change (§4) are the ones that alter live behaviour and
deserve their own window.

Run `php artisan test` first. Nothing here has been.
