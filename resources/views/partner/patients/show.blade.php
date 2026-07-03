@extends('layouts.partner')
@php $title = "{$patient->first_name} {$patient->last_name}"; @endphp
@section('title', $title)
@section('page-title', $title)

@section('content')
<div class="mb-4">
    <a href="{{ route('partner.patients.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Patients
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Patient Info</h6></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Name</dt>
                    <dd class="col-7">{{ $patient->first_name }} {{ $patient->last_name }}</dd>

                    <dt class="col-5 text-muted">Email</dt>
                    <dd class="col-7">{{ $patient->email ?? '—' }}</dd>

                    <dt class="col-5 text-muted">Phone</dt>
                    <dd class="col-7">{{ $patient->phone ?? '—' }}</dd>

                    <dt class="col-5 text-muted">DOB</dt>
                    <dd class="col-7">{{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('M j, Y') : '—' }}</dd>

                    <dt class="col-5 text-muted">Gender</dt>
                    <dd class="col-7">{{ $patient->gender ? ucfirst($patient->gender) : '—' }}</dd>

                    <dt class="col-5 text-muted">Height</dt>
                    <dd class="col-7">{{ $patient->height ? (int)floor($patient->height/12)."' ".round(fmod($patient->height,12)).'"' : '—' }}</dd>

                    <dt class="col-5 text-muted">Weight</dt>
                    <dd class="col-7">{{ $patient->weight ? number_format($patient->weight,1).' lbs' : '—' }}</dd>

                    <dt class="col-5 text-muted">BMI</dt>
                    <dd class="col-7">{{ $patient->bmi ? number_format($patient->bmi,1) : '—' }}</dd>

                    <dt class="col-5 text-muted">State</dt>
                    <dd class="col-7">{{ $patient->state ?? '—' }}</dd>

                    <dt class="col-5 text-muted">External ID</dt>
                    <dd class="col-7"><code>{{ $patient->external_id ?? '—' }}</code></dd>

                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7">
                        @if($patient->status === 'active')
                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">{{ ucfirst($patient->status) }}</span>
                        @endif
                    </dd>

                    <dt class="col-5 text-muted">Since</dt>
                    <dd class="col-7">{{ $patient->created_at->format('M j, Y') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Cases ({{ $patient->cases_count }})</h6></div>
            <div class="card-body p-0">
                @forelse($patient->cases as $case)
                <a href="{{ route('partner.cases.show', $case->uuid) }}"
                   class="d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none border-bottom hover-bg">
                    <div>
                        <code class="text-primary small">{{ substr($case->uuid, 0, 8) }}…</code>
                        <div class="text-muted x-small">{{ $case->created_at->format('M j, Y') }}</div>
                    </div>
                    <span class="badge badge-{{ $case->status }}">{{ ucfirst($case->status) }}</span>
                </a>
                @empty
                <div class="text-center text-muted py-4 small">No cases yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Orders</h6></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Pharmacy</th>
                            <th>Status</th>
                            <th>Tracking</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($patient->orders as $order)
                        <tr>
                            <td><code>{{ $order->id }}</code></td>
                            <td>{{ $order->pharmacy?->name ?? '—' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $order->status }}</span></td>
                            <td>{{ $order->tracking_number ?? '—' }}</td>
                            <td>{{ $order->created_at->format('M j, Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
