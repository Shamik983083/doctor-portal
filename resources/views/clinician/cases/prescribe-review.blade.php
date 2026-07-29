@extends(request()->boolean('modal') ? 'layouts.bare' : 'layouts.clinician-exact')

@section('title', 'Review Prescription')
@section('page-title', 'Review Prescription')

@section('page-styles')
.rx-review-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:start; }
@media (max-width:860px) { .rx-review-grid { grid-template-columns:1fr; } }
@endsection

@section('view')
<div class="page-head">
    <div class="eyebrow">Clinician · Review</div>
    <h1>Review prescription for {{ $case->patient?->full_name ?? 'patient' }}</h1>
    <p>Case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }} · {{ $case->partner?->name ?? '-' }}</p>
</div>

<div class="rx-review-grid">

    {{-- Left: prescription summary --}}
    <section class="panel">
        <div style="padding:20px">
            <div class="subheading" style="margin-bottom:12px">Diagnoses</div>
            @if($prescription->diagnosesCodes->isNotEmpty())
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px">
                    @foreach($prescription->diagnosesCodes->sortBy('sort_order') as $d)
                        <span style="display:inline-flex;align-items:center;gap:4px;background:var(--blue-soft,#e8f0fe);color:var(--blue,#1a56db);border-radius:6px;padding:3px 10px;font-size:12px;font-weight:500">
                            {{ $d->icd_code }} <span style="font-weight:400;color:var(--muted)">{{ $d->description }}</span>
                        </span>
                    @endforeach
                </div>
            @elseif($prescription->diagnoses)
                <p style="font-size:14px;margin-bottom:16px">{{ $prescription->diagnoses }}</p>
            @endif

            <div class="subheading" style="margin-bottom:8px">Medications</div>
            @foreach($prescription->medications as $med)
                <div style="border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:10px">
                    <div style="font-weight:600;font-size:14px;margin-bottom:4px">{{ $med->name }}</div>
                    @if($med->compound_formula)
                        <div style="font-size:12px;color:var(--muted);margin-bottom:4px">{{ $med->compound_formula }}</div>
                    @endif
                    @if($med->sig)
                        <div style="font-size:13px;margin-bottom:4px"><strong>SIG:</strong> {{ $med->sig }}</div>
                    @endif
                    <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--muted)">
                        @if($med->refills !== null)<span>Refills: {{ $med->refills }}</span>@endif
                        @if($med->quantity !== null)<span>Qty: {{ $med->quantity }}</span>@endif
                        @if($med->days_supply !== null)<span>Days supply: {{ $med->days_supply }}</span>@endif
                        @if($med->dispense_unit)<span>Unit: {{ $med->dispense_unit }}</span>@endif
                    </div>
                    @if(!empty($med->dosing['months']))
                        <div style="font-size:12px;color:var(--muted);margin-top:4px">
                            Dosing: {{ implode(' → ', $med->dosing['months']) }}
                        </div>
                    @endif
                </div>
            @endforeach

            @if($prescription->medical_necessity)
                <div class="subheading" style="margin-top:12px;margin-bottom:4px">Medical necessity</div>
                <p style="font-size:13px">{{ $prescription->medical_necessity }}</p>
            @endif
        </div>
    </section>

    {{-- Right: charting note + confirm --}}
    <section class="panel">
        <div style="padding:20px">
            <form method="POST" action="{{ route('clinician.cases.prescribe.confirm', $case->uuid) }}" id="confirmForm">
                @csrf

                <div class="note-block">
                    <div class="note-head">
                        <div>
                            <div class="subheading">Charting note</div>
                            <span class="pill neutral">AI draft · provider edits and signs</span>
                        </div>
                        <button type="button" class="button-secondary" id="genNote">Draft with AI</button>
                    </div>
                    <textarea name="charting_note" class="note-area" id="noteArea" rows="8"
                        placeholder="Review the prescription summary, then draft a charting note with AI or write your own. Your note is saved as an internal record."></textarea>
                    <p class="ai-honesty" id="noteNotice" hidden></p>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid var(--line)">
                    <div style="display:flex;gap:8px">
                        @if(request()->boolean('modal'))
                            <button type="button" class="button-secondary" onclick="parent.postMessage('close-review','*')">Cancel</button>
                        @endif
                        <a href="{{ route('clinician.cases.prescribe.discard', $case->uuid) }}{{ request()->boolean('modal') ? '?modal=1' : '' }}"
                           onclick="return confirm('Discard this draft and return to the prescribe form?')"
                           class="button-secondary">Discard draft</a>
                    </div>
                    <button type="submit" class="button-primary">Confirm &amp; approve</button>
                </div>
            </form>
        </div>
    </section>

</div>
@endsection

@section('scripts')
<script>
    (function () {
        var noteArea = document.getElementById('noteArea');
        var notice   = document.getElementById('noteNotice');
        var genNote  = document.getElementById('genNote');

        if (!genNote || !noteArea) return;

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
                body: JSON.stringify({ provider_text: noteArea.value, decisions: [] })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.text) { noteArea.value = d.text; }
                if (notice && d && d.notice) { notice.textContent = d.notice; notice.removeAttribute('hidden'); }
                genNote.textContent = 'Regenerate';
                genNote.disabled = false;
            })
            .catch(function () {
                if (notice) { notice.textContent = 'Could not generate draft. Write the note manually.'; notice.removeAttribute('hidden'); }
                genNote.textContent = original;
                genNote.disabled = false;
            });
        });
    })();
</script>
@endsection
