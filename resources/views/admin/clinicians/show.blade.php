@extends('layouts.admin')

@section('title', 'Clinician Profile')
@php $clinicianTitle = "Clinician: {$clinician->full_name}"; @endphp
@section('page-title', $clinicianTitle)

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;font-size:2rem;">
                    {{ substr($clinician->user->name, 0, 1) }}
                </div>
                <h5>{{ $clinician->full_name }}</h5>
                <p class="text-muted">{{ $clinician->user->email }}</p>
                <span class="badge {{ $clinician->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($clinician->status) }}</span>
            </div>
            <div class="card-body pt-0">
                <table class="table table-sm table-borderless small mb-0">
                    <tr><th>NPI</th><td><code>{{ $clinician->npi ?? '—' }}</code></td></tr>
                    <tr><th>Specialty</th><td>{{ $clinician->specialty ?? '—' }}</td></tr>
                    <tr><th>Total Cases</th><td>{{ $clinician->cases_count }}</td></tr>
                    <tr><th>Max Daily</th><td>{{ $clinician->max_daily_cases }}</td></tr>
                    <tr><th>Available</th><td>{{ $clinician->is_available ? '✅ Yes' : '❌ No' }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Licensed States</h6></div>
            @php
                $licStates = $clinician->licensed_states ?? [];
                $isRich = count($licStates) && is_array($licStates[0]);
            @endphp
            @if($isRich)
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">State</th>
                            <th>License #</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($licStates as $lic)
                        <tr>
                            <td><span class="badge bg-primary">{{ $lic['state'] }}</span></td>
                            <td><code class="small">{{ $lic['license_number'] ?? '—' }}</code></td>
                            <td>
                                @if(!empty($lic['expiry_date']))
                                    @php $exp = \Carbon\Carbon::parse($lic['expiry_date']); @endphp
                                    <span @class(['small', 'text-danger fw-semibold' => $exp->isPast(), 'text-warning fw-semibold' => !$exp->isPast() && $exp->diffInDays() < 90])>
                                        {{ $exp->format('M j, Y') }}
                                        @if($exp->isPast()) <i class="bi bi-exclamation-circle ms-1"></i>@endif
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @elseif(count($licStates))
            <div class="card-body">
                @foreach($licStates as $state)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ $state }}</span>
                @endforeach
            </div>
            @else
            <div class="card-body">
                <p class="text-muted small mb-0">No states configured.</p>
            </div>
            @endif
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent Cases</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Patient</th><th>Partner</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @forelse($clinician->cases->take(20) as $case)
                            <tr>
                                <td>{{ $case->patient->full_name ?? '—' }}</td>
                                <td>{{ $case->partner->name ?? '—' }}</td>
                                <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                                <td><small>{{ $case->created_at->diffForHumans() }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No cases yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
