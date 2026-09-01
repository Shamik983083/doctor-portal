@extends('layouts.app')

@section('sidebar-nav')
<div class="mt-1 pb-3">

    <a class="nav-link {{ request()->routeIs('support.dashboard') ? 'active' : '' }}"
       href="{{ route('support.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a class="nav-link {{ request()->routeIs('support.cases.*') ? 'active' : '' }}"
       href="{{ route('support.cases.index') }}">
        <i class="bi bi-headset"></i> Support Queue
        @php $supportCount = \App\Models\PatientCase::where('status', \App\Models\PatientCase::STATUS_SUPPORT)->count(); @endphp
        @if($supportCount > 0)
            <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem;">{{ $supportCount }}</span>
        @endif
    </a>

</div>
@endsection
