@extends('layouts.admin')

@section('title', 'Bulk Reassign Cases')
@section('page-title', 'Bulk Reassign Cases')

@section('content')

<style>
.step-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    background: #e0e7ff; color: #4361ee;
    font-size: .72rem; font-weight: 700; flex-shrink: 0;
}
.step-badge.done { background: #dcfce7; color: #16a34a; }
.reassign-toolbar {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}
.reassign-toolbar .sel-summary {
    font-size: .82rem; font-weight: 600; color: #475569;
    white-space: nowrap;
}
.reassign-toolbar .sel-summary span { color: #4361ee; }
#submitBtn {
    white-space: nowrap;
    min-width: 160px;
    font-size: .875rem;
    font-weight: 600;
    padding: 7px 18px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .15s;
}
#submitBtn:not(:disabled):hover { filter: brightness(1.07); }
.case-row-selected { background: #f0f4ff !important; }
</style>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- ── Step 1: Pick source clinician ──────────────────────────────── --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius:10px;">
            <div class="card-header bg-white border-bottom px-4 py-3" style="border-radius:10px 10px 0 0;">
                <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                    <span class="step-badge {{ $fromId ? 'done' : '' }}">
                        {{ $fromId ? '✓' : '1' }}
                    </span>
                    Select Source Clinician
                </h6>
            </div>
            <div class="card-body px-4 py-3">
                <form method="GET" action="{{ route('admin.clinicians.bulk-reassign') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-secondary mb-1">From Clinician</label>
                            <select name="from_clinician_id" class="form-select" onchange="this.form.submit()">
                                <option value="">— Choose a clinician —</option>
                                @foreach($clinicians as $c)
                                    <option value="{{ $c->id }}" {{ $fromId == $c->id ? 'selected' : '' }}>
                                        {{ $c->full_name }}
                                        @php $openCount = $c->cases()->whereIn('status',['assigned','approved','processing'])->count(); @endphp
                                        ({{ $openCount }} open)
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Only open (assigned / approved / processing) cases can be moved.</div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($fromId && $cases->count())
    {{-- ── Step 2: Select cases and target ────────────────────────────── --}}
    <div class="col-12">
        <form method="POST" action="{{ route('admin.clinicians.bulk-reassign.submit') }}" id="reassignForm">
            @csrf
            <div class="card border-0 shadow-sm" style="border-radius:10px; overflow:hidden;">

                {{-- Card header --}}
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                        <span class="step-badge">2</span>
                        Choose Cases &amp; Target Clinician
                    </h6>
                </div>

                {{-- Action toolbar --}}
                <div class="reassign-toolbar">
                    {{-- Select-all + count --}}
                    <div class="d-flex align-items-center gap-2 me-auto">
                        <input type="checkbox" class="form-check-input mt-0" id="selectAll" title="Select all">
                        <span class="sel-summary">
                            <span id="selectedCount">0</span> of {{ $cases->count() }} cases selected
                        </span>
                    </div>

                    {{-- Move-to select --}}
                    <label class="form-label mb-0 small fw-semibold text-secondary" style="white-space:nowrap;">
                        Move to:
                    </label>
                    <select name="to_clinician_id" class="form-select form-select-sm" style="min-width:200px; max-width:260px;" required>
                        <option value="">— Select target clinician —</option>
                        @foreach($clinicians as $c)
                            @if($c->id != $fromId)
                            <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                            @endif
                        @endforeach
                    </select>

                    {{-- Reassign button --}}
                    <button type="submit" class="btn btn-success" id="submitBtn" disabled
                            title="Select at least one case and a target clinician">
                        <i class="bi bi-arrow-right-circle"></i>
                        <span id="submitBtnLabel">Reassign Selected</span>
                    </button>
                </div>

                {{-- Cases table --}}
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle" style="font-size:.875rem;">
                            <thead style="background:#f8fafc; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b;">
                                <tr>
                                    <th style="width:44px; padding:10px 16px;"></th>
                                    <th style="padding:10px 12px;">Patient</th>
                                    <th style="padding:10px 12px;">Product / Offering</th>
                                    <th style="padding:10px 12px;">Status</th>
                                    <th style="padding:10px 12px;">Assigned</th>
                                    <th style="padding:10px 12px;">State</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cases as $case)
                                <tr class="case-row border-bottom" style="transition:background .1s; cursor:pointer;"
                                    onclick="toggleRow(this)">
                                    <td style="padding:12px 16px;" onclick="event.stopPropagation()">
                                        <input type="checkbox" name="case_ids[]" value="{{ $case->id }}"
                                               class="form-check-input mt-0 case-checkbox"
                                               onclick="event.stopPropagation(); syncRow(this)">
                                    </td>
                                    <td style="padding:12px;">
                                        <a href="{{ route('admin.cases.show', $case->uuid) }}" target="_blank"
                                           class="fw-semibold text-decoration-none"
                                           style="color:#1e293b; font-size:.875rem;"
                                           onclick="event.stopPropagation()">
                                            {{ $case->patient?->full_name ?? '—' }}
                                        </a>
                                        <div class="text-muted" style="font-size:.74rem;">{{ $case->patient?->email }}</div>
                                    </td>
                                    <td style="padding:12px; color:#475569;">
                                        {{ $case->caseOfferings->first()?->offering?->name ?? '—' }}
                                    </td>
                                    <td style="padding:12px;">
                                        <span class="badge rounded-pill
                                            @if($case->status === 'assigned') bg-primary
                                            @elseif($case->status === 'approved') bg-success
                                            @elseif($case->status === 'processing') bg-warning text-dark
                                            @else bg-secondary
                                            @endif"
                                            style="font-size:.72rem; padding:4px 10px;">
                                            {{ ucfirst($case->status) }}
                                        </span>
                                    </td>
                                    <td style="padding:12px; color:#64748b; font-size:.82rem;">
                                        {{ $case->assigned_at?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td style="padding:12px; color:#64748b; font-size:.82rem;">
                                        {{ $case->patient_state ?? $case->patient?->state ?? '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </form>
    </div>

    @elseif($fromId && $cases->isEmpty())
    <div class="col-12">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <span>This clinician has no open cases to reassign.</span>
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
// Click anywhere on a row to toggle its checkbox.
function toggleRow(tr) {
    var cb = tr.querySelector('.case-checkbox');
    if (cb) { cb.checked = !cb.checked; syncRow(cb); }
}
function syncRow(cb) {
    var tr = cb.closest('tr');
    if (tr) tr.classList.toggle('case-row-selected', cb.checked);
    if (typeof updateState === 'function') updateState();
}

(function () {
    var selectAll     = document.getElementById('selectAll');
    var checkboxes    = document.querySelectorAll('.case-checkbox');
    var submitBtn     = document.getElementById('submitBtn');
    var submitBtnLabel= document.getElementById('submitBtnLabel');
    var selectedCount = document.getElementById('selectedCount');
    var toSelect      = document.querySelector('[name="to_clinician_id"]');
    var form          = document.getElementById('reassignForm');

    var modalEl    = document.getElementById('confirmReassignModal');
    var confirmBtn = document.getElementById('confirmReassignBtn');
    var bsModal    = null;

    function getModal() {
        if (!bsModal && modalEl && typeof bootstrap !== 'undefined') {
            bsModal = new bootstrap.Modal(modalEl);
        }
        return bsModal;
    }

    window.updateState = function updateState() {
        var checked   = document.querySelectorAll('.case-checkbox:checked').length;
        var hasTarget = toSelect && toSelect.value;

        if (selectedCount) selectedCount.textContent = checked;

        // Sync select-all indeterminate state.
        if (selectAll) {
            selectAll.checked       = checked > 0 && checked === checkboxes.length;
            selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
        }

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
    };

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) {
                cb.checked = selectAll.checked;
                var tr = cb.closest('tr');
                if (tr) tr.classList.toggle('case-row-selected', cb.checked);
            });
            updateState();
        });
    }

    checkboxes.forEach(function (cb) { cb.addEventListener('change', updateState); });
    if (toSelect) toSelect.addEventListener('change', updateState);

    // Intercept form submit — show modal instead of browser confirm().
    var confirmed = false;

    if (form) {
        form.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.case-checkbox:checked').length;
            if (!checked) { e.preventDefault(); return; }

            if (confirmed) {
                confirmed = false;
                return; // let the form submit normally
            }

            e.preventDefault();

            // Populate modal with live counts + target name.
            var targetOpt  = toSelect && toSelect.selectedIndex >= 0
                ? toSelect.options[toSelect.selectedIndex]
                : null;
            var targetName = (targetOpt && targetOpt.value) ? targetOpt.text : 'the selected clinician';
            document.getElementById('confirmCaseCount').textContent  = checked;
            document.getElementById('confirmTargetName').textContent = targetName;

            getModal().show();
        });
    }

    // Confirm button inside modal → set flag and submit.
    if (confirmBtn && form) {
        confirmBtn.addEventListener('click', function () {
            confirmed = true;
            var m = getModal();
            if (m) m.hide();
            form.submit();
        });
    }
})();
</script>
@endsection
