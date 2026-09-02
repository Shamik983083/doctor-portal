@extends('layouts.admin')

@section('title', "Edit Sub-Storefront — {$subStorefront->name}")
@section('page-title', "Edit Sub-Storefront: {$subStorefront->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.partners.sub-storefronts.index', $partner->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Sub-Storefronts
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.partners.sub-storefronts.update', [$partner->id, $subStorefront->id]) }}">
            @csrf
            @method('PUT')

            {{-- Identity (read-only) --}}
            <div class="card mb-3 border-secondary">
                <div class="card-header bg-secondary bg-opacity-10">
                    <h6 class="mb-0 text-secondary"><i class="bi bi-fingerprint me-2"></i>Identity (read-only)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label small text-muted fw-semibold mb-1">UUID</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace bg-light"
                                       id="sfUuid" value="{{ $subStorefront->uuid }}" readonly>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="copyValue('sfUuid')" title="Copy">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <div class="form-text">Send as <code>sub_storefront_id</code> in API payloads.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small text-muted fw-semibold mb-1">Slug</label>
                            <input type="text" class="form-control form-control-sm bg-light font-monospace"
                                   value="{{ $subStorefront->slug }}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Basic info --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Basic Information</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $subStorefront->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="active" @selected(old('status', $subStorefront->status) === 'active')>Active</option>
                            <option value="suspended" @selected(old('status', $subStorefront->status) === 'suspended')>Suspended</option>
                            <option value="inactive" @selected(old('status', $subStorefront->status) === 'inactive')>Inactive</option>
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Healthie credentials --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>Healthie Integration</h6>
                    @if($subStorefront->healthie_organization_id)
                        <span class="badge bg-success small"><i class="bi bi-check-circle-fill me-1"></i>Org provisioned</span>
                    @else
                        <span class="badge bg-warning text-dark small"><i class="bi bi-exclamation-circle me-1"></i>Not provisioned</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Healthie API Key</label>
                        <input type="password" name="healthie_api_key"
                               class="form-control font-monospace @error('healthie_api_key') is-invalid @enderror"
                               value="{{ old('healthie_api_key') }}"
                               placeholder="Leave blank to keep existing value">
                        @error('healthie_api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Stored encrypted. Leave blank to keep the current key.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Healthie Endpoint</label>
                        <input type="url" name="healthie_endpoint"
                               class="form-control @error('healthie_endpoint') is-invalid @enderror"
                               value="{{ old('healthie_endpoint', $subStorefront->healthie_endpoint) }}"
                               placeholder="https://staging-api.gethealthie.com/graphql">
                        @error('healthie_endpoint') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Authorization Shard</label>
                        <input type="text" name="healthie_authorization_shard"
                               class="form-control font-monospace @error('healthie_authorization_shard') is-invalid @enderror"
                               value="{{ old('healthie_authorization_shard', $subStorefront->healthie_authorization_shard) }}">
                        @error('healthie_authorization_shard') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Organization ID</label>
                        <input type="text" name="healthie_organization_id"
                               class="form-control font-monospace @error('healthie_organization_id') is-invalid @enderror"
                               value="{{ old('healthie_organization_id', $subStorefront->healthie_organization_id) }}">
                        @error('healthie_organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Returned by Healthie after sub-org creation. Leave blank to auto-provision on save.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Default Provider ID</label>
                            <input type="text" name="healthie_default_provider_id"
                                   class="form-control font-monospace @error('healthie_default_provider_id') is-invalid @enderror"
                                   value="{{ old('healthie_default_provider_id', $subStorefront->healthie_default_provider_id) }}">
                            @error('healthie_default_provider_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Note Form ID</label>
                            <input type="text" name="healthie_note_form_id"
                                   class="form-control font-monospace @error('healthie_note_form_id') is-invalid @enderror"
                                   value="{{ old('healthie_note_form_id', $subStorefront->healthie_note_form_id) }}">
                            @error('healthie_note_form_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Group ID</label>
                        <input type="text" name="healthie_default_group_id"
                               class="form-control font-monospace @error('healthie_default_group_id') is-invalid @enderror"
                               value="{{ old('healthie_default_group_id', $subStorefront->healthie_default_group_id) }}">
                        @error('healthie_default_group_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="healthie_is_enabled"
                                       id="healthie_is_enabled" value="1"
                                       @checked(old('healthie_is_enabled', $subStorefront->healthie_is_enabled))>
                                <label class="form-check-label fw-semibold" for="healthie_is_enabled">
                                    Healthie Enabled
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="healthie_sandbox_validated"
                                       id="healthie_sandbox_validated" value="1"
                                       @checked(old('healthie_sandbox_validated', $subStorefront->healthie_sandbox_validated))>
                                <label class="form-check-label fw-semibold" for="healthie_sandbox_validated">
                                    Sandbox Validated
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
                <a href="{{ route('admin.partners.sub-storefronts.index', $partner->id) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        {{-- Clinician mapping status --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Clinician Provisioning</h6>
                <span class="badge bg-secondary">{{ $clinicianMappings->count() }}</span>
            </div>
            @if($clinicianMappings->isEmpty())
                <div class="card-body text-muted small text-center py-4">
                    <i class="bi bi-person-x fs-2 d-block mb-2 opacity-25"></i>
                    No clinicians provisioned yet.
                    @if(!$subStorefront->healthie_organization_id)
                        <div class="mt-2">Provision the Healthie sub-org first.</div>
                    @endif
                </div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($clinicianMappings as $mapping)
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small">{{ $mapping->clinician->user->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.76rem">{{ $mapping->clinician->user->email ?? '' }}</div>
                            </div>
                            <div class="text-end">
                                @if($mapping->status === 'synced')
                                    <span class="badge bg-success">Synced</span>
                                @elseif($mapping->status === 'failed')
                                    <span class="badge bg-danger" title="{{ $mapping->last_error }}">Failed</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($mapping->status) }}</span>
                                @endif
                                @if($mapping->healthie_user_id)
                                    <div class="text-muted" style="font-size:.7rem">ID: {{ $mapping->healthie_user_id }}</div>
                                @endif
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Danger zone --}}
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 border-danger">
                <h6 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Deleting this sub-storefront removes it from the portal but <strong>does not delete the Healthie sub-org</strong>.
                    Remove it from Healthie manually after deletion.
                </p>
                <form method="POST" action="{{ route('admin.partners.sub-storefronts.destroy', [$partner->id, $subStorefront->id]) }}"
                      onsubmit="return confirm('Delete \'{{ addslashes($subStorefront->name) }}\'? This cannot be undone. Remove the Healthie sub-org manually.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-trash me-1"></i>Delete Sub-Storefront
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function copyValue(id) {
    const el = document.getElementById(id);
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = el.nextElementSibling;
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check-lg text-success';
        setTimeout(() => icon.className = 'bi bi-clipboard', 1500);
    });
}
</script>
@endsection
