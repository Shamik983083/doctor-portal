@extends('layouts.clinician')

@section('title', 'Clinician Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100" style="border-color:#0d6efd">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Waiting Queue</p>
                        <h3 class="fw-bold text-primary">{{ $stats['waiting'] }}</h3>
                    </div>
                    <i class="bi bi-hourglass-split fs-2 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100" style="border-color:#fd7e14">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">My Assigned</p>
                        <h3 class="fw-bold text-warning">{{ $stats['assigned'] }}</h3>
                    </div>
                    <i class="bi bi-person-check fs-2 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100" style="border-color:#198754">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Approved Today</p>
                        <h3 class="fw-bold text-success">{{ $stats['approved'] }}</h3>
                    </div>
                    <i class="bi bi-check-circle fs-2 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100" style="border-color:#0dcaf0">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted small mb-1">Completed Today</p>
                        <h3 class="fw-bold text-info">{{ $stats['completed_today'] }}</h3>
                    </div>
                    <i class="bi bi-clipboard-check fs-2 text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">My Active Cases</h6>
        <a href="{{ route('clinician.queue') }}" class="btn btn-sm btn-outline-primary">View Queue</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Partner</th>
                        <th>Offerings</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentCases as $case)
                    <tr>
                        <td>
                            <strong>{{ $case->patient->full_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $case->patient->email ?? '' }}</small>
                        </td>
                        <td>{{ $case->partner->name ?? '—' }}</td>
                        <td>
                            @foreach($case->caseOfferings->take(2) as $co)
                                <span class="badge bg-light text-dark border">{{ $co->offering->name ?? 'Unknown' }}</span>
                            @endforeach
                        </td>
                        <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                        <td><small>{{ $case->created_at->diffForHumans() }}</small></td>
                        <td><a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-secondary">Review</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No active cases.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
