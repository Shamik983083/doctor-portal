@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')

@php
$cards = [
    ['label' => 'Partners',        'value' => $stats['partners'],        'icon' => 'building',        'border' => '#0d6efd', 'text' => 'text-primary',   'link' => route('admin.partners.index')],
    ['label' => 'Patients',        'value' => $stats['patients'],        'icon' => 'people',          'border' => '#0dcaf0', 'text' => 'text-info',      'link' => route('admin.patients.index')],
    ['label' => 'Active Cases',    'value' => $stats['active_cases'],    'icon' => 'folder2-open',    'border' => '#ffc107', 'text' => 'text-warning',   'link' => route('admin.cases.index')],
    ['label' => 'Clinicians',      'value' => $stats['clinicians'],      'icon' => 'person-badge',    'border' => '#198754', 'text' => 'text-success',   'link' => route('admin.clinicians.index')],
    ['label' => 'Orders Today',    'value' => $stats['orders_today'],    'icon' => 'cart-check',      'border' => '#6c757d', 'text' => 'text-secondary', 'link' => null],
    ['label' => 'Completed Today', 'value' => $stats['completed_today'], 'icon' => 'clipboard-check', 'border' => '#212529', 'text' => 'text-dark',      'link' => route('admin.cases.index') . '?status=completed'],
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
        @if($card['link'])
        <a href="{{ $card['link'] }}" class="text-decoration-none">
        @endif
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
        @if($card['link'])
        </a>
        @endif
    </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Cases by Status</h6></div>
            <div class="card-body">
                @forelse($casesByStatus as $status => $count)
                @php
                    $barColor = $statusBarColors[$status] ?? 'bg-secondary';
                    $barWidth = $count ? min(100, $count * 10) : 5;
                @endphp
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge badge-status-{{ $status }}">{{ ucfirst($status) }}</span>
                    <div class="d-flex align-items-center gap-2 flex-grow-1 ms-2">
                        <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar {{ $barColor }}" data-width="{{ $barWidth }}"></div>
                        </div>
                        <strong class="text-muted small" style="min-width: 28px; text-align: right;">{{ $count }}</strong>
                    </div>
                </div>
                @empty
                <p class="text-muted small mb-0">No cases yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Cases</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-warning">All Cases</a>
                    <a href="{{ route('admin.patients.index') }}" class="btn btn-sm btn-outline-info">Patients</a>
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
                            <tr style="cursor:pointer" onclick="window.location='{{ route('admin.cases.show', $case->uuid) }}'">
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

@section('scripts')
<script>
document.querySelectorAll('[data-width]').forEach(function(el) {
    el.style.width = el.dataset.width + '%';
});
</script>
@endsection
