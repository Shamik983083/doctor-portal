@extends('layouts.admin')

@section('title', 'Bulk Reassign Cases')
@section('page-title', 'Bulk Reassign Cases')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- Step 1: Pick source clinician --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person-check me-2"></i>Step 1 — Select Source Clinician</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.clinicians.bulk-reassign') }}" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">From Clinician</label>
                        <select name="from_clinician_id" class="form-select" onchange="this.form.submit()">
                            <option value="">— Choose a clinician —</option>
                            @foreach($clinicians as $c)
                                <option value="{{ $c->id }}" {{ $fromId == $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }}
                                    @php
                                        $openCount = $c->cases()
                                            ->whereIn('status', ['assigned','approved','processing'])
                                            ->count();
                                    @endphp
                                    ({{ $openCount }} open)
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Only open (assigned/approved/processing) cases can be moved.</div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($fromId && $cases->count())
    {{-- Step 2: Select cases and target --}}
    <div class="col-12">
        <form method="POST" action="{{ route('admin.clinicians.bulk-reassign.submit') }}" id="reassignForm">
            @csrf
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Step 2 — Choose Cases &amp; Target</h6>
                    <div class="d-flex align-items-center gap-3">
                        <label class="form-label mb-0 small fw-semibold">Move to:</label>
                        <select name="to_clinician_id" class="form-select form-select-sm" style="min-width:220px" required>
                            <option value="">— Select target clinician —</option>
                            @foreach($clinicians as $c)
                                @if($c->id != $fromId)
                                <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary" id="submitBtn" disabled>
                            <i class="bi bi-arrow-right-circle me-1"></i>Reassign Selected
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
                                    </th>
                                    <th>Patient</th>
                                    <th>Product / Offering</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>State</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cases as $case)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="case_ids[]" value="{{ $case->id }}"
                                               class="form-check-input case-checkbox">
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.cases.show', $case->uuid) }}" target="_blank" class="text-decoration-none">
                                            {{ $case->patient?->full_name ?? '—' }}
                                        </a>
                                        <small class="text-muted d-block">{{ $case->patient?->email }}</small>
                                    </td>
                                    <td>
                                        <small>{{ $case->caseOfferings->first()?->offering?->name ?? '—' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if($case->status === 'assigned') bg-primary
                                            @elseif($case->status === 'approved') bg-success
                                            @elseif($case->status === 'processing') bg-warning text-dark
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst($case->status) }}
                                        </span>
                                    </td>
                                    <td><small>{{ $case->assigned_at?->format('M d, Y') ?? '—' }}</small></td>
                                    <td><small>{{ $case->patient_state ?? $case->patient?->state ?? '—' }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer text-muted small">
                    <span id="selectedCount">0</span> of {{ $cases->count() }} cases selected
                </div>
            </div>
        </form>
    </div>

    @elseif($fromId && $cases->isEmpty())
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            This clinician has no open cases to reassign.
        </div>
    </div>
    @endif

</div>

@endsection

@section('scripts')
<script>
(function () {
    var selectAll      = document.getElementById('selectAll');
    var checkboxes     = document.querySelectorAll('.case-checkbox');
    var submitBtn      = document.getElementById('submitBtn');
    var selectedCount  = document.getElementById('selectedCount');
    var toSelect       = document.querySelector('[name="to_clinician_id"]');

    function updateState() {
        var checked = document.querySelectorAll('.case-checkbox:checked').length;
        if (selectedCount) selectedCount.textContent = checked;
        if (submitBtn) submitBtn.disabled = checked === 0 || !toSelect || !toSelect.value;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            updateState();
        });
    }

    checkboxes.forEach(function (cb) { cb.addEventListener('change', updateState); });
    if (toSelect) toSelect.addEventListener('change', updateState);

    document.getElementById('reassignForm') && document.getElementById('reassignForm').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('.case-checkbox:checked').length;
        if (!checked) { e.preventDefault(); return; }
        if (!confirm('Reassign ' + checked + ' case(s)? This cannot be undone.')) {
            e.preventDefault();
        }
    });
})();
</script>
@endsection
