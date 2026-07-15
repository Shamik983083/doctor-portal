@extends('layouts.clinician')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

@section('content')
<x-ma-styles />
<div class="ma-surface">

    {{-- Slice B — triage summary cards (MA metric-grid look) --}}
    <div class="ma-metric-grid">
        <div class="ma-metric accent">
            <div class="ma-metric-label">Open in queue</div>
            <div class="ma-metric-value">{{ $triageMetrics['open'] }}</div>
        </div>
        <div class="ma-metric">
            <div class="ma-metric-label"><span class="ma-pill red"><span class="ma-dot"></span>Red</span></div>
            <div class="ma-metric-value">{{ $triageMetrics['red'] }}</div>
        </div>
        <div class="ma-metric">
            <div class="ma-metric-label"><span class="ma-pill yellow"><span class="ma-dot"></span>Yellow</span></div>
            <div class="ma-metric-value">{{ $triageMetrics['yellow'] }}</div>
        </div>
        <div class="ma-metric">
            <div class="ma-metric-label"><span class="ma-pill green"><span class="ma-dot"></span>Green</span></div>
            <div class="ma-metric-value">{{ $triageMetrics['green'] }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="ma-eyebrow">Provider review queue</div>
                    <div class="ma-title">Cases awaiting review</div>
                    <div class="ma-sub">Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision.</div>
                </div>
                <div class="ma-legend align-self-center">
                    <span class="ma-pill red"><span class="ma-dot"></span>Red · review carefully</span>
                    <span class="ma-pill yellow"><span class="ma-dot"></span>Yellow · closer look</span>
                    <span class="ma-pill green"><span class="ma-dot"></span>Green · routine</span>
                </div>
            </div>
            <form action="{{ route('clinician.queue') }}" method="GET" class="row g-2 align-items-center">
                <div class="col">
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Search patient name…" value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="triage" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Triage</option>
                        @foreach(['red' => 'Red', 'yellow' => 'Yellow', 'green' => 'Green'] as $val => $lbl)
                            <option value="{{ $val }}" {{ request('triage') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="state" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All States</option>
                        @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $st)
                            <option value="{{ $st }}" {{ request('state') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        @foreach(['waiting','assigned','approved','processing','completed','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">Search</button>
                    @if(request()->anyFilled(['search','state','status','triage']))
                        <a href="{{ route('clinician.queue') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Triage</th>
                            <th>Patient</th>
                            <th>State</th>
                            <th>Partner</th>
                            <th>Offerings</th>
                            <th>Status</th>
                            <th>Wait Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                        <tr>
                            <td><x-triage-pill :case="$case" /></td>
                            <td>
                                <strong>{{ $case->patient->full_name ?? 'N/A' }}</strong>
                                @if($case->unread_messages_count > 0)
                                    <span class="ma-pill accent ms-1">{{ $case->unread_messages_count }} new msg</span>
                                @endif
                                <br><small class="text-muted">{{ $case->patient->state ?? '' }}</small>
                            </td>
                            <td>{{ $case->patient_state ?? $case->patient->state ?? '—' }}</td>
                            <td>{{ $case->partner->name ?? '—' }}</td>
                            <td>
                                @foreach($case->caseOfferings->take(2) as $co)
                                    <span class="ma-pill neutral">{{ $co->offering->name ?? '?' }}</span>
                                @endforeach
                            </td>
                            <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                            <td><small>{{ $case->created_at->diffForHumans() }}</small></td>
                            <td class="d-flex gap-1">
                                <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                @if($case->status === 'waiting')
                                <form method="POST" action="{{ route('clinician.cases.assign', $case->uuid) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">Claim</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases in queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($cases->hasPages())
        <div class="card-footer">{{ $cases->links() }}</div>
        @endif
    </div>
</div>
@endsection
