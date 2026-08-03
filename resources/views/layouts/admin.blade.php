@extends('layouts.app')

@section('sidebar-nav')
@php
    $mgmtActive  = request()->routeIs(
        'admin.cases.*', 'admin.patients.*', 'admin.partners.*',
        'admin.clinicians.*', 'admin.offerings.*', 'admin.categories.*',
        'admin.questionnaires.*', 'admin.questions.*', 'admin.partner-dashboard.*',
        'admin.escalations.*', 'admin.messages.*'
    );
    $apiActive   = request()->routeIs('admin.guide.*', 'admin.webhooks.*');
    $cfgActive   = request()->routeIs('admin.settings*', 'admin.triage-rules.*', 'admin.routing.index', 'admin.routing.visit-requirements*', 'admin.ai.*');
    $superActive = request()->routeIs('admin.admins.*', 'admin.audit-log.*', 'admin.users.*');
@endphp

<div class="mt-1 pb-3">

    {{-- Dashboard (always visible) --}}
    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
       href="{{ route('admin.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    {{-- ── Management ──────────────────────────────────────── --}}
    <button class="sidebar-section-toggle {{ $mgmtActive ? '' : 'collapsed' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#snav-management"
            aria-expanded="{{ $mgmtActive ? 'true' : 'false' }}">
        <span>Management</span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $mgmtActive ? 'show' : '' }}" id="snav-management">

        <a class="nav-link {{ request()->routeIs('admin.cases.*') ? 'active' : '' }}"
           href="{{ route('admin.cases.index') }}">
            <i class="bi bi-folder2-open"></i> Cases
        </a>
        <a class="nav-link {{ request()->routeIs('admin.escalations.*') ? 'active' : '' }}"
           href="{{ route('admin.escalations.index') }}">
            <i class="bi bi-exclamation-triangle"></i> Escalations
            @php $openEscalationCount = \App\Models\PatientCase::visibleTo(auth()->user())->where('status', 'support')->count(); @endphp
            @if($openEscalationCount > 0)
                <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem;">{{ $openEscalationCount }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}"
           href="{{ route('admin.messages.index') }}">
            <i class="bi bi-chat-square-text"></i> Messages
            @php
                $adminMsgUnread = \App\Models\Message::whereIn('case_id',
                    \App\Models\PatientCase::visibleTo(auth()->user())->select('id')
                )->where('channel', 'portal')->where('direction', 'inbound')->where('is_read', false)->count();
            @endphp
            @if($adminMsgUnread > 0)
                <span class="badge bg-primary ms-auto" style="font-size:.6rem;">{{ $adminMsgUnread }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('admin.partner-dashboard.*') ? 'active' : '' }}"
           href="{{ route('admin.partner-dashboard.index') }}">
            <i class="bi bi-building-check"></i> Partner Dashboard
        </a>
        <a class="nav-link {{ request()->routeIs('admin.patients.*') ? 'active' : '' }}"
           href="{{ route('admin.patients.index') }}">
            <i class="bi bi-people"></i> Patients
        </a>
        {{-- Storefronts carry their own Healthie credentials, so this is an
             integration surface: super admin only (Devin msg 2117). Hidden rather
             than shown and 403'd, so a Doctor Admin is never offered a dead link. --}}
        @role('super_admin')
        <a class="nav-link {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}"
           href="{{ route('admin.partners.index') }}">
            <i class="bi bi-building"></i> Partners
        </a>
        @endrole
        <a class="nav-link {{ request()->routeIs('admin.clinicians.*') && !request()->routeIs('admin.clinicians.priority') && !request()->routeIs('admin.clinicians.bulk-reassign*') && !request()->routeIs('admin.clinicians.workload') ? 'active' : '' }}"
           href="{{ route('admin.clinicians.index') }}">
            <i class="bi bi-person-badge"></i> Clinicians
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.clinicians.workload') ? 'active' : '' }}"
           href="{{ route('admin.clinicians.workload') }}">
            <i class="bi bi-bar-chart-steps"></i> Provider Workload
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.clinicians.priority') ? 'active' : '' }}"
           href="{{ route('admin.clinicians.priority') }}">
            <i class="bi bi-sort-numeric-down"></i> Assignment Priority
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.clinicians.bulk-reassign*') ? 'active' : '' }}"
           href="{{ route('admin.clinicians.bulk-reassign') }}">
            <i class="bi bi-arrow-left-right"></i> Bulk Reassign
        </a>
        <a class="nav-link {{ request()->routeIs('admin.offerings.*') ? 'active' : '' }}"
           href="{{ route('admin.offerings.index') }}">
            <i class="bi bi-capsule"></i> Offerings
            @php $pendingOfferingsCount = \App\Models\Offering::where('approval_status', 'pending')->count(); @endphp
            @if($pendingOfferingsCount > 0)
                <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem;">{{ $pendingOfferingsCount }}</span>
            @endif
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
           href="{{ route('admin.categories.index') }}">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a class="nav-link {{ request()->routeIs('admin.questionnaires.*') ? 'active' : '' }}"
           href="{{ route('admin.questionnaires.index') }}">
            <i class="bi bi-ui-checks"></i> Questionnaires
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}"
           href="{{ route('admin.questions.index') }}">
            <i class="bi bi-question-circle"></i> Question Bank
        </a>

    </div>

    {{-- ── API & Developer ─────────────────────────────────── --}}
    {{-- Super admin only: "All API integrations etc should be a super admin
         function" (Devin msg 2117). The routes enforce it; this keeps a Doctor
         Admin from being shown links they cannot open. --}}
    @role('super_admin')
    <button class="sidebar-section-toggle {{ $apiActive ? '' : 'collapsed' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#snav-api"
            aria-expanded="{{ $apiActive ? 'true' : 'false' }}">
        <span>API &amp; Developer</span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $apiActive ? 'show' : '' }}" id="snav-api">

        <a class="nav-link {{ request()->routeIs('admin.guide.messaging') ? 'active' : '' }}"
           href="{{ route('admin.guide.messaging') }}">
            <i class="bi bi-chat-dots"></i> Messaging API
        </a>
        <a class="nav-link {{ request()->routeIs('admin.guide.glp-api') ? 'active' : '' }}"
           href="{{ route('admin.guide.glp-api') }}">
            <i class="bi bi-journal-medical"></i> GLP API
        </a>
        <a class="nav-link {{ request()->routeIs('admin.guide.antiaging-api') ? 'active' : '' }}"
           href="{{ route('admin.guide.antiaging-api') }}">
            <i class="bi bi-stars"></i> Anti-Aging API
        </a>
        <a class="nav-link {{ request()->routeIs('admin.guide.webhooks') ? 'active' : '' }}"
           href="{{ route('admin.guide.webhooks') }}">
            <i class="bi bi-broadcast-pin"></i> Webhook Guide
        </a>
        <a class="nav-link {{ request()->routeIs('admin.webhooks.*') ? 'active' : '' }}"
           href="{{ route('admin.webhooks.index') }}">
            <i class="bi bi-broadcast"></i> Webhook Logs
            @php $failedWebhooksCount = \App\Models\WebhookDelivery::where('status', 'failed')->count(); @endphp
            @if($failedWebhooksCount > 0)
                <span class="badge bg-danger ms-auto" style="font-size:.6rem;">{{ $failedWebhooksCount }}</span>
            @endif
        </a>

    </div>
    @endrole

    {{-- ── Configuration ───────────────────────────────────── --}}
    {{-- Also super admin only: settings and the triage rule set change clinical
         behaviour for every doctor, not just one admin's group. --}}
    @role('super_admin')
    <button class="sidebar-section-toggle {{ $cfgActive ? '' : 'collapsed' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#snav-config"
            aria-expanded="{{ $cfgActive ? 'true' : 'false' }}">
        <span>Configuration</span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $cfgActive ? 'show' : '' }}" id="snav-config">

        <a class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}"
           href="{{ route('admin.settings') }}">
            <i class="bi bi-sliders"></i> Settings
        </a>
        <a class="nav-link {{ request()->routeIs('admin.triage-rules.*') ? 'active' : '' }}"
           href="{{ route('admin.triage-rules.index') }}">
            <i class="bi bi-funnel"></i> Triage Rule Set
        </a>
        <a class="nav-link {{ request()->routeIs('admin.routing.index') ? 'active' : '' }}"
           href="{{ route('admin.routing.index') }}">
            <i class="bi bi-diagram-3"></i> Case Routing
        </a>
        {{-- Which states require a live video visit (Devin msg 2313 Q4). Super
             admin only, with the rest of this section, because it encodes
             telehealth law rather than one admin's operating preference. --}}
        <a class="nav-link {{ request()->routeIs('admin.routing.visit-requirements*') ? 'active' : '' }}"
           href="{{ route('admin.routing.visit-requirements') }}">
            <i class="bi bi-camera-video"></i> State Visit Rules
        </a>
        <a class="nav-link {{ request()->routeIs('admin.ai.index', 'admin.ai.edit', 'admin.ai.update', 'admin.ai.examples.*') ? 'active' : '' }}"
           href="{{ route('admin.ai.index') }}">
            <i class="bi bi-robot"></i> AI Instructions
        </a>
        <a class="nav-link {{ request()->routeIs('admin.ai.settings*') ? 'active' : '' }}"
           href="{{ route('admin.ai.settings') }}">
            <i class="bi bi-sliders"></i> AI Settings
        </a>

    </div>
    @endrole

    {{-- ── Routing operations ──────────────────────────────────
         NOT super-admin gated, unlike the configuration block above. Devin msg
         2308 named the Doctor Admin FIRST for exception visibility, and msg 2313
         Q6 put SLA ownership and pull approvals in their hands. Each screen
         scopes its data to the doctors that admin is over, so opening the nav to
         them does not widen what they can see. --}}
    <button class="sidebar-section-toggle {{ request()->routeIs('admin.routing.exceptions') || request()->routeIs('admin.routing.pull-requests') || request()->routeIs('admin.routing.sla') ? '' : 'collapsed' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#snav-routing-ops"
            aria-expanded="false">
        <span>Routing Operations</span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ request()->routeIs('admin.routing.exceptions') || request()->routeIs('admin.routing.pull-requests') || request()->routeIs('admin.routing.sla') ? 'show' : '' }}" id="snav-routing-ops">

        <a class="nav-link {{ request()->routeIs('admin.routing.exceptions') ? 'active' : '' }}"
           href="{{ route('admin.routing.exceptions') }}">
            <i class="bi bi-exclamation-octagon"></i> Routing Exceptions
            @php $openRoutingExceptions = \App\Models\RoutingException::open()->count(); @endphp
            @if($openRoutingExceptions > 0)
                <span class="badge bg-danger ms-auto" style="font-size:.6rem;">{{ $openRoutingExceptions }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('admin.routing.pull-requests') ? 'active' : '' }}"
           href="{{ route('admin.routing.pull-requests') }}">
            <i class="bi bi-inbox-fill"></i> Case Pull Requests
            @php $pendingPulls = \App\Models\CasePullRequest::pending()->count(); @endphp
            @if($pendingPulls > 0)
                <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem;">{{ $pendingPulls }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('admin.routing.sla') ? 'active' : '' }}"
           href="{{ route('admin.routing.sla') }}">
            <i class="bi bi-speedometer2"></i> Provider Pull SLA
        </a>

    </div>

    {{-- ── Super Admin ──────────────────────────────────────── --}}
    @role('super_admin')
    <button class="sidebar-section-toggle {{ $superActive ? '' : 'collapsed' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#snav-super"
            aria-expanded="{{ $superActive ? 'true' : 'false' }}">
        <span>Super Admin</span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $superActive ? 'show' : '' }}" id="snav-super">

        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
           href="{{ route('admin.users.index') }}">
            <i class="bi bi-people"></i> All Users
        </a>
        <a class="nav-link sub {{ request()->routeIs('admin.admins.*') ? 'active' : '' }}"
           href="{{ route('admin.admins.index') }}">
            <i class="bi bi-shield-lock"></i> Admin Users
        </a>
        <a class="nav-link {{ request()->routeIs('admin.audit-log.*') ? 'active' : '' }}"
           href="{{ route('admin.audit-log.index') }}">
            <i class="bi bi-journal-text"></i> Audit Log
        </a>

    </div>
    @endrole

</div>

<script>
/* Save collapse state per group in localStorage so user preference survives navigation */
document.addEventListener('DOMContentLoaded', function () {
    var KEY = 'admin_sidebar_v1';
    function getSaved() { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } }
    function setSaved(s) { localStorage.setItem(KEY, JSON.stringify(s)); }

    ['management', 'api', 'config', 'super'].forEach(function (g) {
        var el = document.getElementById('snav-' + g);
        if (!el) return;
        el.addEventListener('hidden.bs.collapse', function () { var s = getSaved(); s[g] = false; setSaved(s); });
        el.addEventListener('shown.bs.collapse',  function () { var s = getSaved(); s[g] = true;  setSaved(s); });
    });
});
</script>
@endsection
