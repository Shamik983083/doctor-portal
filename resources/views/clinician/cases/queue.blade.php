@extends('layouts.clinician-exact')

@section('title', 'Case Queue')
@section('page-title', 'Case Queue')

{{--
    Case Queue, rebuilt to the design preview's EXACT markup and CSS
    (Devin msg 2265: "rebuild it exactly and deploy"). The table is the preview's
    <table class="review-grid"> with pinned Triage / Queue Time / Full Name
    columns, driven by real cases. Column set and order match
    docs/design-preview/index.html QUEUE_COLS exactly. Medication columns read
    the storefront's clinical_intake via PatientCase::queueClinical().
--}}

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician</div>
    <h1>Case Queue</h1>
    <p>Unclaimed cases you are licensed to review. Claim one to start.</p>
</div>

<section class="panel queue-panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">Provider review queue</div>
            <h2>Fast review, full context one click away</h2>
            <p>Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision.</p>
        </div>
        <div class="queue-actions">
            <button type="button" class="button-secondary">Filters</button>
            <button type="button" class="button-primary" id="preflight" disabled>Run batch preflight (<span id="pfCount">0</span>)</button>
        </div>
    </div>

    <div class="queue-toolbar">
        <div class="queue-count">
            <strong id="selCount">0</strong> selected ·
            <span><span id="eligCount">0</span> batch-eligible · <span id="blkCount">0</span> rows blocked from selection</span>
        </div>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <label class="density"><input type="checkbox" id="compact" /> Compact columns</label>
            <label class="select-all"><input type="checkbox" id="selectAll" /> Select batch-eligible Green cases</label>
        </div>
    </div>

    <div class="review-grid-scroll">
        <table class="review-grid" id="reviewGrid">
            <thead>
                <tr>
                    <th class="pin pin-select"></th>
                    <th class="pin pin-triage">Triage</th>
                    <th class="pin pin-time">Queue Time</th>
                    <th class="pin pin-name">Full Name</th>
                    <th>ID VER</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>BMI</th>
                    <th>On GLP</th>
                    <th>Med 1 Req</th>
                    <th>Med 1 Dose</th>
                    <th>Med 1 Term</th>
                    <th>Titrate?</th>
                    <th>Med 2 Req</th>
                    <th>Med 3 Req</th>
                    <th>Med 4 Req</th>
                    <th>Company</th>
                    <th>Allergies</th>
                    <th>STD ZOF</th>
                    <th>Video Visit</th>
                    <th>Batch Eligibility</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                    @php
                        $clin = $case->queueClinical();
                        $idv  = strtolower($case->patient?->id_verified_status ?? '') === 'verified';
                        $planLabel = ['Titration' => 'Titrate', 'Hold' => 'Hold'][$clin['plan']] ?? $clin['plan'];

                        $eligible = $case->triage === 'green'
                            && in_array($case->status, ['waiting', 'assigned'])
                            && !$case->hold_status;

                        if ($case->triage === 'red') {
                            $batchTone = 'red'; $batchLabel = 'Blocked'; $batchReason = 'Red hard stop';
                        } elseif ($case->hold_status) {
                            $batchTone = 'red'; $batchLabel = 'Blocked'; $batchReason = 'Workflow hold active';
                        } elseif ($case->status === 'support') {
                            $batchTone = 'red'; $batchLabel = 'Blocked'; $batchReason = 'Escalated to support';
                        } elseif ($case->triage === 'yellow') {
                            $batchTone = 'yellow'; $batchLabel = 'Review'; $batchReason = 'Yellow triage · review required';
                        } elseif (!in_array($case->status, ['waiting', 'assigned'])) {
                            $batchTone = 'red'; $batchLabel = 'Blocked'; $batchReason = 'Status: ' . ucfirst($case->status);
                        } else {
                            $batchTone = 'green'; $batchLabel = 'Eligible'; $batchReason = null;
                        }
                    @endphp
                    <tr data-row="{{ $case->uuid }}">
                        <td class="pin pin-select" data-stop="1">
                            <input type="checkbox" class="row-check" data-check="{{ $case->uuid }}"
                                   {{ $eligible ? '' : 'disabled' }}
                                   title="{{ $eligible ? 'Select ' . $case->patient?->full_name : $batchReason }}">
                        </td>
                        <td class="pin pin-triage"><span class="pill {{ $case->triage }}">{{ ucfirst($case->triage ?? 'unclassified') }}</span></td>
                        <td class="pin pin-time">{{ $case->created_at->diffForHumans(null, true) }}</td>
                        <td class="pin pin-name">
                            <a class="patient-link" href="{{ route('clinician.cases.show', $case->uuid) }}">{{ $case->patient?->full_name ?? 'Unknown' }}</a>
                        </td>
                        <td><span class="pill {{ $idv ? 'green' : 'red' }}">{{ $idv ? 'Y' : 'N' }}</span></td>
                        <td>{{ strtoupper(substr($case->patient?->gender ?? '-', 0, 1)) }}</td>
                        <td>{{ $case->patient?->age ?? '-' }}</td>
                        <td>{{ !is_null($case->patient?->bmi) ? number_format($case->patient->bmi, 1) : '-' }}</td>
                        <td>{{ $clin['onGlp'] }}</td>
                        <td>{{ $clin['product'] }}</td>
                        <td>{{ $clin['dose'] }}</td>
                        <td>{{ $clin['term'] }}</td>
                        <td>{{ $planLabel }}</td>
                        <td>{{ $clin['med2'] }}</td>
                        <td>{{ $clin['med3'] }}</td>
                        <td>{{ $clin['med4'] }}</td>
                        <td>{{ $case->partner?->name ?? '-' }}</td>
                        <td>
                            @if($clin['allergy'] === 'Y')
                                <span class="allergy-detail-wrap" data-stop="1">
                                    <button type="button" class="allergy-flag">Y &#9432;</button>
                                    <span role="tooltip" class="allergy-tooltip">{{ $clin['allergyDetail'] ?? 'Allergy flagged at intake' }}</span>
                                </span>
                            @else
                                {{ $clin['allergy'] }}
                            @endif
                        </td>
                        <td>{{ $clin['zofran'] }}</td>
                        <td><span class="pill {{ strtolower($clin['video']) === 'clear' ? 'green' : ($clin['video'] === '-' ? 'neutral' : 'yellow') }}">{{ $clin['video'] }}</span></td>
                        <td class="batch-cell">
                            <span class="pill {{ $batchTone }}">{{ $batchLabel }}</span>
                            @if($batchReason)<div class="batch-reason">{{ $batchReason }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="21"><div class="stub"><strong>Nothing in this queue</strong>No case currently matches this filter.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- Quick review below the grid, the preview's renderDrawer (Devin msg 2267:
     "the case queue should have a quick review below with the relevant data like
     the reference"). Shows the top case in the current view; its summary,
     findings and source answers come from the storefront clinical_intake. --}}
@php($top = $cases->first())
@if($top)
    @php
        $tclin  = $top->queueClinical();
        $tci    = $top->clinical_intake ?? [];
        $tSummary = $tci['summary'] ?? [];
        $tFindings = $tci['findings'] ?? [];
        $tSource = $tci['sourceAnswers'] ?? [];
        $tProtocol = $tci['protocolVersion'] ?? null;

        $topEligible = $top->triage === 'green' && in_array($top->status, ['waiting','assigned']) && !$top->hold_status;
        if ($top->triage === 'red') { $tTone='red'; $tLabel='Blocked'; }
        elseif ($top->hold_status || $top->status === 'support') { $tTone='red'; $tLabel='Blocked'; }
        elseif ($top->triage === 'yellow') { $tTone='yellow'; $tLabel='Review'; }
        elseif (!in_array($top->status, ['waiting','assigned'])) { $tTone='red'; $tLabel='Blocked'; }
        else { $tTone='green'; $tLabel='Eligible'; }
    @endphp
    <section class="panel quick-review">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">Quick review · {{ $top->external_id ?? \Illuminate\Support\Str::limit($top->uuid, 8, '') }}</div>
                <h2>{{ $top->patient?->full_name ?? 'Unknown' }}</h2>
                <p>{{ $top->partner?->name ?? '-' }} · Request {{ $tclin['term'] }} · {{ $tclin['dose'] }}</p>
            </div>
            <div class="quick-pills">
                <span class="pill {{ $top->triage }}">{{ ucfirst($top->triage ?? 'unclassified') }}</span>
                <span class="pill {{ $tTone }}">{{ $tLabel }}</span>
            </div>
        </div>

        <div class="quick-review-grid">
            {{-- 1. AI draft summary --}}
            <div>
                <div class="subheading">AI draft summary</div>
                <div class="ai-draft-chip"><span class="pill neutral">AI draft · provider-assist only</span></div>
                <ul class="summary-list">
                    @forelse($tSummary as $line)
                        <li>{{ is_array($line) ? ($line[0] ?? '') : $line }}</li>
                    @empty
                        <li>No AI draft yet. It is composed from the storefront intake once that is sent for this case.</li>
                    @endforelse
                </ul>
                <p class="ai-honesty">Deterministic placeholder, no model ran. Statements are composed only from the recorded intake answers. The draft never approves, prescribes, or sends anything.</p>

                @if(!empty($tSource))
                    <div class="source-answers">
                        @foreach($tSource as $k => $v)
                            <div><dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{{ ($v === null || $v === '') ? 'Not answered' : $v }}</dd></div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 2. Triage and findings --}}
            <div>
                <div class="subheading">Triage and findings</div>
                <p class="protocol-version">{{ $tProtocol ? $tProtocol . ' · ' : '' }}classification {{ ucfirst($top->triage ?? 'unclassified') }}</p>
                <ul class="finding-list">
                    @forelse($tFindings as $f)
                        <li><span class="finding-dot {{ is_array($f) ? ($f[0] ?? 'neutral') : 'neutral' }}"></span> {{ is_array($f) ? ($f[1] ?? '') : $f }}</li>
                    @empty
                        <li><span class="finding-dot neutral"></span> No findings recorded from intake yet.</li>
                    @endforelse
                </ul>
                <div class="subheading holds-heading">Active workflow holds</div>
                @if($top->hold_status)
                    <ul class="holds-list"><li><code class="audit-verb">WORKFLOW_HOLD_ACTIVE</code></li></ul>
                @else
                    <p class="no-holds">No active workflow holds.</p>
                @endif
            </div>

            {{-- 3. Provider actions (link to the real case flows) --}}
            <div>
                <div class="subheading">Provider actions</div>
                <a class="button-primary full-width" href="{{ route('clinician.cases.prescribe.form', $top->uuid) }}">Review and approve</a>
                <a class="button-secondary full-width" href="{{ route('clinician.cases.show', $top->uuid) }}">Request information</a>
                <a class="button-danger full-width" href="{{ route('clinician.cases.show', $top->uuid) }}">Reject</a>
                @unless($topEligible)
                    <p class="action-reason">{{ $tLabel === 'Eligible' ? '' : 'This case is not batch-eligible; review it individually.' }}</p>
                @endunless
            </div>
        </div>
    </section>
@endif

@if($cases->hasPages())
    <div style="margin-top:16px">{{ $cases->withQueryString()->links() }}</div>
@endif
@endsection

@section('scripts')
<script>
    // Batch selection, mirroring the preview's interactivity: the count and the
    // preflight button react to the eligible checkboxes; select-all toggles the
    // eligible Green rows; compact toggles the dense column mode.
    (function () {
        var grid = document.getElementById('reviewGrid');
        if (!grid) return;

        var checks    = Array.prototype.slice.call(grid.querySelectorAll('.row-check'));
        var eligible  = checks.filter(function (c) { return !c.disabled; });
        var selCount  = document.getElementById('selCount');
        var pfCount   = document.getElementById('pfCount');
        var eligCount = document.getElementById('eligCount');
        var blkCount  = document.getElementById('blkCount');
        var preflight = document.getElementById('preflight');
        var selectAll = document.getElementById('selectAll');
        var compact   = document.getElementById('compact');

        if (eligCount) eligCount.textContent = eligible.length;
        if (blkCount)  blkCount.textContent  = checks.length - eligible.length;

        function refresh() {
            var n = eligible.filter(function (c) { return c.checked; }).length;
            if (selCount) selCount.textContent = n;
            if (pfCount)  pfCount.textContent  = n;
            if (preflight) preflight.disabled = n === 0;
            if (selectAll) selectAll.checked = eligible.length > 0 && n === eligible.length;
        }

        eligible.forEach(function (c) { c.addEventListener('change', refresh); });

        if (selectAll) selectAll.addEventListener('change', function () {
            eligible.forEach(function (c) { c.checked = selectAll.checked; });
            refresh();
        });

        if (compact) compact.addEventListener('change', function () {
            grid.classList.toggle('compact', compact.checked);
        });

        refresh();
    })();
</script>
@endsection
