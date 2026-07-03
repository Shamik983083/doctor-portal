@extends('layouts.admin')
@section('title', 'Webhook Integration Guide')
@section('page-title', 'Webhook Integration Guide')

@section('content')
@php $base = rtrim(config('app.url'), '/'); @endphp

<style>
pre { background:#1e1e2e; color:#cdd6f4; border-radius:8px; padding:1.1rem 1.3rem; font-size:.82rem; overflow-x:auto; position:relative }
.copy-btn { position:absolute; top:.5rem; right:.6rem; font-size:.7rem; padding:2px 8px; opacity:.7 }
.copy-btn:hover { opacity:1 }
.badge-method { font-size:.72rem; font-weight:700; padding:2px 7px; border-radius:4px }
.method-post { background:#e8f5e9; color:#2e7d32 }
.method-get  { background:#e3f2fd; color:#1565c0 }
.section-anchor { scroll-margin-top:80px }
.toc-link { font-size:.85rem }
.event-badge { font-family:monospace; font-size:.78rem; background:#f3f4f6; border:1px solid #d1d5db; border-radius:4px; padding:1px 6px; color:#1f2937 }
.step-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0 }

@media print {
    nav.sidebar, .topbar, .col-lg-3, button, .copy-btn { display:none !important; }
    .main-content { margin-left:0 !important; }
    .p-4 { padding:.5rem !important; }
    .col-lg-9 { width:100% !important; max-width:100% !important; flex:0 0 100% !important; }
    pre { background:#f5f5f5 !important; color:#111 !important; border:1px solid #ccc !important; page-break-inside:avoid; }
    .card { page-break-inside:avoid; border:1px solid #ccc !important; margin-bottom:1rem !important; }
    a { color:inherit !important; text-decoration:none !important; }
}
</style>

<div class="row g-4">

{{-- ── TOC ────────────────────────────────────────────────── --}}
<div class="col-lg-3 d-none d-lg-block">
<div class="card sticky-top" style="top:1rem">
<div class="card-header py-2"><strong class="small">Contents</strong></div>
<div class="card-body py-2 px-3">
<ol class="mb-0 ps-3" style="line-height:2.1">
    <li><a class="toc-link text-decoration-none" href="#overview">Overview</a></li>
    <li><a class="toc-link text-decoration-none" href="#register">Register a Webhook</a></li>
    <li><a class="toc-link text-decoration-none" href="#delivery">Delivery Format</a></li>
    <li><a class="toc-link text-decoration-none" href="#security">Signature Verification</a></li>
    <li><a class="toc-link text-decoration-none" href="#retry">Retry Behaviour</a></li>
    <li><a class="toc-link text-decoration-none" href="#events">All Events</a>
        <ol class="ps-3 mb-0" style="line-height:2">
            <li><a class="toc-link text-decoration-none" href="#ev-case-created">case_created</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-waiting">case_waiting</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-assigned">case_assigned_to_clinician</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-support">case_support</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-approved">case_approved</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-prescription-written">prescription_written</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-processing">case_processing</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-completed">case_completed</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-case-cancelled">case_cancelled</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-note-added">clinical_note_added</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-message-created">message_created</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-patient-message">patient_message_received</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-order-status">order_status_changed</a></li>
            <li><a class="toc-link text-decoration-none" href="#ev-tracking">tracking_number_changed</a></li>
        </ol>
    </li>
    <li><a class="toc-link text-decoration-none" href="#checklist">Checklist</a></li>
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

{{-- OVERVIEW --}}
<div id="overview" class="alert alert-primary border-0 mb-4 section-anchor">
    <strong><i class="bi bi-broadcast me-2"></i>Overview</strong><br>
    The Doctor Portal pushes real-time events to your registered HTTPS endpoint whenever something changes on a case, patient, or order. You register one or more webhook URLs via the API, and we POST a signed JSON payload to each URL within seconds of the event. No polling required.
    <ul class="mb-0 mt-2 small">
        <li>All requests are <strong>POST</strong> with <code>Content-Type: application/json</code></li>
        <li>Every delivery is signed with <strong>HMAC-SHA256</strong> — always verify the signature before processing</li>
        <li>Failed deliveries are retried up to <strong>5 times</strong> with exponential backoff</li>
        <li>Respond with any <strong>2xx status</strong> to acknowledge; anything else triggers a retry</li>
    </ul>
</div>

{{-- 1. REGISTER --}}
<div id="register" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">1</span>Register a Webhook</div>
<div class="card-body">
<p class="mb-2">Create a webhook endpoint using your partner Bearer token. You can subscribe to all events or filter by <code>event_type</code>.</p>

<div class="d-flex align-items-center gap-2 mb-2">
    <span class="badge-method method-post">POST</span>
    <code>{{ $base }}/api/partner/webhooks</code>
</div>
<pre id="code-register">POST {{ $base }}/api/partner/webhooks
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "url":        "https://your-site.com/webhooks/medaxis",
  "secret":     "your-random-signing-secret",
  "event_type": null,          // null = receive ALL events; or pass a single event name string
  "status":     "active"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-register')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 201</strong></p>
<pre id="code-register-resp">{
  "id":         "webhook-uuid",
  "url":        "https://your-site.com/webhooks/medaxis",
  "event_type": null,
  "status":     "active",
  "created_at": "2026-07-03T10:00:00.000000Z"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-register-resp')">Copy</button>

<p class="mt-3 mb-1 small text-muted">To update or delete: <code>PUT /api/partner/webhooks/{id}</code> &nbsp;|&nbsp; <code>DELETE /api/partner/webhooks/{id}</code></p>
</div>
</div>

{{-- 2. DELIVERY FORMAT --}}
<div id="delivery" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">2</span>Delivery Format</div>
<div class="card-body">
<p class="mb-3">Every webhook delivery is an <strong>HTTP POST</strong> to your registered URL with the following headers and a JSON body.</p>

<h6 class="fw-semibold mb-2">Request Headers</h6>
<table class="table table-sm table-bordered mb-3" style="font-size:.85rem">
<thead class="table-light"><tr><th>Header</th><th>Example</th><th>Notes</th></tr></thead>
<tbody>
<tr><td><code>Content-Type</code></td><td><code>application/json</code></td><td>Always JSON</td></tr>
<tr><td><code>X-Event-Type</code></td><td><code>prescription_written</code></td><td>The event name — use this to route to your handler</td></tr>
<tr><td><code>X-Webhook-Signature</code></td><td><code>sha256=abc123…</code></td><td>HMAC-SHA256 of the raw request body — verify before processing</td></tr>
</tbody>
</table>

<h6 class="fw-semibold mb-2">Body Structure — all events share these top-level fields</h6>
<pre id="code-body-structure">{
  "case_id":   "uuid-of-the-case",      // present on case/prescription/note/message events
  "patient_id":"uuid-of-the-patient",   // present on most events
  "timestamp": 1751539200,              // Unix timestamp (seconds)
  // … event-specific fields (see event reference below)
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-body-structure')">Copy</button>
</div>
</div>

{{-- 3. SIGNATURE --}}
<div id="security" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-warning text-dark me-2">3</span>Signature Verification <span class="badge bg-danger ms-2" style="font-size:.65rem">Required</span></div>
<div class="card-body">
<p class="mb-3">We sign every payload with the <code>secret</code> you provided when registering the webhook. Compute the HMAC-SHA256 of the <strong>raw request body</strong> (before any JSON parsing) and compare it to the <code>X-Webhook-Signature</code> header.</p>

<ul class="nav nav-tabs mb-3" id="langTab" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-php">PHP</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-node">Node.js</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-python">Python</button></li>
</ul>
<div class="tab-content">

<div class="tab-pane fade show active" id="tab-php">
<pre id="code-php">$rawBody   = file_get_contents('php://input');
$secret    = 'your-webhook-secret';
$computed  = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
$received  = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (!hash_equals($computed, $received)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);
$type  = $_SERVER['HTTP_X_EVENT_TYPE'] ?? '';</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-php')">Copy</button>
</div>

<div class="tab-pane fade" id="tab-node">
<pre id="code-node">const crypto = require('crypto');

function verifyWebhook(req, secret) {
    const rawBody  = req.rawBody;   // must be raw Buffer, not parsed
    const computed = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(rawBody)
        .digest('hex');
    const received = req.headers['x-webhook-signature'] || '';
    return crypto.timingSafeEqual(
        Buffer.from(computed),
        Buffer.from(received)
    );
}

app.post('/webhooks/medaxis', express.raw({ type: 'application/json' }), (req, res) => {
    if (!verifyWebhook(req, process.env.WEBHOOK_SECRET)) {
        return res.status(401).send('Invalid signature');
    }
    const event = JSON.parse(req.body);
    const type  = req.headers['x-event-type'];
    // handle event …
    res.sendStatus(200);
});</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-node')">Copy</button>
</div>

<div class="tab-pane fade" id="tab-python">
<pre id="code-python">import hmac, hashlib, json
from flask import Flask, request, abort

app    = Flask(__name__)
SECRET = b'your-webhook-secret'

@app.route('/webhooks/medaxis', methods=['POST'])
def webhook():
    raw_body  = request.get_data()
    computed  = 'sha256=' + hmac.new(SECRET, raw_body, hashlib.sha256).hexdigest()
    received  = request.headers.get('X-Webhook-Signature', '')

    if not hmac.compare_digest(computed, received):
        abort(401)

    event = json.loads(raw_body)
    etype = request.headers.get('X-Event-Type')
    # handle event …
    return '', 200</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-python')">Copy</button>
</div>
</div>

<div class="alert alert-warning mt-3 mb-0 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Always use a <strong>constant-time comparison</strong> (<code>hash_equals</code> / <code>timingSafeEqual</code> / <code>hmac.compare_digest</code>) — never <code>===</code> — to prevent timing attacks.
</div>
</div>
</div>

{{-- 4. RETRY --}}
<div id="retry" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">4</span>Retry Behaviour</div>
<div class="card-body">
<table class="table table-sm table-bordered mb-3" style="font-size:.85rem">
<thead class="table-light"><tr><th>Attempt</th><th>Delay after failure</th></tr></thead>
<tbody>
<tr><td>1st try</td><td>Immediate</td></tr>
<tr><td>2nd try</td><td>30 s</td></tr>
<tr><td>3rd try</td><td>60 s</td></tr>
<tr><td>4th try</td><td>120 s</td></tr>
<tr><td>5th try</td><td>240 s</td></tr>
<tr class="table-danger"><td>After 5 failures</td><td>Marked <strong>failed</strong> — no further retries</td></tr>
</tbody>
</table>
<p class="mb-0 small text-muted">A delivery is considered <strong>successful</strong> when your endpoint returns any <code>2xx</code> HTTP status within 10 seconds. A timeout, network error, or non-2xx response triggers a retry. Failed deliveries are visible in the Admin → Webhook Log and can be manually resent.</p>
</div>
</div>

{{-- 5. EVENTS --}}
<div id="events" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-primary text-white me-2">5</span>All Events</div>
<div class="card-body pb-0">
<p class="mb-3 small text-muted">Use the <code>X-Event-Type</code> header to route each delivery to the correct handler. All timestamps are Unix seconds (UTC).</p>
</div>
</div>

{{-- case_created --}}
<div id="ev-case-created" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_created</span>
    <span class="text-muted small">Fired when a new case is created from a form submission or API call</span>
</div>
<div class="card-body">
<pre id="code-ev-created">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "created",
  "timestamp":  1751539200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-created')">Copy</button>
</div>
</div>

{{-- case_waiting --}}
<div id="ev-case-waiting" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_waiting</span>
    <span class="text-muted small">Fired immediately after creation — case is now in the clinician queue</span>
</div>
<div class="card-body">
<pre id="code-ev-waiting">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "waiting",
  "timestamp":  1751539201
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-waiting')">Copy</button>
</div>
</div>

{{-- case_assigned_to_clinician --}}
<div id="ev-case-assigned" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_assigned_to_clinician</span>
    <span class="text-muted small">Fired when a clinician is assigned (auto-assignment or manual)</span>
</div>
<div class="card-body">
<pre id="code-ev-assigned">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "assigned",
  "timestamp":  1751539260
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-assigned')">Copy</button>
<p class="mt-2 mb-0 small text-muted">Retrieve clinician details via <code>GET /api/partner/cases/{case_id}</code> if needed.</p>
</div>
</div>

{{-- case_support --}}
<div id="ev-case-support" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_support</span>
    <span class="text-muted small">Clinician has a question — action required from your side</span>
</div>
<div class="card-body">
<pre id="code-ev-support">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "support",
  "timestamp":  1751539800
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-support')">Copy</button>
<p class="mt-2 mb-0 small text-muted">Fetch <code>GET /api/partner/cases/{case_id}</code> to read the <code>support_note</code> the clinician left. Respond via the Partner Portal or <code>POST /api/partner/cases/{case_id}/support { "note": "..." }</code>.</p>
</div>
</div>

{{-- case_approved --}}
<div id="ev-case-approved" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_approved</span>
    <span class="text-muted small">Clinician has approved the case — prescription may or may not be attached</span>
</div>
<div class="card-body">
<pre id="code-ev-approved">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "approved",
  "timestamp":  1751540000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-approved')">Copy</button>
<div class="alert alert-info mt-2 mb-0 small">
    <i class="bi bi-info-circle me-1"></i>
    When the clinician approves <em>via the Prescribe form</em>, you also receive a separate <strong><code>prescription_written</code></strong> event (see below) which includes the full medication list, doctor name, and NPI. Listen for that event for prescription details.
</div>
</div>
</div>

{{-- prescription_written --}}
<div id="ev-prescription-written" class="card mb-3 section-anchor border-success">
<div class="card-header py-2 d-flex align-items-center gap-2 bg-success bg-opacity-10">
    <span class="event-badge" style="background:#d1fae5;border-color:#6ee7b7;color:#065f46">prescription_written</span>
    <span class="text-muted small">Fired when a clinician submits a prescription — includes full medication details</span>
</div>
<div class="card-body">
<pre id="code-ev-rx">{
  "case_id":         "9d2f1c3e-...",
  "external_id":     "order-wl-20240701-001",   // your reference ID
  "patient_id":      "a1b2c3d4-...",
  "clinician_name":  "Dr. Sarah Johnson, MD",
  "clinician_npi":   "1234567890",
  "diagnoses":       "Obesity (E66.9), Hypertension (I10)",
  "meds_prescribed": [
    {
      "name":             "Semaglutide",
      "compound_formula": "Semaglutide 0.5mg/mL in bacteriostatic water",
      "refills":          "3",
      "quantity":         "1",
      "days_supply":      "30",
      "dispense_unit":    "vial"
    }
  ],
  "timestamp":       1751540001
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-rx')">Copy</button>
<p class="mt-2 mb-0 small text-muted">This event fires alongside <code>case_approved</code> whenever a prescription form is submitted. If no prescription form was used (approval-only), only <code>case_approved</code> fires.</p>
</div>
</div>

{{-- case_processing --}}
<div id="ev-case-processing" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_processing</span>
    <span class="text-muted small">Clinician has sent the prescription to pharmacy</span>
</div>
<div class="card-body">
<pre id="code-ev-processing">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "processing",
  "timestamp":  1751540200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-processing')">Copy</button>
</div>
</div>

{{-- case_completed --}}
<div id="ev-case-completed" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_completed</span>
    <span class="text-muted small">Case is fully closed</span>
</div>
<div class="card-body">
<pre id="code-ev-completed">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "completed",
  "timestamp":  1751599200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-completed')">Copy</button>
</div>
</div>

{{-- case_cancelled --}}
<div id="ev-case-cancelled" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_cancelled</span>
    <span class="text-muted small">Case was cancelled by a clinician, admin, or partner</span>
</div>
<div class="card-body">
<pre id="code-ev-cancelled">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "cancelled",
  "timestamp":  1751540500
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-cancelled')">Copy</button>
<p class="mt-2 mb-0 small text-muted">Fetch <code>GET /api/partner/cases/{case_id}</code> to read the <code>cancellation_reason</code>.</p>
</div>
</div>

{{-- clinical_note_added --}}
<div id="ev-note-added" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">clinical_note_added</span>
    <span class="text-muted small">Clinician added a clinical note to the case</span>
</div>
<div class="card-body">
<pre id="code-ev-note">{
  "case_id":   "9d2f1c3e-...",
  "timestamp": 1751541000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-note')">Copy</button>
<p class="mt-2 mb-0 small text-muted">Note content is not included in the payload (may contain PHI). Retrieve via <code>GET /api/partner/cases/{case_id}</code>.</p>
</div>
</div>

{{-- message_created --}}
<div id="ev-message-created" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">message_created</span>
    <span class="text-muted small">Clinician sent a message to the patient/partner on this case</span>
</div>
<div class="card-body">
<pre id="code-ev-msg">{
  "case_id":   "9d2f1c3e-...",
  "sender":    "clinician",
  "timestamp": 1751541300
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-msg')">Copy</button>
<p class="mt-2 mb-0 small text-muted">Retrieve the message body via <code>GET /api/partner/cases/{case_id}/messages</code>.</p>
</div>
</div>

{{-- patient_message_received --}}
<div id="ev-patient-message" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">patient_message_received</span>
    <span class="text-muted small">Your system sent a message to the portal via the API</span>
</div>
<div class="card-body">
<pre id="code-ev-patient-msg">{
  "case_id":    "9d2f1c3e-...",
  "message_id": "msg-uuid-...",
  "timestamp":  1751541400
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-patient-msg')">Copy</button>
</div>
</div>

{{-- order_status_changed --}}
<div id="ev-order-status" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">order_status_changed</span>
    <span class="text-muted small">A fulfillment order's status was updated</span>
</div>
<div class="card-body">
<pre id="code-ev-order">{
  "order_id":  "ord-uuid-...",
  "case_id":   "9d2f1c3e-...",
  "status":    "shipped",
  "timestamp": 1751599000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-order')">Copy</button>
</div>
</div>

{{-- tracking_number_changed --}}
<div id="ev-tracking" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">tracking_number_changed</span>
    <span class="text-muted small">A tracking number was assigned to a fulfillment order</span>
</div>
<div class="card-body">
<pre id="code-ev-tracking">{
  "order_id":        "ord-uuid-...",
  "tracking_number": "1Z999AA10123456784",
  "timestamp":       1751599100
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-tracking')">Copy</button>
</div>
</div>

{{-- 6. CHECKLIST --}}
<div id="checklist" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-secondary text-white me-2">6</span>Integration Checklist</div>
<div class="card-body">
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain Bearer token via <code>POST /api/partner/auth/token</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register endpoint: <code>POST /api/partner/webhooks</code> with your URL and a strong random <code>secret</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Endpoint must be <strong>HTTPS</strong> and publicly reachable; respond with <code>200</code> within 10 seconds</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Verify <code>X-Webhook-Signature</code> on <strong>every</strong> incoming request using a constant-time comparison</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Route by <code>X-Event-Type</code> header — do not rely solely on payload fields to identify the event</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>prescription_written</code></strong> to get clinician name, NPI, and medication list</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>case_support</code></strong> — fetch the <code>support_note</code> and notify your team; respond via API or portal</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Make your handler <strong>idempotent</strong> — the same event may be delivered more than once on retry</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Return <code>200</code> immediately, then process asynchronously — do not do heavy work before responding</li>
    <li class="mb-0"><i class="bi bi-check-square text-success me-2"></i>Monitor failed deliveries at <code>GET /api/partner/webhooks/deliveries</code> or ask the portal admin to resend</li>
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
