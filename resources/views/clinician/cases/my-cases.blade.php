@extends('layouts.clinician-exact')

@section('title', 'My Cases')
@section('page-title', 'My Cases')

{{--
    My Cases. The preview calls this "the same grid, filtered to yours" (Devin msg
    2283), so it renders the identical _review-grid partial as the Case Queue; the
    only difference is the data (cases assigned to me) and the status tabs above.
--}}

@section('view')
<div class="page-head">
    <div class="eyebrow">Tasks</div>
    <h1>My Cases</h1>
    <p>Cases assigned to you. Same grid as the queue, filtered to yours.</p>
</div>

{{-- Status tabs --}}
<div class="mc-tabs">
    @foreach(['active' => 'Active', 'escalations' => 'My Escalations', 'support' => 'Support thread open', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All cases'] as $key => $label)
        <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('tab','escalation_sub','page'), ['tab' => $key])) }}"
           class="mc-tab {{ $tab === $key ? 'active' : '' }}">
            {{ $label }} <span class="mc-tab-count">{{ number_format($counts[$key]) }}</span>
        </a>
    @endforeach
</div>

{{-- D15: sub-filter chips — only shown on the My Escalations tab --}}
@if($tab === 'escalations')
@php
    $escSubDefs = [
        \App\Models\PatientCase::ESCALATION_SUPPORT         => ['label' => 'Storefront Support', 'icon' => 'bi-headset'],
        \App\Models\PatientCase::ESCALATION_DOCTOR_ADMIN    => ['label' => 'Doctor Admin',        'icon' => 'bi-person-badge'],
        \App\Models\PatientCase::ESCALATION_CLIENT_RESPONSE => ['label' => 'Client Response',     'icon' => 'bi-clock'],
    ];
@endphp
<div class="esc-sub-tabs">
    <span class="esc-sub-label">Filter by type</span>
    <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('escalation_sub','page'), ['tab' => 'escalations'])) }}"
       class="esc-chip {{ is_null($escalationSub) ? 'active' : '' }}">
        All <span class="esc-chip-count">{{ number_format($counts['escalations']) }}</span>
    </a>
    @foreach($escSubDefs as $key => $def)
    <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('escalation_sub','page'), ['tab' => 'escalations', 'escalation_sub' => $key])) }}"
       class="esc-chip {{ $escalationSub === $key ? 'active' : '' }}">
        <i class="bi {{ $def['icon'] }}"></i>
        {{ $def['label'] }}
        <span class="esc-chip-count">{{ number_format($escalationCounts[$key]) }}</span>
    </a>
    @endforeach
</div>
@endif

@php
// D15: compute the active display count (filtered when a sub is chosen, total otherwise).
$activeEscalationCount = ($tab === 'escalations' && $escalationSub)
    ? ($escalationCounts[$escalationSub] ?? 0)
    : $counts['escalations'];

$escSubLabelMap = [
    \App\Models\PatientCase::ESCALATION_SUPPORT         => 'escalated to storefront support',
    \App\Models\PatientCase::ESCALATION_DOCTOR_ADMIN    => 'escalated to Doctor Admin',
    \App\Models\PatientCase::ESCALATION_CLIENT_RESPONSE => 'awaiting client response',
];
$escSubLabel = $escalationSub ? ($escSubLabelMap[$escalationSub] ?? 'escalated to support') : 'escalated to support';

$tabTitle = match($tab) {
    'active'      => 'Active cases',
    'escalations' => 'My Escalations',
    'support'     => 'Support thread open',
    'completed'   => 'Completed cases',
    'cancelled'   => 'Cancelled cases',
    default       => 'All cases',
};

$tabCount = (int) ($counts[(string)$tab] ?? 0);
$tabSub = match($tab) {
    'escalations' => number_format($activeEscalationCount) . ' ' . ($activeEscalationCount === 1 ? 'case' : 'cases') . ' ' . $escSubLabel . '.',
    'support'     => number_format($counts['support']) . ' ' . ($counts['support'] === 1 ? 'case' : 'cases') . ' with unread messages in the support thread.',
    default       => number_format($tabCount) . ' ' . ($tabCount === 1 ? 'case' : 'cases') . ' assigned to you.',
};
@endphp

@include('clinician.cases._review-grid', [
    'cases'   => $cases,
    'eyebrow' => 'My cases',
    'title'   => $tabTitle,
    'sub'     => $tabSub,
])
@endsection

@section('scripts')
<style>
    .mc-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
    .mc-tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line);
        background: #fff; color: var(--muted); font-weight: 680; font-size: 13px;
    }
    .mc-tab:hover { color: var(--ink); background: #f4f7fc; }
    .mc-tab.active { background: var(--blue-bg); color: var(--accent-ink); border-color: transparent; }
    .mc-tab-count {
        background: #eef2f7; color: var(--muted); border-radius: 999px;
        padding: 1px 8px; font-size: 11px; font-weight: 730; font-variant-numeric: tabular-nums;
    }
    .mc-tab.active .mc-tab-count { background: #c7d2fe; color: var(--accent-ink); }

    /* D15: escalation sub-filter chips */
    .esc-sub-tabs {
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        margin: -10px 0 18px; padding: 10px 14px;
        background: #f8f9fc; border: 1px solid var(--line);
        border-radius: 10px;
    }
    .esc-sub-label {
        font-size: 11px; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; color: var(--muted); margin-right: 4px;
    }
    .esc-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px; border-radius: 8px; border: 1px solid var(--line);
        background: #fff; color: var(--muted); font-size: 12px; font-weight: 600;
        text-decoration: none; transition: background .12s, color .12s, border-color .12s;
    }
    .esc-chip:hover { color: var(--ink); background: #eef2f7; }
    .esc-chip.active {
        background: #fef3c7; color: #92400e; border-color: #fbbf24;
    }
    .esc-chip-count {
        background: #e5e7eb; color: var(--muted); border-radius: 999px;
        padding: 1px 7px; font-size: 11px; font-weight: 730; font-variant-numeric: tabular-nums;
    }
    .esc-chip.active .esc-chip-count { background: #fde68a; color: #92400e; }
</style>
@endsection
