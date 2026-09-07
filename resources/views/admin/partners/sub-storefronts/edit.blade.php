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

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>{{ session('info') }}
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

            {{-- Healthie Integration --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>Healthie Integration</h6>
                    @if($subStorefront->healthie_default_group_id)
                        <span class="badge bg-success small"><i class="bi bi-check-circle-fill me-1"></i>Group provisioned</span>
                    @else
                        <span class="badge bg-warning text-dark small"><i class="bi bi-exclamation-circle me-1"></i>No group set</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary small mb-3 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Credentials (API key, endpoint) come from the <strong>parent partner's</strong> Healthie settings.
                        Only the <strong>Group ID</strong> is specific to this sub-storefront — it controls which Healthie
                        group patients are assigned to.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Group ID</label>
                        <div class="input-group">
                            <input type="text" name="healthie_default_group_id"
                                   class="form-control font-monospace @error('healthie_default_group_id') is-invalid @enderror"
                                   value="{{ old('healthie_default_group_id', $subStorefront->healthie_default_group_id) }}"
                                   placeholder="Leave blank to auto-create on save">
                            @error('healthie_default_group_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">
                            Patients in this sub-storefront are assigned to this Healthie group.
                            Leave blank to auto-create a new group using the partner's API key.
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Default Provider ID <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="healthie_default_provider_id"
                                   class="form-control font-monospace @error('healthie_default_provider_id') is-invalid @enderror"
                                   value="{{ old('healthie_default_provider_id', $subStorefront->healthie_default_provider_id) }}">
                            @error('healthie_default_provider_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Override the partner's default provider for this sub-storefront.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Note Form ID <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="healthie_note_form_id"
                                   class="form-control font-monospace @error('healthie_note_form_id') is-invalid @enderror"
                                   value="{{ old('healthie_note_form_id', $subStorefront->healthie_note_form_id) }}">
                            @error('healthie_note_form_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Override the partner's note form for this sub-storefront.</div>
                        </div>
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

            {{-- ── Assigned Clinicians ────────────────────────────────── --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Assigned Clinicians</h6>
                </div>
                <div class="card-body p-0">
                    <p class="px-3 pt-3 pb-2 mb-0 small text-muted">
                        <strong>Global</strong> clinicians are always in the routing pool — their checkbox is locked.
                        Tick <strong>non-global</strong> clinicians to add them to the pool for this sub-storefront only.
                        Any clinician (global or assigned) can be set as the <strong>Collaborating Default</strong> — the secondary clinician auto-copied onto new patients.
                    </p>
                    @if($allClinicians->isEmpty())
                        <div class="text-center text-muted py-4 small">No active clinicians found.</div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:2.5rem" class="text-center" title="In routing pool for this sub-storefront">Pool</th>
                                    <th>Clinician</th>
                                    <th style="width:8rem" class="text-center">Collab Default</th>
                                </tr>
                            </thead>
                            <tbody id="clinician-assign-table">
                                @foreach($allClinicians as $cl)
                                @php
                                    $isGlobal   = (bool) $cl->is_global;
                                    $isAssigned = $isGlobal || isset($assignedClinicianIds[$cl->id]);
                                @endphp
                                <tr class="{{ $isGlobal ? 'table-primary bg-opacity-25' : '' }}">
                                    <td class="text-center">
                                        @if($isGlobal)
                                            <input type="checkbox" class="form-check-input" checked disabled
                                                   title="Global — always eligible for all sub-storefronts">
                                        @else
                                            <input type="checkbox"
                                                   class="form-check-input assign-check"
                                                   name="clinician_ids[]"
                                                   value="{{ $cl->id }}"
                                                   id="cl_{{ $cl->id }}"
                                                   {{ $isAssigned ? 'checked' : '' }}
                                                   onchange="syncCollabRadio({{ $cl->id }}, this.checked)">
                                        @endif
                                    </td>
                                    <td>
                                        <label for="{{ $isGlobal ? '' : 'cl_'.$cl->id }}" class="mb-0"
                                               style="{{ $isGlobal ? '' : 'cursor:pointer' }}">
                                            {{ $cl->full_name }}
                                            @if($isGlobal)
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" style="font-size:.65rem">
                                                    <i class="bi bi-globe2"></i> global
                                                </span>
                                            @endif
                                            @if(isset($clinicianSyncStatus[$cl->id]))
                                                @php $cs = $clinicianSyncStatus[$cl->id]; @endphp
                                                <span class="badge ms-1 {{ $cs === 'synced' ? 'bg-success' : ($cs === 'failed' ? 'bg-danger' : 'bg-secondary') }}" style="font-size:.6rem">
                                                    {{ $cs }}
                                                </span>
                                            @endif
                                        </label>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio"
                                               class="form-check-input collab-radio"
                                               name="collaborating_clinician_id"
                                               value="{{ $cl->id }}"
                                               id="collab_{{ $cl->id }}"
                                               {{ (int) old('collaborating_clinician_id', $subStorefront->collaborating_clinician_id) === $cl->id ? 'checked' : '' }}
                                               {{ (!$isGlobal && !$isAssigned) ? 'disabled' : '' }}>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 border-top small text-muted">
                        <input type="radio" name="collaborating_clinician_id" value="" id="collab_none"
                               class="form-check-input me-1"
                               {{ old('collaborating_clinician_id', $subStorefront->collaborating_clinician_id) ? '' : 'checked' }}>
                        <label for="collab_none">None (no collaborating default)</label>
                    </div>
                    @endif
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
        {{-- Clinician Healthie sync status (partner-level mappings) --}}
        <form id="syncCliniciansForm" method="POST"
              action="{{ route('admin.partners.sub-storefronts.sync-clinicians', [$partner->id, $subStorefront->id]) }}">
            @csrf
        </form>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Clinician Provisioning</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary">{{ $clinicianMappings->count() }}</span>
                    @if($subStorefront->healthie_default_group_id)
                    <button type="submit" form="syncCliniciansForm" class="btn btn-sm btn-outline-primary py-0"
                            title="Re-run clinician provisioning (skips already-synced)">
                        <i class="bi bi-arrow-repeat me-1"></i>Re-sync
                    </button>
                    @endif
                </div>
            </div>
            @if($clinicianMappings->isEmpty())
                <div class="card-body text-muted small text-center py-4">
                    <i class="bi bi-person-x fs-2 d-block mb-2 opacity-25"></i>
                    No clinicians synced to the partner's Healthie org yet.
                    @if(!$subStorefront->healthie_default_group_id)
                        <div class="mt-2">Create the Healthie group first (save the sub-storefront).</div>
                    @else
                        <div class="mt-2">Clinicians are provisioned automatically in the background.</div>
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

        {{-- Healthie Group provisioning --}}
        @if(!$subStorefront->healthie_default_group_id)
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning bg-opacity-10 border-warning">
                <h6 class="mb-0 text-warning-emphasis"><i class="bi bi-diagram-3 me-2"></i>Healthie Group</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    No Healthie User Group is linked to this sub-storefront.
                    Patients cannot be pushed to Healthie until a group is created.
                </p>
                <form id="provisionGroupForm" method="POST"
                      action="{{ route('admin.partners.sub-storefronts.provision-group', [$partner->id, $subStorefront->id]) }}">
                    @csrf
                    <button type="button" class="btn btn-warning btn-sm w-100"
                            onclick="openConfirmModal('provision')">
                        <i class="bi bi-plus-circle me-1"></i>Create Healthie Group
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Danger zone --}}
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 border-danger">
                <h6 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Deleting this sub-storefront removes it from the portal but <strong>does not delete the Healthie User Group</strong>.
                    Remove it from the Healthie admin portal manually after deletion.
                </p>
                <form id="deleteSubStorefrontForm" method="POST"
                      action="{{ route('admin.partners.sub-storefronts.destroy', [$partner->id, $subStorefront->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            onclick="openConfirmModal('delete')">
                        <i class="bi bi-trash me-1"></i>Delete Sub-Storefront
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- ── Shared confirmation modal (provision + delete) ──────────────── --}}
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">

            {{-- Colour strip + icon --}}
            <div id="confirmModalStrip" class="d-flex align-items-center justify-content-center py-4"
                 style="background:linear-gradient(135deg,#fff8ec 0%,#fff3cd 100%);">
                <div id="confirmModalIcon"
                     style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;
                            justify-content:center;font-size:1.55rem;
                            background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.12);">
                    <i class="bi bi-diagram-3-fill text-warning"></i>
                </div>
            </div>

            <div class="modal-body text-center px-4 pt-3 pb-2">
                <h5 class="fw-bold mb-1" id="confirmModalLabel" style="letter-spacing:-.01em;font-size:1.05rem;"></h5>
                <p class="text-muted mb-0" id="confirmModalBody" style="font-size:.875rem;line-height:1.6;"></p>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-light fw-semibold px-4"
                        style="border-radius:10px;border:1px solid #dee2e6;"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmModalSubmit"
                        class="btn fw-semibold px-4"
                        style="border-radius:10px;min-width:140px;">
                </button>
            </div>

        </div>
    </div>
</div>

@section('scripts')
<script>
const MODAL_CONFIG = {
    provision: {
        strip:  'linear-gradient(135deg,#fff8ec 0%,#fff3cd 100%)',
        icon:   'bi-diagram-3-fill text-warning',
        iconBg: '#fff',
        title:  'Create Healthie User Group?',
        body:   'This will call the Healthie API and create a User Group named <strong>{{ addslashes($subStorefront->name) }}</strong>. The Group ID will be saved here automatically.',
        btn:    'Create Group',
        btnCls: 'btn-warning',
        formId: 'provisionGroupForm',
    },
    delete: {
        strip:  'linear-gradient(135deg,#fff5f5 0%,#ffe0e0 100%)',
        icon:   'bi-trash3-fill text-danger',
        iconBg: '#fff',
        title:  'Delete "{{ addslashes($subStorefront->name) }}"?',
        body:   'This will permanently remove the sub-storefront and all its data from the portal. <strong>The Healthie User Group will not be deleted</strong> — remove it from the Healthie admin portal manually.',
        btn:    'Yes, Delete',
        btnCls: 'btn-danger',
        formId: 'deleteSubStorefrontForm',
    },
};

let pendingFormId = null;

function openConfirmModal(action) {
    const cfg = MODAL_CONFIG[action];
    if (!cfg) return;

    document.getElementById('confirmModalStrip').style.background = cfg.strip;

    const iconEl = document.getElementById('confirmModalIcon');
    iconEl.style.background = cfg.iconBg;
    iconEl.innerHTML = `<i class="bi ${cfg.icon}" style="font-size:1.55rem;"></i>`;

    document.getElementById('confirmModalLabel').textContent = cfg.title;
    document.getElementById('confirmModalBody').innerHTML = cfg.body;

    const btn = document.getElementById('confirmModalSubmit');
    btn.textContent = cfg.btn;
    btn.className = `btn fw-semibold px-4 ${cfg.btnCls}`;
    btn.style.borderRadius = '10px';
    btn.style.minWidth = '140px';

    pendingFormId = cfg.formId;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmActionModal')).show();
}

document.getElementById('confirmModalSubmit').addEventListener('click', function () {
    if (pendingFormId) {
        bootstrap.Modal.getInstance(document.getElementById('confirmActionModal')).hide();
        document.getElementById(pendingFormId).submit();
    }
});

function copyValue(id) {
    const el = document.getElementById(id);
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = el.nextElementSibling;
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check-lg text-success';
        setTimeout(() => icon.className = 'bi bi-clipboard', 1500);
    });
}

function syncCollabRadio(clinicianId, isChecked) {
    const radio = document.getElementById('collab_' + clinicianId);
    if (!radio) return;
    radio.disabled = !isChecked;
    if (!isChecked && radio.checked) {
        radio.checked = false;
        document.getElementById('collab_none').checked = true;
    }
}
</script>
@endsection
