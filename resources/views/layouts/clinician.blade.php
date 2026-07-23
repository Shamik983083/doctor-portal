@extends('layouts.app')

{{--
    Clinician portal shell, restyled to the design preview (Devin msg 2253:
    "I want the view you gave me"). Two things change from the shared dark shell,
    both SCOPED to the clinician pages so admin and partner are untouched:

      1. a scoped <style> block turns the sidebar light, matching
         docs/design-preview/index.html (served at /medaxis-preview/);
      2. the flat three-link nav becomes the preview's grouped nav, Tasks /
         Priority / Triage, each row carrying a live count from the
         `clinicianNav` view composer in AppServiceProvider.

    Counts are real. An item whose live filter does not exist yet links to the
    closest real view rather than a dead URL, and is a placeholder until that
    filtered view is built.
--}}

@section('sidebar-nav')
<style>
    /* Light clinician shell, matching the preview. Scoped to this section, so
       it only loads on clinician pages and never restyles admin or partner. */
    .sidebar { background: #ffffff; color: #475569; border-right: 1px solid #e5e9f0; }
    .sidebar-brand { color: #0f172a !important; border-bottom: 1px solid #eef1f6; }
    .sidebar .nav-link { color: #475569; border-radius: 8px; margin: 1px 8px; padding: 7px 10px; display: flex; align-items: center; }
    .sidebar .nav-link:hover { background: #f1f5f9; color: #0f172a; }
    .sidebar .nav-link.active { background: #e0e7ff; color: #1e293b; font-weight: 600; }
    .sidebar .cn-group-label {
        font-size: .68rem; letter-spacing: .06em; text-transform: uppercase;
        color: #94a3b8; font-weight: 700; padding: 14px 18px 4px;
    }
    .sidebar .cn-count {
        margin-left: auto; font-size: .72rem; font-weight: 600;
        background: #eef2f7; color: #64748b; border-radius: 999px; padding: 1px 8px; min-width: 22px; text-align: center;
    }
    .sidebar .nav-link.active .cn-count { background: #c7d2fe; color: #3730a3; }
    .sidebar .cn-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 9px; }
    .sidebar .cn-dot.red { background: #ef4444; }
    .sidebar .cn-dot.yellow { background: #f59e0b; }
    .sidebar .cn-dot.green { background: #22c55e; }
</style>

@php($nav = $clinicianNav ?? ['queue'=>0,'myCases'=>0,'messages'=>0,'escalations'=>0,'support'=>0,'red'=>0,'yellow'=>0,'green'=>0])

<div class="mt-2 pb-4">

    <a class="nav-link {{ request()->routeIs('clinician.dashboard') ? 'active' : '' }}" href="{{ route('clinician.dashboard') }}">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    {{-- Tasks --}}
    <div class="cn-group-label">Tasks</div>

    <a class="nav-link {{ request()->routeIs('clinician.queue') || (request()->routeIs('clinician.cases.*') && !request()->routeIs('clinician.cases.my-cases')) ? 'active' : '' }}"
       href="{{ route('clinician.queue') }}">
        <i class="bi bi-inbox me-2"></i> Case Queue
        <span class="cn-count">{{ $nav['queue'] }}</span>
    </a>

    <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') ? 'active' : '' }}"
       href="{{ route('clinician.cases.my-cases') }}">
        <i class="bi bi-folder2-open me-2"></i> My Cases
        <span class="cn-count">{{ $nav['myCases'] }}</span>
    </a>

    {{-- Messages and Escalations have no dedicated screen yet, so they link to
         the closest real view. Counts are live; destinations are honest
         placeholders until the filtered views are built. --}}
    <a class="nav-link" href="{{ route('clinician.cases.my-cases') }}">
        <i class="bi bi-chat-dots me-2"></i> Messages For Provider
        <span class="cn-count">{{ $nav['messages'] }}</span>
    </a>

    <a class="nav-link" href="{{ route('clinician.queue') }}?status=support">
        <i class="bi bi-exclamation-triangle me-2"></i> My Escalations
        <span class="cn-count">{{ $nav['escalations'] }}</span>
    </a>

    {{-- Priority --}}
    <div class="cn-group-label">Priority</div>

    <a class="nav-link" href="{{ route('clinician.queue') }}?status=support">
        <i class="bi bi-tools me-2"></i> Support thread open
        <span class="cn-count">{{ $nav['support'] }}</span>
    </a>

    {{-- Triage --}}
    <div class="cn-group-label">Triage</div>

    <a class="nav-link" href="{{ route('clinician.queue') }}?triage=red">
        <span class="cn-dot red"></span> Red
        <span class="cn-count">{{ $nav['red'] }}</span>
    </a>
    <a class="nav-link" href="{{ route('clinician.queue') }}?triage=yellow">
        <span class="cn-dot yellow"></span> Yellow
        <span class="cn-count">{{ $nav['yellow'] }}</span>
    </a>
    <a class="nav-link" href="{{ route('clinician.queue') }}?triage=green">
        <span class="cn-dot green"></span> Green
        <span class="cn-count">{{ $nav['green'] }}</span>
    </a>

    <div class="cn-group-label">&nbsp;</div>
    <a class="nav-link {{ request()->routeIs('clinician.notifications.*') ? 'active' : '' }}"
       href="{{ route('clinician.notifications.index') }}">
        <i class="bi bi-bell me-2"></i> Notifications
    </a>

</div>
@endsection
