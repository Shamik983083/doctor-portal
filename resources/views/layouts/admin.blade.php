@extends('layouts.app')

@section('sidebar-nav')
<ul class="nav flex-column mt-2">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <hr>
    <li><span class="sidebar-section">Management</span></li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.cases.*') ? 'active' : '' }}" href="{{ route('admin.cases.index') }}">
            <i class="bi bi-folder2-open"></i> Cases
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.patients.*') ? 'active' : '' }}" href="{{ route('admin.patients.index') }}">
            <i class="bi bi-people"></i> Patients
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}" href="{{ route('admin.partners.index') }}">
            <i class="bi bi-building"></i> Partners
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.clinicians.*') ? 'active' : '' }}" href="{{ route('admin.clinicians.index') }}">
            <i class="bi bi-person-badge"></i> Clinicians
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link sub {{ request()->routeIs('admin.clinicians.priority') ? 'active' : '' }}" href="{{ route('admin.clinicians.priority') }}">
            <i class="bi bi-sort-numeric-down"></i> Assignment Priority
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.offerings.*') ? 'active' : '' }}" href="{{ route('admin.offerings.index') }}">
            <i class="bi bi-capsule"></i> Offerings
            @php $pendingOfferingsCount = \App\Models\Offering::where('approval_status', 'pending')->count(); @endphp
            @if($pendingOfferingsCount > 0)
                <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem;">{{ $pendingOfferingsCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link sub {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">
            <i class="bi bi-tags"></i> Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.questionnaires.*') ? 'active' : '' }}" href="{{ route('admin.questionnaires.index') }}">
            <i class="bi bi-ui-checks"></i> Questionnaires
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link sub {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}">
            <i class="bi bi-question-circle"></i> Question Bank
        </a>
    </li>
    <hr>
    <li><span class="sidebar-section">Integration Guides</span></li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.guide.messaging') ? 'active' : '' }}" href="{{ route('admin.guide.messaging') }}">
            <i class="bi bi-chat-dots"></i> Messaging API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.guide.weightloss-api') ? 'active' : '' }}" href="{{ route('admin.guide.weightloss-api') }}">
            <i class="bi bi-journal-medical"></i> Weight Loss API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.guide.antiaging-api') ? 'active' : '' }}" href="{{ route('admin.guide.antiaging-api') }}">
            <i class="bi bi-stars"></i> Anti-Aging API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.guide.webhooks') ? 'active' : '' }}" href="{{ route('admin.guide.webhooks') }}">
            <i class="bi bi-broadcast-pin"></i> Webhooks
        </a>
    </li>
    <hr>
    <li><span class="sidebar-section">Developer</span></li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.webhooks.*') ? 'active' : '' }}" href="{{ route('admin.webhooks.index') }}">
            <i class="bi bi-broadcast"></i> Webhook Logs
            @php $failedWebhooksCount = \App\Models\WebhookDelivery::where('status', 'failed')->count(); @endphp
            @if($failedWebhooksCount > 0)
                <span class="badge bg-danger ms-auto" style="font-size:.6rem;">{{ $failedWebhooksCount }}</span>
            @endif
        </a>
    </li>
    <hr>
    <li><span class="sidebar-section">Configuration</span></li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings') }}">
            <i class="bi bi-sliders"></i> Settings
        </a>
    </li>
</ul>
<div class="px-3 mt-3 pb-3" style="border-top:1px solid rgba(255,255,255,.06);">
    <small class="sidebar-section" style="padding:.5rem 0 0;">Admin Console</small>
</div>
@endsection
