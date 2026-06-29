@extends('layouts.admin')

@section('title', 'Form Submissions')
@section('page-title', 'Form Submissions')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h6 class="mb-0">Standalone Form Submissions</h6>
            <small class="text-muted">Patients who filled a questionnaire form — not yet linked to a case.</small>
        </div>
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="qualified"    {{ request('status') === 'qualified'    ? 'selected' : '' }}>Qualified</option>
                <option value="disqualified" {{ request('status') === 'disqualified' ? 'selected' : '' }}>Disqualified</option>
            </select>
            @if(request()->hasAny(['partner_id','status']))
                <a href="{{ route('admin.form-submissions.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Questionnaire</th>
                        <th>Partner</th>
                        <th>External Patient ID</th>
                        <th>Result</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($submissions as $sub)
                    <tr>
                        <td><small class="text-muted font-monospace">{{ substr($sub->token, 0, 8) }}</small></td>
                        <td>
                            <span class="fw-semibold">{{ $sub->questionnaire->name ?? '—' }}</span>
                        </td>
                        <td><small>{{ $sub->partner->name ?? '—' }}</small></td>
                        <td>
                            @if($sub->external_patient_id)
                                <code class="small">{{ $sub->external_patient_id }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($sub->is_disqualified)
                                <span class="badge bg-danger">
                                    <i class="bi bi-slash-circle me-1"></i>Disqualified
                                </span>
                            @else
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i>Qualified
                                </span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $sub->completed_at?->diffForHumans() ?? $sub->created_at->diffForHumans() }}</small></td>
                        <td>
                            <a href="{{ route('admin.form-submissions.show', $sub->id) }}"
                               class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No standalone form submissions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($submissions->hasPages())
    <div class="card-footer">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
