@extends('layouts.support')

@section('title', 'Support Queue')
@section('page-title', 'Support Queue')

@section('content')

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            Cases awaiting support
            <span class="text-muted fw-normal small">({{ number_format($cases->total()) }})</span>
        </h6>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control form-control-sm"
                   placeholder="Search patient…" value="{{ request('search') }}" style="width:200px">
            <button class="btn btn-sm btn-primary">Search</button>
            @if(request('search'))
                <a href="{{ route('support.cases.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Partner</th>
                        <th>Offering(s)</th>
                        <th style="width:120px">Assigned Clinician</th>
                        <th style="width:140px">Escalated</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($cases as $case)
                    <tr>
                        <td>
                            <span class="fw-semibold">
                                {{ $case->patient?->first_name }} {{ $case->patient?->last_name }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $case->partner?->name ?? '—' }}</td>
                        <td class="text-muted small">
                            {{ $case->caseOfferings->pluck('offering.name')->filter()->join(', ') ?: '—' }}
                        </td>
                        <td class="text-muted small">
                            {{ $case->clinician?->user?->name ?? '—' }}
                        </td>
                        <td class="text-muted small">
                            {{ $case->updated_at?->format('d M Y H:i') }}
                        </td>
                        <td>
                            <a href="{{ route('support.cases.show', $case->uuid) }}"
                               class="btn btn-sm btn-outline-secondary py-0 px-2">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>
                            No cases in the support queue.
                        </td>
                    </tr>
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
