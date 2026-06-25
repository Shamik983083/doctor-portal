@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')

@php
$cards = [
    ['label' => 'Partners',        'value' => $stats['partners'],        'icon' => 'building',        'border' => '#0d6efd', 'text' => 'text-primary'],
    ['label' => 'Patients',        'value' => $stats['patients'],        'icon' => 'people',          'border' => '#0dcaf0', 'text' => 'text-info'],
    ['label' => 'Active Cases',    'value' => $stats['active_cases'],    'icon' => 'folder2-open',    'border' => '#ffc107', 'text' => 'text-warning'],
    ['label' => 'Clinicians',      'value' => $stats['clinicians'],      'icon' => 'person-badge',    'border' => '#198754', 'text' => 'text-success'],
    ['label' => 'Orders Today',    'value' => $stats['orders_today'],    'icon' => 'cart-check',      'border' => '#6c757d', 'text' => 'text-secondary'],
    ['label' => 'Completed Today', 'value' => $stats['completed_today'], 'icon' => 'clipboard-check', 'border' => '#212529', 'text' => 'text-dark'],
];

$statusBarColors = [
    'completed'  => 'bg-success',
    'cancelled'  => 'bg-danger',
    'approved'   => 'bg-success',
    'waiting'    => 'bg-primary',
    'assigned'   => 'bg-warning',
    'processing' => 'bg-info',
];
@endphp

<div class="row g-3 mb-4">
    @foreach($cards as $card)
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card h-100" style="border-left-color: {{ $card['border'] }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                        <h3 class="fw-bold {{ $card['text'] }}">{{ $card['value'] }}</h3>
                    </div>
                    <i class="bi bi-{{ $card['icon'] }} fs-2 {{ $card['text'] }} opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Cases by Status</h6></div>
            <div class="card-body">
                @foreach($casesByStatus as $status => $count)
                @php
                    $barColor = $statusBarColors[$status] ?? 'bg-secondary';
                    $barWidth = $count ? min(100, $count * 10) : 5;
                @endphp
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge badge-status-{{ $status }}">{{ ucfirst($status) }}</span>
                    <div class="d-flex align-items-center gap-2 flex-grow-1 ms-2">
                        <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar {{ $barColor }}" style="width: {{ $barWidth }}%"></div>
                        </div>
                        <strong class="text-muted small" style="min-width: 28px; text-align: right;">{{ $count }}</strong>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Cases</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.partners.index') }}" class="btn btn-sm btn-outline-primary">Partners</a>
                    <a href="{{ route('admin.clinicians.index') }}" class="btn btn-sm btn-outline-success">Clinicians</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Partner</th>
                                <th>Clinician</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentCases as $case)
                            <tr>
                                <td>{{ $case->patient->full_name ?? '—' }}</td>
                                <td>{{ $case->partner->name ?? '—' }}</td>
                                <td>{{ $case->clinician?->full_name ?? '—' }}</td>
                                <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                                <td>{{ $case->created_at->diffForHumans() }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No cases yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
