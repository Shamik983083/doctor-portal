@extends('layouts.admin')

@section('title', $partner->name)
@section('page-title', "Partner: {$partner->name}")

@section('content')
<div class="row g-4">

    {{-- Left column --}}
    <div class="col-lg-4">

        {{-- Partner info --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1">{{ $partner->name }}</h5>
                        <p class="text-muted small mb-0">{{ $partner->email }}</p>
                    </div>
                    <span class="badge {{ match($partner->status) { 'active' => 'bg-success', 'suspended' => 'bg-warning text-dark', default => 'bg-secondary' } }}">
                        {{ ucfirst($partner->status) }}
                    </span>
                </div>
                <table class="table table-sm table-borderless small mb-0">
                    @if($partner->phone)
                    <tr><th class="text-muted">Phone</th><td>{{ $partner->phone }}</td></tr>
                    @endif
                    @if($partner->website)
                    <tr><th class="text-muted">Website</th><td><a href="{{ $partner->website }}" target="_blank">{{ $partner->website }}</a></td></tr>
                    @endif
                    <tr><th class="text-muted">Patients</th><td>{{ $partner->patients_count }}</td></tr>
                    <tr><th class="text-muted">Cases</th><td>{{ $partner->cases_count }}</td></tr>
                    <tr><th class="text-muted">Offerings</th><td>{{ $partner->offerings_count }}</td></tr>
                    <tr><th class="text-muted">Created</th><td>{{ $partner->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.partners.edit', $partner->id) }}" class="btn btn-outline-primary btn-sm flex-grow-1">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="{{ route('admin.partners.users.create', $partner->id) }}" class="btn btn-outline-success btn-sm flex-grow-1">
                    <i class="bi bi-person-plus me-1"></i>Add User
                </a>
            </div>
        </div>

        {{-- API Credentials --}}
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-10 border-warning d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-key-fill me-2 text-warning"></i>API Credentials</h6>
                <span class="badge bg-warning text-dark small">Share securely</span>
            </div>
            <div class="card-body">
                @if($partner->client_id)

                    {{-- Client ID --}}
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold mb-1">Client ID</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace bg-light"
                                   id="clientId" value="{{ $partner->client_id }}" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyField('clientId')" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Send this as <code>client_id</code> in token requests.</small>
                    </div>

                    {{-- Client Secret --}}
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold mb-1">Client Secret</label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control font-monospace bg-light"
                                   id="clientSecret" value="{{ $partner->client_secret }}" readonly>
                            <button class="btn btn-outline-secondary" onclick="toggleSecret()" title="Show/Hide" id="toggleBtn">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="copyField('clientSecret')" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Send this as <code>client_secret</code> in token requests.</small>
                    </div>

                    {{-- Webhook Secret --}}
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold mb-1">Webhook Signing Secret</label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control font-monospace bg-light"
                                   id="webhookSecret" value="{{ $partner->webhook_secret }}" readonly>
                            <button class="btn btn-outline-secondary" onclick="toggleWebhook()" title="Show/Hide" id="webhookToggleBtn">
                                <i class="bi bi-eye" id="webhookEyeIcon"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="copyField('webhookSecret')" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Used to verify incoming webhook signatures.</small>
                    </div>

                    {{-- Token endpoint --}}
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold mb-1">Token Endpoint</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace bg-light"
                                   id="tokenUrl" value="{{ url('/api/partner/auth/token') }}" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyField('tokenUrl')" title="Copy">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Regenerate --}}
                    <hr class="my-3">
                    <form method="POST" action="{{ route('admin.partners.regenerate-credentials', $partner->id) }}"
                          onsubmit="return confirm('Regenerate credentials? The current client secret will stop working immediately.')">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-arrow-clockwise me-1"></i>Regenerate Credentials
                        </button>
                    </form>

                @else
                    <p class="text-muted small mb-3">No API credentials generated yet.</p>
                    <form method="POST" action="{{ route('admin.partners.regenerate-credentials', $partner->id) }}">
                        @csrf
                        <button class="btn btn-warning btn-sm w-100">
                            <i class="bi bi-key me-1"></i>Generate Credentials
                        </button>
                    </form>
                @endif
            </div>
        </div>

    </div>

    {{-- Right column: integration guide + recent cases --}}
    <div class="col-lg-8">

        {{-- Integration quick-start --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-code-slash me-2"></i>Partner Integration Quick-Start</h6></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Share this guide with the partner so they can start creating offerings and cases via the API.</p>

                <p class="fw-semibold small mb-1">Step 1 — Get a Bearer token</p>
                <pre class="bg-dark text-light rounded p-3 small mb-3"><code>POST {{ url('/api/partner/auth/token') }}
Content-Type: application/json

{
  "grant_type": "client_credentials",
  "client_id": "{{ $partner->client_id ?? '<client_id>' }}",
  "client_secret": "&lt;client_secret&gt;"
}</code></pre>

                <p class="fw-semibold small mb-1">Step 2 — Create an Offering</p>
                <pre class="bg-dark text-light rounded p-3 small mb-3"><code>POST {{ url('/api/partner/offerings') }}
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "name": "Semaglutide 0.5mg Weekly",
  "type": "compound",
  "price": 299.00,
  "pharmacy_type": "boothwyn",
  "available_states": ["CA", "NY", "TX"],
  "is_active": true
}</code></pre>

                <p class="fw-semibold small mb-1">Step 3 — Create a Patient</p>
                <pre class="bg-dark text-light rounded p-3 small mb-3"><code>POST {{ url('/api/partner/patients') }}
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "state": "CA",
  "external_id": "your-system-id-123"
}</code></pre>

                <p class="fw-semibold small mb-1">Step 4 — Open a Case</p>
                <pre class="bg-dark text-light rounded p-3 small mb-0"><code>POST {{ url('/api/partner/cases') }}
Authorization: Bearer &lt;access_token&gt;
Content-Type: application/json

{
  "patient_id": "&lt;patient_uuid&gt;",
  "offerings": [{ "offering_id": "&lt;offering_uuid&gt;", "quantity": 1 }],
  "questions": [{ "question": "Any allergies?", "answer": "None" }]
}</code></pre>
            </div>
        </div>

        {{-- Recent Cases --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent Cases</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Patient</th><th>Status</th><th>Created</th></tr>
                        </thead>
                        <tbody>
                            @forelse($partner->cases()->with('patient')->latest()->take(15)->get() as $case)
                            <tr>
                                <td>{{ $case->patient->full_name ?? '—' }}</td>
                                <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                                <td><small class="text-muted">{{ $case->created_at->diffForHumans() }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No cases yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function copyField(id) {
    const el = document.getElementById(id);
    const val = el.value;
    navigator.clipboard.writeText(val).then(() => {
        const btn = el.nextElementSibling?.tagName === 'BUTTON'
            ? el.nextElementSibling
            : el.parentElement.querySelector('button:last-child');
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check-lg text-success';
        setTimeout(() => icon.className = 'bi bi-clipboard', 1500);
    });
}

function toggleSecret() {
    const el = document.getElementById('clientSecret');
    const icon = document.getElementById('eyeIcon');
    if (el.type === 'password') {
        el.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        el.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function toggleWebhook() {
    const el = document.getElementById('webhookSecret');
    const icon = document.getElementById('webhookEyeIcon');
    if (el.type === 'password') {
        el.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        el.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
@endsection
