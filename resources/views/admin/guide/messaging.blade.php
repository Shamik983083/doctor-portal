@extends('layouts.admin')

@section('title', 'Integration Guide — Messaging')
@section('page-title', 'Integration Guide')

@section('content')
@php $base = rtrim(config('app.url'), '/'); @endphp

<style>
    .guide-section { scroll-margin-top: 80px; }
    pre.code-block {
        background: #1e1e2e;
        color: #cdd6f4;
        border-radius: 8px;
        padding: 1.1rem 1.25rem;
        font-size: .82rem;
        line-height: 1.6;
        overflow-x: auto;
        position: relative;
        margin-bottom: 0;
    }
    pre.code-block .hl  { color: #a6e3a1; }   /* green  — keys / methods */
    pre.code-block .hv  { color: #fab387; }   /* orange — values */
    pre.code-block .hc  { color: #6c7086; }   /* grey   — comments */
    pre.code-block .hh  { color: #89dceb; }   /* cyan   — headers */
    .copy-btn {
        position: absolute;
        top: .55rem;
        right: .75rem;
        font-size: .72rem;
        padding: 2px 10px;
        opacity: .55;
        transition: opacity .15s;
    }
    .copy-btn:hover { opacity: 1; }
    .flow-box {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: .75rem 1.25rem;
        background: #f8f9fa;
        font-size: .875rem;
    }
    .flow-arrow { color: #6c757d; font-size: 1.2rem; line-height:1; }
    .toc a { color: inherit; text-decoration: none; font-size: .875rem; }
    .toc a:hover { text-decoration: underline; }
    .endpoint-method { font-size: .75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
    .method-get  { background:#d1e7dd; color:#0a3622; }
    .method-post { background:#cfe2ff; color:#052c65; }
</style>

{{-- Header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h5 class="mb-1">Patient Portal Messaging Integration</h5>
        <p class="text-muted mb-0" style="font-size:.9rem">Share this guide with partner developers to enable bidirectional in-case messaging between the patient portal and clinicians.</p>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<div class="row g-4">

    {{-- Table of Contents --}}
    <div class="col-lg-3">
        <div class="card sticky-top" style="top:1rem">
            <div class="card-header py-2"><small class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">Contents</small></div>
            <div class="card-body py-2 toc">
                <ul class="list-unstyled mb-0" style="line-height:2">
                    <li><a href="#s-overview">1. Overview</a></li>
                    <li><a href="#s-auth">2. Authentication</a></li>
                    <li><a href="#s-fetch">3. Fetch Message Thread</a></li>
                    <li><a href="#s-send">4. Send Patient Message</a></li>
                    <li><a href="#s-webhooks">5. Register Webhooks</a></li>
                    <li><a href="#s-events">6. Webhook Events</a></li>
                    <li><a href="#s-hmac">7. Verify Signatures</a></li>
                    <li><a href="#s-flow">8. Full Flow Example</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-9">

        {{-- 1. Overview --}}
        <div class="card mb-4 guide-section" id="s-overview">
            <div class="card-header"><strong>1. Overview</strong></div>
            <div class="card-body">
                <p class="mb-3">Communication between your patient portal and the clinician is <strong>bidirectional and case-scoped</strong>. Every message belongs to a specific case UUID.</p>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="flow-box text-center" style="min-width:160px">
                        <div class="fw-semibold">Patient Portal</div>
                        <small class="text-muted">your system</small>
                    </div>
                    <div class="text-center" style="min-width:80px">
                        <div class="flow-arrow">⇄</div>
                        <small class="text-muted d-block" style="font-size:.72rem">REST API<br>+ Webhooks</small>
                    </div>
                    <div class="flow-box text-center" style="min-width:160px">
                        <div class="fw-semibold">Doctor Portal</div>
                        <small class="text-muted">this system</small>
                    </div>
                    <div class="text-center" style="min-width:80px">
                        <div class="flow-arrow">→</div>
                        <small class="text-muted d-block" style="font-size:.72rem">assigned</small>
                    </div>
                    <div class="flow-box text-center" style="min-width:160px">
                        <div class="fw-semibold">Clinician</div>
                        <small class="text-muted">reads &amp; replies</small>
                    </div>
                </div>
                <ul class="mt-3 mb-0" style="font-size:.875rem">
                    <li><strong>Clinician → Patient:</strong> Clinician types a message in the doctor portal → your webhook endpoint receives a <code>message_created</code> event → display to patient.</li>
                    <li class="mt-1"><strong>Patient → Clinician:</strong> Patient replies in your portal → <code>POST /api/partner/cases/{id}/messages</code> → clinician sees it immediately on next case view.</li>
                </ul>
            </div>
        </div>

        {{-- 2. Authentication --}}
        <div class="card mb-4 guide-section" id="s-auth">
            <div class="card-header"><strong>2. Authentication</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">All API calls require an OAuth 2.0 Bearer token obtained via the <code>client_credentials</code> grant. Tokens expire after <strong>1 year</strong> — refresh proactively.</p>
                <div class="mb-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold" style="font-size:.82rem">Request</span>
                    <span><span class="endpoint-method method-post">POST</span> <code style="font-size:.8rem">{{ $base }}/api/partner/auth/token</code></span>
                </div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">curl -X POST {{ $base }}/api/partner/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    <span class="hl">"grant_type"</span>:    <span class="hv">"client_credentials"</span>,
    <span class="hl">"client_id"</span>:     <span class="hv">"YOUR_CLIENT_ID"</span>,
    <span class="hl">"client_secret"</span>: <span class="hv">"YOUR_CLIENT_SECRET"</span>
  }'</pre>
                </div>
                <div class="mt-3 mb-2 fw-semibold" style="font-size:.82rem">Response</div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">{
  <span class="hl">"token_type"</span>:   <span class="hv">"Bearer"</span>,
  <span class="hl">"expires_in"</span>:   <span class="hv">31536000</span>,
  <span class="hl">"access_token"</span>: <span class="hv">"eyJ0eXAiOiJKV1Qi..."</span>
}</pre>
                </div>
                <p class="mt-3 mb-0 text-muted" style="font-size:.82rem">Use the token in all subsequent requests: <code>Authorization: Bearer {access_token}</code></p>
            </div>
        </div>

        {{-- 3. Fetch Thread --}}
        <div class="card mb-4 guide-section" id="s-fetch">
            <div class="card-header"><strong>3. Fetch Message Thread</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">Retrieve the full conversation for a case, ordered oldest-first. Use this to build the initial chat view in your patient portal, or to poll for new messages.</p>
                <div class="mb-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold" style="font-size:.82rem">Request</span>
                    <span><span class="endpoint-method method-get">GET</span> <code style="font-size:.8rem">{{ $base }}/api/partner/cases/{case_id}/messages</code></span>
                </div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">curl {{ $base }}/api/partner/cases/<span class="hv">{case_id}</span>/messages \
  -H <span class="hv">"Authorization: Bearer {access_token}"</span></pre>
                </div>
                <div class="mt-3 mb-2 fw-semibold" style="font-size:.82rem">Response</div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">[
  {
    <span class="hl">"uuid"</span>:        <span class="hv">"550e8400-e29b-41d4-a716-446655440000"</span>,
    <span class="hl">"direction"</span>:   <span class="hv">"outbound"</span>,   <span class="hc">// outbound = clinician sent | inbound = patient sent</span>
    <span class="hl">"sender_type"</span>: <span class="hv">"clinician"</span>,
    <span class="hl">"body"</span>:        <span class="hv">"Hi, please confirm your current dosage."</span>,
    <span class="hl">"is_read"</span>:     <span class="hv">true</span>,
    <span class="hl">"read_at"</span>:     <span class="hv">"2026-07-01T10:30:00.000000Z"</span>,
    <span class="hl">"created_at"</span>:  <span class="hv">"2026-07-01T10:22:00.000000Z"</span>
  },
  {
    <span class="hl">"uuid"</span>:        <span class="hv">"661e9511-f30c-52e5-b827-557766551111"</span>,
    <span class="hl">"direction"</span>:   <span class="hv">"inbound"</span>,
    <span class="hl">"sender_type"</span>: <span class="hv">"patient"</span>,
    <span class="hl">"body"</span>:        <span class="hv">"I am currently taking 10mg once daily."</span>,
    <span class="hl">"is_read"</span>:     <span class="hv">false</span>,
    <span class="hl">"read_at"</span>:     <span class="hv">null</span>,
    <span class="hl">"created_at"</span>:  <span class="hv">"2026-07-01T11:05:00.000000Z"</span>
  }
]</pre>
                </div>
                <div class="mt-3 p-2 px-3 rounded" style="background:#fff3cd;font-size:.82rem">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>direction</strong> is always relative to the doctor portal: <code>outbound</code> = clinician wrote it, <code>inbound</code> = patient wrote it. Render <code>outbound</code> on the right and <code>inbound</code> on the left in your chat UI.
                </div>
            </div>
        </div>

        {{-- 4. Send Patient Message --}}
        <div class="card mb-4 guide-section" id="s-send">
            <div class="card-header"><strong>4. Send Patient Message</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">Call this when a patient submits a reply in your portal. The message is stored immediately and the assigned clinician sees it on their next case view.</p>
                <div class="mb-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold" style="font-size:.82rem">Request</span>
                    <span><span class="endpoint-method method-post">POST</span> <code style="font-size:.8rem">{{ $base }}/api/partner/cases/{case_id}/messages</code></span>
                </div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">curl -X POST {{ $base }}/api/partner/cases/<span class="hv">{case_id}</span>/messages \
  -H <span class="hv">"Authorization: Bearer {access_token}"</span> \
  -H <span class="hv">"Content-Type: application/json"</span> \
  -d '{
    <span class="hl">"body"</span>:        <span class="hv">"I am currently taking 10mg once daily."</span>,
    <span class="hl">"sender_name"</span>: <span class="hv">"Jane Doe"</span>  <span class="hc">// optional, for display purposes</span>
  }'</pre>
                </div>
                <div class="mt-3 mb-2 fw-semibold" style="font-size:.82rem">Response — 201 Created</div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">{
  <span class="hl">"uuid"</span>:        <span class="hv">"661e9511-f30c-52e5-b827-557766551111"</span>,
  <span class="hl">"case_id"</span>:     <span class="hv">42</span>,
  <span class="hl">"direction"</span>:   <span class="hv">"inbound"</span>,
  <span class="hl">"sender_type"</span>: <span class="hv">"patient"</span>,
  <span class="hl">"body"</span>:        <span class="hv">"I am currently taking 10mg once daily."</span>,
  <span class="hl">"is_read"</span>:     <span class="hv">false</span>,
  <span class="hl">"created_at"</span>:  <span class="hv">"2026-07-01T11:05:00.000000Z"</span>
}</pre>
                </div>
                <div class="mt-3 p-2 px-3 rounded" style="background:#d1e7dd;font-size:.82rem">
                    <i class="bi bi-check-circle me-1"></i>
                    The <code>case_id</code> in the URL is the case <strong>UUID</strong> returned when the case was first created via <code>POST /api/partner/cases</code>.
                </div>
            </div>
        </div>

        {{-- 5. Register Webhooks --}}
        <div class="card mb-4 guide-section" id="s-webhooks">
            <div class="card-header"><strong>5. Register Webhooks</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">Register an HTTPS endpoint on your patient portal to receive real-time notifications when a clinician sends a message. You need to do this once per partner account.</p>
                <div class="mb-2 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold" style="font-size:.82rem">Register</span>
                    <span><span class="endpoint-method method-post">POST</span> <code style="font-size:.8rem">{{ $base }}/api/partner/webhooks</code></span>
                </div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">curl -X POST {{ $base }}/api/partner/webhooks \
  -H <span class="hv">"Authorization: Bearer {access_token}"</span> \
  -H <span class="hv">"Content-Type: application/json"</span> \
  -d '{
    <span class="hl">"url"</span>:        <span class="hv">"https://your-patient-portal.com/webhooks/doctor"</span>,
    <span class="hl">"event_type"</span>: <span class="hv">"message_created"</span>  <span class="hc">// omit to receive ALL events</span>
  }'</pre>
                </div>
                <div class="mt-3 mb-2 fw-semibold" style="font-size:.82rem">Response</div>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block">{
  <span class="hl">"id"</span>:         <span class="hv">1</span>,
  <span class="hl">"uuid"</span>:       <span class="hv">"abc12345-..."</span>,
  <span class="hl">"url"</span>:        <span class="hv">"https://your-patient-portal.com/webhooks/doctor"</span>,
  <span class="hl">"event_type"</span>: <span class="hv">"message_created"</span>,
  <span class="hl">"secret"</span>:     <span class="hv">"AbCdEfGhIjKlMnOpQrStUvWxYz123456"</span>,  <span class="hc">// save this — used to verify signatures</span>
  <span class="hl">"status"</span>:     <span class="hv">"active"</span>
}</pre>
                </div>
                <div class="mt-3 p-2 px-3 rounded" style="background:#fff3cd;font-size:.82rem">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Store the <code>secret</code> securely. It is shown only once. You will use it to verify incoming webhook signatures (see Section 7).
                </div>
            </div>
        </div>

        {{-- 6. Webhook Events --}}
        <div class="card mb-4 guide-section" id="s-events">
            <div class="card-header"><strong>6. Webhook Events</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">When your registered endpoint receives a webhook, the request body is JSON with a consistent envelope. The <code>X-Event-Type</code> header identifies the event.</p>

                <p class="fw-semibold mb-1" style="font-size:.82rem">Event: <code>message_created</code> — Clinician sent a message</p>
                <div class="position-relative mb-4">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block"><span class="hc">// HTTP POST to your registered URL</span>
<span class="hh">Content-Type: application/json</span>
<span class="hh">X-Event-Type: message_created</span>
<span class="hh">X-Webhook-Signature: sha256=a3f9...</span>

{
  <span class="hl">"case_id"</span>:   <span class="hv">"550e8400-e29b-41d4-a716-446655440000"</span>,
  <span class="hl">"sender"</span>:    <span class="hv">"clinician"</span>,
  <span class="hl">"timestamp"</span>: <span class="hv">1751452800</span>
}</pre>
                </div>

                <p class="fw-semibold mb-1" style="font-size:.82rem">Event: <code>patient_message_received</code> — Your portal's message was accepted</p>
                <div class="position-relative mb-4">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block"><span class="hh">X-Event-Type: patient_message_received</span>

{
  <span class="hl">"case_id"</span>:    <span class="hv">"550e8400-e29b-41d4-a716-446655440000"</span>,
  <span class="hl">"message_id"</span>: <span class="hv">"661e9511-f30c-52e5-b827-557766551111"</span>,
  <span class="hl">"timestamp"</span>:  <span class="hv">1751453100</span>
}</pre>
                </div>

                <p class="fw-semibold mb-1" style="font-size:.82rem">Other case events (subscribe selectively or receive all)</p>
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
                    <thead class="table-light">
                        <tr><th>event_type</th><th>Triggered when</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>case_created</code></td><td>New case submitted</td></tr>
                        <tr><td><code>case_assigned_to_clinician</code></td><td>Clinician assigned</td></tr>
                        <tr><td><code>case_approved</code></td><td>Clinician approves and submits prescription</td></tr>
                        <tr><td><code>case_processing</code></td><td>Sent to pharmacy</td></tr>
                        <tr><td><code>case_completed</code></td><td>Case closed</td></tr>
                        <tr><td><code>case_cancelled</code></td><td>Case cancelled by any actor</td></tr>
                        <tr><td><code>case_support</code></td><td>Escalated to support</td></tr>
                        <tr><td><code>message_created</code></td><td>Clinician sent a message</td></tr>
                        <tr><td><code>patient_message_received</code></td><td>Patient message stored</td></tr>
                    </tbody>
                </table>
                <p class="mt-2 mb-0 text-muted" style="font-size:.8rem">Failed deliveries are retried up to 5 times with exponential back-off (30 s → 60 s → 120 s → 240 s → 300 s). Your endpoint must return a <code>2xx</code> status within 10 seconds.</p>
            </div>
        </div>

        {{-- 7. HMAC --}}
        <div class="card mb-4 guide-section" id="s-hmac">
            <div class="card-header"><strong>7. Verify Webhook Signatures</strong></div>
            <div class="card-body">
                <p style="font-size:.875rem">Every webhook request includes an <code>X-Webhook-Signature</code> header. Always verify it before trusting the payload — this ensures the request genuinely came from the doctor portal and was not tampered with.</p>

                <div class="mb-3 p-2 px-3 rounded" style="background:#f8f9fa;font-size:.82rem">
                    <strong>Algorithm:</strong> HMAC-SHA256 over the raw JSON request body, using your webhook <code>secret</code>.<br>
                    <strong>Header format:</strong> <code>sha256={hex_digest}</code>
                </div>

                <p class="fw-semibold mb-1" style="font-size:.82rem">PHP</p>
                <div class="position-relative mb-4">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block"><span class="hc">// In your webhook handler</span>
$secret    = <span class="hv">'AbCdEfGhIjKlMnOpQrStUvWxYz123456'</span>; <span class="hc">// from webhook registration</span>
$rawBody   = file_get_contents(<span class="hv">'php://input'</span>);
$signature = hash_hmac(<span class="hv">'sha256'</span>, $rawBody, $secret);
$expected  = <span class="hv">'sha256='</span> . $signature;
$received  = $_SERVER[<span class="hv">'HTTP_X_WEBHOOK_SIGNATURE'</span>] ?? <span class="hv">''</span>;

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit(<span class="hv">'Invalid signature'</span>);
}

$payload = json_decode($rawBody, true);
<span class="hc">// handle $payload['case_id'] etc.</span></pre>
                </div>

                <p class="fw-semibold mb-1" style="font-size:.82rem">Node.js (Express)</p>
                <div class="position-relative mb-4">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block"><span class="hl">const</span> crypto = require(<span class="hv">'crypto'</span>);

app.post(<span class="hv">'/webhooks/doctor'</span>, express.raw({ type: <span class="hv">'application/json'</span> }), (req, res) => {
  <span class="hl">const</span> secret   = <span class="hv">'AbCdEfGhIjKlMnOpQrStUvWxYz123456'</span>;
  <span class="hl">const</span> expected = <span class="hv">'sha256='</span> + crypto
    .createHmac(<span class="hv">'sha256'</span>, secret)
    .update(req.body)
    .digest(<span class="hv">'hex'</span>);
  <span class="hl">const</span> received = req.headers[<span class="hv">'x-webhook-signature'</span>] || <span class="hv">''</span>;

  <span class="hl">if</span> (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(received))) {
    <span class="hl">return</span> res.status(401).send(<span class="hv">'Invalid signature'</span>);
  }

  <span class="hl">const</span> payload = JSON.parse(req.body);
  <span class="hc">// handle payload.case_id, payload.sender, etc.</span>
  res.status(200).send(<span class="hv">'OK'</span>);
});</pre>
                </div>

                <p class="fw-semibold mb-1" style="font-size:.82rem">Python (Flask)</p>
                <div class="position-relative">
                    <button class="btn btn-sm btn-dark copy-btn" onclick="copyCode(this)">Copy</button>
<pre class="code-block"><span class="hl">import</span> hmac, hashlib
<span class="hl">from</span> flask <span class="hl">import</span> request, abort

@app.route(<span class="hv">'/webhooks/doctor'</span>, methods=[<span class="hv">'POST'</span>])
<span class="hl">def</span> webhook():
    secret   = b<span class="hv">'AbCdEfGhIjKlMnOpQrStUvWxYz123456'</span>
    body     = request.get_data()
    expected = <span class="hv">'sha256='</span> + hmac.new(secret, body, hashlib.sha256).hexdigest()
    received = request.headers.get(<span class="hv">'X-Webhook-Signature'</span>, <span class="hv">''</span>)

    <span class="hl">if not</span> hmac.compare_digest(expected, received):
        abort(401)

    payload = request.get_json(force=True)
    <span class="hc"># handle payload['case_id'] etc.</span>
    <span class="hl">return</span> <span class="hv">'OK'</span>, 200</pre>
                </div>
            </div>
        </div>

        {{-- 8. Full Flow --}}
        <div class="card mb-4 guide-section" id="s-flow">
            <div class="card-header"><strong>8. Full Flow Example</strong></div>
            <div class="card-body" style="font-size:.875rem">
                <ol class="mb-0" style="line-height:2.2">
                    <li>Patient submits intake form on your portal → you call <code>POST /api/partner/cases</code> → receive <code>case_id</code> (UUID) in response.</li>
                    <li>Doctor portal assigns a clinician. Your webhook receives <code>case_assigned_to_clinician</code>.</li>
                    <li>Clinician sends a message → your webhook receives <code>message_created</code> with <code>case_id</code>.</li>
                    <li>You call <code>GET /api/partner/cases/{case_id}/messages</code> to fetch the message body and display it to the patient.</li>
                    <li>Patient types a reply → you call <code>POST /api/partner/cases/{case_id}/messages</code> with <code>{ "body": "..." }</code>.</li>
                    <li>Clinician sees the unread badge on their case queue and reads the reply.</li>
                    <li>Clinician replies again → repeat from step 3.</li>
                </ol>

                <hr class="my-4">
                <p class="fw-semibold mb-2">Minimal integration checklist</p>
                <ul class="mb-0" style="line-height:2">
                    <li>☐ Store your <code>client_id</code> and <code>client_secret</code> securely (env variables, not source code)</li>
                    <li>☐ Token refresh logic (re-request when 401 is received)</li>
                    <li>☐ Register a webhook for <code>message_created</code> pointing to an HTTPS endpoint</li>
                    <li>☐ Verify <code>X-Webhook-Signature</code> on every incoming webhook (use constant-time comparison)</li>
                    <li>☐ Return <code>200</code> from your webhook endpoint within 10 seconds (process async if needed)</li>
                    <li>☐ Store <code>case_id</code> (UUID) against your patient record so you can thread messages correctly</li>
                    <li>☐ Handle <code>case_cancelled</code> and <code>case_completed</code> to update patient-facing status</li>
                </ul>
            </div>
        </div>

    </div>{{-- col --}}
</div>{{-- row --}}
@endsection

@section('scripts')
<script>
function copyCode(btn) {
    const pre = btn.nextElementSibling;
    const text = pre.innerText;
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 1800);
    });
}
</script>
@endsection
