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
        <p>{{ $d['company'] }} · Request {{ $d['term'] }} · {{ $d['dose'] }}{{ !empty($d['state']) ? ' · ' . $d['state'] : '' }}</p>
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

        @if(!empty($d['source']))
            <button type="button" class="button-secondary" id="srcToggle" aria-expanded="false">View source answers ({{ count($d['source']) }})</button>
            <div class="qa-sheet" id="sourceAnswers" hidden>
                @foreach($d['source'] as $row)
                    @if($row['consent'])
                        <div class="qa consent">
                            <dt><details><summary>{{ $row['name'] }}</summary><div class="consent-full">{{ $row['q'] }}</div></details></dt>
                            <dd><span class="pill {{ $row['agreed'] ? 'green' : 'red' }}">{{ $row['agreed'] ? 'Agreed' : $row['a'] }}</span></dd>
                        </div>
                    @else
                        <div class="qa"><dt>{{ $row['q'] }}</dt><dd>{{ $row['a'] }}</dd></div>
                    @endif
                @endforeach
            </div>
        @else
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
        <a class="button-primary full-width" href="{{ $d['approveUrl'] }}" data-review-url="{{ $d['reviewUrl'] }}">Review and approve</a>
        <a class="button-secondary full-width" href="{{ $d['showUrl'] }}">Show Full Profile</a>
        <a class="button-secondary full-width" href="{{ $d['msgUrl'] }}">Send Message</a>
        <a class="button-danger full-width" href="{{ $d['showUrl'] }}">Reject</a>
    </div>
</div>

{{-- 4. Prior visit panel — only for refill cases, full-width below the 3-column grid --}}
@if(!empty($d['isRefill']))
    @if(!empty($d['prior']))
        @php $pm = $d['prior']; @endphp
        <details style="margin-top:16px;border:1px solid var(--line);border-radius:14px;overflow:hidden">
            <summary style="padding:12px 16px;cursor:pointer;background:var(--blue-bg);display:flex;align-items:center;gap:10px;list-style:none;font-weight:700;font-size:13px">
                <span style="flex:1">Prior visit</span>
                <span class="pill" style="font-size:10px">Refill</span>
                @if(!empty($pm['date']))<span style="color:var(--muted);font-size:12px;font-weight:500">{{ $pm['date'] }}</span>@endif
                @if(!empty($pm['clinician']))<span style="color:var(--muted);font-size:12px;font-weight:500">Dr. {{ $pm['clinician'] }}</span>@endif
            </summary>
            <div style="padding:14px 16px">
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
            </div>
        </details>
    @else
        <div style="margin-top:12px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--blue-bg);font-size:13px">
            <span class="pill" style="font-size:10px;margin-right:6px">Refill</span>
            <span class="ai-honesty" style="display:inline">No prior completed case found for this patient with this partner.</span>
        </div>
    @endif
@endif
