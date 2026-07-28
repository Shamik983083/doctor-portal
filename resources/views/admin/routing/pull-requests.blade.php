@extends('layouts.admin')

@section('title', 'Case Pull Requests')

@section('content')
{{--
    Doctors asking the pool for work (Devin msg 2308), and the ones held for your
    approval because they are over an SLA you set (msg 2313 Q6).

    Nothing on this screen shows what is IN the queue. The doctor cannot see it and
    neither does approving one of these reveal it: approval grants the oldest cases
    that doctor is eligible for, whatever those turn out to be.
--}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">Case Pull Requests</h4>
        <p class="text-muted small mb-0">
            A doctor asks for a number of cases. The pool grants the oldest ones they are eligible
            for. Requests from doctors over their SLA wait here.
        </p>
    </div>
    <div>
        <a href="{{ route('admin.routing.sla') }}" class="btn btn-outline-secondary btn-sm">Provider Pull SLA</a>
        <a href="{{ route('admin.routing.exceptions') }}" class="btn btn-outline-secondary btn-sm">Exceptions</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">
        Waiting on you ({{ $pending->count() }})
    </div>
    @if($pending->isEmpty())
        <div class="card-body text-muted small">Nothing waiting for a decision.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Doctor</th>
                        <th>Asked for</th>
                        <th>Over SLA on</th>
                        <th>Waiting</th>
                        <th class="text-end">Decision</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pending as $request)
                    <tr>
                        <td>{{ $request->clinician->user?->name ?? 'Doctor #' . $request->clinician_id }}</td>
                        <td>{{ $request->requested_count }}</td>
                        <td class="small">
                            @foreach($request->blocking_reasons ?? [] as $code)
                                <div>{{ \App\Services\Routing\PoolEligibilityEvaluator::REASON_LABELS[$code] ?? $code }}</div>
                            @endforeach
                        </td>
                        <td>{{ $request->created_at->diffForHumans(null, true) }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <form method="POST" action="{{ route('admin.routing.pull-requests.approve', $request->id) }}">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="">
                                    <button class="btn btn-sm btn-primary">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.routing.pull-requests.deny', $request->id) }}">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="">
                                    <button class="btn btn-sm btn-outline-danger">Deny</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body text-muted small border-top">
            Approving grants the cases now, using the doctor's eligibility as it stands at that
            moment. It does not override any cap: a doctor near their limit still gets only what
            fits.
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Recent requests</div>
    @if($recent->isEmpty())
        <div class="card-body text-muted small">No requests yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Doctor</th>
                        <th>Asked</th>
                        <th>Granted</th>
                        <th>Outcome</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recent as $request)
                    <tr>
                        <td>{{ $request->clinician->user?->name ?? 'Doctor #' . $request->clinician_id }}</td>
                        <td>{{ $request->requested_count }}</td>
                        <td>{{ $request->granted_count }}</td>
                        <td class="small">
                            <span class="badge bg-{{ $request->status === 'GRANTED' ? 'success' : 'secondary' }}">
                                {{ $request->statusLabel() }}
                            </span>
                            @if($request->shortfall_reason)
                                <div class="text-muted mt-1">{{ $request->shortfall_reason }}</div>
                            @endif
                            @if($request->decidedBy)
                                <div class="text-muted mt-1">by {{ $request->decidedBy->name }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $request->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
