@extends('layouts.clinician')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

@section('content')
<div class="card">
    <div class="card-header">
        <form action="{{ route('clinician.queue') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <h6 class="mb-0 me-2">Case Queue</h6>
            </div>
            <div class="col">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Search patient name…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <select name="state" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All States</option>
                    @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $st)
                        <option value="{{ $st }}" {{ request('state') == $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    @foreach(['waiting','assigned','approved','processing','completed','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
                @if(request()->anyFilled(['search','state','status']))
                    <a href="{{ route('clinician.queue') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
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
                            <strong>{{ $case->patient->full_name ?? 'N/A' }}</strong>
                            @if($case->unread_messages_count > 0)
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">{{ $case->unread_messages_count }} new msg</span>
                            @endif
                            <br><small class="text-muted">{{ $case->patient->state ?? '' }}</small>
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
