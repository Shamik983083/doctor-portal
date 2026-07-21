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

`resources/views/admin/guide/weightloss-api.blade.php` and `antiaging-api.blade.php` build table
rows in a helper and emit them with `{!! ... !!}`. Inside, `$depQ->key` is escaped with `e()`, but
`$q->depends_on_operator`, `$q->depends_on_value`, `$q->type` and the joined option values are
interpolated raw.

Severity is limited: question content is admin-authored (partners are read-only on questionnaires,
confirmed in `routes/api.php`), and these pages are now super-admin-only after fix 1. So it is
admin-to-admin. Still, an option value containing a script tag executes on the guide page. Escape
the interpolated values.

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

## Priority

1. **A**, cross-tenant questionnaire read. External API, real tenant isolation, two-line fix.
2. **C**, `JSON_HEX_TAG` on the public form page.
3. **B**, escape the guide table values.

None is an emergency, and none blocks the merge. The four original defects are closed.
