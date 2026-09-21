@extends('layouts.admin')
@section('title', 'Anti-Aging API Integration Guide')
@section('page-title', 'Anti-Aging API — Integration Guide')

@section('content')
@php
    $base    = rtrim(config('app.url'), '/');

    // ── Anti-Aging ────────────────────────────────────────────────────────────
    $questions = $questionnaire ? $questionnaire->questions->sortBy(['step_number', 'sort_order'])->values() : collect();
    $byKey = $questions->keyBy('key');

    // Standard Intake questions (step 1 — embedded in the Anti-Aging questionnaire)
    $siPregnant      = $byKey['pregnant_breastfeeding']        ?? null;
    $siBP            = $byKey['blood_pressure_range']           ?? null;
    $siMeds          = $byKey['prescription_medications']       ?? null;
    $siMedsList      = $byKey['prescription_medications_list']  ?? null;
    $siAllergies     = $byKey['medication_allergies']           ?? null;
    $siAllergiesList = $byKey['medication_allergies_list']      ?? null;
    $siConditions    = $byKey['medical_conditions']             ?? null;
    $siCondsList     = $byKey['medical_conditions_list']        ?? null;
    $siInjuries      = $byKey['injuries_surgeries']             ?? null;
    $siInjuriesDet   = $byKey['injuries_surgeries_details']     ?? null;
    $siActivity      = $byKey['physical_activity']              ?? null;
    $siLastEval      = $byKey['last_medical_evaluation']        ?? null;
    $siLastLab       = $byKey['last_lab_tests']                 ?? null;
    $siMessage       = $byKey['first_message_to_doctor']        ?? null;
    $siConsent       = $byKey['telehealth_informed_consent']    ?? null;

    // AA-specific questions (steps 2–3)
    $qPrimaryReason    = $byKey['aa_primary_reason']                   ?? null;
    $qPrimaryReasonOth = $byKey['aa_primary_reason_other']             ?? null;
    $qSymptoms         = $byKey['aa_current_symptoms']                  ?? null;
    $qSymptomsOth      = $byKey['aa_current_symptoms_other']           ?? null;
    $qPriorTreat       = $byKey['aa_prior_treatment']                  ?? null;
    $qPriorReact       = $byKey['aa_prior_treatment_reactions']        ?? null;
    $qPriorReactDet    = $byKey['aa_prior_treatment_reaction_details'] ?? null;
    $qG6pd             = $byKey['aa_g6pd_ckd_liver']                   ?? null;
    $qCancer           = $byKey['aa_cancer_treatment']                 ?? null;
    $qConsentTruth     = $byKey['aa_consent_truthfulness']             ?? null;
    $qConsentInformed  = $byKey['aa_consent_informed']                 ?? null;
    $qUuid             = $questionnaire->uuid ?? '';

    // ─── Dynamic payload answers — rebuilt live from DB questions ────────────
    $_stepLabels = [1 => 'Standard Intake', 2 => 'Anti-Aging Program-Specific Intake', 3 => 'Consents'];
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
            case 'choice':
                $_safe = null;
                foreach ($_opts as $_o) { if (empty($_o['disqualifies'])) { $_safe = $_o; break; } }
                if (!$_safe) $_safe = $_opts->first();
                $_exVal = $_safe ? '"' . ($_safe['value'] ?? '') . '"' : '"(value)"';
                break;
            case 'multi': case 'multiselect':
                $_sv = [];
                foreach ($_opts as $_o) {
                    if (empty($_o['disqualifies']) && !in_array($_o['value'] ?? '', ['none', 'other'])) {
                        $_sv[] = $_o['value'];
                        if (count($_sv) >= 2) break;
                    }
                }
                if (empty($_sv)) $_sv = [($_opts->first()['value'] ?? 'value')];
                $_exVal = '["' . implode('", "', $_sv) . '"]';
                break;
            case 'text':
                $_ph = str_replace('"', "'", $_q->placeholder ?? 'free text');
                if (strlen($_ph) > 50) $_ph = substr($_ph, 0, 47) . '...';
                $_exVal = '"' . $_ph . '"';
                break;
            case 'file':
                $_exVal = '"(file_token — from POST /api/partner/files)"';
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
        content: "Anti-Aging API — Integration Guide";
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
    This guide walks your patient portal developer through submitting an <strong>Anti-Aging</strong> case via the Partner REST API.
    <ul class="mb-0 mt-1">
        <li>A single <strong>GET</strong> call to the Anti-Aging questionnaire returns <em>all</em> questions — standard intake (Step 1), program-specific medical history (Step 2), and consents (Step 3) — in one list, each tagged with a stable <code>slug</code>.</li>
        <li>A single <strong>POST</strong> to <code>/api/partner/cases</code> with your <strong>Offering ID</strong> + a flat <code>answers</code> array of slug/answer pairs. <strong>No questionnaire UUID needed at submission time.</strong></li>
        <li>The <code>patient</code> block must include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> — these are required fields stored directly on the patient record.</li>
        <li>Send your <strong>Vouched IDV result</strong> in <code>patient.id_verified_status</code> (<code>verified</code> / <code>failed</code> / <code>pending</code>) — the portal uses it for clinical triage. For async Vouched flows where the result arrives after case creation, push it later via <code>PATCH /api/partner/patients/{uuid}</code> and open cases re-triage automatically.</li>
        <li>The portal uses the offering to determine which questionnaire applies, then stores answers internally.</li>
        <li>Use <code>slug</code> instead of <code>question_id</code> — slugs are stable and survive question rebuilds; numeric IDs change when a questionnaire is edited.</li>
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
<p class="mb-3">Call the Anti-Aging questionnaire endpoint once. It returns <strong>all questions</strong> — standard intake, program-specific medical history, and consents — in a single flat list. Each question includes a <code>slug</code> (stable text key).</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-get">GET</span>
    <code>{{ $base }}/api/partner/questionnaires/{{ $qUuid }}</code>
</div>
<pre id="code-discover">GET {{ $base }}/api/partner/questionnaires/{{ $qUuid }}
Authorization: Bearer &lt;access_token&gt;</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover')">Copy</button>

<p class="mt-3 mb-1"><strong>Response shape</strong></p>
<pre id="code-discover-resp">{
  "uuid":        "{{ $qUuid }}",
  "name":        "Anti-Aging",
  "questions": [
    // ── Step 1: Standard Intake questions ──
    {
      "slug":                 "{{ $siPregnant->slug ?? 'pregnant_breastfeeding' }}",
      "key":                  "pregnant_breastfeeding",
      "question":             "If female — are you currently pregnant or breastfeeding?",
      "type":                 "choice",
      "is_required":          true,
      "options":              [{"value":"yes","is_disqualify":true},{"value":"no"}],
      "step_number":          1
    },
    // … more Step 1 questions …

    // ── Step 2: Anti-Aging program-specific intake ──
    {
      "slug":                 "{{ $qPrimaryReason->slug ?? 'aa_primary_reason' }}",
      "key":                  "aa_primary_reason",
      "question":             "What is your primary reason for requesting this medication?",
      "type":                 "multi",
      "is_required":          true,
      "options":              [{"value":"general_wellness"},{"value":"anti_aging"},{"value":"boost_energy"}, …],
      "step_number":          2
    },
    // … more Step 2 & 3 questions …
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-resp')">Copy</button>

<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-lightbulb me-1"></i>
    Store <code>slug → question_id</code> in your DB using the <code>slug</code> field. Use <code>slug</code> in all future case submissions — <strong>slugs never change</strong> even if the questionnaire is edited. Numeric <code>id</code>s change on every questionnaire rebuild.
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
    "first_name":    "Jane",          ← required
    "last_name":     "Doe",           ← required
    "email":         "jane.doe@example.com",  ← required
    "phone":         "+15551234567",
    "date_of_birth": "1985-06-15",
    "gender":        "female",
    "height":        65.0,            ← required  (inches — e.g. 65.0 = 5'5")
    "weight":        145.0,           ← required  (lbs)
    "bmi":           24.1,            ← required  (send pre-calculated)
    "address":       "456 Oak Ave",
    "city":          "Austin",
    "state":         "TX",
    "zip":           "78701",
    "external_id":   "portal-user-9002",
    "id_verified_status": "verified",   ← Vouched result: verified | failed | pending
    "id_verified_at":     "2026-07-16T10:30:00Z"
  },
  "patient_state":  "TX",
  "external_id":    "order-aa-20240701-001",
  "visit_type":     "asynchronous",  // "asynchronous" (standard) | "synchronous" (video required)
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      false,         // true = refill/check-in visit — see "Refill / Check-in Cases" section below
  "sub_storefront_id": "e3b0c442-98fc-1c14-9afb-f4c8996fb924", // optional — UUID from POST /api/partner/sub-storefronts
  "metadata":       { "source": "patient-portal" },  // optional free-form object, stored verbatim on the case

  "offerings": [
    // Option A (legacy — no changes needed): direct offering UUID
    { "offering_id": "YOUR_AA_OFFERING_UUID", "quantity": 1 }
    // Option B (new): product_key + month_frequency — portal resolves internally
    // { "product_key": "anti-aging", "month_frequency": 3, "quantity": 1 }
    // Option C — bundle: two or more product_key entries sharing bundle_group.
    // bundle_group is a free-form string you choose — any two offerings in the
    // same request that share the same bundle_group value are treated as one bundle.
    // { "product_key": "nad",         "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1" },
    // { "product_key": "anti-aging",  "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1" }
  ],

  // ── clinical_intake — populates the clinician's left-panel review fields ───────
  // Without this block every field on the Review & Approve screen shows "—".
  // All fields are optional strings; send only what your intake collects.
  "clinical_intake": {
    "term":            "3M",              // requested duration: "1M" | "3M" | "4M" | "6M" | "12M"
    "dose":            "0.5 mg",         // requested starting dose
    "plan":            "Starter",        // protocol plan e.g. "Starter" | "Maintenance"
    "onGlp":           "N",             // currently on GLP-1: "Y" | "N" (if applicable)
    "allergy":         "N",             // allergy flag: "Y" | "N"
    "allergyDetail":   null,            // required when allergy = "Y"
    "video":           "not required",  // "not required" | "required" | "Clear"
    "protocolVersion": "AA protocol v1",

    // ── sourceAnswers — drives the "View source answers" panel in the clinician UI ─
    // Optional. A flat key/value map of any intake Q&A your system collects.
    // Keys are camelCase; the clinician sees them as title-case labels
    // ("primaryReason" → "Primary Reason"). If omitted, the portal falls back to
    // displaying the submitted answers[] Q&A pairs instead.
    "sourceAnswers": {
      "primaryReason":        "Energy and vitality",
      "currentSymptoms":      "Fatigue, brain fog",
      "priorHormoneTherapy":  "No"
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
    "first_name": "Jane",
    "last_name":  "Doe",
    "email":      "jane.doe@example.com"
  },
  "case_offerings": [...],
  "created_at": "2026-07-06T10:00:00.000000Z"
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
    Any answer whose value would <strong>disqualify</strong> the patient still creates the case — the doctor sees a disqualification flag.
    The system silently ignores answers for untriggered conditions.
</div>
</div>
</div>

{{-- ── REFILL / CHECK-IN CASES ─────────────────────────── --}}
<div id="refill" class="card mb-4 section-anchor">
<div class="card-header fw-semibold">
    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Refill / Check-in Cases
</div>
<div class="card-body">

<p class="mb-3">Set <code>"is_refill": true</code> when a patient is returning for a follow-up visit on a program they have already completed. The platform handles three things automatically — no extra endpoints or webhooks required.</p>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-person-check me-1 text-primary"></i>Routing continuity</div>
            <p class="small mb-0 text-muted">The case is routed to the same clinician who handled the patient's most recent completed visit for this partner and program category. Normal routing rules apply as a fallback.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-clipboard2-check me-1 text-success"></i>Check-in questionnaire</div>
            <p class="small mb-0 text-muted">The platform resolves which questionnaire to map the submitted <code>answers</code> slugs against, in priority order:</p>
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
            <p class="small mb-0 text-muted">The reviewing clinician sees a collapsible panel alongside the case showing the patient's prior prescription, intake answers, and clinical note — no extra API calls needed from your side.</p>
        </div>
    </div>
</div>

<h6 class="fw-semibold mb-2">What slugs to send in <code>answers</code></h6>
<p class="small mb-2">You always POST to the same <code>/api/partner/cases</code> endpoint with the same structure. Which slugs to use depends on whether an admin has configured a dedicated check-in questionnaire:</p>

<div class="table-responsive">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light">
<tr><th style="width:40%">Scenario</th><th>Slugs to send in <code>answers</code></th></tr>
</thead>
<tbody>
<tr>
    <td><strong>No check-in questionnaire configured</strong> (most common initially)</td>
    <td>Use the same initial intake slugs you already send for first visits. The platform falls back to the initial intake questionnaire automatically — nothing breaks.</td>
</tr>
<tr>
    <td><strong>Admin has configured a check-in questionnaire</strong> on the category or offering</td>
    <td>GET that questionnaire by its UUID to discover its slugs, then include those in <code>answers</code>. You may also include initial intake slugs — the platform stores what it can match and silently ignores the rest.</td>
</tr>
</tbody>
</table>
</div>

<h6 class="fw-semibold mt-3 mb-2">Minimal Anti-Aging Refill Payload Example</h6>
<p class="small text-muted mb-2">Same endpoint as a new case — just set <code>"is_refill": true</code> and send a new <code>external_id</code>. The patient is matched by <code>patient.external_id</code> (or email + DOB) to link this visit to their prior completed case.</p>
<pre id="code-aa-refill">POST {{ $base }}/api/partner/cases
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "patient": {
    "first_name":    "Jane",
    "last_name":     "Doe",
    "email":         "jane.doe@example.com",
    "date_of_birth": "1980-04-10",
    "height":        64.0,
    "weight":        140.0,
    "bmi":           24.0,
    "state":         "CA",
    "external_id":   "portal-user-5001"   // must match the original patient external_id
  },
  "patient_state":  "CA",
  "external_id":    "order-aa-refill-20260901-001",  // new unique order ID for this visit
  "visit_type":     "asynchronous",
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      true,            // ← marks this as a follow-up visit

  "offerings": [
    { "offering_id": "YOUR_AA_OFFERING_UUID", "quantity": 1 }
    // or use product_key if configured: { "product_key": "antiaging", "month_frequency": 1, "quantity": 1 }
  ],

  // ── Check-in answers ─────────────────────────────────────────────────────────
  // If no check-in questionnaire is configured, send the same initial intake slugs.
  // If a check-in questionnaire is configured, use its slugs (GET /api/partner/questionnaires/{uuid}).
  "answers": [
    { "slug": "side_effects",          "answer": "None" },
    { "slug": "last_dose_date",        "answer": "2026-08-20" },
    { "slug": "dose_continuation",     "answer": "Continue current dose" },
    { "slug": "treatment_response",    "answer": "Improved energy and skin tone" }
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-aa-refill')">Copy</button>

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
    (Option A — legacy, always supported). The <strong>Product Plans</strong> feature is an alternative that lets you
    use your own stable <code>product_key</code> identifiers and a <code>month_frequency</code> integer — the portal
    resolves to the correct offerings internally. One <code>product_key + month_frequency</code> submission fans out
    to <strong>all</strong> plan rows configured for that combination — multiple offerings are attached to the case
    simultaneously (e.g. NAD+ 1000mg and a companion compound all at once from a single <code>product_key</code> call).
</p>

<h6 class="fw-semibold mb-2">Option A — Legacy (no changes required)</h6>
<pre class="mb-3">"offerings": [
  { "offering_id": "YOUR_AA_OFFERING_UUID", "month_frequency": 3, "quantity": 1 }
]
// month_frequency is optional here — add it if you want the prescribe form
// duration pre-filled, but you can omit it and the form defaults to 3M.</pre>

<h6 class="fw-semibold mb-2">Option B — Product Plans (new)</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "aa-program", "month_frequency": 3, "quantity": 1 }
]
// No offering_id needed. The portal looks up the plan you configured
// in Admin → Partners → Product Plans and resolves to the offering.</pre>

<div class="alert alert-warning border-0 small py-2 mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Setup required (Option B only):</strong> Admin must configure at least one Product Plan row
    in Admin → Partners → <em>Product Plans</em> for this partner before Option B calls will succeed.
    Missing plans return <code>422 Product plan not found</code>.
</div>

<h6 class="fw-semibold mb-2">Error responses (Option B)</h6>
<div class="table-responsive mb-3">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light"><tr><th>Status</th><th>Condition</th><th>Message</th></tr></thead>
<tbody>
<tr><td><code>422</code></td><td>No plan row found for this partner + product_key + month_frequency</td><td><code>No product plan found for product_key "…" with month_frequency …</code></td></tr>
<tr><td><code>422</code></td><td>Plan rows exist but all resolved offerings are inaccessible for this partner</td><td><code>All plan offerings for "…" (NM) are inaccessible for this partner.</code></td></tr>
<tr><td><code>422</code></td><td>A resolved offering is unavailable in the patient's state</td><td><code>Offering "…" is not available in state …</code></td></tr>
</tbody>
</table>
</div>

<h6 class="fw-semibold mb-2"><code>prescription_written</code> Webhook — Full Payload Shape</h6>
<p class="small mb-2">
    Fired when the clinician confirms the prescription. The <code>offerings</code> array echoes back every offering
    attached to the case — <strong>one entry per submitted offering</strong>. Option A items have <code>product_key: null</code>;
    Option B items echo the submitted <code>product_key</code>; Option C (bundle) items echo both <code>product_key</code>
    and <code>bundle_group</code> so you can identify which offerings belong to the same bundle.
</p>
<pre id="code-webhook-rx-aa">{
  "case_id":        "case-uuid",
  "external_id":    "your-order-id",
  "patient_id":     "patient-uuid",
  "clinician_name": "Dr. Jane Smith",
  "clinician_npi":  "1234567890",
  "diagnoses": [
    { "code": "Z13.88", "description": "Encounter for screening for disorder due to exposure to contaminants" }
  ],
  "meds_prescribed": [
    {
      "name":                "NAD+",
      "compound_formula":    "…",
      "sig":                 "Inject 0.5 mL subcutaneously weekly",
      "refills":             "1",
      "quantity":            "3",
      "days_supply":         "90",
      "dispense_unit":       "mL",
      "days_until_dispense": 0,
      "dosing": {
        "medication": "NAD+",
        "frequency":  "Weekly",
        "term":       "3M",         ← clinician's selected term (1M / 3M / 6M / 12M)
        "months":     ["100 mg", "100 mg", "200 mg"]
      }
    }
  ],
  "offerings": [
    // Option B — one submission resolves to exactly one entry:
    {
      "offering_id":     "uuid-nad-1000",    ← always present
      "product_key":     "nad",              ← null if Option A used without product_key
      "month_frequency": 3,                  ← null if not submitted
      "bundle_group":    null                ← null for standalone (Option A / B) submissions
    }
    // Option C — bundle: two submitted entries sharing bundle_group → two entries here
    // (bundle_group echoes back whatever string you sent — use it to group items in your CRM):
    // { "offering_id": "uuid-nad-1000", "product_key": "nad",        "month_frequency": 3, "bundle_group": "combo-1" },
    // { "offering_id": "uuid-aa",       "product_key": "anti-aging", "month_frequency": 1, "bundle_group": "combo-1" }
  ],
  "timestamp": 1722000000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-webhook-rx-aa')">Copy</button>

<div class="alert alert-success border-0 small mt-3 mb-0 py-2">
    <i class="bi bi-check-circle me-1"></i>
    <strong>VRIO CRM order flow:</strong> Iterate <code>offerings[]</code> — each item's <code>product_key</code>
    (your identifier) combined with <code>meds_prescribed[0].dosing.term</code> (the clinician's selected duration)
    identifies the VRIO SKU to order. Place one CRM order per offering item. For bundles, items sharing the same
    <code>bundle_group</code> value belong to one logical group — link them in your CRM as needed.
</div>

</div>
</div>

{{-- ── 4. QUESTION REFERENCE ───────────────────────────────── --}}
<div id="questions" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">4</span>Question Reference</div>
<div class="card-body p-0">

@php
function renderAAQRows($rows, $allRows) {
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

{{-- Anti-Aging --}}
<div class="px-3 pt-3 pb-1 small fw-semibold text-muted text-uppercase" style="letter-spacing:.04em">
    Anti-Aging
</div>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 q-table">
<thead class="table-light">
<tr><th>Key</th><th>Slug</th><th>Type</th><th>Step</th><th>Condition</th><th>Accepted Values</th></tr>
</thead>
<tbody>{!! renderAAQRows($questions, $questions) !!}</tbody>
</table>
</div>

<div class="px-3 py-2 small text-muted bg-light rounded-bottom border-top">
    <i class="bi bi-exclamation-circle me-1"></i><strong>Yellow rows</strong> are conditional — only send them when their condition is met.
    &nbsp;|&nbsp;<span class="badge bg-info text-dark">multi</span> answers must be JSON arrays even for a single selection.
    &nbsp;|&nbsp;<strong>Disqualifying</strong> answers (<code>aa_g6pd_ckd_liver: yes</code>, <code>aa_cancer_treatment: yes</code>, consents: <code>disagree</code>, <code>pregnant_breastfeeding: yes</code>) will flag the case — the case is still created but the doctor sees a disqualification notice.
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
    <td>Validation failed (missing required field, wrong type, unknown question ID, etc.)</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The patient.email field is required.",
  "errors": {
    "patient.email": ["The patient.email field is required."],
    "questionnaire_responses.0.answers.2.answer": ["..."]
  }
}</pre></td>
</tr>
<tr>
    <td><span class="badge bg-warning text-dark">422</span></td>
    <td>Required questionnaire not submitted for an attached offering</td>
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
                <li>Your <code>external_id</code> stored for idempotency (refill cases may reuse the same <code>external_id</code>)</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-ui-checks me-2 text-primary"></i>Questionnaire Response + Answers</h6>
            <ul class="small mb-0 ps-3">
                <li>One <code>QuestionnaireResponse</code> record per questionnaire submitted</li>
                <li>One <code>QuestionnaireAnswer</code> row per question, with the question text <strong>frozen at submission time</strong></li>
                <li>Disqualification flag set automatically if any disqualifying option was selected</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-activity me-2 text-primary"></i>Case Offering</h6>
            <ul class="small mb-0 ps-3">
                <li>Linked to the offering UUID you sent</li>
                <li>Quantity recorded</li>
                <li>Questionnaire linked via pivot — used to resolve answer slugs internally</li>
            </ul>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-arrow-right-circle me-1"></i>
    After the case is created with status <strong>waiting</strong>, a clinician will pick it up from their queue, review the questionnaire answers, and either approve or request more information. You will receive webhook events at each status change if you have registered a webhook endpoint.
</div>
</div>
</div>

{{-- ── 7. PUSH CLINICAL INTAKE ──────────────────────────────── --}}
<div id="clinical" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">7</span>Push Clinical Intake Data <span class="text-muted fw-normal small">(optional, post-creation update)</span></div>
<div class="card-body">
<p class="mb-2">If your storefront collects medication details after the case is created, push them with this endpoint. It <strong>replaces</strong> the clinical intake block wholesale.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_uuid}/clinical</code>
</div>
<pre id="code-clinical">POST {{ $base }}/api/partner/cases/{case_uuid}/clinical
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "clinical_intake": {
    "product":         "Sermorelin",         // offering / product name
    "dose":            "0.5 mg",             // current dose
    "term":            "3M",                 // term: 1M | 3M | 6M | 12M
    "plan":            "M1",                 // plan tier
    "video":           "not_required",       // "not_required" | "scheduled" | "completed"
    "protocolVersion": "v1.0",
    "findings":        ["anti_aging"],       // array of clinical finding codes
    "summary":         ["approved"],         // array of summary codes
    "sourceAnswers":   { "custom_key": "val" }, // any extra key-value pairs your system tracks

    // ── ICD-10 auto-population (optional — improves clinical documentation) ────
    // Pass comorbidities so the portal can pre-populate ICD-10 diagnoses for the clinician.
    "comorbidities":   ["hypertension", "hypothyroidism"],
    "conditions":      "hypertension, hypothyroidism"  // alternative key (same effect)
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
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Full message thread. Add <code>?channel=escalation</code> to fetch only the support escalation thread, or <code>?channel=portal</code> for the patient thread.</td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Send a message. When <code>escalation_target=support</code> and <code>support_at</code> is set, routes to the clinician escalation thread regardless of case status. Otherwise creates a patient inbound message. Body: <code>{ "body": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/close-thread</code></td><td>Close a parallel support thread (case NOT in <code>support</code> status). Locks compose forms on both portals and fires <code>support_thread_closed</code> webhook. Body: <code>{ "partner_note": "..." }</code> (optional).</td></tr>
</tbody>
</table>

</div>
</div>

{{-- ── SUB-STOREFRONTS ────────────────────────────────────────── --}}
<div id="sub-storefronts" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2"><i class="bi bi-diagram-3"></i></span>Sub-Storefronts API</div>
<div class="card-body">

<p class="text-muted small mb-3">
    Sub-storefronts sit <strong>below a partner</strong> in the hierarchy (e.g. MedAxis → Amerilean, Invigorota).
    Each sub-storefront maps to its own <strong>Healthie User Group</strong> within the partner's single Healthie org,
    so patient records are segregated by group. Call <code>POST /api/partner/sub-storefronts</code> once when a new
    tenant registers in your portal — a User Group is created automatically.
</p>

<div class="alert alert-info small py-2 mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>User Group provisioning:</strong> On creation, a <strong>Healthie User Group</strong> is automatically
    created using the partner's API credentials. The group ID is stored here and returned in the response.
    No passwords or admin accounts are created.
</div>

<h6 class="fw-semibold mb-2">Endpoints</h6>
<table class="table table-sm table-bordered mb-4" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr>
    <td><span class="badge-method method-post">POST</span></td>
    <td><code>/api/partner/sub-storefronts</code></td>
    <td>Create a sub-storefront and its Healthie User Group</td>
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
<tr><td><code>name</code></td><td><span class="badge bg-danger">required</span></td><td>Display name for the sub-storefront and the Healthie User Group</td></tr>
<tr><td><code>first_name</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility; no longer used for Healthie provisioning</td></tr>
<tr><td><code>last_name</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility</td></tr>
<tr><td><code>email</code></td><td><span class="badge bg-secondary">optional</span></td><td>Retained for forward-compatibility; no longer used for Healthie provisioning</td></tr>
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
    "group_id": "789012",
    "error":    null
  }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyCode('code-sf-resp')">Copy</button>
</div>
<ul class="small text-muted mb-4 mt-2 ps-3">
    <li><code>sub_storefront_id</code> — store this UUID; send it as <code>sub_storefront_id</code> in every future case payload for this tenant.</li>
    <li><code>healthie.group_id</code> — the Healthie User Group ID patients in this sub-storefront are assigned to.</li>
    <li><code>healthie.error</code> — non-null when Healthie provisioning fails. The sub-storefront is still created; an admin must set the group ID manually in the portal.</li>
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
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain <code>client_id</code>, <code>client_secret</code>, and your <strong>Offering UUID(s)</strong> from the admin — Partner → Offerings (shown once approved)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>POST /api/partner/auth/token</code> and cache the token (valid 1 year)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>GET /api/partner/questionnaires/{{ $qUuid }}</code> <strong>once</strong> to discover all question slugs — store the <code>slug</code> list; you do <em>not</em> need this UUID for submission</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Submit <code>POST /api/partner/cases</code> with <code>offerings[].offering_id</code> (legacy) <em>or</em> <code>offerings[].product_key + month_frequency</code> (new — see Product Plans section) + a flat <code>answers[]</code> array of <code>slug</code>/<code>answer</code> pairs. Include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> in the <code>patient</code> block</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Read <code>prescription_written</code> webhook: iterate <code>offerings[]</code> — each item's <code>product_key</code> + <code>meds_prescribed[0].dosing.term</code> identifies the VRIO SKU; place one CRM order per item. Bundle items share the same <code>bundle_group</code> value — link them as a group in your CRM</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Include <code>patient.id_verified_status</code> = <code>"verified"</code> (or <code>"failed"</code> / <code>"pending"</code>) with your Vouched result at case creation time. If Vouched completes asynchronously, push it later via <code>PATCH /api/partner/patients/{uuid}</code> — open cases re-triage automatically</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Only send conditional answers when the parent condition was met — omit <code>aa_primary_reason_other</code>, <code>aa_current_symptoms_other</code>, <code>aa_prior_treatment_reactions</code>, and <code>aa_prior_treatment_reaction_details</code> unless triggered</li>
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
