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
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a2035; color: #ccc; }
        .sidebar .nav-link { color: #adb5bd; padding: .5rem 1rem; border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.1); color: #fff; }
        .sidebar .nav-link i { width: 20px; }
        .sidebar-brand { font-size: 1.1rem; font-weight: 700; color: #fff; padding: 1.5rem 1rem; display: block; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.1); }
        .main-content { min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e9ecef; padding: .75rem 1.5rem; }
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
