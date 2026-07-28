@extends('layouts.admin')

@section('title', $partner->name . ' — Partner Dashboard')
@section('page-title', $partner->name)

@section('content')

{{-- Back + scope note --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.partner-dashboard.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>All partners
    </a>
    @unless($user->isSuperAdmin())
    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small">
        <i class="bi bi-person-lock me-1"></i>Scoped to your doctors
    </span>
    @endunless
</div>

@if($stats['open'] === 0 && $stats['completed'] === 0 && $stats['cancelled'] === 0)
{{-- Empty state: partner exists but no cases in this admin's scope --}}
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
        @if($user->isSuperAdmin())
            No cases recorded for {{ $partner->name }} yet.
        @else
            No cases from {{ $partner->name }} are visible in your scope.
            Cases appear here once one of your doctors is assigned to them.
        @endif
    </div>
</div>
@else

{{-- ── Stat cards ─────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    {{-- Open --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary">{{ number_format($stats['open']) }}</div>
                <div class="text-muted small">Open</div>
            </div>
        </div>
    </div>
    {{-- Waiting --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold">{{ number_format($stats['waiting']) }}</div>
                <div class="text-muted small">Waiting</div>
            </div>
        </div>
    </div>
    {{-- Assigned --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success">{{ number_format($stats['assigned']) }}</div>
                <div class="text-muted small">Assigned</div>
            </div>
        </div>
    </div>
    {{-- Support escalations --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 {{ $stats['support'] > 0 ? 'border-warning' : '' }}">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold {{ $stats['support'] > 0 ? 'text-warning' : '' }}">
                    {{ number_format($stats['support']) }}
                </div>
                <div class="text-muted small">Support</div>
            </div>
        </div>
    </div>
    {{-- Completed --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success">{{ number_format($stats['completed']) }}</div>
                <div class="text-muted small">Completed</div>
            </div>
        </div>
    </div>
    {{-- Cancelled --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-danger">{{ number_format($stats['cancelled']) }}</div>
                <div class="text-muted small">Cancelled</div>
            </div>
        </div>
    </div>
    {{-- First visits --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold">{{ number_format($stats['first_visits']) }}</div>
                <div class="text-muted small">First Visits</div>
            </div>
        </div>
    </div>
    {{-- Refills --}}
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold">{{ number_format($stats['refills']) }}</div>
                <div class="text-muted small">Check-ins</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">

    {{-- ── Provider workload ─────────────────────────────────────────────── --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Provider Workload</h6>
                <div class="text-muted" style="font-size:.75rem;">Active cases for this partner</div>
            </div>
            <div class="card-body p-0">
                @if($providerLoads->isEmpty())
                    <p class="text-muted text-center small py-4">No active assignments.</p>
                @else
                    @foreach($providerLoads as $prov)
                    @php
                        $pct = $prov['cap'] > 0
                            ? min(100, (int) round($prov['active'] / $prov['cap'] * 100))
                            : 0;
                        $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                    @endphp
                    <div class="px-3 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold">{{ $prov['name'] }}</span>
                            <span class="small text-muted">
                                {{ $prov['active'] }}{{ $prov['cap'] > 0 ? ' / ' . $prov['cap'] : '' }}
                            </span>
                        </div>
                        @if($prov['cap'] > 0)
                        <div class="progress" style="height:4px;">
                            <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%"></div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- ── Cases by status breakdown ──────────────────────────────────────── --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Cases by Status</h6>
            </div>
            <div class="card-body">
                @php
                    $total = $byStatus->sum();
                    $statusColors = [
                        'waiting'    => '#6366f1',
                        'assigned'   => '#22c55e',
                        'support'    => '#f59e0b',
                        'approved'   => '#3b82f6',
                        'processing' => '#8b5cf6',
                        'completed'  => '#10b981',
                        'cancelled'  => '#ef4444',
                        'created'    => '#94a3b8',
                    ];
                @endphp
                @if($total === 0)
                    <p class="text-muted text-center small py-4">No cases yet.</p>
                @else
                    {{-- Stacked bar --}}
                    <div class="d-flex rounded overflow-hidden mb-3" style="height:20px;">
                        @foreach($byStatus as $status => $count)
                        @if($count > 0)
                        <div title="{{ ucfirst($status) }}: {{ $count }}"
                             style="width:{{ round($count / $total * 100, 1) }}%;background:{{ $statusColors[$status] ?? '#94a3b8' }};">
                        </div>
                        @endif
                        @endforeach
                    </div>
                    {{-- Legend --}}
                    <div class="row g-2">
                        @foreach($byStatus as $status => $count)
                        @if($count > 0)
                        <div class="col-6 col-md-4 col-xl-3">
                            <div class="d-flex align-items-center gap-2 small">
                                <span class="rounded-circle flex-shrink-0"
                                      style="width:10px;height:10px;background:{{ $statusColors[$status] ?? '#94a3b8' }};display:inline-block;"></span>
                                <span class="text-muted text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                <span class="fw-semibold ms-auto">{{ number_format($count) }}</span>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ── Recent cases ─────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent Cases</h6>
        <a href="{{ route('admin.cases.index', ['partner_id' => $partner->id]) }}"
           class="btn btn-sm btn-outline-secondary py-0 px-2 small">
            View all <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Offering(s)</th>
                        <th>Clinician</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($recentCases as $case)
                    <tr>
                        <td class="fw-semibold">
                            {{ $case->patient?->first_name }} {{ $case->patient?->last_name }}
                        </td>
                        <td class="text-muted small">
                            {{ $case->caseOfferings->pluck('offering.name')->filter()->join(', ') ?: '—' }}
                        </td>
                        <td class="text-muted small">
                            {{ $case->clinician?->user?->name ?? '—' }}
                        </td>
                        <td>
                            <span class="badge badge-status-{{ $case->status }}">{{ $case->status }}</span>
                        </td>
                        <td class="text-muted small">
                            {{ $case->updated_at->format('d M Y') }}
                        </td>
                        <td>
                            <a href="{{ route('admin.cases.show', $case->uuid) }}"
                               class="btn btn-sm btn-outline-secondary py-0 px-2">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4 small">No cases found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endif

@endsection
