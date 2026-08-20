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
<style>
.pr-page { max-width: 960px; }
.pr-section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.pr-section-header { padding:14px 20px; border-bottom:1px solid #f1f3f5; display:flex; align-items:center; justify-content:space-between; }
.pr-section-header .title { font-size:.78rem; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:#6b7280; }
.pr-section-header .count { font-size:.78rem; font-weight:600; color:#374151; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:20px; padding:2px 10px; }
.pr-section-header .count.has-items { background:#fef3c7; border-color:#fde68a; color:#92400e; }
.pr-table { width:100%; border-collapse:collapse; }
.pr-table th { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:#9ca3af; font-weight:600; padding:10px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
.pr-table td { font-size:.82rem; padding:11px 16px; border-bottom:1px solid #f3f4f6; color:#374151; vertical-align:middle; }
.pr-table tbody tr:last-child td { border-bottom:none; }
.pr-table .doctor-name { font-weight:500; color:#1f2937; }
.pr-table .reason-line { font-size:.73rem; color:#6b7280; margin-top:2px; }
.pr-empty { padding:24px 20px; font-size:.82rem; color:#9ca3af; }
.pr-footer { padding:11px 20px; border-top:1px solid #f1f3f5; font-size:.73rem; color:#9ca3af; }
.approve-btn { font-size:.75rem; font-weight:600; padding:5px 14px; border-radius:7px; border:none; background:#1d1d1f; color:#fff; cursor:pointer; transition:opacity .12s; }
.approve-btn:hover { opacity:.82; }
.deny-btn { font-size:.75rem; font-weight:500; padding:5px 12px; border-radius:7px; border:1px solid #fca5a5; background:#fff; color:#dc2626; cursor:pointer; transition:background .12s; }
.deny-btn:hover { background:#fff5f5; }
.status-badge { display:inline-block; font-size:.7rem; font-weight:600; letter-spacing:.04em; text-transform:uppercase; border-radius:5px; padding:2px 8px; }
.status-GRANTED { background:#dcfce7; color:#166534; }
.status-DENIED  { background:#fee2e2; color:#b91c1c; }
.status-PENDING { background:#fef3c7; color:#92400e; }
.status-other   { background:#f3f4f6; color:#374151; }
</style>

<div class="pr-page">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-semibold mb-1" style="font-size:1.2rem;color:#111827;letter-spacing:-.02em">Case Pull Requests</h4>
            <p class="mb-0" style="font-size:.82rem;color:#6b7280">A doctor asks for a number of cases. The pool grants the oldest ones they are eligible for. Requests from doctors over their SLA wait here for your decision.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.routing.sla') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;border-radius:8px">
                <i class="bi bi-speedometer me-1"></i>Provider SLA
            </a>
            <a href="{{ route('admin.routing.exceptions') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;border-radius:8px">
                <i class="bi bi-exclamation-triangle me-1"></i>Exceptions
            </a>
        </div>
    </div>

    {{-- Pending decisions --}}
    <div class="pr-section">
        <div class="pr-section-header">
            <span class="title">Waiting on you</span>
            <span class="count {{ $pending->isNotEmpty() ? 'has-items' : '' }}">{{ $pending->count() }} pending</span>
        </div>
        @if($pending->isEmpty())
            <div class="pr-empty">Nothing waiting for a decision.</div>
        @else
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Asked for</th>
                        <th>Over SLA on</th>
                        <th>Waiting</th>
                        <th style="text-align:right">Decision</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pending as $request)
                    <tr>
                        <td class="doctor-name">{{ $request->clinician->user?->name ?? 'Doctor #' . $request->clinician_id }}</td>
                        <td>{{ $request->requested_count }} {{ Str::plural('case', $request->requested_count) }}</td>
                        <td>
                            @foreach($request->blocking_reasons ?? [] as $code)
                                <div class="reason-line">{{ \App\Services\Routing\PoolEligibilityEvaluator::REASON_LABELS[$code] ?? $code }}</div>
                            @endforeach
                        </td>
                        <td>{{ $request->created_at->diffForHumans(null, true) }}</td>
                        <td style="text-align:right">
                            <div class="d-flex gap-2 justify-content-end">
                                <form method="POST" action="{{ route('admin.routing.pull-requests.approve', $request->id) }}">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="">
                                    <button class="approve-btn">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.routing.pull-requests.deny', $request->id) }}">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="">
                                    <button class="deny-btn">Deny</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pr-footer">
                Approving grants cases using the doctor's eligibility at that moment — it does not override any cap.
            </div>
        @endif
    </div>

    {{-- Recent history --}}
    <div class="pr-section">
        <div class="pr-section-header">
            <span class="title">Recent requests</span>
        </div>
        @if($recent->isEmpty())
            <div class="pr-empty">No requests yet.</div>
        @else
            <table class="pr-table">
                <thead>
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
                        <td class="doctor-name">{{ $request->clinician->user?->name ?? 'Doctor #' . $request->clinician_id }}</td>
                        <td>{{ $request->requested_count }}</td>
                        <td>{{ $request->granted_count ?? '—' }}</td>
                        <td>
                            @php
                                $s = $request->status;
                                $cls = in_array($s, ['GRANTED','DENIED','PENDING']) ? "status-$s" : 'status-other';
                            @endphp
                            <span class="status-badge {{ $cls }}">{{ $request->statusLabel() }}</span>
                            @if($request->shortfall_reason)
                                <div class="reason-line mt-1">{{ $request->shortfall_reason }}</div>
                            @endif
                            @if($request->decidedBy)
                                <div class="reason-line">by {{ $request->decidedBy->name }}</div>
                            @endif
                        </td>
                        <td style="font-size:.78rem;color:#9ca3af">{{ $request->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@endsection
