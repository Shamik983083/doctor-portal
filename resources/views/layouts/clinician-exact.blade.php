<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MEDAXIS')</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='sans-serif' font-size='17' font-weight='bold' fill='white' text-anchor='middle'%3EM%3C/text%3E%3C/svg%3E" />
    <style>
        {{--
            The design preview's stylesheet, lifted verbatim (Devin msg 2265:
            "rebuild it exactly"). No Bootstrap on this shell: the preview is
            frameworkless, and layering it over Bootstrap is exactly what made
            the earlier attempts close-but-larger. See docs/PREVIEW-GAP-ANALYSIS.md.
        --}}
        @include('layouts.partials.preview-css')

        /*
            The preview's .source-answers sets display:grid, and an explicit
            author display value overrides the browser's [hidden] rule, so the
            "View source answers" list rendered expanded and the collapse did
            nothing (Devin msg 2273). This makes the hidden attribute win, so it
            starts collapsed and only opens on click.
        */
        .source-answers[hidden] { display: none; }
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
                <a class="nav-link {{ request()->routeIs('clinician.queue') || (request()->routeIs('clinician.cases.*') && !request()->routeIs('clinician.cases.my-cases')) ? 'active' : '' }}" href="{{ route('clinician.queue') }}">
                    <span class="nav-ico">&#128451;</span><span class="lbl">Case Queue</span>
                    <span class="nav-count {{ $nav['queue'] ? '' : 'zero' }}">{{ $nav['queue'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.cases.my-cases') ? 'active' : '' }}" href="{{ route('clinician.cases.my-cases') }}">
                    <span class="nav-ico">&#128203;</span><span class="lbl">My Cases</span>
                    <span class="nav-count {{ $nav['myCases'] ? '' : 'zero' }}">{{ $nav['myCases'] }}</span>
                </a>
                <a class="nav-link {{ request()->routeIs('clinician.messages.*') ? 'active' : '' }}" href="{{ route('clinician.messages.index') }}">
                    <span class="nav-ico">&#128172;</span><span class="lbl">Messages For Provider</span>
                    <span class="nav-count {{ $nav['messages'] ? '' : 'zero' }}">{{ $nav['messages'] }}</span>
                </a>
                <a class="nav-link" href="{{ route('clinician.queue') }}?status=support">
                    <span class="nav-ico">&#9888;</span><span class="lbl">My Escalations</span>
                    <span class="nav-count {{ $nav['escalations'] ? '' : 'zero' }}">{{ $nav['escalations'] }}</span>
                </a>
            </div>

            <div class="nav-section"><span>Priority</span></div>
            <div class="nav-group">
                <a class="nav-link" href="{{ route('clinician.queue') }}?status=support">
                    <span class="nav-ico">&#128736;</span><span class="lbl">Support thread open</span>
                    <span class="nav-count {{ $nav['support'] ? '' : 'zero' }}">{{ $nav['support'] }}</span>
                </a>
            </div>

            <div class="nav-section"><span>Triage</span></div>
            <div class="nav-group">
                <a class="nav-link" href="{{ route('clinician.queue') }}?triage=red">
                    <span class="nav-ico">&#128308;</span><span class="lbl">Red</span>
                    <span class="nav-count {{ $nav['red'] ? '' : 'zero' }}">{{ $nav['red'] }}</span>
                </a>
                <a class="nav-link" href="{{ route('clinician.queue') }}?triage=yellow">
                    <span class="nav-ico">&#128993;</span><span class="lbl">Yellow</span>
                    <span class="nav-count {{ $nav['yellow'] ? '' : 'zero' }}">{{ $nav['yellow'] }}</span>
                </a>
                <a class="nav-link" href="{{ route('clinician.queue') }}?triage=green">
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
            <div style="font-weight:740;letter-spacing:-.01em">@yield('page-title', 'Case Queue')</div>
            <div class="demo-banner" style="margin-left:auto">
                <span class="demo-dot"></span> {{ $u->name ?? '' }}
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button class="icon-btn" title="Log out" aria-label="Log out" style="width:auto;padding:0 12px;gap:6px">Logout</button>
            </form>
        </div>
        <div class="wrap" id="view">
            @yield('view')
        </div>
    </div>

</div>

<script>
    // Sidebar collapse toggle, same behaviour as the preview.
    (function () {
        var app = document.getElementById('app');
        var btn = document.getElementById('toggle');
        if (btn && app) btn.addEventListener('click', function () { app.classList.toggle('collapsed'); });
    })();
</script>
@yield('scripts')
</body>
</html>
