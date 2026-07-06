@extends('layouts.clinician')

@section('title', 'Submit Prescription')
@section('page-title', 'Submit Prescription — ' . substr($case->uuid, 0, 8))

@section('content')

<div class="mb-3">
    <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Case
    </a>
</div>

{{-- Patient / Case banner --}}
<div class="alert alert-info d-flex gap-3 align-items-start mb-4 py-2">
    <i class="bi bi-person-circle fs-5 flex-shrink-0 mt-1"></i>
    <div class="small">
        <strong>{{ $case->patient->full_name }}</strong>
        &nbsp;·&nbsp; {{ $case->patient->email }}
        &nbsp;·&nbsp; DOB: {{ $case->patient->date_of_birth?->format('M d, Y') ?? '—' }}
        &nbsp;·&nbsp; State: {{ $case->patient_state ?? $case->patient->state ?? '—' }}
        <span class="ms-3 text-muted">Case #{{ substr($case->uuid, 0, 8) }} &middot; {{ $case->partner->name }}</span>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('clinician.cases.prescribe', $case->uuid) }}">
@csrf

<div class="row g-4">

    {{-- Left: Clinical fields --}}
    <div class="col-lg-5">

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Clinical Information</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Diagnoses <span class="text-danger">*</span></label>
                    <textarea name="diagnoses" class="form-control @error('diagnoses') is-invalid @enderror"
                              rows="4" placeholder="e.g. E66.01 – Morbid obesity due to excess calories…" required>{{ old('diagnoses') }}</textarea>
                    @error('diagnoses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Directions</label>
                    <textarea name="directions" class="form-control" rows="3"
                              placeholder="General administration instructions for the patient…">{{ old('directions') }}</textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Medical Necessity</label>
                    <textarea name="medical_necessity" class="form-control" rows="3"
                              placeholder="Justify medical necessity for the prescribed medications…">{{ old('medical_necessity') }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success flex-grow-1">
                <i class="bi bi-check-lg me-1"></i>Approve &amp; Submit Prescription
            </button>
            <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>

    </div>

    {{-- Right: Medications --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-capsule me-2"></i>Medications</h6>
                <small class="text-muted">
                    @if($offerings->count())
                        Showing {{ $offerings->count() }} offering(s) for this case's category
                    @else
                        No offerings available
                    @endif
                </small>
            </div>
            <div class="card-body pb-2">

                {{-- Search box --}}
                <div class="position-relative mb-3">
                    <input type="text" id="med-search" class="form-control"
                           placeholder="Search and select a medication to add…" autocomplete="off">
                    <div id="med-dropdown" class="border rounded bg-white shadow-sm position-absolute w-100 d-none"
                         style="z-index:1000; max-height:220px; overflow-y:auto; top:100%; left:0;"></div>
                </div>

                {{-- Added medications --}}
                <div id="medications-container"></div>

                <p id="no-meds-msg" class="text-muted small text-center py-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>No medications added yet. Search above to add one.
                </p>

            </div>
        </div>
    </div>
</div>

</form>

{{-- Offering data for JS --}}
<script>
// All searchable offerings (active + approved, filtered by case category)
const OFFERINGS = {!! json_encode($offerings->map(function($o) {
    return [
        'id'                  => $o->id,
        'name'                => $o->name,
        'internal_name'       => $o->internal_name ?? '',
        'compound_formula'    => $o->compound_formula ?? '',
        'refills'             => $o->refills ?? '',
        'quantity'            => $o->quantity ?? '',
        'days_supply'         => $o->days_supply ?? '',
        'dispense_unit'       => $o->dispense_unit ?? '',
        'days_until_dispense' => $o->days_until_dispense ?? '',
        'directions'          => $o->directions ?? '',
    ];
})->values()) !!};

// Offerings already on this case — pre-loaded from the patient's order
const CASE_OFFERINGS = {!! json_encode($case->caseOfferings->map(function($co) {
    $o = $co->offering;
    if (!$o) return null;
    return [
        'id'                  => $o->id,
        'name'                => $o->name,
        'internal_name'       => $o->internal_name ?? '',
        'compound_formula'    => $o->compound_formula ?? '',
        'refills'             => $o->refills ?? '',
        'quantity'            => $o->quantity ?? '',
        'days_supply'         => $o->days_supply ?? '',
        'dispense_unit'       => $o->dispense_unit ?? '',
        'days_until_dispense' => $o->days_until_dispense ?? '',
        'directions'          => $o->directions ?? '',
    ];
})->filter()->values()) !!};

let medIndex = 0;

const searchInput      = document.getElementById('med-search');
const dropdown         = document.getElementById('med-dropdown');
const container        = document.getElementById('medications-container');
const noMedsMsg        = document.getElementById('no-meds-msg');
const directionsField  = document.querySelector('textarea[name="directions"]');

// Safely escape a value for use inside an HTML attribute
function esc(val) {
    return String(val ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

searchInput.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    dropdown.innerHTML = '';
    if (!q) { dropdown.classList.add('d-none'); return; }

    const matches = OFFERINGS.filter(o =>
        o.name.toLowerCase().includes(q) ||
        o.internal_name.toLowerCase().includes(q)
    );

    if (!matches.length) {
        dropdown.innerHTML = '<div class="px-3 py-2 text-muted small">No matches found.</div>';
    } else {
        matches.forEach(o => {
            const item = document.createElement('div');
            item.className = 'px-3 py-2 border-bottom cursor-pointer dropdown-item-hover';
            item.style.cursor = 'pointer';
            item.innerHTML = `<span class="fw-semibold">${esc(o.name)}</span>`
                + (o.internal_name ? ` <small class="text-muted ms-1">${esc(o.internal_name)}</small>` : '');
            item.addEventListener('click', () => addMedication(o));
            dropdown.appendChild(item);
        });
    }
    dropdown.classList.remove('d-none');
});

document.addEventListener('click', function (e) {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add('d-none');
    }
});

function addMedication(o) {
    dropdown.classList.add('d-none');
    searchInput.value = '';
    noMedsMsg.classList.add('d-none');

    // Auto-fill the shared Directions textarea from the first medication that has directions
    if (directionsField && !directionsField.value.trim() && o.directions) {
        directionsField.value = o.directions;
    }

    const idx = medIndex++;
    const row = document.createElement('div');
    row.className = 'border rounded mb-3 p-3 position-relative bg-light';
    row.dataset.index = idx;

    row.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 remove-med"
                style="line-height:1; padding:2px 7px; font-size:.75rem;">
            <i class="bi bi-x-lg"></i>
        </button>

        <input type="hidden" name="medications[${idx}][offering_id]" value="${esc(o.id)}">

        <div class="mb-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Medication Name</label>
            <input type="text" name="medications[${idx}][name]"
                   class="form-control form-control-sm" value="${esc(o.name)}" required>
        </div>

        <div class="mb-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Compound Formula</label>
            <input type="text" name="medications[${idx}][compound_formula]"
                   class="form-control form-control-sm" value="${esc(o.compound_formula)}">
        </div>

        <div class="row g-2 mb-2">
            <div class="col-4 col-md-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Refills</label>
                <input type="number" name="medications[${idx}][refills]" min="0"
                       class="form-control form-control-sm" value="${esc(o.refills)}">
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Quantity</label>
                <input type="number" name="medications[${idx}][quantity]" min="0" step="0.01"
                       class="form-control form-control-sm" value="${esc(o.quantity)}">
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Days Supply</label>
                <input type="number" name="medications[${idx}][days_supply]" min="0"
                       class="form-control form-control-sm" value="${esc(o.days_supply)}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold mb-1">Dispense Unit</label>
                <input type="text" name="medications[${idx}][dispense_unit]"
                       class="form-control form-control-sm" value="${esc(o.dispense_unit)}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm fw-semibold mb-1">Days Until Dispense</label>
                <input type="number" name="medications[${idx}][days_until_dispense]" min="0"
                       class="form-control form-control-sm" value="${esc(o.days_until_dispense)}">
            </div>
        </div>
    `;

    row.querySelector('.remove-med').addEventListener('click', () => {
        row.remove();
        if (!container.children.length) noMedsMsg.classList.remove('d-none');
    });

    container.appendChild(row);
}

// Pre-load the medications the patient ordered on this case
CASE_OFFERINGS.forEach(o => addMedication(o));
</script>
@endsection
