@extends('layouts.admin')

@section('title', 'Add Clinician')
@section('page-title', 'Add Clinician')

@php
$usStates = [
    'AL' => 'Alabama',       'AK' => 'Alaska',         'AZ' => 'Arizona',        'AR' => 'Arkansas',
    'CA' => 'California',    'CO' => 'Colorado',        'CT' => 'Connecticut',    'DE' => 'Delaware',
    'DC' => 'D.C.',          'FL' => 'Florida',         'GA' => 'Georgia',        'HI' => 'Hawaii',
    'ID' => 'Idaho',         'IL' => 'Illinois',        'IN' => 'Indiana',        'IA' => 'Iowa',
    'KS' => 'Kansas',        'KY' => 'Kentucky',        'LA' => 'Louisiana',      'ME' => 'Maine',
    'MD' => 'Maryland',      'MA' => 'Massachusetts',   'MI' => 'Michigan',       'MN' => 'Minnesota',
    'MS' => 'Mississippi',   'MO' => 'Missouri',        'MT' => 'Montana',        'NE' => 'Nebraska',
    'NV' => 'Nevada',        'NH' => 'New Hampshire',   'NJ' => 'New Jersey',     'NM' => 'New Mexico',
    'NY' => 'New York',      'NC' => 'North Carolina',  'ND' => 'North Dakota',   'OH' => 'Ohio',
    'OK' => 'Oklahoma',      'OR' => 'Oregon',          'PA' => 'Pennsylvania',   'RI' => 'Rhode Island',
    'SC' => 'South Carolina','SD' => 'South Dakota',    'TN' => 'Tennessee',      'TX' => 'Texas',
    'UT' => 'Utah',          'VT' => 'Vermont',         'VA' => 'Virginia',       'WA' => 'Washington',
    'WV' => 'West Virginia', 'WI' => 'Wisconsin',       'WY' => 'Wyoming',
];
$selectedStates = old('licensed_states', []);
@endphp

@section('content')
<div class="card" style="max-width:860px;">
    <div class="card-header"><h6 class="mb-0">Clinician Details</h6></div>
    <div class="card-body">

        @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.clinicians.store') }}">
            @csrf

            {{-- Account --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>

            <hr class="my-3">

            {{-- Professional details --}}
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Credentials</label>
                    <select name="credentials" class="form-select">
                        <option value="">Select</option>
                        @foreach(['MD','DO','NP','PA'] as $c)
                        <option value="{{ $c }}" {{ old('credentials') === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">NPI Number</label>
                    <input type="text" name="npi" class="form-control" value="{{ old('npi') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Primary License State</label>
                    <input type="text" name="license_state" class="form-control @error('license_state') is-invalid @enderror"
                           maxlength="2" placeholder="e.g. CA" value="{{ old('license_state') }}"
                           style="text-transform:uppercase;">
                    @error('license_state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Specialty</label>
                    <input type="text" name="specialty" class="form-control" value="{{ old('specialty') }}">
                </div>
            </div>

            <hr class="my-3">

            {{-- Licensed States --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-semibold mb-0">
                        Licensed States
                        <span id="state-count" class="badge bg-primary ms-2"
                              style="{{ count($selectedStates) ? '' : 'display:none' }}">
                            {{ count($selectedStates) }} selected
                        </span>
                    </label>
                    <div class="d-flex gap-2">
                        <button type="button" id="select-all-states" class="btn btn-sm btn-outline-primary">Select All</button>
                        <button type="button" id="clear-all-states" class="btn btn-sm btn-outline-secondary">Clear All</button>
                    </div>
                </div>

                <div class="border rounded p-3" style="max-height:240px; overflow-y:auto; background:#fafafa;">
                    <div class="row g-1">
                        @foreach($usStates as $abbr => $name)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input state-checkbox" type="checkbox"
                                       name="licensed_states[]" value="{{ $abbr }}"
                                       id="state_{{ $abbr }}"
                                       {{ in_array($abbr, $selectedStates) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="state_{{ $abbr }}">
                                    <span class="fw-semibold text-primary" style="font-size:.75rem;">{{ $abbr }}</span>
                                    <span class="text-muted" style="font-size:.75rem;"> {{ $name }}</span>
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @error('licensed_states')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">Create Clinician</button>
                <a href="{{ route('admin.clinicians.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkboxes = document.querySelectorAll('.state-checkbox');
    var countBadge = document.getElementById('state-count');

    function updateCount() {
        var checked = document.querySelectorAll('.state-checkbox:checked').length;
        if (checked > 0) {
            countBadge.textContent = checked + ' selected';
            countBadge.style.display = '';
        } else {
            countBadge.style.display = 'none';
        }
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    document.getElementById('select-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = true; });
        updateCount();
    });

    document.getElementById('clear-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        updateCount();
    });

    updateCount();
});
</script>
@endsection
