@extends('layouts.admin')

@section('title', 'Edit Partner')
@section('page-title', "Edit: {$partner->name}")

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header"><h6 class="mb-0">Edit Partner</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.partners.update', $partner->id) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Company Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $partner->name) }}" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $partner->phone) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Website</label>
                    <input type="url" name="website" class="form-control" value="{{ old('website', $partner->website) }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    @foreach(['active','suspended','inactive'] as $s)
                    <option value="{{ $s }}" {{ $partner->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $partner->description) }}</textarea>
            </div>
            {{-- Healthie. See docs/integrations/HEALTHIE-SETUP.md --}}
            @php($healthie = $partner->healthieSettings)
            <hr class="my-4">
            <h6 class="fw-semibold mb-1">Healthie (EHR)</h6>

            @if($healthie && $healthie->missingValues())
                <div class="alert alert-warning py-2 small">
                    Not fully configured. Missing: <strong>{{ implode(', ', $healthie->missingValues()) }}</strong>.
                    Records are built and stored for preview, but nothing is pushed.
                </div>
            @endif

            <p class="text-muted small mb-3">
                This company pushes with its own credential, so its records can never cross into
                another storefront's. Leave the API key blank to keep the stored one.
            </p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">API Key</label>
                    <input type="password" name="healthie_api_key" class="form-control" autocomplete="new-password"
                           placeholder="{{ $healthie && $healthie->api_key ? 'Stored. Leave blank to keep it.' : 'Not set' }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">GraphQL Endpoint</label>
                    <input type="url" name="healthie_endpoint" class="form-control" value="{{ old('healthie_endpoint', $healthie->endpoint ?? '') }}">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Organization ID</label>
                    <input type="text" name="healthie_organization_id" class="form-control" value="{{ old('healthie_organization_id', $healthie->organization_id ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Default Provider ID</label>
                    <input type="text" name="healthie_default_provider_id" class="form-control" value="{{ old('healthie_default_provider_id', $healthie->default_provider_id ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Note Form ID</label>
                    <input type="text" name="healthie_note_form_id" class="form-control" value="{{ old('healthie_note_form_id', $healthie->note_form_id ?? '') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Authorization Shard <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="healthie_authorization_shard" class="form-control" value="{{ old('healthie_authorization_shard', $healthie->authorization_shard ?? '') }}">
            </div>

            {{-- Deliberately separate from entering the values: pasting credentials must never
                 switch a storefront live by itself. --}}
            <div class="form-check mb-2">
                <input type="hidden" name="healthie_sandbox_validated" value="0">
                <input class="form-check-input" type="checkbox" id="healthie_sandbox_validated" name="healthie_sandbox_validated" value="1"
                       {{ old('healthie_sandbox_validated', $healthie->sandbox_validated ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="healthie_sandbox_validated">
                    Sandbox validated for this company
                </label>
            </div>
            <div class="form-check mb-4">
                <input type="hidden" name="healthie_is_enabled" value="0">
                <input class="form-check-input" type="checkbox" id="healthie_is_enabled" name="healthie_is_enabled" value="1"
                       {{ old('healthie_is_enabled', $healthie->is_enabled ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="healthie_is_enabled">
                    Push records to Healthie for this company
                </label>
                <div class="form-text">
                    Both boxes, plus the platform-level flags, are required before anything is sent.
                    Verify the two-storefront case before enabling.
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
