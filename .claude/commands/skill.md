# Pre-Code Codebase Analysis & Plan

You are about to implement a new feature or change for this Laravel project. Before writing a single line of code, follow this exact workflow.

---

## Step 1 — Understand the Requirement

Re-state the requirement in your own words in 2–3 sentences. Identify:
- Which role(s) are affected (admin / clinician / partner web / partner API)
- Which layer(s) are touched (routes, controller, model, migration, view, JS)
- Whether this is net-new or a change to existing behaviour

---

## Step 2 — Scan the Existing Codebase

Run the searches below. Do **not** skip any section even if you think it is unrelated.

### 2a. Routes
```
Read routes/web.php
Read routes/api.php
```
Note every route group, prefix, middleware, and named route that relates to the feature area.

### 2b. Controllers
Glob the relevant controller directory:
- `app/Http/Controllers/Web/Admin/` for admin features
- `app/Http/Controllers/Web/Partner/` for partner web
- `app/Http/Controllers/Api/Partner/` for partner API
- `app/Http/Controllers/Web/Clinician/` for clinician

Read every controller file that touches the same entity (e.g. if the feature is about questionnaires, read `QuestionnaireController.php` and `QuestionController.php`).

### 2c. Models
```
Glob app/Models/*.php
```
Read the model(s) for each entity involved. Check:
- `$fillable` and `$casts`
- Relationships (`hasMany`, `belongsTo`, etc.)
- Scopes and accessors
- Soft-delete trait usage

### 2d. Migrations
```
Glob database/migrations/*.php
```
Read every migration for tables touched by the feature. Understand the current schema exactly — column names, types, defaults, nullable flags, foreign keys.

### 2e. Views
```
Glob resources/views/admin/**/*.blade.php   (or partner / clinician as applicable)
```
Read the index, show, create, edit, and any partials (`_*.blade.php`) for the related section. Note:
- Layout extended (`@extends`)
- Sections used (`@section('scripts')`, etc.)
- How forms POST (action, method-spoofing)
- JS patterns (vanilla fetch, Bootstrap modals, inline scripts)
- CSS framework in use (Bootstrap 5 via CDN — no Vite build step)

### 2f. Shared Layout
```
Read resources/views/layouts/admin.blade.php
Read resources/views/layouts/app.blade.php   (if it exists)
```
Note sidebar nav structure, active-class pattern, and any global JS or meta tags (e.g. `csrf-token`).

### 2g. Config & Middleware
Grep for any middleware relevant to the feature:
```
Grep pattern: "middleware" in app/Http/Middleware/
Grep pattern: "role:" in routes/
```

### 2h. External Dependencies
- Spatie Permission roles: `admin`, `clinician`, `partner`
- Laravel Passport: OAuth2 client_credentials used for partner API routes
- Bootstrap 5 + Bootstrap Icons (CDN, no npm build)
- No Livewire, no Alpine, no Inertia — plain Blade + vanilla JS

---

## Step 3 — Identify Risks & Dependencies

List every file that the new code will call, extend, or affect. For each, answer:

| File | How it is affected | Risk if changed wrong |
|------|-------------------|-----------------------|
| `routes/web.php` | New route added | Route naming collision, wrong middleware group |
| `SomeModel.php` | New relationship added | Breaks existing eager-loads elsewhere |
| `index.blade.php` | New column or button | Breaks existing table JS, pagination |
| … | … | … |

Flag anything that is shared (layouts, partials, base controllers, models used across multiple features).

---

## Step 4 — Prepare the Implementation Plan

Write a numbered plan **before touching any file**. Each step must include:

1. **What** — the exact file to create or edit
2. **Why** — what it provides
3. **How** — the specific change (e.g. "add `Route::get` inside the existing `admin.` group after the questionnaires prefix block")
4. **Depends on** — which earlier step must be done first

Order the steps so that dependencies are always satisfied before the step that needs them (migrations before models, models before controllers, controllers before routes, routes before views).

---

## Step 5 — Style Checklist

Before finalising the plan, confirm each item:

- [ ] Controller namespace follows `App\Http\Controllers\Web\Admin\` pattern
- [ ] Route named with dot notation: `admin.entity.action`
- [ ] Route registered inside the correct `prefix/name/middleware` group
- [ ] View extends `layouts.admin` (or `layouts.app` for partner/clinician)
- [ ] View uses `@section('content')` and `@section('scripts')`
- [ ] Forms use `@csrf` and `@method('PUT')`/`@method('DELETE')` where needed
- [ ] Sidebar link added with the same `request()->routeIs('admin.entity.*') ? 'active' : ''` pattern
- [ ] Model `$fillable` updated if new columns are added
- [ ] Migration created for any new/changed columns
- [ ] No Vite, no npm — all assets loaded from CDN or `public/`
- [ ] JS written as vanilla JS (no jQuery, no frameworks) inside `@section('scripts')`
- [ ] Bootstrap modal populated via `fetch()` returning JSON (same pattern as Question Bank)
- [ ] Flash messages use `session('success')` / `session('error')` pattern
- [ ] Pagination uses `->paginate(25)->withQueryString()`

---

## Step 6 — Present the Plan for Approval

Show the user:
1. Your understanding of the requirement (Step 1 summary)
2. Relevant existing files found (Step 2 summary — file paths only, not full contents)
3. Risk table (Step 3)
4. Numbered implementation plan (Step 4)
5. Any open questions or ambiguities before you start

**Wait for the user to approve the plan before writing any code.**

---

## Step 7 — Execute

Only after approval:
- Implement each step in order
- Mark each step complete as you finish it
- After all code is written, run `php artisan migrate` (if there are migrations), then `php artisan route:clear && php artisan view:clear`
- Report what was done and what to test
