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
        'refills' => $o->refills, 'quantity' => $o->quantity,
        'days_supply' => $o->days_supply, 'dispense_unit' => $o->dispense_unit,
        'compound_formula' => $o->compound_formula,
        'levels' => $o->levels ?? [],
    ])->values();

    $requestedIds = $case->caseOfferings->pluck('offering.id')->filter()->values();
@endphp

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician</div>
    <h1>Case Review for {{ $case->patient?->full_name ?? 'patient' }}</h1>
    <p>{{ $case->patient?->age ?? '-' }} / {{ strtoupper(substr($case->patient?->gender ?? '-', 0, 1)) }} / {{ $case->patient_state ?? $case->patient?->state ?? '-' }} / {{ $case->partner?->name ?? '-' }} · case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }}</p>
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
                            ? \App\Models\StateVisitRequirement::where('state', strtoupper($patientState))->where('requires_sync', true)->exists()
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
                        <div><dt>Allergies</dt><dd>{{ $clin['allergy'] === 'Y' ? ($clin['allergyDetail'] ?? 'Yes') : 'None reported' }}</dd></div>
                    </div>
                </div>
            </div>

            {{-- Right: diagnosis, medications, note --}}
            <div class="modal-right">
                <div class="field">
                    <label>Diagnoses <span class="req">*</span></label>
                    <textarea name="diagnoses" class="note-area" rows="2" required placeholder="e.g. E66.01 obesity due to excess calories">{{ old('diagnoses') }}</textarea>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 8px">
                    <div class="subheading" style="margin:0">Medications</div>
                    <button type="button" class="button-secondary" id="addMed">+ Add medication</button>
                </div>

                @if($offerings->isEmpty())
                    <p class="ai-honesty">No approved offerings are available to prescribe for this case's category.</p>
                @endif

                <div id="medList"></div>

                <div class="note-block">
                    <div class="note-head">
                        <div><div class="subheading">Clinical note (directions)</div>
                        <span class="pill neutral">AI draft · provider edits and signs</span></div>
                        <button type="button" class="button-secondary" id="genNote">Draft with AI</button>
                    </div>
                    <textarea name="directions" class="note-area" id="noteArea" rows="3" placeholder="Write the basics or your instructions here, then draft with AI. What you write is used, not replaced.">{{ old('directions') }}</textarea>
                    <p class="ai-honesty" id="noteNotice" hidden></p>
                    <div class="field" style="margin-top:10px"><label>Medical necessity</label>
                        <textarea name="medical_necessity" class="note-area" rows="2" placeholder="Justify medical necessity for the prescribed medications.">{{ old('medical_necessity', $medicalNecessityPreset ?? '') }}</textarea>
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
    var REQUESTED = @json($requestedIds);
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
        var TERMS = [{ v: '1M', label: '1 month', n: 1 }, { v: '3M', label: '3 months', n: 3 }, { v: '4M', label: '4 months', n: 4 }];
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

        function offeringOptions(selId) {
            return '<option value="">Select medication</option>' + OFFERINGS.map(function (o) {
                return '<option value="' + o.id + '"' + (String(o.id) === String(selId) ? ' selected' : '') + '>' + esc(o.name) + '</option>';
            }).join('');
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
            var offering = OFFERINGS.filter(function (x) { return String(x.id) === String(medSel.value); })[0];
            var levels   = (offering && offering.levels && offering.levels.length) ? offering.levels : null;
            var fam      = levels ? null : family(offering ? offering.name : '');

            var head = '<div class="months-head"><label>Dosage by month <span class="req">*</span></label>'
                + '<span class="months-note">' + n + ' month term, one dose per month</span></div>';
            var cells = '';
            for (var m = 0; m < n; m++) {
                var control;
                if (levels) {
                    var opts = '<option value="">Select level</option>'
                        + levels.map(function (lvl) {
                            return '<option value="' + esc(lvl.label) + '">'
                                + esc(lvl.label) + ' · ' + esc(lvl.formula) + '</option>';
                        }).join('');
                    control = '<select name="medications[' + i + '][months][]">' + opts + '</select>';
                } else if (fam) {
                    control = '<select name="medications[' + i + '][months][]"><option value="">Dose</option>'
                        + optionList(CATALOG[fam], '') + '</select>';
                } else {
                    control = '<input type="text" name="medications[' + i + '][months][]" placeholder="Dose">';
                }
                cells += '<div class="field"><label>M' + (m + 1) + '</label>' + control + '</div>';
            }
            wrap.innerHTML = '<div class="months">' + head + '<div class="months-grid">' + cells + '</div></div>';
        }

        function addRow(offeringId) {
            var i = idx++;
            var row = document.createElement('div');
            row.className = 'med-decision';
            row.setAttribute('data-idx', i);
            row.style.cssText = 'border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px';
            row.innerHTML =
                '<div style="display:flex;justify-content:flex-end;margin-bottom:6px">'
                + '<button type="button" class="button-secondary" data-f="remove" style="padding:4px 10px">Remove</button></div>'
                + '<div class="field-row">'
                + '<div class="field"><label>Medication <span class="req">*</span></label>'
                + '<select data-f="med" name="medications[' + i + '][offering_id]">' + offeringOptions(offeringId) + '</select></div>'
                + '<div class="field"><label>Duration <span class="req">*</span></label>'
                + '<select data-f="term" name="medications[' + i + '][term]">' + optionList(TERMS, '3M') + '</select></div>'
                + '</div>'
                + '<div class="field-row">'
                + '<div class="field"><label>Administration frequency <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][frequency]">' + optionList(FREQUENCIES, 'Weekly') + '</select></div>'
                + '<div class="field"><label>Refills <span class="req">*</span></label>'
                + '<select name="medications[' + i + '][refills]">' + optionList(REFILLS, '0') + '</select></div>'
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
            row.querySelector('[data-f="remove"]').addEventListener('click', function () { row.remove(); refresh(); });

            syncMed();
        }

        function refresh() {
            var rows = list.querySelectorAll('.med-decision');
            var withMed = 0;
            rows.forEach(function (r) { if (r.querySelector('[data-f="med"]').value) withMed++; });
            if (medCount) medCount.textContent = withMed;
            var diagnoses = form.querySelector('[name="diagnoses"]');
            var ready = withMed >= 1 && diagnoses && diagnoses.value.trim() !== '';
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

        if (addBtn) addBtn.addEventListener('click', function () { addRow(''); });
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

        // Pre-load a row for each medication the case actually requested; if none,
        // start with one empty row so there is always something to fill.
        if (REQUESTED && REQUESTED.length) { REQUESTED.forEach(function (id) { addRow(id); }); }
        else { addRow(''); }

        refresh();
    })();
</script>
@endsection
