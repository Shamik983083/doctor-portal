<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Partner Portal') — Doctor Portal</title>
    <!-- Resolve CDN DNS before the parser hits the stylesheet requests -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- Standardised to 5.3.3 (same as admin layout — single cached resource) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* ── Tokens ─────────────────────────────────────────── */
        :root { --sidebar-w: 230px; }

        /* ── Base ───────────────────────────────────────────── */
        body { background: #f0f4f8; }

        /* ── Sidebar ────────────────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            background: #0d47a1;
            color: #fff;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }

        #sidebar .brand {
            padding: 1rem 1.2rem;
            font-size: .95rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,.1);
            display: flex;
            flex-direction: column;
            gap: .1rem;
            flex-shrink: 0;
        }
        #sidebar .brand small { font-size: .68rem; font-weight: 400; opacity: .55; }

        #sidebar .nav-link {
            color: rgba(255,255,255,.72);
            padding: .38rem 1.2rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: background .15s, color .15s;
        }
        #sidebar .nav-link:hover  { color: #fff; background: rgba(255,255,255,.1); }
        #sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.12); border-left-color: #93c5fd; }

        #sidebar .nav-section {
            font-size: .58rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            padding: .65rem 1.2rem .15rem;
        }

        /* ── Sidebar overlay (mobile) ───────────────────────── */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.48);
            z-index: 1044;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }
        .sidebar-overlay.show { opacity: 1; pointer-events: auto; }

        /* ── Topbar ─────────────────────────────────────────── */
        #topbar {
            margin-left: var(--sidebar-w);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: .65rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        /* ── Main content ───────────────────────────────────── */
        #main {
            margin-left: var(--sidebar-w);
            padding: 1.75rem;
        }

        /* ── Cards ──────────────────────────────────────────── */
        .card { border: none; border-radius: .75rem; box-shadow: 0 1px 4px rgba(0,0,0,.07); }

        /* ── Case status badges ─────────────────────────────── */
        .badge-created    { background: #e2e8f0; color: #475569; }
        .badge-waiting    { background: #fef3c7; color: #92400e; }
        .badge-support    { background: #dbeafe; color: #1e40af; }
        .badge-assigned   { background: #ede9fe; color: #5b21b6; }
        .badge-approved   { background: #d1fae5; color: #065f46; }
        .badge-processing { background: #e0f2fe; color: #0369a1; }
        .badge-completed  { background: #dcfce7; color: #14532d; }
        .badge-cancelled  { background: #fee2e2; color: #991b1b; }

        /* ── Responsive: tablet (< 992px) ──────────────────── */
        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.show {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,.35);
            }
            #topbar {
                margin-left: 0;
                padding: .6rem 1rem;
            }
            #main {
                margin-left: 0;
                padding: 1.25rem;
            }
        }

        /* ── Responsive: mobile (< 576px) ──────────────────── */
        @media (max-width: 575.98px) {
            #main { padding: .75rem; }
            .table-responsive { -webkit-overflow-scrolling: touch; }
            #topbar h6 {
                font-size: .85rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 140px;
            }
        }

        /* ── Reduced motion ─────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            #sidebar, .sidebar-overlay { transition: none; }
        }
    </style>
</head>
<body>

{{-- Sidebar overlay for mobile --}}
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

{{-- ── Sidebar ── --}}
<div id="sidebar" aria-label="Partner navigation">
    <div class="brand">
        <span><i class="bi bi-building me-1"></i> Partner Portal</span>
        <small>{{ Auth::user()->partner->name ?? 'Partner' }}</small>
    </div>
    <nav class="py-2 flex-grow-1">
        <div class="nav-section">Overview</div>
        <a href="{{ route('partner.dashboard') }}"
           class="nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <div class="nav-section">Catalogue</div>
        <a href="{{ route('partner.offerings.index') }}"
           class="nav-link {{ request()->routeIs('partner.offerings.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i> Offerings
        </a>
        <div class="nav-section">Patients &amp; Cases</div>
        <a href="{{ route('partner.patients.index') }}"
           class="nav-link {{ request()->routeIs('partner.patients.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Patients
        </a>
        <a href="{{ route('partner.cases.index') }}"
           class="nav-link {{ request()->routeIs('partner.cases.*') ? 'active' : '' }}">
            <i class="bi bi-folder2-open"></i> Cases
        </a>
        <div class="nav-section">Integration</div>
        <a href="{{ route('partner.credentials') }}"
           class="nav-link {{ request()->routeIs('partner.credentials') ? 'active' : '' }}">
            <i class="bi bi-key"></i> API Credentials
        </a>
    </nav>
    <div class="px-3 py-3" style="border-top:1px solid rgba(255,255,255,.1);">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light w-100">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </button>
        </form>
    </div>
</div>

{{-- ── Topbar ── --}}
<div id="topbar">
    <div class="d-flex align-items-center gap-2">
        {{-- Hamburger — visible only on mobile --}}
        <button class="btn btn-sm btn-outline-secondary d-lg-none"
                id="sidebarToggle"
                aria-label="Toggle navigation"
                aria-expanded="false"
                aria-controls="sidebar">
            <i class="bi bi-list" style="font-size:1.2rem; line-height:1;"></i>
        </button>
        <h6 class="mb-0 fw-semibold">@yield('page-title')</h6>
    </div>
    <div class="d-flex align-items-center gap-2 gap-sm-3">
        <span class="text-muted small d-none d-sm-inline">
            <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name ?? '' }}
        </span>
    </div>
</div>

{{-- ── Main content ── --}}
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
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<script>
(function () {
    'use strict';

    /* ── Mobile sidebar toggle ─────────────────────────────── */
    var toggle  = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar && overlay) {
        function openSidebar() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });

        overlay.addEventListener('click', closeSidebar);

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeSidebar();
        });

        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });
    }

    /* ── Auto-wrap bare tables in responsive scroll container ─ */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#main table').forEach(function (t) {
            if (t.closest('.table-responsive')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    });
})();
</script>

@stack('scripts')
</body>
</html>
