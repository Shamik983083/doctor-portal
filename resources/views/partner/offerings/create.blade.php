@extends('layouts.partner')
@section('title', 'Create Offering')
@section('page-title', 'New Offering')

@section('content')
<div class="mb-4">
    <a href="{{ route('partner.offerings.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Offerings
    </a>
</div>

<form method="POST" action="{{ route('partner.offerings.store') }}">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <!-- Left column -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Basic Information</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="e.g. Semaglutide 2.4mg/mL" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="">Select type</option>
                                <option value="medication" @selected(old('type') === 'medication')>Medication</option>
                                <option value="compound"   @selected(old('type') === 'compound')>Compound</option>
                                <option value="supply"     @selected(old('type') === 'supply')>Supply / Device</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">SKU</label>
                            <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" placeholder="SKU-001">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Price (USD)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" step="0.01" min="0" class="form-control"
                                       value="{{ old('price') }}" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Optional description for clinicians…">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Pharmacy Integration</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Pharmacy Type</label>
                            <select name="pharmacy_type" class="form-select">
                                <option value="">None / Custom</option>
                                <option value="boothwyn" @selected(old('pharmacy_type') === 'boothwyn')>Boothwyn</option>
                                <option value="curexa"   @selected(old('pharmacy_type') === 'curexa')>Curexa</option>
                                <option value="custom"   @selected(old('pharmacy_type') === 'custom')>Custom</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">DoseSpot Medication ID</label>
                            <input type="text" name="dosespot_medication_id" class="form-control"
                                   value="{{ old('dosespot_medication_id') }}" placeholder="DS-…">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Boothwyn Compound ID</label>
                            <input type="text" name="boothwyn_compound_id" class="form-control"
                                   value="{{ old('boothwyn_compound_id') }}" placeholder="BC-…">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">State Availability</h6>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">Clear All</button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Leave all unchecked to make this offering available in every state.</p>
                    <div class="row row-cols-3 row-cols-sm-5 row-cols-md-7 g-2" id="stateGrid">
                        @foreach($usStates as $state)
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input state-cb" type="checkbox"
                                       name="available_states[]" value="{{ $state }}"
                                       id="state_{{ $state }}"
                                       @if(in_array($state, old('available_states', []))) checked @endif>
                                <label class="form-check-label small" for="state_{{ $state }}">{{ $state }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Settings</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               role="switch" value="1" @if(old('is_active', true)) checked @endif>
                        <label class="form-check-label" for="is_active">
                            <span class="fw-medium">Active</span>
                            <div class="text-muted small">Visible and orderable by clinicians</div>
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_controlled_substance"
                               id="is_controlled" role="switch" value="1"
                               @if(old('is_controlled_substance')) checked @endif>
                        <label class="form-check-label" for="is_controlled">
                            <span class="fw-medium">Controlled Substance</span>
                            <div class="text-muted small">Flags additional DEA compliance checks</div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i> Create Offering
                    </button>
                    <a href="{{ route('partner.offerings.index') }}" class="btn btn-outline-secondary w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('selectAll').addEventListener('click', () => {
    document.querySelectorAll('.state-cb').forEach(cb => cb.checked = true);
});
document.getElementById('clearAll').addEventListener('click', () => {
    document.querySelectorAll('.state-cb').forEach(cb => cb.checked = false);
});
</script>
@endpush
