@extends('layouts.clinician')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

@section('content')
<div class="ma-surface">

    {{-- Triage summary cards --}}
    <div class="ma-metric-grid">
        <div class="ma-metric accent"><div class="ma-metric-label">Open in queue</div><div class="ma-metric-value">{{ $triageMetrics['open'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill red"><span class="ma-dot"></span>Red</span></div><div class="ma-metric-value">{{ $triageMetrics['red'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill yellow"><span class="ma-dot"></span>Yellow</span></div><div class="ma-metric-value">{{ $triageMetrics['yellow'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill green"><span class="ma-dot"></span>Green</span></div><div class="ma-metric-value">{{ $triageMetrics['green'] }}</div></div>
    </div>

    {{-- Provider review queue --}}
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="ma-eyebrow">Provider review queue</div>
                    <div class="ma-title">Fast review, full context one click away</div>
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search patient name…" value="{{ request('search') }}">
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
                            <th>Time</th>
                            <th>Patient</th>
                            <th>IDV</th>
                            <th>Sex</th>
                            <th>Age</th>
                            <th>BMI</th>
                            <th>Offerings</th>
                            <th>Video visit</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                        @php
                            $st       = strtoupper($case->patient_state ?? optional($case->patient)->state ?? '');
                            $videoReq = in_array($st, $videoStates, true);
                            $idv      = strtolower($case->patient?->id_verified_status ?? '');
                        @endphp
                        <tr>
                            <td><x-triage-pill :case="$case" /></td>
                            <td><small>{{ $case->created_at->diffForHumans(null, true) }}</small></td>
                            <td>
                                <strong>{{ $case->patient?->full_name ?? 'N/A' }}</strong>
                                @if($case->unread_messages_count > 0)
                                    <span class="ma-pill accent ms-1">{{ $case->unread_messages_count }} new</span>
                                @endif
                            </td>
                            <td><span class="ma-pill {{ $idv === 'verified' ? 'green' : 'red' }}">{{ $idv === 'verified' ? 'Y' : 'N' }}</span></td>
                            <td>{{ strtoupper(substr($case->patient?->gender ?? '—', 0, 1)) }}</td>
                            <td>{{ $case->patient?->age ?? '—' }}</td>
                            <td>{{ !is_null($case->patient?->bmi) ? number_format($case->patient->bmi, 1) : '—' }}</td>
                            <td>
                                @foreach($case->caseOfferings->take(2) as $co)
                                    <span class="ma-pill neutral">{{ $co->offering->name ?? '?' }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if($videoReq)
                                    <span class="ma-pill yellow">Required</span>
                                @else
                                    <span class="ma-pill green">Not required</span>
                                @endif
                            </td>
                            <td>{{ $case->partner?->name ?? '—' }}</td>
                            <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                            <td class="text-nowrap">
                                <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                @if($case->status === 'waiting')
                                <form method="POST" action="{{ route('clinician.cases.assign', $case->uuid) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">Claim</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="12" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases in queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($cases->hasPages())
        <div class="card-footer">{{ $cases->links() }}</div>
        @endif
    </div>

    {{-- Quick-review panel --}}
    @if($topCase)
    @php
        $tcState = strtoupper($topCase->patient_state ?? optional($topCase->patient)->state ?? '');
        $tcVideo = in_array($tcState, $videoStates, true);
    @endphp
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="ma-eyebrow">Quick review · {{ $topCase->external_id ?? 'CASE-'.$topCase->id }}</div>
                <div class="ma-title">{{ $topCase->patient?->full_name ?? 'Patient' }}</div>
                <div class="ma-sub">{{ $topCase->partner?->name ?? '—' }} · highest-attention case in current view.</div>
            </div>
            <div class="d-flex gap-2">
                <x-triage-pill :case="$topCase" />
                @if($tcVideo)<span class="ma-pill yellow">Video required</span>@endif
            </div>
        </div>
        <div class="card-body">
            <div class="ma-quick-grid">
                {{-- AI draft summary --}}
                <div>
                    <div class="ma-subheading">Case summary</div>
                    <div class="mb-2"><span class="ma-pill neutral">Assembled from recorded intake</span></div>
                    <ul class="ma-summary-list">
                        @forelse($aiSummary as $line)<li>{{ $line }}</li>@empty<li>No recorded intake for this case yet.</li>@endforelse
                    </ul>
                    @if($intake->isNotEmpty())
                    <details class="mt-2">
                        <summary class="btn btn-sm btn-outline-primary">View source answers</summary>
                        <dl class="ma-source-answers mt-2">
                            @foreach($intake as $row)<div><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] ?: '—' }}</dd></div>@endforeach
                        </dl>
                    </details>
                    @endif
                </div>
                {{-- Triage findings + holds --}}
                <div>
                    <div class="ma-subheading">Triage &amp; findings</div>
                    <ul class="ma-finding-list">
                        <li><span class="ma-finding-dot {{ $topCase->triage ?: 'yellow' }}"></span>Classification {{ $topCase->triageLabel() }} — review-priority signal, not a clinical decision.</li>
                        @foreach(collect($topCase->triage_reasons ?? [])->take(3) as $r)
                            <li><span class="ma-finding-dot {{ $topCase->triage ?: 'yellow' }}"></span>{{ $r }}</li>
                        @endforeach
                        <li><span class="ma-finding-dot {{ $tcVideo ? 'yellow' : 'green' }}"></span>{{ $tcVideo ? $tcState.' requires a synchronous video visit.' : 'No state video requirement.' }}</li>
                    </ul>
                    <div class="ma-subheading mt-2">Active workflow holds</div>
                    @if($topCase->hold_status || $topCase->status === 'support')
                        <div class="ma-chips">
                            @if($topCase->hold_status)<span class="ma-pill yellow">Workflow hold</span>@endif
                            @if($topCase->status === 'support')<span class="ma-pill red">Support escalation</span>@endif
                        </div>
                    @else
                        <p class="ma-sub mb-0">No active workflow holds.</p>
                    @endif
                </div>
                {{-- Decision panel --}}
                <div>
                    <div class="ma-subheading">Actions</div>
                    <div class="d-grid gap-2 mb-3">
                        <a class="btn btn-sm btn-primary" href="{{ route('clinician.cases.show', $topCase->uuid) }}">Open full case &rarr;</a>
                        @if($topCase->status === 'waiting')
                        <form method="POST" action="{{ route('clinician.cases.assign', $topCase->uuid) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary w-100">Claim case</button>
                        </form>
                        @endif
                        @if(in_array($topCase->status, ['assigned']) && optional(Auth::user()->clinician)->id === $topCase->clinician_id)
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('clinician.cases.prescribe.form', $topCase->uuid) }}">Prescribe &rarr;</a>
                        @endif
                    </div>
                    <div class="ma-subheading">Reason codes</div>
                    <ul class="ma-reason-codes">
                        @foreach($reasonCodes as $rc)<li>{{ $rc }}</li>@endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Workflow holds --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="ma-eyebrow">Operational safety</div>
                <div class="ma-title">Workflow holds &amp; waivers</div>
                <div class="ma-sub">Cases currently on hold or escalated to support in the visible queue.</div>
            </div>
        </div>
        <div class="card-body">
            @forelse($heldCases as $hc)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="ma-pill neutral">{{ $hc->patient->full_name ?? 'Patient' }}</span>
                @if($hc->hold_status)<span class="ma-pill yellow">Workflow hold</span>@endif
                @if($hc->status === 'support')<span class="ma-pill red">Support escalation</span>@endif
                <a href="{{ route('clinician.cases.show', $hc->uuid) }}" class="btn btn-sm btn-outline-primary ms-auto">Review</a>
            </div>
            @empty
            <p class="text-muted mb-0">No cases on hold in the current queue.</p>
            @endforelse
        </div>
    </div>

    {{-- Patient messaging inbox --}}
    <div class="card">
        <div class="card-header">
            <div class="ma-eyebrow">Patient messages</div>
            <div class="ma-title">Provider inbox</div>
            <div class="ma-sub">Recent messages across all cases.</div>
        </div>
        <div class="card-body">
            <ul class="ma-inbox-list">
                @forelse($messages as $m)
                @php
                    $pname = optional($m->patient)->full_name ?? optional(optional($m->case)->patient)->full_name ?? 'Patient';
                    $ini   = collect(explode(' ', trim($pname)))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('');
                @endphp
                <li class="ma-inbox-thread {{ ($m->direction === 'inbound' && !$m->is_read) ? 'unread' : '' }}">
                    <span class="ma-inbox-avatar">{{ $ini ?: '?' }}</span>
                    <span class="ma-inbox-body">
                        <span class="ma-inbox-top">
                            <strong>{{ $pname }}</strong>
                            <span class="ma-pill neutral">{{ ucfirst($m->direction ?? 'msg') }}</span>
                            <span class="ma-inbox-waiting">{{ $m->created_at?->diffForHumans() }}</span>
                        </span>
                        <span class="ma-inbox-snippet">{{ \Illuminate\Support\Str::limit($m->body, 90) }}</span>
                    </span>
                </li>
                @empty
                <li class="ma-inbox-thread">
                    <span class="ma-inbox-body"><span class="ma-inbox-snippet">No patient messages yet.</span></span>
                </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Intake summary --}}
    <div class="card">
        <div class="card-header">
            <div class="ma-eyebrow">Recorded intake</div>
            <div class="ma-title">Intake summary@if($topCase) <span class="ma-sub" style="font-weight:400">· {{ $topCase->patient?->full_name ?? 'Patient' }}</span>@endif</div>
            <div class="ma-sub">Questionnaire answers for the top case — source of truth behind triage classification.</div>
        </div>
        <div class="card-body">
            @if($intake->isNotEmpty())
            <dl class="ma-source-answers">
                @foreach($intake as $row)<div><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] ?: '—' }}</dd></div>@endforeach
            </dl>
            @else
            <p class="text-muted mb-0">No recorded intake answers for the top case.</p>
            @endif
        </div>
    </div>

    {{-- Chart note --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="ma-eyebrow">Clinical documentation</div>
                <div class="ma-title">Chart note</div>
                <div class="ma-sub">Most recent clinical note across your cases.</div>
            </div>
            <span class="ma-pill neutral">DRAFT</span>
        </div>
        <div class="card-body">
            @if($note)
                <div class="ma-sub mb-2">{{ $note->case?->patient?->full_name ?? 'Patient' }} · {{ $note->clinician?->user?->name ?? 'Clinician' }} · {{ ucfirst($note->type ?? 'note') }}</div>
                <p style="margin:0">{{ \Illuminate\Support\Str::limit($note->note, 400) }}</p>
            @else
                <p class="text-muted mb-0">No chart notes yet. Notes will appear here as they are created from case screens.</p>
            @endif
        </div>
    </div>

    {{-- Batch review (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="ma-eyebrow">Batch review</div>
                <div class="ma-title">Green batch: preflight → attest → submit</div>
                <div class="ma-sub">Select batch-eligible Green cases, then run preflight. Each case is revalidated independently before a batch draft is seeded.</div>
            </div>
            <x-ma-preview-tag />
        </div>
        <div class="card-body">
            <ol class="ma-batch-steps">
                <li class="done"><span class="ma-batch-dot"></span><div><strong>Select Green cases</strong><span>Only batch-eligible Green rows can enter the selection.</span></div><span class="ma-pill green">done</span></li>
                <li class="active"><span class="ma-batch-dot"></span><div><strong>Per-case preflight</strong><span>Each case revalidated against true state (intake, triage, holds, eligibility, catalog).</span></div><span class="ma-pill yellow">active</span></li>
                <li><span class="ma-batch-dot"></span><div><strong>Provider attestation</strong><span>One attestation covers the batch; each order keeps its own locked snapshot.</span></div><span class="ma-pill neutral">pending</span></li>
                <li><span class="ma-batch-dot"></span><div><strong>Submit + delivery stagger</strong><span>Signed PDF per order dispatched through the outbox.</span></div><span class="ma-pill neutral">pending</span></li>
            </ol>
            <button class="btn btn-sm btn-primary" disabled title="Coming soon">Attest &amp; submit batch</button>
        </div>
    </div>

</div>
@endsection
