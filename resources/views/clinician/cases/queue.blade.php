@extends('layouts.clinician')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Waiting Cases</h6>
        <form class="d-flex gap-2" method="GET">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['waiting','assigned','approved','processing'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>State</th>
                        <th>Partner</th>
                        <th>Offerings</th>
                        <th>Status</th>
                        <th>Wait Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                    <tr>
                        <td><small class="text-muted font-monospace">{{ substr($case->uuid, 0, 8) }}</small></td>
                        <td>
                            <strong>{{ $case->patient->full_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $case->patient->state ?? '' }}</small>
                        </td>
                        <td>{{ $case->patient_state ?? $case->patient->state ?? '—' }}</td>
                        <td>{{ $case->partner->name ?? '—' }}</td>
                        <td>
                            @foreach($case->caseOfferings->take(2) as $co)
                                <span class="badge bg-light text-dark border small">{{ $co->offering->name ?? '?' }}</span>
                            @endforeach
                        </td>
                        <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                        <td><small>{{ $case->created_at->diffForHumans() }}</small></td>
                        <td class="d-flex gap-1">
                            <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary">Review</a>
                            @if($case->status === 'waiting')
                            <form method="POST" action="{{ route('clinician.cases.assign', $case->uuid) }}">
                                @csrf
                                <button class="btn btn-sm btn-warning">Claim</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases in queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($cases->hasPages())
    <div class="card-footer">{{ $cases->links() }}</div>
    @endif
</div>
@endsection
