@extends('layouts.admin')

@section('title', 'New Offering')
@section('page-title', 'New Offering')

@section('content')
<form id="createOfferingForm" method="POST" action="{{ route('admin.offerings.store') }}">
    @csrf
    <div class="row g-4">

        {{-- Left sidebar --}}
        <div class="col-lg-3">

            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0 small">Offering Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Partner <span class="text-danger">*</span></label>
                        <select name="partner_id" class="form-select form-select-sm @error('partner_id') is-invalid @enderror" required>
                            <option value="">Select partner...</option>
                            @foreach($partners as $partner)
                                <option value="{{ $partner->id }}" {{ old('partner_id') == $partner->id ? 'selected' : '' }}>
                                    {{ $partner->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('partner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold small">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
                            <option value="">Select type...</option>
                            <option value="medication" {{ old('type') === 'medication' ? 'selected' : '' }}>Medication</option>
                            <option value="compound"   {{ old('type') === 'compound'   ? 'selected' : '' }}>Compound</option>
                            <option value="supply"     {{ old('type') === 'supply'     ? 'selected' : '' }}>Supply</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Required Questionnaires --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0 small">Required Questionnaires <span class="text-danger">*</span></h6>
                </div>

                @error('questionnaire_ids')
                    <div class="card-body pb-0">
                        <div class="alert alert-danger py-2 mb-0"><small>{{ $message }}</small></div>
                    </div>
                @enderror

                @if($allQuestionnaires->isEmpty())
                    <div class="card-body">
                        <p class="text-muted small mb-0 fst-italic">No active questionnaires found.</p>
                    </div>
                @else
                <div id="questionnaireBox">
                    @foreach($allQuestionnaires as $q)
                    @php $checked = in_array($q->id, old('questionnaire_ids', [])); @endphp
                    <div class="d-flex flex-column px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="form-check mb-1">
                            <input class="form-check-input q-check" type="checkbox"
                                   name="questionnaire_ids[]" value="{{ $q->id }}"
                                   id="qc_{{ $q->id }}" {{ $checked ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" style="font-size:.8rem" for="qc_{{ $q->id }}">
                                {{ $q->name }}
                            </label>
                        </div>
                        <div class="form-check ms-4 mb-0 qr-toggle" id="qrt_{{ $q->id }}"
                             style="{{ $checked ? '' : 'opacity:.35;pointer-events:none' }}">
                            <input class="form-check-input" type="checkbox"
                                   name="questionnaire_required[{{ $q->id }}]" value="1"
                                   id="qr_{{ $q->id }}" checked>
                            <label class="form-check-label small text-muted" for="qr_{{ $q->id }}">Required</label>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div id="qError" class="text-danger small px-3 py-2 border-top" style="display:none">
                    Please select at least one questionnaire.
                </div>
                @endif
            </div>

        </div>

        {{-- Right: Create form --}}
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">New Offering</h6>
                    <a href="{{ route('admin.offerings.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to list
                    </a>
                </div>
                <div class="card-body">

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2">Basic Information</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Offering Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="e.g. Semaglutide 0.5mg Weekly" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Internal Name</label>
                            <input type="text" name="internal_name" class="form-control"
                                   value="{{ old('internal_name') }}" placeholder="Internal label">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                <option value="">Select category...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Pharmacy & Integration heading commented out — no live integration yet --}}

                    {{-- Pharmacy Type, DoseSpot Medication ID, Boothwyn Compound ID commented out — not wired to any live integration yet
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
                    --}}

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Prescription &amp; Dispensing</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Compound Formula <span class="text-danger">*</span></label>
                        <input type="text" name="compound_formula" class="form-control @error('compound_formula') is-invalid @enderror"
                               value="{{ old('compound_formula') }}" placeholder="e.g. NAD+ liquid – Olympia – 100mg/ml 10ml Vial" required>
                        @error('compound_formula')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Refills <span class="text-danger">*</span></label>
                            <input type="number" name="refills" min="0" class="form-control @error('refills') is-invalid @enderror"
                                   value="{{ old('refills') }}" placeholder="0" required>
                            @error('refills')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" min="0" step="0.01" class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity') }}" placeholder="1.00" required>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Days Supply <span class="text-muted fw-normal">(opt)</span></label>
                            <input type="number" name="days_supply" min="0" class="form-control"
                                   value="{{ old('days_supply') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Directions <span class="text-danger">*</span></label>
                        <textarea name="directions" class="form-control @error('directions') is-invalid @enderror" rows="3"
                                  placeholder="e.g. First Week: Inject 20 units once daily, Monday–Friday…" required>{{ old('directions') }}</textarea>
                        @error('directions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Sent to the pharmacy and included in the medication label.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">SIG <span class="text-muted fw-normal">(opt)</span></label>
                        <input type="text" name="sig" class="form-control"
                               value="{{ old('sig') }}"
                               placeholder="e.g. Take 1 capsule orally once daily">
                        <div class="form-text">Default patient-facing SIG instructions for this offering.</div>
                    </div>

                    {{-- Pharmacy Name and Pharmacy Notes commented out — no live integration yet
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
                    --}}

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
                                           {{ in_array($state, old('available_states', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="st_{{ $state }}">{{ $state }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Video Visit Required States</h6>
                    <p class="text-muted small mb-2">Select states where a synchronous video visit is required before prescribing this offering. Leave blank if no video requirement applies.</p>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllVideo">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllVideo">Clear All</button>
                    </div>
                    <div class="row row-cols-8 g-1 mb-3">
                        @foreach($usStates as $state)
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input video-state-cb" type="checkbox"
                                       name="video_required_states[]" value="{{ $state }}"
                                       id="vs_{{ $state }}"
                                       {{ in_array($state, old('video_required_states', [])) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="vs_{{ $state }}">{{ $state }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2 mt-4">Flags</h6>

                    <div class="d-flex gap-4 mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                                   {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActive">Active</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_controlled_substance" value="0">
                            <input class="form-check-input" type="checkbox" name="is_controlled_substance" value="1" id="isControlled"
                                   {{ old('is_controlled_substance') ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isControlled">Controlled Substance</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Create Offering
                        </button>
                        <a href="{{ route('admin.offerings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </div>
            </div>
        </div>

    </div>
</form>
@endsection

@section('scripts')
<script>
    document.getElementById('selectAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = true));
    document.getElementById('clearAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = false));
    document.getElementById('selectAllVideo').addEventListener('click', () =>
        document.querySelectorAll('.video-state-cb').forEach(cb => cb.checked = true));
    document.getElementById('clearAllVideo').addEventListener('click', () =>
        document.querySelectorAll('.video-state-cb').forEach(cb => cb.checked = false));

    document.querySelectorAll('.q-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var toggle = document.getElementById('qrt_' + this.value);
            if (!toggle) return;
            toggle.style.opacity       = this.checked ? '1'    : '0.35';
            toggle.style.pointerEvents = this.checked ? 'auto' : 'none';
            if (!this.checked) {
                var req = document.getElementById('qr_' + this.value);
                if (req) req.checked = false;
            }
            document.getElementById('qError').style.display = 'none';
        });
    });

    document.getElementById('createOfferingForm').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('.q-check:checked').length;
        if (checked === 0 && document.getElementById('questionnaireBox')) {
            e.preventDefault();
            document.getElementById('qError').style.display = 'block';
            document.getElementById('questionnaireBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endsection
