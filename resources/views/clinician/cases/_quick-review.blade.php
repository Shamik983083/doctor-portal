{{--
    Quick-review inner markup for one case, the preview's renderDrawer body.
    $d is the card array from queue.blade's $buildCard. Used server-side for the
    initial (top) case; the JS renderer in queue.blade produces the same markup
    on a row click so the two never diverge.
--}}
<div class="panel-heading">
    <div>
        <div class="eyebrow">Quick review · {{ $d['id'] }}</div>
        <h2>{{ $d['name'] }}</h2>
        <div class="demo-chips">
            @if(!empty($d['gender']))<span class="demo-chip">{{ ucfirst($d['gender']) }}</span>@endif
            @if(isset($d['age']) && $d['age'] !== null)<span class="demo-chip">{{ $d['age'] }} yrs</span>@endif
            @if(isset($d['bmi']) && $d['bmi'] !== null)<span class="demo-chip">BMI {{ number_format((float)$d['bmi'], 1) }}</span>@endif
            @if(!empty($d['state']))<span class="demo-chip">{{ $d['state'] }}</span>@endif
            @php $idStatus = strtolower($d['id_verified'] ?? ''); @endphp
            <span class="demo-chip {{ $idStatus === 'verified' ? 'chip-verified' : 'chip-unverified' }}">{{ $idStatus === 'verified' ? 'ID Verified' : 'ID Unverified' }}</span>
        </div>
        <p>{{ $d['company'] }}{{ !empty($d['subStorefront']) ? ' · ' . $d['subStorefront'] : '' }} · Request {{ $d['term'] }} · {{ $d['dose'] }}{{ !empty($d['state']) ? ' · ' . $d['state'] : '' }}</p>
    </div>
    <div class="quick-pills">
        <span class="pill {{ $d['triage'] }}">{{ ucfirst($d['triage']) }}</span>
        <span class="pill {{ $d['tone'] }}">{{ $d['label'] }}</span>
    </div>
</div>

<div class="quick-review-grid">
    {{-- 1. AI draft summary --}}
    <div>
        <div class="subheading">AI draft summary</div>
        <div class="ai-draft-chip"><span class="pill neutral">AI draft · provider-assist only</span></div>
        <ul class="summary-list">
            @forelse($d['summary'] as $line)
                <li>{{ $line }}</li>
            @empty
                <li>No AI draft yet. It is composed from the storefront intake once that is sent for this case.</li>
            @endforelse
        </ul>
        <p class="ai-honesty">{{ config('ai.enabled') && config('ai.baa_confirmed') ? 'AI model draft. Statements are composed from the recorded intake answers and model output. The draft never approves, prescribes, or sends anything.' : 'Deterministic placeholder, no model ran. Statements are composed only from the recorded intake answers. The draft never approves, prescribes, or sends anything.' }}</p>

        @if(empty($d['source']))
            <p class="ai-honesty">No intake answers were passed for this case yet.</p>
        @endif
    </div>

    {{-- 2. Triage and findings --}}
    <div>
        <div class="subheading">Triage and findings</div>
        <p class="protocol-version">{{ $d['protocol'] ? $d['protocol'] . ' · ' : '' }}classification {{ ucfirst($d['triage']) }}</p>
        <ul class="finding-list">
            @forelse($d['findings'] as $f)
                <li><span class="finding-dot {{ $f['tone'] }}"></span> {{ $f['text'] }}</li>
            @empty
                <li><span class="finding-dot neutral"></span> No findings recorded from intake yet.</li>
            @endforelse
        </ul>
        @if(!empty($d['collab']))
            <p class="ai-honesty" style="margin-top:8px"><strong>Collaborating:</strong> {{ $d['collab'] }}</p>
        @endif
        <div class="subheading holds-heading">Active workflow holds</div>
        @if($d['hold'])
            <ul class="holds-list"><li><code class="audit-verb">WORKFLOW_HOLD_ACTIVE</code></li></ul>
        @else
            <p class="no-holds">No active workflow holds.</p>
        @endif
    </div>

    {{-- 3. Provider actions (link to the real case flows) --}}
    <div>
        <div class="subheading">Provider actions</div>
        @unless(in_array($d['status'] ?? '', ['approved', 'completed', 'cancelled']))
            <a class="button-primary full-width" href="{{ $d['approveUrl'] }}" data-review-url="{{ $d['reviewUrl'] }}">Review and approve</a>
        @endunless
        <a class="button-secondary full-width" href="{{ $d['showUrl'] }}">Show Full Profile</a>
        <a class="button-secondary full-width" href="{{ $d['msgUrl'] }}">Send Message</a>
        @unless(in_array($d['status'] ?? '', ['approved', 'completed', 'cancelled']))
            <a class="button-danger full-width" href="{{ $d['showUrl'] }}">Reject</a>
        @endunless
    </div>
</div>

{{-- 4. Source answers — full-width below the 3-column grid so expanding never breaks the layout --}}
@if(!empty($d['source']))
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--line)">
        <button type="button" class="button-secondary" id="srcToggle" aria-expanded="false">View source answers ({{ count($d['source']) }})</button>
        <div class="qa-sheet" id="sourceAnswers" hidden>
            @foreach($d['source'] as $row)
                @if($row['consent'])
                    <div class="qa"><dt>{{ $row['name'] }}</dt><dd>{{ $row['q'] }}</dd></div>
                @else
                    <div class="qa"><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] }}</dd></div>
                @endif
            @endforeach
        </div>
    </div>
@endif

{{-- 5. Visit history tabs — Current visit / Prior visit, only for refill cases --}}
@if(!empty($d['isRefill']))
@php
    $hasCurrent = !empty($d['current']);
    $hasPrior   = !empty($d['prior']);
    $defaultTab = $hasCurrent ? 'current' : 'prior';
@endphp
<div class="visit-history" style="margin-top:16px">
    <div class="vt-bar">
        <button type="button" class="vt-btn {{ $defaultTab === 'current' ? 'active' : '' }}" data-vtab="current">Current visit</button>
        <button type="button" class="vt-btn {{ $defaultTab === 'prior' ? 'active' : '' }}" data-vtab="prior">Prior visit</button>
        <span class="pill" style="font-size:10px;margin-left:auto">Refill</span>
    </div>

    {{-- Current visit panel --}}
    <div class="vt-panel" data-vtpanel="current" {{ $defaultTab !== 'current' ? 'hidden' : '' }}>
        @if($hasCurrent)
            @php $cv = $d['current']; @endphp
            @if(!empty($cv['date']))<p style="font-size:12px;color:var(--muted);margin:0 0 10px">Prescribed {{ $cv['date'] }}</p>@endif
            <div class="subheading">Prescribed</div>
            @if(!empty($cv['meds']))
                @foreach($cv['meds'] as $med)
                    <div style="padding:5px 0;border-bottom:1px solid var(--line)">
                        <span style="font-weight:680;font-size:13px">{{ $med['name'] }}</span>
                        @if(!empty($med['sig']))<span style="color:var(--muted);font-size:12px"> · {{ $med['sig'] }}</span>@endif
                        @if(!empty($med['dosing']))<span style="color:var(--muted);font-size:12px"> · {{ $med['dosing'] }}</span>@endif
                        @if(isset($med['refills']))<span style="color:var(--soft-muted);font-size:11px"> Refills: {{ $med['refills'] }}</span>@endif
                    </div>
                @endforeach
            @else
                <p class="ai-honesty">No medications recorded for this visit.</p>
            @endif
        @else
            <p class="ai-honesty" style="margin:12px 0">No prescription yet — pending clinician review.</p>
        @endif
    </div>

    {{-- Prior visit panel --}}
    <div class="vt-panel" data-vtpanel="prior" {{ $defaultTab !== 'prior' ? 'hidden' : '' }}>
        @if($hasPrior)
            @php $pm = $d['prior']; @endphp
            @if(!empty($pm['date']) || !empty($pm['clinician']))
                <p style="font-size:12px;color:var(--muted);margin:0 0 10px">
                    @if(!empty($pm['date'])){{ $pm['date'] }}@endif
                    @if(!empty($pm['clinician'])) · Dr. {{ $pm['clinician'] }}@endif
                </p>
            @endif
            <div class="subheading">Prescribed</div>
            @if(!empty($pm['meds']))
                @foreach($pm['meds'] as $med)
                    <div style="padding:5px 0;border-bottom:1px solid var(--line)">
                        <span style="font-weight:680;font-size:13px">{{ $med['name'] }}</span>
                        @if(!empty($med['sig']))<span style="color:var(--muted);font-size:12px"> · {{ $med['sig'] }}</span>@endif
                        @if(!empty($med['dosing']))<span style="color:var(--muted);font-size:12px"> · {{ $med['dosing'] }}</span>@endif
                        @if(isset($med['refills']))<span style="color:var(--soft-muted);font-size:11px"> Refills: {{ $med['refills'] }}</span>@endif
                    </div>
                @endforeach
            @else
                <p class="ai-honesty">No medications recorded.</p>
            @endif

            @if(!empty($pm['intake']))
                <div class="subheading" style="margin-top:12px">Prior intake answers</div>
                <div class="qa-sheet" style="margin-top:0">
                    @foreach($pm['intake'] as $row)
                        <div class="qa"><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] }}</dd></div>
                    @endforeach
                </div>
            @endif

            @if(!empty($pm['note']))
                <div class="subheading" style="margin-top:12px">Clinical note</div>
                <p style="font-size:12px;color:var(--ink);white-space:pre-wrap;margin:4px 0">{{ $pm['note'] }}</p>
            @endif

            <a href="{{ $pm['url'] }}" style="display:inline-block;margin-top:10px;font-size:12px;color:var(--accent)">View full prior case →</a>
        @else
            <p class="ai-honesty" style="margin:12px 0">No prior completed case found for this patient with this partner.</p>
        @endif
    </div>
</div>
@endif
