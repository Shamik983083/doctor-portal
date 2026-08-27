@extends('layouts.admin')

@section('title', 'Case Pull Requests')

@section('content')
<style>
/* ── Page shell ───────────────────────────────────────────────────────── */
.cpr-wrap { max-width: 1020px; }

/* ── Page header ──────────────────────────────────────────────────────── */
.cpr-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; margin-bottom: 28px; flex-wrap: wrap;
}
.cpr-page-title { font-size: 1.18rem; font-weight: 700; color: #0f172a; letter-spacing: -.025em; margin: 0 0 4px; }
.cpr-page-sub   { font-size: .815rem; color: #64748b; line-height: 1.55; margin: 0; max-width: 620px; }
.cpr-nav-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .78rem; font-weight: 600; padding: 7px 14px;
    border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #475569;
    text-decoration: none; white-space: nowrap;
    transition: background .12s, border-color .12s, color .12s;
}
.cpr-nav-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
.cpr-nav-btn i { font-size: .88rem; }

/* ── Card ─────────────────────────────────────────────────────────────── */
.cpr-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; overflow: hidden; margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(15,23,42,.05), 0 0 0 0 transparent;
}

/* ── Card header ──────────────────────────────────────────────────────── */
.cpr-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9; gap: 12px;
}
.cpr-card-head-left { display: flex; align-items: center; gap: 10px; }
.cpr-card-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
}
.cpr-card-icon.amber  { background: #fef3c7; color: #b45309; }
.cpr-card-icon.slate  { background: #f1f5f9; color: #475569; }
.cpr-card-icon.green  { background: #dcfce7; color: #15803d; }
.cpr-label {
    font-size: .72rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #94a3b8;
}
.cpr-card-title { font-size: .93rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.2; }

/* ── Pill count ───────────────────────────────────────────────────────── */
.cpr-count {
    font-size: .72rem; font-weight: 700; border-radius: 20px;
    padding: 3px 11px; border: 1px solid;
}
.cpr-count.neutral { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
.cpr-count.warn    { background: #fef3c7; border-color: #fde68a; color: #92400e; }

/* ── Table ────────────────────────────────────────────────────────────── */
.cpr-table { width: 100%; border-collapse: collapse; }
.cpr-table th {
    font-size: .67rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #94a3b8;
    padding: 10px 18px; background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.cpr-table td {
    padding: 14px 18px; border-bottom: 1px solid #f1f5f9;
    font-size: .835rem; color: #334155; vertical-align: middle;
}
.cpr-table tbody tr:last-child td { border-bottom: none; }
.cpr-table tbody tr { transition: background .1s; }
.cpr-table tbody tr:hover { background: #fafbfd; }

/* ── Doctor cell ──────────────────────────────────────────────────────── */
.cpr-doctor { display: flex; align-items: center; gap: 10px; }
.cpr-avatar {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    letter-spacing: .02em;
}
.cpr-doctor-name { font-weight: 600; color: #0f172a; font-size: .855rem; }
.cpr-doctor-sub  { font-size: .72rem; color: #94a3b8; margin-top: 1px; }

/* ── Stat cell ────────────────────────────────────────────────────────── */
.cpr-stat { font-size: .93rem; font-weight: 700; color: #1e293b; }
.cpr-stat-sub { font-size: .72rem; color: #94a3b8; }

/* ── Reason tags ──────────────────────────────────────────────────────── */
.cpr-reason-tag {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .7rem; font-weight: 600; padding: 2px 8px;
    border-radius: 5px; background: #fff7ed; color: #9a3412;
    border: 1px solid #fed7aa; margin: 1px 0;
}

/* ── Status badges ────────────────────────────────────────────────────── */
.cpr-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .7rem; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; border-radius: 6px; padding: 3px 9px;
}
.cpr-badge i { font-size: .75rem; }
.cpr-badge-granted { background: #dcfce7; color: #15803d; }
.cpr-badge-denied  { background: #fee2e2; color: #b91c1c; }
.cpr-badge-pending { background: #fef3c7; color: #92400e; }
.cpr-badge-other   { background: #f1f5f9; color: #475569; }

.cpr-outcome-reason { font-size: .73rem; color: #64748b; margin-top: 4px; line-height: 1.4; }
.cpr-outcome-by     { font-size: .7rem; color: #94a3b8; margin-top: 2px; }

/* ── Action buttons ───────────────────────────────────────────────────── */
.cpr-approve-btn, .cpr-deny-btn {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .775rem; font-weight: 600; padding: 6px 14px;
    border-radius: 8px; border: 1px solid; cursor: pointer;
    transition: all .12s; white-space: nowrap;
}
.cpr-approve-btn {
    background: #f0fdf4; border-color: #bbf7d0; color: #15803d;
}
.cpr-approve-btn:hover { background: #dcfce7; border-color: #86efac; }
.cpr-deny-btn {
    background: #fff; border-color: #fca5a5; color: #dc2626;
}
.cpr-deny-btn:hover { background: #fff5f5; border-color: #f87171; }

/* ── When cell ────────────────────────────────────────────────────────── */
.cpr-when { font-size: .79rem; color: #94a3b8; white-space: nowrap; }

/* ── Empty state ──────────────────────────────────────────────────────── */
.cpr-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 40px 24px; gap: 8px; text-align: center;
}
.cpr-empty-icon {
    width: 48px; height: 48px; border-radius: 14px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: #94a3b8; margin-bottom: 4px;
}
.cpr-empty-title { font-size: .88rem; font-weight: 600; color: #475569; margin: 0; }
.cpr-empty-sub   { font-size: .78rem; color: #94a3b8; margin: 0; }

/* ── Card footer ──────────────────────────────────────────────────────── */
.cpr-footer {
    padding: 11px 22px; border-top: 1px solid #f1f5f9;
    font-size: .72rem; color: #94a3b8; display: flex; align-items: center; gap: 6px;
}

/* ── All-clear banner ─────────────────────────────────────────────────── */
.cpr-allclear {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 22px;
}
.cpr-allclear-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: #dcfce7; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: #15803d; flex-shrink: 0;
}
.cpr-allclear-text { font-size: .845rem; font-weight: 600; color: #166534; }
.cpr-allclear-sub  { font-size: .76rem; color: #4ade80; margin-top: 1px; }
</style>

<div class="cpr-wrap">

    {{-- ── Page header ──────────────────────────────────────────────── --}}
    <div class="cpr-page-header">
        <div>
            <h1 class="cpr-page-title">Case Pull Requests</h1>
            <p class="cpr-page-sub">
                A doctor asks for a number of cases. The pool grants the oldest ones
                they are eligible for. Requests from doctors over their SLA wait here
                for your decision.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.routing.sla') }}" class="cpr-nav-btn">
                <i class="bi bi-speedometer2"></i> Provider SLA
            </a>
            <a href="{{ route('admin.routing.exceptions') }}" class="cpr-nav-btn">
                <i class="bi bi-exclamation-triangle"></i> Exceptions
            </a>
        </div>
    </div>

    {{-- ── Waiting on you ───────────────────────────────────────────── --}}
    <div class="cpr-card">
        <div class="cpr-card-head">
            <div class="cpr-card-head-left">
                <div class="cpr-card-icon {{ $pending->isNotEmpty() ? 'amber' : 'green' }}">
                    <i class="bi {{ $pending->isNotEmpty() ? 'bi-hourglass-split' : 'bi-check-circle' }}"></i>
                </div>
                <div>
                    <div class="cpr-label">Action required</div>
                    <div class="cpr-card-title">Waiting on You</div>
                </div>
            </div>
            <span class="cpr-count {{ $pending->isNotEmpty() ? 'warn' : 'neutral' }}">
                {{ $pending->count() }} pending
            </span>
        </div>

        @if($pending->isEmpty())
            <div class="cpr-allclear">
                <div class="cpr-allclear-icon"><i class="bi bi-check-lg"></i></div>
                <div>
                    <div class="cpr-allclear-text">All clear — nothing waiting for a decision.</div>
                    <div class="cpr-allclear-sub" style="color:#4ade80;">New pull requests will appear here when a doctor is over their SLA threshold.</div>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="cpr-table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Requested</th>
                            <th>Over SLA on</th>
                            <th>Waiting</th>
                            <th style="text-align:right;">Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($pending as $req)
                        <tr>
                            <td>
                                <div class="cpr-doctor">
                                    @php $name = $req->clinician->user?->name ?? 'Doctor #'.$req->clinician_id;
                                         $initials = collect(explode(' ', $name))->map(fn($w)=>strtoupper($w[0]))->take(2)->implode(''); @endphp
                                    <div class="cpr-avatar">{{ $initials }}</div>
                                    <div>
                                        <div class="cpr-doctor-name">{{ $name }}</div>
                                        <div class="cpr-doctor-sub">Clinician</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="cpr-stat">{{ $req->requested_count }}</span>
                                <div class="cpr-stat-sub">{{ Str::plural('case', $req->requested_count) }}</div>
                            </td>
                            <td>
                                @foreach($req->blocking_reasons ?? [] as $code)
                                    <div class="cpr-reason-tag">
                                        <i class="bi bi-flag-fill" style="font-size:.6rem;"></i>
                                        {{ \App\Services\Routing\PoolEligibilityEvaluator::REASON_LABELS[$code] ?? $code }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="cpr-when">{{ $req->created_at->diffForHumans(null, true) }}</td>
                            <td style="text-align:right;">
                                <div class="d-flex gap-2 justify-content-end">
                                    <form method="POST" action="{{ route('admin.routing.pull-requests.approve', $req->id) }}">
                                        @csrf
                                        <input type="hidden" name="decision_note" value="">
                                        <button type="submit" class="cpr-approve-btn">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.routing.pull-requests.deny', $req->id) }}">
                                        @csrf
                                        <input type="hidden" name="decision_note" value="">
                                        <button type="submit" class="cpr-deny-btn">
                                            <i class="bi bi-x-lg"></i> Deny
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="cpr-footer">
                <i class="bi bi-info-circle"></i>
                Approving grants the oldest eligible cases for this doctor — it does not override any daily cap.
            </div>
        @endif
    </div>

    {{-- ── Recent requests ──────────────────────────────────────────── --}}
    <div class="cpr-card">
        <div class="cpr-card-head">
            <div class="cpr-card-head-left">
                <div class="cpr-card-icon slate">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="cpr-label">History</div>
                    <div class="cpr-card-title">Recent Requests</div>
                </div>
            </div>
        </div>

        @if($recent->isEmpty())
            <div class="cpr-empty">
                <div class="cpr-empty-icon"><i class="bi bi-inbox"></i></div>
                <p class="cpr-empty-title">No requests yet</p>
                <p class="cpr-empty-sub">Completed pull requests will appear here.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="cpr-table">
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
                    @foreach($recent as $req)
                        @php
                            $s = $req->status;
                            $badgeCls = match($s) {
                                'GRANTED' => 'cpr-badge-granted',
                                'DENIED'  => 'cpr-badge-denied',
                                'PENDING' => 'cpr-badge-pending',
                                default   => 'cpr-badge-other',
                            };
                            $badgeIcon = match($s) {
                                'GRANTED' => 'bi-check-circle-fill',
                                'DENIED'  => 'bi-x-circle-fill',
                                'PENDING' => 'bi-hourglass-split',
                                default   => 'bi-circle',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="cpr-doctor">
                                    @php $name = $req->clinician->user?->name ?? 'Doctor #'.$req->clinician_id;
                                         $initials = collect(explode(' ', $name))->map(fn($w)=>strtoupper($w[0]))->take(2)->implode(''); @endphp
                                    <div class="cpr-avatar">{{ $initials }}</div>
                                    <div>
                                        <div class="cpr-doctor-name">{{ $name }}</div>
                                        <div class="cpr-doctor-sub">Clinician</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="cpr-stat">{{ $req->requested_count }}</span>
                            </td>
                            <td>
                                <span class="cpr-stat">{{ $req->granted_count ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="cpr-badge {{ $badgeCls }}">
                                    <i class="bi {{ $badgeIcon }}"></i>
                                    {{ $req->statusLabel() }}
                                </span>
                                @if($req->shortfall_reason)
                                    <div class="cpr-outcome-reason">{{ $req->shortfall_reason }}</div>
                                @endif
                                @if($req->decidedBy)
                                    <div class="cpr-outcome-by">
                                        <i class="bi bi-person me-1"></i>by {{ $req->decidedBy->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="cpr-when">{{ $req->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
