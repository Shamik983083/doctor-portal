<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MEDAXIS')</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='sans-serif' font-size='17' font-weight='bold' fill='white' text-anchor='middle'%3EM%3C/text%3E%3C/svg%3E" />
    {{-- Bootstrap loaded first so preview-css overrides it on clinical screens --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        {{--
            The design preview's stylesheet, lifted verbatim (Devin msg 2265:
            "rebuild it exactly"). No Bootstrap on this shell: the preview is
            frameworkless, and layering it over Bootstrap is exactly what made
            the earlier attempts close-but-larger. See docs/PREVIEW-GAP-ANALYSIS.md.
        --}}
        @include('layouts.partials.preview-css')

        /*
            An explicit author display value overrides the browser's [hidden]
            rule, so a collapsed list rendered open (Devin msg 2273). Force hidden
            to win for both the old and the new source containers.
        */
        .source-answers[hidden], .qa-sheet[hidden] { display: none; }

        /* The review modal overlay. .modal-back sets its own display, which beats
           the [hidden] attribute, so force hidden to win here too. */
        .modal-back[hidden] { display: none; }

        /*
            Source answers, cleaned up (Devin msg 2275): the question / answer
            rows use the preview's .qa grid so each pair reads on its own line
            with a divider, not blended. Consents are collapsed to a name that
            opens the full text, with the answer shown as a pill.
        */
        .qa-sheet { margin-top: 10px; max-height: 340px; overflow-y: auto; padding-right: 4px; }
        .qa-sheet .qa { align-items: start; }
        .qa-sheet summary { cursor: pointer; font-weight: 680; color: var(--ink); list-style: revert; }
        .qa-sheet .consent-full { color: var(--muted); font-size: 12px; line-height: 1.55; margin-top: 6px; }

        /* Bootstrap badge-status overrides for clinical screens */
        .badge-status-waiting    { background:#4361ee; color:#fff; }
        .badge-status-assigned   { background:#fd7e14; color:#fff; }
        .badge-status-approved   { background:#198754; color:#fff; }
        .badge-status-processing { background:#0dcaf0; color:#000; }
        .badge-status-completed  { background:#198754; color:#fff; }
        .badge-status-cancelled  { background:#dc3545; color:#fff; }
        .badge-status-support    { background:#ffc107; color:#000; }
        .badge-status-created    { background:#6c757d; color:#fff; }

        /* Notification bell in topbar */
        .notif-bell-btn { background:none; border:none; cursor:pointer; padding:6px 8px; border-radius:8px; color:#647188; position:relative; line-height:1; transition:background .15s; }
        .notif-bell-btn:hover { background:#f1f5f9; color:#172033; }
        .notif-badge { position:absolute; top:2px; right:2px; min-width:1rem; height:1rem; padding:0 .25rem; font-size:.6rem; font-weight:700; line-height:1rem; border-radius:999px; background:#ef4444; color:#fff; text-align:center; pointer-events:none; display:none; }
        .notif-panel { position:absolute; right:0; top:calc(100% + 8px); width:360px; max-width:95vw; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.12); z-index:2000; display:none; overflow:hidden; }
        .notif-panel.open { display:block; }
        .notif-panel-header { padding:.85rem 1rem .75rem; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
        .notif-panel-header span { font-size:.9rem; font-weight:600; }
        .notif-mark-all { background:none; border:none; font-size:.75rem; color:#94a3b8; cursor:pointer; padding:0; }
        .notif-mark-all:hover { color:#475569; }
        .notif-list { max-height:360px; overflow-y:auto; }
        .notif-empty { padding:2.5rem 1rem; text-align:center; color:#94a3b8; font-size:.85rem; }
        .notif-item { display:flex; align-items:flex-start; gap:.75rem; padding:.75rem 1rem; border-bottom:1px solid #f8fafc; cursor:pointer; text-decoration:none; color:inherit; transition:background .12s; }
        .notif-item:hover { background:#f8fafc; }
        .notif-item.unread { background:#eff6ff; border-left:3px solid #3b82f6; }
        .notif-icon { width:2.2rem; height:2.2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
        .notif-icon.case_assigned { background:#fef3c7; color:#b45309; }
        .notif-icon.new_message   { background:#fce7f3; color:#be185d; }
        .notif-icon.info          { background:#f1f5f9; color:#64748b; }
        .notif-title { font-size:.8rem; font-weight:600; color:#0f172a; line-height:1.3; }
        .notif-body  { font-size:.75rem; color:#64748b; margin-top:.15rem; }
        .notif-time  { font-size:.68rem; color:#94a3b8; margin-top:.25rem; }
        .notif-panel-footer { padding:.6rem 1rem; border-top:1px solid #f1f5f9; background:#f8fafc; text-align:center; }
        .notif-panel-footer a { font-size:.8rem; color:#64748b; text-decoration:none; }
        .notif-panel-footer a:hover { color:#172033; }

        @yield('page-styles')
    </style>
</head>
<body>
@php
    $u = auth()->user();
    $nav = $clinicianNav ?? ['queue'=>0,'myCases'=>0,'messages'=>0,'escalations'=>0,'support'=>0,'red'=>0,'yellow'=>0,'green'=>0];
    $initials = collect(explode(' ', $u->name ?? 'MA'))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp
<div class="app" id="app">

    <aside class="side">
        <div class="side-head">
            <div class="brand-mark">M</div>
            <div class="brand-text">MEDAXIS</div>
        </div>

        <div class="side-scroll" id="nav">
            <a class="nav-link {{ request()->routeIs('clinician.dashboard') ? 'active' : '' }}" href="{{ route('clinician.dashboard') }}">
                <span class="nav-ico">&#128202;</span><span class="lbl">Dashboard</span>
            </a>

            <div class="nav-section"><span>Tasks</span></div>
            <div class="nav-group">
                {{-- Case Queue hidden: clinicians only see cases assigned to them (My Cases) --}}
                {{-- <a class="nav-link {{ request()->routeIs('clinician.queue') || request()->routeIs('clinician.cases.queue') ? 'active' : '' }}" href="{{ route('clinician.queue') }}">
                    <span class="nav-ico">&#128451;</span><span class="lbl">Case Queue</span>
                    <span class="nav-count {{ $nav['queue'] ? '' : 'zero' }}">{{ $nav['queue'] }}</span>
                </a> --}}
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases') }}">
                    <span class="nav-ico">&#128203;</span><span class="lbl">My Cases</span>
                    <span class="nav-count {{ $nav['myCases'] ? '' : 'zero' }}">{{ $nav['myCases'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.cases.refills') ? 'active' : '' }}" href="{{ route('clinician.cases.refills') }}">
                    <span class="nav-ico">&#128260;</span><span class="lbl">Refills</span>
                    <span class="nav-count {{ ($nav['refills'] ?? 0) ? '' : 'zero' }}">{{ $nav['refills'] ?? 0 }}</span>
                </a>
                {{-- The provider pool (Devin msg 2308). A doctor asks for a
                     number of cases and the pool grants the oldest ones they are
                     eligible for. No count: they never see what is in the queue,
                     so there is no number to show them. --}}
                <a class="nav-link {{ request()->routeIs('clinician.pool.*') ? 'active' : '' }}" href="{{ route('clinician.pool.index') }}">
                    <span class="nav-ico">&#128229;</span><span class="lbl">Request Cases</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.messages.*') ? 'active' : '' }}" href="{{ route('clinician.messages.index') }}">
                    <span class="nav-ico">&#128172;</span><span class="lbl">Messages For Provider</span>
                    <span class="nav-count {{ $nav['messages'] ? '' : 'zero' }}" id="msgBadge">{{ $nav['messages'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') && request()->get('tab') === 'escalations' ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases', ['tab' => 'escalations']) }}">
                    <span class="nav-ico">&#9888;</span><span class="lbl">My Escalations</span>
                    <span class="nav-count {{ $nav['escalations'] ? '' : 'zero' }}">{{ $nav['escalations'] }}</span>
                </a>
            </div>

            <div class="nav-section"><span>Priority</span></div>
            <div class="nav-group">
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') && request()->get('tab') === 'support' ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases', ['tab' => 'support']) }}">
                    <span class="nav-ico">&#128736;</span><span class="lbl">Support thread open</span>
                    <span class="nav-count {{ $nav['support'] ? '' : 'zero' }}">{{ $nav['support'] }}</span>
                </a>
            </div>

            <div class="nav-section"><span>Triage</span></div>
            <div class="nav-group">
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') && request()->get('triage') === 'red' ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases', ['triage' => 'red']) }}">
                    <span class="nav-ico">&#128308;</span><span class="lbl">Red</span>
                    <span class="nav-count {{ $nav['red'] ? '' : 'zero' }}">{{ $nav['red'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') && request()->get('triage') === 'yellow' ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases', ['triage' => 'yellow']) }}">
                    <span class="nav-ico">&#128993;</span><span class="lbl">Yellow</span>
                    <span class="nav-count {{ $nav['yellow'] ? '' : 'zero' }}">{{ $nav['yellow'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') && request()->get('triage') === 'green' ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases', ['triage' => 'green']) }}">
                    <span class="nav-ico">&#128994;</span><span class="lbl">Green</span>
                    <span class="nav-count {{ $nav['green'] ? '' : 'zero' }}">{{ $nav['green'] }}</span>
                </a>
            </div>

            <div class="nav-section"><span>&nbsp;</span></div>
            <div class="nav-group">
                <a class="nav-link {{ request()->routeIs('clinician.notifications.*') ? 'active' : '' }}" href="{{ route('clinician.notifications.index') }}">
                    <span class="nav-ico">&#128276;</span><span class="lbl">Notifications</span>
                </a>
            </div>
        </div>

        <div class="side-foot">
            <div class="avatar">{{ strtoupper($initials) }}</div>
            <div class="who">
                <strong>{{ $u->name ?? 'Clinician' }}</strong>
                <span>Clinician</span>
            </div>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button class="icon-btn" id="toggle" title="Collapse sidebar" aria-label="Collapse sidebar">&#9776;</button>
            <div style="font-weight:740;letter-spacing:-.01em">@yield('page-title', 'My Cases')</div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:12px;">
                {{-- Notification bell --}}
                <div style="position:relative;" id="notifWrap">
                    <button class="notif-bell-btn" id="notifBellBtn" aria-label="Notifications">
                        <i class="bi bi-bell-fill" style="font-size:1.05rem;"></i>
                        <span class="notif-badge" id="notifBadge"></span>
                    </button>
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-panel-header">
                            <span>Notifications</span>
                            <button class="notif-mark-all" id="notifMarkAll">Mark all read</button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty" id="notifEmpty">You're all caught up!</div>
                        </div>
                        <div class="notif-panel-footer">
                            <a href="{{ route('clinician.notifications.index') }}">View all notifications</a>
                        </div>
                    </div>
                </div>
                <div class="demo-banner">
                    <span class="demo-dot"></span> {{ $u->name ?? '' }}
                </div>
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button class="icon-btn" title="Log out" aria-label="Log out" style="width:auto;padding:0 12px;gap:6px">Logout</button>
                </form>
            </div>
        </div>
        <div class="wrap" id="view">
            {{-- Flash messages --}}
            @if(session('success'))
                <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;background:#eaf8ef;border:1px solid #bbf7d0;border-radius:10px;color:#166534;font-size:.875rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('info'))
                <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e40af;font-size:.875rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-info-circle-fill"></i> {{ session('info') }}
                </div>
            @endif
            {{-- 'warning' had no block here. The pool refusal path depends on it
                 reaching the doctor: "you asked for 20 and got nothing, here is
                 why" is flashed as a warning, and without this it was rendered
                 nowhere, which is the exact silent failure the pool was built to
                 avoid. --}}
            @if(session('warning'))
                <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;background:#fff7df;border:1px solid #fde68a;border-radius:10px;color:#92400e;font-size:.875rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ session('warning') }}
                </div>
            @endif
            @if(session('error'))
                <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;background:#fff0ef;border:1px solid #fecaca;border-radius:10px;color:#991b1b;font-size:.875rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;background:#fff0ef;border:1px solid #fecaca;border-radius:10px;color:#991b1b;font-size:.875rem;">
                    <ul style="margin:0;padding-left:1.25rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            {{-- Support both @section('view') and @section('content') --}}
            @yield('view')
            @yield('content')
        </div>
    </div>

</div>

{{-- Reverb / Pusher — loaded once in the layout so every page shares one connection --}}
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/2.2.4/echo.iife.min.js"></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var EchoCtor = (typeof Echo === 'object' && typeof Echo.default === 'function') ? Echo.default
                 : (typeof Echo === 'function' ? Echo : null);

    if (!EchoCtor) return;

    window.Pusher = Pusher;
    window.Echo = new EchoCtor({
        broadcaster:       'pusher',
        key:               "{{ config('reverb.apps.apps.0.key') }}",
        wsHost:            "{{ config('reverb.apps.apps.0.options.host') }}",
        wsPort:            {{ config('reverb.apps.apps.0.options.port') }},
        wssPort:           {{ config('reverb.apps.apps.0.options.port') }},
        disableStats:      true,
        forceTLS:          {{ config('reverb.apps.apps.0.options.useTLS') ? 'true' : 'false' }},
        cluster:           'mt1',
        enabledTransports: ['ws', 'wss'],
        authEndpoint:      '/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } },
    });

    // Sidebar badge: increment live when a new patient message arrives on any page.
    var msgBadge = document.getElementById('msgBadge');
    window.Echo.private('provider-inbox').listen('.NewPatientMessage', function () {
        if (!msgBadge) return;
        var n = (parseInt(msgBadge.textContent, 10) || 0) + 1;
        msgBadge.textContent = n;
        msgBadge.classList.remove('zero');
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
(function () {
    // Sidebar collapse toggle
    var app = document.getElementById('app');
    var btn = document.getElementById('toggle');
    if (btn && app) btn.addEventListener('click', function () { app.classList.toggle('collapsed'); });

    // Notification bell
    var csrf      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var bellBtn   = document.getElementById('notifBellBtn');
    var panel     = document.getElementById('notifPanel');
    var badge     = document.getElementById('notifBadge');
    var list      = document.getElementById('notifList');
    var empty     = document.getElementById('notifEmpty');
    var markAllBtn= document.getElementById('notifMarkAll');

    var iconMap = {
        case_assigned: { icon: 'bi-person-check-fill', cls: 'case_assigned' },
        new_message:   { icon: 'bi-chat-dots-fill',    cls: 'new_message'   },
        info:          { icon: 'bi-info-circle-fill',   cls: 'info'          },
    };

    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function renderNotifications(items) {
        if (!items.length) { list.innerHTML = ''; list.appendChild(empty); return; }
        var html = '';
        items.forEach(function (n) {
            var meta = iconMap[n.type] || iconMap.info;
            html += '<a href="'+esc(n.url)+'" class="notif-item'+(n.is_read?'':' unread')+'" data-id="'+esc(n.id)+'">'
                + '<div class="notif-icon '+meta.cls+'"><i class="bi '+meta.icon+'"></i></div>'
                + '<div style="flex:1;min-width:0;">'
                +   '<div class="notif-title">'+esc(n.title)+'</div>'
                +   '<div class="notif-body">'+esc(n.body)+'</div>'
                +   '<div class="notif-time">'+esc(n.time)+'</div>'
                + '</div></a>';
        });
        list.innerHTML = html;
        list.querySelectorAll('.notif-item').forEach(function (el) {
            el.addEventListener('click', function () {
                if (el.classList.contains('unread')) {
                    fetch('/clinician/notifications/'+el.dataset.id+'/read', {
                        method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
                    }).then(function(){ el.classList.remove('unread'); refreshBadge(); });
                }
            });
        });
    }

    function refreshBadge() {
        fetch('/clinician/notifications', { headers:{'Accept':'application/json'} })
            .then(function(r){ return r.json(); })
            .then(function(data){
                var count = data.unread || 0;
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = count > 0 ? 'block' : 'none';
                renderNotifications(data.notifications || []);
            }).catch(function(){});
    }

    if (bellBtn && panel) {
        bellBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.classList.toggle('open');
            if (panel.classList.contains('open')) refreshBadge();
        });
        document.addEventListener('click', function (e) {
            if (!document.getElementById('notifWrap').contains(e.target)) panel.classList.remove('open');
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('/clinician/notifications/read-all', {
                method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
            }).then(function(){
                list.querySelectorAll('.notif-item.unread').forEach(function(el){ el.classList.remove('unread'); });
                badge.style.display = 'none';
            });
        });
    }

    refreshBadge();
    setInterval(refreshBadge, 60000);
})();
</script>
@yield('scripts')
</body>
</html>
