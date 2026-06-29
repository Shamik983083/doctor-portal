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
            <select name="active" class="form-select form-select-sm w-auto">
                <option value="">All Statuses</option>
                <option value="1" {{ request('active')==='1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('active')==='0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filter</button>
            @if(request()->hasAny(['search','partner_id','type','active']))
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
                        <th>Internal Name</th>
                        <th>Partner</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>States</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                    <tr>
                        <td><strong>{{ $offering->name }}</strong></td>
                        <td><span class="text-muted small">{{ $offering->internal_name ?? '—' }}</span></td>
                        <td>{{ $offering->partner->name ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst($offering->type) }}</span></td>
                        <td><span class="text-muted small">{{ $offering->category?->name ?? '—' }}</span></td>
                        <td>
                            @if($offering->available_states && count($offering->available_states))
                                {{ implode(', ', array_slice($offering->available_states, 0, 3)) }}
                                @if(count($offering->available_states) > 3)<small class="text-muted"> +{{ count($offering->available_states)-3 }}</small>@endif
                            @else <span class="text-muted small">All</span>
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
                        <td class="text-end">
                            <a href="{{ route('admin.offerings.show', $offering->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.offerings.destroy', $offering->id) }}"
                                  class="d-inline" onsubmit="return confirm('Delete {{ addslashes($offering->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-capsule fs-2 d-block mb-2 opacity-25"></i>No offerings yet.
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
