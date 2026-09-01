@extends('layouts.admin')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@section('content')

@php
$actionBadge = [
    'created'      => 'bg-success',
    'updated'      => 'bg-primary',
    'deleted'      => 'bg-danger',
    'restored'     => 'bg-warning text-dark',
    'force_deleted'=> 'bg-dark',
];
@endphp

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Model</label>
                <select name="auditable_type" class="form-select form-select-sm" style="width:auto">
                    <option value="">All models</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ $filterType === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Action</label>
                <select name="action" class="form-select form-select-sm" style="width:auto">
                    <option value="">All actions</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ $filterAction === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Actor</label>
                <select name="actor_id" class="form-select form-select-sm" style="width:auto">
                    <option value="">All actors</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor->id }}" {{ (string)$filterActorId === (string)$actor->id ? 'selected' : '' }}>
                            {{ $actor->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="{{ $filterDateFrom }}" style="width:140px">
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="{{ $filterDateTo }}" style="width:140px">
            </div>
            <div class="col-sm-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary">Filter</button>
                @if($filterType || $filterAction || $filterActorId || $filterDateFrom || $filterDateTo)
                    <a href="{{ route('admin.audit-log.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            Audit entries
            <span class="text-muted fw-normal small">({{ number_format($logs->total()) }})</span>
        </h6>
        <span class="text-muted small">Showing newest first &middot; 50 per page</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:160px">When</th>
                        <th style="width:160px">Actor</th>
                        <th style="width:110px">Action</th>
                        <th style="width:120px">Model</th>
                        <th>Record</th>
                        <th style="width:80px">Changes</th>
                        <th style="width:180px">Context</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-muted small text-nowrap">
                            {{ $log->created_at->format('d M Y H:i:s') }}
                        </td>
                        <td>
                            @if($log->actor_name)
                                <span class="fw-semibold">{{ $log->actor_name }}</span>
                            @else
                                <span class="text-muted small fst-italic">system / console</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $actionBadge[$log->action] ?? 'bg-secondary' }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $log->auditable_type }}</td>
                        <td>
                            <span class="text-truncate d-inline-block" style="max-width:260px;"
                                  title="{{ $log->auditable_label }}">
                                {{ $log->auditable_label ?? "#{$log->auditable_id}" }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($log->action === 'updated' && !empty($log->diff['new']))
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#diffModal"
                                        data-diff="{{ json_encode($log->diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}"
                                        data-label="{{ $log->auditable_label }}"
                                        data-action="{{ $log->action }}"
                                        title="View diff">
                                    {{ count($log->diff['new'] ?? []) }} field{{ count($log->diff['new'] ?? []) !== 1 ? 's' : '' }}
                                </button>
                            @elseif($log->action === 'created' && !empty($log->diff))
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#diffModal"
                                        data-diff="{{ json_encode($log->diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}"
                                        data-label="{{ $log->auditable_label }}"
                                        data-action="{{ $log->action }}"
                                        title="View created values">
                                    {{ count($log->diff) }} field{{ count($log->diff) !== 1 ? 's' : '' }}
                                </button>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted small text-truncate" style="max-width:180px;"
                            title="{{ $log->context }}">
                            {{ $log->context }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                            No audit entries match the current filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($logs->hasPages())
    <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>

{{-- Diff modal --}}
<div class="modal fade" id="diffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title mb-0">
                    <span id="diffModalAction" class="badge bg-secondary me-2"></span>
                    <span id="diffModalLabel"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Updated: old/new table --}}
                <div id="diffUpdatedView">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:180px">Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                        </thead>
                        <tbody id="diffUpdatedBody"></tbody>
                    </table>
                </div>
                {{-- Created: key/value list --}}
                <div id="diffCreatedView" style="display:none">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:180px">Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody id="diffCreatedBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    var modal = document.getElementById('diffModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (e) {
        var btn    = e.relatedTarget;
        var action = btn.dataset.action;
        var label  = btn.dataset.label;
        var diff;

        try { diff = JSON.parse(btn.dataset.diff); } catch (_) { diff = {}; }

        document.getElementById('diffModalAction').textContent = action;
        document.getElementById('diffModalLabel').textContent  = label || '';

        var updatedView = document.getElementById('diffUpdatedView');
        var createdView = document.getElementById('diffCreatedView');
        var updatedBody = document.getElementById('diffUpdatedBody');
        var createdBody = document.getElementById('diffCreatedBody');

        updatedBody.innerHTML = '';
        createdBody.innerHTML = '';

        if (action === 'updated') {
            updatedView.style.display = '';
            createdView.style.display = 'none';

            var newFields = diff.new || {};
            var oldFields = diff.old || {};

            Object.keys(newFields).forEach(function (key) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="fw-semibold text-muted">' + escHtml(key) + '</td>' +
                    '<td class="text-danger font-monospace" style="word-break:break-all;">' + escHtml(stringify(oldFields[key])) + '</td>' +
                    '<td class="text-success font-monospace" style="word-break:break-all;">' + escHtml(stringify(newFields[key])) + '</td>';
                updatedBody.appendChild(tr);
            });
        } else {
            updatedView.style.display = 'none';
            createdView.style.display = '';

            Object.keys(diff).forEach(function (key) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="fw-semibold text-muted">' + escHtml(key) + '</td>' +
                    '<td class="font-monospace" style="word-break:break-all;">' + escHtml(stringify(diff[key])) + '</td>';
                createdBody.appendChild(tr);
            });
        }
    });

    function stringify(val) {
        if (val === null || val === undefined) return '(null)';
        if (typeof val === 'object') return JSON.stringify(val);
        return String(val);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
@endsection
