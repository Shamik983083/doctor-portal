@extends('layouts.admin')

@section('title', 'Supervisor Physicians')
@section('page-title', 'Supervisor Physicians')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0">Manage supervisor physician accounts. Only Super Admins can access this page.</p>
    </div>
    <a href="{{ route('admin.supervisor-physicians.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus"></i> New supervisor physician
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.supervisor-physicians.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or email…" value="{{ request('search') }}">
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
                @if(request()->anyFilled(['search']))
                    <a href="{{ route('admin.supervisor-physicians.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>NPI</th>
                        <th>Licensed States</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($physicians as $physician)
                    <tr>
                        <td><strong>{{ $physician->name }}</strong></td>
                        <td>{{ $physician->email }}</td>
                        <td>
                            @if($physician->supervisorPhysician?->npi)
                                <code>{{ $physician->supervisorPhysician->npi }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @php $states = $physician->supervisorPhysician?->licensed_states ?? []; @endphp
                            @if(count($states))
                                <span class="badge bg-info text-dark">{{ count($states) }} state{{ count($states) !== 1 ? 's' : '' }}</span>
                                <small class="text-muted ms-1">{{ implode(', ', $states) }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($physician->is_active ?? true)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td><small>{{ $physician->created_at->format('M j, Y') }}</small></td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.supervisor-physicians.show', $physician->id) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>

                            <form method="POST" action="{{ route('admin.supervisor-physicians.toggle-active', $physician->id) }}" class="d-inline">
                                @csrf @method('PATCH')
                                @if($physician->is_active ?? true)
                                    <button class="btn btn-sm btn-outline-warning" title="Deactivate" onclick="return confirm('Deactivate {{ addslashes($physician->name) }}?')"><i class="bi bi-pause-circle"></i></button>
                                @else
                                    <button class="btn btn-sm btn-outline-success" title="Reactivate" onclick="return confirm('Reactivate {{ addslashes($physician->name) }}?')"><i class="bi bi-play-circle"></i></button>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('admin.supervisor-physicians.destroy', $physician->id) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Permanently delete {{ addslashes($physician->name) }}? This cannot be undone.')"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-person-badge fs-2 d-block mb-2"></i>No supervisor physicians found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($physicians->hasPages())
    <div class="card-footer">{{ $physicians->links() }}</div>
    @endif
</div>
@endsection
