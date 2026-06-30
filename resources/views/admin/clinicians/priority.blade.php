@extends('layouts.admin')

@section('title', 'Assignment Priority')
@section('page-title', 'Clinician Assignment Priority')

@section('content')

<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div class="small">
        <strong>How auto-assignment works:</strong>
        When a new case enters the <em>Waiting</em> queue, the system automatically assigns it to the
        highest-priority clinician who is <strong>Active</strong>, <strong>Available</strong>, and
        below their <strong>Max Case Load</strong>. Drag rows to re-order priority.
        If no clinician is eligible the case stays in the waiting queue for manual assignment.
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div id="save-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index:2000; display:none;">
    <div class="toast show align-items-center text-bg-success border-0">
        <div class="d-flex">
            <div class="toast-body" id="toast-msg">Saved.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="document.getElementById('save-toast').style.display='none'"></button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-sort-numeric-down me-2"></i>Drag to Set Priority</h6>
        <a href="{{ route('admin.clinicians.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Clinicians
        </a>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:60px;">Priority</th>
                        <th>Clinician</th>
                        <th>Credentials</th>
                        <th>Active Cases</th>
                        <th style="width:180px;">Max Case Load</th>
                        <th>Available</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="clinician-list">
                    @forelse($clinicians as $i => $clinician)
                    <tr data-id="{{ $clinician->id }}">
                        <td class="text-center drag-handle" style="cursor:grab; color:#aaa;">
                            <i class="bi bi-grip-vertical fs-5"></i>
                        </td>
                        <td>
                            <span class="badge bg-primary rank-badge">#{{ $i + 1 }}</span>
                        </td>
                        <td>
                            <strong>{{ $clinician->user->name }}</strong><br>
                            <small class="text-muted">{{ $clinician->user->email }}</small>
                        </td>
                        <td>{{ $clinician->credentials ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $clinician->active_cases_count >= $clinician->max_daily_cases ? 'bg-danger' : 'bg-success' }}">
                                {{ $clinician->active_cases_count }} / {{ $clinician->max_daily_cases }}
                            </span>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control form-control-sm case-load-input"
                                       value="{{ $clinician->max_daily_cases }}" min="1" max="999"
                                       style="max-width:70px;">
                                <button class="btn btn-outline-secondary btn-sm"
                                        onclick="saveCaseLoad(this)" type="button">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            @if($clinician->is_available && $clinician->status === 'active')
                                <span class="badge bg-success">Available</span>
                            @elseif(!$clinician->is_available)
                                <span class="badge bg-warning text-dark">Unavailable</span>
                            @else
                                <span class="badge bg-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $clinician->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($clinician->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No clinicians found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($clinicians->isNotEmpty())
    <div class="card-footer text-muted small">
        <i class="bi bi-grip-vertical me-1"></i> Drag rows to reorder. Changes save automatically.
        &nbsp;·&nbsp;
        <i class="bi bi-check-lg me-1 text-success"></i> Click the checkmark to save a Max Case Load change.
    </div>
    @endif
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
const REORDER_URL  = '{{ route('admin.clinicians.reorder') }}';
const CASELOAD_URL = '/admin/clinicians/{id}/case-load';
const CSRF         = document.querySelector('meta[name="csrf-token"]').content;

// --- Drag-and-drop reorder ---
const list = document.getElementById('clinician-list');

if (list) {
    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'table-active',
        onEnd: function () {
            // Update rank badges
            list.querySelectorAll('tr[data-id]').forEach((row, i) => {
                const badge = row.querySelector('.rank-badge');
                if (badge) badge.textContent = '#' + (i + 1);
            });

            const ids = [...list.querySelectorAll('tr[data-id]')].map(r => parseInt(r.dataset.id));

            fetch(REORDER_URL, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ids }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showToast('Priority order saved.');
            })
            .catch(() => showToast('Error saving order.'));
        }
    });
}

// --- Inline case-load save ---
function saveCaseLoad(btn) {
    const row   = btn.closest('tr[data-id]');
    const id    = row.dataset.id;
    const input = row.querySelector('.case-load-input');
    const val   = parseInt(input.value);

    if (!val || val < 1) return;

    fetch(CASELOAD_URL.replace('{id}', id), {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ max_daily_cases: val }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Max case load updated.');
            // Update the active/max badge in the same row
            const badge = row.querySelector('.badge.bg-success, .badge.bg-danger');
            if (badge) {
                const parts = badge.textContent.trim().split('/');
                const active = parseInt(parts[0]);
                badge.textContent = active + ' / ' + val;
                badge.className = 'badge ' + (active >= val ? 'bg-danger' : 'bg-success');
            }
        }
    })
    .catch(() => showToast('Error saving case load.'));
}

// --- Toast helper ---
function showToast(msg) {
    const toastEl = document.getElementById('save-toast');
    document.getElementById('toast-msg').textContent = msg;
    toastEl.style.display = 'block';
    setTimeout(() => { toastEl.style.display = 'none'; }, 2500);
}
</script>
@endsection
