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
                        <button type="submit" class="btn btn-sm btn-success" id="submitBtn" disabled
                                title="Select at least one case to enable">
                            <i class="bi bi-arrow-right-circle me-1"></i>
                            <span id="submitBtnLabel">Reassign Selected</span>
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

{{-- ── Confirm Reassign Modal ──────────────────────────────────────── --}}
<div class="modal fade" id="confirmReassignModal" tabindex="-1" aria-labelledby="confirmReassignLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;overflow:hidden;">

            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle"
                         style="width:42px;height:42px;background:#fff3cd;flex-shrink:0;">
                        <i class="bi bi-arrow-left-right" style="font-size:1.1rem;color:#b45309;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" id="confirmReassignLabel" style="font-size:1rem;">
                        Confirm Reassignment
                    </h5>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 pt-3 pb-2">
                <p class="text-secondary mb-2" style="font-size:.88rem;" id="confirmReassignMsg">
                    You are about to reassign <strong id="confirmCaseCount">0</strong> case(s)
                    to <strong id="confirmTargetName">the selected clinician</strong>.
                </p>
                <div class="rounded p-2 px-3 mb-1" style="background:#fff8ec;border:1px solid #fde68a;font-size:.8rem;color:#92400e;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    This action cannot be undone. The clinicians will be notified.
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-2 gap-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="font-size:.875rem;">
                    Cancel
                </button>
                <button type="button" class="btn btn-warning px-4 fw-semibold" id="confirmReassignBtn" style="font-size:.875rem;color:#1c1917;">
                    <i class="bi bi-arrow-left-right me-1"></i>Yes, Reassign
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    var selectAll     = document.getElementById('selectAll');
    var checkboxes    = document.querySelectorAll('.case-checkbox');
    var submitBtn     = document.getElementById('submitBtn');
    var submitBtnLabel= document.getElementById('submitBtnLabel');
    var selectedCount = document.getElementById('selectedCount');
    var toSelect      = document.querySelector('[name="to_clinician_id"]');
    var form          = document.getElementById('reassignForm');

    // Bootstrap modal instance
    var modalEl       = document.getElementById('confirmReassignModal');
    var bsModal       = modalEl ? new bootstrap.Modal(modalEl) : null;
    var confirmBtn    = document.getElementById('confirmReassignBtn');

    function updateState() {
        var checked  = document.querySelectorAll('.case-checkbox:checked').length;
        var hasTarget = toSelect && toSelect.value;

        if (selectedCount) selectedCount.textContent = checked;

        var enabled = checked > 0 && hasTarget;
        if (submitBtn) {
            submitBtn.disabled = !enabled;
            submitBtn.title    = enabled ? '' :
                (checked === 0 && !hasTarget ? 'Select cases and a target clinician' :
                 checked === 0              ? 'Select at least one case' :
                                              'Choose a target clinician');
        }
        if (submitBtnLabel) {
            submitBtnLabel.textContent = checked > 0
                ? 'Reassign ' + checked + ' Case' + (checked === 1 ? '' : 's')
                : 'Reassign Selected';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            updateState();
        });
    }

    checkboxes.forEach(function (cb) { cb.addEventListener('change', updateState); });
    if (toSelect) toSelect.addEventListener('change', updateState);

    // Intercept form submit — show modal instead of browser confirm().
    var confirmed = false;

    if (form && bsModal) {
        form.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.case-checkbox:checked').length;
            if (!checked) { e.preventDefault(); return; }

            if (confirmed) {
                confirmed = false; // reset for potential future use
                return;            // let the form submit normally
            }

            e.preventDefault();

            // Populate modal with live counts + target name.
            var targetOpt  = toSelect && toSelect.selectedIndex >= 0
                ? toSelect.options[toSelect.selectedIndex]
                : null;
            var targetName = (targetOpt && targetOpt.value) ? targetOpt.text : 'the selected clinician';
            document.getElementById('confirmCaseCount').textContent  = checked;
            document.getElementById('confirmTargetName').textContent = targetName;

            bsModal.show();
        });
    }

    // Confirm button inside modal → set flag and submit.
    if (confirmBtn && form) {
        confirmBtn.addEventListener('click', function () {
            confirmed = true;
            if (bsModal) bsModal.hide();
            form.submit();
        });
    }
})();
</script>
@endsection
