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
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name..." value="{{ request('search') }}" style="max-width:220px;">
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
            <button class="btn btn-outline-secondary btn-sm">Filter</button>
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
                        <th>Price</th>
                        <th>States</th>
                        <th>Controlled</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                    <tr>
                        <td><strong>{{ $offering->name }}</strong></td>
                        <td>{{ $offering->partner->name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($offering->type) }}</span></td>
                        <td>{{ $offering->price ? '$'.number_format($offering->price, 2) : '—' }}</td>
                        <td>
                            @if($offering->available_states)
                                {{ implode(', ', array_slice($offering->available_states, 0, 3)) }}
                                @if(count($offering->available_states) > 3)<small class="text-muted"> +{{ count($offering->available_states)-3 }}</small>@endif
                            @else <span class="text-muted small">All</span>
                            @endif
                        </td>
                        <td>{{ $offering->is_controlled_substance ? '⚠️ Yes' : 'No' }}</td>
                        <td>
                            <span class="badge {{ $offering->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $offering->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td><a href="{{ route('admin.offerings.show', $offering->id) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No offerings yet.</td></tr>
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
