@extends('layouts.partner')
@section('title', 'Patients')
@section('page-title', 'Patients')

@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search by name or email…" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="active"   @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a href="{{ route('partner.patients.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Patient</th>
                    <th>External ID</th>
                    <th>DOB</th>
                    <th>State</th>
                    <th>Cases</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $patient)
                <tr>
                    <td>
                        <div class="fw-medium">{{ $patient->first_name }} {{ $patient->last_name }}</div>
                        <div class="text-muted small">{{ $patient->email }}</div>
                    </td>
                    <td><code>{{ $patient->external_id ?? '—' }}</code></td>
                    <td>{{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('M j, Y') : '—' }}</td>
                    <td>{{ $patient->state ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $patient->cases_count }}</span></td>
                    <td>
                        @if($patient->status === 'active')
                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ ucfirst($patient->status) }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('partner.patients.show', $patient->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-3 d-block mb-2 opacity-25"></i>
                        No patients found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($patients->hasPages())
    <div class="card-footer bg-white border-top">{{ $patients->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
