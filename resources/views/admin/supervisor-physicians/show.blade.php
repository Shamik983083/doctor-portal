@extends('layouts.admin')

@section('title', $user->name)
@section('page-title', $user->name)

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
$profile       = $user->supervisorPhysician;
$savedStates   = $profile?->licensed_states ?? [];
@endphp

@section('content')
<div class="row g-3">
    {{-- Account summary --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="ma-eyebrow">Supervisor Physician</div>
                <div class="ma-title">{{ $user->name }}</div>
                <div class="ma-sub">{{ $user->email }}</div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">NPI</dt>
                    <dd class="col-sm-8">{{ $profile?->npi ?: '—' }}</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        @if($user->is_active ?? true)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Licensed States</dt>
                    <dd class="col-sm-8">
                        @if(count($savedStates))
                            {{ implode(', ', $savedStates) }}
                            <span class="text-muted small">({{ count($savedStates) }})</span>
                        @else
                            <span class="text-muted">None selected</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8">{{ $user->created_at->format('M j, Y g:i A') }}</dd>

                    <dt class="col-sm-4">Last updated</dt>
                    <dd class="col-sm-8">{{ $user->updated_at->format('M j, Y g:i A') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    @if($user->id !== Auth::id())
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="ma-eyebrow">Actions</div>
                <div class="ma-title">Manage account</div>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <form method="POST" action="{{ route('admin.supervisor-physicians.toggle-active', $user->id) }}">
                    @csrf @method('PATCH')
                    @if($user->is_active ?? true)
                        <button class="btn btn-outline-warning w-100" onclick="return confirm('Deactivate {{ addslashes($user->name) }}?')">
                            <i class="bi bi-pause-circle"></i> Deactivate account
                        </button>
                    @else
                        <button class="btn btn-outline-success w-100" onclick="return confirm('Reactivate {{ addslashes($user->name) }}?')">
                            <i class="bi bi-play-circle"></i> Reactivate account
                        </button>
                    @endif
                </form>

                <hr>

                <form method="POST" action="{{ route('admin.supervisor-physicians.destroy', $user->id) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger w-100" onclick="return confirm('Permanently delete {{ addslashes($user->name) }}? This cannot be undone.')">
                        <i class="bi bi-trash"></i> Delete account
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Edit NPI + Licensed States --}}
<div class="card mt-4">
    <div class="card-header">
        <div class="ma-eyebrow">Profile</div>
        <div class="ma-title">NPI &amp; Licensed States</div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.supervisor-physicians.update', $user->id) }}">
            @csrf @method('PUT')

            <div class="mb-3" style="max-width:320px">
                <label class="form-label">NPI <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="npi" class="form-control @error('npi') is-invalid @enderror"
                       value="{{ old('npi', $profile?->npi) }}" maxlength="20" placeholder="10-digit NPI">
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
                                       {{ in_array($abbr, old('licensed_states', $savedStates)) ? 'checked' : '' }}>
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

            <button type="submit" class="btn btn-primary">Save profile</button>
        </form>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('admin.supervisor-physicians.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to supervisor physicians
    </a>
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
