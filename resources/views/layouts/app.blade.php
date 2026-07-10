<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Doctor Portal')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f1f5f9; }
        .sidebar { min-height: 100vh; background: #0f172a; color: #94a3b8; z-index: 1030; }
        .sidebar .nav-link { color: #94a3b8; padding: .38rem .9rem; border-radius: 5px; margin: 1px 8px; font-size: .82rem; font-weight: 500; transition: background .15s, color .15s; border-left: 2px solid transparent; display: flex; align-items: center; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #e2e8f0; }
        .sidebar .nav-link.active { background: rgba(59,130,246,.14); color: #60a5fa; border-left-color: #3b82f6; }
        .sidebar .nav-link i { width: 18px; text-align: center; margin-right: .4rem; font-size: .88rem; flex-shrink: 0; }
        .sidebar .nav-link.sub { font-size: .78rem; padding-left: 2.2rem; color: #64748b; }
        .sidebar .nav-link.sub:hover { color: #cbd5e1; }
        .sidebar .nav-link.sub.active { color: #60a5fa; }
        .sidebar-brand { font-size: 1rem; font-weight: 700; color: #fff; padding: 1rem; display: flex; align-items: center; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.07); }
        .sidebar-section { padding: .65rem 1.1rem .15rem; font-size: .59rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.3); font-weight: 600; display: block; }
        .sidebar hr { border-color: rgba(255,255,255,.07); margin: .3rem 0; }
        .main-content { min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e9ecef; padding: .65rem 1.5rem; }
        .stat-card { border-left: 4px solid; border-radius: 8px; }
        .badge-status-created    { background: #6c757d; }
        .badge-status-waiting    { background: #0d6efd; }
        .badge-status-assigned   { background: #fd7e14; }
        .badge-status-approved   { background: #198754; }
        .badge-status-processing { background: #0dcaf0; color:#000; }
        .badge-status-completed  { background: #198754; }
        .badge-status-cancelled  { background: #dc3545; }
        .badge-status-support    { background: #ffc107; color:#000; }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="sidebar col-auto" style="width:240px; position:fixed; top:0; bottom:0; overflow-y:auto;">
        <a class="sidebar-brand" href="/">
            <i class="bi bi-heart-pulse-fill me-2 text-danger"></i> Doctor Portal
        </a>
        @yield('sidebar-nav')
    </nav>
    <div class="main-content flex-grow-1" style="margin-left:240px;">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">@yield('page-title')</h6>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">{{ Auth::user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </div>
        <div class="p-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
