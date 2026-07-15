@props(['case'])
{{--
    Slice B — triage band pill. Reads $case->triage (green|yellow|red|null).
    Shows the reason codes as a native tooltip so a reviewer can see WHY a case
    landed in its band. Purely presentational — no clinical action.
--}}
@php
    $level  = $case->triage ?: 'neutral';
    $label  = $case->triageLabel();
    $reasons = collect($case->triage_reasons ?? []);
    $tip = $reasons->isNotEmpty()
        ? $case->triageMeaning() . "\n• " . $reasons->implode("\n• ")
        : $case->triageMeaning();
@endphp
<span class="ma-pill {{ $level }}" title="{{ $tip }}">
    <span class="ma-dot"></span>{{ $label }}
</span>
