@extends('layouts.admin')

@section('title', 'New Partner')
@section('page-title', 'New Partner')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header"><h6 class="mb-0">Partner Details</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.partners.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Website</label>
                    <input type="url" name="website" class="form-control" value="{{ old('website') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            {{--
                Healthie values, captured here so a new company is configured to push from the
                moment it exists. Each company pushes with its OWN credential: that is what keeps
                one storefront's records out of another's. See docs/integrations/HEALTHIE-SETUP.md.
            --}}
            <hr class="my-4">
            <h6 class="fw-semibold mb-1">Healthie (EHR)</h6>
            <p class="text-muted small mb-3">
                Each company pushes to Healthie with its own credential, so records can never cross
                between storefronts. Leave blank to fill in later. A new company always starts
                disabled for push: enable it on the edit screen once its sandbox has been validated.
            </p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">API Key</label>
                    <input type="password" name="healthie_api_key" class="form-control" autocomplete="new-password" value="{{ old('healthie_api_key') }}">
                    <div class="form-text">Stored encrypted. Sent as <code>Authorization: Basic &lt;key&gt;</code>.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">GraphQL Endpoint</label>
                    <input type="url" name="healthie_endpoint" class="form-control" value="{{ old('healthie_endpoint') }}">
                    <div class="form-text">Staging and production are different hosts.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Organization ID</label>
                    <input type="text" name="healthie_organization_id" class="form-control" value="{{ old('healthie_organization_id') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Default Provider ID</label>
                    <input type="text" name="healthie_default_provider_id" class="form-control" value="{{ old('healthie_default_provider_id') }}">
                    <div class="form-text">Healthie user the note is attributed to.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Note Form ID</label>
                    <input type="text" name="healthie_note_form_id" class="form-control" value="{{ old('healthie_note_form_id') }}">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Default Group ID <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="healthie_default_group_id" class="form-control" value="{{ old('healthie_default_group_id') }}">
                    <div class="form-text">Healthie patient group new clients are added to. Leave blank if no groups are configured.</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Authorization Shard <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="healthie_authorization_shard" class="form-control" value="{{ old('healthie_authorization_shard') }}">
                <div class="form-text">Only required if this Healthie account's data resides in a shard.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Partner & Generate API Credentials</button>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
