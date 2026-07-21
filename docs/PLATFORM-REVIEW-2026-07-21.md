# Platform review, 2026-07-21

Requested by Devin (msg 2151): fix the four defects, and read every file of the app.

## What "read every file" actually meant here

Be clear about the shape of this, because "reviewed" can mean very different depths.

The app is **303 files, ~38,000 lines** (`app` 124 files, `resources/views` 75 files and 19.5k
lines, `database` 75, `config` 18, `routes` 4, `tests` 7).

- **Read closely, line by line:** `routes/` (all 4 files), `app/Models/`, `app/Http/Middleware/`,
  `app/Http/Controllers/Web/Admin/`, `app/Http/Controllers/Web/Auth/`,
  `app/Http/Controllers/Api/Partner/`, `app/Services/Routing/`, the seeders, and every migration
  added by the two branches.
- **Swept systematically for defect classes across all 303 files:** unescaped Blade output, raw SQL
  interpolation, hardcoded credentials, mass assignment from request, routes missing auth or role
  middleware, and cross-tenant lookups missing an ownership check.
- **Not read prose-by-prose:** the bulk of `resources/views` (19.5k lines, mostly markup) and the
  vendor-shaped config files. These were swept, not studied.

**Nothing was executed, tested or linted.** There is no PHP, Composer or MySQL on this
workstation. Edited files were checked for bracket balance with a small script, which is not a
parser and proves only that no edit was grossly malformed.

---

## The four defects: fixed

Commit `571f735`.

### 1. `/ma-portal/*` was behind `auth` with no role gate
`MaPortalController` reads real records. `practitioner()` loads open cases with patient
demographics, recorded intake answers, clinical notes and messages. `superAdmin()` lists every
user in the install with email and roles. Behind `auth` alone, a **partner login** (an external
storefront operator) could read all of it by typing the URL.

Each route is now gated to the tier whose surface it previews: practitioner
`clinician|admin|super_admin`, admin `admin|super_admin`, super-admin `super_admin`. The bare
`/ma-portal` redirect stays on `auth` because it only forwards.

### 2. `AdminUserController::toggleActive()` wrote a column that does not exist
`users` has no `is_active`, and it was not in `User::$fillable` either, so Eloquent's
mass-assignment guard dropped it before any SQL was built. No error, no exception, and the UI
still flashed "Admin account status updated." Deactivating an admin did nothing and said it
worked.

Added the column (additive, defaults `true`, so nobody is locked out on deploy), plus `$fillable`
and a boolean cast.

**The column alone would have been the same lie in a tidier form**, so `LoginController` now
honours it. Two details that were deliberate:

- The check runs **after** a successful `Auth::attempt`, not before. Refusing earlier would tell
  an anonymous visitor which addresses exist and which are switched off, so the generic
  credential error is reused.
- A **last-active-super-admin guard** was added. Blocking self-toggle does not cover A
  deactivating B when B is the only other super admin left, which would lock everyone out of the
  super-admin-only screens, including the one that grants the role back.

### 3. Clinician portal dereferenced null for admins
Routes allow `role:clinician|admin`, but an admin has no `Clinician` record, and the controllers
do `Auth::user()->clinician` then `->id` on the result. That is 11 call sites in
`Clinician\CaseController` alone, plus the dashboard and notification controllers.

Guarding each call site would fix today's list and miss tomorrow's, so the check moved to the
door as `clinician.portal` middleware, the same shape as the existing `partner.portal`. An admin
is redirected to their own dashboard with an explanation rather than 403'd, because reaching
those URLs is a wrong turn, not an intrusion.

### 4. No offering route was behind `role:super_admin`
`RolesAndPermissionsSeeder` grants `admin` the single permission `view offerings` and withholds
create, update and delete, so it described a restriction that no route enforced.

Reads stay open to both admin tiers (a Doctor Admin needs to see what is prescribable); writes are
now `role:super_admin`.

**Caught in my own fix before committing:** moving `GET /create` inside the write group placed it
after `GET /{id}`, which captures the literal string "create" as an id. It is registered ahead of
`/{id}` again, with a comment explaining why it has to stay there.

---

## New findings from the full read

### A. Cross-tenant questionnaire read on the partner API (worth acting on)

`GET /api/partner/questionnaires/{uuid}` →
`Api/Partner/QuestionnaireController::show()`:

```php
$questionnaire = Questionnaire::with([...])
    ->where('uuid', $uuid)
    ->where('is_active', true)
    ->firstOrFail();
```

There is no partner check, and `questionnaires.partner_id` exists (nullable), so a questionnaire
**can** be partner-owned. Partner A holding Partner B's questionnaire UUID can read B's full
intake form: every question, slug, key, option set and disqualification rule. It is the only one
of the nine partner API controllers with zero partner-scoping references.

Mitigated by the UUID being unguessable, so this is not remotely enumerable. But UUIDs travel, in
logs, support tickets and integration docs, and "you need to know the id" is not an authorization
model.

Suggested fix, matching how the other controllers scope:

```php
->where('uuid', $uuid)
->where('is_active', true)
->where(fn ($q) => $q->whereNull('partner_id')
                     ->orWhere('partner_id', $request->partner->id))
```

Left unfixed deliberately: it is outside the four defects I was asked to close, and it changes an
external API's behaviour, so it should be your call rather than a silent scope expansion.

### B. Stored XSS in the API guide screens (low severity)

**Correction to my first report of this, which overstated it.** I originally said four values were
interpolated raw. On a closer read, two of them are not: `$cond` and `$valueCell` are built with
raw interpolation but then emitted through `e(...)`, so `depends_on_operator`,
`depends_on_value` and the option values were already escaped at output.

The genuine gaps were narrower:

- `$typeBadge` is emitted **unescaped** (`'<td>' . $typeBadge . '</td>'`) and contains `$q->type`
  interpolated raw in two of its four branches. This is the real one.
- `$q->step_number` was emitted unescaped. An integer column, so low risk, but free to fix.
- `$cond` pre-escaped `$depQ->key` and was then escaped again at output, so a key containing `&`
  rendered as a literal `&amp;` on screen. A display bug rather than a security one.

All three are fixed. Severity was always limited: question content is admin-authored (partners are
read-only on questionnaires, confirmed in `routes/api.php`) and these pages are super-admin-only
after fix 1, so the reach was admin to admin.

### C. `json_encode` without `JSON_HEX_TAG` into a script block (low severity)

`QuestionnaireFormController` builds `$postMessagePayload` with plain `json_encode`, and
`forms/result.blade.php` emits it as `var payload = {!! $postMessagePayload !!};`. A `</script>`
sequence inside any encoded value would break out of the block. The payload currently carries a
system token, two booleans and `$disqualifiedOn`, all of which are system or admin controlled
rather than patient-supplied, so this is hardening rather than a live hole. Add
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.

**This is a public, unauthenticated page** (`/forms/{uuid}`), which is why it is worth fixing even
at low severity: it is the one surface here an anonymous visitor reaches.

### D. Indentation that reads as a security bug (fixed, cosmetic)

`Route::post('/{uuid}/notes', ...)` sat at column 0 inside the clinician group, so a
`grep '^Route::'` lists it alongside the genuinely ungrouped public routes. It **is** correctly
inside the group, because PHP closures do not care about indentation, and it carries the full
`auth` + `role` + `clinician.portal` middleware. Re-indented so the next person auditing this file
does not have to re-derive that.

### What came back clean

- **No hardcoded credentials** anywhere in `app/`, `config/`, `routes/`, `database/`. Everything
  goes through `env()`/`config()`.
- **No mass assignment from `$request->all()`.** Every controller validates first.
- **No SQL injection.** The four `selectRaw`/`whereRaw` sites interpolate no user input; the one
  with a variable binds it as a parameter.
- **Partner API scoping** is present and consistent in eight of nine controllers; only
  `QuestionnaireController` is missing it (finding A).
- **Public routes** are the intended four: login, password reset, the questionnaire form renderer,
  and the root redirect.

---

## All three fixed (Devin msg 2153)

Commit follows the review. Same caveat as everywhere else: **not executed, not linted.**

### A, done, and deliberately wider than the two-line version I first proposed

The obvious fix is `partner_id IS NULL OR partner_id = caller`. That closes the hole and **would
have broken a legitimate case**: an admin can attach any questionnaire to any offering through
`offering_questionnaire`, including one owned by a different partner, and that composition is
intentional. A two-clause scope would cut a partner off from a form their own offering requires.

So the scope has a third clause: **or the questionnaire is attached to an offering the caller
owns.**

Why that provably does not lose legitimate access: the only way a partner learns a questionnaire
uuid through this API is `GET /offerings/{id}/questionnaires`, which is already scoped to the
caller's own offerings. Every uuid that endpoint can hand out is therefore covered by clause 3.
The only thing the fix removes is access to questionnaires the caller has no link to at all,
which is exactly the hole.

`linkedQuestionnaire` is deliberately **not** scoped. A linked questionnaire is an
admin-configured composition of one form into another, so a link crossing partners is the feature
working. Restricting it would break multi-part intake.

Also qualified the column as `offerings.partner_id`: the `whereHas` subquery joins
`offering_questionnaire`, and an unqualified `partner_id` there is asking to become ambiguous the
day someone adds that column to the pivot.

### B, done
Escaped `$q->type` inside `$typeBadge` (the real gap), escaped `step_number`, and removed the
double-escape on `$depQ->key`. See the correction above: this was narrower than I first reported.

### C, done
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` on the payload. `JSON_HEX_*` escapes
to `\u00XX`, which JavaScript parses back to the identical string, so the receiving window sees a
byte-for-byte unchanged payload. No consumer changes.

## What "make sure it doesn't break anything" could and could not cover

**Could, and did:**
- Traced every caller of the one signature that changed (`QuestionnaireController::show` now takes
  `Request $request` first). One route references it; Laravel resolves `Request` by type-hint and
  `{uuid}` by name, so the route needs no change.
- Confirmed `Questionnaire::offerings()` exists (`belongsToMany` via `offering_questionnaire`)
  before relying on it in `whereHas`. A missing relation would have thrown at runtime.
- Reasoned through the access paths above to show clause 3 preserves everything discoverable.
- Bracket-balance checked every edited file. The two Blade files are unbalanced, but they were
  **already** unbalanced at HEAD (they mix HTML and CSS braces), and the signature is byte-identical
  before and after, so the edits are structurally inert.
- Read the final diff line by line: 4 files, escaping and scoping only.

**Could not:** run the app, run the test suite, or execute a single line of PHP. There is no PHP,
Composer or MySQL here. Nothing above is a substitute for someone running this.

**The one thing to check first when it does run:** log in as a partner whose offering uses a
questionnaire owned by a *different* partner, and confirm
`GET /api/partner/questionnaires/{uuid}` still returns it. That is the exact case clause 3 exists
for, and the one a two-clause fix would have silently broken.
