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
                    <tr><th class="text-muted">Price</th><td>{{ $offering->price ? '$'.number_format($offering->price, 2) : '—' }}</td></tr>
                    <tr><th class="text-muted">SKU</th><td>{{ $offering->sku ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Pharmacy</th><td>{{ $offering->pharmacy_type ? ucfirst($offering->pharmacy_type) : '—' }}</td></tr>
                    <tr><th class="text-muted">DoseSpot ID</th><td><code>{{ $offering->dosespot_medication_id ?? '—' }}</code></td></tr>
                    <tr><th class="text-muted">Boothwyn ID</th><td><code>{{ $offering->boothwyn_compound_id ?? '—' }}</code></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
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
    </div>

    {{-- Right: Edit form --}}
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Edit Offering</h6>
                <a href="{{ route('admin.offerings.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to list
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.offerings.update', $offering->id) }}">
                    @csrf @method('PUT')

                    <h6 class="text-muted text-uppercase small fw-semibold mb-3 border-bottom pb-2">Basic Information</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Offering Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $offering->name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (USD)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" step="0.01" min="0" class="form-control"
                                       value="{{ old('price', $offering->price) }}" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $offering->description) }}</textarea>
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
@endsection

@section('scripts')
<script>
    document.getElementById('selectAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = true));
    document.getElementById('clearAll').addEventListener('click', () =>
        document.querySelectorAll('.state-cb').forEach(cb => cb.checked = false));
</script>
@endsection
