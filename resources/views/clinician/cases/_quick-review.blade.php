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
        <p>{{ $d['company'] }} · Request {{ $d['term'] }} · {{ $d['dose'] }}</p>
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
        <p class="ai-honesty">Deterministic placeholder, no model ran. Statements are composed only from the recorded intake answers. The draft never approves, prescribes, or sends anything.</p>

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
        <a class="button-primary full-width" href="{{ $d['approveUrl'] }}">Review and approve</a>
        <a class="button-secondary full-width" href="{{ $d['showUrl'] }}">Request information</a>
        <a class="button-danger full-width" href="{{ $d['showUrl'] }}">Reject</a>
    </div>
</div>
