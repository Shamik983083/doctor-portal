@extends('layouts.clinician-exact')

@section('title', 'Case Review')
@section('page-title', 'Case Review')

{{--
    Review and approve, rebuilt to the design preview's modal look with its
    options (Devin msg 2277: "keep the functionality and what passes in there now
    but make it look and have all the options from the preview"). Per requested
    medication: Approve / Deny / None, and the prescribing fields. The FORM still
    posts to clinician.cases.prescribe with the exact fields that endpoint already
    expects (diagnoses + medications[]), so approving still creates the
    prescription, approves and completes the case, generates the PDF, dispatches
    and fires the webhook. Only the presentation changed.
--}}

@php
    $clin = $case->queueClinical();
    $ci   = $case->clinical_intake ?? [];
    $idv  = strtolower($case->patient?->id_verified_status ?? '') === 'verified';
    $meds = $case->caseOfferings->pluck('offering')->filter()->values();
    if ($meds->isEmpty()) { $meds = $offerings; }
    $findingFlags = collect($ci['findings'] ?? [])->filter(fn($f) => is_array($f) && ($f[0] ?? '') !== 'green')->count();
@endphp

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician</div>
    <h1>Case Review for {{ $case->patient?->full_name ?? 'patient' }}</h1>
    <p>{{ $case->patient?->age ?? '-' }} / {{ strtoupper(substr($case->patient?->gender ?? '-', 0, 1)) }} / {{ $case->partner?->name ?? '-' }} · case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }}</p>
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
                    <div><dt>Visit type</dt><dd>{{ strtolower($clin['video']) === 'required' ? 'Synchronous, video' : 'Asynchronous' }}</dd></div>
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

            {{-- Right: diagnosis, per-medication decisions, clinical note --}}
            <div class="modal-right">
                <div class="field">
                    <label>Diagnoses <span class="req">*</span></label>
                    <textarea name="diagnoses" class="note-area" rows="2" required placeholder="e.g. E66.01 obesity due to excess calories">{{ old('diagnoses') }}</textarea>
                </div>

                <div class="subheading" style="margin:16px 0 8px">Requested medications</div>

                @forelse($meds as $i => $offering)
                    <div class="med-decision" data-med="{{ $i }}" style="border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                            <strong>{{ $offering->name }}</strong>
                            <div class="decision-row">
                                <button type="button" class="decision-btn approve" data-dec="approve" data-med="{{ $i }}">Approve</button>
                                <button type="button" class="decision-btn deny" data-dec="deny" data-med="{{ $i }}">Deny</button>
                                <button type="button" class="decision-btn none" data-dec="none" data-med="{{ $i }}">None</button>
                            </div>
                        </div>

                        <input type="hidden" name="medications[{{ $i }}][offering_id]" value="{{ $offering->id }}" disabled data-input="{{ $i }}">
                        <input type="hidden" name="medications[{{ $i }}][name]" value="{{ $offering->name }}" disabled data-input="{{ $i }}">
                        <input type="hidden" name="medications[{{ $i }}][compound_formula]" value="{{ $offering->compound_formula }}" disabled data-input="{{ $i }}">

                        <div class="med-fields" data-fields="{{ $i }}" hidden>
                            <div class="field-row">
                                <div class="field"><label>Refills</label>
                                    <input type="number" min="0" name="medications[{{ $i }}][refills]" value="{{ $offering->refills }}" disabled data-input="{{ $i }}"></div>
                                <div class="field"><label>Quantity</label>
                                    <input type="number" min="0" step="0.01" name="medications[{{ $i }}][quantity]" value="{{ $offering->quantity }}" disabled data-input="{{ $i }}"></div>
                            </div>
                            <div class="field-row">
                                <div class="field"><label>Days supply</label>
                                    <input type="number" min="0" name="medications[{{ $i }}][days_supply]" value="{{ $offering->days_supply }}" disabled data-input="{{ $i }}"></div>
                                <div class="field"><label>Dispense unit</label>
                                    <input type="text" name="medications[{{ $i }}][dispense_unit]" value="{{ $offering->dispense_unit }}" disabled data-input="{{ $i }}"></div>
                            </div>
                        </div>
                        <p class="action-reason" data-reason="{{ $i }}" hidden></p>
                    </div>
                @empty
                    <p class="ai-honesty">No medications are attached to this case.</p>
                @endforelse

                <div class="note-block">
                    <div class="note-head">
                        <div><div class="subheading">Clinical note (directions)</div>
                        <span class="pill neutral">Provider writes and owns this</span></div>
                    </div>
                    <textarea name="directions" class="note-area" rows="3" placeholder="Administration instructions for the patient.">{{ old('directions') }}</textarea>
                    <div class="field" style="margin-top:10px"><label>Medical necessity</label>
                        <textarea name="medical_necessity" class="note-area" rows="2" placeholder="Justify medical necessity for the prescribed medications.">{{ old('medical_necessity') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-foot">
            <div class="foot-status"><strong id="decidedCount">0</strong> of {{ $meds->count() }} medication{{ $meds->count() === 1 ? '' : 's' }} decided
                <label class="check-line" style="margin-left:16px"><input type="checkbox" id="attest"> I attest this is clinically appropriate.</label>
            </div>
            <div class="foot-actions">
                <a class="button-secondary" href="{{ route('clinician.cases.show', $case->uuid) }}">Cancel</a>
                <button type="submit" class="button-primary" id="submitBtn" disabled>Submit decision</button>
            </div>
        </div>
    </section>
</form>
@endsection

@section('scripts')
<script>
    (function () {
        var form = document.getElementById('reviewForm');
        if (!form) return;
        var attest = document.getElementById('attest');
        var submit = document.getElementById('submitBtn');
        var count  = document.getElementById('decidedCount');
        var total  = {{ $meds->count() }};
        var state  = {}; // med index -> 'approve' | 'deny' | 'none'

        function inputs(i) { return form.querySelectorAll('[data-input="' + i + '"]'); }

        function apply(i, dec) {
            state[i] = dec;
            form.querySelectorAll('.decision-btn[data-med="' + i + '"]').forEach(function (b) {
                b.classList.toggle('on', b.getAttribute('data-dec') === dec);
            });
            var fields = form.querySelector('[data-fields="' + i + '"]');
            var reason = form.querySelector('[data-reason="' + i + '"]');
            var approved = dec === 'approve';

            // Only an approved medication submits its inputs. Disabled inputs are
            // not posted, so denied / none meds contribute nothing, exactly like
            // the preview leaves them off the order.
            inputs(i).forEach(function (el) { el.disabled = !approved; });
            if (fields) { if (approved) { fields.removeAttribute('hidden'); } else { fields.setAttribute('hidden', ''); } }
            if (reason) {
                if (dec === 'deny') { reason.textContent = 'Denied. No prescription is created for this medication.'; reason.removeAttribute('hidden'); }
                else if (dec === 'none') { reason.textContent = 'Marked none. Left undecided on the order.'; reason.removeAttribute('hidden'); }
                else { reason.setAttribute('hidden', ''); }
            }
            refresh();
        }

        function refresh() {
            var decided = Object.keys(state).length;
            var approvedCount = Object.keys(state).filter(function (k) { return state[k] === 'approve'; }).length;
            if (count) count.textContent = decided;
            var diagnoses = form.querySelector('[name="diagnoses"]');
            var ready = decided === total && total > 0 && approvedCount >= 1
                && attest && attest.checked && diagnoses && diagnoses.value.trim() !== '';
            if (submit) submit.disabled = !ready;
        }

        form.querySelectorAll('.decision-btn').forEach(function (b) {
            b.addEventListener('click', function () { apply(b.getAttribute('data-med'), b.getAttribute('data-dec')); });
        });
        if (attest) attest.addEventListener('change', refresh);
        var diag = form.querySelector('[name="diagnoses"]');
        if (diag) diag.addEventListener('input', refresh);

        refresh();
    })();
</script>
@endsection
