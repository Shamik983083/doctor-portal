<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Partner Portal') — Doctor Portal</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <!-- Resolve CDN DNS before the parser hits the stylesheet requests -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- Standardised to 5.3.3 (same as admin layout — single cached resource) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* ── Tokens ─────────────────────────────────────────── */
        :root {
            --sidebar-w:      240px;
            --sidebar-bg:     #0f172a;
            --sidebar-border: rgba(255,255,255,.06);
            --accent:         #38bdf8;
            --accent-bg:      rgba(56,189,248,.12);
        }

        /* ── Base ───────────────────────────────────────────── */
        body { background: #f1f5f9; }

        /* ── Sidebar ────────────────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            background: var(--sidebar-bg);
            color: #94a3b8;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform .25s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }

        /* Brand */
        #sidebar .brand {
            padding: 1.1rem 1.25rem .95rem;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
            text-decoration: none;
        }
        #sidebar .brand-title {
            font-size: .92rem;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: .45rem;
            line-height: 1.2;
        }
        #sidebar .brand-title .brand-icon {
            width: 28px; height: 28px;
            border-radius: 7px;
            background: var(--accent-bg);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        #sidebar .brand-title .brand-icon i { color: var(--accent); font-size: .85rem; }
        #sidebar .brand-sub {
            font-size: .68rem;
            color: #64748b;
            margin-top: .3rem;
            padding-left: .1rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Nav sections */
        #sidebar .nav-section {
            font-size: .58rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #334155;
            padding: .85rem 1.25rem .2rem;
        }

        /* Nav links */
        #sidebar .nav-link {
            color: #94a3b8;
            padding: .42rem 1rem .42rem 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            border-radius: 0;
            margin: 1px 0;
            transition: background .15s, color .15s, border-color .15s;
        }
        #sidebar .nav-link i { width: 16px; text-align: center; font-size: .85rem; flex-shrink: 0; }
        #sidebar .nav-link:hover  { color: #e2e8f0; background: rgba(255,255,255,.05); }
        #sidebar .nav-link.active {
            color: var(--accent);
            background: var(--accent-bg);
            border-left-color: var(--accent);
            font-weight: 600;
        }

        /* Bottom sign-out area */
        #sidebar .sidebar-footer {
            padding: .85rem 1.25rem;
            border-top: 1px solid var(--sidebar-border);
            margin-top: auto;
            flex-shrink: 0;
        }
        #sidebar .sidebar-footer .btn {
            font-size: .78rem;
            color: #64748b;
            border-color: #1e293b;
            background: transparent;
            transition: background .15s, color .15s;
        }
        #sidebar .sidebar-footer .btn:hover { background: rgba(255,255,255,.06); color: #cbd5e1; border-color: #334155; }

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
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
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
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,.35); }
            #topbar { margin-left: 0; padding: .6rem 1rem; }
            #main   { margin-left: 0; padding: 1.25rem; }
        }

        /* ── Responsive: mobile (< 576px) ──────────────────── */
        @media (max-width: 575.98px) {
            #main { padding: .75rem; }
            .table-responsive { -webkit-overflow-scrolling: touch; }
            #topbar h6 { font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
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

    {{-- Brand --}}
    <div class="brand">
        <div class="brand-title">
            <div class="brand-icon"><i class="bi bi-building-fill"></i></div>
            Partner Portal
        </div>
        <div class="brand-sub">{{ Auth::user()->partner->name ?? 'Partner' }}</div>
    </div>

    {{-- Nav --}}
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

    {{-- Sign out --}}
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm w-100">
                <i class="bi bi-box-arrow-left me-1"></i> Sign Out
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

        {{-- Notification bell --}}
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
                <div class="notif-panel-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold" style="font-size:.9rem;">Notifications</span>
                    <button class="btn btn-link btn-sm p-0 text-muted" id="notifMarkAllBtn" style="font-size:.75rem;">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty" id="notifEmpty">
                        <i class="bi bi-bell-slash d-block fs-3 mb-2 text-muted"></i>
                        <span class="text-muted small">You're all caught up!</span>
                    </div>
                </div>
                <div class="notif-panel-footer text-center">
                    <a href="{{ route('partner.notifications.index') }}" class="text-muted small" style="text-decoration:none;">View all notifications</a>
                </div>
            </div>
        </div>

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

<style>
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
.notif-panel {
    width: 360px;
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
    max-height: 360px;
    overflow-y: auto;
    background: #fff;
}
.notif-empty { padding: 2.5rem 1rem; text-align: center; }
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
.notif-icon.case_completed { background: #dcfce7; color: #15803d; }
.notif-icon.case_cancelled { background: #fee2e2; color: #b91c1c; }
.notif-icon.case_support   { background: #fef3c7; color: #b45309; }
.notif-icon.info           { background: #f1f5f9; color: #64748b; }
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
    var csrf       = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var badge      = document.getElementById('notifBadge');
    var list       = document.getElementById('notifList');
    var empty      = document.getElementById('notifEmpty');
    var markAllBtn = document.getElementById('notifMarkAllBtn');
    var bellBtn    = document.getElementById('notifBellBtn');
    var loaded     = false;

    var iconMap = {
        case_completed: { icon: 'bi-check-circle-fill', cls: 'case_completed' },
        case_cancelled: { icon: 'bi-x-circle-fill',     cls: 'case_cancelled' },
        case_support:   { icon: 'bi-headset',           cls: 'case_support'   },
        info:           { icon: 'bi-info-circle-fill',  cls: 'info'           },
    };

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderNotifications(items) {
        if (!items.length) { list.innerHTML = ''; list.appendChild(empty); return; }
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
            el.addEventListener('click', function () {
                if (el.classList.contains('unread')) {
                    fetch('/partner/notifications/' + el.dataset.id + '/read', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    }).then(function () { el.classList.remove('unread'); refreshBadge(); });
                }
            });
        });
    }

    function refreshBadge() {
        fetch('/partner/notifications', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var count = data.unread || 0;
                if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.classList.remove('d-none'); }
                else { badge.classList.add('d-none'); }
                if (loaded) return;
                renderNotifications(data.notifications || []);
            }).catch(function () {});
    }

    if (bellBtn) {
        bellBtn.addEventListener('click', function () {
            fetch('/partner/notifications', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loaded = true;
                    renderNotifications(data.notifications || []);
                    var count = data.unread || 0;
                    if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.classList.remove('d-none'); }
                    else { badge.classList.add('d-none'); }
                }).catch(function () {});
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('/partner/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(function () {
                list.querySelectorAll('.notif-item.unread').forEach(function (el) { el.classList.remove('unread'); });
                badge.classList.add('d-none');
            });
        });
    }

    refreshBadge();
    setInterval(refreshBadge, 60000);
})();
</script>

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

<script>
(function () {
    var SPINNER = '<svg style="display:inline-block;vertical-align:middle;margin-right:5px;animation:glbl-spin .65s linear infinite" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/><path d="M7 1.5a5.5 5.5 0 0 1 5.5 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    var STYLE = document.createElement('style');
    STYLE.textContent = '@keyframes glbl-spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(STYLE);
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.noSubmitLoader !== undefined) return;
        var btn = form.querySelector('button[type="submit"]:not([data-no-loader])');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = SPINNER + 'Saving…';
        btn.style.opacity = '.88';
    }, true);
})();
</script>
</body>
</html>
