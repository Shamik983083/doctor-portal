@extends('layouts.partner')
@section('title', 'Cases')
@section('page-title', 'Cases')

@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['created','waiting','support','assigned','approved','processing','completed','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-filter"></i> Filter</button>
                <a href="{{ route('partner.cases.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>UUID</th>
                    <th>Patient</th>
                    <th>Offerings</th>
                    <th>Clinician</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                <tr>
                    <td><code class="text-primary">{{ substr($case->uuid, 0, 8) }}…</code></td>
                    <td>
                        <a href="{{ route('partner.patients.show', $case->patient->id) }}" class="text-decoration-none">
                            {{ $case->patient->first_name }} {{ $case->patient->last_name }}
                        </a>
                    </td>
                    <td>
                        @foreach($case->caseOfferings->take(2) as $co)
                            <span class="badge bg-light text-dark border">{{ $co->offering->name }}</span>
                        @endforeach
                        @if($case->caseOfferings->count() > 2)
                            <span class="badge bg-secondary">+{{ $case->caseOfferings->count() - 2 }}</span>
                        @endif
                    </td>
                    <td>{{ $case->clinician?->user->name ?? '—' }}</td>
                    <td><span class="badge badge-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                    <td class="text-muted small">{{ $case->created_at->diffForHumans() }}</td>
                    <td>
                        <a href="{{ route('partner.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-folder2-open fs-3 d-block mb-2 opacity-25"></i>
                        No cases found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cases->hasPages())
    <div class="card-footer bg-white border-top">{{ $cases->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
