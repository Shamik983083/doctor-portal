@extends('layouts.admin')
@section('title', 'Partner API — Integration Guide')
@section('page-title', 'Partner API — Integration Guide')

@section('content')
@php
    $base = rtrim(config('app.url'), '/');

    // ── GLP Questionnaire ──────────────────────────────────────────────────────
    $glpQuestions = $glpQuestionnaire ? $glpQuestionnaire->questions->sortBy(['step_number', 'sort_order'])->values() : collect();
    $glpByKey     = $glpQuestions->keyBy('key');
    $glpQUuid     = $glpQuestionnaire->uuid ?? '';

    // GLP step labels
    $_glpStepLabels = [1 => 'Standard Intake', 2 => 'Program-Specific Medical Intake', 3 => 'Consents'];
    $_glpPayloadLines = [];
    $_glpPrevStep = null;
    foreach ($glpQuestions as $_q) {
        if ($_q->step_number !== $_glpPrevStep) {
            $_glpPrevStep = $_q->step_number;
            $_label = $_glpStepLabels[$_q->step_number] ?? 'Step ' . $_q->step_number;
            $_glpPayloadLines[] = '';
            $_glpPayloadLines[] = '    // ── Step ' . $_q->step_number . ': ' . $_label . ' ' . str_repeat('─', 26);
        }
        $_dep = $glpQuestions->firstWhere('id', $_q->depends_on_question_id);
        if ($_dep) {
            $_glpPayloadLines[] = '    // conditional — only when "' . $_dep->key . '" ' . $_q->depends_on_operator . ' "' . $_q->depends_on_value . '"';
        } elseif (!$_q->is_required) {
            $_glpPayloadLines[] = '    // optional';
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
        $_slug = $_q->slug ?? $_q->key;
        $_isLast = $glpQuestions->last()->id === $_q->id;
        $_glpPayloadLines[] = '    { "slug": "' . $_slug . '", "answer": ' . $_exVal . ' }' . ($_isLast ? '' : ',');
    }
    $glpPayloadAnswers = implode("\n", $_glpPayloadLines);

    // ── NAD Questionnaire ──────────────────────────────────────────────────────
    $nadQuestions = $nadQuestionnaire ? $nadQuestionnaire->questions->sortBy(['step_number', 'sort_order'])->values() : collect();
    $nadByKey     = $nadQuestions->keyBy('key');
    $nadQUuid     = $nadQuestionnaire->uuid ?? '';
    $qHeartArrhythmia = $nadByKey['heart_arrhythmia'] ?? null;

    $_nadStepLabels = [1 => 'NAD Safety Screen'];
    $_nadPayloadLines = [];
    $_nadPrevStep = null;
    foreach ($nadQuestions as $_q) {
        if ($_q->step_number !== $_nadPrevStep) {
            $_nadPrevStep = $_q->step_number;
            $_label = $_nadStepLabels[$_q->step_number] ?? 'Step ' . $_q->step_number;
            $_nadPayloadLines[] = '';
            $_nadPayloadLines[] = '    // ── Step ' . $_q->step_number . ': ' . $_label . ' ' . str_repeat('─', 26);
        }
        $_dep = $nadQuestions->firstWhere('id', $_q->depends_on_question_id);
        if ($_dep) {
            $_nadPayloadLines[] = '    // conditional — only when "' . $_dep->key . '" ' . $_q->depends_on_operator . ' "' . $_q->depends_on_value . '"';
        } elseif (!$_q->is_required) {
            $_nadPayloadLines[] = '    // optional';
        }
        $_opts = collect($_q->options ?? []);
        switch ($_q->type) {
            case 'radio': case 'select': case 'choice':
                $_safe = null;
                foreach ($_opts as $_o) { if (empty($_o['is_disqualify']) && empty($_o['disqualifies'])) { $_safe = $_o; break; } }
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
        $_slug = $_q->slug ?? $_q->key;
        $_isLast = $nadQuestions->isEmpty() ? true : ($nadQuestions->last()->id === $_q->id);
        $_nadPayloadLines[] = '    { "slug": "' . $_slug . '", "answer": ' . $_exVal . ' }' . ($_isLast ? '' : ',');
    }
    $nadPayloadAnswers = implode("\n", $_nadPayloadLines);

    // ── Shared renderApiQRows helper ───────────────────────────────────────────
    if (!function_exists('renderApiQRows')) {
        function renderApiQRows($rows, $allRows) {
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
                $valueCell = $q->type === 'file'
                    ? 'file_token (UUID)'
                    : (in_array($q->type, ['multi', 'checkbox', 'multiselect']) ? 'array of: ' . $optVals : ($optVals ?: '(free text)'));
                $rowClass  = $q->depends_on_question_id ? 'table-warning' : '';
                $slugCell  = $q->slug
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
    }
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

/* Program switcher */
.prog-switcher { background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:.6rem .8rem; display:flex; align-items:center; gap:.5rem; margin-bottom:1.25rem }
.prog-switcher .label { font-size:.78rem; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.04em; margin-right:.25rem }
.prog-btn { border-radius:6px; font-size:.82rem; font-weight:600; padding:4px 14px; cursor:pointer; border:2px solid transparent; transition:all .15s }
.prog-btn.glp { background:#4361ee; color:#fff; border-color:#4361ee }
.prog-btn.glp.inactive { background:#fff; color:#4361ee; border-color:#4361ee }
.prog-btn.nad { background:#198754; color:#fff; border-color:#198754 }
.prog-btn.nad.inactive { background:#fff; color:#198754; border-color:#198754 }
.prog-indicator { font-size:.75rem; padding:2px 8px; border-radius:4px; font-weight:600 }
.prog-indicator.glp { background:#4361ee18; color:#4361ee }
.prog-indicator.nad { background:#19875418; color:#198754 }

/* Diff comparison table */
.diff-table th { font-size:.8rem; font-weight:700 }
.diff-table td { font-size:.82rem; vertical-align:middle }
.diff-table td.common { color:#6c757d; font-style:italic }
.diff-table td.glp-val code, .diff-table td.nad-val code { font-size:.78rem }
.diff-glp { background:#4361ee08 }
.diff-nad { background:#19875408 }

/* Hide prog-specific sections by default (GLP shown, NAD hidden) */
.prog-nad { display:none }

@media print {
    nav.sidebar, .topbar, .col-lg-3, button, .copy-btn, .prog-switcher { display:none !important }
    .main-content { margin-left:0 !important }
    .p-4 { padding:.5rem !important }
    .col-lg-9 { width:100% !important; max-width:100% !important; flex:0 0 100% !important }
    pre { background:#f5f5f5 !important; color:#111 !important; border:1px solid #ccc !important; page-break-inside:avoid }
    .card { page-break-inside:avoid; border:1px solid #ccc !important; margin-bottom:1rem !important }
    a { color:inherit !important; text-decoration:none !important }
}
</style>

<div class="row g-4">

{{-- ── TOC ────────────────────────────────────────────────── --}}
<div class="col-lg-3 d-none d-lg-block">
<div class="card sticky-top" style="top:1rem">
<div class="card-header py-2 px-3">
    <div class="fw-semibold small mb-2">Program</div>
    <div class="d-flex gap-1 mb-2">
        <button class="btn btn-sm prog-btn glp" id="toc-btn-glp" onclick="setProgram('glp')">GLP-1</button>
        <button class="btn btn-sm prog-btn nad inactive" id="toc-btn-nad" onclick="setProgram('nad')">NAD+</button>
    </div>
    <strong class="small">Contents</strong>
</div>
<div class="card-body py-2 px-3">
<ol class="mb-0 ps-3" style="line-height:2">
    <li><a class="toc-link text-decoration-none" href="#auth">Authentication</a></li>
    <li><a class="toc-link text-decoration-none" href="#discover">Discover Question Slugs</a></li>
    <li class="prog-glp toc-upload-li"><a class="toc-link text-decoration-none" href="#upload">Upload Prescription Image</a></li>
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

{{-- ── PROGRAM SWITCHER ────────────────────────────────────── --}}
<div class="prog-switcher">
    <span class="label">Program:</span>
    <button class="prog-btn glp" id="main-btn-glp" onclick="setProgram('glp')"><i class="bi bi-journal-medical me-1"></i>GLP-1 (Weight Loss)</button>
    <button class="prog-btn nad inactive" id="main-btn-nad" onclick="setProgram('nad')"><i class="bi bi-capsule me-1"></i>NAD+</button>
    <small class="text-muted ms-auto"><i class="bi bi-info-circle me-1"></i>Toggle to see program-specific payload sections</small>
</div>

{{-- ── KEY DIFFERENCES TABLE ───────────────────────────────── --}}
<div class="card mb-4 border-2" style="border-color:#dee2e6">
<div class="card-header fw-semibold py-2">
    <i class="bi bi-table me-2 text-secondary"></i>Key Differences at a Glance
    <small class="text-muted fw-normal ms-2">— same endpoint, different payload fields</small>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-sm table-bordered mb-0 diff-table">
<thead class="table-dark">
<tr>
    <th style="width:30%">Payload Field</th>
    <th class="diff-glp" style="width:35%"><span class="prog-indicator glp">GLP-1 (Weight Loss)</span></th>
    <th class="diff-nad" style="width:35%"><span class="prog-indicator nad">NAD+</span></th>
</tr>
</thead>
<tbody>
<tr>
    <td><code>offerings[].product_key</code></td>
    <td class="diff-glp glp-val"><code>"semaglutide"</code>, <code>"tirzepatide"</code>, <code>"semaglutide_tirzepatide"</code>, or <code>"glp1-weightloss"</code></td>
    <td class="diff-nad nad-val"><code>"nad"</code></td>
</tr>
<tr>
    <td><code>offerings[].month_frequency</code></td>
    <td class="diff-glp glp-val">Typically <code>3</code> (months)</td>
    <td class="diff-nad nad-val">Typically <code>1</code> (month)</td>
</tr>
<tr>
    <td>Upload prescription image step</td>
    <td class="diff-glp glp-val"><i class="bi bi-check-circle-fill text-success me-1"></i>Required if patient has Rx photo</td>
    <td class="diff-nad nad-val"><i class="bi bi-dash-circle text-secondary me-1"></i>Not applicable</td>
</tr>
<tr>
    <td><code>answers[]</code> questionnaire</td>
    <td class="diff-glp glp-val">3-step MWL questionnaire (15+ questions: intake, medical history, consents)</td>
    <td class="diff-nad nad-val">1-question cardiac safety screen (<code>heart_arrhythmia</code>)</td>
</tr>
<tr>
    <td><code>clinical_intake.dose</code></td>
    <td class="diff-glp glp-val"><code>"L1 · 2.5 mg"</code> (titration level)</td>
    <td class="diff-nad nad-val"><code>"LVL1 - 500MG"</code> or <code>"LVL2 - 1000MG"</code></td>
</tr>
<tr>
    <td><code>clinical_intake.plan</code></td>
    <td class="diff-glp glp-val"><code>"Titration"</code> / <code>"Starter"</code> / <code>"Maintenance"</code></td>
    <td class="diff-nad nad-val"><code>null</code> — NAD has no titration plans</td>
</tr>
<tr>
    <td><code>clinical_intake.onGlp</code></td>
    <td class="diff-glp glp-val"><code>"Y"</code> or <code>"N"</code> (currently on GLP-1)</td>
    <td class="diff-nad nad-val"><em class="text-muted">Omit this field</em></td>
</tr>
<tr>
    <td>Disqualification trigger</td>
    <td class="diff-glp glp-val">Any option with <code>is_disqualify: true</code> selected</td>
    <td class="diff-nad nad-val"><code>heart_arrhythmia = "Yes"</code></td>
</tr>
</tbody>
</table>
</div>
</div>
</div>

{{-- ── OVERVIEW ─────────────────────────────────────────────── --}}
<div class="prog-glp">
<div class="alert alert-primary border-0 mb-4">
    <strong><i class="bi bi-info-circle me-2"></i>Overview — GLP-1 (Weight Loss)</strong><br>
    <ul class="mb-0 mt-1">
        <li>A single <strong>GET</strong> to the MWL questionnaire returns <em>all</em> questions — standard intake (Step 1), program-specific medical history (Step 2), and consents (Step 3) — in one flat list, each tagged with a stable <code>slug</code>.</li>
        <li>A single <strong>POST</strong> to <code>/api/partner/cases</code> with your <strong>Offering ID</strong> + a flat <code>answers</code> array. <strong>No questionnaire UUID needed at submission time.</strong></li>
        <li>The <code>patient</code> block requires <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong>.</li>
        <li>If the patient has a GLP-1 prescription photo, upload it first via <code>POST /api/partner/files</code> and pass the <code>file_token</code> in <code>answers</code>.</li>
        <li>Send your <strong>Vouched IDV result</strong> in <code>patient.id_verified_status</code>. For async flows, push later via <code>PATCH /api/partner/patients/{uuid}</code>.</li>
    </ul>
</div>
</div>

<div class="prog-nad">
<div class="alert alert-success border-0 mb-4">
    <strong><i class="bi bi-info-circle me-2"></i>Overview — NAD+ (Nicotinamide Adenine Dinucleotide)</strong><br>
    <ul class="mb-0 mt-1">
        <li>A single <strong>GET</strong> to the NAD questionnaire returns the cardiac safety-screen questions — short by design.</li>
        <li>A single <strong>POST</strong> to <code>/api/partner/cases</code> with your <strong>Offering ID</strong> + a flat <code>answers</code> array. <strong>No questionnaire UUID needed at submission time.</strong></li>
        <li>The <code>patient</code> block requires <strong>height</strong> (inches), <strong>weight</strong> (lbs), and <strong>bmi</strong>.</li>
        <li><strong>No prescription image upload step</strong> — skip that step entirely for NAD cases.</li>
        <li><strong>Disqualification:</strong> <code>heart_arrhythmia = "Yes"</code> still creates the case — the clinician decides next steps. Never suppress the submission.</li>
        <li>NAD+ is available standalone or bundled with GLP-1 via <code>bundle_group</code>.</li>
    </ul>
</div>
</div>

{{-- ── 1. AUTH ──────────────────────────────────────────────── --}}
<div id="auth" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">1</span>Authentication <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span></div>
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
<p class="mt-3 mb-0 text-muted small">Add to every subsequent request: <code>Authorization: Bearer &lt;access_token&gt;</code></p>
</div>
</div>

{{-- ── 2. DISCOVER ──────────────────────────────────────────── --}}
<div id="discover" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">2</span>Discover Question Slugs <span class="text-muted fw-normal small">(one call per environment)</span></div>
<div class="card-body">

{{-- GLP discover --}}
<div class="prog-glp">
<p class="mb-3">Call the MWL questionnaire endpoint once. Returns <strong>all questions</strong> — standard intake, program-specific medical history, and consents — in a flat list. Each question has a stable <code>slug</code>.</p>
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-get">GET</span>
    <code>{{ $base }}/api/partner/questionnaires/{{ $glpQUuid }}</code>
</div>
<pre id="code-discover-glp">GET {{ $base }}/api/partner/questionnaires/{{ $glpQUuid }}
Authorization: Bearer &lt;access_token&gt;</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-glp')">Copy</button>

<p class="mt-3 mb-1"><strong>Response shape</strong></p>
<pre id="code-discover-glp-resp">{
  "uuid":        "{{ $glpQUuid }}",
  "name":        "GLP Questionnaire",
  "questions": [
    // ── Step 1: Standard Intake questions ──
    {
      "slug":        "pregnant_breastfeeding",
      "key":         "pregnant_breastfeeding",
      "question":    "If female — are you currently pregnant or breastfeeding?",
      "type":        "choice",
      "is_required": true,
      "options":     [{"value":"yes","is_disqualify":true},{"value":"no"}],
      "step_number": 1
    },
    // … more Step 1 questions …

    // ── Step 2: Program-Specific Medical Intake ──
    {
      "slug":        "mwl_medical_conditions",
      "key":         "mwl_medical_conditions",
      "question":    "Please check all current or past medical conditions …",
      "type":        "multi",
      "is_required": true,
      "options":     [{"value":"gastroparesis","is_disqualify":true}, …],
      "step_number": 2
    }
    // … Step 2 & 3 questions (GLP-1 history, allergies, consents) …
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-glp-resp')">Copy</button>
</div>

{{-- NAD discover --}}
<div class="prog-nad">
<p class="mb-3">Call the NAD questionnaire endpoint once. Returns the cardiac safety-screen question(s) with stable <code>slug</code>s. The NAD questionnaire is short by design — always fetch live rather than hard-coding slugs, as admins may add questions over time.</p>
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-get">GET</span>
    <code>{{ $base }}/api/partner/questionnaires/{{ $nadQUuid }}</code>
</div>
<pre id="code-discover-nad">GET {{ $base }}/api/partner/questionnaires/{{ $nadQUuid }}
Authorization: Bearer &lt;access_token&gt;</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-nad')">Copy</button>

<p class="mt-3 mb-1"><strong>Response shape</strong></p>
<pre id="code-discover-nad-resp">{
  "uuid":      "{{ $nadQUuid }}",
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
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-discover-nad-resp')">Copy</button>
<div class="alert alert-warning mt-3 mb-0 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Disqualifying answer:</strong> <code>"Yes"</code> to the heart arrhythmia question is flagged as disqualifying.
    The case is still <strong>created</strong> — do not block submission — but the clinician sees a disqualification notice and makes the final clinical decision.
</div>
</div>

<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-lightbulb me-1"></i>
    Store <code>slug → question_id</code> in your DB. Use <code>slug</code> in all case submissions — <strong>slugs never change</strong> even if the questionnaire is edited. Numeric <code>id</code>s change on every questionnaire rebuild.
</div>
</div>
</div>

{{-- ── 3. UPLOAD ────────────────────────────────────────────── --}}
{{-- GLP only — hidden for NAD --}}
<div id="upload" class="card mb-4 section-anchor prog-glp">
<div class="card-header fw-semibold">
    <span class="step-badge bg-primary text-white me-2">3</span>Upload Prescription Image
    <span class="text-muted fw-normal small">(GLP-1 only — skip for NAD+)</span>
    <span class="badge ms-2" style="background:#4361ee18;color:#4361ee;font-size:.7rem">GLP-1</span>
</div>
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
    Submit a flat <code>answers</code> array — the portal looks up which questionnaire each slug belongs to from the <code>offering_id</code> you send.
</div>

<div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/cases</code>
</div>

{{-- GLP payload --}}
<div class="prog-glp">
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="prog-indicator glp">GLP-1 (Weight Loss) payload</span>
    <small class="text-muted">— includes 3-step questionnaire answers, titration clinical intake</small>
</div>
<pre id="code-create-glp">POST {{ $base }}/api/partner/cases
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
    "external_id":   "portal-user-9001",
    "id_verified_status": "verified",   ← Vouched result: verified | failed | pending
    "id_verified_at":     "2026-07-16T10:30:00Z"
  },
  "patient_state":  "TX",
  "external_id":    "order-glp-20240701-001",
  "visit_type":     "asynchronous",  // "asynchronous" (standard) | "synchronous" (video required)
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      false,
  "sub_storefront_id": "e3b0c442-...",   // optional — UUID from POST /api/partner/sub-storefronts
  "metadata":       { "source": "patient-portal" },

  // ── offerings ────────────────────────────────────────────────────────────────
  // GLP-1 product_key: "semaglutide", "tirzepatide", "semaglutide_tirzepatide", or "glp1-weightloss"
  // typical month_frequency: 3
  "offerings": [
    { "offering_id": "YOUR_MWL_OFFERING_UUID", "quantity": 1 }          // Option A: direct UUID (legacy)
    // { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "formulation": "injectable" }  // Option B: product key
    // "formulation": "injectable" | "oral" — optional; auto-selects the clinician's medication dropdown
    // Bundle with NAD+:
    // { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" },
    // { "product_key": "nad",         "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" }
  ],

  // ── clinical_intake (GLP-1 fields) ───────────────────────────────────────────
  "clinical_intake": {
    "product":         "Semaglutide",     // product name — matches offering name in DB
    "term":            "3M",              // 1M | 3M | 4M | 6M | 12M — must match month_frequency
    "dose":            "L1 · 2.5 mg",    // requested starting dose (titration level)
    "plan":            "Titration",       // "Titration" | "Starter" | "Maintenance"
    "onGlp":           "N",              // currently on GLP-1: "Y" | "N"   ← GLP-1 specific
    "allergy":         "N",              // GLP-1 allergy: "Y" | "N"
    "allergyDetail":   null,             // required when allergy = "Y"
    "zofran":          "N",              // anti-nausea rider: "Y" | "N"
    "video":           "not required",
    "protocolVersion": "GLP-1 protocol v8",
    "findings":        [],               // optional — triage flag slugs from your protocol engine
    "summary":         [],               // optional — human-readable approval summary lines
    "sourceAnswers": {
      "productPick":                  "semaglutide",
      "glp1Allergies":                "No known allergies",
      "currentGlucoseMedications":    "None",
      "weightLossMedications":        "None",
      "gastricBypass6Months":         "No"
    }
  },

  // ── answers (3-step MWL questionnaire — generated live from DB) ───────────
  "answers": [{{ $glpPayloadAnswers }}

  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-create-glp')">Copy</button>
</div>

{{-- NAD payload --}}
<div class="prog-nad">
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="prog-indicator nad">NAD+ payload</span>
    <small class="text-muted">— 1-question cardiac safety screen, no upload step, no titration plan</small>
</div>
<pre id="code-create-nad">POST {{ $base }}/api/partner/cases
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
    "height":        70.0,             ← required  (inches)
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
  "visit_type":     "asynchronous",
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      false,
  "sub_storefront_id": "e3b0c442-...",   // optional
  "metadata":       { "source": "patient-portal" },

  // ── offerings ────────────────────────────────────────────────────────────────
  // NAD+ product_key: "nad"
  // typical month_frequency: 1
  "offerings": [
    { "offering_id": "YOUR_NAD_OFFERING_UUID", "quantity": 1 }          // Option A: direct UUID (legacy)
    // { "product_key": "nad", "month_frequency": 1, "quantity": 1, "formulation": "injectable" }  // Option B: product key
    // NAD is always injectable — send "injectable" or omit formulation entirely
    // Bundle with GLP-1:
    // { "product_key": "nad",         "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" },
    // { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" }
  ],

  // ── clinical_intake (NAD+ fields) ────────────────────────────────────────────
  "clinical_intake": {
    "product":         "NAD+ (Nicotinamide Adenine Dinucleotide)",  // or "NAD+/Glutathione" for combo
    "term":            "1M",              // 1M | 3M | 6M | 12M
    "dose":            "LVL1 - 500MG",   // NAD dose: "LVL1 - 500MG" | "LVL2 - 1000MG"
                                          // For NAD+/Glutathione: "LVL1 - 500MG/500MG" | "LVL2 - 1000MG/1000MG"
    "plan":            null,              // ← NAD has NO titration plans — omit or null
    // "onGlp" field is NOT used for NAD — omit entirely
    "allergy":         "N",              // known allergy to NAD components: "Y" | "N"
    "allergyDetail":   null,             // required when allergy = "Y"
    "zofran":          "N",              // anti-nausea rider — send "Y" if patient needs it, else "N"
    "video":           "not required",
    "protocolVersion": "NAD protocol v1",
    "findings":        [],               // optional — triage flag slugs from your protocol engine
    "summary":         [],               // optional — human-readable approval summary lines
    "sourceAnswers": {
      "heartArrhythmia": "No",           // answer to the cardiac safety screen
      "requestedDose":   "500 MG",       // patient's selected dose level
      "deliveryMethod":  "Intramuscular" // "Intramuscular" | "Intravenous"
    }
  },

  // ── answers (NAD safety screen — 1 question, generated live from DB) ─────
  "answers": [{{ $nadPayloadAnswers }}

  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-create-nad')">Copy</button>
</div>

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

<div class="alert alert-info mt-3 small">
    <i class="bi bi-shield-check me-1"></i>
    <strong>Async Vouched flow:</strong> If your Vouched check completes <em>after</em> the case is created, push the result via
    <code>PATCH {{ $base }}/api/partner/patients/{patient_uuid}</code> with
    <code>{ "id_verified_status": "verified", "id_verified_at": "…" }</code>.
    The portal re-classifies all open cases for that patient automatically.
</div>
<div class="alert alert-warning mt-0 mb-0 small">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Payload is generated live from the DB.</strong>
    Lines marked <code>// conditional</code> must be <strong>omitted</strong> when the parent condition was not met.
    Lines marked <code>// optional</code> may always be omitted.
    Disqualifying answers still create the case — the doctor sees a flag and decides.
    The system silently ignores answers for untriggered conditions.
</div>
</div>
</div>

{{-- ── 5. REFILL / CHECK-IN ────────────────────────────────── --}}
<div id="refill" class="card mb-4 section-anchor">
<div class="card-header fw-semibold">
    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Refill / Check-in Cases <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span>
</div>
<div class="card-body">
<p class="mb-3">Set <code>"is_refill": true</code> when a patient is returning for a follow-up visit. The platform handles three things automatically.</p>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-person-check me-1 text-primary"></i>Routing continuity</div>
            <p class="small mb-0 text-muted">Routed to the same clinician who handled the patient's most recent completed visit. When the case belongs to a sub-storefront, only prior visits within that same sub-storefront are considered; otherwise the match is partner-wide. Normal routing applies as a fallback.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-clipboard2-check me-1 text-success"></i>Check-in questionnaire</div>
            <p class="small mb-0 text-muted">Resolves which questionnaire to use, in priority order:</p>
            <ol class="small mb-0 mt-1 ps-3 text-muted">
                <li>Per-offering <code>check_in</code> questionnaire</li>
                <li>Category-level default</li>
                <li>Initial intake (always-available fallback)</li>
            </ol>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 bg-light rounded border h-100">
            <div class="fw-semibold mb-1"><i class="bi bi-card-list me-1 text-info"></i>Prior visit panel</div>
            <p class="small mb-0 text-muted">The reviewing clinician sees a collapsible panel showing the patient's prior prescription, intake answers, and clinical note — no extra API calls needed.</p>
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

<h6 class="fw-semibold mt-3 mb-2">Minimal Refill Payload</h6>
<p class="small text-muted mb-2">Send the same <code>POST /api/partner/cases</code> endpoint with <code>"is_refill": true</code>. You only need to include fields that have changed — patient demographics, offerings, and answers. The portal will match the patient by <code>external_id</code> (or email + DOB) and link this as a follow-up to their prior visit.</p>
<pre id="code-refill-example">POST {{ $base }}/api/partner/cases
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "patient": {
    "first_name":    "Jane",
    "last_name":     "Doe",
    "email":         "jane.doe@example.com",
    "date_of_birth": "1990-06-15",
    "height":        65.0,
    "weight":        182.0,          // updated current weight
    "bmi":           30.3,
    "state":         "TX",
    "external_id":   "portal-user-1001"   // must match the original case's patient external_id
  },
  "patient_state":  "TX",
  "external_id":    "order-glp-refill-001",  // new unique order ID for this visit
  "visit_type":     "asynchronous",
  "is_chargeable":  true,
  "hold_status":    false,
  "is_refill":      true,            // ← this is the key flag

  "offerings": [
    { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "formulation": "injectable" }
  ],

  // ── Check-in answers — Refill General Check-In questionnaire ───────────────
  // Slugs below match the "Refill General Check-In" questionnaire (purpose=check_in).
  // Conditional follow-up fields only need to be sent when their parent answer applies.
  // Retrieve the exact slugs via: GET /api/partner/questionnaires/{uuid}
  "answers": [
    { "slug": "medication_tolerance",    "answer": "mild_side_effects" },
    { "slug": "side_effects",            "answer": "Mild nausea in the mornings, resolved after week 2" },
    { "slug": "weight_change",           "answer": "lost_weight" },
    { "slug": "weight_change_details",   "answer": "Lost approximately 8 lbs over 3 months" },
    { "slug": "new_medications",         "answer": "no" },
    { "slug": "new_conditions",          "answer": "no" },
    { "slug": "dose_continuation",       "answer": "same_dose" },
    { "slug": "additional_notes",        "answer": "Feeling great overall, energy levels improved." }
  ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-refill-example')">Copy</button>

<h6 class="fw-semibold mt-3 mb-2">What changes vs. a new case</h6>
<div class="table-responsive">
<table class="table table-sm table-bordered small mb-3">
<thead class="table-light"><tr><th>Field</th><th>New case</th><th>Refill case</th></tr></thead>
<tbody>
<tr><td><code>is_refill</code></td><td><code>false</code> (or omit)</td><td><code>true</code></td></tr>
<tr><td><code>external_id</code></td><td>First order ID</td><td>New unique ID for this visit</td></tr>
<tr><td><code>patient.external_id</code></td><td>Set here</td><td>Same value — used to match prior visit</td></tr>
<tr><td><code>patient.weight</code> / <code>bmi</code></td><td>Initial</td><td>Current (updated)</td></tr>
<tr><td><code>answers</code></td><td>Full intake questionnaire</td><td>Check-in questionnaire (configured per-offering or category)</td></tr>
<tr><td>Routing</td><td>Assigned by routing policy</td><td>Continuity — goes to same clinician as prior visit</td></tr>
<tr><td><code>external_id</code> duplicate check</td><td>Returns 409 if duplicate</td><td>Skipped — same patient can have multiple refill orders</td></tr>
</tbody>
</table>
</div>

<div class="alert alert-success border-0 small mt-3 mb-0 py-2">
    <i class="bi bi-check-circle me-1"></i>
    <strong>No breaking changes.</strong> Omitting <code>is_refill</code> or sending <code>false</code> behaves exactly as before. Existing integrations require no updates.
</div>
</div>
</div>

{{-- ── 6. PRODUCT PLANS ─────────────────────────────────────── --}}
<div id="product-plans" class="card mb-4 section-anchor">
<div class="card-header fw-semibold">
    <i class="bi bi-grid me-2 text-secondary"></i>Product Plans — One-to-Many Offering Mapping
</div>
<div class="card-body">

<p class="mb-3">
    Use your own stable <code>product_key</code> identifiers + <code>month_frequency</code> — the portal resolves to the correct offerings internally (Option B). Or send a direct <code>offering_id</code> UUID (Option A — always supported).
</p>

{{-- GLP Product Plans --}}
<div class="prog-glp">
<div class="mb-3"><span class="prog-indicator glp">GLP-1</span></div>
<h6 class="fw-semibold mb-2">Option A — Legacy (no changes required)</h6>
<pre class="mb-3">"offerings": [
  { "offering_id": "YOUR_MWL_OFFERING_UUID", "month_frequency": 3, "quantity": 1 }
]</pre>
<h6 class="fw-semibold mb-2">Option B — Product Plans</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "formulation": "injectable" }
  // formulation: "injectable" | "oral" (optional) — auto-selects the clinician's medication dropdown.
  // For semaglutide: "oral" picks the SNAC tablet; "injectable" picks B12 or B6. Omit to leave choice to the clinician.
  // or: { "product_key": "tirzepatide",            "month_frequency": 3, "quantity": 1, "formulation": "injectable" }
  // or: { "product_key": "semaglutide_tirzepatide", "month_frequency": 3, "quantity": 1, "formulation": "injectable" }
  // or: { "product_key": "glp1-weightloss",         "month_frequency": 3, "quantity": 1, "formulation": "injectable" }
]</pre>
<h6 class="fw-semibold mb-2">Option C — Bundle (GLP-1 + NAD+)</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" },
  { "product_key": "nad",         "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" }
]
// bundle_group is any string you choose — offerings sharing the same value are treated
// as one bundle on the prescribe screen. Each gets its own locked dropdown.</pre>

<h6 class="fw-semibold mb-2"><code>prescription_written</code> Webhook — GLP-1 Payload</h6>
<pre id="code-webhook-glp">{
  "case_id":        "case-uuid",
  "external_id":    "your-order-id",
  "patient_id":     "patient-uuid",
  "clinician_name": "Dr. Jane Smith",
  "clinician_npi":  "1234567890",
  "diagnoses":      [{ "code": "E66.01", "description": "Morbid (severe) obesity …" }],
  "meds_prescribed": [{
    "name":   "Semaglutide",
    "sig":    "Inject 0.25 mL subcutaneously weekly",
    "dosing": {
      "medication": "Semaglutide",
      "frequency":  "Weekly",
      "term":       "3M",
      "months":     ["L1 · 2.5 mg", "L1 · 2.5 mg", "L2 · 5 mg"]
    }
  }],
  "offerings": [
    { "offering_id": "uuid-snac", "product_key": "semaglutide", "month_frequency": 3, "bundle_group": null }
  ],
  "timestamp": 1722000000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-webhook-glp')">Copy</button>
</div>

{{-- NAD Product Plans --}}
<div class="prog-nad">
<div class="mb-3"><span class="prog-indicator nad">NAD+</span></div>

<h6 class="fw-semibold mb-2">Available NAD Offerings</h6>
<div class="table-responsive mb-3">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light"><tr><th>Product Name</th><th>Internal Name</th><th>Levels / Doses</th><th>Route</th></tr></thead>
<tbody>
<tr>
    <td>NAD+ (Nicotinamide Adenine Dinucleotide)</td><td><code>NAD+</code></td>
    <td>LVL1 - 500MG (100mg/mL, 5mL)<br>LVL2 - 1000MG (100mg/mL, 10mL)</td><td>IV / IM</td>
</tr>
<tr>
    <td>NAD+/Glutathione</td><td><code>NAD+/Glut</code></td>
    <td>LVL1 - 500MG/500MG<br>LVL2 - 1000MG/1000MG</td><td>IV / IM</td>
</tr>
</tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Option A — Legacy</h6>
<pre class="mb-3">"offerings": [
  { "offering_id": "YOUR_NAD_OFFERING_UUID", "month_frequency": 1, "quantity": 1 }
]</pre>
<h6 class="fw-semibold mb-2">Option B — Product Plans</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "nad", "month_frequency": 1, "quantity": 1, "formulation": "injectable" }
  // formulation: "injectable" | "oral" (optional). NAD is always injectable — send "injectable" or omit.
]
// "product_key": "nad" can fan out to both NAD+ and NAD+/Glutathione
// simultaneously if both are configured as plan rows.</pre>
<h6 class="fw-semibold mb-2">Option C — Bundle (NAD+ + GLP-1)</h6>
<pre class="mb-3">"offerings": [
  { "product_key": "nad",         "month_frequency": 1, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" },
  { "product_key": "semaglutide", "month_frequency": 3, "quantity": 1, "bundle_group": "combo-1", "formulation": "injectable" }
]</pre>

<h6 class="fw-semibold mb-2"><code>prescription_written</code> Webhook — NAD+ Payload</h6>
<pre id="code-webhook-nad">{
  "case_id":        "case-uuid",
  "external_id":    "order-nad-20260828-001",
  "patient_id":     "patient-uuid",
  "clinician_name": "Dr. Jane Smith",
  "clinician_npi":  "1234567890",
  "diagnoses":      [{ "code": "Z71.89", "description": "Encounter for other specified counseling" }],
  "meds_prescribed": [{
    "name":   "NAD+ (Nicotinamide Adenine Dinucleotide)",
    "sig":    "Administer intravenously or intramuscularly as directed by your provider.",
    "dosing": {
      "medication": "NAD+",
      "frequency":  "Monthly",
      "term":       "1M",
      "dose":       "LVL1 - 500MG"
    }
  }],
  "offerings": [
    { "offering_id": "uuid-nad-500", "product_key": "nad", "month_frequency": 1, "bundle_group": null }
  ],
  "timestamp": 1722000000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-webhook-nad')">Copy</button>
</div>

<div class="alert alert-warning border-0 small py-2 mt-3 mb-0">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Setup required (Option B / C only):</strong> Admin must configure at least one Product Plan row in Admin → Partners → <em>Product Plans</em>.
    Missing plans return <code>422 No product plan found for product_key "…"</code>.
</div>
</div>
</div>

{{-- ── 7. QUESTION REFERENCE ───────────────────────────────── --}}
<div id="questions" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">5</span>Question Reference</div>
<div class="card-body p-0">

{{-- GLP questions table --}}
<div class="prog-glp">
<div class="px-3 pt-3 pb-1 small fw-semibold text-muted text-uppercase d-flex align-items-center gap-2" style="letter-spacing:.04em">
    <span>MWL – Weight Loss Questionnaire</span>
    <span class="prog-indicator glp">GLP-1</span>
</div>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 q-table">
<thead class="table-light">
<tr><th>Key</th><th>Slug</th><th>Type</th><th>Step</th><th>Condition</th><th>Accepted Values</th></tr>
</thead>
<tbody>
@if($glpQuestions->isNotEmpty())
{!! renderApiQRows($glpQuestions, $glpQuestions) !!}
@else
<tr><td colspan="6" class="text-center text-muted py-3 small">No GLP questions found — run MWLWeightLossSeeder on this environment.</td></tr>
@endif
</tbody>
</table>
</div>
<div class="px-3 py-2 small text-muted bg-light border-top">
    <i class="bi bi-exclamation-circle me-1"></i><strong>Yellow rows</strong> are conditional — only send when condition is met.
    &nbsp;|&nbsp;<span class="badge bg-info text-dark">multi</span> answers must be JSON arrays.
    &nbsp;|&nbsp;<strong>Disqualifying</strong> answers create the case with a disqualification flag.
</div>
</div>

{{-- NAD questions table --}}
<div class="prog-nad">
<div class="px-3 pt-3 pb-1 small fw-semibold text-muted text-uppercase d-flex align-items-center gap-2" style="letter-spacing:.04em">
    <span>NAD Questionnaire — Safety Screen</span>
    <span class="prog-indicator nad">NAD+</span>
</div>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 q-table">
<thead class="table-light">
<tr><th>Key</th><th>Slug</th><th>Type</th><th>Step</th><th>Condition</th><th>Accepted Values</th></tr>
</thead>
<tbody>
@if($nadQuestions->isNotEmpty())
{!! renderApiQRows($nadQuestions, $nadQuestions) !!}
@else
<tr><td colspan="6" class="text-center text-muted py-3 small">No NAD questions found — run NadQuestionnaireSeeder on this environment.</td></tr>
@endif
</tbody>
</table>
</div>
<div class="px-3 py-2 small text-muted bg-light border-top">
    <i class="bi bi-exclamation-circle me-1"></i><strong>Disqualifying:</strong> <code>heart_arrhythmia: "Yes"</code> flags the case — still created, clinician decides.
    &nbsp;|&nbsp;Safe answer: <code>"No"</code>.
</div>
</div>

</div>
</div>

{{-- ── 8. ERRORS ───────────────────────────────────────────── --}}
<div id="errors" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-danger text-white me-2">6</span>Error Responses <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span></div>
<div class="card-body p-0">
<table class="table table-sm mb-0 err-table">
<thead class="table-light"><tr><th>HTTP</th><th>When</th><th>Example body</th></tr></thead>
<tbody>
<tr>
    <td><span class="badge bg-danger">401</span></td>
    <td>Token missing, expired, or malformed</td>
    <td><code>{"message":"Unauthenticated."}</code></td>
</tr>
<tr>
    <td><span class="badge bg-danger">403</span></td>
    <td>Token valid but partner account inactive or suspended</td>
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
    <td>Required questionnaire not submitted for an attached offering</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The given data was invalid.",
  "errors": {
    "questionnaire_responses": ["Required questionnaires not submitted: {uuid}"]
  }
}</pre></td>
</tr>
<tr>
    <td><span class="badge bg-secondary">422</span></td>
    <td>File upload (GLP-1 only) — wrong type or too large</td>
    <td><pre class="mt-1 mb-0" style="font-size:.75rem">{
  "message": "The file field must be a file of type: pdf, jpg, jpeg, png.",
  "errors": { "file": ["…"] }
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

{{-- ── 9. DB RESULT ─────────────────────────────────────────── --}}
<div id="db" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-success text-white me-2">7</span>What Gets Created in the Database <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span></div>
<div class="card-body">
<p class="mb-3">A single successful <code>POST /api/partner/cases</code> atomically creates:</p>
<div class="row g-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-person-circle me-2 text-primary"></i>Patient</h6>
            <ul class="small mb-0 ps-3">
                <li>Looked up by <code>external_id</code> first, then <code>email</code></li>
                <li>Created if no match; fields updated if found</li>
                <li>Linked to the partner account</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-folder2 me-2 text-primary"></i>Case</h6>
            <ul class="small mb-0 ps-3">
                <li>Status set to <strong>waiting</strong> (unless <code>hold_status: true</code>)</li>
                <li>Linked to patient and partner</li>
                <li><code>external_id</code> stored for idempotency</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-ui-checks me-2 text-primary"></i>Questionnaire Response + Answers</h6>
            <ul class="small mb-0 ps-3">
                <li>One <code>QuestionnaireResponse</code> per questionnaire submitted</li>
                <li>One <code>QuestionnaireAnswer</code> per question, text frozen at submission time</li>
                <li>Disqualification flag set automatically if a disqualifying option selected</li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h6 class="fw-semibold mb-2"><i class="bi bi-image me-2 text-primary"></i>Prescription Image <span class="prog-indicator glp" style="font-size:.65rem">GLP-1 only</span></h6>
            <ul class="small mb-0 ps-3">
                <li>File saved to <code>storage/app/patient-files/YYYY/MM/</code></li>
                <li>ClamAV virus scan queued automatically (non-blocking)</li>
                <li>Linked to patient, case, and partner</li>
            </ul>
        </div>
    </div>
</div>
<div class="alert alert-info mt-3 mb-0 small">
    <i class="bi bi-arrow-right-circle me-1"></i>
    After the case is created with status <strong>waiting</strong>, a clinician picks it up, reviews the answers, and approves or requests more information. You receive webhook events at each status change if a webhook endpoint is registered.
</div>
</div>
</div>

{{-- ── 10. PUSH CLINICAL INTAKE ──────────────────────────────── --}}
<div id="clinical" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">8</span>Push Clinical Intake Data <span class="text-muted fw-normal small">(optional, post-creation update)</span></div>
<div class="card-body">
<p class="mb-2">If your storefront collects clinical details after case creation — or if data changes — push it with this endpoint. It <strong>replaces</strong> the clinical intake block wholesale.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_uuid}/clinical</code>
</div>

{{-- GLP clinical intake --}}
<div class="prog-glp">
<div class="mb-2"><span class="prog-indicator glp">GLP-1</span></div>
<pre id="code-clinical-glp">POST {{ $base }}/api/partner/cases/{case_uuid}/clinical
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "clinical_intake": {
    "product":         "Semaglutide",
    "dose":            "0.25 mg",          // current dose (titration level)
    "term":            "3M",               // 1M | 3M | 6M | 12M
    "plan":            "M1",               // plan tier
    "onGlp":           "yes",             // currently on GLP-1 ← GLP-1 specific field
    "zofran":          "no",              // anti-nausea rider
    "allergy":         "no",
    "allergyDetail":   null,
    "video":           "not_required",
    "protocolVersion": "v2.1",
    "findings":        ["bmi_30_39"],
    "summary":         ["approved_for_glp1"],
    "sourceAnswers":   { "custom_key": "val" },
    "comorbidities":   ["hypertension", "type_2_diabetes", "sleep_apnea"]
  }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-clinical-glp')">Copy</button>
</div>

{{-- NAD clinical intake --}}
<div class="prog-nad">
<div class="mb-2"><span class="prog-indicator nad">NAD+</span></div>
<pre id="code-clinical-nad">POST {{ $base }}/api/partner/cases/{case_uuid}/clinical
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "clinical_intake": {
    "product":         "NAD+ (Nicotinamide Adenine Dinucleotide)",
    "dose":            "LVL2 - 1000MG",    // updated dose level  ← NAD specific values
    "term":            "1M",               // 1M | 3M | 6M | 12M
    "plan":            null,               // ← NAD has NO titration plans — null or omit
    // "onGlp" is NOT a NAD field — omit entirely
    "allergy":         "no",
    "allergyDetail":   null,
    "video":           "not_required",
    "protocolVersion": "NAD protocol v1",
    "sourceAnswers":   { "deliveryMethod": "Intravenous", "requestedDose": "1000 MG" },
    "comorbidities":   ["fatigue", "cognitive_decline"]
  }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-clinical-nad')">Copy</button>
</div>

<p class="mt-3 mb-1"><strong>Success 200</strong></p>
<pre id="code-clinical-resp">{
  "message": "Clinical intake updated.",
  "case": { "uuid": "case-uuid-here", "status": "waiting", ... }
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-clinical-resp')">Copy</button>
</div>
</div>

{{-- ── 11. ADDITIONAL ENDPOINTS ──────────────────────────────── --}}
<div id="endpoints" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">9</span>Additional API Endpoints <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span></div>
<div class="card-body">
<h6 class="fw-semibold mb-2">Patient Management</h6>
<table class="table table-sm table-bordered mb-3" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients</code></td><td>List all patients for this partner (paginated)</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Get single patient by UUID</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/patients/by-external-id/{id}</code></td><td>Look up patient by your <code>external_id</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/patients</code></td><td>Create a standalone patient record (no case)</td></tr>
<tr><td><span class="badge-method method-post" style="background:#fff3cd;color:#664d03">PATCH</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Update patient fields — use for async Vouched IDV pushes</td></tr>
<tr><td><span class="badge-method method-post" style="background:#f8d7da;color:#842029">DELETE</span></td><td><code>/api/partner/patients/{uuid}</code></td><td>Soft-delete patient — fires <code>patient_deleted</code> webhook</td></tr>
</tbody>
</table>

<h6 class="fw-semibold mb-2">Case Actions</h6>
<table class="table table-sm table-bordered mb-3" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases</code></td><td>List cases (paginated; filter by <code>?status=</code>)</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}</code></td><td>Get single case — includes patient, clinician, offerings, answers, prescription</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/by-external-id/{id}</code></td><td>Look up case by your <code>external_id</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/cancel</code></td><td>Cancel a case. Body: <code>{ "reason": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/hold</code></td><td>Put on or release a hold. Body: <code>{ "hold": true|false }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/support</code></td><td>Escalate to support. Body: <code>{ "note": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/return-to-clinician</code></td><td>Close escalation and return to clinician. Body: <code>{ "partner_note": "..." }</code> (required)</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}/events</code></td><td>Full event log for the case</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Full message thread. Add <code>?channel=escalation</code> or <code>?channel=portal</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/messages</code></td><td>Send a message. Body: <code>{ "body": "..." }</code></td></tr>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/cases/{uuid}/close-thread</code></td><td>Close a parallel support thread. Fires <code>support_thread_closed</code> webhook.</td></tr>
</tbody>
</table>
</div>
</div>

{{-- ── 12. SUB-STOREFRONTS ─────────────────────────────────────── --}}
<div id="sub-storefronts" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2"><i class="bi bi-diagram-3"></i></span>Sub-Storefronts API <span class="text-muted fw-normal small">(same for GLP-1 and NAD+)</span></div>
<div class="card-body">
<p class="text-muted small mb-3">
    Sub-storefronts sit <strong>below a partner</strong> in the hierarchy. Each maps to its own <strong>Healthie User Group</strong> within the partner's single Healthie org, so patient records are segregated by group. Call <code>POST /api/partner/sub-storefronts</code> once when a new tenant registers — a User Group is created automatically.
</p>
<div class="alert alert-info small py-2 mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>User Group provisioning:</strong> On creation, a <strong>Healthie User Group</strong> is automatically created using the partner's API credentials. The group ID is returned in the response. No passwords or admin accounts are created.
</div>
<h6 class="fw-semibold mb-2">Endpoints</h6>
<table class="table table-sm table-bordered mb-4" style="font-size:.84rem">
<thead class="table-light"><tr><th>Method</th><th>URL</th><th>Purpose</th></tr></thead>
<tbody>
<tr><td><span class="badge-method method-post">POST</span></td><td><code>/api/partner/sub-storefronts</code></td><td>Create sub-storefront and its Healthie User Group</td></tr>
<tr><td><span class="badge-method method-get">GET</span></td><td><code>/api/partner/sub-storefronts</code></td><td>List active sub-storefronts for this partner</td></tr>
</tbody>
</table>
<div class="position-relative mb-3">
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
<p class="small text-muted mt-2 mb-3">Store <code>sub_storefront_id</code> and send it as <code>sub_storefront_id</code> in every case payload for this tenant. <code>healthie.error</code> is non-null when Healthie provisioning fails — the sub-storefront is still created; an admin sets the group ID manually. Cases are pushed to the partner's Healthie org with the patient assigned to that sub-storefront's User Group; the clinician is added to the patient's care team automatically.</p>
<div class="position-relative mb-0">
<pre class="bg-dark text-light rounded p-3 small mb-0" id="code-sf-case">// Include sub_storefront_id in every case submission for this tenant:
{
  "sub_storefront_id": "e3b0c442-98fc-1c14-9afb-f4c8996fb924",
  "patient": { ... },
  "offerings": [ ... ],
  "answers": [ ... ]
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyCode('code-sf-case')">Copy</button>
</div>
</div>
</div>

{{-- ── 13. CHECKLIST ─────────────────────────────────────────── --}}
<div id="checklist" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-secondary text-white me-2">✓</span>Integration Checklist</div>
<div class="card-body">

{{-- GLP checklist --}}
<div class="prog-glp">
<div class="mb-2"><span class="prog-indicator glp">GLP-1 (Weight Loss)</span></div>
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain <code>client_id</code>, <code>client_secret</code>, and your <strong>MWL Offering UUID(s)</strong> from admin (Admin → Partners → Offerings)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>POST /api/partner/auth/token</code> and cache the token (valid 1 year)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>GET /api/partner/questionnaires/{{ $glpQUuid }}</code> once to discover all slugs — store the <code>slug</code> list; you do not need this UUID for submission</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>If patient has a prescription image: <code>POST /api/partner/files</code> → store <code>file_token</code>, pass as answer to <code>mwl_prescription_pic_upload</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Submit <code>POST /api/partner/cases</code> with <code>offerings[].offering_id</code> (legacy) or <code>offerings[].product_key + month_frequency</code> + flat <code>answers[]</code> array. Include <strong>height</strong>, <strong>weight</strong>, <strong>bmi</strong> in <code>patient</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Set <code>clinical_intake.plan</code> to <code>"Titration"</code>, <code>"Starter"</code>, or <code>"Maintenance"</code>. Set <code>clinical_intake.onGlp</code> to <code>"Y"</code> or <code>"N"</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Read <code>prescription_written</code> webhook: iterate <code>offerings[]</code> — each item's <code>product_key</code> + <code>meds_prescribed[0].dosing.term</code> identifies the VRIO SKU. Bundle items share <code>bundle_group</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Include <code>patient.id_verified_status</code> at creation. If async, push later via <code>PATCH /api/partner/patients/{uuid}</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Store the returned <code>uuid</code> for future lookups and messaging</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register a webhook — portal fires: <code>case_waiting</code>, <code>case_assigned_to_clinician</code>, <code>case_support</code>, <code>case_approved</code>, <code>prescription_written</code>, <code>case_completed</code>, <code>case_cancelled</code>, <code>message_created</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Verify HMAC signature: <code>X-Webhook-Signature: sha256=&lt;digest&gt;</code></li>
    <li class="mb-0"><i class="bi bi-check-square text-success me-2"></i>Use <code>external_id</code> on every submission for safe retries (409 = already created, treat as success)</li>
</ul>
</div>

{{-- NAD checklist --}}
<div class="prog-nad">
<div class="mb-2"><span class="prog-indicator nad">NAD+</span></div>
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain <code>client_id</code>, <code>client_secret</code>, and your <strong>NAD Offering UUID(s)</strong> from admin (Admin → Partners → Offerings)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>POST /api/partner/auth/token</code> and cache the token (valid 1 year)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Call <code>GET /api/partner/questionnaires/{{ $nadQUuid }}</code> once to discover slugs — fetch live rather than hard-coding, as questions may be added over time</li>
    <li class="mb-2 fw-semibold"><i class="bi bi-x-square text-danger me-2"></i>Skip the prescription image upload step entirely — NAD cases do not require a prescription photo</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Submit <code>POST /api/partner/cases</code> with <code>offerings[].offering_id</code> (or <code>product_key: "nad"</code>) + flat <code>answers[]</code>. Include <strong>height</strong>, <strong>weight</strong>, <strong>bmi</strong> in <code>patient</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i><strong>Always submit the heart arrhythmia answer</strong> — even if the patient answers <code>"Yes"</code>. The case is still created and the clinician makes the final decision. Never suppress the submission</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Set <code>clinical_intake.dose</code> to <code>"LVL1 - 500MG"</code> or <code>"LVL2 - 1000MG"</code> (or the combo variant for NAD+/Glutathione). Omit <code>plan</code> or send <code>null</code>. Omit <code>onGlp</code> entirely</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Read <code>prescription_written</code> webhook: each <code>offerings[]</code> item's <code>product_key</code> + <code>meds_prescribed[0].dosing.term</code> identifies the VRIO SKU</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Include <code>patient.id_verified_status</code> at creation. If async, push later via <code>PATCH /api/partner/patients/{uuid}</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Store the returned <code>uuid</code> for future lookups and messaging</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register a webhook — portal fires: <code>case_waiting</code>, <code>case_assigned_to_clinician</code>, <code>case_support</code>, <code>case_approved</code>, <code>prescription_written</code>, <code>case_completed</code>, <code>case_cancelled</code>, <code>message_created</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Verify HMAC signature: <code>X-Webhook-Signature: sha256=&lt;digest&gt;</code></li>
    <li class="mb-0"><i class="bi bi-check-square text-success me-2"></i>Use <code>external_id</code> on every submission for safe retries (409 = already created, treat as success)</li>
</ul>
</div>

</div>
</div>

</div>{{-- /col-lg-9 --}}
</div>{{-- /row --}}

@endsection

@section('scripts')
<script>
function setProgram(p) {
    // Toggle main content sections.
    // GLP: '' removes the inline style and lets each element revert to its browser default
    //   (block for divs, list-item for the TOC <li>) — no CSS hides GLP by default.
    // NAD: must use 'block' explicitly to override the CSS .prog-nad { display:none } rule;
    //   '' would just remove the inline override and leave the CSS hiding in place.
    document.querySelectorAll('.prog-glp').forEach(function(el) {
        el.style.display = (p === 'glp') ? '' : 'none';
    });
    document.querySelectorAll('.prog-nad').forEach(function(el) {
        el.style.display = (p === 'nad') ? 'block' : 'none';
    });

    // Update main switcher buttons
    var btnGlp = document.getElementById('main-btn-glp');
    var btnNad = document.getElementById('main-btn-nad');
    if (btnGlp) { btnGlp.classList.toggle('inactive', p !== 'glp'); }
    if (btnNad) { btnNad.classList.toggle('inactive', p !== 'nad'); }

    // Update TOC buttons
    var tocGlp = document.getElementById('toc-btn-glp');
    var tocNad = document.getElementById('toc-btn-nad');
    if (tocGlp) { tocGlp.classList.toggle('inactive', p !== 'glp'); }
    if (tocNad) { tocNad.classList.toggle('inactive', p !== 'nad'); }

    // Save preference
    try { localStorage.setItem('api_prog', p); } catch (e) {}
}

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

// Restore last-selected program on load
document.addEventListener('DOMContentLoaded', function () {
    var saved = null;
    try { saved = localStorage.getItem('api_prog'); } catch (e) {}
    if (saved === 'nad') setProgram('nad');
    // else GLP is already shown by default (CSS)
});
</script>
@endsection
