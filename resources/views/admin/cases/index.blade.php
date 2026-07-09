@extends('layouts.admin')

@section('title', 'Cases')
@section('page-title', 'All Cases')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Cases</h6>
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['created','waiting','support','assigned','approved','processing','completed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="clinician_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Clinicians</option>
                @foreach($clinicians as $c)
                    <option value="{{ $c->id }}" {{ request('clinician_id') == $c->id ? 'selected' : '' }}>{{ $c->full_name }}</option>
                @endforeach
            </select>
            @if(request()->hasAny(['status','partner_id','clinician_id']))
                <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Partner</th>
                        <th>Clinician</th>
                        <th>Offerings</th>
                        <th>Status</th>
                        <th>Support</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                    <tr>
                        <td><small class="text-muted font-monospace">{{ substr($case->uuid, 0, 8) }}</small></td>
                        <td>
                            <strong>{{ $case->patient->full_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $case->patient_state ?? $case->patient->state ?? '—' }}</small>
                        </td>
                        <td><small>{{ $case->partner->name ?? '—' }}</small></td>
                        <td>
                            @if($case->clinician)
                                <small>{{ $case->clinician->full_name }}</small>
                            @else
                                <span class="text-muted small">Unassigned</span>
                            @endif
                        </td>
                        <td>
                            @foreach($case->caseOfferings->take(2) as $co)
                                <span class="badge bg-light text-dark border small">{{ $co->offering->name ?? '?' }}</span>
                            @endforeach
                        </td>
                        <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                        <td>
                            @if($case->support_at)
                                <i class="bi bi-check-circle-fill text-warning" title="In support at {{ $case->support_at->format('M d H:i') }}"></i>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $case->created_at->diffForHumans() }}</small></td>
                        <td>
                            <a href="{{ route('admin.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <form method="POST" action="{{ route('admin.cases.destroy', $case->uuid) }}" onsubmit="return confirm('Are you sure you want to delete this case? This cannot be undone.')" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases found.</td></tr>
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
