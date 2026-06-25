@extends('layouts.partner')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $statCards = [
        ['label' => 'Active Offerings', 'value' => $stats['offerings'],  'icon' => 'box-seam',      'color' => '#0d47a1'],
        ['label' => 'Total Patients',   'value' => $stats['patients'],   'icon' => 'people',         'color' => '#1565c0'],
        ['label' => 'Open Cases',       'value' => $stats['open_cases'], 'icon' => 'folder2-open',   'color' => '#e65100'],
        ['label' => 'Completed Cases',  'value' => $stats['completed'],  'icon' => 'check-circle',   'color' => '#2e7d32'],
    ];
@endphp

<div class="row g-3 mb-4">
    @foreach($statCards as $card)
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                @php $bg = $card['color']; @endphp
                <div class="rounded-3 d-flex align-items-center justify-content-center"
                     style="width:48px;height:48px;background:{{ $bg }}1a">
                    <i class="bi bi-{{ $card['icon'] }} fs-4" style="color:{{ $bg }}"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1">{{ $card['value'] }}</div>
                    <div class="text-muted small">{{ $card['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-semibold">Recent Cases</h6>
        <a href="{{ route('partner.cases.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Case ID</th>
                    <th>Patient</th>
                    <th>Offerings</th>
                    <th>Clinician</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentCases as $case)
                <tr>
                    <td><code class="text-primary">{{ substr($case->uuid, 0, 8) }}…</code></td>
                    <td>{{ $case->patient->first_name ?? '—' }} {{ $case->patient->last_name ?? '' }}</td>
                    <td>
                        @foreach($case->caseOfferings->take(2) as $co)
                            <span class="badge bg-light text-dark border">{{ $co->offering->name }}</span>
                        @endforeach
                        @if($case->caseOfferings->count() > 2)
                            <span class="badge bg-secondary">+{{ $case->caseOfferings->count() - 2 }}</span>
                        @endif
                    </td>
                    <td>{{ $case->clinician?->user->name ?? '<span class="text-muted">Unassigned</span>' }}</td>
                    <td>
                        <span class="badge badge-{{ $case->status }}">{{ ucfirst($case->status) }}</span>
                    </td>
                    <td class="text-muted small">{{ $case->created_at->diffForHumans() }}</td>
                    <td>
                        <a href="{{ route('partner.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No cases yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
