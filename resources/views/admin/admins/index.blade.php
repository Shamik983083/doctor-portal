@extends('layouts.admin')

@section('title', 'Admin Users')
@section('page-title', 'Admin Users')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0">Manage admin and super admin accounts. Only Super Admins can access this page.</p>
    </div>
    <a href="{{ route('admin.admins.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus"></i> New admin user
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.admins.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or email…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All roles</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
                @if(request()->anyFilled(['search','role']))
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $admin)
                    <tr>
                        <td>
                            <strong>{{ $admin->name }}</strong>
                            @if($admin->id === Auth::id())
                                <span class="badge bg-secondary ms-1">You</span>
                            @endif
                        </td>
                        <td>{{ $admin->email }}</td>
                        <td>
                            @if($admin->hasRole('super_admin'))
                                <span class="badge bg-warning text-dark">Super Admin</span>
                            @else
                                <span class="badge bg-secondary">Admin</span>
                            @endif
                        </td>
                        <td><small>{{ $admin->created_at->format('M j, Y') }}</small></td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.admins.show', $admin->id) }}" class="btn btn-sm btn-outline-primary">View</a>

                            @if($admin->id !== Auth::id())
                                @if($admin->hasRole('admin'))
                                    <form method="POST" action="{{ route('admin.admins.promote', $admin->id) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Promote {{ addslashes($admin->name) }} to Super Admin?')">Promote</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.admins.demote', $admin->id) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary" onclick="return confirm('Demote {{ addslashes($admin->name) }} to Admin?')">Demote</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.admins.destroy', $admin->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete {{ addslashes($admin->name) }}? This cannot be undone.')">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-people fs-2 d-block mb-2"></i>No admin users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($admins->hasPages())
    <div class="card-footer">{{ $admins->links() }}</div>
    @endif
</div>
@endsection
