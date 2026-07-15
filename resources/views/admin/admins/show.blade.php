@extends('layouts.admin')

@section('title', $admin->name)
@section('page-title', $admin->name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="ma-eyebrow">Admin account</div>
                <div class="ma-title">{{ $admin->name }}</div>
                <div class="ma-sub">{{ $admin->email }}</div>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Role</dt>
                    <dd class="col-sm-8">
                        @if($admin->hasRole('super_admin'))
                            <span class="badge bg-warning text-dark">Super Admin</span>
                        @else
                            <span class="badge bg-secondary">Admin</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">{{ $admin->email }}</dd>
                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8">{{ $admin->created_at->format('M j, Y g:i A') }}</dd>
                    <dt class="col-sm-4">Last updated</dt>
                    <dd class="col-sm-8">{{ $admin->updated_at->format('M j, Y g:i A') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    @if($admin->id !== Auth::id())
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="ma-eyebrow">Actions</div>
                <div class="ma-title">Manage account</div>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @if($admin->hasRole('admin'))
                    <form method="POST" action="{{ route('admin.admins.promote', $admin->id) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-warning w-100" onclick="return confirm('Promote {{ addslashes($admin->name) }} to Super Admin?')">
                            <i class="bi bi-arrow-up-circle"></i> Promote to Super Admin
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.admins.demote', $admin->id) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-outline-secondary w-100" onclick="return confirm('Demote {{ addslashes($admin->name) }} to regular Admin?')">
                            <i class="bi bi-arrow-down-circle"></i> Demote to Admin
                        </button>
                    </form>
                @endif

                <hr>

                <form method="POST" action="{{ route('admin.admins.destroy', $admin->id) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger w-100" onclick="return confirm('Permanently delete {{ addslashes($admin->name) }}? This cannot be undone.')">
                        <i class="bi bi-trash"></i> Delete account
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="mt-3">
    <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to admin users
    </a>
</div>
@endsection
