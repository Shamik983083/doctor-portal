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
    <div class="card" id="fullQueue">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="ma-eyebrow">Provider review queue</div>
                    <div class="ma-title">Fast review, full context one click away</div>
                    <div class="ma-sub">Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision.</div>
                </div>
                <div class="align-self-center">
                    <button id="batchPreflightBtn" class="btn btn-sm btn-primary" disabled>Run batch preflight (<span id="batchPreflightCount">0</span>)</button>
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
            <div id="batchToolbar" style="display:none; border-top:1px solid #dee2e6; margin-top:.5rem; padding-top:.5rem; align-items:center; justify-content:space-between;">
                <span id="batchCount" class="ma-pill neutral">0 selected</span>
                <label class="form-check-label d-flex align-items-center gap-2 text-muted small" style="cursor:pointer">
                    <input type="checkbox" id="batchSelectAll" class="form-check-input m-0" title="Select all eligible">
                    Select all batch-eligible Green cases
                </label>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:2rem"></th>
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
                            <th>Batch Eligibility</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                        @php
                            $st       = strtoupper($case->patient_state ?? optional($case->patient)->state ?? '');
                            $videoReq = $st && $case->caseOfferings->some(fn($co) => optional($co->offering)->isVideoRequiredInState($st));
                            $idv      = strtolower($case->patient?->id_verified_status ?? '');
                        @endphp
                        @php
                            $batchEligible = $case->triage === 'green'
                                && in_array($case->status, ['waiting', 'assigned'])
                                && !$case->hold_status
                                && $case->status !== 'support';

                            // Extract the first IDV-related triage reason for display
                            $idvTriageReason = collect($case->triage_reasons ?? [])
                                ->filter(fn($r) => str_starts_with($r, 'ID_'))
                                ->map(fn($r) => 'IDV: ' . trim(substr($r, strpos($r, ':') + 1)))
                                ->first();

                            if ($case->triage === 'red') {
                                $batchBand   = 'red';
                                $batchLabel  = 'Blocked';
                                $batchReason = $idvTriageReason ?? 'Red triage · hard stop';
                            } elseif ($case->triage === 'yellow') {
                                $batchBand   = 'yellow';
                                $batchLabel  = 'Review';
                                $batchReason = $idvTriageReason ?? 'Yellow triage · review required';
                            } elseif ($case->hold_status) {
                                $batchBand   = 'red';
                                $batchLabel  = 'Blocked';
                                $batchReason = 'Workflow hold active';
                            } elseif ($case->status === 'support') {
                                $batchBand   = 'red';
                                $batchLabel  = 'Blocked';
                                $batchReason = 'Escalated to support';
                            } elseif (!in_array($case->status, ['waiting', 'assigned'])) {
                                $batchBand   = 'red';
                                $batchLabel  = 'Blocked';
                                $batchReason = 'Status: ' . ucfirst($case->status);
                            } else {
                                $batchBand   = 'green';
                                $batchLabel  = 'Eligible';
                                $batchReason = null;
                            }
                        @endphp
                        <tr data-uuid="{{ $case->uuid }}" data-batch="{{ $batchEligible ? '1' : '0' }}">
                            <td>
                                @if($batchEligible)
                                    <input type="checkbox" class="form-check-input batch-cb" data-uuid="{{ $case->uuid }}" title="Select for batch review">
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
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
                            <td>
                                <span class="ma-pill {{ $batchBand }}">{{ $batchLabel }}</span>
                                @if($batchReason)<div class="batch-reason">{{ $batchReason }}</div>@endif
                            </td>
                            <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="btn btn-sm btn-primary">Review &rarr;</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="15" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases in queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($cases->hasPages())
        <div class="card-footer">{{ $cases->links() }}</div>
        @endif
    </div>

    {{-- Top 10 cases compact card --}}
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="ma-eyebrow">Pending review</div>
                <div class="ma-title">Top cases</div>
                <div class="ma-sub">Highest-attention cases sorted by triage priority.</div>
            </div>
            <a href="#fullQueue" class="btn btn-sm btn-outline-primary">View all cases &uarr;</a>
        </div>
        <div class="card-body p-0" style="overflow:visible">
            <div class="table-responsive" style="overflow-x:auto;min-height:1px">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Triage</th>
                            <th>Patient</th>
                            <th>Company</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases->getCollection()->take(10) as $topRow)
                        <tr>
                            <td><x-triage-pill :case="$topRow" /></td>
                            <td>
                                <strong>{{ $topRow->patient?->full_name ?? 'N/A' }}</strong>
                                @if($topRow->unread_messages_count > 0)
                                    <span class="ma-pill accent ms-1">{{ $topRow->unread_messages_count }} new</span>
                                @endif
                            </td>
                            <td>{{ $topRow->partner?->name ?? '—' }}</td>
                            <td><small class="text-muted">{{ $topRow->created_at->diffForHumans(null, true) }}</small></td>
                            <td><span class="badge badge-status-{{ $topRow->status }}">{{ ucfirst($topRow->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('clinician.cases.show', $topRow->uuid) }}" class="btn btn-sm btn-primary">Review &rarr;</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No cases in queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Quick-review panel --}}
    @if($topCase)
    @php
        $tcState = strtoupper($topCase->patient_state ?? optional($topCase->patient)->state ?? '');
        $tcVideo = $tcState && $topCase->caseOfferings->some(fn($co) => optional($co->offering)->isVideoRequiredInState($tcState));
    @endphp
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="ma-eyebrow">Quick review · {{ $topCase->external_id ?? 'CASE-'.$topCase->id }}</div>
                <div class="ma-title">{{ $topCase->patient?->full_name ?? 'Patient' }}</div>
                <div class="ma-sub">{{ $topCase->partner?->name ?? '—' }} · highest-attention case in current view.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <x-triage-pill :case="$topCase" />
                @if($tcVideo)<span class="ma-pill yellow">Video required</span>@endif
                <button type="button" class="btn btn-sm btn-outline-secondary" id="quickReviewToggle"
                        onclick="(function(btn){var body=document.getElementById('quickReviewBody');var hidden=body.style.display==='none'||body.style.display==='';body.style.display=hidden?'block':'none';btn.textContent=hidden?'Hide':'Show details';})(this)">
                    Show details
                </button>
            </div>
        </div>
        <div class="card-body" id="quickReviewBody" style="display:none">
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
                        <a class="btn btn-sm btn-outline-warning" href="{{ route('clinician.cases.show', $topCase->uuid) }}#supportModal">Escalate to Support &rarr;</a>
                        <a class="btn btn-sm btn-outline-danger" href="{{ route('clinician.cases.show', $topCase->uuid) }}#cancelModal">Reject &rarr;</a>
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
                    $pname   = optional($m->patient)->full_name ?? optional($m->case?->patient)->full_name ?? 'Patient';
                    $ini     = collect(explode(' ', trim($pname)))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('');
                    $caseUrl = $m->case ? route('clinician.cases.show', $m->case->uuid) . '#tab-messages' : '#';
                @endphp
                <li class="ma-inbox-thread {{ !$m->is_read ? 'unread' : '' }}">
                    <a href="{{ $caseUrl }}" class="d-flex align-items-center gap-3 text-decoration-none text-reset w-100">
                        <span class="ma-inbox-avatar flex-shrink-0">{{ $ini ?: '?' }}</span>
                        <span class="ma-inbox-body flex-grow-1 min-w-0">
                            <span class="ma-inbox-top">
                                <strong>{{ $pname }}</strong>
                                <span class="ma-inbox-waiting">{{ $m->created_at?->diffForHumans() }}</span>
                            </span>
                            <span class="ma-inbox-snippet d-block text-truncate">{{ \Illuminate\Support\Str::limit($m->body, 90) }}</span>
                        </span>
                        <i class="bi bi-chevron-right text-muted flex-shrink-0 small"></i>
                    </a>
                </li>
                @empty
                <li class="ma-inbox-thread">
                    <span class="ma-inbox-body"><span class="ma-inbox-snippet">No patient messages yet.</span></span>
                </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Batch preflight / prescription / attest modal --}}
    <div class="modal fade" id="batchModal" tabindex="-1" aria-labelledby="batchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchModalLabel"><i class="bi bi-check2-all me-2"></i>Batch Approve &amp; Prescribe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:80vh; overflow-y:auto;">

                    {{-- Step 1: preflight results table --}}
                    <div id="batchPreflightResults"></div>

                    {{-- Step 2: Prescription form — shown after preflight when passes exist --}}
                    <div id="batchPrescriptionSection" style="display:none" class="mt-3">
                        <hr class="my-3">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-clipboard2-pulse text-primary fs-5"></i>
                            <h6 class="fw-semibold mb-0">Prescription — applied to all passing cases</h6>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border small fw-normal">shared</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="card h-100">
                                    <div class="card-header py-2 bg-light">
                                        <small class="fw-semibold text-secondary"><i class="bi bi-file-medical me-1"></i>Clinical Information</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label form-label-sm fw-semibold">Diagnoses <span class="text-danger">*</span></label>
                                            <textarea id="batchDiagnoses" class="form-control form-control-sm" rows="4"
                                                      placeholder="e.g. E66.01 – Morbid obesity due to excess calories…"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label form-label-sm fw-semibold">Directions</label>
                                            <textarea id="batchDirections" class="form-control form-control-sm" rows="3"
                                                      placeholder="General administration instructions for the patient…"></textarea>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label form-label-sm fw-semibold">Medical Necessity</label>
                                            <textarea id="batchMedNecessity" class="form-control form-control-sm" rows="3"
                                                      placeholder="Justify medical necessity for prescribed medications…"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="card h-100">
                                    <div class="card-header py-2 bg-light">
                                        <small class="fw-semibold text-secondary"><i class="bi bi-capsule me-1"></i>Medications</small>
                                    </div>
                                    <div class="card-body pb-2">
                                        <div class="position-relative mb-3">
                                            <input type="text" id="batchMedSearch" class="form-control form-control-sm"
                                                   placeholder="Search and add a medication…" autocomplete="off">
                                            <div id="batchMedDropdown" class="border rounded bg-white shadow-sm position-absolute w-100 d-none"
                                                 style="z-index:2000; max-height:200px; overflow-y:auto; top:100%; left:0;"></div>
                                        </div>
                                        <div id="batchMedContainer"></div>
                                        <p id="batchNoMedsMsg" class="text-muted small text-center py-2 mb-0">
                                            <i class="bi bi-info-circle me-1"></i>Medications from passing cases are pre-loaded. Add or remove as needed.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: attestation --}}
                    <div id="batchAttestSection" style="display:none" class="mt-3 p-3 border rounded bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="batchAttestCheck">
                            <label class="form-check-label fw-semibold" for="batchAttestCheck">
                                I have reviewed all passing cases above and attest that approving them and submitting this prescription is clinically appropriate.
                            </label>
                        </div>
                    </div>

                    <div id="batchSubmitResults" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="batchSubmitBtn" disabled>
                        <i class="bi bi-check-lg me-1"></i>Approve &amp; Submit Prescriptions
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
{{-- Pusher JS — used by the Provider Inbox real-time listener (Reverb WebSocket server) --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<style>
.batch-reason { font-size: .72rem; color: #6c757d; margin-top: .2rem; line-height: 1.3; }
</style>
<script>
/* ── Provider Inbox — real-time via Reverb ────────────────────────────────
   Subscribes to the private-provider-inbox channel. When a new inbound
   patient message arrives, it is prepended to the inbox list without a
   page reload. The inbox badge counter is not tracked here; the user sees
   the message immediately and can click through to the case.
   ──────────────────────────────────────────────────────────────────────── */
(function () {
    var csrfToken   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var caseBaseUrl = '{{ url('/clinician/cases') }}';
    var inboxList   = document.querySelector('.ma-inbox-list');

    var pusher = new Pusher('{{ config('reverb.apps.apps.0.key') }}', {
        wsHost:            '{{ config('reverb.apps.apps.0.options.host', 'localhost') }}',
        wsPort:            {{ config('reverb.apps.apps.0.options.port', 8080) }},
        wssPort:           {{ config('reverb.apps.apps.0.options.port', 8080) }},
        forceTLS:          {{ config('reverb.apps.apps.0.options.useTLS', false) ? 'true' : 'false' }},
        disableStats:      true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint:      '{{ url('/broadcasting/auth') }}',
        auth: {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        },
    });

    var channel = pusher.subscribe('private-provider-inbox');

    channel.bind('NewPatientMessage', function (data) {
        if (!inboxList) return;

        /* Compute two-letter initials from patient name */
        var ini = (data.patientName || 'P')
            .split(' ')
            .map(function (w) { return w.charAt(0).toUpperCase(); })
            .slice(0, 2)
            .join('');

        var caseUrl  = caseBaseUrl + '/' + data.caseUuid + '#tab-messages';
        var timeAgo  = 'just now';

        var li = document.createElement('li');
        li.className = 'ma-inbox-thread unread';
        li.innerHTML =
            '<a href="' + escHtml(caseUrl) + '" class="d-flex align-items-center gap-3 text-decoration-none text-reset w-100">' +
            '<span class="ma-inbox-avatar flex-shrink-0">' + escHtml(ini) + '</span>' +
            '<span class="ma-inbox-body flex-grow-1 min-w-0">' +
                '<span class="ma-inbox-top">' +
                    '<strong>' + escHtml(data.patientName || 'Patient') + '</strong>' +
                    '<span class="ma-inbox-waiting">' + timeAgo + '</span>' +
                '</span>' +
                '<span class="ma-inbox-snippet d-block text-truncate">' + escHtml(data.snippet || '') + '</span>' +
            '</span>' +
            '<i class="bi bi-chevron-right text-muted flex-shrink-0 small"></i>' +
            '</a>';

        /* Remove the "No patient messages yet" placeholder if present */
        var placeholder = inboxList.querySelector('.ma-inbox-thread:only-child .ma-inbox-snippet');
        if (placeholder && placeholder.textContent.trim() === 'No patient messages yet.') {
            inboxList.innerHTML = '';
        }

        inboxList.insertBefore(li, inboxList.firstChild);

        /* Brief highlight to draw the clinician's eye */
        li.style.transition = 'background .4s';
        li.style.background = 'rgba(59,130,246,.08)';
        setTimeout(function () { li.style.background = ''; }, 2000);
    });

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>

<script>
(function () {
    const preflightUrl = '{{ route('clinician.cases.batch.preflight') }}';
    const submitUrl    = '{{ route('clinician.cases.batch.submit') }}';
    const csrf         = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Queue toolbar elements
    const selectAll        = document.getElementById('batchSelectAll');
    const preflightBtn     = document.getElementById('batchPreflightBtn');
    const preflightCountEl = document.getElementById('batchPreflightCount');
    const batchCountEl     = document.getElementById('batchCount');
    const batchToolbar     = document.getElementById('batchToolbar');

    // Modal elements — lazy init so Bootstrap defer-load doesn't crash our IIFE
    const batchModalEl     = document.getElementById('batchModal');
    const resultsEl        = document.getElementById('batchPreflightResults');
    const prescriptionSection = document.getElementById('batchPrescriptionSection');
    const attestSection    = document.getElementById('batchAttestSection');
    const attestCheck      = document.getElementById('batchAttestCheck');
    const submitBtn        = document.getElementById('batchSubmitBtn');
    const submitResultsEl  = document.getElementById('batchSubmitResults');

    // Prescription form elements
    const batchDiagnoses   = document.getElementById('batchDiagnoses');
    const batchDirections  = document.getElementById('batchDirections');
    const batchMedNecessity= document.getElementById('batchMedNecessity');
    const batchMedSearch   = document.getElementById('batchMedSearch');
    const batchMedDropdown = document.getElementById('batchMedDropdown');
    const batchMedContainer= document.getElementById('batchMedContainer');
    const batchNoMedsMsg   = document.getElementById('batchNoMedsMsg');

    // Offerings pool — populated from preflight results for passing cases
    let batchOfferings = [];
    let batchMedIdx    = 0;

    // ── Checkbox / toolbar ──────────────────────────────────────────────────
    function getChecked() {
        return [...document.querySelectorAll('.batch-cb:checked')].map(cb => cb.dataset.uuid);
    }

    function updateCount() {
        const uuids = getChecked();
        const n = uuids.length;
        batchCountEl.textContent = n + ' selected';
        batchCountEl.className = 'ma-pill ' + (n > 0 ? 'green' : 'neutral');
        preflightBtn.disabled = n === 0;
        preflightCountEl.textContent = n;
        batchToolbar.style.display = n > 0 ? 'flex' : 'none';
    }

    document.querySelectorAll('.batch-cb').forEach(cb => {
        cb.addEventListener('change', () => {
            updateCount();
            const allCbs = document.querySelectorAll('.batch-cb');
            selectAll.checked = allCbs.length > 0 && [...allCbs].every(c => c.checked);
        });
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.batch-cb').forEach(cb => { cb.checked = selectAll.checked; });
            updateCount();
        });
    }

    // ── Preflight ────────────────────────────────────────────────────────────
    preflightBtn.addEventListener('click', async () => {
        const uuids = getChecked();
        if (!uuids.length) return;

        preflightBtn.disabled = true;
        resultsEl.innerHTML = '';
        prescriptionSection.style.display = 'none';
        attestSection.style.display = 'none';
        attestCheck.checked = false;
        submitBtn.disabled = true;
        submitResultsEl.innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(batchModalEl).show();

        try {
            const res = await fetch(preflightUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ uuids })
            });
            const data = await res.json();

            let passUuids = [];
            let html = '<table class="table table-sm table-bordered mb-0"><thead><tr>'
                + '<th>Patient</th><th>Triage</th><th>Status</th><th>State</th><th>Result</th>'
                + '</tr></thead><tbody>';
            for (const [uuid, r] of Object.entries(data)) {
                if (r.pass) {
                    passUuids.push(uuid);
                    html += `<tr class="table-success"><td>${esc(r.patient)}</td><td>${esc(r.triage)}</td><td>${esc(r.status)}</td><td>${esc(r.state)}</td><td><span class="badge bg-success">Pass</span></td></tr>`;
                } else {
                    html += `<tr class="table-danger"><td colspan="4">${esc(r.reason ?? 'Unknown error')}</td><td><span class="badge bg-danger">Fail</span></td></tr>`;
                }
            }
            html += '</tbody></table>';
            resultsEl.innerHTML = html;

            if (passUuids.length > 0) {
                // Build union of offerings from all passing cases (dedup by id)
                const seen = new Set();
                batchOfferings = [];
                for (const [uuid, r] of Object.entries(data)) {
                    if (r.pass && Array.isArray(r.offerings)) {
                        r.offerings.forEach(o => {
                            if (!seen.has(o.id)) {
                                seen.add(o.id);
                                batchOfferings.push(o);
                            }
                        });
                    }
                }

                // Reset prescription section
                batchMedContainer.innerHTML = '';
                batchNoMedsMsg.classList.remove('d-none');
                batchMedIdx = 0;
                batchDiagnoses.value     = '';
                batchDirections.value    = '';
                batchMedNecessity.value  = '';
                batchDiagnoses.classList.remove('is-invalid');

                // Pre-populate medications from passing cases
                batchOfferings.forEach(o => addBatchMedication(o));

                prescriptionSection.style.display = '';
                attestSection.style.display       = '';
                attestSection.dataset.passUuids   = JSON.stringify(passUuids);
            }
        } catch (err) {
            resultsEl.innerHTML = '<div class="alert alert-danger">Preflight request failed. Please try again.</div>';
        } finally {
            preflightBtn.disabled = false;
        }
    });

    // ── Medication search ────────────────────────────────────────────────────
    batchMedSearch.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        batchMedDropdown.innerHTML = '';
        if (!q) { batchMedDropdown.classList.add('d-none'); return; }

        const matches = batchOfferings.filter(o =>
            o.name.toLowerCase().includes(q) ||
            (o.internal_name || '').toLowerCase().includes(q)
        );

        if (!matches.length) {
            batchMedDropdown.innerHTML = '<div class="px-3 py-2 text-muted small">No matches found.</div>';
        } else {
            matches.forEach(o => {
                const item = document.createElement('div');
                item.className = 'px-3 py-2 border-bottom';
                item.style.cursor = 'pointer';
                item.innerHTML = `<span class="fw-semibold">${esc(o.name)}</span>`
                    + (o.internal_name ? ` <small class="text-muted ms-1">${esc(o.internal_name)}</small>` : '');
                item.addEventListener('click', () => addBatchMedication(o));
                batchMedDropdown.appendChild(item);
            });
        }
        batchMedDropdown.classList.remove('d-none');
    });

    document.addEventListener('click', function (e) {
        if (!batchMedSearch.contains(e.target) && !batchMedDropdown.contains(e.target)) {
            batchMedDropdown.classList.add('d-none');
        }
    });

    function addBatchMedication(o) {
        batchMedDropdown.classList.add('d-none');
        batchMedSearch.value = '';
        batchNoMedsMsg.classList.add('d-none');

        if (batchDirections && !batchDirections.value.trim() && o.directions) {
            batchDirections.value = o.directions;
        }

        const row = document.createElement('div');
        row.className = 'border rounded mb-3 p-3 position-relative bg-light';
        row.dataset.offeringId = o.id || '';

        row.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 batch-remove-med"
                    style="line-height:1; padding:2px 7px; font-size:.75rem;">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="mb-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Medication Name</label>
                <input type="text" data-field="name" class="form-control form-control-sm" value="${esc(o.name)}" required>
            </div>
            <div class="mb-2">
                <label class="form-label form-label-sm fw-semibold mb-1">Compound Formula</label>
                <input type="text" data-field="compound_formula" class="form-control form-control-sm" value="${esc(o.compound_formula || '')}">
            </div>
            <div class="row g-2 mb-1">
                <div class="col-4 col-md-2">
                    <label class="form-label form-label-sm fw-semibold mb-1">Refills</label>
                    <input type="number" data-field="refills" min="0" class="form-control form-control-sm" value="${esc(o.refills || '')}">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label form-label-sm fw-semibold mb-1">Quantity</label>
                    <input type="number" data-field="quantity" min="0" step="0.01" class="form-control form-control-sm" value="${esc(o.quantity || '')}">
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label form-label-sm fw-semibold mb-1">Days Supply</label>
                    <input type="number" data-field="days_supply" min="0" class="form-control form-control-sm" value="${esc(o.days_supply || '')}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm fw-semibold mb-1">Dispense Unit</label>
                    <input type="text" data-field="dispense_unit" class="form-control form-control-sm" value="${esc(o.dispense_unit || '')}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm fw-semibold mb-1">Days Until Dispense</label>
                    <input type="number" data-field="days_until_dispense" min="0" class="form-control form-control-sm" value="${esc(o.days_until_dispense || '')}">
                </div>
            </div>
        `;

        row.querySelector('.batch-remove-med').addEventListener('click', () => {
            row.remove();
            if (!batchMedContainer.children.length) batchNoMedsMsg.classList.remove('d-none');
        });

        batchMedContainer.appendChild(row);
    }

    function collectBatchMedications() {
        const meds = [];
        batchMedContainer.querySelectorAll('[data-offering-id]').forEach(card => {
            meds.push({
                offering_id:         card.dataset.offeringId || null,
                name:                card.querySelector('[data-field="name"]').value,
                compound_formula:    card.querySelector('[data-field="compound_formula"]').value,
                refills:             card.querySelector('[data-field="refills"]').value || null,
                quantity:            card.querySelector('[data-field="quantity"]').value || null,
                days_supply:         card.querySelector('[data-field="days_supply"]').value || null,
                dispense_unit:       card.querySelector('[data-field="dispense_unit"]').value,
                days_until_dispense: card.querySelector('[data-field="days_until_dispense"]').value || null,
            });
        });
        return meds;
    }

    // ── Attest + diagnoses gate ───────────────────────────────────────────────
    function updateSubmitBtn() {
        submitBtn.disabled = !(attestCheck.checked && batchDiagnoses.value.trim().length > 0);
    }
    attestCheck.addEventListener('change', updateSubmitBtn);
    batchDiagnoses.addEventListener('input', updateSubmitBtn);

    // ── Submit ───────────────────────────────────────────────────────────────
    submitBtn.addEventListener('click', async () => {
        const passUuids = JSON.parse(attestSection.dataset.passUuids || '[]');
        if (!passUuids.length) return;

        const diagnoses = batchDiagnoses.value.trim();
        if (!diagnoses) {
            batchDiagnoses.classList.add('is-invalid');
            batchDiagnoses.focus();
            return;
        }
        batchDiagnoses.classList.remove('is-invalid');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Submitting…';
        submitResultsEl.innerHTML = '';

        try {
            const res = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    uuids:             passUuids,
                    diagnoses:         diagnoses,
                    directions:        batchDirections.value,
                    medical_necessity: batchMedNecessity.value,
                    medications:       collectBatchMedications(),
                })
            });
            const data = await res.json();

            let successCount = 0;
            let html = '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Patient</th><th>Result</th></tr></thead><tbody>';
            for (const [uuid, r] of Object.entries(data)) {
                if (r.success) {
                    successCount++;
                    html += `<tr class="table-success"><td>${esc(r.patient)}</td><td><span class="badge bg-success">Approved &amp; Prescribed</span></td></tr>`;
                } else {
                    html += `<tr class="table-danger"><td>${esc(r.error ?? 'Unknown error')}</td><td><span class="badge bg-danger">Failed</span></td></tr>`;
                }
            }
            html += '</tbody></table>';
            submitResultsEl.innerHTML = html;

            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Done';

            if (successCount > 0) {
                setTimeout(() => window.location.reload(), 2500);
            }
        } catch (err) {
            submitResultsEl.innerHTML = '<div class="alert alert-danger">Submit request failed. Please try again.</div>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Approve &amp; Submit Prescriptions';
        }
    });

    function esc(str) {
        if (str == null) return '—';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
@endsection
