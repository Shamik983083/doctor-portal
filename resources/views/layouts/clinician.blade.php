@extends('layouts.app')

@section('sidebar-nav')
<ul class="nav flex-column mt-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('clinician.dashboard') ? 'active' : '' }}" href="{{ route('clinician.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('clinician.queue') || request()->routeIs('clinician.cases.*') ? 'active' : '' }}" href="{{ route('clinician.queue') }}">
            <i class="bi bi-inbox"></i> Case Queue
        </a>
    </li>
</ul>
<div class="mt-auto px-3 py-3 border-top border-secondary" style="position:absolute; bottom:0; width:100%;">
    <small class="text-muted">Clinician Portal</small>
</div>
@endsection
