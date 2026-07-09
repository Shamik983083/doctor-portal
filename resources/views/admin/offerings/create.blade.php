@extends('layouts.admin')

@section('title', 'New Offering')
@section('page-title', 'New Offering')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Create Offering</h6>
        <a href="{{ route('admin.offerings.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.offerings.store') }}">
            @csrf

            {{-- Basic Info --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2">Basic Information</h6>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Offering Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="e.g. Semaglutide 0.5mg Weekly" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Internal Name</label>
                    <input type="text" name="internal_name" class="form-control"
                           value="{{ old('internal_name') }}" placeholder="Internal label">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">Select type...</option>
                        <option value="medication" {{ old('type') === 'medication' ? 'selected' : '' }}>Medication</option>
                        <option value="compound"   {{ old('type') === 'compound'   ? 'selected' : '' }}>Compound</option>
                        <option value="supply"     {{ old('type') === 'supply'     ? 'selected' : '' }}>Supply</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">No category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Partner <span class="text-danger">*</span></label>
                    <select name="partner_id" class="form-select @error('partner_id') is-invalid @enderror" required>
                        <option value="">Select partner...</option>
                        @foreach($partners as $partner)
                            <option value="{{ $partner->id }}" {{ old('partner_id') == $partner->id ? 'selected' : '' }}>
                                {{ $partner->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('partner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Pharmacy / Integration --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Pharmacy & Integration IDs</h6>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Pharmacy Type <span class="text-danger">*</span></label>
                    <select name="pharmacy_type" class="form-select @error('pharmacy_type') is-invalid @enderror" required>
                        <option value="">Select pharmacy type...</option>
                        <option value="boothwyn" {{ old('pharmacy_type') === 'boothwyn' ? 'selected' : '' }}>Boothwyn</option>
                        <option value="curexa"   {{ old('pharmacy_type') === 'curexa'   ? 'selected' : '' }}>Curexa</option>
                        <option value="custom"   {{ old('pharmacy_type') === 'custom'   ? 'selected' : '' }}>Custom</option>
                    </select>
                    @error('pharmacy_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">DoseSpot Medication ID</label>
                    <input type="text" name="dosespot_medication_id" class="form-control"
                           value="{{ old('dosespot_medication_id') }}" placeholder="DoseSpot ID">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Boothwyn Compound ID</label>
                    <input type="text" name="boothwyn_compound_id" class="form-control"
                           value="{{ old('boothwyn_compound_id') }}" placeholder="Boothwyn ID">
                </div>
            </div>

            {{-- Prescription & Dispensing --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Prescription &amp; Dispensing</h6>

            <div class="mb-3">
                <label class="form-label fw-semibold">Compound Formula <span class="text-danger">*</span></label>
                <input type="text" name="compound_formula" class="form-control @error('compound_formula') is-invalid @enderror"
                       value="{{ old('compound_formula') }}" placeholder="e.g. NAD+ liquid – Olympia – 100mg/ml 10ml Vial" required>
                @error('compound_formula')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Refills <span class="text-danger">*</span></label>
                    <input type="number" name="refills" min="0" class="form-control @error('refills') is-invalid @enderror"
                           value="{{ old('refills') }}" placeholder="0" required>
                    @error('refills')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" min="0" step="0.01" class="form-control @error('quantity') is-invalid @enderror"
                           value="{{ old('quantity') }}" placeholder="1.00" required>
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Days Supply <span class="text-muted fw-normal">(opt)</span></label>
                    <input type="number" name="days_supply" min="0" class="form-control"
                           value="{{ old('days_supply') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Dispense Unit <span class="text-danger">*</span></label>
                    <input type="text" name="dispense_unit" class="form-control @error('dispense_unit') is-invalid @enderror"
                           value="{{ old('dispense_unit') }}" placeholder="e.g. Each, Vial, mL" required>
                    @error('dispense_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Days Until Dispense <span class="text-muted fw-normal">(opt)</span></label>
                    <input type="number" name="days_until_dispense" min="0" class="form-control"
                           value="{{ old('days_until_dispense') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Directions <span class="text-danger">*</span></label>
                <textarea name="directions" class="form-control @error('directions') is-invalid @enderror" rows="3"
                          placeholder="e.g. First Week: Inject 20 units once daily, Monday–Friday…" required>{{ old('directions') }}</textarea>
                @error('directions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Sent to the pharmacy and included in the medication label.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pharmacy Name <span class="text-muted fw-normal">(opt)</span></label>
                    <input type="text" name="pharmacy_name" class="form-control"
                           value="{{ old('pharmacy_name') }}" placeholder="e.g. THE PHARMACY HUB LLC (271328)">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Pharmacy Notes <span class="text-muted fw-normal">(opt)</span></label>
                <textarea name="pharmacy_notes" class="form-control" rows="2"
                          placeholder="e.g. Bill to partner, Ship to Patient">{{ old('pharmacy_notes') }}</textarea>
            </div>

            {{-- State Availability --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">
                State Availability
                <span class="text-muted fw-normal normal-case" style="text-transform:none; font-size:.8rem;">
                    — leave all unchecked to allow all states
                </span>
            </h6>

            <div class="mb-3">
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">Clear All</button>
                </div>
                <div class="row row-cols-6 g-1" id="stateCheckboxes">
                    @foreach($usStates as $state)
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input state-cb" type="checkbox"
                                   name="available_states[]" value="{{ $state }}"
                                   id="state_{{ $state }}"
                                   {{ in_array($state, old('available_states', [])) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="state_{{ $state }}">{{ $state }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Required Questionnaires --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Required Questionnaires <span class="text-danger">*</span></h6>
            <p class="text-muted small mb-3">Select which questionnaire forms must be completed when a case is submitted for this offering.</p>

            @error('questionnaire_ids')
                <div class="alert alert-danger py-2 mb-3"><small>{{ $message }}</small></div>
            @enderror

            @if($allQuestionnaires->isEmpty())
                <p class="text-muted small fst-italic mb-3">No active questionnaires found.</p>
            @else
            <div class="border rounded mb-3" id="questionnaireBox">
                @foreach($allQuestionnaires as $q)
                @php $checked = in_array($q->id, old('questionnaire_ids', [])); @endphp
                <div class="d-flex align-items-center justify-content-between px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="form-check mb-0">
                        <input class="form-check-input q-check" type="checkbox"
                               name="questionnaire_ids[]" value="{{ $q->id }}"
                               id="qc_{{ $q->id }}" {{ $checked ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold small" for="qc_{{ $q->id }}">
                            {{ $q->name }}
                        </label>
                    </div>
                    <div class="form-check form-check-inline mb-0 qr-toggle" id="qrt_{{ $q->id }}"
                         style="{{ $checked ? '' : 'opacity:.35;pointer-events:none' }}">
                        <input class="form-check-input" type="checkbox"
                               name="questionnaire_required[{{ $q->id }}]" value="1"
                               id="qr_{{ $q->id }}" checked>
                        <label class="form-check-label small text-muted" for="qr_{{ $q->id }}">Required</label>
                    </div>
                </div>
                @endforeach
            </div>
            <div id="qError" class="text-danger small mb-3" style="display:none">Please select at least one questionnaire.</div>
            @endif

            {{-- Flags --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Flags</h6>

            <div class="d-flex gap-4 mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                           {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActive">Active</label>
                    <div class="text-muted small">Visible and orderable by partners</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_controlled_substance" value="1" id="isControlled"
                           {{ old('is_controlled_substance') ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isControlled">Controlled Substance</label>
                    <div class="text-muted small">Requires additional DEA compliance</div>
                </div>
            </div>

            <div class="d-flex gap-2 pt-2 border-top">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Create Offering
                </button>
                <a href="{{ route('admin.offerings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('selectAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = true));
    document.getElementById('clearAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = false));

    document.querySelectorAll('.q-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var toggle = document.getElementById('qrt_' + this.value);
            if (!toggle) return;
            toggle.style.opacity        = this.checked ? '1'    : '0.35';
            toggle.style.pointerEvents  = this.checked ? 'auto' : 'none';
            if (!this.checked) {
                var req = document.getElementById('qr_' + this.value);
                if (req) req.checked = false;
            }
            document.getElementById('qError').style.display = 'none';
            document.getElementById('questionnaireBox').style.borderColor = '';
        });
    });

    document.querySelector('form').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('.q-check:checked').length;
        if (checked === 0 && document.getElementById('questionnaireBox')) {
            e.preventDefault();
            var err = document.getElementById('qError');
            err.style.display = 'block';
            document.getElementById('questionnaireBox').style.borderColor = '#dc3545';
            document.getElementById('questionnaireBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endsection
