<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Doctor Portal')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <!-- Resolve CDN DNS before the parser hits the stylesheet requests -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* ── Tokens ─────────────────────────────────────────── */
        :root {
            --sidebar-w: 240px;
            --topbar-h:  52px;
        }

        /* ── Base ───────────────────────────────────────────── */
        body { background: #f6f8fb; }

        /* ── Sidebar ────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            position: fixed;
            top: 0; bottom: 0; left: 0;
            overflow-y: auto;
            overflow-x: hidden;
            background: #0f172a;
            color: #94a3b8;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            /* Hardware-accelerated slide transition */
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }

        .sidebar .nav-link {
            color: #94a3b8;
            padding: .38rem .9rem;
            border-radius: 5px;
            margin: 1px 8px;
            font-size: .82rem;
            font-weight: 500;
            transition: background .15s, color .15s;
            border-left: 2px solid transparent;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover  { background: rgba(255,255,255,.08); color: #e2e8f0; }
        .sidebar .nav-link.active { background: rgba(59,130,246,.14); color: #60a5fa; border-left-color: #3b82f6; }
        .sidebar .nav-link i      { width: 18px; text-align: center; margin-right: .4rem; font-size: .88rem; flex-shrink: 0; }
        .sidebar .nav-link.sub    { font-size: .78rem; padding-left: 2.2rem; color: #64748b; }
        .sidebar .nav-link.sub:hover  { color: #cbd5e1; }
        .sidebar .nav-link.sub.active { color: #60a5fa; }

        .sidebar-brand {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            padding: 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,.07);
            flex-shrink: 0;
        }

        .sidebar-section {
            padding: .65rem 1.1rem .15rem;
            font-size: .59rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255,255,255,.3);
            font-weight: 600;
            display: block;
        }
        .sidebar hr { border-color: rgba(255,255,255,.07); margin: .3rem 0; }

        /* ── Overlay (mobile only) ──────────────────────────── */
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

        /* ── Main content ───────────────────────────────────── */
        .main-content {
            min-height: 100vh;
            margin-left: var(--sidebar-w);
            display: flex;
            flex-direction: column;
        }

        /* ── Topbar ─────────────────────────────────────────── */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: .65rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        /* ── Content area ───────────────────────────────────── */
        .page-content {
            padding: 1.5rem;
            flex: 1;
        }

        /* ── Status badges ──────────────────────────────────── */
        .badge-status-created    { background: #6c757d; }
        .badge-status-waiting    { background: #0d6efd; }
        .badge-status-assigned   { background: #fd7e14; }
        .badge-status-approved   { background: #198754; }
        .badge-status-processing { background: #0dcaf0; color: #000; }
        .badge-status-completed  { background: #198754; }
        .badge-status-cancelled  { background: #dc3545; }
        .badge-status-support    { background: #ffc107; color: #000; }

        /* ── Responsive: tablet (< 992px) ──────────────────── */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,.35);
            }
            .main-content {
                margin-left: 0;
            }
            .page-content {
                padding: 1rem;
            }
            .topbar {
                padding: .6rem 1rem;
            }
        }

        /* ── Responsive: mobile (< 576px) ──────────────────── */
        @media (max-width: 575.98px) {
            .page-content {
                padding: .75rem;
            }
            /* Tables always scroll on the smallest screens */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
            }
            /* Collapse long page titles gracefully */
            .topbar h6 {
                font-size: .85rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 140px;
            }
        }

        /* ── Reduced motion ─────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay { transition: none; }
        }
    </style>

    {{-- MA-DOCPORTAL design system promoted to global shell (O0.1) --}}
    <x-ma-styles />
</head>
<body>

{{-- Sidebar overlay for mobile (tap outside to close) --}}
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<div class="d-flex">

    {{-- ── Sidebar ── --}}
    <nav class="sidebar" id="adminSidebar" aria-label="Main navigation">
        <a class="sidebar-brand" href="/">
            <i class="bi bi-heart-pulse-fill me-2 text-danger"></i> Doctor Portal
        </a>
        @yield('sidebar-nav')
    </nav>

    {{-- ── Main area ── --}}
    <div class="main-content flex-grow-1">

        {{-- Topbar --}}
        <div class="topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                {{-- Hamburger — visible only on mobile --}}
                <button class="btn btn-sm btn-outline-secondary d-lg-none"
                        id="sidebarToggle"
                        aria-label="Toggle navigation"
                        aria-expanded="false"
                        aria-controls="adminSidebar">
                    <i class="bi bi-list" style="font-size:1.2rem; line-height:1;"></i>
                </button>
                <h6 class="mb-0 fw-semibold">@yield('page-title')</h6>
            </div>
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <span class="text-muted small d-none d-sm-inline">{{ Auth::user()->name ?? '' }}</span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-sm-inline ms-1">Logout</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Flash messages + content --}}
        <div class="page-content ma-surface">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
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

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<script>
(function () {
    'use strict';

    /* ── Mobile sidebar toggle ─────────────────────────────── */
    var toggle  = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
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

        /* Close when resized back to desktop */
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeSidebar();
        });

        /* Close when a nav link is clicked (navigating away) */
        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) closeSidebar();
            });
        });
    }

    /* ── Auto-wrap bare tables in responsive scroll container ─ */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.card-body table, .page-content table').forEach(function (t) {
            /* Skip tables already inside a .table-responsive wrapper */
            if (t.closest('.table-responsive')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    });
})();
</script>

@yield('scripts')
</body>
</html>
