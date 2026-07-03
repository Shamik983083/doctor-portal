@extends('layouts.partner')
@section('title', 'Offerings')
@section('page-title', 'Offerings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Manage the products and medications available through your integration.</p>
    <a href="{{ route('partner.offerings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Offering
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <option value="medication" @selected(request('type') === 'medication')>Medication</option>
                    <option value="compound"   @selected(request('type') === 'compound')>Compound</option>
                    <option value="supply"     @selected(request('type') === 'supply')>Supply</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="active" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Filter</button>
                @if(request()->hasAny(['search','type','active']))
                    <a href="{{ route('partner.offerings.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Internal Name</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>States</th>
                    <th>Approval</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($offerings as $offering)
                <tr>
                    <td class="fw-medium">{{ $offering->name }}</td>
                    <td><span class="text-muted small">{{ $offering->internal_name ?? '—' }}</span></td>
                    <td><span class="badge bg-light text-dark border text-capitalize">{{ $offering->type }}</span></td>
                    <td><span class="text-muted small">{{ $offering->category?->name ?? '—' }}</span></td>
                    <td>
                        @if(empty($offering->available_states))
                            <span class="badge bg-success bg-opacity-10 text-success">All States</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ count($offering->available_states) }} states</span>
                        @endif
                    </td>
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
                        <form method="POST" action="{{ route('partner.offerings.toggle-status', $offering->id) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm border-0 p-0">
                                @if($offering->is_active)
                                    <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactive</span>
                                @endif
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('partner.offerings.show', $offering->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('partner.offerings.destroy', $offering->id) }}"
                              class="d-inline" onsubmit="return confirm('Delete {{ addslashes($offering->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted" style="min-height:200px">
                        <i class="bi bi-box-seam fs-3 d-block mb-2 opacity-25"></i>
                        No offerings yet. <a href="{{ route('partner.offerings.create') }}">Create your first one.</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($offerings->hasPages())
    <div class="card-footer bg-white border-top">{{ $offerings->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
