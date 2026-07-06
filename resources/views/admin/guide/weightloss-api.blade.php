@extends('layouts.admin')
@section('title', 'Weight Loss API Integration Guide')
@section('page-title', 'Weight Loss API — Integration Guide')

@section('content')
@php
    $base    = rtrim(config('app.url'), '/');

    // ── MWL – Weight Loss ─────────────────────────────────────────────────────
    $questions = $questionnaire ? $questionnaire->questions->sortBy(['step_number', 'sort_order'])->values() : collect();
    $byKey = $questions->keyBy('key');

    // Standard Intake questions (step 1 — embedded in the MWL questionnaire)
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

    // MWL-specific questions (steps 2–3)
    $qMedCond  = $byKey['mwl_medical_conditions']   ?? null;
    $qGbConsent= $byKey['mwl_gallbladder_consent']   ?? null;
    $qTConsent = $byKey['mwl_thyroid_consent']        ?? null;
    $qBypass   = $byKey['mwl_gastric_bypass']         ?? null;
    $qAllergy  = $byKey['mwl_glp1_brand_allergy']     ?? null;
    $qCurGlp1  = $byKey['mwl_current_glp1']           ?? null;
    $qSideFx   = $byKey['mwl_glp1_side_effects']      ?? null;
    $qDose     = $byKey['mwl_current_dose']            ?? null;
    $qContinu  = $byKey['mwl_treatment_continuation']  ?? null;
    $qHasPic   = $byKey['mwl_has_prescription_pic']    ?? null;
    $qPicUp    = $byKey['mwl_prescription_pic_upload'] ?? null;
    $qTruth    = $byKey['mwl_consent_truthfulness']    ?? null;
    $qGlp1Con  = $byKey['mwl_consent_glp1']            ?? null;
    $qUuid     = $questionnaire->uuid ?? '';
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
    /* Hide admin chrome */
    nav.sidebar,
    .topbar,
    .col-lg-3,
    button, .copy-btn { display: none !important; }

    /* Remove sidebar margin so content fills the page */
    .main-content { margin-left: 0 !important; }
    .p-4 { padding: 0.5rem !important; }

    /* Expand main content column to full width */
    .col-lg-9 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }

    /* Print-friendly code blocks */
    pre { background: #f5f5f5 !important; color: #111 !important; border: 1px solid #ccc !important; page-break-inside: avoid; }

    /* Keep cards on one page where possible */
    .card { page-break-inside: avoid; border: 1px solid #ccc !important; margin-bottom: 1rem !important; }

    a { color: inherit !important; text-decoration: none !important; }

    /* Print title at top */
    body::before {
        content: "Weight Loss API — Integration Guide";
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
    <li><a class="toc-link text-decoration-none" href="#upload">Upload Prescription Image</a></li>
    <li><a class="toc-link text-decoration-none" href="#create">Create Case (Full Payload)</a></li>
    <li><a class="toc-link text-decoration-none" href="#questions">Question Reference</a></li>
    <li><a class="toc-link text-decoration-none" href="#errors">Error Responses</a></li>
    <li><a class="toc-link text-decoration-none" href="#db">What Gets Created in DB</a></li>
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
    This guide walks your patient portal developer through submitting a <strong>Weight Loss (MWL)</strong> case via the Partner REST API.
    <ul class="mb-0 mt-1">
        <li>A single <strong>GET</strong> call to the MWL questionnaire returns <em>all</em> questions — standard intake (Step 1), program-specific medical history (Step 2), and consents (Step 3) — in one list, each tagged with a stable <code>slug</code>.</li>
        <li>A single <strong>POST</strong> to <code>/api/partner/cases</code> with your <strong>Offering ID</strong> + a flat <code>answers</code> array of slug/answer pairs. <strong>No questionnaire UUID needed at submission time.</strong></li>
        <li>The <code>patient</code> block must include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> — these are required fields and are stored directly on the patient record.</li>
        <li>The portal uses the offering to determine which questionnaire applies, then stores the answers internally.</li>
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
<p class="mb-3">Call the MWL questionnaire endpoint once. It returns <strong>all questions</strong> — standard intake, program-specific medical history, and consents — in a single flat list. Each question includes a <code>slug</code> (stable text key).</p>

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
  "name":        "MWL – Weight Loss",
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

    // ── Step 2: Program-specific medical intake ──
    {
      "slug":                 "{{ $qMedCond->slug ?? 'mwl_medical_conditions' }}",
      "key":                  "mwl_medical_conditions",
      "question":             "Please check all current or past medical conditions …",
      "type":                 "multi",
      "is_required":          true,
      "options":              [{"value":"gastroparesis","is_disqualify":true}, …],
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

{{-- ── 3. UPLOAD ────────────────────────────────────────────── --}}
<div id="upload" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">3</span>Upload Prescription Image <span class="text-muted fw-normal small">(only if patient has GLP-1 prescription photo)</span></div>
<div class="card-body">
<p class="mb-2">Upload the image first and receive a <code>file_token</code>. Pass that token as the answer to slug <code>mwl_prescription_pic_upload</code> in the case payload. Skip this step entirely if the patient answered <strong>No</strong> to having a prescription picture.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/files</code>
</div>
<pre id="code-upload">POST {{ $base }}/api/partner/files
Authorization: Bearer &lt;access_token&gt;
Content-Type: multipart/form-data

file=&lt;binary image — JPG, PNG, or PDF, max 10 MB&gt;</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-upload')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 201</strong></p>
<pre id="code-upload-resp">{
  "file_token":    "3f2a1b4c-...",   ← use this as the answer value
  "original_name": "prescription.jpg",
  "size":          204800,
  "mime_type":     "image/jpeg"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-upload-resp')">Copy</button>
</div>
</div>

{{-- ── 4. CREATE CASE ───────────────────────────────────────── --}}
<div id="create" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">4</span>Create Case — Full Payload</div>
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
    "height":        70.5,            ← required  (inches — e.g. 70.5 = 5'10.5")
    "weight":        185.0,           ← required  (lbs)
    "bmi":           26.2,            ← required  (send pre-calculated)
    "address":       "123 Main St",
    "city":          "Austin",
    "state":         "TX",
    "zip":           "78701",
    "external_id":   "portal-user-9001"
  },
  "patient_state":  "TX",
  "external_id":    "order-wl-20240701-001",
  "visit_type":     "weightloss",
  "is_chargeable":  true,
  "hold_status":    false,

  "offerings": [
    { "offering_id": "YOUR_MWL_OFFERING_UUID", "quantity": 1 }
  ],

  "answers": [

    // ── Step 1: Standard Intake ─────────────────────────────────────
    { "slug": "{{ $siPregnant->slug      ?? 'pregnant_breastfeeding' }}",       "answer": "no" },
    { "slug": "{{ $siBP->slug            ?? 'blood_pressure_range' }}",          "answer": "normal" },
    { "slug": "{{ $siMeds->slug          ?? 'prescription_medications' }}",      "answer": "yes" },
    { "slug": "{{ $siMedsList->slug      ?? 'prescription_medications_list' }}", "answer": "Metformin 500mg twice daily" },
    { "slug": "{{ $siAllergies->slug     ?? 'medication_allergies' }}",          "answer": "no" },
    // (omit medication_allergies_list if "no" above)
    { "slug": "{{ $siConditions->slug    ?? 'medical_conditions' }}",            "answer": "no" },
    // (omit medical_conditions_list if "no" above)
    { "slug": "{{ $siInjuries->slug      ?? 'injuries_surgeries' }}",            "answer": "no" },
    // (omit injuries_surgeries_details if "no" above)
    { "slug": "{{ $siActivity->slug      ?? 'physical_activity' }}",             "answer": "somewhat_active" },
    { "slug": "{{ $siLastEval->slug      ?? 'last_medical_evaluation' }}",       "answer": "less_than_1_year" },
    { "slug": "{{ $siLastLab->slug       ?? 'last_lab_tests' }}",                "answer": "less_than_1_year" },
    { "slug": "{{ $siMessage->slug       ?? 'first_message_to_doctor' }}",       "answer": "Starting my weight loss journey." },
    { "slug": "{{ $siConsent->slug       ?? 'telehealth_informed_consent' }}",   "answer": "agree" },

    // ── Step 2: Program-Specific Medical Intake ─────────────────────
    { "slug": "{{ $qMedCond->slug   ?? 'mwl_medical_conditions' }}",    "answer": ["gallbladder_disease", "hypertension"] },
    { "slug": "{{ $qBypass->slug    ?? 'mwl_gastric_bypass' }}",        "answer": "no" },
    { "slug": "{{ $qAllergy->slug   ?? 'mwl_glp1_brand_allergy' }}",    "answer": ["none"] },
    { "slug": "{{ $qCurGlp1->slug   ?? 'mwl_current_glp1' }}",          "answer": "semaglutide" },
    { "slug": "{{ $qSideFx->slug    ?? 'mwl_glp1_side_effects' }}",     "answer": "no" },
    { "slug": "{{ $qDose->slug      ?? 'mwl_current_dose' }}",           "answer": "sema_0_5" },
    { "slug": "{{ $qContinu->slug   ?? 'mwl_treatment_continuation' }}", "answer": "same_dose" },
    { "slug": "{{ $qHasPic->slug    ?? 'mwl_has_prescription_pic' }}",   "answer": "yes" },
    { "slug": "{{ $qPicUp->slug     ?? 'mwl_prescription_pic_upload' }}", "answer": "3f2a1b4c-..." },
    // (omit mwl_prescription_pic_upload if "no" above)

    // ── Step 3: Consents ────────────────────────────────────────────
    { "slug": "{{ $qGbConsent->slug ?? 'mwl_gallbladder_consent' }}",   "answer": "agree" },
    // (omit mwl_gallbladder_consent if gallbladder_disease NOT selected in mwl_medical_conditions)
    // (omit mwl_thyroid_consent if thyroid_issues NOT selected in mwl_medical_conditions)
    { "slug": "{{ $qTruth->slug     ?? 'mwl_consent_truthfulness' }}",   "answer": "agree" },
    { "slug": "{{ $qGlp1Con->slug   ?? 'mwl_consent_glp1' }}",           "answer": "agree" }
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
  "created_at": "2026-07-03T10:00:00.000000Z"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-create-resp')">Copy</button>

<div class="alert alert-warning mt-3 mb-0 small">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Conditional answers</strong> — Only send answers for questions the patient actually answered. Conditional questions (GLP-1 follow-ups, prescription image, gallbladder/thyroid consents) should be <strong>omitted entirely</strong> if the condition was not met. The system does not error on missing optional answers — it silently skips them.
</div>
</div>
</div>

{{-- ── 5. QUESTION REFERENCE ───────────────────────────────── --}}
<div id="questions" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">5</span>Question Reference</div>
<div class="card-body p-0">

@php
function renderQRows($rows, $allRows) {
    $out = '';
    foreach ($rows as $q) {
        $depQ = $allRows->firstWhere('id', $q->depends_on_question_id);
        $cond = $depQ
            ? 'Show if ' . e($depQ->key) . ' ' . $q->depends_on_operator . ' "' . $q->depends_on_value . '"'
            : '—';
        $optVals = collect($q->options ?? [])->pluck('value')->implode(', ');
        if (strlen($optVals) > 60) $optVals = substr($optVals, 0, 58) . '…';
        if (in_array($q->type, ['multi', 'checkbox', 'multiselect'])) {
            $typeBadge = '<span class="badge bg-info text-dark">' . $q->type . '</span>';
        } elseif ($q->type === 'file') {
            $typeBadge = '<span class="badge bg-warning text-dark">file</span>';
        } elseif ($q->type === 'hidden') {
            $typeBadge = '<span class="badge bg-secondary">hidden</span>';
        } else {
            $typeBadge = '<span class="badge bg-light text-dark border">' . $q->type . '</span>';
        }
        $valueCell = $q->type === 'file'
            ? 'file_token (UUID)'
            : (in_array($q->type, ['multi', 'checkbox']) ? 'array of: ' . $optVals : ($optVals ?: '(free text)'));
        $rowClass = $q->depends_on_question_id ? 'table-warning' : '';
        $slugCell = $q->slug
            ? '<code style="font-size:.72rem;color:#166534">' . e($q->slug) . '</code>'
            : '<span class="text-muted">—</span>';
        $out .= '<tr class="' . $rowClass . '">'
            . '<td><code style="font-size:.75rem">' . e($q->key) . '</code></td>'
            . '<td>' . $slugCell . '</td>'
            . '<td>' . $typeBadge . '</td>'
            . '<td>' . $q->step_number . '</td>'
            . '<td class="small text-muted" style="font-size:.78rem">' . e($cond) . '</td>'
            . '<td class="small text-muted" style="font-size:.78rem">' . e($valueCell) . '</td>'
            . '</tr>';
    }
    return $out;
}
@endphp

{{-- MWL – Weight Loss --}}
<div class="px-3 pt-3 pb-1 small fw-semibold text-muted text-uppercase" style="letter-spacing:.04em">
    MWL – Weight Loss
</div>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 q-table">
<thead class="table-light">
<tr><th>Key</th><th>Slug</th><th>Type</th><th>Step</th><th>Condition</th><th>Accepted Values</th></tr>
</thead>
<tbody>{!! renderQRows($questions, $questions) !!}</tbody>
</table>
</div>

<div class="px-3 py-2 small text-muted bg-light rounded-bottom border-top">
    <i class="bi bi-exclamation-circle me-1"></i><strong>Yellow rows</strong> are conditional — only send them when their condition is met.
    &nbsp;|&nbsp;<span class="badge bg-info text-dark">multi</span> answers must be JSON arrays even for a single selection.
    &nbsp;|&nbsp;<strong>Disqualifying</strong> answers will mark the case as disqualified — the case is still created but the doctor will see a disqualification flag.
</div>
</div>
</div>

{{-- ── 6. ERRORS ───────────────────────────────────────────── --}}
<div id="errors" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-danger text-white me-2">6</span>Error Responses</div>
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
    <td><code>external_id</code> already exists for this partner</td>
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
    <td><span class="badge bg-secondary">422</span></td>
    <td>File upload — wrong type or too large</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The file field must be a file of type: pdf, jpg, jpeg, png.",
  "errors": { "file": ["..."] }
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

{{-- ── 7. DB RESULT ─────────────────────────────────────────── --}}
<div id="db" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-success text-white me-2">7</span>What Gets Created in the Database</div>
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
                <li>One <code>QuestionnaireAnswer</code> row per question, with the question text <strong>frozen at submission time</strong></li>
                <li>Disqualification flag set automatically if any disqualifying option was selected</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-image me-2 text-primary"></i>Prescription Image</h6>
            <ul class="small mb-0 ps-3">
                <li>File saved to <code>storage/app/patient-files/YYYY/MM/</code></li>
                <li>ClamAV virus scan queued automatically (non-blocking)</li>
                <li>File record linked to the patient, case, and partner</li>
                <li>Accessible to the reviewing clinician from the case view</li>
            </ul>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-arrow-right-circle me-1"></i>
    After the case is created with status <strong>waiting</strong>, a clinician will pick it up from their queue, review the questionnaire answers and prescription image, and either approve or request more information. You will receive webhook events at each status change if you have registered a webhook endpoint.
</div>
</div>
</div>

{{-- ── 8. CHECKLIST ─────────────────────────────────────────── --}}
<div id="checklist" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-secondary text-white me-2">8</span>Integration Checklist</div>
<div class="card-body">
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain <code>client_id</code>, <code>client_secret</code>, and your <strong>Offering UUID(s)</strong> from the admin — Partner → Offerings (shown once approved)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>POST /api/partner/auth/token</code> and cache the token (valid 1 year)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>GET /api/partner/questionnaires/{{ $qUuid }}</code> <strong>once</strong> to discover all question slugs — store the <code>slug</code> list; you do <em>not</em> need this UUID for submission</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>If patient has a prescription image: <code>POST /api/partner/files</code> → store <code>file_token</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Submit <code>POST /api/partner/cases</code> with <code>offerings[].offering_id</code> + a flat <code>answers[]</code> array of <code>slug</code>/<code>answer</code> pairs — no questionnaire UUID required; the portal resolves answers internally. Include <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong> as required fields in the <code>patient</code> block</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Store the returned <code>uuid</code> (case UUID) for future status lookups and messaging</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register a webhook at <code>POST /api/partner/webhooks</code> to receive status events — the portal fires: <code>case_waiting</code>, <code>case_assigned_to_clinician</code>, <code>case_support</code>, <code>case_approved</code>, <code>case_processing</code>, <code>case_completed</code>, <code>case_cancelled</code>, <code>message_created</code></li>
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
