@extends('layouts.admin')

@section('title', $admin->name)
@section('page-title', $admin->name)

@section('content')
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

{{--
    Which doctors this admin is over (Devin msg 2117). Not shown for super
    admins: offering the picker would imply their access depends on it, and it
    does not.
--}}
@if($admin->hasRole('super_admin'))
    <div class="card mt-4">
        <div class="card-body">
            <h6 class="fw-semibold mb-1">Doctors</h6>
            <p class="text-muted small mb-0">
                Super admins see every doctor, every case and every integration. There is nothing to scope.
            </p>
        </div>
    </div>
@else
    <div class="card mt-4">
        <div class="card-body">
            <h6 class="fw-semibold mb-1">Doctors this admin is over</h6>
            <p class="text-muted small">
                This admin sees only the cases belonging to the doctors ticked below, and can only
                reassign work between them.
            </p>

            @if($admin->managedClinicians->isEmpty())
                <div class="alert alert-warning py-2 small">
                    Not over any doctors yet, so this admin currently sees <strong>no cases at all</strong>.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.admins.clinicians.update', $admin->id) }}">
                @csrf
                @method('PUT')

                {{-- So that unticking everything submits an empty list rather than no key at all. --}}
                <input type="hidden" name="clinician_ids[]" value="">

                <div class="row">
                    @foreach($clinicians as $clinician)
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="clinician_{{ $clinician->id }}"
                                       name="clinician_ids[]" value="{{ $clinician->id }}"
                                       {{ $admin->managedClinicians->contains('id', $clinician->id) ? 'checked' : '' }}>
                                <label class="form-check-label" for="clinician_{{ $clinician->id }}">
                                    {{ $clinician->user->name ?? 'Doctor #' . $clinician->id }}
                                    @if($clinician->credentials)
                                        <span class="text-muted small">{{ $clinician->credentials }}</span>
                                    @endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button class="btn btn-primary btn-sm mt-2">Save doctors</button>
            </form>
        </div>
    </div>
@endif

<div class="mt-3">
    <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to admin users
    </a>
</div>
@endsection
