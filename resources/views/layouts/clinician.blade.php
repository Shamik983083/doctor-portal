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

    /* ────────────────────────────────────────────────────────────────
       Content styling ported from the design preview
       (docs/design-preview/index.html), values copied 1:1 so the clinician
       screens read identically (Devin msg 2258/2259: "look identical",
       "the preview should have everything you need"). The app's existing
       markup classes are remapped to the preview's exact look rather than
       rewriting the DOM. Scoped to clinician pages via this section.
       ──────────────────────────────────────────────────────────────── */
    :root {
        --pv-ink:#172033; --pv-muted:#647188; --pv-soft:#8792a3;
        --pv-line:#e5e9f0; --pv-line-strong:#d8deea;
        --pv-green:#17834e; --pv-green-bg:#eaf8ef;
        --pv-yellow:#9a6500; --pv-yellow-bg:#fff7df;
        --pv-red:#b42318; --pv-red-bg:#fff0ef;
        --pv-accent:#2563eb; --pv-shadow:0 14px 45px rgba(26,40,73,.08);
    }

    /* Page header (eyebrow / title / sub) */
    .ma-eyebrow { color:var(--pv-muted); font-size:11px; font-weight:780; letter-spacing:.1em; text-transform:uppercase; }
    .ma-title   { font-size:18px; letter-spacing:-.028em; font-weight:700; margin:4px 0; }
    .ma-sub     { color:var(--pv-muted); font-size:13px; }

    /* Panels (the app's .card) */
    .page-content .card {
        background:#fff; border:1px solid var(--pv-line); border-radius:18px;
        box-shadow:var(--pv-shadow); overflow:hidden;
    }
    .page-content .card-header { background:#fff; border-bottom:1px solid var(--pv-line); padding:16px 20px; }
    .page-content .card-footer { background:#fcfdff; border-top:1px solid var(--pv-line); }

    /* Metric cards */
    .ma-metric-grid { display:grid; gap:16px; grid-template-columns:repeat(4,1fr); margin-bottom:20px; }
    @media (max-width:1100px){ .ma-metric-grid { grid-template-columns:repeat(2,1fr); } }
    .ma-metric {
        background:rgba(255,255,255,.86); border:1px solid var(--pv-line);
        border-radius:17px; padding:17px; box-shadow:0 1px 2px rgba(26,40,73,.03);
    }
    .ma-metric-label { color:var(--pv-muted); font-size:11px; font-weight:780; letter-spacing:.06em; text-transform:uppercase; }
    .ma-metric-value { font-size:29px; line-height:1; font-weight:790; letter-spacing:-.045em; margin:9px 0 4px; }

    /* Table density and type, matching the preview's table.tbl */
    .page-content table.table { border-collapse:separate; border-spacing:0; background:#fff; }
    .page-content table.table thead th {
        font-weight:780; font-size:10px; color:var(--pv-muted); letter-spacing:.075em; text-transform:uppercase;
        text-align:left; padding:11px 14px; background:#fbfcfe; border-bottom:1px solid var(--pv-line); white-space:nowrap;
    }
    .page-content table.table tbody td {
        padding:12px 14px; border-bottom:1px solid var(--pv-line); border-top:0;
        font-size:13px; color:#263248; white-space:nowrap; vertical-align:middle;
    }
    .page-content table.table tbody tr:hover td { background:#fafcff; }

    /* Pills, matching the preview */
    .ma-pill {
        display:inline-flex; align-items:center; white-space:nowrap; padding:4px 9px;
        border-radius:99px; font-size:11px; font-weight:750; background:#eef4ff; color:#245ed8;
    }
    .ma-pill.green   { background:var(--pv-green-bg);  color:var(--pv-green); }
    .ma-pill.yellow  { background:var(--pv-yellow-bg); color:var(--pv-yellow); }
    .ma-pill.red     { background:var(--pv-red-bg);    color:var(--pv-red); }
    .ma-pill.neutral { background:#f0f3f8; color:#536175; }
    .ma-pill.accent  { background:#eaf1ff; color:#245ed8; }

    /* Batch-reason subtext under a pill */
    .batch-reason { color:var(--pv-soft); font-size:11px; margin-top:3px; white-space:normal; max-width:200px; }

    /* Primary button, matching the preview */
    .page-content .btn-primary {
        background:var(--pv-accent); border-color:var(--pv-accent); border-radius:10px;
        font-weight:740; box-shadow:0 7px 16px rgba(37,99,235,.16);
    }
</style>

@php($nav = $clinicianNav ?? ['queue'=>0,'myCases'=>0,'messages'=>0,'escalations'=>0,'support'=>0,'red'=>0,'yellow'=>0,'green'=>0])

<div class="mt-2 pb-4">

    <a class="nav-link {{ request()->routeIs('clinician.dashboard') ? 'active' : '' }}" href="{{ route('clinician.dashboard') }}">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    {{-- Tasks --}}
    <div class="cn-group-label">Tasks</div>

    {{-- Case Queue hidden: clinicians only see cases assigned to them (My Cases) --}}
    {{-- <a class="nav-link {{ request()->routeIs('clinician.queue') || (request()->routeIs('clinician.cases.*') && !request()->routeIs('clinician.cases.my-cases')) ? 'active' : '' }}"
       href="{{ route('clinician.queue') }}">
        <i class="bi bi-inbox me-2"></i> Case Queue
        <span class="cn-count">{{ $nav['queue'] }}</span>
    </a> --}}

    <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') ? 'active' : '' }}"
       href="{{ route('clinician.cases.my-cases') }}">
        <i class="bi bi-folder2-open me-2"></i> My Cases
        <span class="cn-count">{{ $nav['myCases'] }}</span>
    </a>

    <a class="nav-link {{ request()->routeIs('clinician.cases.refills') ? 'active' : '' }}"
       href="{{ route('clinician.cases.refills') }}">
        <i class="bi bi-arrow-repeat me-2"></i> Refills
        <span class="cn-count">{{ $nav['refills'] ?? 0 }}</span>
    </a>

    {{-- Messages now has its own screen (Devin msg 2256). Escalations still
         links to the queue filtered to support until it gets a dedicated view. --}}
    <a class="nav-link {{ request()->routeIs('clinician.messages.*') ? 'active' : '' }}"
       href="{{ route('clinician.messages.index') }}">
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
