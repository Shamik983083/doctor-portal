@extends('layouts.admin')

@section('title', 'New Supervisor Physician')
@section('page-title', 'New Supervisor Physician')

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
$oldStates = old('licensed_states', []);
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div class="ma-eyebrow">Super Admin</div>
                <div class="ma-title">Create supervisor physician</div>
                <div class="ma-sub">The new user will be able to log in immediately.</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.supervisor-physicians.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">NPI <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="npi" class="form-control @error('npi') is-invalid @enderror" value="{{ old('npi') }}" maxlength="20" placeholder="10-digit NPI">
                        @error('npi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Licensed States <span class="text-danger">*</span>
                            <span class="ms-2" id="state-count-badge"></span>
                        </label>
                        @error('licensed_states')
                            <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror
                        <div class="border rounded p-2" style="max-height:240px;overflow-y:auto;">
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="select-all-states">Select All</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-all-states">Clear All</button>
                            </div>
                            <div class="row g-1">
                                @foreach($usStates as $abbr => $label)
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input state-checkbox" type="checkbox"
                                               id="state_{{ $abbr }}"
                                               name="licensed_states[]"
                                               value="{{ $abbr }}"
                                               {{ in_array($abbr, $oldStates) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="state_{{ $abbr }}">
                                            <strong>{{ $abbr }}</strong> {{ $label }}
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-text">Select at least one state.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.supervisor-physicians.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var checkboxes = document.querySelectorAll('.state-checkbox');
    var badge = document.getElementById('state-count-badge');

    function updateBadge() {
        var n = document.querySelectorAll('.state-checkbox:checked').length;
        badge.innerHTML = n > 0
            ? '<span class="badge bg-primary">' + n + ' selected</span>'
            : '';
    }

    checkboxes.forEach(function (cb) { cb.addEventListener('change', updateBadge); });

    document.getElementById('select-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = true; });
        updateBadge();
    });

    document.getElementById('clear-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        updateBadge();
    });

    updateBadge();
})();
</script>
@endsection
