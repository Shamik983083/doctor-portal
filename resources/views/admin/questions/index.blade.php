@extends('layouts.admin')

@section('title', 'Question Bank')
@section('page-title', 'Question Bank')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h6 class="mb-0">Question Bank</h6>
                <small class="text-muted">View and manage all questionnaire questions</small>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <form class="d-flex gap-2 flex-wrap align-items-center" method="GET" id="filter-form">
                    <input type="text" name="search" class="form-control form-control-sm" style="width:180px"
                           placeholder="Search questions..." value="{{ request('search') }}">
                    <select name="type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                        <option value="">All Field Types</option>
                        @foreach($fieldTypes as $id => $label)
                            <option value="{{ $id }}" {{ request('type') === $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="questionnaire_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                        <option value="">All Questionnaires</option>
                        @foreach($questionnaires as $q)
                            <option value="{{ $q->id }}" {{ request('questionnaire_id') == $q->id ? 'selected' : '' }}>{{ $q->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    @if(request()->hasAny(['search','type','questionnaire_id','status']))
                        <a href="{{ route('admin.questions.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- Bulk delete bar --}}
    <div id="bulk-bar" class="d-none bg-light border-bottom px-3 py-2 d-flex align-items-center gap-3">
        <span id="bulk-count" class="small fw-semibold text-dark"></span>
        <form method="POST" action="{{ route('admin.questions.bulk-destroy') }}" id="bulk-delete-form"
              onsubmit="return confirm('Delete selected questions? This cannot be undone.')">
            @csrf
            <div id="bulk-ids"></div>
            <button type="submit" class="btn btn-sm btn-danger">
                <i class="bi bi-trash me-1"></i>Delete Selected
            </button>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:36px">
                            <input type="checkbox" class="form-check-input" id="check-all">
                        </th>
                        <th style="width:80px" class="text-center">Actions</th>
                        <th>Question</th>
                        <th>Field Type</th>
                        <th>Required</th>
                        <th>Questionnaire</th>
                        <th>Added On</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($questions as $q)
                    @php
                        $typeBadges = [
                            'hidden'=>'bg-secondary','input'=>'bg-secondary','email'=>'bg-secondary',
                            'textarea'=>'bg-secondary','date'=>'bg-info text-dark','select'=>'bg-primary',
                            'multiselect'=>'bg-primary','radio'=>'bg-warning text-dark','checkbox'=>'bg-warning text-dark',
                            'file'=>'bg-secondary','number'=>'bg-secondary','height'=>'bg-success',
                            'weight'=>'bg-success','bmi'=>'bg-success',
                        ];
                        $badge = $typeBadges[$q->type] ?? 'bg-secondary';
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="{{ $q->id }}">
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <button type="button" class="btn btn-sm btn-link p-0 text-primary view-btn"
                                        data-id="{{ $q->id }}" title="View Details">
                                    <i class="bi bi-eye fs-5"></i>
                                </button>
                                <a href="{{ route('admin.questions.edit', $q->id) }}"
                                   class="btn btn-sm btn-link p-0 text-secondary" title="Edit">
                                    <i class="bi bi-pencil fs-5"></i>
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="fw-semibold small">{{ Str::limit($q->question, 80) }}</span>
                            @if($q->key)
                                <br><small class="text-muted font-monospace">{{ $q->key }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $badge }} bg-opacity-85">
                                {{ $fieldTypes[$q->type] ?? $q->type }}
                            </span>
                        </td>
                        <td>
                            @if($q->is_required)
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Yes</span>
                            @else
                                <span class="text-muted small">No</span>
                            @endif
                        </td>
                        <td>
                            @if($q->questionnaire)
                                <a href="{{ route('admin.questionnaires.show', $q->questionnaire->id) }}"
                                   class="text-decoration-none small">
                                    {{ $q->questionnaire->name }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $q->created_at->format('d M Y') }}</small></td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input status-toggle" type="checkbox"
                                       data-id="{{ $q->id }}"
                                       data-url="{{ route('admin.questions.toggle-status', $q->id) }}"
                                       {{ $q->is_active ? 'checked' : '' }}>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-question-circle fs-2 d-block mb-2 opacity-25"></i>
                            No questions found.
                            <a href="{{ route('admin.questionnaires.create') }}">Create a questionnaire</a> to add questions.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($questions->hasPages())
    <div class="card-footer">{{ $questions->links() }}</div>
    @endif
</div>

{{-- Details Modal --}}
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-semibold" id="detailsModalLabel">Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2" id="details-body">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
/* ── View Details modal ─────────────────────────────────── */
document.querySelectorAll('.view-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id   = this.dataset.id;
        var body = document.getElementById('details-body');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
        var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();

        fetch('{{ url('admin/questions') }}/' + id, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) { body.innerHTML = buildDetails(d); })
        .catch(function()  { body.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
    });
});

function buildDetails(d) {
    var rows = '';

    function row(label, value) {
        return '<tr><th class="text-muted fw-semibold" style="width:45%;font-size:.8rem">' + label + '</th>'
             + '<td style="font-size:.85rem">' + value + '</td></tr>';
    }

    rows += row('Question', '<span class="fw-semibold">' + esc(d.question) + '</span>');

    if (d.questionnaire) {
        rows += row('Assigned To', esc(d.questionnaire.name));
    }

    if (d.key) {
        rows += row('Field Key', '<span class="font-monospace small">' + esc(d.key) + '</span>');
    }

    rows += row('Field Type', '<span class="badge bg-primary bg-opacity-75">' + esc(d.type_label) + '</span>');

    if (d.placeholder) {
        rows += row('Placeholder', '<em class="text-muted">' + esc(d.placeholder) + '</em>');
    }

    rows += row('Is Required?', d.is_required
        ? '<span class="text-danger fw-semibold">Yes</span>'
        : '<span class="text-muted">No</span>');

    rows += row('Is Readonly?', d.is_readonly
        ? '<span class="text-warning fw-semibold">Yes</span>'
        : '<span class="text-muted">No</span>');

    /* Options */
    if (d.option_types.indexOf(d.type) !== -1 && d.options && d.options.length) {
        var opts = '<ul class="mb-0 ps-3 small">';
        d.options.forEach(function(o) {
            var val   = (typeof o === 'object') ? o.value : o;
            var disq  = (typeof o === 'object') && o.is_disqualify;
            opts += '<li class="' + (disq ? 'text-danger' : '') + '">'
                  + esc(val)
                  + (disq ? ' <i class="bi bi-slash-circle ms-1" title="Disqualify"></i>' : '')
                  + '</li>';
        });
        opts += '</ul>';
        rows += row('Options', opts);
    }

    rows += row('Added On', esc(d.created_at));
    rows += row('Status', d.is_active
        ? '<span class="badge bg-success rounded-pill px-3">Active</span>'
        : '<span class="badge bg-secondary rounded-pill px-3">Inactive</span>');

    return '<table class="table table-borderless table-sm mb-0">' + rows + '</table>';
}

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Status toggle ──────────────────────────────────────── */
document.querySelectorAll('.status-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var url = this.dataset.url;
        fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).catch(function() {
            toggle.checked = !toggle.checked; // revert on error
        });
    });
});

/* ── Bulk select ────────────────────────────────────────── */
var checkAll  = document.getElementById('check-all');
var bulkBar   = document.getElementById('bulk-bar');
var bulkCount = document.getElementById('bulk-count');
var bulkIds   = document.getElementById('bulk-ids');

function updateBulkBar() {
    var checked = document.querySelectorAll('.row-check:checked');
    if (checked.length) {
        bulkBar.classList.remove('d-none');
        bulkBar.classList.add('d-flex');
        bulkCount.textContent = checked.length + ' question(s) selected';
        bulkIds.innerHTML = '';
        checked.forEach(function(cb) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = cb.value;
            bulkIds.appendChild(inp);
        });
    } else {
        bulkBar.classList.add('d-none');
        bulkBar.classList.remove('d-flex');
    }
}

checkAll.addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = checkAll.checked; });
    updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(function(cb) {
    cb.addEventListener('change', updateBulkBar);
});
</script>
@endsection
