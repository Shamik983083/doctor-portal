@extends('layouts.admin')

@section('title', "Offering: {$offering->name}")
@section('page-title', "Offering: {$offering->name}")

@section('content')
<div class="row g-4">

    {{-- Left: Summary card --}}
    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 bg-light border" style="width:72px;height:72px;font-size:2rem;">
                    @if($offering->type === 'medication') 💊
                    @elseif($offering->type === 'compound') 🧪
                    @else 📦
                    @endif
                </div>
                <h6 class="mb-1">{{ $offering->name }}</h6>
                <span class="badge bg-light text-dark border">{{ ucfirst($offering->type) }}</span>
                <span class="badge {{ $offering->is_active ? 'bg-success' : 'bg-secondary' }} ms-1">
                    {{ $offering->is_active ? 'Active' : 'Inactive' }}
                </span>
                @if($offering->is_controlled_substance)
                <div class="mt-2"><span class="badge bg-danger">⚠ Controlled Substance</span></div>
                @endif
            </div>
            <div class="card-body pt-0 border-top">
                <table class="table table-sm table-borderless small mb-0">
                    <tr><th class="text-muted">Partner</th><td>{{ $offering->partner->name ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Category</th><td>{{ $offering->category?->name ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Price</th><td>{{ $offering->price ? '$'.number_format($offering->price, 2) : '—' }}</td></tr>
                    <tr><th class="text-muted">SKU</th><td>{{ $offering->sku ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Pharmacy</th><td>{{ $offering->pharmacy_type ? ucfirst($offering->pharmacy_type) : '—' }}</td></tr>
                    <tr><th class="text-muted">DoseSpot ID</th><td><code>{{ $offering->dosespot_medication_id ?? '—' }}</code></td></tr>
                    <tr><th class="text-muted">Boothwyn ID</th><td><code>{{ $offering->boothwyn_compound_id ?? '—' }}</code></td></tr>
                </table>

                @if($offering->approval_status === 'approved')
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="bi bi-key-fill text-success" style="font-size:.8rem"></i>
                        <span class="small fw-semibold">Offering ID</span>
                        <span class="badge bg-success ms-auto" style="font-size:.6rem">Approved</span>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="adminOfferingUuid" class="form-control font-monospace bg-light"
                               value="{{ $offering->uuid }}" readonly style="font-size:.7rem">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyAdminUuid()" title="Copy ID">
                            <i class="bi bi-copy" id="adminCopyIcon" style="font-size:.8rem"></i>
                        </button>
                    </div>
                    <div class="form-text mt-1" style="font-size:.7rem">
                        Share with the patient portal developer to reference this offering in case submissions.
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Approval Status Card --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0 small">Approval Status</h6></div>
            <div class="card-body">
                @if($offering->approval_status === 'approved')
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mb-2 d-block text-start p-2">
                        <i class="bi bi-check-circle me-1"></i>Approved
                    </span>
                    @if($offering->approvedBy)
                        <p class="text-muted small mb-2">By {{ $offering->approvedBy->name }}<br>{{ $offering->approved_at?->format('M j, Y g:i A') }}</p>
                    @endif
                    <button class="btn btn-sm btn-outline-danger w-100"
                            data-bs-toggle="modal" data-bs-target="#rejectModal"
                            data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                            data-name="{{ $offering->name }}"
                            data-note="">
                        <i class="bi bi-x-circle me-1"></i>Revoke Approval
                    </button>
                @elseif($offering->approval_status === 'rejected')
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 mb-2 d-block text-start p-2">
                        <i class="bi bi-x-circle me-1"></i>Rejected
                    </span>
                    @if($offering->rejection_note)
                        <div class="border rounded p-2 mb-2 bg-danger bg-opacity-10" style="font-size:.8rem;">
                            <div class="text-muted small fw-semibold mb-1">Rejection note:</div>
                            <div>{{ $offering->rejection_note }}</div>
                        </div>
                    @endif
                    <p class="text-muted small mb-2">Partner can edit and re-submit.</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.offerings.approve', $offering->id) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-sm btn-success w-100">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-danger flex-fill" title="Update rejection note"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                                data-name="{{ $offering->name }}"
                                data-note="{{ $offering->rejection_note }}">
                            <i class="bi bi-pencil me-1"></i>Edit Note
                        </button>
                    </div>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 mb-2 d-block text-start p-2">
                        <i class="bi bi-clock me-1"></i>Pending Admin Review
                    </span>
                    <p class="text-muted small mb-2">Not visible to clinicians until approved.</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.offerings.approve', $offering->id) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-sm btn-success w-100">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-danger flex-fill"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                                data-name="{{ $offering->name }}"
                                data-note="">
                            <i class="bi bi-x-lg me-1"></i>Reject
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0 small">Available States</h6></div>
            <div class="card-body">
                @if($offering->available_states && count($offering->available_states))
                    @foreach($offering->available_states as $state)
                        <span class="badge bg-light text-dark border me-1 mb-1">{{ $state }}</span>
                    @endforeach
                @else
                    <p class="text-muted small mb-0"><i class="bi bi-globe me-1"></i>All states</p>
                @endif
            </div>
        </div>

        {{-- Required Questionnaires --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 small">Required Questionnaires</h6>
                @if($offering->questionnaires->isNotEmpty())
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"
                          style="font-size:.65rem">{{ $offering->questionnaires->count() }}</span>
                @endif
            </div>

            {{-- Attached list --}}
            @if($offering->questionnaires->isEmpty())
                <div class="card-body py-2">
                    <p class="text-muted small mb-0">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        No questionnaires attached. Partners can still submit via <code>questionnaire_responses</code>.
                    </p>
                </div>
            @else
                <ul class="list-group list-group-flush">
                    @foreach($offering->questionnaires->sortBy('pivot.sort_order') as $q)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <div>
                            <div class="small fw-semibold">{{ $q->name }}</div>
                            @if($q->pivot->is_required)
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.62rem">Required</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.62rem">Optional</span>
                            @endif
                        </div>
                        <form method="POST"
                              action="{{ route('admin.offerings.questionnaires.detach', [$offering->id, $q->id]) }}"
                              onsubmit="return confirm('Remove {{ addslashes($q->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger border-0 p-1" title="Remove">
                                <i class="bi bi-x-lg" style="font-size:.75rem"></i>
                            </button>
                        </form>
                    </li>
                    @endforeach
                </ul>
            @endif

            {{-- Add questionnaire --}}
            @php $attachable = $allQuestionnaires->whereNotIn('id', $offering->questionnaires->pluck('id')); @endphp
            @if($attachable->isNotEmpty())
            <div class="card-footer px-3 py-2 bg-light border-top">
                <p class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Attach Questionnaire</p>
                <form method="POST"
                      action="{{ route('admin.offerings.questionnaires.attach', $offering->id) }}">
                    @csrf
                    <select name="questionnaire_id" class="form-select form-select-sm mb-2" required>
                        <option value="">Select questionnaire…</option>
                        @foreach($attachable as $q)
                            <option value="{{ $q->id }}">{{ $q->name }}</option>
                        @endforeach
                    </select>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_required" value="1"
                                   id="aqReq_{{ $offering->id }}" checked>
                            <label class="form-check-label small" for="aqReq_{{ $offering->id }}">Mark as Required</label>
                        </div>
                        <button class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>

    {{-- Right: Edit form --}}
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Edit Offering</h6>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.offerings.destroy', $offering->id) }}"
                          onsubmit="return confirm('Delete {{ addslashes($offering->name) }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                    <a href="{{ route('admin.offerings.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to list
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.offerings.update', $offering->id) }}">
                    @csrf @method('PUT')

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2">Basic Information</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Offering Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $offering->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Internal Name</label>
                            <input type="text" name="internal_name" class="form-control"
                                   value="{{ old('internal_name', $offering->internal_name) }}" placeholder="Internal label">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">No category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ old('category_id', $offering->category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Pharmacy & Integration</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pharmacy Type</label>
                            <select name="pharmacy_type" class="form-select">
                                <option value="">Not specified</option>
                                @foreach(['boothwyn','curexa','custom'] as $pt)
                                <option value="{{ $pt }}" {{ old('pharmacy_type', $offering->pharmacy_type) === $pt ? 'selected' : '' }}>
                                    {{ ucfirst($pt) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DoseSpot Medication ID</label>
                            <input type="text" name="dosespot_medication_id" class="form-control"
                                   value="{{ old('dosespot_medication_id', $offering->dosespot_medication_id) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Boothwyn Compound ID</label>
                            <input type="text" name="boothwyn_compound_id" class="form-control"
                                   value="{{ old('boothwyn_compound_id', $offering->boothwyn_compound_id) }}">
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Prescription &amp; Dispensing</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Compound Formula</label>
                        <input type="text" name="compound_formula" class="form-control"
                               value="{{ old('compound_formula', $offering->compound_formula) }}"
                               placeholder="e.g. NAD+ liquid – Olympia – 100mg/ml 10ml Vial">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Refills</label>
                            <input type="number" name="refills" min="0" class="form-control"
                                   value="{{ old('refills', $offering->refills) }}" placeholder="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Quantity</label>
                            <input type="number" name="quantity" min="0" step="0.01" class="form-control"
                                   value="{{ old('quantity', $offering->quantity) }}" placeholder="1.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Days Supply <span class="text-muted fw-normal">(opt)</span></label>
                            <input type="number" name="days_supply" min="0" class="form-control"
                                   value="{{ old('days_supply', $offering->days_supply) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Dispense Unit</label>
                            <input type="text" name="dispense_unit" class="form-control"
                                   value="{{ old('dispense_unit', $offering->dispense_unit) }}"
                                   placeholder="e.g. Each, Vial, mL">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Days Until Dispense <span class="text-muted fw-normal">(opt)</span></label>
                            <input type="number" name="days_until_dispense" min="0" class="form-control"
                                   value="{{ old('days_until_dispense', $offering->days_until_dispense) }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Directions</label>
                        <textarea name="directions" class="form-control" rows="3"
                                  placeholder="e.g. First Week: Inject 20 units once daily, Monday–Friday…">{{ old('directions', $offering->directions) }}</textarea>
                        <div class="form-text">Sent to the pharmacy and included in the medication label.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Pharmacy Name <span class="text-muted fw-normal">(opt)</span></label>
                            <input type="text" name="pharmacy_name" class="form-control"
                                   value="{{ old('pharmacy_name', $offering->pharmacy_name) }}"
                                   placeholder="e.g. THE PHARMACY HUB LLC (271328)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pharmacy Notes <span class="text-muted fw-normal">(opt)</span></label>
                        <textarea name="pharmacy_notes" class="form-control" rows="2"
                                  placeholder="e.g. Bill to partner, Ship to Patient">{{ old('pharmacy_notes', $offering->pharmacy_notes) }}</textarea>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">
                        State Availability
                        <span class="fw-normal" style="text-transform:none; font-size:.8rem;">— leave all unchecked for all states</span>
                    </h6>

                    <div class="mb-3">
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">Clear All</button>
                        </div>
                        <div class="row row-cols-8 g-1">
                            @foreach($usStates as $state)
                            <div class="col">
                                <div class="form-check">
                                    <input class="form-check-input state-cb" type="checkbox"
                                           name="available_states[]" value="{{ $state }}"
                                           id="st_{{ $state }}"
                                           {{ in_array($state, old('available_states', $offering->available_states ?? [])) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="st_{{ $state }}">{{ $state }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Flags</h6>

                    <div class="d-flex gap-4 mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                                   {{ old('is_active', $offering->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActive">Active</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_controlled_substance" value="0">
                            <input class="form-check-input" type="checkbox" name="is_controlled_substance" value="1" id="isControlled"
                                   {{ old('is_controlled_substance', $offering->is_controlled_substance) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isControlled">Controlled Substance</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="{{ route('admin.offerings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Rejection note modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="rejectModalLabel">
                    <i class="bi bi-x-octagon text-danger me-2"></i>Reject Offering
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Rejecting: <strong id="rejectOfferingName"></strong>
                    </p>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">
                            Rejection Note <span class="text-danger">*</span>
                        </label>
                        <textarea id="rejectNote" name="rejection_note" class="form-control" rows="4"
                                  placeholder="Explain why this offering is being rejected so the partner knows what to fix…"
                                  required maxlength="1000"></textarea>
                        <div class="form-text">This note is visible to the partner.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('selectAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = true));
    document.getElementById('clearAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = false));

    document.getElementById('rejectModal').addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        document.getElementById('rejectOfferingName').textContent = btn.dataset.name;
        document.getElementById('rejectForm').action = btn.dataset.action;
        document.getElementById('rejectNote').value = btn.dataset.note || '';
    });

    function copyAdminUuid() {
        var input = document.getElementById('adminOfferingUuid');
        var icon  = document.getElementById('adminCopyIcon');
        navigator.clipboard.writeText(input.value).then(function () {
            icon.className = 'bi bi-check-lg text-success';
            setTimeout(function () { icon.className = 'bi bi-copy'; }, 1800);
        });
    }
</script>
@endsection
