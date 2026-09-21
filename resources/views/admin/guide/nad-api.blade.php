@extends('layouts.admin')
@section('title', 'NAD API Integration Guide')
@section('page-title', 'NAD API — Integration Guide')

@section('content')
@php
    $base = rtrim(config('app.url'), '/');

    // ── NAD Questionnaire ──────────────────────────────────────────────────────
    $questions = $questionnaire ? $questionnaire->questions->sortBy(['step_number', 'sort_order'])->values() : collect();
    $byKey     = $questions->keyBy('key');

    $qHeartArrhythmia = $byKey['heart_arrhythmia'] ?? null;
    $qUuid            = $questionnaire->uuid ?? '';

    // ─── Dynamic payload answers — rebuilt live from DB questions ────────────
    $_stepLabels = [1 => 'NAD Safety Screen'];
    $_payloadLines = [];
    $_prevStep = null;
    foreach ($questions as $_q) {
        if ($_q->step_number !== $_prevStep) {
            $_prevStep = $_q->step_number;
            $_label = $_stepLabels[$_q->step_number] ?? 'Step ' . $_q->step_number;
            $_payloadLines[] = '';
            $_payloadLines[] = '    // ── Step ' . $_q->step_number . ': ' . $_label . ' ' . str_repeat('─', 26);
        }
        $_dep = $questions->firstWhere('id', $_q->depends_on_question_id);
        if ($_dep) {
            $_payloadLines[] = '    // conditional — only when "' . $_dep->key . '" ' . $_q->depends_on_operator . ' "' . $_q->depends_on_value . '"';
        } elseif (!$_q->is_required) {
            $_payloadLines[] = '    // optional';
        }
        $_opts = collect($_q->options ?? []);
        switch ($_q->type) {
            case 'radio':
            case 'select':
            case 'choice':
                $_safe = null;
                foreach ($_opts as $_o) {
                    if (empty($_o['is_disqualify']) && empty($_o['disqualifies'])) { $_safe = $_o; break; }
                }
                if (!$_safe) $_safe = $_opts->first();
                $_exVal = $_safe ? '"' . ($_safe['value'] ?? '') . '"' : '"(value)"';
                break;
            case 'multi': case 'multiselect': case 'checkbox':
                $_sv = [];
                foreach ($_opts as $_o) {
                    if (empty($_o['is_disqualify']) && empty($_o['disqualifies']) && !in_array($_o['value'] ?? '', ['none', 'other'])) {
                        $_sv[] = $_o['value'];
                        if (count($_sv) >= 2) break;
                    }
                }
                if (empty($_sv)) $_sv = [($_opts->first()['value'] ?? 'value')];
                $_exVal = '["' . implode('", "', $_sv) . '"]';
                break;
            case 'text': case 'textarea':
                $_ph = str_replace('"', "'", $_q->placeholder ?? 'free text');
                if (strlen($_ph) > 50) $_ph = substr($_ph, 0, 47) . '...';
                $_exVal = '"' . $_ph . '"';
                break;
            default:
                $_exVal = '"(value)"';
        }
        $_slug    = $_q->slug ?? $_q->key;
        $_isLast  = $questions->last()->id === $_q->id;
        $_payloadLines[] = '    { "slug": "' . $_slug . '", "answer": ' . $_exVal . ' }' . ($_isLast ? '' : ',');
    }
    $payloadAnswers = implode("\n", $_payloadLines);
@endphp

<style>
pre { background:#1e1e2e; color:#cdd6f4; border-radius:8px; padding:1.1rem 1.3rem; font-size:.82rem; overflow-x:auto; position:relative }
.copy-btn { position:absolute; top:.5rem; right:.6rem; font-size:.7rem; padding:2px 8px; opacity:.7 }
.copy-btn:hover { opacity:1 }
.badge-method { font-size:.72rem; font-weight:700; padding:2px 7px; border-radius:4px }
.method-post { background:#e8f5e9; color:#2e7d32 }
.method-get  { background:#e3f2fd; color:#1565c0 }
.section-anchor { scroll-margin-top:80px }
.toc-link { font-size:.85rem }
.q-table td:first-child { font-family:monospace; font-size:.8rem; white-space:nowrap }
.err-table td { font-size:.85rem; vertical-align:top }
.step-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0 }

@media print {
    nav.sidebar,
    .topbar,
    .col-lg-3,
    button, .copy-btn { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .p-4 { padding: 0.5rem !important; }
    .col-lg-9 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
    pre { background: #f5f5f5 !important; color: #111 !important; border: 1px solid #ccc !important; page-break-inside: avoid; }
    .card { page-break-inside: avoid; border: 1px solid #ccc !important; margin-bottom: 1rem !important; }
    a { color: inherit !important; text-decoration: none !important; }
    body::before {
        content: "NAD API — Integration Guide";
        display: block;
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 1rem;
        border-bottom: 2px solid #333;
        padding-bottom: .5rem;
    }
}
</style>

<div class="row g-4">

{{-- ── TOC ────────────────────────────────────────────────── --}}
<div class="col-lg-3 d-none d-lg-block">
<div class="card sticky-top" style="top:1rem">
<div class="card-header py-2"><strong class="small">Contents</strong></div>
<div class="card-body py-2 px-3">
<ol class="mb-0 ps-3" style="line-height:2">
    <li><a class="toc-link text-decoration-none" href="#auth">Authentication</a></li>
    <li><a class="toc-link text-decoration-none" href="#discover">Discover Question Slugs</a></li>
    <li><a class="toc-link text-decoration-none" href="#create">Create Case (Full Payload)</a></li>
    <li><a class="toc-link text-decoration-none" href="#refill">Refill / Check-in Cases</a></li>
    <li><a class="toc-link text-decoration-none" href="#product-plans">Product Plans (one-to-many)</a></li>
    <li><a class="toc-link text-decoration-none" href="#questions">Question Reference</a></li>
    <li><a class="toc-link text-decoration-none" href="#errors">Error Responses</a></li>
    <li><a class="toc-link text-decoration-none" href="#db">What Gets Created in DB</a></li>
    <li><a class="toc-link text-decoration-none" href="#clinical">Push Clinical Intake</a></li>
    <li><a class="toc-link text-decoration-none" href="#endpoints">Additional Endpoints</a></li>
    <li><a class="toc-link text-decoration-none" href="#sub-storefronts">Sub-Storefronts API</a></li>
    <li><a class="toc-link text-decoration-none" href="#checklist">Integration Checklist</a></li>
</ol>
</div>
<div class="card-footer py-2 px-3">
<button class="btn btn-sm btn-outline-secondary w-100" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>Print
</button>
</div>
</div>
</div>

{{-- ── Main content ────────────────────────────────────────── --}}
<div class="col-lg-9">

{{-- INTRO --}}
<div class="alert alert-primary border-0 mb-4">
    <strong><i class="bi bi-info-circle me-2"></i>Overview</strong><br>
    This guide walks your patient portal developer through submitting a <strong>NAD+ (Nicotinamide Adenine Dinucleotide)</strong> case via the Partner REST API.
    <ul class="mb-0 mt-1">
        <li>A single <strong>GET</strong> call to the NAD questionnaire returns the safety-screen questions in one list, each tagged with a stable <code>slug</code>.</li>
        <li>A single <strong>POST</strong> to <code>/api/partner/cases</code> with your <strong>Offering ID</strong> + a flat <code>answers</code> array of slug/answer pairs. <strong>No questionnaire UUID needed at submission time.</strong></li>
        <li>The <code>patient</code> block must include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> — stored directly on the patient record.</li>
        <li>Send your <strong>Vouched IDV result</strong> in <code>patient.id_verified_status</code> (<code>verified</code> / <code>failed</code> / <code>pending</code>). For async Vouched flows, push it later via <code>PATCH /api/partner/patients/{uuid}</code> and open cases re-triage automatically.</li>
        <li><strong>Disqualification:</strong> If the patient answers <code>Yes</code> to the heart arrhythmia question, the case is created but immediately flagged as disqualified — the clinician sees the flag and decides next steps.</li>
        <li>NAD+ is available standalone (<em>NAD+</em>, <em>NAD+/Glutathione</em>) or bundled alongside GLP-1 or Anti-Aging offerings via <code>bundle_group</code>.</li>
    </ul>
</div>

{{-- ── 1. AUTH ──────────────────────────────────────────────── --}}
<div id="auth" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">1</span>Authentication</div>
<div class="card-body">
<p class="mb-2">All endpoints require a Bearer token obtained via the OAuth2 <strong>client_credentials</strong> flow.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/auth/token</code>
</div>
<pre id="code-auth">POST {{ $base }}/api/partner/auth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=YOUR_CLIENT_ID
&client_secret=YOUR_CLIENT_SECRET</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-auth')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 200</strong></p>
<pre id="code-auth-resp">{
  "token_type": "Bearer",
  "expires_in": 31536000,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGci..."
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-auth-resp')">Copy</button>

<p class="mt-3 mb-0 text-muted small">Add the token to every subsequent request as: <code>Authorization: Bearer &lt;access_token&gt;</code></p>
</div>
</div>

{{-- ── 2. DISCOVER ──────────────────────────────────────────── --}}
<div id="discover" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">2</span>Discover Question Slugs <span class="text-muted fw-normal small">(one call — do once per environment)</span></div>
<div class="card-body">
<p class="mb-3">Call the NAD questionnaire endpoint once. It returns the cardiac safety-screen question(s) with a stable <code>slug</code> per question. The NAD questionnaire is short by design — additional questions may be added by admins over time, so always fetch live rather than hard-coding slugs.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-get">GET</span>
    <code>{{ $base }}/api/partner/questionnaires/{{ $qUuid }}</code>
</div>
<pre id="code-discover">GET {{ $base }}/api/partner/questionnaires/{{ $qUuid }}
Authorization: Bearer &lt;access_token&gt;</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover')">Copy</button>

<p class="mt-3 mb-1"><strong>Response shape</strong></p>
<pre id="code-discover-resp">{
  "uuid":      "{{ $qUuid }}",
  "name":      "NAD Questionnaire",
  "questions": [
    // ── Step 1: NAD Safety Screen ──
    {
      "slug":        "{{ $qHeartArrhythmia->slug ?? 'heart_arrhythmia' }}",
      "key":         "heart_arrhythmia",
      "question":    "Do you now, or have you ever had, any heart arrhythmia or irregular heartbeat?",
      "type":        "radio",
      "is_required": true,
      "options": [
        { "value": "Yes", "is_disqualify": true  },
        { "value": "No",  "is_disqualify": false }
      ],
      "step_number": 1
    }
    // … additional safety questions may appear here as the questionnaire grows
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-resp')">Copy</button>

<div class="alert alert-warning mt-3 mb-0 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Disqualifying answer:</strong> <code>"Yes"</code> to the heart arrhythmia question is flagged as disqualifying.
    The case is still <strong>created</strong> — do not block submission on your end — but the clinician sees a disqualification notice and decides whether to proceed or decline.
    Never silently suppress the case; the clinician must make the final clinical decision.
</div>
</div>
</div>

{{-- ── 3. CREATE CASE ───────────────────────────────────────── --}}
<div id="create" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">3</span>Create Case — Full Payload</div>
<div class="card-body">

<div class="alert alert-success border-0 small mb-3 py-2">
    <i class="bi bi-stars me-1"></i>
    <strong>No questionnaire UUID needed.</strong>
    Submit a flat <code>answers</code> array — the portal looks up which questionnaire each slug belongs to
    from the <code>offering_id</code> you already send.
</div>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/cases</code>
</div>
<pre id="code-create">POST {{ $base }}/api/partner/cases
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "patient": {
    "first_name":    "John",           ← required
    "last_name":     "Smith",          ← required
    "email":         "john.smith@example.com",  ← required
    "phone":         "+15551234567",
    "date_of_birth": "1978-03-22",
    "gender":        "male",
    "height":        70.0,             ← required  (inches — e.g. 70.0 = 5'10")
    "weight":        185.0,            ← required  (lbs)
    "bmi":           26.5,             ← required  (send pre-calculated)
    "address":       "789 Pine Rd",
    "city":          "Dallas",
    "state":         "TX",
    "zip":           "75201",
    "external_id":   "portal-user-9003",
    "id_verified_status": "verified",  ← Vouched result: verified | failed | pending
    "id_verified_at":     "2026-08-28T09:00:00Z"
  },
  "patient_state":  "TX",
  "external_id":    "order-nad-20260828-001",
  "visit_type":     "asynchronous",   // "asynchronous" (standard) | "synchronous" (video required)
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      false,            // true = refill/check-in visit — see "Refill / Check-in Cases" below
  "sub_storefront_id": "e3b0c442-98fc-1c14-9afb-f4c8996fb924", // optional — UUID from POST /api/partner/sub-storefronts
  "metadata":       { "source": "patient-portal" },  // optional free-form object

  "offerings": [
    // Option A (legacy — no changes needed): direct offering UUID
    { "offering_id": "YOUR_NAD_OFFERING_UUID", "quantity": 1 }

    // Option B (new): product_key + month_frequency — portal resolves internally
    // { "product_key": "nad", "month_frequency": 1, "quantity": 1, "formulation": "injectable" }
    // formulation: "injectable" | "oral" (optional) — NAD is always injectable; you may
    // omit this field or explicitly pass "injectable". "oral" is not valid for NAD.

    // Option C — bundle: NAD alongside another program (e.g. GLP-1 or Anti-Aging).
    // bundle_group is a free-form string — any offerings sharing the same value
    // are treated as one bundle on the prescribe screen.
    // { "product_key": "nad",          "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" },
    // { "product_key": "anti-aging",   "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1" }
  ],

  // ── clinical_intake — populates the clinician's left-panel review fields ───────
  // Without this block every field on the Review & Approve screen shows "—".
  // All fields are optional strings; send only what your intake collects.
  "clinical_intake": {
    "term":            "1M",              // requested duration: "1M" | "3M" | "6M" | "12M"
    "dose":            "LVL1 - 500MG",   // NAD dose level: "LVL1 - 500MG" | "LVL2 - 1000MG"
                                          // For NAD+/Glutathione: "LVL1 - 500MG/500MG" | "LVL2 - 1000MG/1000MG"
    "plan":            null,              // NAD does not use titration plans — omit or null
    "allergy":         "N",              // allergy flag: "Y" | "N"
    "allergyDetail":   null,             // required when allergy = "Y"
    "video":           "not required",   // "not required" | "required" | "Clear"
    "protocolVersion": "NAD protocol v1",

    // ── sourceAnswers — drives the "View source answers" panel in the clinician UI ─
    // Optional. A flat key/value map of any intake Q&A your system collects.
    // Keys are camelCase; the clinician sees them as title-case labels.
    "sourceAnswers": {
      "heartArrhythmia": "No",
      "requestedDose":   "500 MG",
      "deliveryMethod":  "Intramuscular"
    }
  },

  "answers": [{{ $payloadAnswers }}

  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-create')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 201</strong></p>
<pre id="code-create-resp">{
  "uuid":       "case-uuid-here",
  "status":     "waiting",
  "patient": {
    "uuid":       "patient-uuid",
    "first_name": "John",
    "last_name":  "Smith",
    "email":      "john.smith@example.com"
  },
  "case_offerings": [...],
  "created_at": "2026-08-28T09:00:00.000000Z"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-create-resp')">Copy</button>

<div class="alert alert-info mt-3 small">
    <i class="bi bi-shield-check me-1"></i>
    <strong>Async Vouched flow:</strong> If your Vouched check completes <em>after</em> the case is created, push the result via
    <code>PATCH {{ $base }}/api/partner/patients/{patient_uuid}</code> with
    <code>{ "id_verified_status": "verified", "id_verified_at": "…" }</code>.
    The portal immediately re-classifies all open cases for that patient — no re-submission needed.
</div>

<div class="alert alert-warning mt-0 mb-0 small">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Payload is generated live from the DB.</strong>
    Lines marked <code>// conditional</code> must be <strong>omitted</strong> when the parent condition was not met.
    Lines marked <code>// optional</code> may always be omitted.
    A <code>Yes</code> answer to the heart arrhythmia question still creates the case — the doctor sees a disqualification flag and makes the final decision.
</div>
</div>
</div>

{{-- ── REFILL / CHECK-IN CASES ─────────────────────────── --}}
<div id="refill" class="card mb-4 section-anchor">
<div class="card-header fw-semibold">
    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Refill / Check-in Cases
</div>
<div class="card-body">

<p class="mb-3">Set <code>"is_refill": true</code> when a patient returns for a follow-up NAD+ session. The platform handles three things automatically — no extra endpoints or webhooks required.</p>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-person-check me-1 text-primary"></i>Routing continuity</div>
            <p class="small mb-0 text-muted">The case is routed to the same clinician who handled the patient's most recent completed NAD+ visit for this partner. Normal routing rules apply as a fallback.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-clipboard2-check me-1 text-success"></i>Check-in questionnaire</div>
            <p class="small mb-0 text-muted">The platform resolves which questionnaire to map submitted <code>answers</code> slugs against, in priority order:</p>
            <ol class="small mb-0 mt-1 ps-3 text-muted">
                <li>Per-offering <code>check_in</code> questionnaire</li>
                <li>Category-level default check-in questionnaire</li>
                <li>Initial intake questionnaire (always-available fallback)</li>
            </ol>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-card-list me-1 text-info"></i>Prior visit panel</div>
            <p class="small mb-0 text-muted">The reviewing clinician sees a collapsible panel with the patient's prior NAD+ prescription, intake answers, and clinical note — no extra API calls needed from your side.</p>
        </div>
    </div>
</div>

<div class="alert alert-warning border-0 small mt-3 mb-2 py-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Admin setup required for the check-in answers panel.</strong>
    The clinician's prescribe screen shows a green <em>"Check-in answers"</em> panel only when the questionnaire's
    <strong>Purpose</strong> is set to <code>check_in</code> in the admin panel
    (Admin → Questionnaires → edit questionnaire → Purpose field).
    If the questionnaire purpose is left as <code>clinical</code>, the answers are still stored and the
    dose-hint auto-selection still works — but the structured Q&amp;A panel will not appear for the clinician.
</div>

<h6 class="fw-semibold mt-3 mb-2">Minimal NAD+ Refill Payload Example</h6>
<p class="small text-muted mb-2">Same endpoint as a new case — just set <code>"is_refill": true</code> and send a new <code>external_id</code>. The patient is matched by <code>patient.external_id</code> (or email + DOB) to link this visit to their prior completed case.</p>
<pre id="code-nad-refill">POST {{ $base }}/api/partner/cases
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "patient": {
    "first_name":    "John",
    "last_name":     "Smith",
    "email":         "john.smith@example.com",
    "date_of_birth": "1978-03-22",
    "height":        70.0,
    "weight":        183.0,          // current weight (updated)
    "bmi":           26.3,
    "state":         "TX",
    "external_id":   "portal-user-9003"   // must match the original patient external_id
  },
  "patient_state":  "TX",
  "external_id":    "order-nad-refill-20260901-001",  // new unique order ID for this visit
  "visit_type":     "asynchronous",
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      true,            // ← marks this as a follow-up NAD+ visit

  "offerings": [
    { "product_key": "nad", "month_frequency": 1, "quantity": 1, "formulation": "injectable" }
  ],

  // ── Check-in answers ─────────────────────────────────────────────────────────
  // If no check-in questionnaire is configured, you may send the same initial intake slugs.
  // If a check-in questionnaire is configured, use its slugs (GET /api/partner/questionnaires/{uuid}).
  "answers": [
    { "slug": "current_weight",           "answer": "183" },
    { "slug": "side_effects",             "answer": "None" },
    { "slug": "last_dose_date",           "answer": "2026-08-15" },
    { "slug": "dose_continuation",        "answer": "Continue current dose" },
    { "slug": "energy_improvement",       "answer": "Yes, significant improvement" }
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-nad-refill')">Copy</button>

<div class="alert alert-success border-0 small mt-3 mb-0 py-2">
    <i class="bi bi-check-circle me-1"></i>
    <strong>No breaking changes.</strong> Omitting <code>is_refill</code> or sending <code>false</code> behaves exactly as before. Existing integrations require no updates unless you want to start submitting refill cases.
</div>

</div>
</div>

{{-- ── PRODUCT PLANS (one-to-many offering mapping) ────────── --}}
<div id="product-plans" class="card mb-4 section-anchor">
<div class="card-header fw-semibold">
    <i class="bi bi-grid me-2 text-secondary"></i>Product Plans — One-to-Many Offering Mapping
</div>
<div class="card-body">

<p class="mb-3">
    By default, partners send a portal <code>offering_id</code> UUID directly in the <code>offerings</code> array
    (Option A — legacy, always supported). The <strong>Product Plans</strong> feature lets you use your own stable
    <code>product_key</code> identifiers and a <code>month_frequency</code> integer — the portal resolves internally.
    For NAD, one <code>"product_key": "nad"</code> submission can fan out to both
    <em>NAD+ (Nicotinamide Adenine Dinucleotide)</em> <strong>and</strong> <em>NAD+/Glutathione</em> simultaneously
    if both are configured as plan rows.
</p>

<div class="mb-3">
<h6 class="fw-semibold mb-2">Available NAD Offerings</h6>
<div class="table-responsive">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light"><tr><th>Product Name</th><th>Internal Name</th><th>Levels / Doses</th><th>Route</th></tr></thead>
<tbody>
<tr>
    <td>NAD+ (Nicotinamide Adenine Dinucleotide)</td>
    <td><code>NAD+</code></td>
    <td>LVL1 - 500MG (100mg/mL, 5mL)<br>LVL2 - 1000MG (100mg/mL, 10mL)</td>
    <td>IV / IM</td>
</tr>
<tr>
    <td>NAD+/Glutathione</td>
    <td><code>NAD+/Glut</code></td>
    <td>LVL1 - 500MG/500MG (100mg/100mg/mL, 5mL)<br>LVL2 - 1000MG/1000MG (100mg/100mg/mL, 10mL)</td>
    <td>IV / IM</td>
</tr>
</tbody>
</table>
</div>
</div>

<h6 class="fw-semibold mb-2">Option A — Legacy (no changes required)</h6>
<pre class="mb-3">"offerings": [
  { "offering_id": "YOUR_NAD_OFFERING_UUID", "month_frequency": 1, "quantity": 1 }
]
// month_frequency is optional here — add it if you want the prescribe form
// duration pre-filled, but you can omit it and the form defaults to the first available.</pre>

<h6 class="fw-semibold mb-2">Option B — Product Plans (new)</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "nad", "month_frequency": 1, "quantity": 1, "formulation": "injectable" }
  // formulation: "injectable" | "oral" (optional). NAD is always injectable;
  // sending "injectable" or omitting the field are both correct.
]
// No offering_id needed. The portal looks up the plan you configured
// in Admin → Partners → Product Plans and resolves to the offering(s).</pre>

<h6 class="fw-semibold mb-2">Option C — NAD bundled with another program</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "nad",         "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1" },
  { "product_key": "anti-aging",  "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1" }
]
// Both offerings are attached to the case as a bundle.
// Each gets its own locked dropdown on the prescribe screen filtered to its drug family.
// bundle_group is any string you choose — items sharing it are treated as one bundle.</pre>

<div class="alert alert-warning border-0 small py-2 mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Setup required (Option B / C only):</strong> Admin must configure at least one Product Plan row
    in Admin → Partners → <em>Product Plans</em> for this partner before Option B/C calls will succeed.
    Missing plans return <code>422 Product plan not found</code>.
</div>

<h6 class="fw-semibold mb-2">Error responses (Option B / C)</h6>
<div class="table-responsive mb-3">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light"><tr><th>Status</th><th>Condition</th><th>Message</th></tr></thead>
<tbody>
<tr><td><code>422</code></td><td>No plan row found for this partner + product_key + month_frequency</td><td><code>No product plan found for product_key "nad" with month_frequency 1</code></td></tr>
<tr><td><code>422</code></td><td>Plan rows exist but all resolved offerings are inaccessible for this partner</td><td><code>All plan offerings for "nad" (NM) are inaccessible for this partner.</code></td></tr>
<tr><td><code>422</code></td><td>A resolved offering is unavailable in the patient's state</td><td><code>Offering "NAD+ …" is not available in state TX</code></td></tr>
</tbody>
</table>
</div>

<h6 class="fw-semibold mb-2"><code>prescription_written</code> Webhook — Full Payload Shape</h6>
<p class="small mb-2">
    Fired when the clinician confirms the prescription. The <code>offerings</code> array echoes back every offering
    attached to the case. Option A items have <code>product_key: null</code>; Option B items echo the submitted
    <code>product_key</code>; Option C items echo both <code>product_key</code> and <code>bundle_group</code>.
</p>
<pre id="code-webhook-rx-nad">{
  "case_id":        "case-uuid",
  "external_id":    "order-nad-20260828-001",
  "patient_id":     "patient-uuid",
  "clinician_name": "Dr. Jane Smith",
  "clinician_npi":  "1234567890",
  "diagnoses": [
    { "code": "Z71.89", "description": "Encounter for other specified counseling" }
  ],
  "meds_prescribed": [
    {
      "name":                "NAD+ (Nicotinamide Adenine Dinucleotide)",
      "compound_formula":    "Nicotinamide Adenine Dinucleotide compounded injection",
      "sig":                 "Administer intravenously or intramuscularly as directed by your provider.",
      "refills":             "0",
      "quantity":            "5",
      "days_supply":         "30",
      "dispense_unit":       "mL",
      "days_until_dispense": 0,
      "dosing": {
        "medication": "NAD+",
        "frequency":  "Monthly",
        "term":       "1M",       ← clinician's selected term (1M / 3M / 6M / 12M)
        "dose":       "LVL1 - 500MG"
      }
    }
  ],
  "offerings": [
    // Option B — standalone NAD submission:
    {
      "offering_id":     "uuid-nad-500",   ← always present
      "product_key":     "nad",            ← null if Option A used without product_key
      "month_frequency": 1,                ← null if not submitted
      "bundle_group":    null              ← null for standalone (Option A / B) submissions
    }
    // Option C — bundle with Anti-Aging: two entries sharing bundle_group:
    // { "offering_id": "uuid-nad-500",  "product_key": "nad",        "month_frequency": 1, "bundle_group": "combo-1" },
    // { "offering_id": "uuid-aa",       "product_key": "anti-aging", "month_frequency": 3, "bundle_group": "combo-1" }
  ],
  "timestamp": 1722000000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-webhook-rx-nad')">Copy</button>

<div class="alert alert-success border-0 small mt-3 mb-0 py-2">
    <i class="bi bi-check-circle me-1"></i>
    <strong>VRIO CRM order flow:</strong> Iterate <code>offerings[]</code> — each item's <code>product_key</code>
    combined with <code>meds_prescribed[0].dosing.term</code> identifies the VRIO SKU to order. For bundles,
    items sharing the same <code>bundle_group</code> value belong to one logical group — link them in your CRM as needed.
</div>

</div>
</div>

{{-- ── 4. QUESTION REFERENCE ───────────────────────────────── --}}
<div id="questions" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">4</span>Question Reference</div>
<div class="card-body p-0">

@php
function renderNadQRows($rows, $allRows) {
    $out = '';
    foreach ($rows as $q) {
        $depQ = $allRows->firstWhere('id', $q->depends_on_question_id);
        $cond = $depQ
            ? 'Show if ' . $depQ->key . ' ' . $q->depends_on_operator . ' "' . $q->depends_on_value . '"'
            : '—';
        $optVals = collect($q->options ?? [])->pluck('value')->implode(', ');
        if (strlen($optVals) > 60) $optVals = substr($optVals, 0, 58) . '…';
        if (in_array($q->type, ['multi', 'checkbox', 'multiselect'])) {
            $typeBadge = '<span class="badge bg-info text-dark">' . e($q->type) . '</span>';
        } elseif ($q->type === 'file') {
            $typeBadge = '<span class="badge bg-warning text-dark">file</span>';
        } elseif ($q->type === 'hidden') {
            $typeBadge = '<span class="badge bg-secondary">hidden</span>';
        } else {
            $typeBadge = '<span class="badge bg-light text-dark border">' . e($q->type) . '</span>';
        }
        $valueCell = in_array($q->type, ['multi', 'checkbox'])
            ? 'array of: ' . $optVals
            : ($optVals ?: '(free text)');
        $rowClass = $q->depends_on_question_id ? 'table-warning' : '';
        $slugCell = $q->slug
            ? '<code style="font-size:.72rem;color:#166534">' . e($q->slug) . '</code>'
            : '<span class="text-muted">—</span>';
        $out .= '<tr class="' . $rowClass . '">'
            . '<td><code style="font-size:.75rem">' . e($q->key) . '</code></td>'
            . '<td>' . $slugCell . '</td>'
            . '<td>' . $typeBadge . '</td>'
            . '<td>' . e($q->step_number) . '</td>'
            . '<td class="small text-muted" style="font-size:.78rem">' . e($cond) . '</td>'
            . '<td class="small text-muted" style="font-size:.78rem">' . e($valueCell) . '</td>'
            . '</tr>';
    }
    return $out;
}
@endphp

<div class="px-3 pt-3 pb-1 small fw-semibold text-muted text-uppercase" style="letter-spacing:.04em">
    NAD Questionnaire — Safety Screen
</div>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 q-table">
<thead class="table-light">
<tr><th>Key</th><th>Slug</th><th>Type</th><th>Step</th><th>Condition</th><th>Accepted Values</th></tr>
</thead>
<tbody>
@if($questions->isNotEmpty())
{!! renderNadQRows($questions, $questions) !!}
@else
<tr><td colspan="6" class="text-center text-muted py-3 small">No questions found — run NadQuestionnaireSeeder on this environment.</td></tr>
@endif
</tbody>
</table>
</div>

<div class="px-3 py-2 small text-muted bg-light rounded-bottom border-top">
    <i class="bi bi-exclamation-circle me-1"></i><strong>Yellow rows</strong> are conditional — only send them when their condition is met.
    &nbsp;|&nbsp;
    <strong class="text-danger">Disqualifying:</strong> <code>heart_arrhythmia: "Yes"</code> flags the case — the case is still created but the clinician sees a disqualification notice.
    &nbsp;|&nbsp;
    Safe answer: <code>"No"</code>.
</div>
</div>
</div>

{{-- ── 5. ERRORS ───────────────────────────────────────────── --}}
<div id="errors" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-danger text-white me-2">5</span>Error Responses</div>
<div class="card-body p-0">
<table class="table table-sm mb-0 err-table">
<thead class="table-light">
    <tr><th>HTTP</th><th>When</th><th>Example body</th></tr>
</thead>
<tbody>
<tr>
    <td><span class="badge bg-danger">401</span></td>
    <td>Token missing, expired, or malformed</td>
    <td><code>{"message":"Unauthenticated."}</code></td>
</tr>
<tr>
    <td><span class="badge bg-danger">403</span></td>
    <td>Token is valid but partner account is inactive or suspended</td>
    <td><code>{"message":"Partner account is not active."}</code></td>
</tr>
<tr>
    <td><span class="badge bg-warning text-dark">409</span></td>
    <td><code>external_id</code> already exists for this partner (skipped when <code>is_refill: true</code>)</td>
    <td><code>{"message":"Case with this external_id already exists."}</code></td>
</tr>
<tr>
    <td><span class="badge bg-warning text-dark">422</span></td>
    <td>Validation failed (missing required field, wrong type, unknown slug, etc.)</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The patient.email field is required.",
  "errors": {
    "patient.email": ["The patient.email field is required."]
  }
}</pre></td>
</tr>
<tr>
    <td><span class="badge bg-warning text-dark">422</span></td>
    <td>Required NAD questionnaire not submitted for an attached offering</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The given data was invalid.",
  "errors": {
    "questionnaire_responses": ["Required questionnaires not submitted: {{ $qUuid }}"]
  }
}</pre></td>
</tr>
<tr>
    <td><span class="badge bg-danger">500</span></td>
    <td>Unexpected server error (rare)</td>
    <td><code>{"message":"Server Error"}</code> — contact the portal team with the request timestamp.</td>
</tr>
</tbody>
</table>
</div>
</div>

{{-- ── 6. DB RESULT ─────────────────────────────────────────── --}}
<div id="db" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-success text-white me-2">6</span>What Gets Created in the Database</div>
<div class="card-body">
<p class="mb-3">A single successful <code>POST /api/partner/cases</code> call atomically creates:</p>
<div class="row g-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-person-circle me-2 text-primary"></i>Patient</h6>
            <ul class="small mb-0 ps-3">
                <li>Looked up by <code>external_id</code> first, then by <code>email</code></li>
                <li>Created if no match; fields updated if a match is found</li>
                <li>Linked to the partner account</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-folder2 me-2 text-primary"></i>Case</h6>
            <ul class="small mb-0 ps-3">
                <li>Status set to <strong>waiting</strong> immediately (unless <code>hold_status: true</code>)</li>
                <li>Linked to patient and partner</li>
                <li>Your <code>external_id</code> stored for idempotency</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-ui-checks me-2 text-primary"></i>Questionnaire Response + Answers</h6>
            <ul class="small mb-0 ps-3">
                <li>One <code>QuestionnaireResponse</code> record per questionnaire submitted</li>
                <li>One <code>QuestionnaireAnswer</code> row per question, with question text <strong>frozen at submission time</strong></li>
                <li>Disqualification flag set automatically if <code>heart_arrhythmia</code> = <code>"Yes"</code></li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-activity me-2 text-primary"></i>Case Offering</h6>
            <ul class="small mb-0 ps-3">
                <li>Linked to the NAD offering UUID you sent</li>
                <li>Quantity recorded</li>
                <li>Questionnaire linked via pivot — used to resolve answer slugs internally</li>
            </ul>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-arrow-right-circle me-1"></i>
    After the case is created with status <strong>waiting</strong>, a clinician picks it up from their queue, reviews the cardiac safety screen answer, and either approves or declines. You will receive webhook events at each status change if you have registered a webhook endpoint.
</div>
</div>
</div>

{{-- ── 7. PUSH CLINICAL INTAKE ──────────────────────────────── --}}
<div id="clinical" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">7</span>Push Clinical Intake Data <span class="text-muted fw-normal small">(optional, post-creation update)</span></div>
<div class="card-body">
<p class="mb-2">If your storefront collects NAD dose selection or delivery preferences after the case is created, push them with this endpoint. It <strong>replaces</strong> the clinical intake block wholesale.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_uuid}/clinical</code>
</div>
<pre id="code-clinical">POST {{ $base }}/api/partner/cases/{case_uuid}/clinical
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "clinical_intake": {
    "product":         "NAD+ (Nicotinamide Adenine Dinucleotide)",
    "dose":            "LVL2 - 1000MG",     // updated dose level
    "term":            "1M",                 // 1M | 3M | 6M | 12M
    "plan":            null,                 // NAD has no titration plans
    "video":           "not_required",
    "protocolVersion": "NAD protocol v1",
    "sourceAnswers":   { "deliveryMethod": "Intravenous", "requestedDose": "1000 MG" },

    // ── ICD-10 auto-population (optional) ────────────────────────────────────
    "comorbidities": ["fatigue", "cognitive_decline"]
  }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-clinical')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 200</strong></p>
<pre id="code-clinical-resp">{
  "message": "Clinical intake updated.",
  "case": { "uuid": "case-uuid-here", "status": "waiting", ... }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-clinical-resp')">Copy</button>
</div>
</div>

{{-- ── 8. ADDITIONAL ENDPOINTS ───────────────────────────────── --}}
<div id="endpoints" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">8</span>Additional API Endpoints</div>
<div class="card-body">

<h6 class="fw-semibold mb-2">Patient Management</h6>
<table class="table table-sm table-bordered mb-3" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients</code></td><td>List all patients for this partner (paginated)</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Get single patient by UUID</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients/by-external-id/{id}</code></td><td>Look up patient by your <code>external_id</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/patients</code></td><td>Create a standalone patient record (no case)</td></tr>
<tr><td><span class="badge-method method-post" style="background:#fff3cd;color:#664d03">PATCH</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Update patient fields — use for async Vouched IDV pushes and demographic corrections</td></tr>
<tr><td><span class="badge-method method-post" style="background:#f8d7da;color:#842029">DELETE</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Soft-delete patient — fires <code>patient_deleted</code> webhook; data retained for audit</td></tr>
</tbody>
</table>

<h6 class="fw-semibold mb-2">Case Actions</h6>
<table class="table table-sm table-bordered mb-3" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases</code></td><td>List cases for this partner (paginated; filter by <code>?status=</code>)</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}</code></td><td>Get single case — includes patient, clinician, offerings, questionnaire answers, prescription</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/by-external-id/{id}</code></td><td>Look up case by your <code>external_id</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/cancel</code></td><td>Cancel a case. Body: <code>{ "reason": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/hold</code></td><td>Put on or release a hold. Body: <code>{ "hold": true|false }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/support</code></td><td>Escalate case to support. Body: <code>{ "note": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/return-to-clinician</code></td><td>Close the escalation and return the case to the clinician. Body: <code>{ "partner_note": "..." }</code> (required). Fires <code>case_returned_to_clinician</code> webhook.</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}/events</code></td><td>Full event log for the case</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Full message thread. Add <code>?channel=escalation</code> for the support escalation thread, or <code>?channel=portal</code> for the patient thread.</td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Send a message to the patient or escalation thread. Body: <code>{ "body": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/close-thread</code></td><td>Close a parallel support thread. Fires <code>support_thread_closed</code> webhook. Body: <code>{ "partner_note": "..." }</code> (optional).</td></tr>
</tbody>
</table>

</div>
</div>

{{-- ── SUB-STOREFRONTS ────────────────────────────────────────── --}}
<div id="sub-storefronts" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2"><i class="bi bi-diagram-3"></i></span>Sub-Storefronts API</div>
<div class="card-body">

<p class="text-muted small mb-3">
    Sub-storefronts sit <strong>below a partner</strong> in the hierarchy (e.g. AXISmd → Amerilean, Invigorota).
    Each sub-storefront maps to its own <strong>Healthie User Group</strong>, giving patients a segregated chart view
    while sharing the partner's single Healthie organisation. Call <code>POST /api/partner/sub-storefronts</code> once
    when a new tenant registers — the User Group is created automatically using the partner's API key.
</p>

<div class="alert alert-info small py-2 mb-3">
    <i class="bi bi-people-fill me-1"></i>
    <strong>User Group provisioning:</strong> A Healthie User Group is automatically created using the partner's API key.
    Patients submitted with this <code>sub_storefront_id</code> are assigned to that group on first EHR push,
    and the prescribing clinician is added to their care team for permission-scoped access.
</div>

<h6 class="fw-semibold mb-2">Endpoints</h6>
<table class="table table-sm table-bordered mb-4" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr>
    <td><span class="badge-method method-post">POST</span></td>
    <td><code>/api/partner/sub-storefronts</code></td>
    <td>Create a sub-storefront + Healthie User Group</td>
</tr>
<tr>
    <td><span class="badge-method method-get">GET</span></td>
    <td><code>/api/partner/sub-storefronts</code></td>
    <td>List active sub-storefronts for this partner</td>
</tr>
</tbody>
</table>

<h6 class="fw-semibold mb-2">Create a Sub-Storefront — Request</h6>
<div class="position-relative mb-1">
<pre class="bg-dark text-light rounded p-3 small mb-0" id="code-sf-req">POST /api/partner/sub-storefronts
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "name":       "Amerilean",
  "first_name": "John",
  "last_name":  "Doe",
  "email":      "john@amerilean.com"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyCode('code-sf-req')">Copy</button>
</div>
<table class="table table-sm table-bordered mb-4" style="font-size:.83rem">
<thead class="table-light"><tr><th>Field</th><th>Required</th><th>Notes</th></tr></thead>
<tbody>
<tr><td><code>name</code></td><td><span class="badge bg-danger">required</span></td><td>Display name for the sub-storefront. Also used as the Healthie User Group name.</td></tr>
<tr><td><code>first_name</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility. No Healthie admin user is created.</td></tr>
<tr><td><code>last_name</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility.</td></tr>
<tr><td><code>email</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility.</td></tr>
</tbody>
</table>

<h6 class="fw-semibold mb-2">Response — 201 Created</h6>
<div class="position-relative mb-1">
<pre class="bg-dark text-light rounded p-3 small mb-0" id="code-sf-resp">{
  "sub_storefront_id": "e3b0c442-98fc-1c14-9afb-f4c8996fb924",
  "name":   "Amerilean",
  "slug":   "amerilean",
  "status": "active",
  "healthie": {
    "group_id": "12345",
    "error":    null
  }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyCode('code-sf-resp')">Copy</button>
</div>
<ul class="small text-muted mb-4 mt-2 ps-3">
    <li><code>sub_storefront_id</code> — store this UUID; send it as <code>sub_storefront_id</code> in every future case payload for this tenant.</li>
    <li><code>healthie.group_id</code> — the Healthie User Group ID patients in this sub-storefront are assigned to. Store it if your portal needs to reference the group directly.</li>
    <li><code>healthie.error</code> — non-null when Healthie provisioning fails (e.g. partner's Healthie API key not configured). The sub-storefront is still created; an admin must set the group ID manually in the portal.</li>
</ul>

<h6 class="fw-semibold mb-2">Sending Cases to a Sub-Storefront</h6>
<div class="position-relative mb-1">
<pre class="bg-dark text-light rounded p-3 small mb-0" id="code-sf-case">POST /api/partner/cases
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "sub_storefront_id": "e3b0c442-98fc-1c14-9afb-f4c8996fb924",
  "patient": { ... },
  "offerings": [ ... ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyCode('code-sf-case')">Copy</button>
</div>
<p class="small text-muted mt-2 mb-0">
    Cases with a valid <code>sub_storefront_id</code> are pushed to the partner's Healthie org with the patient
    assigned to that sub-storefront's User Group. The prescribing clinician is added to the patient's care team
    automatically. All webhook events include <code>sub_storefront_id</code>. Cases without it behave exactly as before.
</p>

</div>
</div>

{{-- ── 9. CHECKLIST ─────────────────────────────────────────── --}}
<div id="checklist" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-secondary text-white me-2">9</span>Integration Checklist</div>
<div class="card-body">
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain <code>client_id</code>, <code>client_secret</code>, and your <strong>NAD Offering UUID(s)</strong> from the admin — Admin → Partners → Offerings</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>POST /api/partner/auth/token</code> and cache the token (valid 1 year)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>GET /api/partner/questionnaires/{{ $qUuid }}</code> <strong>once</strong> to discover question slugs — store the <code>slug</code> list; you do <em>not</em> need this UUID for submission</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Submit <code>POST /api/partner/cases</code> with <code>offerings[].offering_id</code> (legacy) <em>or</em> <code>offerings[].product_key + month_frequency</code> (new — see Product Plans section) + a flat <code>answers[]</code> array. Include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> in the <code>patient</code> block</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i><strong>Always submit the heart arrhythmia answer</strong> — even if the patient answers <code>"Yes"</code> (disqualifying). The case is created and the clinician makes the final decision. Never suppress the submission on your end.</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Set <code>clinical_intake.dose</code> to the patient's requested level: <code>"LVL1 - 500MG"</code> or <code>"LVL2 - 1000MG"</code> (or the combo variant for NAD+/Glutathione)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Read <code>prescription_written</code> webhook: iterate <code>offerings[]</code> — each item's <code>product_key</code> + <code>meds_prescribed[0].dosing.term</code> identifies the VRIO SKU; place one CRM order per item</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Include <code>patient.id_verified_status</code> = <code>"verified"</code> (or <code>"failed"</code> / <code>"pending"</code>) with your Vouched result. If Vouched completes asynchronously, push it later via <code>PATCH /api/partner/patients/{uuid}</code> — open cases re-triage automatically</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Store the returned <code>uuid</code> (case UUID) for future status lookups and messaging</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register a webhook at <code>POST /api/partner/webhooks</code> to receive status events — the portal fires: <code>case_waiting</code>, <code>case_assigned_to_clinician</code>, <code>case_support</code>, <code>case_approved</code>, <code>prescription_written</code>, <code>case_completed</code>, <code>case_cancelled</code>, <code>message_created</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Verify HMAC signature on incoming webhooks: <code>X-Webhook-Signature: sha256=&lt;digest&gt;</code></li>
    <li class="mb-0"><i class="bi bi-check-square text-success me-2"></i>Use <code>external_id</code> on every case submission for safe retries (409 = already created, treat as success)</li>
</ul>
</div>
</div>

</div>{{-- /col-lg-9 --}}
</div>{{-- /row --}}

@endsection

@section('scripts')
<script>
function copyCode(id) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.innerText).then(function () {
        var btns = document.querySelectorAll('button[onclick="copyCode(\'' + id + '\')"]');
        btns.forEach(function (b) {
            var orig = b.textContent;
            b.textContent = 'Copied!';
            setTimeout(function () { b.textContent = orig; }, 1500);
        });
    });
}
</script>
@endsection
