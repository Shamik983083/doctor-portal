@extends('layouts.admin')

@section('title', 'Questionnaires')
@section('page-title', 'Questionnaires')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">All Questionnaires <span class="text-muted fw-normal small">({{ $questionnaires->total() }})</span></h6>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form class="d-flex gap-2 flex-wrap" method="GET">
                <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">All Partners</option>
                    <option value="none" {{ request('partner_id') === 'none' ? 'selected' : '' }}>Global (no partner)</option>
                    @foreach($partners as $p)
                        <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                @if(request()->hasAny(['partner_id','status']))
                    <a href="{{ route('admin.questionnaires.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </form>
            <a href="{{ route('admin.questionnaires.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Questionnaire
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Partner</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($questionnaires as $q)
                    <tr>
                        <td>
                            <a href="{{ route('admin.questionnaires.show', $q->id) }}" class="fw-semibold text-decoration-none">
                                {{ $q->name }}
                            </a>
                            @if($q->description)
                                <br><small class="text-muted">{{ Str::limit($q->description, 60) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($q->partner)
                                <span class="badge bg-light text-dark border">{{ $q->partner->name }}</span>
                            @else
                                <span class="text-muted small">Global</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-primary">{{ $q->questions_count }}</span>
                        </td>
                        <td>
                            @if($q->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $q->created_at->format('M d, Y') }}</small></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('admin.questionnaires.show', $q->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="{{ route('admin.questionnaires.edit', $q->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('admin.questionnaires.destroy', $q->id) }}"
                                      onsubmit="return confirm('Delete this questionnaire?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-ui-checks fs-2 d-block mb-2"></i>
                            No questionnaires yet.
                            <a href="{{ route('admin.questionnaires.create') }}">Create one</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($questionnaires->hasPages())
    <div class="card-footer">{{ $questionnaires->links() }}</div>
    @endif
</div>
@endsection
