@extends('layouts.admin')

@section('title', 'Partner Dashboard')
@section('page-title', 'Partner Dashboard')

@section('content')

<p class="text-muted small mb-4">
    Case activity by sub-storefront
    @unless(auth()->user()->isSuperAdmin())
        — scoped to your doctors
    @endunless
    . Select a sub-storefront to see the full breakdown.
</p>

@if($subStorefronts->isEmpty() && $directPartners->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-building fs-2 d-block mb-2 opacity-25"></i>
            @if(auth()->user()->isSuperAdmin())
                No cases recorded yet.
            @else
                No cases are visible in your scope yet.
            @endif
        </div>
    </div>
@else
    <div class="row g-3">

        {{-- Sub-storefront cards --}}
        @foreach($subStorefronts as $ss)
        @php
            $open    = (int) ($openCounts[$ss->id]    ?? 0);
            $support = (int) ($supportCounts[$ss->id] ?? 0);
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $support > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-0 fw-semibold">{{ $ss->name }}</h6>
                            <div class="text-muted" style="font-size:.75rem;">{{ $ss->partner?->name }}</div>
                            <span class="badge mt-1 {{ ($ss->status ?? 'active') === 'active' ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-secondary bg-opacity-10 text-secondary border' }}"
                                  style="font-size:.65rem;">
                                {{ ucfirst($ss->status ?? 'active') }}
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold fs-4 lh-1">{{ number_format($open) }}</div>
                            <div class="text-muted" style="font-size:.72rem;">open cases</div>
                        </div>
                    </div>

                    @if($support > 0)
                    <div class="alert alert-warning py-1 px-2 mb-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        {{ $support }} escalated to support
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
                    <a href="{{ route('admin.partner-dashboard.show-sub', $ss->id) }}"
                       class="btn btn-sm btn-outline-primary w-100">
                        View Dashboard <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Direct (no sub-storefront) cards --}}
        @foreach($directPartners as $partner)
        @php
            $open    = (int) ($directOpenCounts[$partner->id]    ?? 0);
            $support = (int) ($directSupportCounts[$partner->id] ?? 0);
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $support > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-0 fw-semibold">Direct</h6>
                            <div class="text-muted" style="font-size:.75rem;">{{ $partner->name }}</div>
                            <span class="badge mt-1 {{ ($partner->status ?? 'active') === 'active' ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-secondary bg-opacity-10 text-secondary border' }}"
                                  style="font-size:.65rem;">
                                {{ ucfirst($partner->status ?? 'active') }}
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold fs-4 lh-1">{{ number_format($open) }}</div>
                            <div class="text-muted" style="font-size:.72rem;">open cases</div>
                        </div>
                    </div>

                    @if($support > 0)
                    <div class="alert alert-warning py-1 px-2 mb-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        {{ $support }} escalated to support
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
                    <a href="{{ route('admin.partner-dashboard.show', [$partner->id, 'direct' => 1]) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        View Dashboard <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        @endforeach

    </div>
@endif

@endsection
