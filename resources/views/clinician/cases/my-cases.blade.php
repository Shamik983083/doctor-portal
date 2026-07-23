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
        <a href="{{ route('clinician.cases.my-cases', array_merge(request()->except('tab','page'), ['tab' => $key])) }}"
           class="mc-tab {{ $tab === $key ? 'active' : '' }}">
            {{ $label }} <span class="mc-tab-count">{{ number_format($counts[$key]) }}</span>
        </a>
    @endforeach
</div>

@php
$tabTitle = match($tab) {
    'active'      => 'Active cases',
    'escalations' => 'My Escalations',
    'support'     => 'Support thread open',
    'completed'   => 'Completed cases',
    'cancelled'   => 'Cancelled cases',
    default       => 'All cases',
};
$tabSub = match($tab) {
    'escalations' => number_format($counts['escalations']) . ' ' . ($counts['escalations'] === 1 ? 'case' : 'cases') . ' escalated to support.',
    'support'     => number_format($counts['support']) . ' ' . ($counts['support'] === 1 ? 'case' : 'cases') . ' with unread messages in the support thread.',
    default       => number_format($counts[$tab]) . ' ' . ($counts[$tab] === 1 ? 'case' : 'cases') . ' assigned to you.',
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
</style>
@endsection
