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

        /* ── Base ───────────────────────────────────────────────
           Fonts and palette matched to the design preview
           (docs/design-preview/index.html, served at /medaxis-preview/), so the
           whole portal reads as one system (Devin msg 2256: "I want it all to
           look like that ... the font styling everything to match"). System
           font stack, so nothing is fetched from a CDN. */
        body {
            color: #172033;
            background: radial-gradient(circle at top left, #fff 0, #f5f7fb 42%, #f3f6fb 100%);
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI Variable Text", "Segoe UI", Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 15px;
            line-height: 1.47;
            letter-spacing: -.006em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-variant-numeric: tabular-nums;
        }
        h1, h2, h3, h4, h5, h6, .sidebar-brand {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI Variable Display", "Segoe UI", Inter, sans-serif;
            letter-spacing: -.01em;
        }

        /* ── Sidebar (light, matching the preview) ──────────────
           Was a dark rail; now light across the whole portal. Admin, clinician
           and partner all share this shell, so lighting it here lights all
           three, which is what Devin asked for. */
        .sidebar {
            width: var(--sidebar-w);
            position: fixed;
            top: 0; bottom: 0; left: 0;
            overflow-y: auto;
            overflow-x: hidden;
            background: #ffffff;
            color: #475569;
            border-right: 1px solid #e5e9f0;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            /* Hardware-accelerated slide transition */
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }

        .sidebar .nav-link {
            color: #475569;
            padding: .42rem .9rem;
            border-radius: 8px;
            margin: 1px 8px;
            font-size: .84rem;
            font-weight: 500;
            transition: background .15s, color .15s;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover  { background: #f1f5f9; color: #0f172a; }
        .sidebar .nav-link.active { background: #e0e7ff; color: #1e293b; font-weight: 600; }
        .sidebar .nav-link i      { width: 18px; text-align: center; margin-right: .4rem; font-size: .88rem; flex-shrink: 0; }
        .sidebar .nav-link.sub    { font-size: .8rem; padding-left: 2.2rem; color: #64748b; }
        .sidebar .nav-link.sub:hover  { color: #0f172a; }
        .sidebar .nav-link.sub.active { color: #1e293b; background: #eef2ff; }

        .sidebar-brand {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            padding: 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            border-bottom: 1px solid #eef1f6;
            flex-shrink: 0;
        }

        .sidebar-section, .sidebar-section-toggle {
            padding: .8rem 1.1rem .2rem;
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            font-weight: 700;
            display: block;
            background: none;
            border: 0;
            width: 100%;
            text-align: left;
        }
        .sidebar hr { border-color: #eef1f6; margin: .3rem 0; }

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

        /* ── Sidebar collapsible group toggles (light) ──────── */
        .sidebar-section-toggle {
            background: none;
            border: none;
            cursor: pointer;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .8rem 1.1rem .2rem;
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            font-weight: 700;
            transition: color .15s;
        }
        .sidebar-section-toggle:hover { color: #64748b; }
        .sidebar-chevron {
            font-size: .6rem;
            opacity: .45;
            flex-shrink: 0;
            transition: transform .2s ease, opacity .15s;
        }
        .sidebar-section-toggle:hover .sidebar-chevron { opacity: .8; }
        .sidebar-section-toggle.collapsed .sidebar-chevron { transform: rotate(-90deg); }

        /* ── Reduced motion ─────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay { transition: none; }
            .sidebar-chevron { transition: none; }
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

                {{-- Notification bell — admin/super_admin/clinician --}}
                @if(Auth::check() && Auth::user()->hasAnyRole(['admin','super_admin','clinician','partner']))
                @php
                    $notifBase = match(true) {
                        Auth::user()->hasAnyRole(['admin','super_admin']) => '/admin/notifications',
                        Auth::user()->hasRole('clinician')                => '/clinician/notifications',
                        default                                           => '/partner/notifications',
                    };
                    $notifViewAllRoute = match(true) {
                        Auth::user()->hasAnyRole(['admin','super_admin']) => route('admin.notifications.index'),
                        Auth::user()->hasRole('clinician')                => route('clinician.notifications.index'),
                        default                                           => route('partner.notifications.index'),
                    };
                @endphp
                <div class="dropdown" id="notifDropdownWrap">
                    <button class="btn btn-sm notif-bell-btn position-relative"
                            id="notifBellBtn"
                            aria-label="Notifications"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false">
                        <i class="bi bi-bell-fill" style="font-size:1.1rem;"></i>
                        <span class="notif-badge d-none" id="notifBadge"></span>
                    </button>

                    <div class="dropdown-menu notif-panel p-0 shadow-lg" aria-labelledby="notifBellBtn">
                        {{-- Panel header --}}
                        <div class="notif-panel-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold" style="font-size:.9rem;">Notifications</span>
                            <button class="btn btn-link btn-sm p-0 text-muted" id="notifMarkAllBtn" style="font-size:.75rem;">Mark all read</button>
                        </div>

                        {{-- Notification list --}}
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty" id="notifEmpty">
                                <i class="bi bi-bell-slash d-block fs-3 mb-2 text-muted"></i>
                                <span class="text-muted small">You're all caught up!</span>
                            </div>
                        </div>

                        {{-- Panel footer --}}
                        <div class="notif-panel-footer text-center">
                            <a href="{{ $notifViewAllRoute }}" class="text-muted small" style="text-decoration:none;">View all notifications</a>
                        </div>
                    </div>
                </div>
                @endif

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

@if(Auth::check() && Auth::user()->hasAnyRole(['admin','super_admin','clinician','partner']))
@php
    $notifBase ??= match(true) {
        Auth::user()->hasAnyRole(['admin','super_admin']) => '/admin/notifications',
        Auth::user()->hasRole('clinician')                => '/clinician/notifications',
        default                                           => '/partner/notifications',
    };
@endphp
<style>
/* ── Notification bell ───────────────────────────────────── */
.notif-bell-btn {
    color: #64748b;
    background: transparent;
    border: none;
    padding: .3rem .45rem;
    border-radius: .4rem;
    transition: background .15s, color .15s;
    line-height: 1;
}
.notif-bell-btn:hover { background: #f1f5f9; color: #0f172a; }
.notif-bell-btn:focus { box-shadow: none; }

.notif-badge {
    position: absolute;
    top: 2px; right: 2px;
    min-width: 1rem; height: 1rem;
    padding: 0 .25rem;
    font-size: .6rem; font-weight: 700;
    line-height: 1rem;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    text-align: center;
    pointer-events: none;
}

/* ── Dropdown panel ─────────────────────────────────────── */
.notif-panel {
    width: 380px;
    max-width: 95vw;
    border-radius: .6rem;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    right: 0 !important;
    left: auto !important;
}
.notif-panel-header {
    padding: .85rem 1rem .75rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 1;
}
.notif-list {
    max-height: 380px;
    overflow-y: auto;
    background: #fff;
}
.notif-empty {
    padding: 2.5rem 1rem;
    text-align: center;
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .75rem 1rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background .12s;
    text-decoration: none;
    color: inherit;
}
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #eff6ff; border-left: 3px solid #3b82f6; }
.notif-item.unread:hover { background: #dbeafe; }
.notif-icon {
    width: 2.2rem; height: 2.2rem;
    border-radius: 999px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
    margin-top: .05rem;
}
.notif-icon.new_case      { background: #dbeafe; color: #1d4ed8; }
.notif-icon.case_assigned { background: #fef3c7; color: #b45309; }
.notif-icon.case_completed{ background: #dcfce7; color: #15803d; }
.notif-icon.new_offering  { background: #ede9fe; color: #7c3aed; }
.notif-icon.new_message   { background: #fce7f3; color: #be185d; }
.notif-icon.info          { background: #f1f5f9; color: #64748b; }
.notif-title { font-size: .8rem; font-weight: 600; color: #0f172a; line-height: 1.3; }
.notif-body  { font-size: .75rem; color: #64748b; margin-top: .15rem; line-height: 1.4; }
.notif-time  { font-size: .68rem; color: #94a3b8; margin-top: .25rem; }
.notif-panel-footer {
    padding: .6rem 1rem;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}
</style>

<script>
(function () {
    var csrf      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var badge     = document.getElementById('notifBadge');
    var list      = document.getElementById('notifList');
    var empty     = document.getElementById('notifEmpty');
    var markAllBtn= document.getElementById('notifMarkAllBtn');
    var bellBtn   = document.getElementById('notifBellBtn');
    var loaded    = false;

    var iconMap = {
        new_case:       { icon: 'bi-inbox-fill',        cls: 'new_case'       },
        case_assigned:  { icon: 'bi-person-check-fill', cls: 'case_assigned'  },
        case_completed: { icon: 'bi-check-circle-fill', cls: 'case_completed' },
        new_offering:   { icon: 'bi-box-seam-fill',     cls: 'new_offering'   },
        new_message:    { icon: 'bi-chat-dots-fill',    cls: 'new_message'    },
        info:           { icon: 'bi-info-circle-fill',  cls: 'info'           },
    };

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderNotifications(items) {
        if (!items.length) {
            list.innerHTML = '';
            list.appendChild(empty);
            return;
        }
        var html = '';
        items.forEach(function (n) {
            var meta = iconMap[n.type] || iconMap.info;
            html += '<a href="' + esc(n.url) + '" class="notif-item' + (n.is_read ? '' : ' unread') + '" data-id="' + esc(n.id) + '">'
                + '<div class="notif-icon ' + meta.cls + '"><i class="bi ' + meta.icon + '"></i></div>'
                + '<div class="flex-grow-1 min-w-0">'
                +   '<div class="notif-title">' + esc(n.title) + '</div>'
                +   '<div class="notif-body">'  + esc(n.body)  + '</div>'
                +   '<div class="notif-time">'  + esc(n.time)  + '</div>'
                + '</div>'
                + '</a>';
        });
        list.innerHTML = html;

        list.querySelectorAll('.notif-item').forEach(function (el) {
            el.addEventListener('click', function (e) {
                var id = el.dataset.id;
                if (el.classList.contains('unread')) {
                    fetch('{{ $notifBase }}/' + id + '/read', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    }).then(function () { el.classList.remove('unread'); refreshBadge(); });
                }
            });
        });
    }

    function refreshBadge() {
        fetch('{{ $notifBase }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var count = data.unread || 0;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
                if (loaded) return;
                renderNotifications(data.notifications || []);
            })
            .catch(function () {});
    }

    // Load panel content when dropdown opens
    if (bellBtn) {
        bellBtn.addEventListener('click', function () {
            fetch('{{ $notifBase }}', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loaded = true;
                    renderNotifications(data.notifications || []);
                    var count = data.unread || 0;
                    if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.classList.remove('d-none'); }
                    else { badge.classList.add('d-none'); }
                })
                .catch(function () {});
        });
    }

    // Mark all read
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('{{ $notifBase }}/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(function () {
                list.querySelectorAll('.notif-item.unread').forEach(function (el) { el.classList.remove('unread'); });
                badge.classList.add('d-none');
            });
        });
    }

    // Poll badge count every 60 seconds
    refreshBadge();
    setInterval(refreshBadge, 60000);
})();
</script>
@endif

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
