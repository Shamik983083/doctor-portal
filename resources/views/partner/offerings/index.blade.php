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
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <option value="medication" @selected(request('type') === 'medication')>Medication</option>
                    <option value="compound"   @selected(request('type') === 'compound')>Compound</option>
                    <option value="supply"     @selected(request('type') === 'supply')>Supply</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a href="{{ route('partner.offerings.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
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
                    <th>SKU</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>States</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($offerings as $offering)
                <tr>
                    <td class="fw-medium">{{ $offering->name }}</td>
                    <td><code>{{ $offering->sku ?? '—' }}</code></td>
                    <td><span class="badge bg-light text-dark border text-capitalize">{{ $offering->type }}</span></td>
                    <td>{{ $offering->price ? '$'.number_format($offering->price, 2) : '—' }}</td>
                    <td>
                        @if(empty($offering->available_states))
                            <span class="badge bg-success bg-opacity-10 text-success">All States</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ count($offering->available_states) }} states</span>
                        @endif
                    </td>
                    <td>
                        @if($offering->is_active)
                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('partner.offerings.show', $offering->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
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
