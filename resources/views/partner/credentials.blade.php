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

                {{-- Add Webhook Form --}}
                <form method="POST" action="{{ route('partner.webhooks.store') }}" class="mb-4">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Endpoint URL</label>
                            <input type="url" name="url" class="form-control form-control-sm @error('url') is-invalid @enderror"
                                   placeholder="https://your-server.com/webhooks/doctor-portal" value="{{ old('url') }}" required>
                            @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Event Type</label>
                            <select name="event_type" class="form-select form-select-sm @error('event_type') is-invalid @enderror">
                                <option value="">All Events</option>
                                @foreach(['case_waiting','case_assigned_to_clinician','case_support','case_approved','prescription_written','case_completed','case_cancelled','message_created'] as $evt)
                                    <option value="{{ $evt }}" @selected(old('event_type') === $evt)>{{ $evt }}</option>
                                @endforeach
                            </select>
                            @error('event_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-plus-lg me-1"></i>Add
                            </button>
                        </div>
                    </div>
                </form>

                @if($webhooks->isEmpty())
                    <p class="text-muted small mb-0">No webhooks configured yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>URL</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
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
                                    <td class="text-end text-nowrap">
                                        <form method="POST" action="{{ route('partner.webhooks.update', $wh->id) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-outline-secondary btn-sm py-0 px-1" title="Toggle status">
                                                <i class="bi bi-{{ $wh->status === 'active' ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('partner.webhooks.destroy', $wh->id) }}" class="d-inline"
                                              onsubmit="return confirm('Delete this webhook?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
                <p class="small text-muted mb-3">
                    Follow these steps to integrate with the Partner API.
                    For a full field reference and questionnaire payload, see the
                    <strong>Weight Loss API</strong> or <strong>Anti-Aging API</strong> guides in your admin console.
                </p>

                <h6 class="small fw-bold mb-2">Step 1 — Get an access token</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/auth/token') }}
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&amp;client_id=YOUR_CLIENT_ID
&amp;client_secret=YOUR_CLIENT_SECRET</pre>
                <p class="small text-muted mt-1 mb-3">Response: <code>{"token_type":"Bearer","access_token":"…","expires_in":…}</code></p>

                <h6 class="small fw-bold mb-2">Step 2 — Register a patient</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/patients') }}
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "external_id":   "user-123",
  "first_name":    "Jane",
  "last_name":     "Doe",
  "email":         "jane@example.com",
  "date_of_birth": "1985-06-15",
  "gender":        "female",
  "phone":         "+15551234567",
  "state":         "CA",
  "address":       "123 Main St",
  "city":          "Los Angeles",
  "zip":           "90001"
}</pre>

                <h6 class="small fw-bold mb-2 mt-3">Step 3 — Submit a case</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">POST {{ url('/api/partner/cases') }}
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "patient_id":   1,
  "offering_ids": ["&lt;offering_uuid&gt;"],
  "questionnaire_responses": [
    { "slug": "pregnant_breastfeeding",   "answer": "no" },
    { "slug": "blood_pressure_range",     "answer": "normal" },
    { "slug": "prescription_medications", "answer": "no" },
    { "slug": "medication_allergies",     "answer": "no" },
    { "slug": "medical_conditions",       "answer": "no" },
    { "slug": "first_message_to_doctor",  "answer": "Interested in weight loss program." },
    { "slug": "telehealth_informed_consent", "answer": "yes" }
  ]
}</pre>
                <p class="small text-muted mt-1 mb-3">
                    The <code>offering_uuid</code> is shown on each approved offering's detail page.
                    Required questionnaire slugs vary by offering — see the full API guides for a complete list.
                </p>

                <h6 class="small fw-bold mb-2">Step 4 — Verify webhook signature</h6>
                <pre class="bg-dark text-light rounded p-3 small" style="font-size:.72rem">$header = $request->header('X-Webhook-Signature');
// format: "sha256={hex_digest}"
$digest   = str_replace('sha256=', '', $header);
$expected = hash_hmac('sha256', $rawBody, WEBHOOK_SECRET);

if (!hash_equals($expected, $digest)) {
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
