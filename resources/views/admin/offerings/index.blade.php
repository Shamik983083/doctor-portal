@extends('layouts.admin')

@section('title', 'Offerings')
@section('page-title', 'Offerings')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Offerings</h6>
        <a href="{{ route('admin.offerings.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Offering
        </a>
    </div>
    <div class="card-header bg-light border-top-0">
        <form class="d-flex gap-2 flex-wrap">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name..."
                   value="{{ request('search') }}" style="max-width:220px;">
            <select name="partner_id" class="form-select form-select-sm w-auto">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                <option value="{{ $p->id }}" {{ request('partner_id')==$p->id?'selected':'' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="type" class="form-select form-select-sm w-auto">
                <option value="">All Types</option>
                @foreach(['medication','compound','supply'] as $t)
                <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <select name="approval" class="form-select form-select-sm w-auto">
                <option value="">All Approvals</option>
                <option value="pending"  {{ request('approval')==='pending'  ?'selected':'' }}>Pending</option>
                <option value="approved" {{ request('approval')==='approved' ?'selected':'' }}>Approved</option>
                <option value="rejected" {{ request('approval')==='rejected' ?'selected':'' }}>Rejected</option>
            </select>
            <select name="active" class="form-select form-select-sm w-auto">
                <option value="">All Statuses</option>
                <option value="1" {{ request('active')==='1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('active')==='0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filter</button>
            @if(request()->hasAny(['search','partner_id','type','active','approval']))
                <a href="{{ route('admin.offerings.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Partner</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Approval</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                    <tr>
                        <td>
                            <strong>{{ $offering->name }}</strong>
                            @if($offering->internal_name)
                                <br><span class="text-muted small">{{ $offering->internal_name }}</span>
                            @endif
                        </td>
                        <td>{{ $offering->partner->name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($offering->type) }}</span></td>
                        <td><span class="text-muted small">{{ $offering->category?->name ?? '—' }}</span></td>
                        <td>
                            @if($offering->approval_status === 'approved')
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                    <i class="bi bi-check-circle me-1"></i>Approved
                                </span>
                            @elseif($offering->approval_status === 'rejected')
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                    <i class="bi bi-x-circle me-1"></i>Rejected
                                </span>
                            @else
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                    <i class="bi bi-clock me-1"></i>Pending
                                </span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.offerings.toggle-status', $offering->id) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm border-0 p-0">
                                    <span class="badge {{ $offering->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $offering->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </button>
                            </form>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            @if($offering->approval_status === 'pending')
                                <form method="POST" action="{{ route('admin.offerings.approve', $offering->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger" title="Reject"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                                        data-name="{{ $offering->name }}">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            @elseif($offering->approval_status === 'approved')
                                <button class="btn btn-sm btn-outline-secondary" title="Revoke approval"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                                        data-name="{{ $offering->name }}">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            @elseif($offering->approval_status === 'rejected')
                                <form method="POST" action="{{ route('admin.offerings.approve', $offering->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger" title="Update rejection note"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-action="{{ route('admin.offerings.reject', $offering->id) }}"
                                        data-name="{{ $offering->name }}"
                                        data-note="{{ $offering->rejection_note }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            @endif
                            <a href="{{ route('admin.offerings.show', $offering->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.offerings.destroy', $offering->id) }}"
                                  class="d-inline" onsubmit="return confirm('Delete {{ addslashes($offering->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-capsule fs-2 d-block mb-2 opacity-25"></i>No offerings found.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($offerings->hasPages())
    <div class="card-footer">{{ $offerings->links() }}</div>
    @endif
</div>
@endsection

{{-- Rejection note modal (shared for all reject/revoke actions) --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="rejectModalLabel">
                    <i class="bi bi-x-octagon text-danger me-2"></i>Reject Offering
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Rejecting: <strong id="rejectOfferingName"></strong>
                    </p>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">
                            Rejection Note <span class="text-danger">*</span>
                        </label>
                        <textarea id="rejectNote" name="rejection_note" class="form-control" rows="4"
                                  placeholder="Explain why this offering is being rejected so the partner knows what to fix…"
                                  required maxlength="1000"></textarea>
                        <div class="form-text">This note is visible to the partner.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('rejectOfferingName').textContent = btn.dataset.name;
    document.getElementById('rejectForm').action = btn.dataset.action;
    document.getElementById('rejectNote').value = btn.dataset.note || '';
});
</script>
@endsection
