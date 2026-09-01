@extends('layouts.clinician-exact')

@section('title', 'Review rejection message')
@section('page-title', 'Review rejection message')

@section('view')
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div>
        <div class="eyebrow">Clinician · Decline case</div>
        <h1>Review rejection message</h1>
        <p>{{ $case->partner?->name ?? '-' }} · {{ $case->patient?->full_name ?? 'Patient' }} · case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }}</p>
    </div>
    <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="button-secondary">
        ← Cancel &amp; go back
    </a>
</div>

<div class="reject-two-col" style="display:grid;grid-template-columns:1fr 1.5fr;gap:20px;margin-top:8px;align-items:start">

    {{-- Left: context panel --}}
    <div class="panel" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
            <div class="subheading" style="margin:0 0 6px">Case context</div>
            <div style="font-size:14px;font-weight:700">{{ $case->patient?->full_name ?? 'Unknown patient' }}</div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">{{ $case->partner?->name ?? '-' }}</div>
        </div>

        @php
            $offering = $case->caseOfferings->first()?->offering;
        @endphp
        @if($offering)
        <div style="padding:12px 20px;border-bottom:1px solid var(--line)">
            <div class="subheading" style="margin:0 0 4px">Requested</div>
            <div style="font-size:13px">{{ $offering->name }}</div>
            @if($offering->compound_formula)
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">{{ \Illuminate\Support\Str::limit($offering->compound_formula, 80) }}</div>
            @endif
        </div>
        @endif

        <div style="padding:12px 20px">
            <div class="subheading" style="margin:0 0 6px">Clinical reason for declining</div>
            <p style="font-size:12.5px;color:var(--ink);white-space:pre-wrap;margin:0;line-height:1.5">{{ $reason }}</p>
        </div>

        <div style="padding:12px 20px;border-top:1px solid var(--line);background:var(--red-bg,#fff6f6);border-radius:0 0 18px 18px">
            <span class="pill red" style="font-size:10px;margin-bottom:6px;display:inline-block">Declining case</span>
            <p style="font-size:12px;color:var(--muted);margin:0;line-height:1.5">Once you send this message the case will be declined. This cannot be undone.</p>
        </div>
    </div>

    {{-- Right: draft message editor --}}
    <div class="panel" style="padding:0">
        <form method="POST" action="{{ route('clinician.cases.reject-confirm', $case->uuid) }}">
            @csrf
            <input type="hidden" name="reason" value="{{ $reason }}">

            <div style="padding:16px 20px;border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div>
                        <div class="subheading" style="margin:0 0 2px">Patient-facing rejection message</div>
                        <p style="font-size:12px;color:var(--muted);margin:0">This message will be sent to the patient. Review and edit it before confirming.</p>
                    </div>
                    <span class="pill neutral" style="flex-shrink:0">AI draft · you edit and send</span>
                </div>

                @if($draftNotice)
                    <p class="ai-honesty" style="margin:10px 0 0">{{ $draftNotice }}</p>
                @endif
            </div>

            <div style="padding:16px 20px">
                @error('message_body')
                    <div style="color:var(--red,#c0392f);font-size:12.5px;margin-bottom:8px">{{ $message }}</div>
                @enderror
                <textarea
                    name="message_body"
                    class="note-area"
                    rows="10"
                    required
                    placeholder="Edit the patient-facing message here…"
                    style="width:100%">{{ old('message_body', $draftText) }}</textarea>
                <p class="ai-honesty" style="margin-top:8px">
                    This message will appear in the patient's portal and may be sent via email depending on notification settings.
                    The clinical reason you entered is recorded internally only — it is never shown to the patient.
                </p>
            </div>

            <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;padding:12px 20px;border-top:1px solid var(--line)">
                <a href="{{ route('clinician.cases.show', $case->uuid) }}" class="button-secondary">Cancel</a>
                <button type="submit" class="button-danger">Send &amp; decline case</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('page-styles')
<style>
@media (max-width:860px) { .reject-two-col { grid-template-columns:1fr !important; } }
</style>
@endsection
