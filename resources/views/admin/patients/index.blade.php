@extends('layouts.admin')

@section('title', 'Patients')
@section('page-title', 'Patients')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">All Patients <span class="text-muted fw-normal small">({{ $patients->total() }})</span></h6>
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="text" name="search" class="form-control form-control-sm" style="width:200px"
                   placeholder="Name, email, phone..." value="{{ request('search') }}">
            <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['active','inactive','suspended'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            @if(request()->hasAny(['search','partner_id','status','state']))
                <a href="{{ route('admin.patients.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>State</th>
                        <th>Partner</th>
                        <th>Cases</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($patients as $patient)
                    <tr>
                        <td>
                            <strong>{{ $patient->full_name }}</strong><br>
                            <small class="text-muted">{{ $patient->date_of_birth?->format('M d, Y') ?? '—' }} &bull; {{ ucfirst($patient->gender ?? '—') }}</small>
                        </td>
                        <td><small>{{ $patient->email }}</small></td>
                        <td><small>{{ $patient->phone ?? '—' }}</small></td>
                        <td>{{ $patient->state ?? '—' }}</td>
                        <td><small>{{ $patient->partner->name ?? '—' }}</small></td>
                        <td>
                            <span class="badge {{ $patient->cases_count > 0 ? 'bg-primary' : 'bg-light text-dark border' }}">
                                {{ $patient->cases_count }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ match($patient->status) { 'active' => 'bg-success', 'inactive' => 'bg-secondary', default => 'bg-warning text-dark' } }}">
                                {{ ucfirst($patient->status ?? 'active') }}
                            </span>
                        </td>
                        <td><small class="text-muted">{{ $patient->created_at->format('M d, Y') }}</small></td>
                        <td>
                            <a href="{{ route('admin.patients.show', $patient->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>No patients found.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($patients->hasPages())
    <div class="card-footer">{{ $patients->links() }}</div>
    @endif
</div>
@endsection
