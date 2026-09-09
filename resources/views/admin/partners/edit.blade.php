@extends('layouts.admin')

@section('title', 'Edit Partner')
@section('page-title', "Edit: {$partner->name}")

@push('head')
<style>
    /* ---------- Design tokens ---------- */
    :root {
        --font-ui: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        --color-bg:          #f5f5f7;
        --color-surface:     #ffffff;
        --color-surface-alt: #f9f9fb;
        --color-border:      #d1d1d6;
        --color-border-light:#e5e5ea;
        --color-text:        #1d1d1f;
        --color-text-2:      #3a3a3c;
        --color-text-muted:  #6e6e73;
        --color-accent:      #0071e3;
        --color-accent-hover:#0077ed;
        --color-success:     #28a745;
        --color-warning:     #f59e0b;
        --color-danger:      #dc3545;
        --color-code-bg:     #f0f0f5;
        --radius-card:       12px;
        --radius-input:      8px;
        --shadow-card:       0 1px 4px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
        --shadow-card-hover: 0 2px 8px rgba(0,0,0,.08), 0 8px 24px rgba(0,0,0,.06);
    }

    /* ---------- Layout ---------- */
    .ep-wrap {
        max-width: 900px;
        font-family: var(--font-ui);
    }
    .ep-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border-light);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .ep-card-header {
        display: flex;
        align-items: center;
        gap: .625rem;
        padding: 1rem 1.375rem;
        border-bottom: 1px solid var(--color-border-light);
        background: var(--color-surface-alt);
    }
    .ep-card-header .ep-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .ep-icon-blue  { background: #e8f2fd; color: var(--color-accent); }
    .ep-icon-green { background: #e6f7ed; color: #1a8f3a; }
    .ep-icon-purple{ background: #f0ebfa; color: #7b4fc4; }
    .ep-card-header h6 {
        margin: 0;
        font-size: .9375rem;
        font-weight: 600;
        color: var(--color-text);
        letter-spacing: -.01em;
    }
    .ep-card-header .ep-header-desc {
        margin: 0 0 0 auto;
        font-size: .75rem;
        color: var(--color-text-muted);
    }
    .ep-card-body { padding: 1.375rem; }

    /* ---------- Form controls ---------- */
    .ep-label {
        display: block;
        font-size: .8125rem;
        font-weight: 600;
        color: var(--color-text-2);
        margin-bottom: .3rem;
        letter-spacing: .01em;
    }
    .ep-label .ep-optional {
        font-weight: 400;
        color: var(--color-text-muted);
        margin-left: .25rem;
    }
    .ep-hint {
        font-size: .75rem;
        color: var(--color-text-muted);
        margin-top: .25rem;
        line-height: 1.4;
    }
    .ep-hint code {
        background: var(--color-code-bg);
        border-radius: 4px;
        padding: 1px 4px;
        font-size: .7rem;
    }
    .form-control, .form-select {
        border-radius: var(--radius-input) !important;
        border-color: var(--color-border) !important;
        font-size: .875rem !important;
        color: var(--color-text) !important;
        background: var(--color-surface) !important;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.04) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--color-accent) !important;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.04), 0 0 0 3px rgba(0,113,227,.12) !important;
        outline: none !important;
    }
    .form-check-input:checked {
        background-color: var(--color-accent);
        border-color: var(--color-accent);
    }
    .form-check-input:focus {
        box-shadow: 0 0 0 3px rgba(0,113,227,.15);
        border-color: var(--color-accent);
    }
    .form-check-label { font-size: .875rem; color: var(--color-text-2); }

    /* ---------- Status badge ---------- */
    .ep-status {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .2rem .6rem;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .ep-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .ep-status-ok     { background: #e6f7ed; color: #1a8f3a; }
    .ep-status-ok .ep-status-dot { background: #1a8f3a; }
    .ep-status-warn   { background: #fef3cd; color: #92650a; }
    .ep-status-warn .ep-status-dot { background: #f59e0b; }
    .ep-status-inactive { background: #f0f0f5; color: #6e6e73; }
    .ep-status-inactive .ep-status-dot { background: #aeaeb2; }

    /* ---------- Lookup panel ---------- */
    #healthie-lookup-panel {
        display: none;
        margin-top: 1rem;
        border: 1px solid var(--color-border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--color-surface);
    }
    .lookup-tabs {
        display: flex;
        border-bottom: 1px solid var(--color-border-light);
        background: var(--color-surface-alt);
    }
    .lookup-tab {
        padding: .625rem 1rem;
        font-size: .8125rem;
        font-weight: 500;
        color: var(--color-text-muted);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: color .15s, border-color .15s;
        user-select: none;
    }
    .lookup-tab.active {
        color: var(--color-accent);
        border-bottom-color: var(--color-accent);
    }
    .lookup-tab-pane { display: none; }
    .lookup-tab-pane.active { display: block; }
    .lookup-list {
        max-height: 260px;
        overflow-y: auto;
        padding: .375rem;
    }
    .lookup-row {
        display: flex;
        align-items: center;
        gap: .625rem;
        padding: .5rem .625rem;
        border-radius: 7px;
        transition: background .1s;
    }
    .lookup-row:hover { background: var(--color-surface-alt); }
    .lookup-row-id {
        font-size: .75rem;
        font-variant-numeric: tabular-nums;
        color: var(--color-text-muted);
        background: var(--color-code-bg);
        padding: 2px 6px;
        border-radius: 5px;
        white-space: nowrap;
        font-family: 'SF Mono', 'Cascadia Mono', 'Fira Mono', monospace;
    }
    .lookup-row-name {
        flex: 1;
        font-size: .8125rem;
        color: var(--color-text-2);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .lookup-use-btn {
        padding: .2rem .65rem;
        font-size: .75rem;
        font-weight: 600;
        color: var(--color-accent);
        background: rgba(0,113,227,.08);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background .15s, color .15s;
        white-space: nowrap;
    }
    .lookup-use-btn:hover {
        background: var(--color-accent);
        color: #fff;
    }
    .lookup-empty {
        padding: 1.5rem;
        text-align: center;
        font-size: .8125rem;
        color: var(--color-text-muted);
    }
    .lookup-error {
        padding: .75rem 1rem;
        margin: .5rem;
        border-radius: 8px;
        background: #fff4f4;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: .8125rem;
    }

    /* ---------- Buttons ---------- */
    .btn-ep-primary {
        background: var(--color-accent);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: .5rem 1.125rem;
        font-size: .875rem;
        font-weight: 600;
        font-family: var(--font-ui);
        cursor: pointer;
        transition: background .15s, box-shadow .15s, transform .05s;
        display: inline-flex;
        align-items: center;
        gap: .375rem;
    }
    .btn-ep-primary:hover {
        background: var(--color-accent-hover);
        box-shadow: 0 2px 8px rgba(0,113,227,.25);
    }
    .btn-ep-primary:active { transform: scale(.98); }
    .btn-ep-secondary {
        background: transparent;
        color: var(--color-text-2);
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: .5rem 1.125rem;
        font-size: .875rem;
        font-weight: 500;
        font-family: var(--font-ui);
        cursor: pointer;
        transition: background .15s, border-color .15s;
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        text-decoration: none;
    }
    .btn-ep-secondary:hover { background: var(--color-surface-alt); border-color: #aeaeb2; }
    .btn-ep-lookup {
        background: transparent;
        color: var(--color-accent);
        border: 1px solid rgba(0,113,227,.35);
        border-radius: 8px;
        padding: .4375rem .875rem;
        font-size: .8125rem;
        font-weight: 600;
        font-family: var(--font-ui);
        cursor: pointer;
        transition: background .15s, border-color .15s, box-shadow .15s;
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        white-space: nowrap;
    }
    .btn-ep-lookup:hover {
        background: rgba(0,113,227,.06);
        border-color: var(--color-accent);
        box-shadow: 0 0 0 3px rgba(0,113,227,.08);
    }
    .btn-ep-lookup:disabled {
        opacity: .55;
        cursor: default;
        pointer-events: none;
    }

    /* ---------- Section divider ---------- */
    .ep-divider {
        height: 1px;
        background: var(--color-border-light);
        margin: 1.25rem 0;
    }

    /* ---------- Spinner ---------- */
    .ep-spin {
        width: 14px; height: 14px;
        border: 2px solid rgba(0,113,227,.25);
        border-top-color: var(--color-accent);
        border-radius: 50%;
        animation: ep-spin .6s linear infinite;
        display: inline-block;
    }
    @keyframes ep-spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')
@php
    $healthie = $partner->healthieSettings;
    $ehrStatus = 'inactive';
    $ehrStatusLabel = 'Not configured';
    if ($healthie) {
        if ($healthie->isPushable()) {
            $ehrStatus = 'ok';
            $ehrStatusLabel = 'Active';
        } elseif ($healthie->missingValues() === [] && $healthie->sandbox_validated) {
            $ehrStatus = 'warn';
            $ehrStatusLabel = 'Push disabled';
        } elseif (!empty($healthie->api_key)) {
            $ehrStatus = 'warn';
            $ehrStatusLabel = 'Incomplete';
        }
    }
@endphp

<div class="ep-wrap">

    {{-- ── Company Info ─────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('admin.partners.update', $partner->id) }}">
    @csrf @method('PUT')

    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-blue"><i class="bi bi-building"></i></div>
            <h6>Company Information</h6>
        </div>
        <div class="ep-card-body">
            <div class="mb-3">
                <label class="ep-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Phone <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $partner->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Website <span class="ep-optional">(optional)</span></label>
                    <input type="url" name="website" class="form-control" value="{{ old('website', $partner->website) }}" placeholder="https://…">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="ep-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active','suspended','inactive'] as $s)
                        <option value="{{ $s }}" {{ $partner->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-0">
                <label class="ep-label">Description <span class="ep-optional">(optional)</span></label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $partner->description) }}</textarea>
            </div>
        </div>
    </div>

    {{-- ── Collaborating Clinician ───────────────────────────────── --}}
    {{-- Hidden when partner has sub-storefronts: each sub-storefront manages its own default. --}}
    @if($partner->subStorefronts()->exists())
    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-green"><i class="bi bi-person-badge"></i></div>
            <h6>Collaborating Clinician Default</h6>
        </div>
        <div class="ep-card-body">
            <div class="alert alert-info py-2 mb-0 small">
                <i class="bi bi-info-circle me-1"></i>
                This partner has sub-storefronts. Each sub-storefront manages its own collaborating clinician default
                and clinician assignment pool. Set those on the
                <a href="{{ route('admin.partners.sub-storefronts.index', $partner->id) }}">Sub-Storefronts</a> page.
            </div>
        </div>
    </div>
    @else
    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-green"><i class="bi bi-person-badge"></i></div>
            <h6>Collaborating Clinician Default</h6>
        </div>
        <div class="ep-card-body">
            <p class="ep-hint mb-3">
                When a new case arrives from this storefront and the patient has no collaborating clinician set,
                this clinician is automatically assigned. Existing assignments are never overwritten.
            </p>
            <div style="max-width:360px;">
                <label class="ep-label" for="collaborating_clinician_id">Default Clinician</label>
                <select name="collaborating_clinician_id" id="collaborating_clinician_id"
                        class="form-select @error('collaborating_clinician_id') is-invalid @enderror">
                    <option value="">— None —</option>
                    @foreach($clinicians as $cl)
                    <option value="{{ $cl->id }}"
                        {{ (int) old('collaborating_clinician_id', $partner->collaborating_clinician_id) === $cl->id ? 'selected' : '' }}>
                        {{ $cl->full_name }}
                    </option>
                    @endforeach
                </select>
                @error('collaborating_clinician_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
    @endif

    {{-- ── Healthie EHR Settings ─────────────────────────────────── --}}
    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-purple"><i class="bi bi-activity"></i></div>
            <h6>Healthie EHR</h6>
            <span class="ep-header-desc">
                <span class="ep-status ep-status-{{ $ehrStatus }}">
                    <span class="ep-status-dot"></span>{{ $ehrStatusLabel }}
                </span>
            </span>
        </div>
        <div class="ep-card-body">

            @if($healthie && $healthie->missingValues())
            <div class="alert alert-warning py-2 small mb-4">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Missing required fields: <strong>{{ implode(', ', $healthie->missingValues()) }}</strong>.
                Records are stored for preview, but nothing is pushed.
            </div>
            @endif

            <p class="ep-hint mb-4">
                Each partner pushes with its own API key so records can never cross between storefronts.
                Leave the API key blank to keep the currently stored one.
            </p>

            {{-- Credentials row --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">API Key</label>
                    <input type="password" name="healthie_api_key" class="form-control"
                           autocomplete="new-password"
                           placeholder="{{ $healthie && $healthie->api_key ? 'Stored — leave blank to keep' : 'Not set' }}">
                    <p class="ep-hint">Stored encrypted. Sent as <code>Authorization: Basic &lt;key&gt;</code>.</p>
                </div>
                <div class="col-md-6">
                    <label class="ep-label">GraphQL Endpoint</label>
                    <input type="url" name="healthie_endpoint" class="form-control"
                           value="{{ old('healthie_endpoint', $healthie->endpoint ?? '') }}"
                           placeholder="https://staging-api.gethealthie.com/graphql">
                    <p class="ep-hint">Staging and production are different hosts.</p>
                </div>
            </div>

            {{-- Org / Provider --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Organization ID</label>
                    <input type="text" name="healthie_organization_id" class="form-control"
                           value="{{ old('healthie_organization_id', $healthie->organization_id ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Default Provider ID</label>
                    <input type="text" name="healthie_default_provider_id" class="form-control"
                           value="{{ old('healthie_default_provider_id', $healthie->default_provider_id ?? '') }}">
                    <p class="ep-hint">Healthie user the chart note is attributed to.</p>
                </div>
            </div>

            {{-- Form ID + Group ID with lookup --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Note Form ID</label>
                    <input type="text" name="healthie_note_form_id" id="healthie_note_form_id"
                           class="form-control"
                           value="{{ old('healthie_note_form_id', $healthie->note_form_id ?? '') }}"
                           placeholder="e.g. 2357150">
                    <p class="ep-hint">The "Free Text" charting template ID from Healthie.</p>
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Default Group ID <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="healthie_default_group_id" id="healthie_default_group_id"
                           class="form-control"
                           value="{{ old('healthie_default_group_id', $healthie->default_group_id ?? '') }}"
                           placeholder="e.g. 12345">
                    <p class="ep-hint">Patient group new clients are added to. Leave blank if none configured.</p>
                </div>
            </div>

            {{-- Lookup button --}}
            @if($healthie && $healthie->api_key && $healthie->endpoint)
            <div class="mb-4">
                <button type="button" class="btn-ep-lookup" id="lookup-btn"
                        onclick="healthieLookup()">
                    <i class="bi bi-cloud-download" id="lookup-icon"></i>
                    Look up Form &amp; Group IDs from Healthie
                </button>
                <p class="ep-hint mt-1">Fetches the available charting forms and patient groups from your stored credentials.</p>

                <div id="healthie-lookup-panel">
                    <div class="lookup-tabs">
                        <div class="lookup-tab active" onclick="switchTab('forms')">
                            <i class="bi bi-file-text me-1"></i>Forms
                            <span id="forms-count" class="badge rounded-pill bg-secondary ms-1" style="font-size:.65rem;display:none;"></span>
                        </div>
                        <div class="lookup-tab" onclick="switchTab('groups')">
                            <i class="bi bi-people me-1"></i>Groups
                            <span id="groups-count" class="badge rounded-pill bg-secondary ms-1" style="font-size:.65rem;display:none;"></span>
                        </div>
                    </div>
                    <div id="lookup-error" class="lookup-error" style="display:none;"></div>
                    <div class="lookup-tab-pane active" id="pane-forms">
                        <div class="lookup-list" id="list-forms"></div>
                    </div>
                    <div class="lookup-tab-pane" id="pane-groups">
                        <div class="lookup-list" id="list-groups"></div>
                    </div>
                </div>
            </div>
            @else
            <div class="mb-4">
                <p class="ep-hint">
                    <i class="bi bi-info-circle me-1"></i>
                    Save the API key and endpoint first, then the
                    <strong>Look up Form &amp; Group IDs</strong> button will appear here.
                </p>
            </div>
            @endif

            {{-- Auth shard --}}
            <div class="mb-4" style="max-width:360px;">
                <label class="ep-label">Authorization Shard <span class="ep-optional">(optional)</span></label>
                <input type="text" name="healthie_authorization_shard" class="form-control"
                       value="{{ old('healthie_authorization_shard', $healthie->authorization_shard ?? '') }}">
                <p class="ep-hint">Only required if this Healthie account's data resides in a shard.</p>
            </div>

            <div class="ep-divider"></div>

            {{-- Enable / validate switches --}}
            <p class="ep-hint mb-3 fw-semibold" style="color:var(--color-text-2);">
                Activation — both switches plus the platform-level flags are required before anything is pushed.
            </p>
            <div class="form-check mb-2">
                <input type="hidden" name="healthie_sandbox_validated" value="0">
                <input class="form-check-input" type="checkbox" id="healthie_sandbox_validated"
                       name="healthie_sandbox_validated" value="1"
                       {{ old('healthie_sandbox_validated', $healthie->sandbox_validated ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="healthie_sandbox_validated">
                    <span class="fw-semibold">Sandbox validated</span> for this partner
                </label>
                <p class="ep-hint">Check this only after verifying two-storefront isolation in staging.</p>
            </div>
            <div class="form-check mb-0">
                <input type="hidden" name="healthie_is_enabled" value="0">
                <input class="form-check-input" type="checkbox" id="healthie_is_enabled"
                       name="healthie_is_enabled" value="1"
                       {{ old('healthie_is_enabled', $healthie->is_enabled ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="healthie_is_enabled">
                    <span class="fw-semibold">Push records to Healthie</span> for this partner
                </label>
            </div>

        </div>
    </div>

    {{-- ── Action bar ──────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center gap-2 pb-4">
        <button type="submit" class="btn-ep-primary">
            <i class="bi bi-check2"></i> Save Changes
        </button>
        <a href="{{ route('admin.partners.index') }}" class="btn-ep-secondary">
            Cancel
        </a>
    </div>

    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    /* ── Tab switching ─────────────────────────────────── */
    window.switchTab = function (name) {
        document.querySelectorAll('.lookup-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.lookup-tab-pane').forEach(function (p) { p.classList.remove('active'); });
        const tabs = document.querySelectorAll('.lookup-tab');
        const idx  = name === 'forms' ? 0 : 1;
        tabs[idx].classList.add('active');
        document.getElementById('pane-' + name).classList.add('active');
    };

    /* ── Lookup ────────────────────────────────────────── */
    window.healthieLookup = function () {
        const btn   = document.getElementById('lookup-btn');
        const icon  = document.getElementById('lookup-icon');
        const panel = document.getElementById('healthie-lookup-panel');
        const errEl = document.getElementById('lookup-error');

        btn.disabled = true;
        icon.className = '';
        icon.innerHTML = '<span class="ep-spin"></span>';

        errEl.style.display = 'none';
        panel.style.display = 'block';

        const url = '{{ route('admin.partners.healthie-lookup', $partner->id) }}';

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    errEl.textContent = data.error;
                    errEl.style.display = 'block';
                    document.getElementById('list-forms').innerHTML  = '';
                    document.getElementById('list-groups').innerHTML = '';
                    return;
                }
                renderList('list-forms',  'forms-count',  data.forms,  'healthie_note_form_id');
                renderList('list-groups', 'groups-count', data.groups, 'healthie_default_group_id');
            })
            .catch(function (e) {
                errEl.textContent = 'Network error: ' + e.message;
                errEl.style.display = 'block';
            })
            .finally(function () {
                btn.disabled = false;
                icon.innerHTML = '';
                icon.className = 'bi bi-cloud-download';
            });
    };

    function renderList(listId, countId, items, targetField) {
        const el    = document.getElementById(listId);
        const badge = document.getElementById(countId);

        if (!items || items.length === 0) {
            el.innerHTML = '<div class="lookup-empty">No items found in this Healthie account.</div>';
            badge.style.display = 'none';
            return;
        }

        badge.textContent    = items.length;
        badge.style.display  = 'inline';

        el.innerHTML = items.map(function (item) {
            const safeName = escHtml(item.name || '(unnamed)');
            const safeId   = escHtml(String(item.id));
            const safeField = escHtml(targetField);
            return '<div class="lookup-row">'
                 + '<span class="lookup-row-id">' + safeId + '</span>'
                 + '<span class="lookup-row-name" title="' + safeName + '">' + safeName + '</span>'
                 + '<button type="button" class="lookup-use-btn" onclick="fillField(\'' + safeField + '\', \'' + safeId + '\')">Use</button>'
                 + '</div>';
        }).join('');
    }

    window.fillField = function (fieldId, value) {
        const el = document.getElementById(fieldId);
        if (el) {
            el.value = value;
            el.focus();
            el.classList.add('border-success');
            setTimeout(function () { el.classList.remove('border-success'); }, 1500);
        }
    };

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }


})();
</script>
@endsection
