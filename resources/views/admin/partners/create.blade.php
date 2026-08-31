@extends('layouts.admin')

@section('title', 'New Partner')
@section('page-title', 'New Partner')

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
        --color-code-bg:     #f0f0f5;
        --radius-card:       12px;
        --radius-input:      8px;
        --shadow-card:       0 1px 4px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
    }

    /* ---------- Layout ---------- */
    .ep-wrap { max-width: 900px; font-family: var(--font-ui); }
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
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .ep-icon-blue   { background: #e8f2fd; color: var(--color-accent); }
    .ep-icon-purple { background: #f0ebfa; color: #7b4fc4; }
    .ep-card-header h6 {
        margin: 0;
        font-size: .9375rem; font-weight: 600;
        color: var(--color-text); letter-spacing: -.01em;
    }
    .ep-card-body { padding: 1.375rem; }

    /* ---------- Form ---------- */
    .ep-label {
        display: block;
        font-size: .8125rem; font-weight: 600;
        color: var(--color-text-2);
        margin-bottom: .3rem; letter-spacing: .01em;
    }
    .ep-label .ep-optional { font-weight: 400; color: var(--color-text-muted); margin-left: .25rem; }
    .ep-hint {
        font-size: .75rem; color: var(--color-text-muted);
        margin-top: .25rem; line-height: 1.4;
    }
    .ep-hint code {
        background: var(--color-code-bg);
        border-radius: 4px; padding: 1px 4px; font-size: .7rem;
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

    /* ---------- Info callout ---------- */
    .ep-callout {
        display: flex; gap: .625rem; align-items: flex-start;
        padding: .75rem 1rem;
        background: #f0f6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        font-size: .8125rem;
        color: #1e40af;
        line-height: 1.5;
    }
    .ep-callout i { flex-shrink: 0; margin-top: .05rem; }

    /* ---------- Divider ---------- */
    .ep-divider { height: 1px; background: var(--color-border-light); margin: 1.25rem 0; }

    /* ---------- Buttons ---------- */
    .btn-ep-primary {
        background: var(--color-accent); color: #fff; border: none;
        border-radius: 8px; padding: .5rem 1.125rem;
        font-size: .875rem; font-weight: 600; font-family: var(--font-ui);
        cursor: pointer; transition: background .15s, box-shadow .15s, transform .05s;
        display: inline-flex; align-items: center; gap: .375rem;
    }
    .btn-ep-primary:hover { background: var(--color-accent-hover); box-shadow: 0 2px 8px rgba(0,113,227,.25); }
    .btn-ep-primary:active { transform: scale(.98); }
    .btn-ep-secondary {
        background: transparent; color: var(--color-text-2);
        border: 1px solid var(--color-border); border-radius: 8px;
        padding: .5rem 1.125rem; font-size: .875rem; font-weight: 500;
        font-family: var(--font-ui); cursor: pointer;
        transition: background .15s, border-color .15s;
        display: inline-flex; align-items: center; gap: .375rem;
        text-decoration: none;
    }
    .btn-ep-secondary:hover { background: var(--color-surface-alt); border-color: #aeaeb2; }
</style>
@endpush

@section('content')
<div class="ep-wrap">

    <form method="POST" action="{{ route('admin.partners.store') }}">
    @csrf

    {{-- ── Company Info ─────────────────────────────────────── --}}
    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-blue"><i class="bi bi-building"></i></div>
            <h6>Company Information</h6>
        </div>
        <div class="ep-card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Phone <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Website <span class="ep-optional">(optional)</span></label>
                    <input type="url" name="website" class="form-control"
                           value="{{ old('website') }}" placeholder="https://…">
                </div>
            </div>
            <div class="mb-0">
                <label class="ep-label">Description <span class="ep-optional">(optional)</span></label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ── Healthie EHR ─────────────────────────────────────── --}}
    {{--
        Healthie values are captured at creation so a new storefront is configured to
        push from the moment it exists. Each storefront uses its OWN credential, which
        is what keeps records from crossing between partners.
        See docs/integrations/HEALTHIE-SETUP.md.
    --}}
    <div class="ep-card">
        <div class="ep-card-header">
            <div class="ep-icon ep-icon-purple"><i class="bi bi-activity"></i></div>
            <h6>Healthie EHR</h6>
        </div>
        <div class="ep-card-body">
            <p class="ep-hint mb-4">
                Each partner pushes to Healthie with its own credential, so records can never cross
                between storefronts. Leave any field blank to fill in later.
                A new partner always starts <strong>disabled</strong> for push — enable it on the
                edit screen once its sandbox has been validated.
            </p>

            {{-- Credentials --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">API Key <span class="ep-optional">(optional)</span></label>
                    <input type="password" name="healthie_api_key" class="form-control"
                           autocomplete="new-password" value="{{ old('healthie_api_key') }}">
                    <p class="ep-hint">Stored encrypted. Sent as <code>Authorization: Basic &lt;key&gt;</code>.</p>
                </div>
                <div class="col-md-6">
                    <label class="ep-label">GraphQL Endpoint <span class="ep-optional">(optional)</span></label>
                    <input type="url" name="healthie_endpoint" class="form-control"
                           value="{{ old('healthie_endpoint') }}"
                           placeholder="https://staging-api.gethealthie.com/graphql">
                    <p class="ep-hint">Staging and production are different hosts.</p>
                </div>
            </div>

            {{-- Org / Provider --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Organization ID <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="healthie_organization_id" class="form-control"
                           value="{{ old('healthie_organization_id') }}">
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Default Provider ID <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="healthie_default_provider_id" class="form-control"
                           value="{{ old('healthie_default_provider_id') }}">
                    <p class="ep-hint">Healthie user the chart note is attributed to.</p>
                </div>
            </div>

            {{-- Form ID + Group ID --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="ep-label">Note Form ID <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="healthie_note_form_id" class="form-control"
                           value="{{ old('healthie_note_form_id') }}" placeholder="e.g. 2357150">
                    <p class="ep-hint">The "Free Text" charting template ID from Healthie.</p>
                </div>
                <div class="col-md-6">
                    <label class="ep-label">Default Group ID <span class="ep-optional">(optional)</span></label>
                    <input type="text" name="healthie_default_group_id" class="form-control"
                           value="{{ old('healthie_default_group_id') }}" placeholder="e.g. 12345">
                    <p class="ep-hint">Patient group new clients are added to. Leave blank if none configured.</p>
                </div>
            </div>

            {{-- Auth shard --}}
            <div class="mb-4" style="max-width:360px;">
                <label class="ep-label">Authorization Shard <span class="ep-optional">(optional)</span></label>
                <input type="text" name="healthie_authorization_shard" class="form-control"
                       value="{{ old('healthie_authorization_shard') }}">
                <p class="ep-hint">Only required if this Healthie account's data resides in a shard.</p>
            </div>

            <div class="ep-callout">
                <i class="bi bi-lightbulb"></i>
                <span>
                    <strong>Not sure of the Form or Group IDs?</strong>
                    Save the API key and endpoint first, then open this partner's
                    <strong>Edit</strong> screen — it has a
                    <strong>Look up Form &amp; Group IDs from Healthie</strong> button
                    that fetches the available options directly from your account.
                </span>
            </div>
        </div>
    </div>

    {{-- ── Action bar ──────────────────────────────────────────── --}}
    <div class="d-flex align-items-center gap-2 pb-4">
        <button type="submit" class="btn-ep-primary">
            <i class="bi bi-plus-lg"></i> Create Partner &amp; Generate API Credentials
        </button>
        <a href="{{ route('admin.partners.index') }}" class="btn-ep-secondary">
            Cancel
        </a>
    </div>

    </form>
</div>
@endsection
