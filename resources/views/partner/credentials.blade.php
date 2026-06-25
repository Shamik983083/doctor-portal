@extends('layouts.partner')
@section('title', 'API Credentials')
@section('page-title', 'API Credentials')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">OAuth2 Client Credentials</h6></div>
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Use these credentials to obtain access tokens for the Partner REST API.
                    Keep your Client Secret and Webhook Secret confidential — never expose them in client-side code.
                </p>

                <div class="mb-4">
                    <label class="form-label fw-medium small text-muted text-uppercase tracking-wide">Client ID</label>
                    <div class="input-group">
                        <input type="text" id="clientId" class="form-control font-monospace"
                               value="{{ $partner->client_id }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyField('clientId')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium small text-muted text-uppercase">Client Secret</label>
                    <div class="input-group">
                        <input type="password" id="clientSecret" class="form-control font-monospace"
                               value="{{ $partner->client_secret }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="toggleField('clientSecret', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="copyField('clientSecret')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium small text-muted text-uppercase">Webhook Secret</label>
                    <div class="input-group">
                        <input type="password" id="webhookSecret" class="form-control font-monospace"
                               value="{{ $partner->webhook_secret }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="toggleField('webhookSecret', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="copyField('webhookSecret')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <div class="form-text">Used to verify HMAC-SHA256 signatures on incoming webhook payloads.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium small text-muted text-uppercase">Token Endpoint</label>
                    <input type="text" class="form-control font-monospace bg-light"
                           value="{{ url('/api/partner/auth/token') }}" readonly>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Webhooks</h6></div>
            <div class="card-body">
                @if($webhooks->isEmpty())
                    <p class="text-muted small mb-0">No webhooks configured. Contact the platform administrator to set up webhook endpoints.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>URL</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($webhooks as $wh)
                                <tr>
                                    <td class="font-monospace">{{ Str::limit($wh->url, 45) }}</td>
                                    <td>{{ $wh->event_type ?? 'All events' }}</td>
                                    <td>
                                        @if($wh->status === 'active')
                                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ ucfirst($wh->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Quick-Start Guide</h6></div>
            <div class="card-body">
                <p class="small text-muted mb-3">Follow these steps to integrate with the Partner API.</p>

                <h6 class="small fw-bold mb-2">Step 1 — Get an access token</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/auth/token') }}
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&amp;client_id=YOUR_CLIENT_ID
&amp;client_secret=YOUR_CLIENT_SECRET</pre>

                <h6 class="small fw-bold mb-2 mt-3">Step 2 — Register a patient</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/patients') }}
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "external_id": "user-123",
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "date_of_birth": "1985-06-15",
  "state": "CA"
}</pre>

                <h6 class="small fw-bold mb-2 mt-3">Step 3 — Submit a case</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/cases') }}
Authorization: Bearer {access_token}

{
  "patient_id": 1,
  "offering_ids": [1, 2],
  "intake_data": { "chief_complaint": "…" }
}</pre>

                <h6 class="small fw-bold mb-2 mt-3">Step 4 — Verify webhook signature</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">$sig = hash_hmac('sha256',
    $rawBody, WEBHOOK_SECRET);

if (!hash_equals($sig,
    $request->header('X-Signature'))) {
    return response('Unauthorized', 401);
}</pre>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleField(id, btn) {
    const field = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
function copyField(id) {
    const field = document.getElementById(id);
    navigator.clipboard.writeText(field.value).then(() => {
        const orig = field.nextElementSibling?.nextElementSibling || field.nextElementSibling;
        const btn  = orig?.tagName === 'BUTTON' ? orig : field.parentElement.querySelector('button:last-child');
        if (btn) {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check-lg text-success';
            setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 1800);
        }
    });
}
</script>
@endpush
