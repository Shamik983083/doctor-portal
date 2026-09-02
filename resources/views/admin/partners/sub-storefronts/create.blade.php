@extends('layouts.admin')

@section('title', "New Sub-Storefront — {$partner->name}")
@section('page-title', "New Sub-Storefront: {$partner->name}")

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.partners.sub-storefronts.index', $partner->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Sub-Storefronts
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.partners.sub-storefronts.store', $partner->id) }}">
            @csrf

            {{-- Basic info --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Basic Information</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="e.g. Amerilean" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">The slug is generated automatically from the name.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Healthie credentials --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>Healthie Integration</h6>
                    <span class="badge bg-info text-dark small">Auto-provisioned on save</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-3 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Leave <strong>Healthie API Key</strong> and <strong>Organization ID</strong> blank to auto-create the sub-org
                        using the parent partner's API key. Fill them in to link an existing Healthie sub-org.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Healthie API Key</label>
                        <input type="password" name="healthie_api_key"
                               class="form-control font-monospace @error('healthie_api_key') is-invalid @enderror"
                               value="{{ old('healthie_api_key') }}"
                               placeholder="Leave blank to auto-provision">
                        @error('healthie_api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Healthie Endpoint</label>
                        <input type="url" name="healthie_endpoint"
                               class="form-control @error('healthie_endpoint') is-invalid @enderror"
                               value="{{ old('healthie_endpoint') }}"
                               placeholder="https://staging-api.gethealthie.com/graphql">
                        @error('healthie_endpoint') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Authorization Shard</label>
                        <input type="text" name="healthie_authorization_shard"
                               class="form-control font-monospace @error('healthie_authorization_shard') is-invalid @enderror"
                               value="{{ old('healthie_authorization_shard') }}">
                        @error('healthie_authorization_shard') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Organization ID</label>
                        <input type="text" name="healthie_organization_id"
                               class="form-control font-monospace @error('healthie_organization_id') is-invalid @enderror"
                               value="{{ old('healthie_organization_id') }}"
                               placeholder="Returned by Healthie after sub-org creation">
                        @error('healthie_organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Default Provider ID</label>
                            <input type="text" name="healthie_default_provider_id"
                                   class="form-control font-monospace @error('healthie_default_provider_id') is-invalid @enderror"
                                   value="{{ old('healthie_default_provider_id') }}">
                            @error('healthie_default_provider_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Note Form ID</label>
                            <input type="text" name="healthie_note_form_id"
                                   class="form-control font-monospace @error('healthie_note_form_id') is-invalid @enderror"
                                   value="{{ old('healthie_note_form_id') }}">
                            @error('healthie_note_form_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Group ID</label>
                        <input type="text" name="healthie_default_group_id"
                               class="form-control font-monospace @error('healthie_default_group_id') is-invalid @enderror"
                               value="{{ old('healthie_default_group_id') }}">
                        @error('healthie_default_group_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="healthie_is_enabled"
                                       id="healthie_is_enabled" value="1"
                                       @checked(old('healthie_is_enabled'))>
                                <label class="form-check-label fw-semibold" for="healthie_is_enabled">
                                    Healthie Enabled
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="healthie_sandbox_validated"
                                       id="healthie_sandbox_validated" value="1"
                                       @checked(old('healthie_sandbox_validated'))>
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
                    <i class="bi bi-floppy me-1"></i>Create Sub-Storefront
                </button>
                <a href="{{ route('admin.partners.sub-storefronts.index', $partner->id) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-info">
            <div class="card-header bg-info bg-opacity-10 border-info">
                <h6 class="mb-0 text-info"><i class="bi bi-info-circle me-2"></i>How it works</h6>
            </div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-0">
                    <li class="mb-2">A UUID is automatically assigned to every sub-storefront. Tenants send it as <code>sub_storefront_id</code> in case payloads.</li>
                    <li class="mb-2">On save, if no Healthie credentials are provided, the system attempts to auto-create a Healthie sub-org under the parent partner using the partner's API key.</li>
                    <li class="mb-2">Once the sub-org is provisioned, all active global clinicians are automatically added to it.</li>
                    <li>When a doctor approves a case tied to this sub-storefront, the EHR push uses <em>this sub-storefront's</em> Healthie credentials — records never cross sub-orgs.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
