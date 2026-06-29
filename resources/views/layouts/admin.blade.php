@extends('layouts.app')

@section('sidebar-nav')
<ul class="nav flex-column mt-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <hr class="border-secondary my-2">
    <li><span class="px-3 text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Management</span></li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.cases.*') ? 'active' : '' }}" href="{{ route('admin.cases.index') }}">
            <i class="bi bi-folder2-open"></i> Cases
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link ps-4 {{ request()->routeIs('admin.form-submissions.*') ? 'active' : '' }}"
           href="{{ route('admin.form-submissions.index') }}" style="font-size:.85rem">
            <i class="bi bi-file-earmark-check me-1"></i> Form Submissions
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
        <a class="nav-link {{ request()->routeIs('admin.offerings.*') ? 'active' : '' }}" href="{{ route('admin.offerings.index') }}">
            <i class="bi bi-capsule"></i> Offerings
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link ps-4 {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}" style="font-size:.85rem">
            <i class="bi bi-tags me-1"></i> Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.questionnaires.*') ? 'active' : '' }}" href="{{ route('admin.questionnaires.index') }}">
            <i class="bi bi-ui-checks"></i> Questionnaires
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link ps-4 {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}" style="font-size:.85rem">
            <i class="bi bi-question-circle me-1"></i> Question Bank
        </a>
    </li>
    <hr class="border-secondary my-2">
    <li><span class="px-3 text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Clinician View</span></li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('clinician.dashboard') }}">
            <i class="bi bi-heart-pulse"></i> Clinician Portal
        </a>
    </li>
</ul>
<div class="mt-auto px-3 py-3 border-top border-secondary" style="position:absolute; bottom:0; width:100%;">
    <small class="text-muted">Admin Console</small>
</div>
@endsection
