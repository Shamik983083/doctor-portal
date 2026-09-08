{{-- Full screen normally; bare (no sidebar) when opened as a modal over the grid
     via ?modal=1 (Devin msg 2292). --}}
@extends(request()->boolean('modal') ? 'layouts.bare' : 'layouts.clinician-exact')

@section('title', 'Case Review')
@section('page-title', 'Case Review')

{{--
    Review and approve, the full model from MA-DOCPORTAL / the design preview
    (Devin msg 2279: dropdowns, and the ability to add multiple months or
    multiple medications). Each medication has a medication / duration /
    frequency / refills dropdown and a dose-per-month ladder driven by the
    duration; medications can be added and removed.

    Still posts to clinician.cases.prescribe, which now also stores the rich
    dosing alongside the flat medication columns. Approving creates the
    prescription, approves and completes the case, generates the PDF, dispatches
    and fires the webhook, exactly as before.
--}}

@php
    $clin = $case->queueClinical();
    $ci   = $case->clinical_intake ?? [];
    $idv  = strtolower($case->patient?->id_verified_status ?? '') === 'verified';
    $findingFlags = collect($ci['findings'] ?? [])->filter(fn($f) => is_array($f) && ($f[0] ?? '') !== 'green')->count();

    $offeringData = $offerings->map(fn($o) => [
        'id' => $o->id, 'name' => $o->name,
        'category_id' => $o->category_id,
        'formulation_type' => $o->formulation_type,
        'refills' => $o->refills, 'quantity' => $o->quantity,
        'days_supply' => $o->days_supply, 'dispense_unit' => $o->dispense_unit,
        'compound_formula' => $o->compound_formula,
        'levels' => $o->levels ?? [],
        // Effective SIG: use partner-level sig_override from the pivot when set,
        // otherwise fall back to the global offering sig.
        'sig' => (($o->pivot->sig_override ?? '') !== '') ? $o->pivot->sig_override : ($o->sig ?? ''),
    ])->values();

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
    $rawState   = $case->patient_state ?? $case->patient?->state ?? null;
    $stateLabel = $rawState ? ($usStates[strtoupper(trim($rawState))] ?? $rawState) : '-';
@endphp

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician</div>
    <h1>Case Review for {{ $case->patient?->full_name ?? 'patient' }}</h1>
    <p>{{ $case->patient?->age ?? '-' }} / {{ strtoupper(substr($case->patient?->gender ?? '-', 0, 1)) }} / {{ $stateLabel }} / {{ $case->partner?->name ?? '-' }} · case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }}</p>
</div>

@if($errors->any())
    <section class="panel" style="border-color:#f2c9c4;background:#fff5f4;margin-bottom:16px">
        <div style="padding:14px 18px">
            <strong style="color:var(--red)">Please fix the following</strong>
            <ul style="margin:8px 0 0;padding-left:18px;color:#8a5a55">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    </section>
@endif

<form method="POST" action="{{ route('clinician.cases.prescribe', $case->uuid) }}" id="reviewForm">
        @if(request()->boolean('modal'))<input type="hidden" name="modal" value="1">@endif
    @csrf

    <section class="panel">
        <div class="modal-body">
            {{-- Left: case facts --}}
            <div class="modal-left">
                <dl class="rx-meta">
                    <div><dt>Time in queue</dt><dd>{{ $case->created_at->diffForHumans(null, true) }}</dd></div>
                    @php
                        $patientState = $case->patient_state ?? $case->patient?->state;
                        $stateRequiresSync = $patientState
                            ? \App\Models\StateVisitRequirement::where('state', strtoupper($patientState))->where('requires_synchronous', true)->exists()
                            : false;
                        $defaultVt = strtolower($clin['video']) === 'required' ? 'synchronous' : 'asynchronous';
                        $rawVt = strtolower((string) ($case->visit_type ?? ''));
                        $currentVt = str_contains($rawVt, 'sync') ? 'synchronous' : ($rawVt !== '' ? 'asynchronous' : $defaultVt);
                    @endphp
                    <div>
                        <dt>Visit type</dt>
                        <dd>
                            <select name="visit_type" id="visitTypeSelect"
                                    data-state-requires-sync="{{ $stateRequiresSync ? '1' : '0' }}"
                                    data-original="{{ $currentVt }}"
                                    style="font-size:13px;padding:3px 6px;border:1px solid var(--line);border-radius:6px;background:var(--surface)">
                                <option value="asynchronous" {{ $currentVt === 'asynchronous' ? 'selected' : '' }}>Asynchronous</option>
                                <option value="synchronous"  {{ $currentVt === 'synchronous'  ? 'selected' : '' }}>Synchronous, video</option>
                            </select>
                        </dd>
                    </div>
                    <div><dt>Requested term</dt><dd>{{ $clin['term'] }}</dd></div>
                    <div><dt>ID verified</dt><dd>{{ $idv ? 'Yes' : 'No' }}</dd></div>
                </dl>

                {{-- Check-in answers for the current refill visit --}}
                @if($case->isRefillRequest() && $checkInResponses->isNotEmpty())
                    @foreach($checkInResponses as $checkInResp)
                        @php
                            $ciAnswers = $checkInResp->answers
                                ->filter(fn($a) => filled($a->question_text))
                                ->values();
                        @endphp
                        <details class="prior-visit-prescribe" open style="margin-bottom:14px;border:1px solid rgba(23,131,78,.25);border-radius:12px;overflow:hidden">
                            <summary style="padding:10px 14px;cursor:pointer;background:var(--green-bg);display:flex;align-items:center;gap:9px;list-style:none;font-size:12px;font-weight:700">
                                <span style="flex:1">Check-in answers</span>
                                <span class="pill green" style="font-size:10px">This visit</span>
                                @if($checkInResp->questionnaire?->name)
                                    <span style="color:var(--muted);font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $checkInResp->questionnaire->name }}">{{ $checkInResp->questionnaire->name }}</span>
                                @endif
                                @if($checkInResp->completed_at)
                                    <span style="color:var(--muted);font-weight:500">{{ $checkInResp->completed_at->format('M j, Y') }}</span>
                                @endif
                            </summary>
                            <div style="padding:12px 14px;font-size:12px">
                                @if($checkInResp->is_disqualified)
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;padding:7px 10px;background:var(--red-bg,#fff6f6);border:1px solid rgba(192,57,47,.15);border-radius:7px;font-size:11.5px;color:var(--red,#c0392f);font-weight:600">
                                        <span>⚠</span>
                                        <span>Patient was disqualified during this check-in{{ $checkInResp->disqualified_on ? ' — question: ' . $checkInResp->disqualified_on : '' }}.</span>
                                    </div>
                                @endif

                                @if($ciAnswers->isNotEmpty())
                                    <div class="qa-sheet" style="margin-top:0">
                                        @foreach($ciAnswers as $ans)
                                            <div class="qa" @if($ans->is_disqualified) style="background:var(--red-bg,#fff6f6);margin:0 -14px;padding:6px 14px" @endif>
                                                <dt>{{ $ans->question_text }}</dt>
                                                <dd>
                                                    {{ filled($ans->answer) ? $ans->answer : '—' }}
                                                    @if($ans->is_disqualified)
                                                        <span style="display:inline-block;margin-left:5px;font-size:10px;font-weight:700;color:var(--red,#c0392f);background:var(--red-bg,#fff6f6);padding:1px 5px;border-radius:4px">Disqualified</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="ai-honesty" style="margin:4px 0">No answers recorded for this check-in.</p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                @endif

                {{-- Prior visit panel (refill cases only) --}}
                @if($case->isRefillRequest())
                    @if($priorCase)
                        <details class="prior-visit-prescribe" open style="margin-bottom:14px;border:1px solid var(--line);border-radius:12px;overflow:hidden">
                            <summary style="padding:10px 14px;cursor:pointer;background:var(--blue-bg);display:flex;align-items:center;gap:9px;list-style:none;font-size:12px;font-weight:700">
                                <span style="flex:1">Prior visit</span>
                                <span class="pill" style="font-size:10px">Refill</span>
                                @if($priorCase->completed_at)<span style="color:var(--muted);font-weight:500">{{ $priorCase->completed_at->format('M j, Y') }}</span>@endif
                                @if($priorCase->clinician?->user?->name)<span style="color:var(--muted);font-weight:500">Dr. {{ $priorCase->clinician->user->name }}</span>@endif
                            </summary>
                            <div style="padding:12px 14px;font-size:12px">
                                <div class="subheading" style="font-size:10px">Prescribed</div>
                                @if($priorCase->casePrescription?->medications->isNotEmpty())
                                    @foreach($priorCase->casePrescription->medications as $pm)
                                        <div style="padding:4px 0;border-bottom:1px solid var(--line)">
                                            <strong>{{ $pm->name }}</strong>
                                            @if($pm->sig)<span style="color:var(--muted)"> · {{ $pm->sig }}</span>@endif
                                            @if(is_array($pm->dosing) && count(array_filter($pm->dosing)))<span style="color:var(--muted)"> · {{ collect($pm->dosing)->filter()->map(fn($d) => is_scalar($d) ? (string) $d : null)->filter()->values()->implode(' → ') }}</span>@endif
                                            @if($pm->refills !== null)<span style="color:var(--soft-muted);font-size:11px"> Refills: {{ $pm->refills }}</span>@endif
                                        </div>
                                    @endforeach
                                @else
                                    <p class="ai-honesty" style="margin:4px 0">No medications recorded.</p>
                                @endif

                                @if($priorCase->caseQuestions->filter(fn($q) => filled($q->question))->isNotEmpty())
                                    <div class="subheading" style="font-size:10px;margin-top:10px">Prior intake answers</div>
                                    <div class="qa-sheet" style="margin-top:0">
                                        @foreach($priorCase->caseQuestions->filter(fn($q) => filled($q->question))->take(8) as $cq)
                                            <div class="qa"><dt>{{ $cq->question }}</dt><dd>{{ $cq->answer ?: '—' }}</dd></div>
                                        @endforeach
                                    </div>
                                @endif

                                @php $priorNote = $priorCase->clinicalNotes->first(); @endphp
                                @if($priorNote?->note)
                                    <div class="subheading" style="font-size:10px;margin-top:10px">Clinical note</div>
                                    <p style="white-space:pre-wrap;margin:4px 0;color:var(--ink)">{{ \Illuminate\Support\Str::limit($priorNote->note, 400) }}</p>
                                @endif

                                <a href="{{ route('clinician.cases.show', $priorCase->uuid) }}" target="_blank"
                                   style="display:inline-block;margin-top:10px;color:var(--accent)">
                                    View full prior case →
                                </a>
                            </div>
                        </details>
                    @else
                        <div style="margin-bottom:14px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:var(--blue-bg);font-size:12px">
                            <span class="pill" style="font-size:10px;margin-right:6px">Refill</span>
                            <span style="color:var(--muted)">No prior completed case found for this patient with this partner.</span>
                        </div>
                    @endif
                @endif

                <div class="flags-panel {{ $case->triage === 'green' ? 'clean' : '' }}">
                    <p class="flags-title">{{ $ci['protocolVersion'] ?? 'Triage' }}: {{ $findingFlags }} flag(s), triage {{ ucfirst($case->triage ?? 'unclassified') }}</p>
                    <div class="flags-grid">
                        <div><dt>Age</dt><dd>{{ $case->patient?->age ?? '-' }}</dd></div>
                        <div><dt>Sex</dt><dd>{{ strtoupper(substr($case->patient?->gender ?? '-', 0, 1)) }}</dd></div>
                        <div><dt>BMI</dt><dd>{{ !is_null($case->patient?->bmi) ? number_format($case->patient->bmi, 1) : '-' }}</dd></div>
                        <div><dt>On GLP-1</dt><dd>{{ $clin['onGlp'] === 'Y' ? 'Yes' : ($clin['onGlp'] === 'N' ? 'No' : $clin['onGlp']) }}</dd></div>
                        <div><dt>Requested dose</dt><dd>{{ $clin['dose'] }}</dd></div>
                        <div><dt>Plan</dt><dd>{{ $clin['plan'] }}</dd></div>
                        <div><dt>Storefront</dt><dd>{{ $case->partner?->name ?? '-' }}</dd></div>
                        @if($case->subStorefront)<div><dt>Sub-Storefront</dt><dd>{{ $case->subStorefront->name }}</dd></div>@endif
                        <div><dt>Allergies</dt><dd>{{ $clin['allergy'] === 'Y' ? ($clin['allergyDetail'] ?? 'Yes') : 'None reported' }}</dd></div>
                    </div>
                </div>
            </div>

            {{-- Right: medications, note, diagnoses, medical necessity --}}
            <div class="modal-right">
                <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 8px">
                    <div class="subheading" style="margin:0">Medications</div>
                    <button type="button" class="button-secondary" id="addMed">+ Add medication</button>
                </div>

                @if($offerings->isEmpty())
                    <p class="ai-honesty">No approved offerings are available to prescribe for this case's category.</p>
                @endif

                <div id="medList"></div>

                <div class="note-block">
                    <div class="note-head">
                        <div><div class="subheading">Charting note <span class="req">*</span> <span style="font-size:11px;font-weight:400;color:var(--muted)">(private · not sent to patient or partner)</span></div>
                        <span class="pill neutral">AI draft · provider edits and signs</span></div>
                        <button type="button" class="button-secondary" id="genNote">Draft with AI</button>
                    </div>
                    <textarea name="directions" class="note-area" id="noteArea" rows="3" placeholder="Write your clinical rationale here. This charting note is private — it will not be visible to the patient or the partner." required>{{ old('directions') }}</textarea>
                    <p class="ai-honesty" id="noteNotice" hidden></p>

                    {{-- C9: ICD-10 structured code editor — sits above Medical Necessity --}}
                    <div class="field" style="margin-top:10px">
                        <label>ICD-10 Diagnoses <span class="req">*</span>
                            <span class="pill neutral" style="margin-left:6px">Auto-populated · edit as needed</span>
                        </label>
                        <div id="icdEditor" style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;padding:8px;border:1px solid var(--line);border-radius:8px;min-height:44px;background:var(--surface)"></div>
                        <div style="display:flex;gap:8px;margin-top:8px">
                            <input id="icdCodeInput" type="text" placeholder="Code e.g. E66.01" style="flex:0 0 120px;font-size:13px;padding:5px 8px;border:1px solid var(--line);border-radius:6px;background:var(--surface)">
                            <input id="icdDescInput" type="text" placeholder="Description" style="flex:1;font-size:13px;padding:5px 8px;border:1px solid var(--line);border-radius:6px;background:var(--surface)">
                            <button type="button" id="icdAddBtn" class="button-secondary">Add code</button>
                        </div>
                        <div id="icdHiddens"></div>
                        <p class="ai-honesty" style="margin-top:4px">Codes are auto-populated from the patient's intake. You can remove, edit, or add codes before confirming.</p>
                    </div>

                    <div class="field" style="margin-top:10px"><label>Medical necessity <span class="req">*</span></label>
                        <textarea name="medical_necessity" class="note-area" rows="2" placeholder="Justify medical necessity for the prescribed medications." required>{{ old('medical_necessity', $medicalNecessityPreset ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-foot">
            <div class="foot-status"><strong id="medCount">0</strong> medication(s) decided</div>
            <div class="foot-actions">
                @if(request()->boolean('modal'))
                    <button type="button" class="button-secondary" onclick="parent.postMessage('close-review','*')">Cancel</button>
                @else
                    <a class="button-secondary" href="{{ route('clinician.cases.show', $case->uuid) }}">Cancel</a>
                @endif
                <button type="submit" class="button-primary" id="submitBtn" disabled>Approve &amp; submit</button>
            </div>
        </div>
    </section>
</form>
@endsection

@section('scripts')
<script>
    var OFFERINGS = @json($offeringData);
    var CASE_OFFERINGS_DATA = @json($caseOfferingsData);
    var ICD10_SUGGESTIONS = @json($icd10Suggestions ?? []);
    var CHECK_IN_DOSE_HINT = @json($checkInDoseHint);
    var FORMULATION_HINT = @json($formulationHint ?? null);
</script>
<script>
    // C9: ICD-10 chip editor
    (function () {
        var editor   = document.getElementById('icdEditor');
        var hiddens  = document.getElementById('icdHiddens');
        var codeIn   = document.getElementById('icdCodeInput');
        var descIn   = document.getElementById('icdDescInput');
        var addBtn   = document.getElementById('icdAddBtn');
        if (!editor) return;

        var codes = [];

        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

        function render() {
            editor.innerHTML = '';
            hiddens.innerHTML = '';
            codes.forEach(function (c, i) {
                var chip = document.createElement('span');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;background:var(--blue-soft,#e8f0fe);color:var(--blue,#1a56db);border-radius:6px;padding:3px 8px;font-size:12px;font-weight:500';
                chip.innerHTML = esc(c.code) + ' <span style="font-weight:400;color:var(--muted)">' + esc(c.description) + '</span>'
                    + '<button type="button" style="background:none;border:none;cursor:pointer;color:var(--muted);padding:0 0 0 4px;font-size:14px;line-height:1" data-idx="' + i + '">×</button>';
                chip.querySelector('button').addEventListener('click', function () { codes.splice(+this.getAttribute('data-idx'), 1); render(); refreshSubmit(); });
                editor.appendChild(chip);

                var inCode = document.createElement('input');
                inCode.type = 'hidden';
                inCode.name = 'diagnoses[' + i + '][code]';
                inCode.value = c.code;
                hiddens.appendChild(inCode);

                var inDesc = document.createElement('input');
                inDesc.type = 'hidden';
                inDesc.name = 'diagnoses[' + i + '][description]';
                inDesc.value = c.description;
                hiddens.appendChild(inDesc);
            });
        }

        function refreshSubmit() {
            var submit = document.getElementById('submitBtn');
            if (!submit) return;
            var hasMed = document.querySelectorAll('#medList .med-decision').length >= 1;
            submit.disabled = !(codes.length >= 1 && hasMed);
        }

        function addCode(code, desc) {
            code = (code || '').trim().toUpperCase();
            desc = (desc || '').trim();
            if (!code) return;
            // deduplicate
            if (codes.some(function(c){ return c.code === code; })) return;
            codes.push({ code: code, description: desc || code });
            render();
            refreshSubmit();
        }

        addBtn.addEventListener('click', function () {
            addCode(codeIn.value, descIn.value);
            codeIn.value = '';
            descIn.value = '';
            codeIn.focus();
        });

        codeIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); } });
        descIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); } });

        // Seed with auto-populated suggestions
        if (Array.isArray(ICD10_SUGGESTIONS)) {
            ICD10_SUGGESTIONS.forEach(function (s) { addCode(s.code, s.description); });
        }
    })();
</script>
<script>
    (function () {
        var form   = document.getElementById('reviewForm');
        if (!form) return;
        var list   = document.getElementById('medList');
        var addBtn = document.getElementById('addMed');
        var submit = document.getElementById('submitBtn');
        var medCount = document.getElementById('medCount');

        // Dose ladders by drug family (from the MA / preview catalog). A medication
        // matched to a family gets a dropdown per month; anything else gets a free
        // text dose per month, so nothing is un-prescribable.
        var CATALOG = {
            semaglutide: ['L1 · 2.5 mg', 'L2 · 5 mg', 'L3 · 7.5 mg', 'L4 · 10 mg'],
            tirzepatide: ['L1 · 2.5 mg', 'L2 · 5 mg', 'L3 · 7.5 mg', 'L4 · 10 mg', 'L5 · 12.5 mg'],
            zofran:      ['4 mg', '8 mg'],
            nad:         ['100 mg', '200 mg']
        };
        var TERMS = [
            { v: '1M',  label: '1 month',   n: 1  },
            { v: '3M',  label: '3 months',  n: 3  },
            { v: '4M',  label: '4 months',  n: 4  },
            { v: '6M',  label: '6 months',  n: 6  },
            { v: '12M', label: '12 months', n: 12 },
        ];
        // Map month_frequency integer → term code for pre-filling the duration dropdown.
        // Falls back to '3M' when frequency is null (legacy cases) or unmapped.
        var FREQ_TO_TERM = { 1: '1M', 3: '3M', 4: '4M', 6: '6M', 12: '12M' };
        var defaultTerm  = FREQ_TO_TERM[{{ $requestedMonthFrequency ?? 'null' }}] || '3M';
        var FREQUENCIES = ['Weekly', 'Every 2 weeks', 'Daily', 'As needed'];
        var REFILLS = ['0', '1', '2', '3', '5', '11'];

        var idx = 0;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }
        function family(name) {
            var n = (name || '').toLowerCase();
            if (n.indexOf('semaglutide') > -1) return 'semaglutide';
            if (n.indexOf('tirzepatide') > -1) return 'tirzepatide';
            if (n.indexOf('zofran') > -1 || n.indexOf('ondansetron') > -1) return 'zofran';
            if (n.indexOf('nad') > -1) return 'nad';
            return null;
        }
        function monthsIn(term) { var t = TERMS.filter(function (x) { return x.v === term; })[0]; return t ? t.n : 1; }
        // Number of dosing-level dropdowns to render per term:
        // 1M → 1, 3M → 3, 4M → 4, 6M/12M → 3.
        function dosingCount(term) {
            if (term === '1M') return 1;
            if (term === '3M') return 3;
            if (term === '4M') return 4;
            return 3;
        }

        // Map product_key → drug family for bundle dropdown filtering.
        // Mirrors the server-side family() helper in JS so both sides agree.
        function pkFamily(pk) {
            if (!pk) return null;
            var k = pk.toLowerCase();
            if (k.indexOf('semaglutide') > -1) return 'semaglutide';
            if (k.indexOf('tirzepatide') > -1) return 'tirzepatide';
            if (k.indexOf('nad')         > -1) return 'nad';
            if (k.indexOf('zofran')      > -1 || k.indexOf('ondansetron') > -1) return 'zofran';
            return null;
        }

        // offeringOptions(selId, bundleProductKey)
        // bundleProductKey: when set, filters the list to that drug family only.
        // null / undefined → show all offerings (existing behaviour).
        function offeringOptions(selId, bundleProductKey) {
            var fam  = bundleProductKey ? pkFamily(bundleProductKey) : null;
            var list = fam
                ? OFFERINGS.filter(function (o) { return family(o.name) === fam; })
                : OFFERINGS;
            if (!list.length) {
                list = OFFERINGS; // safety fallback: unknown family → show all
            }
            return '<option value="">Select medication</option>' + list.map(function (o) {
                return '<option value="' + o.id + '"' + (String(o.id) === String(selId) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            }).join('');
        }

        // Remove all med-decision rows that share a bundle_group.
        function removeBundle(bgKey) {
            list.querySelectorAll('.med-decision[data-bundle-group="' + bgKey + '"]').forEach(function (r) {
                r.remove();
            });
            var wrapper = list.querySelector('.bundle-group-wrapper[data-bundle-group="' + bgKey + '"]');
            if (wrapper) wrapper.remove();
            refresh();
        }

        // Render a single offering as a sub-row inside a bundle wrapper.
        function addBundleMedRow(container, offeringId, bgKey, productKey, sharedTermEl) {
            var i = idx++;
            var row = document.createElement('div');
            row.className = 'med-decision bundle-med-row';
            row.setAttribute('data-idx', i);
            row.setAttribute('data-bundle-group', bgKey);
            row.setAttribute('data-product-key', productKey || '');
            row.style.cssText = 'border:1px solid #dde8f8;border-radius:8px;padding:12px;background:var(--surface);margin-top:8px';

            row.innerHTML =
                '<div class="field-row">'
                + '<div class="field"><label>Medication <span class="req">*</span></label>'
                + '<select data-f="med" name="medications[' + i + '][offering_id]" required>'
                + offeringOptions(offeringId, productKey) + '</select></div>'
                + '<div class="field"><label>Administration frequency <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][frequency]" required>' + optionList(FREQUENCIES, 'Weekly') + '</select></div>'
                + '<div class="field"><label>Refills <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][refills]" required>' + optionList(REFILLS, '0') + '</select></div>'
                + '</div>'
                + '<div data-f="months"></div>'
                + '<input type="hidden" data-f="term" name="medications[' + i + '][term]" value="' + esc(sharedTermEl ? sharedTermEl.value : defaultTerm) + '">'
                + '<input type="hidden" data-f="name" name="medications[' + i + '][name]" value="">'
                + '<input type="hidden" data-f="quantity" name="medications[' + i + '][quantity]" value="">'
                + '<input type="hidden" data-f="days_supply" name="medications[' + i + '][days_supply]" value="">'
                + '<input type="hidden" data-f="dispense_unit" name="medications[' + i + '][dispense_unit]" value="">'
                + '<input type="hidden" data-f="compound" name="medications[' + i + '][compound_formula]" value="">';

            container.appendChild(row);

            var med = row.querySelector('[data-f="med"]');
            function syncMed() {
                var o = OFFERINGS.filter(function (x) { return String(x.id) === String(med.value); })[0];
                row.querySelector('[data-f="name"]').value          = o ? o.name : '';
                row.querySelector('[data-f="quantity"]').value      = o && o.quantity       != null ? o.quantity       : '';
                row.querySelector('[data-f="days_supply"]').value   = o && o.days_supply    != null ? o.days_supply    : '';
                row.querySelector('[data-f="dispense_unit"]').value = o && o.dispense_unit  != null ? o.dispense_unit  : '';
                row.querySelector('[data-f="compound"]').value      = o && o.compound_formula != null ? o.compound_formula : '';
                renderMonths(row);
                refresh();
            }
            med.addEventListener('change', syncMed);
            syncMed();
            return row;
        }

        // Render all offerings in a bundle inside a single shared wrapper.
        // One duration dropdown controls all sub-rows; one button removes everything.
        function addBundleGroup(bundleItems, bgKey) {
            var wrapper = document.createElement('div');
            wrapper.className = 'bundle-group-wrapper';
            wrapper.setAttribute('data-bundle-group', bgKey);
            wrapper.style.cssText = 'border:1px solid #c7d8f5;border-radius:12px;padding:14px;margin-bottom:12px;background:#f7faff';

            wrapper.innerHTML =
                '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">'
                + '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">'
                + '<span style="font-size:10px;font-weight:700;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:5px">Bundle</span>'
                + '<label style="font-size:12px;font-weight:600;margin:0;white-space:nowrap">Duration <span class="req">*</span></label>'
                + '<select class="bundle-shared-term" style="font-size:12px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--surface);color:var(--ink)">'
                + optionList(TERMS, defaultTerm) + '</select>'
                + '</div>'
                + '<button type="button" class="button-secondary bundle-remove-btn" style="padding:4px 10px">Remove bundle</button>'
                + '</div>'
                + '<div class="bundle-meds-container"></div>';

            list.appendChild(wrapper);

            var sharedTerm    = wrapper.querySelector('.bundle-shared-term');
            var medsContainer = wrapper.querySelector('.bundle-meds-container');

            wrapper.querySelector('.bundle-remove-btn').addEventListener('click', function () {
                wrapper.remove();
                refresh();
            });

            bundleItems.forEach(function (co) {
                addBundleMedRow(medsContainer, co.offering_id, bgKey, co.product_key, sharedTerm);
            });

            // When shared duration changes, push the new value into every sub-row
            // hidden term input and re-render that row's dosage ladder.
            sharedTerm.addEventListener('change', function () {
                wrapper.querySelectorAll('.bundle-med-row').forEach(function (row) {
                    var termInput = row.querySelector('[data-f="term"]');
                    if (termInput) {
                        termInput.value = sharedTerm.value;
                        renderMonths(row);
                    }
                });
            });
        }
        function optionList(arr, sel) {
            return arr.map(function (v) {
                var val = v.v !== undefined ? v.v : v;
                var label = v.label !== undefined ? v.label : v;
                return '<option value="' + esc(val) + '"' + (String(val) === String(sel) ? ' selected' : '') + '>' + esc(label) + '</option>';
            }).join('');
        }

        function renderMonths(row) {
            var i      = row.getAttribute('data-idx');
            var medSel = row.querySelector('[data-f="med"]');
            var term   = row.querySelector('[data-f="term"]').value;
            var n      = monthsIn(term);
            var wrap   = row.querySelector('[data-f="months"]');

            // Prefer offering-specific levels; fall back to family CATALOG; then free text.
            var offering   = OFFERINGS.filter(function (x) { return String(x.id) === String(medSel.value); })[0];
            var levels     = (offering && offering.levels && offering.levels.length) ? offering.levels : null;
            var fam        = levels ? null : family(offering ? offering.name : '');
            var defaultSig = (offering && offering.sig) ? offering.sig : '';

            var slots = dosingCount(term);
            var head = '<div class="months-head"><label>Dosage by month <span class="req">*</span></label>'
                + '<span class="months-note">' + n + ' month term · ' + slots + ' level' + (slots > 1 ? 's' : '') + '</span></div>';
            var cells = '';
            for (var m = 0; m < slots; m++) {
                var control;
                if (levels) {
                    var opts = '<option value="" data-sig="">Select level</option>'
                        + levels.map(function (lvl) {
                            return '<option value="' + esc(lvl.label) + '" data-sig="' + esc(lvl.sig || '') + '">'
                                + esc(lvl.label) + ' · ' + esc(lvl.formula) + '</option>';
                        }).join('');
                    control = '<select name="medications[' + i + '][months][]" required class="level-select">' + opts + '</select>';
                } else if (fam) {
                    control = '<select name="medications[' + i + '][months][]" required><option value="">Dose</option>'
                        + optionList(CATALOG[fam], '') + '</select>';
                } else {
                    control = '<input type="text" name="medications[' + i + '][months][]" placeholder="Dose" required>';
                }
                var sigInput = '<input type="text"'
                    + ' name="medications[' + i + '][sigs][]"'
                    + ' value="' + esc(defaultSig) + '"'
                    + ' placeholder="SIG / instructions"'
                    + ' required'
                    + ' style="margin-top:5px;width:100%;font-size:11px;padding:4px 7px;'
                    + 'border:1px solid var(--line);border-radius:6px;background:var(--surface);color:var(--ink);">';
                cells += '<div class="field"><label>M' + (m + 1) + '</label>' + control + sigInput + '</div>';
            }
            wrap.innerHTML = '<div class="months">' + head + '<div class="months-grid">' + cells + '</div></div>';

            // Wire level→SIG auto-fill after DOM is set
            if (levels) {
                wrap.querySelectorAll('.level-select').forEach(function (sel) {
                    sel.addEventListener('change', function () {
                        var opt = this.options[this.selectedIndex];
                        var sig = opt ? (opt.getAttribute('data-sig') || '') : '';
                        var sigInp = this.parentElement.querySelector('input[name$="[sigs][]"]');
                        if (sigInp) sigInp.value = sig;
                    });
                });
                // Auto-select levels from check-in dose hint
                autoFillMonths(wrap, levels);
            }
        }

        // Auto-select month dosage levels based on check-in answers.
        //
        // Reads two questionnaire hints passed from PHP:
        //   CHECK_IN_DOSE_HINT.last_dose    → e.g. "Tirzepatide 7.5 mg"
        //   CHECK_IN_DOSE_HINT.continuation → e.g. "Increase my dosage"
        //
        // Continuation → slot assignment (M_n = month n; startIdx = resolved starting level):
        //   Same dose          → all months = currentIdx           (stay flat)
        //   Increase dosage    → M1=currentIdx, M2=+1, M3=+2 …    (ascending, M1 stays at current)
        //   Decrease dosage    → startIdx=max(0,currentIdx−1);     (step back first, then ascending)
        //                        M1=startIdx, M2=+1, M3=+2 …
        //   Change medications → identical to Increase             (prior dose is the new baseline)
        //   Provider decides   → no fill (return early)
        //
        // All indices are capped at levels.length−1.
        function autoFillMonths(wrap, levels) {
            var hint = CHECK_IN_DOSE_HINT;
            if (!hint || !hint.last_dose || !hint.continuation || !levels || !levels.length) return;

            var continuation = hint.continuation.toLowerCase();
            if (continuation.indexOf('provider') !== -1) return;

            // Extract numeric mg dose from patient answer (e.g. "Tirzepatide 7.5 mg" → 7.5)
            var doseMatch = hint.last_dose.match(/(\d+\.?\d*)\s*mg/i);
            if (!doseMatch) return;
            var dose = parseFloat(doseMatch[1]);

            // PATH A — cross-offering mg search.
            // Search every offering (not just the currently selected one) for a level whose
            // label+formula contains the patient's dose. Return the level INDEX (0-based),
            // not the mg value itself, so the same ordinal position can be applied even
            // when the selected offering's mg values differ (e.g. injection → tablet switch).
            var currentIdx = -1;
            for (var oi = 0; oi < OFFERINGS.length && currentIdx === -1; oi++) {
                var oLevels = OFFERINGS[oi].levels;
                if (!oLevels || !oLevels.length) continue;
                for (var li = 0; li < oLevels.length; li++) {
                    var haystack = (oLevels[li].label || '') + ' ' + (oLevels[li].formula || '');
                    var nums = haystack.match(/(\d+\.?\d*)\s*mg/gi) || [];
                    for (var ni = 0; ni < nums.length; ni++) {
                        if (Math.abs(parseFloat(nums[ni]) - dose) < 0.001) { currentIdx = li; break; }
                    }
                    if (currentIdx !== -1) break;
                }
            }

            // PATH B — hardcoded GLP titration ladder fallback.
            // Used when OFFERINGS is filtered to a single formulation and the patient's
            // prior dose doesn't appear in any loaded offering's levels (e.g. partner
            // loads only tablets but patient was on injection).
            if (currentIdx === -1) {
                var KNOWN_DOSE_LEVELS = {
                    semaglutide: [0.25, 0.5, 1, 1.7, 2.5],           // injection weekly ladder
                    tirzepatide: [2.5, 5, 7.5, 10, 12.5, 15]         // injection weekly ladder
                };
                var lastDoseLower = (hint.last_dose || '').toLowerCase();
                var drugFam = null;
                if (lastDoseLower.indexOf('semaglutide') > -1) drugFam = 'semaglutide';
                else if (lastDoseLower.indexOf('tirzepatide') > -1) drugFam = 'tirzepatide';
                if (drugFam) {
                    var knownDoses = KNOWN_DOSE_LEVELS[drugFam];
                    for (var ki = 0; ki < knownDoses.length; ki++) {
                        if (Math.abs(knownDoses[ki] - dose) < 0.001) { currentIdx = ki; break; }
                    }
                }
            }

            if (currentIdx === -1) return; // dose not found anywhere — leave dropdowns alone

            // Cap to the selected offering's level count (may have fewer levels than the source)
            currentIdx = Math.min(currentIdx, levels.length - 1);

            // Resolve starting index for M1 from the continuation answer
            var startIdx;
            if (continuation.indexOf('same') !== -1) {
                startIdx = currentIdx;                          // flat — all months at current
            } else if (continuation.indexOf('increase') !== -1) {
                startIdx = currentIdx;                          // M1 stays at current, M2+ step up
            } else if (continuation.indexOf('decrease') !== -1) {
                startIdx = Math.max(0, currentIdx - 1);        // M1 one level down, then ascending
            } else if (continuation.indexOf('change') !== -1) {
                startIdx = currentIdx;                          // treat same as increase
            } else {
                return;
            }

            var isSame = continuation.indexOf('same') !== -1;
            var selects = wrap.querySelectorAll('.level-select');
            selects.forEach(function (sel, m) {
                var targetIdx = isSame ? startIdx : Math.min(startIdx + m, levels.length - 1);
                // Use selectedIndex (+1 because option[0] is the blank placeholder) rather than
                // sel.value = label, so special characters in level labels never cause a mismatch.
                sel.selectedIndex = targetIdx + 1;
                sel.dispatchEvent(new Event('change')); // triggers SIG auto-fill
            });
        }

        // Auto-select the medication dropdown from the patient's last dose formulation.
        //
        // Goal: set the dropdown to an offering that matches the patient's prior drug
        // family AND formulation type (injection → injection, oral → oral). Fires after
        // rows are pre-loaded from CASE_OFFERINGS_DATA; overrides the pre-selection when
        // a better formulation match exists in OFFERINGS.
        //
        // Guards: returns early when hint is missing, continuation is "provider", or no
        // mg value is extractable. "Change medications" does NOT exit early — the prior
        // drug family and formulation are still auto-selected as the starting point.
        //
        // PATH A — find source offering in OFFERINGS by mg value.
        // PATH B — fallback: infer family ("semaglutide"/"tirzepatide") and formulation
        //          ("tablet"/"oral" → oral; anything else → injection) from answer text.
        //          Used when OFFERINGS is filtered and the patient's prior formulation
        //          isn't loaded (e.g. partner shows only tablets, patient was on injection).
        //
        // Injection keywords (name check): injection, b12, subq, sq.
        // Oral: anything that doesn't match injection keywords.
        function autoSelectMedication() {
            var hint = CHECK_IN_DOSE_HINT;
            var hasHint = hint && hint.last_dose && hint.continuation;

            // PATH C — new case with formulation hint from the partner API.
            // Runs only when there is no check-in questionnaire hint (new cases).
            // Picks the first offering whose formulation_type matches the hint.
            if (!hasHint && FORMULATION_HINT) {
                var wantInjection = (FORMULATION_HINT === 'injectable');
                var fTarget = null;
                for (var fi = 0; fi < OFFERINGS.length; fi++) {
                    var ft = OFFERINGS[fi].formulation_type;
                    if (!ft) continue;
                    if ((ft === 'injectable') === wantInjection) { fTarget = OFFERINGS[fi]; break; }
                }
                if (fTarget) {
                    list.querySelectorAll('[data-f="med"]').forEach(function (sel) {
                        if (String(sel.value) !== String(fTarget.id)) {
                            sel.value = String(fTarget.id);
                            sel.dispatchEvent(new Event('change'));
                        }
                    });
                }
                return;
            }

            if (!hasHint) return;
            var continuation = hint.continuation.toLowerCase();
            if (continuation.indexOf('provider') !== -1) return; // clinician decides manually

            var doseMatch = hint.last_dose.match(/(\d+\.?\d*)\s*mg/i);
            if (!doseMatch) return;
            var dose = parseFloat(doseMatch[1]);

            // PATH A — search OFFERINGS for a level whose label+formula contains the dose
            var sourceOffering = null;
            for (var oi = 0; oi < OFFERINGS.length && !sourceOffering; oi++) {
                var oLevels = OFFERINGS[oi].levels;
                if (!oLevels || !oLevels.length) continue;
                for (var li = 0; li < oLevels.length; li++) {
                    var hay = (oLevels[li].label || '') + ' ' + (oLevels[li].formula || '');
                    var ns  = hay.match(/(\d+\.?\d*)\s*mg/gi) || [];
                    for (var ni = 0; ni < ns.length; ni++) {
                        if (Math.abs(parseFloat(ns[ni]) - dose) < 0.001) { sourceOffering = OFFERINGS[oi]; break; }
                    }
                    if (sourceOffering) break;
                }
            }

            var srcFam, srcIsInjection;
            if (sourceOffering) {
                // PATH A succeeded — derive family + formulation type from the source offering name
                srcFam = family(sourceOffering.name);
                var sn = (sourceOffering.name || '').toLowerCase();
                srcIsInjection = sn.indexOf('injection') > -1 || sn.indexOf('b12') > -1
                              || sn.indexOf('subq') > -1 || sn.indexOf(' sq ') > -1;
            } else {
                // PATH B — source not in OFFERINGS; infer from patient's answer text
                var lastLower = (hint.last_dose || '').toLowerCase();
                if (lastLower.indexOf('semaglutide') > -1)      srcFam = 'semaglutide';
                else if (lastLower.indexOf('tirzepatide') > -1) srcFam = 'tirzepatide';
                else return; // unknown drug family — no auto-select
                srcIsInjection = lastLower.indexOf('tablet') === -1 && lastLower.indexOf('oral') === -1;
            }

            // Find the first offering in OFFERINGS with the same drug family AND formulation type
            var targetOffering = null;
            for (var ti = 0; ti < OFFERINGS.length; ti++) {
                if (family(OFFERINGS[ti].name) !== srcFam) continue;
                var tn = (OFFERINGS[ti].name || '').toLowerCase();
                var tIsInjection = tn.indexOf('injection') > -1 || tn.indexOf('b12') > -1
                                || tn.indexOf('subq') > -1 || tn.indexOf(' sq ') > -1;
                if (tIsInjection === srcIsInjection) { targetOffering = OFFERINGS[ti]; break; }
            }
            if (!targetOffering) return; // matching formulation not in OFFERINGS — leave as-is

            // Apply to all med dropdowns (overrides pre-selection if ID differs).
            // For bundle rows whose dropdown is filtered to a different family, setting a
            // non-existent value is a silent no-op, so those rows are unaffected.
            list.querySelectorAll('[data-f="med"]').forEach(function (sel) {
                if (String(sel.value) !== String(targetOffering.id)) {
                    sel.value = String(targetOffering.id);
                    sel.dispatchEvent(new Event('change')); // → syncMed → renderMonths → autoFillMonths
                }
            });
        }

        // addRow(offeringId, bundleGroup, productKey)
        //   bundleGroup  — non-null string means this row is part of a bundle.
        //                  The dropdown is filtered to the drug family of productKey,
        //                  and Remove atomically removes all rows in the same group.
        //   productKey   — partner's product_key (e.g. "semaglutide") used to derive
        //                  the family filter for the bundle dropdown.
        //   Both null    — standalone row, existing behaviour (full dropdown).
        function addRow(offeringId, bundleGroup, productKey) {
            var i = idx++;
            var isBundle = !!bundleGroup;
            var row = document.createElement('div');
            row.className = 'med-decision';
            row.setAttribute('data-idx', i);
            if (isBundle) {
                row.setAttribute('data-bundle-group', bundleGroup);
                row.setAttribute('data-product-key', productKey || '');
            }
            row.style.cssText = 'border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px'
                + (isBundle ? ';border-color:#c7d8f5;background:#f7faff' : '');

            var bundgeBadge = isBundle
                ? '<span style="font-size:10px;font-weight:700;background:#dbeafe;color:#1d4ed8;padding:2px 7px;border-radius:5px;margin-right:8px">Bundle</span>'
                : '';
            var removeLabel = isBundle ? 'Remove bundle' : 'Remove';

            row.innerHTML =
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">'
                + bundgeBadge
                + '<button type="button" class="button-secondary" data-f="remove" style="padding:4px 10px;margin-left:auto">' + removeLabel + '</button></div>'
                + '<div class="field-row">'
                + '<div class="field"><label>Medication <span class="req">*</span></label>'
                + '<select data-f="med" name="medications[' + i + '][offering_id]" required>' + offeringOptions(offeringId, isBundle ? productKey : null) + '</select></div>'
                + '<div class="field"><label>Duration <span class="req">*</span></label>'
                + '<select data-f="term" name="medications[' + i + '][term]" required>' + optionList(TERMS, defaultTerm) + '</select></div>'
                + '</div>'
                + '<div class="field-row" style="margin-top:10px">'
                + '<div class="field"><label>Administration frequency <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][frequency]" required>' + optionList(FREQUENCIES, 'Weekly') + '</select></div>'
                + '<div class="field"><label>Refills <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][refills]" required>' + optionList(REFILLS, '0') + '</select></div>'
                + '</div>'
                + '<div data-f="months"></div>'
                + '<input type="hidden" data-f="name" name="medications[' + i + '][name]" value="">'
                + '<input type="hidden" data-f="quantity" name="medications[' + i + '][quantity]" value="">'
                + '<input type="hidden" data-f="days_supply" name="medications[' + i + '][days_supply]" value="">'
                + '<input type="hidden" data-f="dispense_unit" name="medications[' + i + '][dispense_unit]" value="">'
                + '<input type="hidden" data-f="compound" name="medications[' + i + '][compound_formula]" value="">';
            list.appendChild(row);

            var med = row.querySelector('[data-f="med"]');
            var termSel = row.querySelector('[data-f="term"]');

            function syncMed() {
                var o = OFFERINGS.filter(function (x) { return String(x.id) === String(med.value); })[0];
                row.querySelector('[data-f="name"]').value = o ? o.name : '';
                row.querySelector('[data-f="quantity"]').value = o && o.quantity != null ? o.quantity : '';
                row.querySelector('[data-f="days_supply"]').value = o && o.days_supply != null ? o.days_supply : '';
                row.querySelector('[data-f="dispense_unit"]').value = o && o.dispense_unit != null ? o.dispense_unit : '';
                row.querySelector('[data-f="compound"]').value = o && o.compound_formula != null ? o.compound_formula : '';
                renderMonths(row);
                refresh();
            }

            med.addEventListener('change', syncMed);
            termSel.addEventListener('change', function () { renderMonths(row); });
            row.querySelector('[data-f="remove"]').addEventListener('click', function () {
                if (isBundle) { removeBundle(bundleGroup); } else { row.remove(); refresh(); }
            });

            syncMed();
        }

        function refresh() {
            var rows = list.querySelectorAll('.med-decision');
            var withMed = 0;
            rows.forEach(function (r) { if (r.querySelector('[data-f="med"]').value) withMed++; });
            if (medCount) medCount.textContent = withMed;
            // C9: ready when at least one ICD-10 code exists AND at least one medication is selected
            var hasIcd = document.querySelectorAll('#icdHiddens input[name$="[code]"]').length >= 1;
            var ready = withMed >= 1 && hasIcd;
            if (submit) submit.disabled = !ready;
        }

        // The current medication rows as decisions, for the AI note draft.
        function currentDecisions() {
            var out = [];
            list.querySelectorAll('.med-decision').forEach(function (r) {
                var name = r.querySelector('[data-f="name"]').value;
                if (!name) return;
                var months = [];
                r.querySelectorAll('[name$="[months][]"]').forEach(function (m) { if (m.value) months.push(m.value); });
                out.push({
                    name: name, decision: 'approve',
                    term: (r.querySelector('[data-f="term"]') || {}).value || '',
                    frequency: (r.querySelector('select[name$="[frequency]"]') || {}).value || '',
                    refills: (r.querySelector('select[name$="[refills]"]') || {}).value || '',
                    months: months
                });
            });
            return out;
        }

        // AI note tie-in (Devin msg 2281). Posts what the provider has typed plus
        // the current decisions to draft-note; the returned text fills the note,
        // and the notice says honestly whether a model ran or it was composed
        // locally. What the provider wrote is used as the steer, not replaced.
        var genNote  = document.getElementById('genNote');
        var noteArea = document.getElementById('noteArea');
        var notice   = document.getElementById('noteNotice');
        if (genNote && noteArea) {
            genNote.addEventListener('click', function () {
                genNote.disabled = true;
                var original = genNote.textContent;
                genNote.textContent = 'Drafting...';
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch('{{ route('clinician.cases.draft-note', $case->uuid) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ provider_text: noteArea.value, decisions: currentDecisions() })
                })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.text) { noteArea.value = d.text; refresh(); }
                    if (notice && d && d.notice) { notice.textContent = d.notice; notice.removeAttribute('hidden'); }
                    genNote.textContent = 'Regenerate';
                    genNote.disabled = false;
                })
                .catch(function () {
                    if (notice) { notice.textContent = 'The draft could not be generated. Write the note manually.'; notice.removeAttribute('hidden'); }
                    genNote.textContent = original;
                    genNote.disabled = false;
                });
            });
        }

        if (addBtn) addBtn.addEventListener('click', function () { addRow('', null, null); });
        var diag = form.querySelector('[name="diagnoses"]');
        if (diag) diag.addEventListener('input', refresh);

        // C10: warn if provider downgrades a state-mandated sync visit to async
        var vtSel = document.getElementById('visitTypeSelect');
        if (vtSel) {
            vtSel.addEventListener('change', function () {
                var stateRequiresSync = this.getAttribute('data-state-requires-sync') === '1';
                var original = this.getAttribute('data-original');
                if (stateRequiresSync && this.value === 'asynchronous' && original === 'synchronous') {
                    if (!confirm('This patient’s state requires a synchronous video visit. Downgrading to asynchronous may not comply with state regulations.\n\nContinue anyway?')) {
                        this.value = 'synchronous';
                    }
                }
            });
        }

        // Pre-load medication rows from case_offerings.
        // Bundle rows: grouped by bundle_group, dropdown filtered to drug family,
        //              Remove atomically removes the whole group.
        // Standalone rows: full dropdown, individual Remove — existing behaviour.
        (function () {
            if (!CASE_OFFERINGS_DATA || !CASE_OFFERINGS_DATA.length) {
                addRow('', null, null);
                return;
            }
            // Separate bundles from standalone offerings.
            var bundleGroups = {};
            var standalone   = [];
            CASE_OFFERINGS_DATA.forEach(function (co) {
                if (co.bundle_group) {
                    if (!bundleGroups[co.bundle_group]) bundleGroups[co.bundle_group] = [];
                    bundleGroups[co.bundle_group].push(co);
                } else {
                    standalone.push(co);
                }
            });
            // Render bundle rows first — all offerings in a group share one wrapper,
            // one duration dropdown, and one Remove button.
            Object.keys(bundleGroups).forEach(function (bgKey) {
                addBundleGroup(bundleGroups[bgKey], bgKey);
            });
            // Render standalone rows (existing behaviour, full dropdown).
            standalone.forEach(function (co) {
                addRow(co.offering_id, null, null);
            });
        }());

        // Auto-select medication + levels from check-in questionnaire answers
        autoSelectMedication();

        refresh();

        // Spinner on Approve & submit
        form.addEventListener('submit', function () {
            var btn = document.getElementById('submitBtn');
            if (!btn || btn.classList.contains('btn-loading')) return;
            btn.classList.add('btn-loading');
            btn.innerHTML = '<span class="btn-spin"></span>Submitting…';
        });
    })();
</script>
@endsection
