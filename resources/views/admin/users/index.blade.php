@extends('layouts.admin')

@section('title', 'All Users')
@section('page-title', 'All Users')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Every user across all roles — admins, clinicians, partners, and support staff. Use the role-specific screens to manage credentials, assignments, and role-specific settings.</p>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search name or email…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All roles</option>
                    <option value="super_admin"  {{ request('role') === 'super_admin'  ? 'selected' : '' }}>Super Admin</option>
                    <option value="admin"        {{ request('role') === 'admin'        ? 'selected' : '' }}>Doctor Admin</option>
                    <option value="clinician"    {{ request('role') === 'clinician'    ? 'selected' : '' }}>Clinician</option>
                    <option value="partner"      {{ request('role') === 'partner'      ? 'selected' : '' }}>Partner</option>
                    <option value="support_staff"{{ request('role') === 'support_staff'? 'selected' : '' }}>Support Staff</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
                @if(request()->anyFilled(['search', 'role']))
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    @php
                        $primaryRole = $user->roles->first()?->name;
                        $detailUrl   = match(true) {
                            $user->hasRole('super_admin') || $user->hasRole('admin')
                                => route('admin.admins.show', $user->id),
                            $user->hasRole('clinician') && $user->clinician
                                => route('admin.clinicians.show', $user->clinician->id),
                            $user->hasRole('partner') && $user->partner_id
                                => route('admin.partners.show', $user->partner_id),
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $user->name }}</span>
                            @if($user->id === Auth::id())
                                <span class="badge bg-secondary ms-1" style="font-size:.65rem;">You</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                @php
                                    $badgeClass = match($role->name) {
                                        'super_admin'  => 'bg-dark',
                                        'admin'        => 'bg-primary',
                                        'clinician'    => 'bg-success',
                                        'partner'      => 'bg-info text-dark',
                                        'support_staff'=> 'bg-warning text-dark',
                                        default        => 'bg-secondary',
                                    };
                                    $label = match($role->name) {
                                        'super_admin'  => 'Super Admin',
                                        'admin'        => 'Doctor Admin',
                                        'clinician'    => 'Clinician',
                                        'partner'      => 'Partner',
                                        'support_staff'=> 'Support Staff',
                                        default        => $role->name,
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}" style="font-size:.7rem;">{{ $label }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if(($user->is_active ?? true))
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;">Active</span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">Inactive</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $user->created_at->format('d M Y') }}</td>
                        <td>
                            @if($detailUrl)
                                <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    View
                                </a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-2 d-block mb-2 opacity-25"></i>
                            No users found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($users->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="text-muted small">{{ number_format($users->total()) }} users total</span>
        {{ $users->links() }}
    </div>
    @else
    <div class="card-footer">
        <span class="text-muted small">{{ number_format($users->total()) }} users total</span>
    </div>
    @endif
</div>

@endsection
