@extends('layouts.admin')

@section('title', 'Edit Clinician')
@section('page-title', 'Edit Clinician — ' . $clinician->full_name)

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

// Build existing license map keyed by state abbreviation
$existingLicenses = [];
foreach ($clinician->licensed_states ?? [] as $lic) {
    if (is_array($lic) && isset($lic['state'])) {
        $existingLicenses[$lic['state']] = [
            'number' => $lic['license_number'] ?? '',
            'expiry' => $lic['expiry_date'] ?? '',
        ];
    }
}
$licenseInfo = old('license_info', $existingLicenses);
$hasLicenses = count($licenseInfo) > 0;
@endphp

@section('content')
<div class="card" style="max-width:900px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Edit Clinician Details</h6>
        <a href="{{ route('admin.clinicians.show', $clinician->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">

        @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.clinicians.update', $clinician->id) }}">
            @csrf
            @method('PUT')

            {{-- Account --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $clinician->user->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $clinician->user->email) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Optional password change --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">New Password <span class="text-muted fw-normal small">(leave blank to keep current)</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>

            <hr class="my-3">

            {{-- Professional details --}}
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Credentials <span class="text-danger">*</span></label>
                    <select name="credentials" class="form-select @error('credentials') is-invalid @enderror" required>
                        @foreach(['MD','DO','NP','PA'] as $c)
                        <option value="{{ $c }}" {{ old('credentials', $clinician->credentials) === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                    @error('credentials')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">NPI Number <span class="text-danger">*</span></label>
                    <input type="text" name="npi" class="form-control @error('npi') is-invalid @enderror"
                           value="{{ old('npi', $clinician->npi) }}" required>
                    @error('npi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Specialty</label>
                    <input type="text" name="specialty" class="form-control" value="{{ old('specialty', $clinician->specialty) }}">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach(['active','inactive','suspended'] as $s)
                        <option value="{{ $s }}" {{ old('status', $clinician->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Available for Cases</label>
                    <select name="is_available" class="form-select">
                        <option value="1" {{ old('is_available', $clinician->is_available ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('is_available', $clinician->is_available ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Max Daily Cases</label>
                    <input type="number" name="max_daily_cases" class="form-control" min="1" max="999"
                           value="{{ old('max_daily_cases', $clinician->max_daily_cases) }}">
                    <small class="text-muted">Overall daily ceiling. Blank means no limit.</small>
                </div>
            </div>

            <hr class="my-3">

            {{-- Capacity controls (Devin msgs 2248/2250) --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-3">Capacity</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Accepting New Cases</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="accepting_new_cases" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="acceptingNew" name="accepting_new_cases" value="1"
                               {{ old('accepting_new_cases', $clinician->accepting_new_cases ? '1' : '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="acceptingNew">Books open</label>
                    </div>
                    <small class="text-muted">Off = books full. Stops NEW patients. Their own check-ins still come through.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Max Daily New Cases</label>
                    <input type="number" name="max_daily_new_cases" class="form-control" min="1" max="999"
                           value="{{ old('max_daily_new_cases', $clinician->max_daily_new_cases) }}">
                    <small class="text-muted">First visits per day. Blank means no limit. Check-ins do not count.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Max Open Cases</label>
                    <input type="number" name="max_open_cases" class="form-control" min="1" max="999"
                           value="{{ old('max_open_cases', $clinician->max_open_cases) }}">
                    <small class="text-muted">Total open, non-terminal cases. Blank means no limit.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Refill Alert Threshold</label>
                    <input type="number" name="daily_refill_alert_threshold" class="form-control" min="1" max="999"
                           value="{{ old('daily_refill_alert_threshold', $clinician->daily_refill_alert_threshold) }}">
                    <small class="text-muted">Alerts the doctor admin past this many check-ins in a day. Never blocks.</small>
                </div>
            </div>

            <hr class="my-3">

            {{-- The eligibility gate, doctor side (Devin msg 2308). What this
                 doctor will take: which product categories, and which kinds of
                 visit. Both are hard blocks, and neither is overridden by
                 continuity, so unticking a category also stops that doctor's own
                 returning patients on that category. --}}
            <h6 class="text-muted text-uppercase small fw-semibold mb-1">What this doctor accepts</h6>
            <p class="text-muted small mb-3">
                Both of these block cases outright. A doctor is never given a case in a category they
                do not accept, including check-ins from patients they have treated before.
            </p>

            <div class="mb-3">
                <label class="form-label fw-semibold">Product categories</label>
                @if($categories->isEmpty())
                    <div class="alert alert-warning small mb-0">
                        No categories exist yet. Add them under Categories first.
                    </div>
                @else
                    <div class="row">
                        @foreach($categories as $category)
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="category_{{ $category->id }}"
                                           name="accepted_categories[]" value="{{ $category->id }}"
                                           {{ in_array($category->id, old('accepted_categories', $acceptedCategoryIds)) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="category_{{ $category->id }}">
                                        {{ $category->name }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">
                        None ticked means this doctor receives no cases at all.
                    </small>
                @endif
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Visit types</label>
                    <div class="form-check">
                        <input type="hidden" name="accepts_async_visits" value="0">
                        <input class="form-check-input" type="checkbox" id="acceptsAsync"
                               name="accepts_async_visits" value="1"
                               {{ old('accepts_async_visits', $clinician->accepts_async_visits ? '1' : '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="acceptsAsync">Asynchronous review</label>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="accepts_sync_visits" value="0">
                        <input class="form-check-input" type="checkbox" id="acceptsSync"
                               name="accepts_sync_visits" value="1"
                               {{ old('accepts_sync_visits', $clinician->accepts_sync_visits ? '1' : '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="acceptsSync">Synchronous video visit</label>
                    </div>
                    <small class="text-muted">
                        Whether a case needs video is decided by the patient's state, not here.
                    </small>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-semibold">Booking link</label>
                    <input type="url" name="scheduling_link" class="form-control" maxlength="500"
                           placeholder="https://calendly.com/..."
                           value="{{ old('scheduling_link', $clinician->scheduling_link) }}">
                    <small class="text-muted">
                        Where a patient books a live visit. Required for synchronous cases: without
                        it, a video case cannot be assigned to this doctor even with the box ticked,
                        because the patient would have no way to book.
                    </small>
                </div>
            </div>

            <hr class="my-3">

            {{-- Licensed States --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-semibold mb-0">
                        Licensed States <span class="text-danger">*</span>
                        <span id="state-count" @class(['badge', 'bg-primary', 'ms-2', 'd-none' => !$hasLicenses])>
                            {{ count($licenseInfo) }} selected
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
                                       value="{{ $abbr }}"
                                       id="state_{{ $abbr }}"
                                       {{ isset($licenseInfo[$abbr]) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="state_{{ $abbr }}">
                                    <span class="fw-semibold text-primary" style="font-size:.75rem;">{{ $abbr }}</span>
                                    <span class="text-muted" style="font-size:.75rem;"> {{ $name }}</span>
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div id="stateError" class="text-danger small mt-1" style="display:none">
                    <i class="bi bi-exclamation-circle me-1"></i>Please select at least one licensed state.
                </div>
            </div>

            {{-- License Details (per state) --}}
            <div class="mb-4">
                <div id="license-table-wrapper" @class(['d-none' => !$hasLicenses])>
                    <label class="form-label fw-semibold">
                        License Details <span class="text-danger">*</span>
                        <span class="text-muted fw-normal small ms-1">— required for each selected state</span>
                    </label>
                    <div class="border rounded overflow-hidden">
                        <table class="table table-sm mb-0" id="license-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:130px;">State</th>
                                    <th>License Number <span class="text-danger">*</span></th>
                                    <th style="width:180px;">Expiry Date <span class="text-danger">*</span></th>
                                </tr>
                            </thead>
                            <tbody id="license-tbody">
                                @foreach($licenseInfo as $abbr => $info)
                                <tr id="license-row-{{ $abbr }}">
                                    <td class="align-middle">
                                        <span class="badge bg-primary me-1">{{ $abbr }}</span>
                                        <small class="text-muted">{{ $usStates[$abbr] ?? '' }}</small>
                                        <input type="hidden" name="license_info[{{ $abbr }}][state]" value="{{ $abbr }}">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="license_info[{{ $abbr }}][number]"
                                               class="form-control form-control-sm @error("license_info.{$abbr}.number") is-invalid @enderror"
                                               value="{{ $info['number'] ?? '' }}"
                                               placeholder="e.g. MD-12345"
                                               required>
                                        @error("license_info.{$abbr}.number")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </td>
                                    <td>
                                        <input type="date"
                                               name="license_info[{{ $abbr }}][expiry]"
                                               class="form-control form-control-sm @error("license_info.{$abbr}.expiry") is-invalid @enderror"
                                               value="{{ $info['expiry'] ?? '' }}"
                                               required>
                                        @error("license_info.{$abbr}.expiry")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p id="license-empty" @class(['text-muted', 'small', 'mb-0', 'mt-1', 'd-none' => $hasLicenses])>
                    <i class="bi bi-info-circle me-1"></i>Select states above to enter license details.
                </p>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                <a href="{{ route('admin.clinicians.show', $clinician->id) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var stateNames = <?php echo json_encode($usStates); ?>;

    var checkboxes   = document.querySelectorAll('.state-checkbox');
    var countBadge   = document.getElementById('state-count');
    var tbody        = document.getElementById('license-tbody');
    var tableWrapper = document.getElementById('license-table-wrapper');
    var emptyMsg     = document.getElementById('license-empty');

    function updateCount() {
        var n = document.querySelectorAll('.state-checkbox:checked').length;
        countBadge.textContent = n + ' selected';
        countBadge.classList.toggle('d-none', n === 0);
    }

    function showTable() {
        tableWrapper.classList.remove('d-none');
        emptyMsg.classList.add('d-none');
    }

    function hideTable() {
        tableWrapper.classList.add('d-none');
        emptyMsg.classList.remove('d-none');
    }

    function addLicenseRow(abbr, existingNumber, existingExpiry) {
        if (document.getElementById('license-row-' + abbr)) return;
        var name = stateNames[abbr] || abbr;
        var tr = document.createElement('tr');
        tr.id = 'license-row-' + abbr;
        tr.innerHTML =
            '<td class="align-middle">'
            +   '<span class="badge bg-primary me-1">' + abbr + '</span>'
            +   '<small class="text-muted">' + name + '</small>'
            +   '<input type="hidden" name="license_info[' + abbr + '][state]" value="' + abbr + '">'
            + '</td>'
            + '<td>'
            +   '<input type="text" name="license_info[' + abbr + '][number]"'
            +          ' class="form-control form-control-sm"'
            +          ' value="' + (existingNumber || '') + '"'
            +          ' placeholder="e.g. MD-12345" required>'
            + '</td>'
            + '<td>'
            +   '<input type="date" name="license_info[' + abbr + '][expiry]"'
            +          ' class="form-control form-control-sm"'
            +          ' value="' + (existingExpiry || '') + '" required>'
            + '</td>';
        tbody.appendChild(tr);
        showTable();
    }

    function removeLicenseRow(abbr) {
        var row = document.getElementById('license-row-' + abbr);
        if (row) row.remove();
        if (tbody.children.length === 0) hideTable();
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (this.checked) {
                addLicenseRow(this.value);
            } else {
                removeLicenseRow(this.value);
            }
            updateCount();
        });
    });

    document.getElementById('select-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) {
            if (!cb.checked) { cb.checked = true; addLicenseRow(cb.value); }
        });
        updateCount();
    });

    document.getElementById('clear-all-states').addEventListener('click', function () {
        checkboxes.forEach(function (cb) {
            cb.checked = false;
            removeLicenseRow(cb.value);
        });
        updateCount();
    });

    updateCount();

    document.querySelector('form').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('.state-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            var err = document.getElementById('stateError');
            err.style.display = 'block';
            document.querySelector('.border.rounded.p-3').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    document.querySelectorAll('.state-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (this.checked) document.getElementById('stateError').style.display = 'none';
        });
    });
});
</script>
@endsection
