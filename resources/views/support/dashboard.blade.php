@extends('layouts.support')

@section('title', 'Support Dashboard')
@section('page-title', 'Support Dashboard')

@section('content')

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:44px;height:44px;background:#fff3cd;flex-shrink:0;">
                        <i class="bi bi-headset text-warning fs-5"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1">{{ $supportCount }}</div>
                        <div class="text-muted small">Cases awaiting support</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <a href="{{ route('support.cases.index') }}" class="btn btn-warning btn-sm w-100">
                    View Support Queue
                </a>
            </div>
        </div>
    </div>
</div>

@if($supportCount === 0)
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
        <strong>All clear</strong> — no cases are in the support queue right now.
    </div>
</div>
@endif

@endsection
