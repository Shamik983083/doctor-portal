{{--
    The provider review grid + quick review, shared by Case Queue and My Cases so
    they are literally the same surface (Devin msg 2283: My Cases should mimic the
    Case Queue in look, feel and functionality; the preview calls My Cases "the
    same grid, filtered to yours"). Params:
      $cases   (required) the paginator
      $eyebrow $title $sub  the panel heading text
    Both views include this inside @section('view'); the <script> blocks run in
    the body, so no separate scripts section is needed.
--}}
@php
    $eyebrow = $eyebrow ?? 'Provider review queue';
    $title = $title ?? 'Fast review, full context one click away';
    $sub = $sub ?? 'Highest-attention cases surface first. Triage is a review-priority signal, not a clinical decision.';
    $priorCasesMap = $priorCasesMap ?? [];

    $usStates = [
        'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California',
        'CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia',
        'HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
        'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
        'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri',
        'MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey',
        'NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio',
        'OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
        'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont',
        'VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming',
        'DC'=>'Washington D.C.',
    ];
    $expandState = fn(?string $abbr) => ($abbr && $abbr !== '-')
        ? ($usStates[strtoupper(trim($abbr))] ?? $abbr)
        : null;
@endphp

<section class="panel queue-panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">{{ $eyebrow }}</div>
            <h2>{{ $title }}</h2>
            <p>{{ $sub }}</p>
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
            <label class="density"><input type="checkbox" id="compact" checked /> Compact columns</label>
            <label class="select-all"><input type="checkbox" id="selectAll" /> Select batch-eligible Green cases</label>
        </div>
    </div>

    <div class="review-grid-scroll">
        <table class="review-grid compact" id="reviewGrid">
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
                            {{-- Button, not a link: clicking the row updates the quick review below. --}}
                            <button type="button" class="patient-link">{{ $case->patient?->full_name ?? 'Unknown' }}</button>
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

{{-- Quick review below the grid, the preview's renderDrawer. Each visible case's
     data is built once below and embedded as JSON; the panel renders the top
     case server-side and JS re-renders it when a row is clicked. --}}
@php
    $buildCard = function ($case) use ($expandState, $priorCasesMap) {
        $clin = $case->queueClinical();
        $ci   = $case->clinical_intake ?? [];

        // Derive summary bullets from basic clinical fields when the partner
        // omitted the pre-computed summary array (API cases without storefront
        // intake processing). Falls through to the existing $ci['summary'] when
        // that is present, so storefront cases are unaffected.
        $derivedSummary = [];
        if (empty($ci['summary'])) {
            $hasClinical = ($clin['term'] !== '-' || $clin['dose'] !== '-' || $clin['plan'] !== '-');
            if ($hasClinical) {
                $reg = 'Requested regimen:';
                if ($clin['term'] !== '-') $reg .= ' ' . $clin['term'] . ' term';
                if ($clin['dose'] !== '-') $reg .= ($clin['term'] !== '-' ? ', dose ' : ' dose ') . $clin['dose'];
                if ($clin['plan'] !== '-') $reg .= ', plan ' . $clin['plan'];
                $derivedSummary[] = rtrim($reg, ', ') . '.';
            }
            if ($clin['onGlp'] !== '-') {
                $derivedSummary[] = 'Currently on GLP-1 therapy: ' . (strtoupper($clin['onGlp']) === 'Y' ? 'yes' : 'no') . '.';
            }
            if ($clin['allergy'] !== '-') {
                $allergyFlag = strtoupper($clin['allergy']) === 'Y'
                    ? 'yes' . ($clin['allergyDetail'] ? ' — ' . $clin['allergyDetail'] : '')
                    : 'no';
                $derivedSummary[] = 'Concerning allergies flagged: ' . $allergyFlag . '.';
            }
            if ($clin['zofran'] !== '-') {
                $derivedSummary[] = 'Standard Zofran included: ' . (strtoupper($clin['zofran']) === 'Y' ? 'yes' : 'no') . '.';
            }
        }

        if ($case->triage === 'red') { $tone='red'; $label='Blocked'; }
        elseif ($case->hold_status || $case->status === 'support') { $tone='red'; $label='Blocked'; }
        elseif ($case->triage === 'yellow') { $tone='yellow'; $label='Review'; }
        elseif (!in_array($case->status, ['waiting','assigned'])) { $tone='red'; $label='Blocked'; }
        else { $tone='green'; $label='Eligible'; }

        $srcRow = function ($question, $answer, $type = null) {
            $q = trim((string) $question);
            $a = filled($answer) ? (string) $answer : 'Not answered';
            $isConsent = ($type === 'consent')
                || preg_match('/\b(consent|i agree|i acknowledge|i authorize|telehealth|hipaa|terms of|privacy policy)\b/i', $q);
            $agreed = (bool) preg_match('/^(agreed|yes|i agree|accept|accepted|true|1)$/i', trim($a));

            $name = null;
            if ($isConsent) {
                if (preg_match('/telehealth/i', $q))                 { $name = 'Telehealth consent'; }
                elseif (preg_match('/hipaa|privacy/i', $q))          { $name = 'Privacy / HIPAA consent'; }
                elseif (preg_match('/terms/i', $q))                  { $name = 'Terms of use'; }
                elseif (preg_match('/sms|text|message/i', $q))       { $name = 'SMS / messaging consent'; }
                else { $name = \Illuminate\Support\Str::limit($q, 42); }
            }

            return ['q' => $q, 'a' => $a, 'consent' => $isConsent, 'name' => $name, 'agreed' => $agreed];
        };

        $source = [];
        // Show questionnaire Q&A only — caseQuestions first, then questionnaireResponses.
        // clinical_intake.sourceAnswers is intentionally excluded from this panel.
        $cqWithAnswers = $case->relationLoaded('caseQuestions')
            ? $case->caseQuestions->filter(fn($cq) => filled($cq->question) && filled($cq->answer))
            : collect();
        if ($cqWithAnswers->isNotEmpty()) {
            foreach ($cqWithAnswers as $cq) {
                $source[] = $srcRow($cq->question, $cq->answer, $cq->type);
            }
        } elseif ($case->relationLoaded('questionnaireResponses') && $case->questionnaireResponses->isNotEmpty()) {
            foreach ($case->questionnaireResponses as $resp) {
                foreach ($resp->answers as $ans) {
                    if (filled($ans->question_text)) { $source[] = $srcRow($ans->question_text, $ans->answer); }
                }
            }
        }

        // Prior visit data for refill cases (prior case is pre-loaded in the
        // controller to avoid N+1 — one query per refill case on the page).
        $isRefill = $case->isRefillRequest();
        $prior    = $priorCasesMap[$case->uuid] ?? null;
        $priorData = null;
        if ($isRefill && $prior) {
            $priorMeds = $prior->casePrescription?->medications?->map(fn($m) => [
                'name'    => $m->name,
                'sig'     => $m->sig ?? null,
                'dosing'  => is_array($m->dosing) ? collect($m->dosing)->filter()->implode(' → ') : null,
                'refills' => $m->refills,
            ])->values()->all() ?? [];

            $priorIntake = $prior->caseQuestions
                ->filter(fn($q) => filled($q->question))
                ->map(fn($q) => ['q' => $q->question, 'a' => $q->answer ?: '—'])
                ->values()->all();

            $priorNote = $prior->clinicalNotes->first()?->note ?? null;

            $priorData = [
                'clinician' => $prior->clinician?->user?->name ?? null,
                'date'      => $prior->completed_at?->format('M j, Y') ?? null,
                'meds'      => $priorMeds,
                'intake'    => array_slice($priorIntake, 0, 6),
                'note'      => $priorNote ? \Illuminate\Support\Str::limit($priorNote, 300) : null,
                'url'       => route('clinician.cases.show', $prior->uuid),
            ];
        }

        return [
            'id'       => $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, ''),
            'name'     => $case->patient?->full_name ?? 'Unknown',
            'company'  => $case->partner?->name ?? '-',
            'term'     => $clin['term'],
            'dose'     => $clin['dose'],
            'triage'   => $case->triage ?? 'unclassified',
            'tone'     => $tone,
            'label'    => $label,
            'summary'  => collect($ci['summary'] ?? $derivedSummary)->map(fn($l) => is_array($l) ? ($l[0] ?? '') : $l)->filter()->values(),
            'findings' => collect($ci['findings'] ?? [])->map(fn($f) => is_array($f) ? ['tone' => $f[0] ?? 'neutral', 'text' => $f[1] ?? ''] : ['tone' => 'neutral', 'text' => $f])->values(),
            'protocol' => $ci['protocolVersion'] ?? null,
            'source'   => $source,
            'hold'     => (bool) $case->hold_status,
            'state'    => $expandState($case->patient_state ?? $case->patient?->state ?? null),
            'collab'   => $case->patient?->collaboratingClinician?->full_name ?? null,
            'isRefill' => $isRefill,
            'prior'    => $priorData,
            'approveUrl' => route('clinician.cases.prescribe.form', $case->uuid),
            'reviewUrl'  => route('clinician.cases.prescribe.form', $case->uuid) . '?modal=1',
            'showUrl'    => route('clinician.cases.show', $case->uuid),
            'msgUrl'     => route('clinician.messages.index', ['case' => $case->uuid]),
        ];
    };

    $caseData = [];
    foreach ($cases as $case) { $caseData[$case->uuid] = $buildCard($case); }
    $topCard = $cases->first() ? $caseData[$cases->first()->uuid] : null;
@endphp

@if($topCard)
    <section class="panel quick-review" id="quickReview">
        @include('clinician.cases._quick-review', ['d' => $topCard])
    </section>
@endif

@if($cases->hasPages())
    <div style="margin-top:16px">{{ $cases->withQueryString()->links() }}</div>
@endif

@php
    $aiHonestyText = (config('ai.enabled') && config('ai.baa_confirmed'))
        ? 'AI model draft. Statements are composed from the recorded intake answers and model output. The draft never approves, prescribes, or sends anything.'
        : 'Deterministic placeholder, no model ran. Statements are composed only from the recorded intake answers. The draft never approves, prescribes, or sends anything.';
@endphp
<script>
    var CASE_DATA = @json($caseData ?? []);
    var AI_HONESTY_TEXT = @json($aiHonestyText);
</script>
<script>
    // Row click swaps the quick-review panel to the clicked case, rebuilding the
    // same markup the server partial produces so the two never diverge.
    (function () {
        var panel = document.getElementById('quickReview');
        var grid  = document.getElementById('reviewGrid');
        if (!panel || !grid) return;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }

        function render(d) {
            if (!d) return;
            var summary = (d.summary && d.summary.length)
                ? d.summary.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('')
                : '<li>No AI draft yet. It is composed from the storefront intake once that is sent for this case.</li>';

            var findings = (d.findings && d.findings.length)
                ? d.findings.map(function (f) { return '<li><span class="finding-dot ' + esc(f.tone) + '"></span> ' + esc(f.text) + '</li>'; }).join('')
                : '<li><span class="finding-dot neutral"></span> No findings recorded from intake yet.</li>';

            // Source answers render below the 3-column grid (not inside col1) so they
            // cannot distort grid row height and push the Provider Actions column off-screen.
            var col1NoSource = (!d.source || !d.source.length)
                ? '<p class="ai-honesty">No intake answers were passed for this case yet.</p>'
                : '';
            var sourceSection = (d.source && d.source.length)
                ? '<div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--line)">'
                  + '<button type="button" class="button-secondary" id="srcToggle" aria-expanded="false">View source answers (' + d.source.length + ')</button>'
                  + '<div class="qa-sheet" id="sourceAnswers" hidden>' + d.source.map(function (r) {
                    if (r.consent) {
                        return '<div class="qa consent"><dt><details><summary>' + esc(r.name)
                            + '</summary><div class="consent-full">' + esc(r.q) + '</div></details></dt>'
                            + '<dd><span class="pill ' + (r.agreed ? 'green' : 'red') + '">' + esc(r.agreed ? 'Agreed' : r.a) + '</span></dd></div>';
                    }
                    return '<div class="qa"><dt>' + esc(r.q) + '</dt><dd>' + esc(r.a) + '</dd></div>';
                  }).join('') + '</div></div>'
                : '';

            var holds = d.hold
                ? '<ul class="holds-list"><li><code class="audit-verb">WORKFLOW_HOLD_ACTIVE</code></li></ul>'
                : '<p class="no-holds">No active workflow holds.</p>';

            var triageLabel = d.triage ? d.triage.charAt(0).toUpperCase() + d.triage.slice(1) : '';
            var protocol = (d.protocol ? esc(d.protocol) + ' · ' : '') + 'classification ' + esc(triageLabel);

            // Prior visit section (refill cases only)
            var priorHtml = '';
            if (d.isRefill) {
                if (d.prior) {
                    var pm = d.prior;
                    var medItems = '';
                    if (pm.meds && pm.meds.length) {
                        pm.meds.forEach(function (m) {
                            medItems += '<div style="padding:5px 0;border-bottom:1px solid var(--line)">'
                                + '<span style="font-weight:680;font-size:13px">' + esc(m.name) + '</span>';
                            if (m.sig)    medItems += ' <span style="color:var(--muted);font-size:12px">· ' + esc(m.sig) + '</span>';
                            if (m.dosing) medItems += ' <span style="color:var(--muted);font-size:12px">· ' + esc(m.dosing) + '</span>';
                            if (m.refills != null) medItems += ' <span style="color:var(--soft-muted);font-size:11px">Refills: ' + esc(String(m.refills)) + '</span>';
                            medItems += '</div>';
                        });
                    } else {
                        medItems = '<p class="ai-honesty">No medications recorded.</p>';
                    }

                    var intakeItems = '';
                    if (pm.intake && pm.intake.length) {
                        pm.intake.forEach(function (r) {
                            intakeItems += '<div class="qa"><dt>' + esc(r.q) + '</dt><dd>' + esc(r.a) + '</dd></div>';
                        });
                    }

                    priorHtml = '<details style="margin-top:16px;border:1px solid var(--line);border-radius:14px;overflow:hidden">'
                        + '<summary style="padding:12px 16px;cursor:pointer;background:var(--blue-bg);display:flex;align-items:center;gap:10px;list-style:none;font-weight:700;font-size:13px">'
                        + '<span style="flex:1">Prior visit</span>'
                        + '<span class="pill" style="font-size:10px">Refill</span>'
                        + (pm.date ? '<span style="color:var(--muted);font-size:12px;font-weight:500">' + esc(pm.date) + '</span>' : '')
                        + (pm.clinician ? '<span style="color:var(--muted);font-size:12px;font-weight:500">Dr. ' + esc(pm.clinician) + '</span>' : '')
                        + '</summary>'
                        + '<div style="padding:14px 16px">'
                        + '<div class="subheading">Prescribed</div>'
                        + medItems
                        + (intakeItems ? '<div class="subheading" style="margin-top:12px">Prior intake answers</div><div class="qa-sheet" style="margin-top:0">' + intakeItems + '</div>' : '')
                        + (pm.note ? '<div class="subheading" style="margin-top:12px">Clinical note</div><p style="font-size:12px;color:var(--ink);white-space:pre-wrap;margin:4px 0">' + esc(pm.note) + '</p>' : '')
                        + '<a href="' + esc(pm.url) + '" style="display:inline-block;margin-top:10px;font-size:12px;color:var(--accent)">View full prior case →</a>'
                        + '</div></details>';
                } else {
                    priorHtml = '<div style="margin-top:12px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--blue-bg)">'
                        + '<span class="subheading" style="margin:0">Refill</span> '
                        + '<span class="ai-honesty" style="display:inline;font-size:12px">No prior completed case found for this patient with this partner.</span>'
                        + '</div>';
                }
            }

            panel.innerHTML =
                '<div class="panel-heading"><div>'
                + '<div class="eyebrow">Quick review · ' + esc(d.id) + '</div>'
                + '<h2>' + esc(d.name) + (d.isRefill ? ' <span class="pill" style="font-size:10px;vertical-align:middle">Refill</span>' : '') + '</h2>'
                + '<p>' + esc(d.company) + ' · Request ' + esc(d.term) + ' · ' + esc(d.dose) + (d.state ? ' · ' + esc(d.state) : '') + '</p></div>'
                + '<div class="quick-pills"><span class="pill ' + esc(d.triage) + '">' + esc(triageLabel) + '</span>'
                + '<span class="pill ' + esc(d.tone) + '">' + esc(d.label) + '</span></div></div>'
                + '<div class="quick-review-grid">'
                + '<div><div class="subheading">AI draft summary</div>'
                + '<div class="ai-draft-chip"><span class="pill neutral">AI draft · provider-assist only</span></div>'
                + '<ul class="summary-list">' + summary + '</ul>'
                + '<p class="ai-honesty">' + AI_HONESTY_TEXT + '</p>'
                + col1NoSource + '</div>'
                + '<div><div class="subheading">Triage and findings</div>'
                + '<p class="protocol-version">' + protocol + '</p>'
                + '<ul class="finding-list">' + findings + '</ul>'
                + (d.collab ? '<p class="ai-honesty" style="margin-top:8px"><strong>Collaborating:</strong> ' + esc(d.collab) + '</p>' : '')
                + '<div class="subheading holds-heading">Active workflow holds</div>' + holds + '</div>'
                + '<div><div class="subheading">Provider actions</div>'
                + '<a class="button-primary full-width" href="' + esc(d.approveUrl) + '" data-review-url="' + esc(d.reviewUrl) + '">Review and approve</a>'
                + '<a class="button-secondary full-width" href="' + esc(d.showUrl) + '">Show Full Profile</a>'
                + '<a class="button-secondary full-width" href="' + esc(d.msgUrl) + '">Send Message</a>'
                + '<a class="button-danger full-width" href="' + esc(d.showUrl) + '">Reject</a></div>'
                + '</div>'
                + sourceSection
                + priorHtml;
        }

        grid.querySelectorAll('tbody tr[data-row]').forEach(function (tr) {
            tr.style.cursor = 'pointer';
            tr.addEventListener('click', function (e) {
                if (e.target.closest('[data-stop]')) return;
                grid.querySelectorAll('tr.selected-row').forEach(function (r) { r.classList.remove('selected-row'); });
                tr.classList.add('selected-row');
                render(CASE_DATA[tr.getAttribute('data-row')]);
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        panel.addEventListener('click', function (e) {
            var btn = e.target.closest('#srcToggle');
            if (!btn) return;
            var box = document.getElementById('sourceAnswers');
            if (!box) return;
            var open = box.hasAttribute('hidden');
            if (open) { box.removeAttribute('hidden'); } else { box.setAttribute('hidden', ''); }
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = (open ? 'Hide source answers' : 'View source answers') + ' (' + box.children.length + ')';
        });
    })();
</script>
<script>
    // Batch selection: count and preflight react to the eligible checkboxes;
    // select-all toggles the eligible Green rows; compact toggles dense columns.
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

{{-- Review and approve as a modal over the grid (Devin msg 2292: make it a pop
     so providers don't change screens). The Review button opens the existing
     prescribe form in an iframe (rendered bare via ?modal=1), so all its working
     logic runs natively. On submit the form redirects to the case screen; the
     iframe navigating away is the signal to close and refresh the grid. --}}
<div class="modal-back" id="reviewOverlay" hidden>
    <div class="modal" style="width:min(1080px,94vw);height:88vh;padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--line)">
            <div style="display:flex;align-items:center;gap:10px;">
                <strong style="font-size:15px" id="reviewTitle">Review and approve</strong>
                <span id="batchCounter" style="display:none;font-size:12px;color:var(--muted);background:var(--blue-bg);padding:2px 10px;border-radius:99px;font-weight:600;"></span>
            </div>
            <button type="button" class="icon-btn" id="reviewClose" aria-label="Close">&times;</button>
        </div>
        <iframe id="reviewFrame" title="Review and approve" style="flex:1;width:100%;border:0"></iframe>
    </div>
</div>
<script>
    (function () {
        var overlay    = document.getElementById('reviewOverlay');
        var frame      = document.getElementById('reviewFrame');
        var counter    = document.getElementById('batchCounter');
        var preflight  = document.getElementById('preflight');
        if (!overlay || !frame) return;

        var reviewPath = '/clinician/cases/';

        // Batch queue state
        var batchQueue  = [];
        var batchIndex  = 0;
        var batchActive = false;

        function updateCounter() {
            if (!batchActive || batchQueue.length <= 1) { counter.style.display = 'none'; return; }
            counter.style.display = 'inline-block';
            counter.textContent   = 'Case ' + (batchIndex + 1) + ' of ' + batchQueue.length;
        }

        function openUrl(url) {
            frame.src = url;
            overlay.removeAttribute('hidden');
            document.body.style.overflow = 'hidden';
            updateCounter();
        }

        function closeModal(reload) {
            overlay.setAttribute('hidden', '');
            frame.src = 'about:blank';
            document.body.style.overflow = '';
            batchActive = false;
            batchQueue  = [];
            batchIndex  = 0;
            counter.style.display = 'none';
            if (reload) window.location.reload();
        }

        function onCaseSubmitted() {
            batchIndex++;
            if (batchIndex < batchQueue.length) {
                // Open next case in the queue
                openUrl(batchQueue[batchIndex]);
            } else {
                // All done — reload so statuses refresh
                closeModal(true);
            }
        }

        // Single Review button click (data-review-url attribute)
        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-review-url]');
            if (!link) return;
            e.preventDefault();
            batchActive = false;
            batchQueue  = [link.getAttribute('data-review-url')];
            batchIndex  = 0;
            openUrl(batchQueue[0]);
        });

        // Batch preflight button — open selected cases in sequence
        if (preflight) {
            preflight.addEventListener('click', function () {
                var grid = document.getElementById('reviewGrid');
                if (!grid) return;
                var urls = [];
                grid.querySelectorAll('.row-check:checked:not(:disabled)').forEach(function (cb) {
                    var uuid = cb.getAttribute('data-check');
                    if (uuid) urls.push(reviewPath + uuid + '/prescribe?modal=1');
                });
                if (!urls.length) return;
                batchQueue  = urls;
                batchIndex  = 0;
                batchActive = urls.length > 1;
                openUrl(batchQueue[0]);
            });
        }

        // Cancel message from inside the iframe
        window.addEventListener('message', function (e) {
            if (e.data === 'close-review') closeModal(false);
        });

        // Iframe navigated away from the prescribe form = case was submitted
        frame.addEventListener('load', function () {
            var href;
            try { href = frame.contentWindow.location.href; } catch (err) { return; }
            if (!href || href === 'about:blank') return;
            if (href.indexOf('modal=1') === -1 && href.indexOf(reviewPath) !== -1) {
                if (batchActive) {
                    onCaseSubmitted();
                } else {
                    closeModal(true);
                }
            }
        });

        document.getElementById('reviewClose').addEventListener('click', function () { closeModal(false); });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) closeModal(false); });
    })();
</script>
