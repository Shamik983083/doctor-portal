@extends('layouts.admin')

@section('title', $patient->full_name)
@section('page-title', $patient->full_name)

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('admin.patients.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Patients
    </a>
    <form method="POST" action="{{ route('admin.patients.destroy', $patient->id) }}" onsubmit="return confirm('Are you sure you want to delete this patient? This cannot be undone.')" class="d-inline">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete Patient</button>
    </form>
</div>

<div class="row g-4">

    {{-- Left: Patient Info --}}
    <div class="col-lg-4">

        {{-- Personal Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-person-circle me-2"></i>Patient Info</h6>
                <span class="badge {{ match($patient->status) { 'active' => 'bg-success', 'inactive' => 'bg-secondary', default => 'bg-warning text-dark' } }}">
                    {{ ucfirst($patient->status ?? 'active') }}
                </span>
            </div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Full Name</th><td>{{ $patient->full_name }}</td></tr>
                    <tr><th class="text-muted">Email</th><td>{{ $patient->email }}</td></tr>
                    <tr><th class="text-muted">Phone</th><td>{{ $patient->phone ?? '—' }}</td></tr>
                    <tr><th class="text-muted">DOB</th><td>{{ $patient->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Gender</th><td>{{ ucfirst($patient->gender ?? '—') }}</td></tr>
                    <tr>
                        <th class="text-muted">Height</th>
                        <td>{{ $patient->height ? (int)floor($patient->height/12)."' ".round(fmod($patient->height,12)).'"' : '—' }}</td>
                    </tr>
                    <tr><th class="text-muted">Weight</th><td>{{ $patient->weight ? number_format($patient->weight,1).' lbs' : '—' }}</td></tr>
                    <tr><th class="text-muted">BMI</th><td>{{ $patient->bmi ? number_format($patient->bmi,1) : '—' }}</td></tr>
                    <tr><th class="text-muted">State</th><td>{{ $patient->state ?? '—' }}</td></tr>
                    <tr><th class="text-muted">City</th><td>{{ $patient->city ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Zip</th><td>{{ $patient->zip ?? '—' }}</td></tr>
                    @if($patient->address)
                    <tr><th class="text-muted">Address</th><td>{{ $patient->address }}</td></tr>
                    @endif
                    <tr><th class="text-muted">Partner</th><td>{{ $patient->partner->name ?? '—' }}</td></tr>
                    @if($patient->external_id)
                    <tr><th class="text-muted">External ID</th><td><small class="font-monospace">{{ $patient->external_id }}</small></td></tr>
                    @endif
                    <tr><th class="text-muted">UUID</th><td><small class="font-monospace text-muted">{{ substr($patient->uuid, 0, 16) }}…</small></td></tr>
                    <tr><th class="text-muted">Joined</th><td>{{ $patient->created_at->format('M d, Y') }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Tags --}}
        @if($patient->tags->count())
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-tags me-2"></i>Tags</h6></div>
            <div class="card-body">
                @foreach($patient->tags as $tag)
                    <span class="badge me-1 mb-1" style="background-color: {{ $tag->color ?? '#6c757d' }}">{{ $tag->name }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Orders summary --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-cart me-2"></i>Orders ({{ $patient->orders->count() }})</h6></div>
            @if($patient->orders->count())
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Order</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @foreach($patient->orders->take(5) as $order)
                            <tr>
                                <td><small class="font-monospace">{{ substr($order->uuid ?? '—', 0, 8) }}</small></td>
                                <td><span class="badge bg-secondary">{{ ucfirst($order->status ?? '—') }}</span></td>
                                <td><small>{{ $order->created_at->format('M d') }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
            <div class="card-body"><p class="text-muted small mb-0">No orders yet.</p></div>
            @endif
        </div>
    </div>

    {{-- Right: Cases --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Cases ({{ $patient->cases->count() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Offerings</th>
                                <th>Clinician</th>
                                <th>Status</th>
                                <th>Support</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($patient->cases as $case)
                            <tr>
                                <td><small class="font-monospace text-muted">{{ substr($case->uuid, 0, 8) }}</small></td>
                                <td>
                                    @foreach($case->caseOfferings->take(2) as $co)
                                        <span class="badge bg-light text-dark border small">{{ $co->offering->name ?? '?' }}</span>
                                    @endforeach
                                </td>
                                <td><small>{{ $case->clinician?->full_name ?? '—' }}</small></td>
                                <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                                <td>
                                    @if($case->support_at)
                                        <i class="bi bi-check-circle-fill text-warning" title="{{ $case->support_at->format('M d H:i') }}"></i>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $case->created_at->format('M d, Y') }}</small></td>
                                <td>
                                    <a href="{{ route('admin.cases.show', $case->uuid) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-folder2-open fs-2 d-block mb-2"></i>
                                    No cases yet.<br>
                                    <small>Cases are created when a partner submits them via the API.</small>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
