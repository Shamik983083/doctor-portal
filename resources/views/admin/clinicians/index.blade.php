@extends('layouts.admin')

@section('title', 'Clinicians')
@section('page-title', 'Clinicians')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Clinicians</h6>
        <a href="{{ route('admin.clinicians.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Clinician</a>
    </div>
    <div class="card-header bg-light border-top-0">
        <form class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name..." value="{{ request('search') }}">
            <select name="status" class="form-select form-select-sm w-auto">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
                <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Clinician</th>
                        <th>Credentials</th>
                        <th>NPI</th>
                        <th>States</th>
                        <th>Total Cases</th>
                        <th>Status</th>
                        <th>Available</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clinicians as $clinician)
                    <tr>
                        <td>
                            <strong>{{ $clinician->user->name }}</strong><br>
                            <small class="text-muted">{{ $clinician->user->email }}</small>
                        </td>
                        <td>{{ $clinician->credentials ?? '—' }}</td>
                        <td><code class="small">{{ $clinician->npi ?? '—' }}</code></td>
                        <td>
                            @php
                                $ls = $clinician->licensed_states ?? [];
                                $codes = count($ls) && is_array($ls[0]) ? array_column($ls, 'state') : $ls;
                            @endphp
                            @if(count($codes))
                                {{ implode(', ', array_slice($codes, 0, 4)) }}
                                @if(count($codes) > 4) <small class="text-muted">+{{ count($codes) - 4 }}</small>@endif
                            @else —
                            @endif
                        </td>
                        <td>{{ $clinician->cases_count }}</td>
                        <td>
                            <span class="badge {{ $clinician->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($clinician->status) }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $clinician->is_available ? 'bg-success' : 'bg-warning text-dark' }}">{{ $clinician->is_available ? 'Yes' : 'No' }}</span>
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.clinicians.show', $clinician->id) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.clinicians.edit', $clinician->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.clinicians.destroy', $clinician->id) }}" onsubmit="return confirm('Are you sure you want to delete this clinician? This cannot be undone.')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No clinicians yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($clinicians->hasPages())
    <div class="card-footer">{{ $clinicians->links() }}</div>
    @endif
</div>
@endsection
