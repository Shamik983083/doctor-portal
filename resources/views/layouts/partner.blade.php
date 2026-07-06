<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Partner Portal') — Doctor Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root { --sidebar-width: 240px; }
        body { background: #f0f4f8; }
        #sidebar {
            width: var(--sidebar-width); min-height: 100vh; position: fixed;
            top: 0; left: 0; background: #0d47a1; color: #fff; z-index: 100;
            display: flex; flex-direction: column;
        }
        #sidebar .brand {
            padding: 1.2rem 1.5rem; font-size: 1.1rem; font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,.15); letter-spacing: .5px;
        }
        #sidebar .brand small { display: block; font-size: .7rem; font-weight: 400; opacity: .7; }
        #sidebar .nav-link {
            color: rgba(255,255,255,.75); padding: .55rem 1.5rem;
            display: flex; align-items: center; gap: .6rem; border-radius: 0;
            transform-origin: left center;
            transition: transform .18s ease, background .18s ease, color .18s ease, box-shadow .18s ease;
        }
        #sidebar .nav-link:hover {
            color: #fff; background: rgba(255,255,255,.12);
            transform: scale(1.06); box-shadow: 0 2px 10px rgba(0,0,0,.25); border-radius: 4px;
        }
        #sidebar .nav-link.active {
            color: #fff; background: rgba(255,255,255,.12);
        }
        #sidebar .nav-section {
            font-size: .65rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            color: rgba(255,255,255,.4); padding: 1rem 1.5rem .3rem;
        }
        #topbar {
            margin-left: var(--sidebar-width); background: #fff;
            border-bottom: 1px solid #e2e8f0; padding: .75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 99;
        }
        #main { margin-left: var(--sidebar-width); padding: 1.75rem; }
        .card { border: none; border-radius: .75rem; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        .badge-created    { background: #e2e8f0; color: #475569; }
        .badge-waiting    { background: #fef3c7; color: #92400e; }
        .badge-support    { background: #dbeafe; color: #1e40af; }
        .badge-assigned   { background: #ede9fe; color: #5b21b6; }
        .badge-approved   { background: #d1fae5; color: #065f46; }
        .badge-processing { background: #e0f2fe; color: #0369a1; }
        .badge-completed  { background: #dcfce7; color: #14532d; }
        .badge-cancelled  { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="brand">
        <i class="bi bi-building"></i> Partner Portal
        <small>{{ Auth::user()->partner->name ?? 'Partner' }}</small>
    </div>
    <nav class="py-2 flex-grow-1">
        <div class="nav-section">Overview</div>
        <a href="{{ route('partner.dashboard') }}" class="nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="nav-section">Catalogue</div>
        <a href="{{ route('partner.offerings.index') }}" class="nav-link {{ request()->routeIs('partner.offerings.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Offerings
        </a>
        <div class="nav-section">Patients & Cases</div>
        <a href="{{ route('partner.patients.index') }}" class="nav-link {{ request()->routeIs('partner.patients.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Patients
        </a>
        <a href="{{ route('partner.cases.index') }}" class="nav-link {{ request()->routeIs('partner.cases.*') ? 'active' : '' }}">
            <i class="bi bi-folder2-open"></i> Cases
        </a>
        <div class="nav-section">Integration</div>
        <a href="{{ route('partner.credentials') }}" class="nav-link {{ request()->routeIs('partner.credentials') ? 'active' : '' }}">
            <i class="bi bi-key"></i> API Credentials
        </a>
    </nav>
    <div class="px-3 py-3 border-top border-white border-opacity-10">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light w-100">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </button>
        </form>
    </div>
</div>

<div id="topbar">
    <h6 class="mb-0 fw-semibold">@yield('page-title')</h6>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}</span>
    </div>
</div>

<div id="main">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
