@extends('layouts.admin')

@section('title', 'Routing Exceptions')

@section('content')
{{--
    Cases that did not route (Devin msg 2308: "NO SILENT FAILURES").

    Two lists, deliberately kept apart. Exceptions are cases nobody could take and
    something is wrong. The pool queue is cases waiting to be claimed, which under
    PROVIDER_POOL is the design working. Merging them would make "broken" and
    "working" look the same, which is how a screen like this stops being read.
--}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">Routing Exceptions</h4>
        <p class="text-muted small mb-0">
            Cases that could not be assigned, with the reason each one is stuck. An exception clears
            itself the moment its case routes.
        </p>
    </div>
    <div>
        <a href="{{ route('admin.routing.pull-requests') }}" class="btn btn-outline-secondary btn-sm">Pull requests</a>
    </div>
</div>

@if($exceptions->isEmpty())
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i><strong>Nothing is stuck.</strong>
        Every case has either been assigned or is waiting in the pool below.
    </div>
@else
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ $exceptions->count() }} case(s) could not be assigned</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Case</th>
                        <th>State</th>
                        <th>Why</th>
                        <th>Stuck for</th>
                        <th>Tries</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($exceptions as $exception)
                    <tr class="{{ $exception->isSystemic() ? 'table-danger' : '' }}">
                        <td>
                            @if($exception->case)
                                <a href="{{ route('admin.cases.show', $exception->case->uuid) }}">
                                    {{ Str::limit($exception->case->uuid, 8, '') }}
                                </a>
                                <div class="text-muted small">{{ $exception->case->partner?->name }}</div>
                            @else
                                <span class="text-muted">(case removed)</span>
                            @endif
                        </td>
                        <td>{{ $exception->case?->patient_state ?? '—' }}</td>
                        <td>
                            <span class="fw-semibold">{{ $exception->reasonLabel() }}</span>
                            @if($exception->isSystemic())
                                <div class="badge bg-danger mt-1">Affects every case</div>
                            @endif
                            @if($exception->provider_reasons)
                                {{-- Per-doctor reasons, so "nobody was eligible"
                                     can be read as WHY nobody was. --}}
                                @php
                                    $codes = collect($exception->provider_reasons)->flatten()->countBy();
                                @endphp
                                <div class="text-muted small mt-1">
                                    @foreach($codes as $code => $count)
                                        {{ \App\Services\Routing\EligibilityEvaluator::REASON_LABELS[$code] ?? $code }}
                                        ({{ $count }}){{ ! $loop->last ? ' · ' : '' }}
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="{{ $exception->ageHours() >= 24 ? 'text-danger fw-semibold' : '' }}">
                                {{ $exception->first_seen_at?->diffForHumans(null, true) }}
                            </span>
                        </td>
                        <td>{{ $exception->occurrences }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.routing.exceptions.resolve', $exception->id) }}">
                                @csrf
                                <button class="btn btn-outline-secondary btn-sm">Clear</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header bg-white">
        <span class="fw-semibold">Unclaimed pool queue</span>
        <span class="text-muted small ms-2">
            {{ $pooledTotal }} case(s) waiting to be claimed. Not errors: under provider pool mode
            this is how cases wait. Ordered oldest first, which is the order the pool grants them in.
        </span>
    </div>
    @if($pooled->isEmpty())
        <div class="card-body text-muted small">Nothing waiting.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Case</th>
                        <th>State</th>
                        <th>Partner</th>
                        <th>Waiting</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pooled as $case)
                    <tr>
                        <td><a href="{{ route('admin.cases.show', $case->uuid) }}">{{ Str::limit($case->uuid, 8, '') }}</a></td>
                        <td>{{ $case->patient_state ?? '—' }}</td>
                        <td>{{ $case->partner?->name }}</td>
                        <td class="{{ $case->created_at->diffInHours(now()) >= 48 ? 'text-danger fw-semibold' : '' }}">
                            {{ $case->created_at->diffForHumans(null, true) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($pooledTotal > $pooled->count())
            <div class="card-body text-muted small">
                Showing the {{ $pooled->count() }} oldest of {{ $pooledTotal }}.
            </div>
        @endif
    @endif
</div>
@endsection
