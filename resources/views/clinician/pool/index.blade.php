@extends('layouts.clinician')

@section('title', 'Request Cases')

@section('content')
{{--
    The provider pool, from the doctor's side (Devin msg 2308).

    There is deliberately no list of available cases here. The doctor asks for a
    number and the pool grants the oldest cases they are eligible for. Showing the
    queue would let it be cherry-picked, and the oldest-first guarantee is the only
    thing keeping the tail of the queue from going stale.
--}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">Request Cases</h4>
        <p class="text-muted small mb-0">
            Ask for a number of cases. You will be given the oldest ones in the queue that match your
            licensed states, the categories you accept and the visit types you take.
        </p>
    </div>
</div>

@if($eligibility->isBlocked())
    <div class="alert alert-danger">
        <strong>You cannot take cases from the pool right now.</strong>
        <ul class="mb-0 mt-2">
            @foreach($eligibility->blockingReasons as $code)
                <li>{{ $reasonLabels[$code] ?? $code }}</li>
            @endforeach
        </ul>
    </div>
@elseif($eligibility->requiresApproval)
    <div class="alert alert-warning">
        <strong>You are over an SLA your admin set.</strong>
        You can still ask, but the request goes to your Doctor Admin to approve rather than being
        granted straight away.
        <ul class="mb-0 mt-2">
            @foreach($eligibility->slaViolations as $code)
                <li>{{ $reasonLabels[$code] ?? $code }}</li>
            @endforeach
        </ul>
    </div>
@elseif($eligibility->slaViolations !== [])
    <div class="alert alert-warning">
        You are over an SLA your admin set, and their setting lets the request through anyway. They
        will be told each time this happens.
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('clinician.pool.request') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-auto">
                <label class="form-label fw-semibold">How many cases?</label>
                <input type="number" name="count" class="form-control" min="1"
                       max="{{ $maxPerRequest }}" value="{{ old('count', 5) }}" required
                       @disabled($eligibility->isBlocked())>
                <div class="form-text">Up to {{ $maxPerRequest }} per request.</div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" @disabled($eligibility->isBlocked())>Request cases</button>
            </div>
            @if($remainingToday !== null)
                <div class="col-auto">
                    <div class="text-muted small">{{ $remainingToday }} left under today's limit.</div>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-2">What you will be given</h6>
        <div class="row small text-muted">
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Your states</div>
                @php $states = collect($clinician->licensed_states ?? [])->pluck('state')->filter(); @endphp
                {{ $states->isEmpty() ? 'None recorded, so nothing can be assigned to you.' : $states->implode(', ') }}
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Categories you accept</div>
                {{ $clinician->acceptedCategories->pluck('name')->implode(', ') ?: 'None ticked, so nothing can be assigned to you.' }}
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Visit types</div>
                {{ $clinician->accepts_async_visits ? 'Asynchronous' : '' }}
                {{ $clinician->accepts_sync_visits && $clinician->hasSchedulingLink() ? ' · Synchronous' : '' }}
                @if($clinician->accepts_sync_visits && ! $clinician->hasSchedulingLink())
                    <div class="text-danger">
                        You take synchronous visits but have no booking link, so those cases cannot
                        reach you. Ask an admin to add it.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Your recent requests</div>
    @if($requests->isEmpty())
        <div class="card-body text-muted small">You have not requested any cases yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Asked</th>
                        <th>Given</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($requests as $request)
                    <tr>
                        <td class="small text-muted">{{ $request->created_at->diffForHumans() }}</td>
                        <td>{{ $request->requested_count }}</td>
                        <td>{{ $request->granted_count }}</td>
                        <td class="small">
                            <span class="badge bg-{{ $request->status === 'GRANTED' ? 'success' : 'secondary' }}">
                                {{ $request->statusLabel() }}
                            </span>
                            @if($request->shortfall_reason)
                                <div class="text-muted mt-1">{{ $request->shortfall_reason }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
