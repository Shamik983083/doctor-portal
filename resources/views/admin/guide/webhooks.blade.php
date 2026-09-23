@extends('layouts.admin')
@section('title', 'Webhook Integration Guide')
@section('page-title', 'Webhook Integration Guide')

@section('content')
@php $base = rtrim(config('app.url'), '/'); @endphp

@if($partner ?? null)
<div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-building me-1"></i>
    <span>
        Showing examples for <strong>{{ $partner->name }}</strong>.
        @php $activeHooks = $partner->webhooks()->where('status','active')->count(); @endphp
        @if($activeHooks)
            {{ $activeHooks }} active webhook{{ $activeHooks !== 1 ? 's' : '' }} registered — example URL pre-filled below.
        @else
            No active webhooks registered yet — placeholder URL shown.
        @endif
    </span>
    <a href="{{ route('admin.guide.webhooks') }}" class="btn btn-sm btn-outline-secondary ms-auto">Clear context</a>
</div>
@endif

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

/* ── Sidebar nav links ─────────────────────────── */
.wh-nav-link {
    display:block;
    font-size:.8rem;
    font-weight:500;
    color:#374151;
    text-decoration:none;
    padding:5px 14px 5px 16px;
    border-left:2px solid transparent;
    transition:background .12s, color .12s, border-color .12s;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.wh-nav-link.wh-event {
    font-family:monospace;
    font-size:.75rem;
    font-weight:400;
    color:#6b7280;
    padding-left:20px;
}
.wh-nav-link.wh-event-rx {
    color:#065f46;
    font-weight:600;
}
.wh-nav-link:hover {
    background:#f9fafb;
    color:#111827;
    border-left-color:#d1d5db;
}
.wh-nav-link.wh-active {
    background:#eff6ff;
    color:#1d4ed8;
    border-left-color:#1d4ed8;
    font-weight:600;
}
.wh-nav-link.wh-event.wh-active {
    color:#1d4ed8;
    font-weight:600;
}
.step-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0 }
.endpoint-row { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:.45rem .75rem; font-size:.8rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:.75rem }
.endpoint-row .method-pill { font-size:.68rem; font-weight:700; padding:1px 7px; border-radius:3px; flex-shrink:0 }
.endpoint-row code { font-size:.8rem; color:#0f172a; word-break:break-all }

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
<div id="wh-toc" style="position:sticky;top:1rem;max-height:calc(100vh - 2rem);display:flex;flex-direction:column;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden">

  {{-- header --}}
  <div style="padding:12px 16px 10px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
    <span style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280">Contents</span>
    <button onclick="window.print()" title="Print" style="background:none;border:none;padding:2px 6px;border-radius:5px;cursor:pointer;color:#6b7280;line-height:1" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
      <i class="bi bi-printer" style="font-size:.85rem"></i>
    </button>
  </div>

  {{-- scrollable links --}}
  <div style="overflow-y:auto;flex:1;padding:8px 0 12px">

    <div style="padding:4px 14px 2px">
      <span style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af">Setup</span>
    </div>
    <a class="wh-nav-link" href="#overview">Overview</a>
    <a class="wh-nav-link" href="#register">Register a Webhook</a>
    <a class="wh-nav-link" href="#delivery">Delivery Format</a>
    <a class="wh-nav-link" href="#security">Signature Verification</a>
    <a class="wh-nav-link" href="#retry">Retry Behaviour</a>

    <div style="padding:10px 14px 2px;margin-top:2px;border-top:1px solid #f3f4f6">
      <span style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af">Case Events</span>
    </div>
    <a class="wh-nav-link wh-event" href="#ev-case-created">case_created</a>
    <a class="wh-nav-link wh-event" href="#ev-case-waiting">case_waiting</a>
    <a class="wh-nav-link wh-event" href="#ev-case-assigned">case_assigned_to_clinician</a>
    <a class="wh-nav-link wh-event" href="#ev-case-support">case_support</a>
    <a class="wh-nav-link wh-event" href="#ev-escalation-started">escalation_started</a>
    <a class="wh-nav-link wh-event" href="#ev-escalation-message">escalation_message_sent</a>
    <a class="wh-nav-link wh-event" href="#ev-case-returned">case_returned_to_clinician</a>
    <a class="wh-nav-link wh-event" href="#ev-support-thread-closed">support_thread_closed</a>
    <a class="wh-nav-link wh-event" href="#ev-case-approved">case_approved</a>
    <a class="wh-nav-link wh-event wh-event-rx" href="#ev-prescription-written">prescription_written</a>
    <a class="wh-nav-link wh-event" href="#ev-case-processing">case_processing</a>
    <a class="wh-nav-link wh-event" href="#ev-case-completed">case_completed</a>
    <a class="wh-nav-link wh-event" href="#ev-case-cancelled">case_cancelled</a>

    <div style="padding:10px 14px 2px;margin-top:2px;border-top:1px solid #f3f4f6">
      <span style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af">Clinical &amp; Messaging</span>
    </div>
    <a class="wh-nav-link wh-event" href="#ev-note-added">clinical_note_added</a>
    <a class="wh-nav-link wh-event" href="#ev-message-created">message_created</a>
    <a class="wh-nav-link wh-event" href="#ev-patient-message">patient_message_received</a>

    <div style="padding:10px 14px 2px;margin-top:2px;border-top:1px solid #f3f4f6">
      <span style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af">Orders &amp; Patients</span>
    </div>
    <a class="wh-nav-link wh-event" href="#ev-order-status">order_status_changed</a>
    <a class="wh-nav-link wh-event" href="#ev-tracking">tracking_number_changed</a>
    <a class="wh-nav-link wh-event" href="#ev-patient-modified">patient_modified</a>
    <a class="wh-nav-link wh-event" href="#ev-patient-created">patient_created</a>
    <a class="wh-nav-link wh-event" href="#ev-patient-deleted">patient_deleted</a>

    <div style="padding:10px 14px 2px;margin-top:2px;border-top:1px solid #f3f4f6">
      <span style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9ca3af">Reference</span>
    </div>
    <a class="wh-nav-link" href="#checklist">Integration Checklist</a>

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
  "url":        "{{ $webhookUrl ?? 'https://your-site.com/webhooks/medaxis' }}",
  "event_type": null,          // null = receive ALL events; or pass a single event name string
  "status":     "active"
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-register')">Copy</button>

<p class="mt-3 mb-1"><strong>Success 201</strong></p>
<pre id="code-register-resp">{
  "id":         "webhook-uuid",
  "url":        "{{ $webhookUrl ?? 'https://your-site.com/webhooks/medaxis' }}",
  "event_type": null,
  "secret":     "AbCdEfGhIjKlMnOpQrStUvWxYz123456",  // auto-generated — save this, shown only once
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
  "event":              "case_created",          // always present — mirrors the X-Event-Type header; injected before HMAC signing
  "case_id":            "uuid-of-the-case",      // present on case/prescription/note/message events
  "patient_id":         "uuid-of-the-patient",   // present on most events
  "sub_storefront_id":  "e3b0c442-98fc-1c14-9afb-f4c8996fb924", // UUID of the sub-storefront, or null for partner-level cases
  "timestamp":          1751539200,              // Unix timestamp (seconds)
  // … event-specific fields (see event reference below)
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-body-structure')">Copy</button>
</div>
</div>

{{-- 3. SIGNATURE --}}
<div id="security" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-warning text-dark me-2">3</span>Signature Verification <span class="badge bg-danger ms-2" style="font-size:.65rem">Required</span></div>
<div class="card-body">
<p class="mb-3">We sign every payload with the per-webhook <code>secret</code> returned in the registration response (auto-generated by the server — partners do not supply it). Compute the HMAC-SHA256 of the <strong>raw request body</strong> (before any JSON parsing) and compare it to the <code>X-Webhook-Signature</code> header.</p>

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
<div class="alert alert-info border-0 mb-3 small py-2">
    <i class="bi bi-diagram-3 me-1"></i>
    <strong>Sub-storefronts:</strong> All case-related events include a <code>sub_storefront_id</code> field (UUID string, or <code>null</code> for partner-level cases).
    Use it to route the event to the correct tenant in your system.
</div>

<div class="alert alert-primary border-0 mb-3 small">
    <strong><i class="bi bi-arrow-left-right me-1"></i>Push vs Pull — understand the two directions:</strong>
    <ul class="mb-0 mt-2">
        <li><strong>MEDAXIS → Your server (push):</strong> We POST webhook events to your registered URL (<code>{{ $webhookUrl ?? 'https://your-site.com/webhooks/medaxis' }}</code>). The payload contains everything you need — <strong>no polling required</strong>. For prescription events, the full medication list, diagnoses, and offerings are in the payload itself.</li>
        <li class="mt-1"><strong>Your server → MEDAXIS (pull):</strong> The <code>GET {{ $base }}/api/partner/…</code> endpoints shown below are <strong>optional fallbacks</strong> — use them only if you need additional context not in the payload, or to re-fetch data after a missed delivery.</li>
    </ul>
</div>
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
  "visit_type": "asynchronous",
  "timestamp":  1751539200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-created')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> The payload confirms the new <code>case_id</code> and <code>patient_id</code>. Use these to link the case in your system. GET is optional — only needed if you want full patient details or the offering list at creation time.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch full patient record and offering details</span>
</div>
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
  "visit_type": "asynchronous",
  "timestamp":  1751539201
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-waiting')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> This event is informational — the payload confirms the case is now in the clinician queue. No action is required. GET is optional.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: confirm queue position or fetch full case details</span>
</div>
</div>
</div>

{{-- case_assigned_to_clinician --}}
<div id="ev-case-assigned" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_assigned_to_clinician</span>
    <span class="text-muted small">Fired when a clinician is assigned (auto-assignment, pool claim, or admin reassignment)</span>
</div>
<div class="card-body">
<p class="small text-muted mb-2">This event fires on every assignment — including when an admin reassigns the case to a different clinician. Always process the latest event to get the current clinician's booking link.</p>
<pre id="code-ev-assigned">{
  "case_id":      "9d2f1c3e-...",
  "patient_id":   "a1b2c3d4-...",
  "status":       "assigned",
  "visit_type":   "asynchronous",
  "booking_link": null,
  "timestamp":    1751539260
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-assigned')">Copy</button>

<h6 class="fw-semibold mt-3 mb-2" style="font-size:.82rem">Fields</h6>
<div class="table-responsive mb-3">
<table class="table table-sm table-bordered small mb-0">
<thead class="table-light"><tr><th>Field</th><th>Type</th><th>Notes</th></tr></thead>
<tbody>
<tr><td><code>case_id</code></td><td>string (UUID)</td><td>The case this event is for</td></tr>
<tr><td><code>patient_id</code></td><td>string (UUID)</td><td>The patient on the case</td></tr>
<tr><td><code>status</code></td><td>string</td><td>Always <code>"assigned"</code></td></tr>
<tr><td><code>visit_type</code></td><td>string / null</td><td>Partner-supplied visit type string (e.g. <code>"synchronous"</code>)</td></tr>
<tr>
    <td><code>booking_link</code></td>
    <td>string / null</td>
    <td>
        <strong>Non-null only for synchronous video cases.</strong>
        The clinician's booking URL (e.g. Calendly). Direct your patient to this link to schedule the video appointment before the clinician reviews the case.
        <code>null</code> for asynchronous cases — no action required.
    </td>
</tr>
<tr><td><code>timestamp</code></td><td>integer</td><td>Unix timestamp (seconds)</td></tr>
</tbody>
</table>
</div>

<div class="alert alert-info mt-2 mb-2 small">
    <i class="bi bi-camera-video me-1"></i>
    <strong>Synchronous (video) case flow:</strong>
    When <code>booking_link</code> is non-null, notify your patient immediately so they can book the video appointment.
    The clinician will review and prescribe after the visit is completed.
    If the booking link ever changes (e.g. admin reassigns to a different clinician), you will receive a new <code>case_assigned_to_clinician</code> event with the updated link.
</div>

<div class="alert alert-warning mt-2 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Clinician name and NPI are NOT in this payload.</strong> Call GET to retrieve the clinician's name, credentials, and the live booking link (useful if it changes after assignment).
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— returns <code>clinician.scheduling_link</code> (always the live value), plus name, NPI, and credentials</span>
</div>
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
  "case_id":          "9d2f1c3e-...",
  "patient_id":       "a1b2c3d4-...",
  "status":           "support",
  "visit_type":       "asynchronous",
  "escalation_target": "support",       // who the escalation is directed at (see below)
  "support_note":     "Need lab confirmation before prescribing",   // omitted when blank
  "timestamp":        1751539800
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-support')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> Both <code>support_note</code> and <code>escalation_target</code> are in the payload above — no pull needed to surface the escalation to your team. GET is optional (for full case context only).
</div>
<div class="alert alert-info mt-0 mb-2 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong><code>escalation_target</code></strong> tells you who the clinician needs a response from:
    <ul class="mb-0 mt-1">
        <li><code>support</code> — general support escalation; the portal team will handle it</li>
        <li><code>doctor_admin</code> — needs a physician admin review (complex clinical case)</li>
        <li><code>client_response</code> — waiting for the patient or partner to supply additional information</li>
    </ul>
    For all three values, your portal should surface the case status to the patient and/or your ops team. For <code>client_response</code>, prompt the patient to check their messaging thread — the clinician has left a note there.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— <code>support_note</code> is also in the payload above; call this for full case context</span>
</div>
<div class="endpoint-row mt-1">
    <span class="method-pill method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/messages</code>
    <span class="text-muted" style="font-size:.75rem">— send a message directly to the clinician (escalation chat, when <code>escalation_target=support</code>)</span>
</div>
<div class="endpoint-row mt-1">
    <span class="method-pill method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/return-to-clinician</code>
    <span class="text-muted" style="font-size:.75rem">— close the escalation and return the case to the clinician</span>
</div>
</div>
</div>

{{-- escalation_started --}}
<div id="ev-escalation-started" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge" style="background:#fef3c7;border-color:#fde68a;color:#92400e;">escalation_started</span>
    <span class="text-muted small">Clinician forwarded a patient message to your support team</span>
</div>
<div class="card-body">
<p class="small text-muted mb-2">Fired when a clinician clicks <strong>Forward to Support</strong> on a patient message. Unlike <code>case_support</code>, this does <em>not</em> change the case status — the case stays in its current workflow (assigned/processing) while a parallel support thread opens. Notify your support team and use <code>GET /messages?channel=escalation</code> or the partner portal to view and reply.</p>
<pre id="code-ev-esc-started">{
  "event":       "escalation_started",
  "case_id":     "9d2f1c3e-...",
  "patient_id":  "a1b2c3d4-...",
  "case_status": "assigned",
  "timestamp":   1724745600
}</pre>
<div class="d-flex gap-2 mt-2">
    <button class="btn btn-sm btn-outline-secondary" onclick="copyCode('code-ev-esc-started')">Copy</button>
</div>
</div>
</div>

{{-- escalation_message_sent --}}
<div id="ev-escalation-message" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge" style="background:#ede9fe;border-color:#c4b5fd;color:#5b21b6;">escalation_message_sent</span>
    <span class="text-muted small">Clinician replied in the escalation thread — poll for the message body</span>
</div>
<div class="card-body">
<p class="small text-muted mb-2">Fired when the assigned clinician sends a message into an active support escalation thread. Subscribe to this to avoid polling; then call <code>GET /messages?channel=escalation</code> to fetch the body (PHI exclusion — body is not in the webhook payload).</p>
<pre id="code-ev-esc-msg">{
  "event":      "escalation_message_sent",
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "sender":     "clinician",
  "timestamp":  1751539800
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-esc-msg')">Copy</button>
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-shield-lock me-1"></i>
    <strong>PHI exclusion.</strong> The message body is not included in this webhook. After receiving this event, call <code>GET /api/partner/cases/{case_id}/messages?channel=escalation</code> to fetch the full thread.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/messages?channel=escalation</code>
    <span class="text-muted" style="font-size:.75rem">— fetch the escalation thread after receiving this event</span>
</div>
<div class="endpoint-row mt-1">
    <span class="method-pill method-post">POST</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/messages</code>
    <span class="text-muted" style="font-size:.75rem">— reply to the clinician (body: <code>{ "body": "..." }</code>)</span>
</div>
</div>
</div>

{{-- support_thread_closed --}}
<div id="ev-support-thread-closed" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge" style="background:#dcfce7;border-color:#86efac;color:#166534;">support_thread_closed</span>
    <span class="text-muted small">Parallel support thread closed by partner (case status unchanged)</span>
</div>
<div class="card-body">
<p class="small text-muted mb-2">Fired when the partner closes a <em>parallel</em> support thread (one opened via <strong>Forward to Support</strong>, where the case was never moved to <code>status=support</code>). The case continues in its current status. Both compose forms lock immediately. This is distinct from <code>case_returned_to_clinician</code>, which fires when closing a full-escalation thread.</p>
<pre id="code-ev-thread-closed">{
  "event":       "support_thread_closed",
  "case_id":     "9d2f1c3e-...",
  "patient_id":  "a1b2c3d4-...",
  "case_status": "assigned",
  "timestamp":   1724745600
}</pre>
<div class="d-flex gap-2 mt-2">
    <button class="btn btn-sm btn-outline-secondary" onclick="copyCode('code-ev-thread-closed')">Copy</button>
</div>
</div>
</div>

{{-- case_returned_to_clinician --}}
<div id="ev-case-returned" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge" style="background:#dcfce7;border-color:#86efac;color:#166534;">case_returned_to_clinician</span>
    <span class="text-muted small">Escalation closed — case is back with the clinician</span>
</div>
<div class="card-body">
<p class="small text-muted mb-2">Fired when your team closes the support escalation (via the partner portal or API). Use this to lock the escalation thread on your side. You will also receive a <code>case_assigned_to_clinician</code> event on the same transition — subscribe to <code>case_returned_to_clinician</code> specifically when you need to distinguish a return-from-support from an initial assignment.</p>
<pre id="code-ev-returned">{
  "event":      "case_returned_to_clinician",
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "assigned",
  "visit_type": "asynchronous",
  "timestamp":  1751540200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-returned')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-check-circle me-1"></i>
    <strong>No pull needed.</strong> Use this event to mark the escalation resolved in your system and stop polling the escalation thread.
</div>
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
  "visit_type": "asynchronous",
  "timestamp":  1751540000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-approved')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>No pull needed for prescriptions.</strong> When the clinician approves via the Prescribe form, a <strong><code>prescription_written</code></strong> event fires immediately after — the full medication list, diagnoses, NPI, <code>product_key</code>, and <code>month_frequency</code> are all inside each medication object in that payload. You do <strong>not</strong> need to call our API to get prescription data.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch full case record if you need additional context beyond the payload</span>
</div>
</div>
</div>

{{-- prescription_written --}}
<div id="ev-prescription-written" class="card mb-3 section-anchor border-success">
<div class="card-header py-2 d-flex align-items-center gap-2 bg-success bg-opacity-10">
    <span class="event-badge" style="background:#d1fae5;border-color:#6ee7b7;color:#065f46">prescription_written</span>
    <span class="text-muted small">Fired when a clinician confirms a prescription — includes structured diagnoses, full medication details with <code>offering_id</code>, <code>product_key</code>, <code>month_frequency</code>, per-month SIG instructions inside <code>dosing.sigs[]</code>, compound variant details inside <code>dosing.formulas[]</code>, and per-level dispense quantities inside <code>dosing.quantities[]</code></span>
</div>
<div class="card-body">
<p class="small text-muted mb-2"><strong>As of Phase 2</strong> — <code>diagnoses</code> is a structured array of ICD-10-CM codes and each medication includes both a flat <code>sig</code> (offering default, resolved per partner) and per-month <code>dosing.sigs[]</code> overrides set by the clinician at prescription time. <strong>As of Phase 3</strong> — <code>dosing.formulas[]</code> contains the compound variant detail string for each dose level (e.g. the exact concentration/volume specification for compounding or dispensing), parallel to <code>months[]</code> and <code>sigs[]</code>. <strong>As of Phase 4</strong> — <code>dosing.quantities[]</code> contains the dispense quantity for each dose level, set per-level on the offering and auto-filled by the clinician at prescription time.</p>
<pre id="code-ev-rx">{
  "case_id":         "9d2f1c3e-...",
  "external_id":     "order-wl-20240701-001",   // your reference ID
  "patient_id":      "a1b2c3d4-...",
  "clinician_name":           "Dr. Sarah Johnson, MD",
  "clinician_npi":            "1234567890",
  "clinician_license_state":  "CA",             // patient's state at time of prescription
  "clinician_license_number": "G12345",         // clinician's license number for that state
  "clinician_phone":          "+1 555-123-4567",
  "clinician_email":          "dr.johnson@clinic.com",

  // Structured ICD-10-CM codes (Phase 2+). Always an array.
  // Falls back to a plain string on legacy prescriptions written before Phase 2.
  "diagnoses": [
    { "code": "E66.01", "description": "Morbid (severe) obesity due to excess calories" },
    { "code": "Z68.41", "description": "Body mass index (BMI) 40.0-44.9, adult" }
  ],

  "meds_prescribed": [
    {
      "name":                "Semaglutide",
      "offering_id":         "b3f8e1a2-...",   // MEDAXIS offering UUID
      "product_key":         "glp1-monthly",   // your product identifier — use to map to your catalogue
      "month_frequency":     3,                // billing cycle in months
      "compound_formula":    "Semaglutide 0.5mg/mL in bacteriostatic water",

      // sig: offering-level default — partner-specific override when configured,
      //      otherwise the global offering SIG. null when not set.
      //      Use dosing.sigs[] for the clinician's per-level instruction overrides.
      "sig":                 "Inject subcutaneously once weekly",

      "refills":             "3",
      "quantity":            "1",
      "days_supply":         "30",
      "dispense_unit":       "vial",
      "days_until_dispense": 7,

      "dosing": {
        "medication": "Semaglutide",
        "frequency":  "Once weekly",
        "term":       "3M",

        // months[]: one entry per dose level (L1→L2→L3→L4 for a 3M term).
        // Count follows the portal rule: 1M→1 level, 3M→4 levels, all others→3 levels.
        "months": ["0.25 mg", "0.5 mg", "1.0 mg", "1.5 mg"],

        // sigs[]: per-level SIG instructions set by the clinician at prescription time.
        // Parallel to months[] — sigs[0] is the instruction for months[0], etc.
        // null (or absent) when the clinician left all SIG fields unchanged.
        // Individual entries may be empty string "" when only some levels were overridden.
        "sigs": [
          "Inject 0.25 mg subcutaneously once weekly for the first month",
          "Inject 0.5 mg subcutaneously once weekly",
          "Inject 1.0 mg subcutaneously once weekly",
          "Inject 1.5 mg subcutaneously once weekly"
        ],

        // formulas[]: compound variant detail for each dose level — passed directly to pharmacy.
        // Parallel to months[] and sigs[] — formulas[0] is the variant for months[0], etc.
        // NOT shown to the prescriber. Use these for dispensing labels and pharmacy prep.
        // null on prescriptions written before Phase 3 (this field was not collected).
        "formulas": [
          "0.25mg/0.5mg/0.5mL (2mL)",
          "0.5mg/0.5mg/0.5mL (2mL)",
          "1mg/1mg/0.5mL (2mL)",
          "1.7mg/1mg/0.5mL (2mL)"
        ],

        // quantities[]: dispense quantity for each dose level, set on the offering per-level.
        // Parallel to months[], sigs[], and formulas[] — quantities[0] is the qty for months[0].
        // null (or absent) when no per-level quantities have been configured on the offering.
        // Individual entries may be null or "" when only some levels have a quantity set.
        "quantities": [0.5, 0.5, 1.0, 1.0]
      }
    }
  ],

  "timestamp": 1751540001
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-rx')">Copy</button>

<div class="alert alert-info mt-3 mb-2 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Handling <code>diagnoses</code>:</strong> Check the type before using — post-Phase 2 prescriptions send an <strong>array</strong> of <code>{ code, description }</code> objects; legacy prescriptions send a plain comma-separated <strong>string</strong>. Guard accordingly:
    <pre style="background:#f8fafc;color:#1f2937;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem;font-size:.8rem;margin-top:.4rem;overflow-x:auto">// PHP example
if (is_array($payload['diagnoses'])) {
    foreach ($payload['diagnoses'] as $d) {
        // $d['code'], $d['description']
    }
} else {
    // legacy comma-joined string: "E66.9, I10"
    $codes = explode(',', $payload['diagnoses']);
}</pre>
</div>

<div class="alert alert-info mt-0 mb-2 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Using <code>dosing.sigs[]</code> for per-level dispensing instructions:</strong>
    <code>dosing.sigs</code> is an array parallel to <code>dosing.months</code> — index 0 is the SIG for dose level M1, index 1 for M2, and so on. Use these to populate per-shipment dispensing labels or pharmacy instructions.
    <ul class="mb-1 mt-2">
        <li><code>dosing.sigs</code> is <code>null</code> when the clinician left all SIG fields at their defaults — fall back to the top-level <code>sig</code> field in that case.</li>
        <li>Individual entries may be an empty string <code>""</code> when only some levels were overridden — guard each entry before using it.</li>
        <li>The top-level <code>sig</code> field is always present (offering default, resolved per partner) and is safe to use as a fallback for the entire prescription.</li>
    </ul>
    <pre style="background:#f8fafc;color:#1f2937;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem;font-size:.8rem;margin-top:.4rem;overflow-x:auto">// PHP — resolve the SIG for a given dose level
function sigForLevel(array $med, int $levelIndex): string {
    $perLevel = $med['dosing']['sigs'][$levelIndex] ?? '';
    if ($perLevel !== '') return $perLevel;
    return $med['sig'] ?? '';   // offering default fallback
}</pre>
</div>

<div class="alert alert-info mt-0 mb-2 small">
    <i class="bi bi-capsule me-1"></i>
    <strong>Using <code>dosing.formulas[]</code> for pharmacy compound specifications:</strong>
    <code>dosing.formulas</code> is an array parallel to <code>dosing.months</code> and <code>dosing.sigs</code>. Each entry is the exact compound variant detail for that dose level (e.g. <code>"0.25mg/0.5mg/0.5mL (2mL)"</code>), intended for pharmacy dispensing labels and compounding instructions.
    <ul class="mb-1 mt-2">
        <li>This field was <strong>not shown to the prescriber</strong> — it is a pharmacy-facing specification only.</li>
        <li><code>dosing.formulas</code> is <code>null</code> on prescriptions written before Phase 3 (field was not collected). Always null-check before using.</li>
        <li>The array is parallel to <code>months[]</code> — <code>formulas[0]</code> is the variant for M1, <code>formulas[1]</code> for M2, and so on.</li>
    </ul>
    <pre style="background:#f8fafc;color:#1f2937;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem;font-size:.8rem;margin-top:.4rem;overflow-x:auto">// PHP — resolve compound formula for a given dose level
function formulaForLevel(array $med, int $levelIndex): ?string {
    return $med['dosing']['formulas'][$levelIndex] ?? null;
}</pre>
</div>

<div class="alert alert-info mt-0 mb-2 small">
    <i class="bi bi-123 me-1"></i>
    <strong>Using <code>dosing.quantities[]</code> for per-level dispense quantities:</strong>
    <code>dosing.quantities</code> is an array parallel to <code>dosing.months</code>, <code>dosing.sigs</code>, and <code>dosing.formulas</code>. Each entry is the numeric dispense quantity for that dose level, configured on the offering by the admin and auto-filled by the clinician at prescription time.
    <ul class="mb-1 mt-2">
        <li><code>dosing.quantities</code> is <code>null</code> when no per-level quantities have been configured on the offering. Fall back to the top-level <code>quantity</code> field in that case.</li>
        <li>Individual entries may be <code>null</code> or <code>""</code> when only some levels have a quantity set — guard each entry before using.</li>
        <li>The array is parallel to <code>months[]</code> — <code>quantities[0]</code> is the quantity for M1, <code>quantities[1]</code> for M2, and so on.</li>
    </ul>
    <pre style="background:#f8fafc;color:#1f2937;border:1px solid #e2e8f0;border-radius:6px;padding:.5rem .75rem;font-size:.8rem;margin-top:.4rem;overflow-x:auto">// PHP — resolve dispense quantity for a given dose level
function quantityForLevel(array $med, int $levelIndex): string {
    $perLevel = $med['dosing']['quantities'][$levelIndex] ?? null;
    if ($perLevel !== null && $perLevel !== '') return (string) $perLevel;
    return $med['quantity'] ?? '1';   // top-level offering quantity fallback
}</pre>
</div>

<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— full prescription data is in the payload above; call this only for additional case context</span>
</div>
<p class="mt-2 mb-0 small text-muted">This event fires alongside <code>case_approved</code> and <code>case_completed</code> whenever a prescription is confirmed through the review step. All three events fire in quick succession — listen for <code>case_completed</code> as the final confirmation.</p>
</div>
</div>

{{-- case_processing --}}
<div id="ev-case-processing" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">case_processing</span>
    <span class="text-muted small">Case has entered fulfilment processing (prescription sent to pharmacy / dispenser)</span>
</div>
<div class="card-body">
<pre id="code-ev-processing">{
  "case_id":    "9d2f1c3e-...",
  "patient_id": "a1b2c3d4-...",
  "status":     "processing",
  "visit_type": "asynchronous",
  "timestamp":  1751540100
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-processing')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> The payload confirms the case has entered fulfilment. GET is optional — use it only if you need order or tracking details at this point (a separate <code>tracking_number_changed</code> event fires when tracking is assigned).
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch current order and tracking details</span>
</div>
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
  "visit_type": "asynchronous",
  "timestamp":  1751599200
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-completed')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> Completion is confirmed in the payload. If you already handled <code>prescription_written</code>, you have all the clinical data — this event is the final signal to close the case in your system. GET is optional (for audit logs only).
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch final case record for your own audit log</span>
</div>
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
  "visit_type": "asynchronous",
  "timestamp":  1751540500
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-cancelled')">Copy</button>
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong><code>cancellation_reason</code> is NOT in this payload.</strong> The payload only confirms the case was cancelled. To display or log why it was cancelled, call GET to read the <code>cancellation_reason</code> field from the case record.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— read the <code>cancellation_reason</code> field for the specific reason</span>
</div>
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
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Note content is NOT in this payload (PHI exclusion).</strong> The payload is intentionally minimal — only the <code>case_id</code> is sent. Call GET to retrieve the clinical note body. This is a required pull for this event.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— retrieve the clinical note content</span>
</div>
</div>
</div>

{{-- message_created --}}
<div id="ev-message-created" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">message_created</span>
    <span class="text-muted small">A message was sent to the patient — <code>sender</code> identifies the origin; call GET to retrieve the body</span>
</div>
<div class="card-body">

<p class="small mb-2"><strong>This event fires in three distinct scenarios:</strong></p>
<table class="table table-sm table-bordered mb-3" style="font-size:.83rem">
<thead class="table-light"><tr><th><code>sender</code></th><th>When it fires</th><th>What to do</th></tr></thead>
<tbody>
<tr>
    <td><code>clinician</code></td>
    <td>Clinician sends a direct message to the patient; or clinician confirms a prescription (approval message sent automatically)</td>
    <td>Pull messages and display to patient</td>
</tr>
<tr>
    <td><code>system</code></td>
    <td>System sends an automated intake confirmation when a case is first assigned to a clinician</td>
    <td>Pull messages and display to patient — patient may not have prompted this message</td>
</tr>
<tr>
    <td><code>support</code></td>
    <td>Support team sends a message to the patient</td>
    <td>Pull messages and display to patient</td>
</tr>
</tbody>
</table>

<p class="small text-muted mb-1">Standard payload — all three senders:</p>
<pre id="code-ev-msg">{
  "case_id":   "9d2f1c3e-...",
  "sender":    "clinician",   // "clinician" | "system" | "support"
  "timestamp": 1751541300
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-msg')">Copy</button>

<p class="small text-muted mb-1 mt-3">Rejection payload — when a clinician declines a case, <code>reason</code> and <code>body</code> are also present:</p>
<pre id="code-ev-msg-reject">{
  "case_id":   "9d2f1c3e-...",
  "sender":    "clinician",
  "body":      "Thank you for submitting your request…",   // patient-facing rejection message
  "reason":    "case_declined",                            // always "case_declined" for rejections
  "timestamp": 1751541600
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-msg-reject')">Copy</button>

<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Message body is NOT in the standard payload.</strong> For all non-rejection messages, the body is excluded — call GET to retrieve the message text. The <code>reason: "case_declined"</code> field is only present on rejection payloads and can be used to route directly to a decline-handling path without a GET call.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/messages</code>
    <span class="text-muted" style="font-size:.75rem">— retrieve the full message thread; the most recent outbound message is the one just sent</span>
</div>
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
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Message body is NOT in this payload.</strong> The <code>message_id</code> confirms delivery, but the body is excluded. Call GET to retrieve the full message text for display or logging. This is a required pull if you need the content.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}/messages</code>
    <span class="text-muted" style="font-size:.75rem">— retrieve the full message body and thread</span>
</div>
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
  "case_id":   "9d2f1c3e-...",   // omitted when triggered by a cancel action
  "status":    "shipped",
  "timestamp": 1751599000
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-order')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>Pushed to your server.</strong> The new <code>order_id</code> and <code>status</code> are in the payload — update your order record directly. GET is optional.
</div>
<div class="alert alert-warning mt-0 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <code>case_id</code> is present when triggered by a status update but <strong>omitted</strong> when triggered by a cancel action. Always guard for its absence before using it.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch full order list and fulfillment status</span>
</div>
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
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>No pull needed.</strong> The <code>tracking_number</code> is in the payload — display it directly to the patient without any API call. GET is optional (for the full order record only).
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/cases/{case_id}</code>
    <span class="text-muted" style="font-size:.75rem">— optional: fetch the full order record if needed</span>
</div>
</div>
</div>

{{-- patient_modified --}}
<div id="ev-patient-modified" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">patient_modified</span>
    <span class="text-muted small">Fired when patient fields are updated via <code>PATCH /api/partner/patients/{uuid}</code></span>
</div>
<div class="card-body">
<pre id="code-ev-patient-modified">{
  "patient_id": "a1b2c3d4-...",
  "timestamp":  1751599300
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-patient-modified')">Copy</button>
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Updated fields are NOT listed in this payload.</strong> Only the <code>patient_id</code> is sent. To see what changed (e.g. updated <code>id_verified_status</code>), call GET to read the current patient record.
</div>
<div class="alert alert-info mt-0 mb-0 small">
    <i class="bi bi-shield-check me-1"></i>
    <strong>Vouched / async IDV:</strong> The most common reason to call <code>PATCH /api/partner/patients/{uuid}</code> is to push a Vouched identity-verification result after it resolves. Send <code>{ "id_verified_status": "verified", "id_verified_at": "…" }</code> — the portal immediately re-classifies all open cases for that patient and this <code>patient_modified</code> event fires as confirmation.
    Accepted values for <code>id_verified_status</code>: <code>verified</code> (triage unaffected), <code>pending</code> (Yellow triage), <code>failed</code> (Red hard stop).
</div>
<div class="endpoint-row mt-2">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/patients/{patient_id}</code>
    <span class="text-muted" style="font-size:.75rem">— confirm updated <code>id_verified_status</code> and <code>id_verified_at</code> on the patient record</span>
</div>
</div>
</div>

{{-- patient_created --}}
<div id="ev-patient-created" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">patient_created</span>
    <span class="text-muted small">Fired when a new patient record is created via <code>POST /api/partner/patients</code></span>
</div>
<div class="card-body">
<pre id="code-ev-patient-created">{
  "patient_id": "a1b2c3d4-...",
  "timestamp":  1751599350
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-patient-created')">Copy</button>
<div class="alert alert-warning mt-3 mb-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Full patient data is NOT in this payload.</strong> Only the <code>patient_id</code> is sent as a reference. Call GET to retrieve the full patient record and link it to your system.
</div>
<div class="endpoint-row">
    <span class="method-pill method-get">GET</span>
    <code>{{ $base }}/api/partner/patients/{patient_id}</code>
    <span class="text-muted" style="font-size:.75rem">— retrieve the full patient record just created</span>
</div>
</div>
</div>

{{-- patient_deleted --}}
<div id="ev-patient-deleted" class="card mb-3 section-anchor">
<div class="card-header py-2 d-flex align-items-center gap-2">
    <span class="event-badge">patient_deleted</span>
    <span class="text-muted small">Fired when a patient record is soft-deleted via <code>DELETE /api/partner/patients/{uuid}</code></span>
</div>
<div class="card-body">
<pre id="code-ev-patient-deleted">{
  "patient_id": "a1b2c3d4-...",
  "timestamp":  1751599400
}</pre>
<button class="btn btn-sm btn-outline-secondary copy-btn" style="position:relative;top:auto;right:auto;margin-top:-4px" onclick="copyCode('code-ev-patient-deleted')">Copy</button>
<div class="alert alert-success mt-3 mb-2 small">
    <i class="bi bi-broadcast me-1"></i>
    <strong>No pull needed.</strong> The payload confirms the deletion. The record is soft-deleted and no longer accessible via the API — any GET call will return 404. Mark this patient as inactive in your system.
</div>
<div class="alert alert-info mt-0 mb-0 small">
    <i class="bi bi-info-circle me-1"></i>
    The patient record is <strong>soft-deleted</strong> — it is no longer accessible via the API but data is retained for audit purposes. Any open cases for this patient should be considered stale.
</div>
</div>
</div>

{{-- 6. CHECKLIST --}}
<div id="checklist" class="card mb-4 section-anchor">
<div class="card-header fw-semibold"><span class="step-badge bg-secondary text-white me-2">6</span>Integration Checklist</div>
<div class="card-body">
<ul class="list-unstyled mb-0">
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Obtain Bearer token via <code>POST /api/partner/auth/token</code></li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Register endpoint: <code>POST /api/partner/webhooks</code> with your URL — store the <code>secret</code> returned in the response (shown only once)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Endpoint must be <strong>HTTPS</strong> and publicly reachable; respond with <code>200</code> within 10 seconds</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Verify <code>X-Webhook-Signature</code> on <strong>every</strong> incoming request using a constant-time comparison</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Route by <code>X-Event-Type</code> header — do not rely solely on payload fields to identify the event</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>prescription_written</code></strong> — each item in <code>meds_prescribed[]</code> includes <code>offering_id</code>, <code>product_key</code>, <code>month_frequency</code>, a flat <code>sig</code> (offering default), per-level <code>dosing.sigs[]</code> overrides (prefer <code>dosing.sigs[i]</code> when non-empty, fall back to <code>sig</code>), and <strong><code>dosing.formulas[]</code></strong> (compound variant detail per dose level, for pharmacy/dispensing — <code>null</code> on legacy prescriptions)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>case_support</code></strong> — <code>support_note</code> is in the payload; notify your team and use the escalation chat (portal or <code>POST /messages</code>) to reply</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>escalation_started</code></strong> — clinician forwarded a patient message without changing case status; notify your support team and call <code>GET /messages?channel=escalation</code> to fetch the initial message</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Subscribe to <strong><code>escalation_message_sent</code></strong> — poll <code>GET /messages?channel=escalation</code> on receipt to fetch the clinician's reply body</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>case_returned_to_clinician</code></strong> — lock the escalation thread on your side when received (full escalation path)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>support_thread_closed</code></strong> — parallel thread closed by your team; lock the thread on your side (case status unchanged)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Handle <strong><code>message_created</code></strong> — check <code>sender</code>: <code>clinician</code> = doctor message or prescription approval message, <code>system</code> = automated intake confirmation, <code>support</code> = support team message; if <code>reason === "case_declined"</code> the body is in the payload directly (rejection path only)</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Make your handler <strong>idempotent</strong> — the same event may be delivered more than once on retry</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Return <code>200</code> immediately, then process asynchronously — do not do heavy work before responding</li>
    <li class="mb-2"><i class="bi bi-check-square text-success me-2"></i>Push Vouched IDV results via <code>PATCH /api/partner/patients/{uuid}</code> with <code>id_verified_status</code> = <code>verified</code> / <code>failed</code> / <code>pending</code> — you will receive a <code>patient_modified</code> event as confirmation and open cases re-triage automatically</li>
    <li class="mb-0"><i class="bi bi-check-square text-success me-2"></i>Resend failed deliveries via <code>POST /api/partner/webhooks/deliveries/{deliveryId}/resend</code>, or ask the portal admin to resend from the Webhook Logs screen</li>
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

// ── Scroll spy ──────────────────────────────────────────────
(function () {
    var navLinks = document.querySelectorAll('#wh-toc .wh-nav-link');
    var sections = [];
    navLinks.forEach(function (a) {
        var id = a.getAttribute('href').replace('#', '');
        var el = document.getElementById(id);
        if (el) sections.push({ el: el, link: a });
    });
    if (!sections.length) return;

    function onScroll() {
        var scrollY = window.scrollY + 100;
        var active = sections[0];
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].el.getBoundingClientRect().top + window.scrollY <= scrollY) {
                active = sections[i];
            }
        }
        navLinks.forEach(function (a) { a.classList.remove('wh-active'); });
        active.link.classList.add('wh-active');

        // keep active link visible in the scrollable TOC
        var toc = document.querySelector('#wh-toc > div:nth-child(2)');
        if (toc) {
            var linkTop = active.link.offsetTop;
            var linkBot = linkTop + active.link.offsetHeight;
            if (linkTop < toc.scrollTop) toc.scrollTop = linkTop - 8;
            else if (linkBot > toc.scrollTop + toc.clientHeight) toc.scrollTop = linkBot - toc.clientHeight + 8;
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();
</script>
@endsection
