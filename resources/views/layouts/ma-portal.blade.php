@extends('layouts.app')

@section('sidebar-nav')
<div class="sidebar-section">MA Portal preview</div>
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('ma-portal.practitioner') ? 'active' : '' }}" href="{{ route('ma-portal.practitioner') }}">
            <i class="bi bi-clipboard2-pulse"></i> Practitioner
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('ma-portal.admin') ? 'active' : '' }}" href="{{ route('ma-portal.admin') }}">
            <i class="bi bi-buildings"></i> Admin
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('ma-portal.super-admin') ? 'active' : '' }}" href="{{ route('ma-portal.super-admin') }}">
            <i class="bi bi-shield-lock"></i> Super Admin
        </a>
    </li>
</ul>
<div class="sidebar-section">Back to</div>
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('clinician.queue') }}"><i class="bi bi-arrow-left"></i> MEDAXIS portal</a>
    </li>
</ul>
@endsection
