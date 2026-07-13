# HIPAA Compliance Plan — Doctor Portal
**Assessed:** 2026-07-13  
**Stack:** Laravel 12, PHP 8.2, MySQL, Bootstrap 5, Passport (OAuth2)  
**Current verdict:** Not HIPAA compliant. Multiple critical gaps exist.

This document audits every gap found in the actual codebase and gives an exact, ordered implementation plan. Each step includes the file to change, the code to write, and the edge cases to watch for. Follow the steps in order — later steps depend on earlier ones.

---

## Quick Summary of Gaps Found

| Severity | Count | Top Issues |
|---|---|---|
| Critical | 8 | No MFA, no login lockout, no HTTPS enforcement, no audit log, `APP_DEBUG=true`, root DB with blank password, no PHI column encryption, no secure cookie |
| High | 9 | No password complexity, no rate limiting, no file encryption, PHI leaking into logs, no HSTS |
| Medium | 7 | Session not encrypted, 2-hour timeout, no security headers, no data retention policy |
| Low | 2 | 60-min reset token, redundant `age` column |

---

## Phase 1 — Critical Fixes (Do These First)

These must be done before any production deployment. Every item here represents a direct PHI exposure risk.

---

### Step 1 — Turn Off Debug Mode and Tighten Logging

**Why:** `APP_DEBUG=true` causes Laravel to render full stack traces in the browser on any unhandled exception. Those traces include request data, which may contain PHI (patient names, DOBs, diagnoses). `LOG_LEVEL=debug` writes verbose request data including PHI-containing payloads to `storage/logs/laravel.log`.

**File:** `.env`

Change these values in production:
```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
```

**Edge cases:**
- Turning off debug mode will show a generic 500 page instead of a stack trace. Create custom error views in `resources/views/errors/500.blade.php` and `419.blade.php` so users see a polished, non-alarming message.
- `LOG_LEVEL=warning` suppresses info/debug logs. The `CaseAutoAssigner` fallback warning (`Log::warning`) will still be captured — that is correct.
- Never commit `.env` to git. Verify `.gitignore` has `.env` listed (it does).

**Create `resources/views/errors/500.blade.php`:**
```html
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Server Error — Doctor Portal</title></head>
<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f1f5f9;">
<div style="text-align:center;max-width:420px;padding:2rem;">
    <h1 style="font-size:1.5rem;color:#0f172a;">Something went wrong</h1>
    <p style="color:#64748b;">Our team has been notified. Please try again or contact support.</p>
    <a href="/login" style="color:#00B4D8;">Return to login</a>
</div>
</body>
</html>
```

---

### Step 2 — Enforce HTTPS and Add HSTS

**Why:** Without HTTPS enforcement, session cookies (and PHI) travel over plaintext HTTP. HIPAA's Security Rule (§164.312(e)(1)) requires encryption in transit.

**File:** `app/Providers/AppServiceProvider.php`

Add to the `boot()` method:
```php
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    // Existing Passport expiry lines stay here

    // Force HTTPS in non-local environments
    if (config('app.env') !== 'local') {
        URL::forceScheme('https');
    }
}
```

**File:** Create `app/Http/Middleware/SecurityHeaders.php`
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS: trust HTTPS for 1 year, include subdomains
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
```

**File:** `bootstrap/app.php` — register the middleware globally:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

    // existing aliases stay
    $middleware->alias([...]);
})
```

**File:** `bootstrap/app.php` — also add `TrustProxies` if running behind a load balancer or reverse proxy:
```php
$middleware->append(\Illuminate\Http\Middleware\TrustProxies::class);
```

Then create `config/trustedproxy.php` or set in the class:
```php
// app/Http/Middleware/TrustProxies.php  (Laravel 12 uses the built-in class)
// Set in .env:
TRUSTED_PROXIES=*
```

**Edge cases:**
- `URL::forceScheme('https')` only affects URL generation — it does not redirect HTTP requests to HTTPS. That redirect must be handled at the web server level (Nginx/Apache) or via a redirect middleware. Add an Nginx rule: `return 301 https://$host$request_uri;`
- Do not set HSTS until you are confident HTTPS is fully working. A wrong HSTS header can lock users out of the domain for up to a year.
- Local development with XAMPP uses HTTP — the `env !== 'local'` guard prevents breaking local dev.

---

### Step 3 — Fix Session Cookie Security

**Why:** Without the `secure` flag, session cookies are sent over HTTP. Without encryption, session data (which holds the authenticated user ID and CSRF token) is stored as plaintext in the `sessions` table.

**File:** `.env`

```
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_LIFETIME=30
```

**Edge cases:**
- `SESSION_SECURE_COOKIE=true` with HTTP (local dev) will break login — the browser will refuse to send the cookie. Guard this with an environment check. In `.env` for local dev keep it `false`; for production set it `true`. Never share the same `.env` between environments.
- `SESSION_ENCRYPT=true` encrypts using `APP_KEY`. If `APP_KEY` is rotated without migrating sessions, all active sessions are instantly invalidated (users are logged out). Rotate the key during a low-traffic window and warn users.
- Reducing `SESSION_LIFETIME` to 30 minutes will log out clinicians mid-review. Communicate the change and consider adding an activity-based heartbeat ping (a silent AJAX call to a `/ping` route that touches the session every 25 minutes) so active users are not interrupted.

---

### Step 4 — Add Login Rate Limiting and Account Lockout

**Why:** With no throttle on `POST /login`, an attacker can brute-force any account. HIPAA §164.312(d) requires unique user identification and access controls.

**File:** `routes/web.php`

```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1')   // 5 attempts per minute per IP
    ->name('login.post');
```

**File:** `app/Http/Controllers/Web/Auth/LoginController.php`

Replace the `login()` method:
```php
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;

public function login(Request $request)
{
    $credentials = $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $key = 'login.' . Str::lower($request->input('email')) . '.' . $request->ip();
    $limiter = app(RateLimiter::class);

    if ($limiter->tooManyAttempts($key, 5)) {
        $seconds = $limiter->availableIn($key);
        return back()
            ->withErrors(['email' => "Too many login attempts. Try again in {$seconds} seconds."])
            ->withInput($request->only('email'));
    }

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $limiter->clear($key);
        $request->session()->regenerate();

        // Audit log — Step 7 will implement the AuditLog model
        // AuditLog::record('login', Auth::id(), $request->ip());

        return redirect()->intended($this->redirectAfterLogin());
    }

    $limiter->hit($key, 60); // lockout window: 60 seconds

    return back()
        ->withErrors(['email' => 'Invalid credentials.'])
        ->withInput($request->only('email'));
}
```

Also throttle the API token endpoint:

**File:** `routes/api.php`

```php
Route::post('/partner/auth/token', [AuthController::class, 'token'])
    ->middleware('throttle:10,1');
```

**Edge cases:**
- The throttle key combines email + IP. A legitimate user on a shared IP (office NAT) could be locked out by a colleague's failed attempts. Consider keying by email only for the per-user lockout, and IP only for the anti-scraping guard.
- The `throttle:5,1` middleware on the route AND the manual `RateLimiter` create two independent counters. Keep only one approach to avoid confusion. The manual `RateLimiter` approach above is more flexible (per-email+IP) and should be preferred; remove `throttle:5,1` from the route if you implement the manual approach.
- Clear the rate limiter on successful login (`$limiter->clear($key)`) so a user who recovers their correct password is not throttled again immediately.

---

### Step 5 — Add Multi-Factor Authentication (MFA)

**Why:** HIPAA §164.312(d) requires "procedures to verify that a person or entity seeking access to ePHI is the one claimed." Passwords alone do not meet this standard for clinical systems in modern guidance.

**Install:**
```bash
composer require pragmarx/google2fa-laravel
composer require bacon/bacon-qr-code
```

**Implementation outline** (full code would span several files — this is the exact plan):

1. **Migration** — add `two_factor_secret` (nullable string) and `two_factor_confirmed_at` (nullable timestamp) to the `users` table.

2. **User model** — add the two columns to `$fillable`. Add a cast: `'two_factor_secret' => 'encrypted'` (uses Laravel's built-in encrypted cast, auto-encrypts the TOTP secret at rest).

3. **MFA setup flow (admin and clinician users only initially):**
   - `GET /mfa/setup` — generate a TOTP secret, show QR code
   - `POST /mfa/setup` — verify the user's first OTP code, set `two_factor_confirmed_at`
   - `GET /mfa/verify` — shown after password login if MFA is confirmed
   - `POST /mfa/verify` — validate the OTP, complete the session

4. **Middleware** — create `app/Http/Middleware/RequireMfa.php`:
   - If the user has `two_factor_confirmed_at` set but the session key `mfa_verified` is not `true`, redirect to `/mfa/verify`.
   - Apply to `admin.*` and `clinician.*` route groups.

5. **Login flow change** — after `Auth::attempt()` succeeds, check if MFA is enabled. If yes, store `mfa_pending_user_id` in session and redirect to `/mfa/verify` *before* fully authenticating the session.

**Edge cases:**
- If a user loses their authenticator, you need a recovery code path. Generate 8 single-use recovery codes on MFA setup, store them hashed, show them once.
- Do not require MFA for `partner` role on first roll-out if partners are external companies — coordinate the rollout with them separately.
- If `two_factor_secret` uses Laravel's `encrypted` cast, key rotation invalidates all TOTP secrets. Document this in your key rotation runbook.
- Test that the session is NOT fully established (i.e. role-protected routes are inaccessible) during the MFA challenge step.

---

### Step 6 — Create the Audit Log System

**Why:** HIPAA §164.312(b) requires audit controls — hardware, software, and procedural mechanisms that record and examine activity in systems containing PHI. This is one of the most commonly cited gaps in HIPAA audits.

**What must be logged:**
- Successful login / logout (who, when, from what IP)
- Failed login attempts (email attempted, IP)
- PHI record accessed (Patient show, Case show, Prescription view)
- PHI record created, modified, deleted (Patient, ClinicalNote, Message, Prescription)
- File upload and file download
- Password reset requested and completed
- Admin actions (user created, role changed, partner created/deleted)

**Step 6a — Migration:**
```php
// database/migrations/2026_07_13_000001_create_audit_logs_table.php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('event');                     // e.g. 'login', 'patient.viewed', 'case.updated'
    $table->nullableMorphs('subject');           // the record acted upon (patient_id, case_id, etc.)
    $table->unsignedBigInteger('causer_id')->nullable();  // user who performed the action
    $table->string('causer_type')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->json('properties')->nullable();      // before/after values for edits (no raw PHI)
    $table->timestamp('created_at');
});
// No updated_at — audit records are immutable
```

**Step 6b — AuditLog model:**
```php
// app/Models/AuditLog.php
class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public static function record(
        string $event,
        $subjectType = null,
        $subjectId = null,
        array $properties = []
    ): void {
        static::create([
            'event'        => $event,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'causer_id'    => Auth::id(),
            'causer_type'  => Auth::check() ? 'user' : null,
            'ip_address'   => request()->ip(),
            'user_agent'   => substr(request()->userAgent() ?? '', 0, 250),
            'properties'   => $properties,
            'created_at'   => now(),
        ]);
    }
}
```

**Step 6c — Wire up authentication events:**

In `LoginController::login()`:
```php
// On success:
AuditLog::record('auth.login');

// On failure:
AuditLog::record('auth.login_failed', null, null, ['email' => $request->input('email')]);
```

In `LoginController::logout()`:
```php
AuditLog::record('auth.logout');
```

In `ForgotPasswordController::sendResetLink()`:
```php
AuditLog::record('auth.password_reset_requested');
```

In `ResetPasswordController::reset()` on success:
```php
AuditLog::record('auth.password_reset_completed');
```

**Step 6d — Model Observers for PHI changes:**

Create `app/Observers/PatientObserver.php`:
```php
class PatientObserver
{
    public function created(Patient $patient): void {
        AuditLog::record('patient.created', Patient::class, $patient->id);
    }
    public function updated(Patient $patient): void {
        AuditLog::record('patient.updated', Patient::class, $patient->id, [
            'changed_fields' => array_keys($patient->getDirty()),
            // Do NOT log the actual before/after PHI values in properties
        ]);
    }
    public function deleted(Patient $patient): void {
        AuditLog::record('patient.deleted', Patient::class, $patient->id);
    }
}
```

Register in `AppServiceProvider::boot()`:
```php
Patient::observe(PatientObserver::class);
ClinicalNote::observe(ClinicalNoteObserver::class);
Message::observe(MessageObserver::class);
Prescription::observe(PrescriptionObserver::class);
```

**Step 6e — PHI access logging in controllers:**

In `AdminPatientController::show()`:
```php
AuditLog::record('patient.viewed', Patient::class, $patient->id);
```

In `ClinicianCaseController::show()`:
```php
AuditLog::record('case.viewed', PatientCase::class, $case->id);
```

**Step 6f — File access logging:**

In every controller or service method that serves a file for download:
```php
AuditLog::record('file.downloaded', PatientFile::class, $file->id);
```

**Edge cases:**
- Do NOT store actual PHI values (patient name, DOB, diagnoses) inside `audit_logs.properties`. Store only field names that changed (`changed_fields`) and foreign key IDs. The full PHI is recoverable by joining to the main record — there is no need to duplicate it.
- Audit log writes must not block the main request. If your `jobs` table is under load, consider writing audit logs synchronously (not queued) since they are lightweight inserts.
- Audit logs must be protected from modification. Set database-level permissions so the application user can only INSERT, SELECT on `audit_logs` — never UPDATE or DELETE.
- Retention: HIPAA requires documentation to be retained for 6 years. Add a scheduled command to archive (not delete) audit logs older than 6 years to cold storage. Do not hard-delete them.
- Build an admin view at `GET /admin/audit-logs` (role:admin only) to search and export audit logs.

---

### Step 7 — Secure the Database

**Why:** Root MySQL account with a blank password is accessible to any process on the machine. A compromised web server process immediately has full database access.

**Steps:**

1. Create a dedicated database user in MySQL:
```sql
CREATE USER 'docportal_app'@'localhost' IDENTIFIED BY 'use-a-long-random-password-here';
GRANT SELECT, INSERT, UPDATE, DELETE ON doctor_portal.* TO 'docportal_app'@'localhost';
-- Explicitly deny dangerous operations:
REVOKE DROP, ALTER, CREATE, INDEX ON doctor_portal.* FROM 'docportal_app'@'localhost';
FLUSH PRIVILEGES;
```

2. Update `.env`:
```
DB_USERNAME=docportal_app
DB_PASSWORD=use-a-long-random-password-here
```

3. Enable MySQL SSL (for production — MySQL and app on separate servers):
```
MYSQL_ATTR_SSL_CA=/path/to/ca-cert.pem
```
This activates the already-present SSL config block in `config/database.php` (lines 62–64).

**Edge cases:**
- Running migrations requires `CREATE TABLE`, `DROP TABLE`, `ALTER TABLE` permissions. Use a separate migration user with elevated rights only during deployments, not the runtime user.
- `REVOKE DROP, ALTER` on the app user means `php artisan migrate` will fail with that user. Use a deploy-time user with a separate `.env` or `--database` flag for migrations only.
- On localhost XAMPP (local dev), MySQL SSL adds unnecessary complexity. Keep SSL config behind an environment variable only applied in staging/production.

---

## Phase 2 — High Priority Fixes

Do these after all Critical items are resolved.

---

### Step 8 — Enforce Strong Password Complexity

**Why:** `min:8` allows passwords like `12345678`. HIPAA §164.308(a)(5)(ii)(D) requires procedures for creating, changing, and safeguarding passwords.

**File:** `app/Providers/AppServiceProvider.php`

In `boot()`:
```php
use Illuminate\Validation\Rules\Password;

Password::defaults(function () {
    return Password::min(12)
        ->mixedCase()       // upper + lower
        ->numbers()         // at least one digit
        ->symbols()         // at least one special char
        ->uncompromised();  // check against Have I Been Pwned database
});
```

**File:** `app/Http/Controllers/Web/Auth/ResetPasswordController.php`

Change line 31:
```php
// Before:
'password' => 'required|string|min:8|confirmed',

// After:
'password' => ['required', 'confirmed', Password::defaults()],
```

**File:** `app/Http/Controllers/Web/Admin/ClinicianController.php` — find where new clinicians are created and update the password rule there too.

**File:** `app/Http/Controllers/Web/Admin/PartnerController.php` — same for partner user creation.

**Edge cases:**
- `uncompromised()` makes an external HTTPS request to the HaveIBeenPwned k-anonymity API. If the server has no outbound internet access, this will timeout and fail the validation. Wrap it: `Password::min(12)->mixedCase()->numbers()->symbols()` without `->uncompromised()` if the server is network-restricted.
- Existing users have passwords that may not meet the new policy. Do NOT force-reset all passwords on deploy — this would lock everyone out simultaneously. Instead, enforce the new rules only on next password change or reset. Add a `password_changed_at` timestamp to `users` and prompt users to update if it is older than 90 days.
- Update the login page hint text to show the new requirements when users create passwords.

---

### Step 9 — Reduce Session Timeout to 30 Minutes

Already covered in Step 3 (`.env`: `SESSION_LIFETIME=30`).

Add an activity heartbeat to avoid logging out active clinicians mid-case:

**File:** `resources/views/layouts/app.blade.php` — add at the bottom:
```javascript
// Silently refresh the session every 25 minutes while the tab is open
(function () {
    setInterval(function () {
        fetch('{{ route("session.ping") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
    }, 25 * 60 * 1000);
})();
```

**File:** `routes/web.php` — add a ping route (inside the `auth` middleware group):
```php
Route::post('/session/ping', fn() => response()->noContent())->name('session.ping');
```

**Edge cases:**
- The ping keeps the session alive as long as the browser tab is open, even if the user has walked away. Pair this with a front-end idle detector: if the user has not moved the mouse or typed for 30 minutes, show a "Session expiring — click to continue" modal before the ping stops.
- The ping route must be inside the `auth` middleware so unauthenticated pings return 401 instead of refreshing a dead session.

---

### Step 10 — Encrypt PHI Columns at Rest

**Why:** HIPAA §164.312(a)(2)(iv) requires encryption of ePHI stored on electronic media.

**What to encrypt:** Every field that constitutes PHI under HIPAA's 18 identifiers:

| Model | Fields to encrypt |
|---|---|
| `Patient` | `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `address`, `address2`, `city`, `zip` |
| `ClinicalNote` | `note` |
| `Message` | `body` |
| `Prescription` | `notes`, `pharmacy_instructions` (check the model for exact field names) |

**How to apply in Laravel 12:**

In each model, add to the `casts()` method:
```php
protected function casts(): array
{
    return [
        // existing casts...
        'first_name'    => 'encrypted',
        'last_name'     => 'encrypted',
        'email'         => 'encrypted',
        'phone'         => 'encrypted',
        'date_of_birth' => 'encrypted:date',
        'address'       => 'encrypted',
        'address2'      => 'encrypted',
        'city'          => 'encrypted',
        'zip'           => 'encrypted',
    ];
}
```

**Edge cases — this is the highest-risk step:**

- **Searching breaks.** `WHERE email = ?` no longer works on encrypted columns because each value is AES-256 encrypted with a random IV, so two encryptions of the same email produce different ciphertext. You cannot do a database `WHERE` clause on them. Solutions:
  - For email lookups (login, password reset): `User::where('email', $email)` → You must change these to load by ID or use a separate blind index. The `User.email` is on the `users` table (not `patients`) and is used by Passport — do NOT encrypt `users.email` or Passport breaks. Only encrypt `patients.email` (the patient contact detail, a different field).
  - For name searches in the admin patient list: Pull all records and filter in PHP (only feasible under ~10k records), or store a separate searchable hash (HMAC of the lowercase value) in a `_hash` companion column and query on that.
- **`state` column used in clinician assignment (CaseAutoAssigner).** `CaseAutoAssigner::findNext()` compares `patient.state` to `clinician.licensed_states`. Do NOT encrypt `state` — it is a 2-letter code, not a HIPAA identifier on its own, and encrypting it breaks the assignment logic.
- **Migration required.** Existing plaintext values in the database will NOT be readable after adding the `encrypted` cast — existing rows will fail to decrypt. You must run a one-time migration script that reads each row's plaintext value, re-saves it (which triggers the cast and encrypts it), before switching the cast on in production. Do this as a separate artisan command with dry-run mode.
- **`APP_KEY` is the encryption key.** Back it up. If it is lost, all encrypted PHI is permanently unrecoverable.
- **Column length.** Encrypted values are longer than plaintext. A `varchar(100)` first_name becomes roughly 200–250 chars after encryption. Change affected columns to `text` in a migration before enabling the cast.

---

### Step 11 — Stop PHI from Leaking into Logs

**Why:** The `SendWebhookJob` currently logs the full webhook payload — including `diagnoses`, `clinician_npi`, and medication names — to `storage/logs/laravel.log` via `Log::info()`.

**File:** `app/Jobs/SendWebhookJob.php`

Find the `Log::info()` call (approximately line 47) and change it to log only metadata:
```php
// Before (logs PHI):
Log::info('Webhook dispatch', ['delivery_id' => $delivery->id, 'payload' => $payload]);

// After (metadata only):
Log::info('Webhook dispatch', [
    'delivery_id' => $delivery->id,
    'event'       => $delivery->event_type ?? 'unknown',
    'partner_id'  => $delivery->partner_id ?? null,
]);
```

**Also:** `storage/logs/laravel.log` is a plaintext file. Set permissions so only the web server process can read it:
```bash
chmod 640 storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log
```

**Edge cases:**
- Check ALL `Log::info()`, `Log::debug()`, and `Log::error()` calls across the codebase with: `grep -r "Log::" app/` and manually review each one for PHI exposure.
- The Laravel exception handler may log request data (including POST body) on errors. Disable this for PHI-containing routes or implement a custom exception handler that strips PHI fields before logging.

---

### Step 12 — Encrypt Files at Rest

**Why:** Patient ID documents and prescription images stored in `storage/app/private` are in plaintext on disk.

**Option A (Recommended for production on cloud) — S3 with SSE:**

```
# .env (production)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

In the S3 bucket policy, enable default encryption (AES-256 or AWS KMS). All files are then encrypted at rest by AWS without code changes.

**Option B — Local disk with Laravel encryption:**

In `app/Services/FileUploadService.php`, wrap the file store:
```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

// When saving:
$encrypted = Crypt::encrypt(file_get_contents($uploadedFile->getRealPath()));
Storage::disk('local')->put($storagePath, $encrypted);

// When serving:
$encrypted = Storage::disk('local')->get($storagePath);
$contents  = Crypt::decrypt($encrypted);
return response($contents)->header('Content-Type', $mimeType);
```

**Edge cases:**
- Option B means you cannot stream large files — the entire file must be decrypted into memory. Set `memory_limit` high enough in `php.ini` for the largest expected file.
- Encrypting files at rest means ClamAV cannot scan the encrypted bytes on disk. Scan the file BEFORE encrypting and storing. Confirm `ScanUploadedFileJob` runs synchronously before the encrypted store step.
- Option A (S3) requires the S3 bucket to have a strict bucket policy blocking public access. Verify this with `aws s3api get-bucket-acl`.

---

### Step 13 — Add File Download Audit Logging

In every place a patient file is downloaded or previewed, add:
```php
AuditLog::record('file.downloaded', PatientFile::class, $file->id, [
    'filename' => $file->original_name,
]);
```

Do the same for any route that serves a file stream response.

---

### Step 14 — Add Rate Limiting to API Endpoints

Already partly covered in Step 4. Add general throttling to all API routes:

**File:** `routes/api.php`

Wrap all authenticated partner API routes in a throttle:
```php
Route::middleware(['partner.auth', 'throttle:60,1'])->group(function () {
    // all existing partner routes
});
```

The `60,1` allows 60 requests per minute per access token. Adjust per your expected partner usage patterns.

---

## Phase 3 — Medium Priority

Do these within 30 days of completing Phases 1 and 2.

---

### Step 15 — Implement a Data Retention and Purge Policy

**Why:** HIPAA requires PHI to be retained for a defined period and then securely destroyed.

**Soft-delete purge for patients:**

Create `app/Console/Commands/PurgeDeletedPatients.php`:
```php
// Hard-delete patients soft-deleted more than 7 years ago
Patient::onlyTrashed()
    ->where('deleted_at', '<', now()->subYears(7))
    ->each(fn($p) => $p->forceDelete());
```

Register in `routes/console.php` or schedule in `app/Console/Kernel.php`:
```php
Schedule::command('patients:purge-deleted')->monthly();
```

**Webhook delivery payload purge:**

Webhook payloads in `webhook_deliveries` contain PHI. Add a purge command:
```php
// Delete deliveries older than 90 days (retain for debugging window only)
WebhookDelivery::where('created_at', '<', now()->subDays(90))->delete();
```

**Edge cases:**
- Run purges during off-peak hours (2–4 AM) using `->dailyAt('03:00')`.
- Log a purge event to the audit log each time purge runs, including the count of records deleted.
- Before implementing purges, confirm with legal/compliance that 90 days is acceptable for webhook logs and 7 years is the correct retention window for patient records (this varies by state).

---

### Step 16 — Purge Redundant PHI Field

**Why:** The `patients` table has both `date_of_birth` and `age`. Redundant PHI increases exposure surface.

**Migration:**
```php
Schema::table('patients', function (Blueprint $table) {
    $table->dropColumn('age');
});
```

**Code change:** Replace any `$patient->age` references with:
```php
$patient->date_of_birth?->age  // Carbon accessor
// or
now()->diffInYears($patient->date_of_birth)
```

---

### Step 17 — Shorten Password Reset Token Expiry

**File:** `config/auth.php` — change line 103:
```php
'expire' => 30,  // was 60 — 30 minutes is safer for clinical systems
```

---

### Step 18 — Make Virus Scanning Mandatory

**File:** `app/Services/VirusScanService.php` — find the silent-skip guard (approximately line 27):
```php
// Before:
if (!$this->clamavBinaryExists()) {
    return true; // silently pass if ClamAV not installed
}

// After:
if (!$this->clamavBinaryExists()) {
    Log::critical('ClamAV binary not found — file upload blocked');
    throw new \RuntimeException('Virus scanning service is unavailable. File upload is disabled.');
}
```

---

### Step 19 — Configure Production Mail with TLS

**File:** `.env` (production)
```
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Doctor Portal"
```

**Edge cases:**
- Keep `MAIL_MAILER=log` in local `.env` so development emails are not accidentally sent to real patients.
- The password reset email sent by Laravel's built-in `ResetPassword` notification contains only a reset URL — no PHI. No changes needed to the notification content itself.
- If you add any email notifications that reference patient data in future, ensure all such emails are encrypted in transit (SMTP with TLS) and do not embed raw PHI in the email body — link to the portal instead.

---

### Step 20 — Build the Audit Log Admin View

Add a read-only admin page to search audit logs:

**Route:**
```php
Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
```

**Controller:** `app/Http/Controllers/Web/Admin/AuditLogController.php`

Filters to support: event type, causer (user), date range, subject type.

**Edge cases:**
- Make this route accessible only to `role:admin` middleware — clinicians and partners must never see the audit log.
- Export to CSV for compliance reporting purposes.
- Audit log must itself be immutable — no delete or edit buttons anywhere on this page.

---

## Phase 4 — Business Associate Agreements (BAAs)

Technical controls alone do not make you HIPAA compliant. You also need:

| Vendor / Service | BAA Required? | Action |
|---|---|---|
| Hosting provider (VPS/cloud) | Yes | Obtain a BAA from AWS, DigitalOcean, Azure, etc. |
| SendGrid / SMTP provider | Yes | Obtain a BAA from your email provider |
| DoseSpot (pharmacy integration) | Yes | Confirm BAA is in place with DoseSpot |
| Any S3 or file storage provider | Yes | Obtain a BAA from your cloud storage provider |
| Any analytics or error monitoring service | Yes | Sentry, Datadog, etc. all require a BAA if they receive PHI in error reports |

---

## Phase 5 — Policies and Documentation

HIPAA requires written policies, not just technical controls.

| Document | Description |
|---|---|
| Security Risk Assessment | Annual assessment of threats and vulnerabilities to ePHI |
| Workforce Training Policy | Document that all staff with PHI access are trained on HIPAA |
| Incident Response Plan | Procedure for breach notification within 60 days (HHS) and 60 days (patients) |
| Sanction Policy | What happens when workforce members violate HIPAA |
| Access Control Policy | Who can access what PHI and under what circumstances |
| Backup and Disaster Recovery Plan | How PHI is backed up, how often, how it is restored |
| Media Disposal Policy | How old servers, drives, or backups with PHI are securely destroyed |

---

## Implementation Checklist

Use this to track progress:

### Phase 1 — Critical
- [ ] Step 1: Set `APP_DEBUG=false`, `LOG_LEVEL=warning`, create error views
- [ ] Step 2: Add `SecurityHeaders` middleware, `URL::forceScheme`, HSTS, `TrustProxies`
- [ ] Step 3: Set `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_LIFETIME=30`
- [ ] Step 4: Add login rate limiting and per-email+IP lockout
- [ ] Step 5: Implement MFA (Google Authenticator TOTP)
- [ ] Step 6: Create `audit_logs` table, `AuditLog` model, observers, and controller calls
- [ ] Step 7: Create dedicated DB user, remove root/blank-password access, enable DB SSL

### Phase 2 — High
- [ ] Step 8: Set `Password::defaults()` with complexity rules, update all validation
- [ ] Step 9: Reduce session lifetime, add heartbeat ping for active clinicians
- [ ] Step 10: Encrypt PHI columns — run migration script before enabling casts
- [ ] Step 11: Strip PHI from `SendWebhookJob` log output
- [ ] Step 12: Encrypt files at rest (S3 SSE or local Crypt)
- [ ] Step 13: Add file download audit logging
- [ ] Step 14: Add `throttle:60,1` to all authenticated API routes

### Phase 3 — Medium
- [ ] Step 15: Create purge commands for soft-deleted patients and old webhook payloads
- [ ] Step 16: Drop redundant `age` column, use `date_of_birth` everywhere
- [ ] Step 17: Reduce reset token expiry to 30 minutes
- [ ] Step 18: Make ClamAV presence mandatory (fail loudly if missing)
- [ ] Step 19: Configure production SMTP with TLS
- [ ] Step 20: Build audit log admin view

### Phase 4 — Legal
- [ ] Obtain BAAs from all vendors that handle PHI
- [ ] Confirm DoseSpot BAA is current

### Phase 5 — Documentation
- [ ] Complete Security Risk Assessment
- [ ] Write and distribute HIPAA workforce training
- [ ] Draft Incident Response Plan
- [ ] Draft remaining policy documents

---

## Notes on What is Already Done Well

- Password reset has user-enumeration protection (same message for found/not-found)
- `CaseEvent` provides a partial audit trail for case status changes
- Session uses `database` driver (not cookie/file — harder to tamper)
- `csrf` protection is active on all web routes
- Files are stored in a non-web-accessible directory (`storage/app/private`)
- File MIME type is validated against actual file content, not just extension
- OAuth2 Bearer tokens used for partner API (not API keys in query strings)
- `remember_token` column exists and is properly used
- Role-based access control (Spatie) is in place for admin/clinician/partner separation
- Session fixation protection (`session()->regenerate()` on login) is implemented

---

*This document should be reviewed and updated after each implementation phase and whenever the codebase changes in ways that affect PHI handling.*
