@extends('layouts.ma-portal')

@section('title', 'MA Portal · Practitioner')
@section('page-title', 'MA Portal — Practitioner')

@section('content')
<x-ma-styles />
<div class="ma-surface">

    <div class="card" style="margin-bottom:1rem">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div><div class="ma-eyebrow">MA-DOCPORTAL role view</div><div class="ma-title">Practitioner</div>
            <div class="ma-sub">How MA presents the provider surface. Functional here from real case data; backend-heavy pieces are shown as clearly-labeled previews.</div></div>
        </div>
    </div>

    {{-- Triage summary (functional) --}}
    <div class="ma-metric-grid">
        <div class="ma-metric accent"><div class="ma-metric-label">Open in queue</div><div class="ma-metric-value">{{ $triageMetrics['open'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill red"><span class="ma-dot"></span>Red</span></div><div class="ma-metric-value">{{ $triageMetrics['red'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill yellow"><span class="ma-dot"></span>Yellow</span></div><div class="ma-metric-value">{{ $triageMetrics['yellow'] }}</div></div>
        <div class="ma-metric"><div class="ma-metric-label"><span class="ma-pill green"><span class="ma-dot"></span>Green</span></div><div class="ma-metric-value">{{ $triageMetrics['green'] }}</div></div>
    </div>

    {{-- Review queue — dense quick-look grid (functional) --}}
    <div class="card">
        <div class="card-header"><div class="ma-eyebrow">Provider review queue</div><div class="ma-title">Fast review, full context one click away</div><div class="ma-sub">Highest-attention cases first. Triage is a review-priority signal, not a clinical decision.</div></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Triage</th><th>Time</th><th>Patient</th><th>IDV</th><th>Sex</th><th>Age</th><th>BMI</th><th>Offerings</th><th>Video visit</th><th>Company</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($cases as $case)
                @php
                    $st = strtoupper($case->patient_state ?? optional($case->patient)->state ?? '');
                    $videoReq = in_array($st, $videoStates, true);
                @endphp
                <tr>
                    <td><x-triage-pill :case="$case" /></td>
                    <td><small>{{ $case->created_at->diffForHumans(null, true) }}</small></td>
                    <td><strong>{{ $case->patient->full_name ?? 'N/A' }}</strong></td>
                    <td>@php $idv = strtolower($case->patient->id_verified_status ?? ''); @endphp<span class="ma-pill {{ $idv === 'verified' ? 'green' : 'red' }}">{{ $idv === 'verified' ? 'Y' : 'N' }}</span></td>
                    <td>{{ strtoupper(substr($case->patient->gender ?? '—', 0, 1)) }}</td>
                    <td>{{ $case->patient->age ?? '—' }}</td>
                    <td>{{ $case->patient->bmi ? number_format($case->patient->bmi, 1) : '—' }}</td>
                    <td>@foreach($case->caseOfferings->take(2) as $co)<span class="ma-pill neutral">{{ $co->offering->name ?? '?' }}</span>@endforeach</td>
                    <td>@if($videoReq)<span class="ma-pill yellow" title="{{ $st }} requires a synchronous video visit — a waiver never satisfies this">Video required</span>@else<span class="ma-pill green">No requirement</span>@endif</td>
                    <td>{{ $case->partner->name ?? '—' }}</td>
                    <td><span class="ma-pill neutral">{{ ucfirst($case->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-5">No open cases. Seed with <code>php artisan db:seed --class=Database\Seeders\TriageDemoCasesSeeder</code>.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    </div>

    {{-- Quick-review drawer: AI draft summary + triage/holds + decision panel (functional) --}}
    @if($topCase)
    @php
        $tcState = strtoupper($topCase->patient_state ?? optional($topCase->patient)->state ?? '');
        $tcVideo = in_array($tcState, $videoStates, true);
    @endphp
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><div class="ma-eyebrow">Quick review · {{ $topCase->external_id ?? 'CASE-'.$topCase->id }}</div><div class="ma-title">{{ $topCase->patient->full_name ?? 'Patient' }}</div><div class="ma-sub">{{ $topCase->partner->name ?? '—' }} · fast review with full context one click away.</div></div>
            <div class="d-flex gap-2"><x-triage-pill :case="$topCase" />@if($tcVideo)<span class="ma-pill yellow">Video required</span>@endif</div>
        </div>
        <div class="card-body">
            <div class="ma-quick-grid">
                {{-- Col 1 — AI draft summary --}}
                <div>
                    <div class="ma-subheading">AI draft summary</div>
                    <div class="mb-2"><span class="ma-pill neutral">Assembled from recorded intake — no model ran</span></div>
                    <ul class="ma-summary-list">
                        @forelse($aiSummary as $line)<li>{{ $line }}</li>@empty<li>No recorded intake for this case yet.</li>@endforelse
                    </ul>
                    <p class="ma-honesty">Deterministic summary composed only from this case's recorded intake and triage. A live model fills this after an approved clinical AI service is enabled; the draft never approves, prescribes, or sends anything.</p>
                    @if($intake->isNotEmpty())
                    <details>
                        <summary class="btn btn-sm btn-outline-primary">View source answers</summary>
                        <dl class="ma-source-answers">
                            @foreach($intake as $row)<div><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] ?: '—' }}</dd></div>@endforeach
                        </dl>
                    </details>
                    @endif
                </div>
                {{-- Col 2 — triage findings + holds --}}
                <div>
                    <div class="ma-subheading">Triage &amp; findings</div>
                    <ul class="ma-finding-list">
                        <li><span class="ma-finding-dot {{ $topCase->triage ?: 'yellow' }}"></span>Classification {{ $topCase->triageLabel() }} — review-priority signal, not a clinical decision.</li>
                        @foreach(collect($topCase->triage_reasons ?? [])->take(3) as $r)<li><span class="ma-finding-dot {{ $topCase->triage ?: 'yellow' }}"></span>{{ $r }}</li>@endforeach
                        <li><span class="ma-finding-dot {{ $tcVideo ? 'yellow' : 'green' }}"></span>{{ $tcVideo ? $tcState.' requires a synchronous video visit.' : 'No state video requirement.' }}</li>
                    </ul>
                    <div class="ma-subheading" style="margin-top:.6rem">Active workflow holds</div>
                    @if($topCase->hold_status || $topCase->status === 'support')
                        <div class="ma-chips">@if($topCase->hold_status)<span class="ma-pill yellow">Workflow hold</span>@endif @if($topCase->status === 'support')<span class="ma-pill red">Support escalation</span>@endif</div>
                    @else
                        <p class="ma-sub mb-0">No active workflow holds.</p>
                    @endif
                </div>
                {{-- Col 3 — decision panel (functional-styled; buttons inert / link to real case) --}}
                <div>
                    <div class="ma-subheading">Provider decision</div>
                    <div class="d-grid gap-2 mb-3">
                        <button class="btn btn-sm btn-primary" disabled title="Preview — decisions are made on the real case screen">Approve &amp; queue order</button>
                        <button class="btn btn-sm btn-outline-primary" disabled title="Preview only">Request information</button>
                        <button class="btn btn-sm btn-outline-primary ma-btn-danger" disabled title="Preview only">Reject</button>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('clinician.cases.show', $topCase->uuid) }}">Open full case &rarr;</a>
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

    {{-- Workflow holds + waivers (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Operational safety</div><div class="ma-title">Workflow holds &amp; waivers</div><div class="ma-sub">Holds are an operational axis, separate from triage. A waiver can release an operational hold but <strong>never</strong> satisfies a state synchronous-video requirement.</div></div><x-ma-preview-tag /></div>
        <div class="card-body">
            @forelse($heldCases as $hc)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="ma-pill neutral">{{ $hc->patient->full_name ?? 'Patient' }}</span>
                @if($hc->hold_status)<span class="ma-pill yellow">Workflow hold</span>@endif
                @if($hc->status === 'support')<span class="ma-pill red">Support escalation</span>@endif
                <button class="btn btn-sm btn-outline-primary ms-auto" disabled title="Preview only — authorized waiver is a backend action in MA">Waive (authorized)</button>
            </div>
            @empty
            <p class="text-muted mb-0">No cases on hold in the visible queue.</p>
            @endforelse
        </div>
    </div>

    {{-- Patient messaging inbox (functional) --}}
    <div class="card">
        <div class="card-header"><div class="ma-eyebrow">Patient messages</div><div class="ma-title">Provider inbox</div><div class="ma-sub">Recent messages across your cases. Driven by the real Message model (direction, sender, body, timestamp).</div></div>
        <div class="card-body">
            <ul class="ma-inbox-list">
                @forelse($messages as $m)
                @php $pname = optional($m->patient)->full_name ?? optional(optional($m->case)->patient)->full_name ?? 'Patient'; $ini = collect(explode(' ', trim($pname)))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode(''); @endphp
                <li class="ma-inbox-thread {{ ($m->direction === 'inbound' && ! $m->is_read) ? 'unread' : '' }}">
                    <span class="ma-inbox-avatar">{{ $ini ?: '?' }}</span>
                    <span class="ma-inbox-body">
                        <span class="ma-inbox-top"><strong>{{ $pname }}</strong><span class="ma-pill neutral">{{ ucfirst($m->direction ?? $m->sender_type ?? 'msg') }}</span><span class="ma-inbox-waiting">{{ $m->created_at?->diffForHumans() }}</span></span>
                        <span class="ma-inbox-snippet">{{ \Illuminate\Support\Str::limit($m->body, 90) }}</span>
                    </span>
                </li>
                @empty
                <li class="ma-inbox-thread"><span class="ma-inbox-body"><span class="ma-inbox-snippet">No patient messages yet. New inbound/outbound messages appear here as they are recorded.</span></span></li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Intake summary (functional) --}}
    <div class="card">
        <div class="card-header"><div class="ma-eyebrow">Recorded intake</div><div class="ma-title">Intake summary @if($topCase)<span class="ma-sub" style="font-weight:400">· {{ $topCase->patient->full_name ?? 'Patient' }}</span>@endif</div><div class="ma-sub">The recorded questionnaire answers for the top case, exactly as captured — the source of truth behind the triage band and AI draft above.</div></div>
        <div class="card-body">
            @if($intake->isNotEmpty())
            <dl class="ma-source-answers">
                @foreach($intake as $row)<div><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] ?: '—' }}</dd></div>@endforeach
            </dl>
            @else
            <p class="text-muted mb-0">No recorded intake answers for the top case. Intake answers appear here from <code>case_questions</code> or questionnaire responses once captured.</p>
            @endif
        </div>
    </div>

    {{-- Chart note (functional read) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Clinical documentation</div><div class="ma-title">Chart note</div><div class="ma-sub">Notes are drafted then signed; a signed note is immutable and revised only by a new revision.</div></div><span class="ma-pill neutral">DRAFT</span></div>
        <div class="card-body">
            @if($note)
                <div class="ma-sub mb-2">{{ $note->case->patient->full_name ?? 'Patient' }} · {{ optional($note->clinician->user)->name ?? 'Clinician' }} · {{ ucfirst($note->type ?? 'note') }}</div>
                <p style="margin:0">{{ \Illuminate\Support\Str::limit($note->note, 400) }}</p>
            @else
                <p class="text-muted mb-0">No notes yet. In MA a provider drafts then signs a chart note; each signature is an immutable revision.</p>
            @endif
            <div class="d-flex gap-2 mt-3"><button class="btn btn-sm btn-outline-primary">Edit draft</button><button class="btn btn-sm btn-primary">Sign note</button></div>
        </div>
    </div>

    {{-- Prescription document (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Prescription document</div><div class="ma-title">Signed PDF from the locked order snapshot</div><div class="ma-sub">On approval MA generates one immutable, provider-signed PDF from the locked order snapshot, carrying the attestation. Dispatch sends the exact PDF (never an image) through the transactional outbox.</div></div><x-ma-preview-tag /></div>
        <div class="card-body">
            <ul class="ma-simple-list mb-3">
                <li class="ma-sub">Source: locked provider-approved order snapshot (immutable once signed).</li>
                <li class="ma-sub">Attestation: provider signature bound to the snapshot version.</li>
                <li class="ma-sub">Format: PDF embedded as <code>order.document.pdfBase64</code> when attachment policy is on.</li>
            </ul>
            <button class="btn btn-sm btn-outline-primary" disabled title="Preview only — the signed PDF is generated on approval in MA">View signed PDF</button>
        </div>
    </div>

    {{-- Batch review (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Batch review</div><div class="ma-title">Green batch: preflight → attest → submit</div><div class="ma-sub">Select batch-eligible Green cases, then run preflight. Each case is revalidated independently before a batch draft is seeded.</div></div><x-ma-preview-tag /></div>
        <div class="card-body">
            <ol class="ma-batch-steps">
                <li class="done"><span class="ma-batch-dot"></span><div><strong>Select Green cases</strong><span>Only batch-eligible Green rows can enter the selection.</span></div><span class="ma-pill green">done</span></li>
                <li class="active"><span class="ma-batch-dot"></span><div><strong>Per-case preflight</strong><span>Each case revalidated against true state (intake, triage, holds, eligibility, catalog).</span></div><span class="ma-pill yellow">active</span></li>
                <li><span class="ma-batch-dot"></span><div><strong>Provider attestation</strong><span>One attestation covers the batch; each order keeps its own locked snapshot.</span></div><span class="ma-pill neutral">pending</span></li>
                <li><span class="ma-batch-dot"></span><div><strong>Submit + delivery stagger</strong><span>Signed PDF per order dispatched through the outbox.</span></div><span class="ma-pill neutral">pending</span></li>
            </ol>
            <button class="btn btn-sm btn-primary" disabled title="Preview only">Attest &amp; submit batch</button>
        </div>
    </div>

    {{-- Fulfillment (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Fulfillment</div><div class="ma-title">Order &amp; shipment status</div><div class="ma-sub">Dispatch is a signed PDF from the locked order snapshot, sent through the transactional outbox. VRIO is a mock adapter; LifeFile direct is disabled until sandbox approval.</div></div><x-ma-preview-tag /></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Order</th><th>Route</th><th>Status</th><th>Tracking</th></tr></thead>
            <tbody>
                <tr><td><strong>ORD-4471</strong></td><td>VRIO (mock)</td><td><span class="ma-pill green">Shipped</span></td><td>1Z••••8842</td></tr>
                <tr><td><strong>ORD-4468</strong></td><td>VRIO (mock)</td><td><span class="ma-pill neutral">Accepted</span></td><td>pending</td></tr>
                <tr><td><strong>ORD-4459</strong></td><td>LifeFile (disabled)</td><td><span class="ma-pill yellow">Queued</span></td><td>—</td></tr>
            </tbody>
        </table></div></div>
    </div>
</div>
@endsection
