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
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body p-0">

                {{-- Avatar + name header --}}
                <div class="px-3 pt-3 pb-3 border-bottom d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span style="font-size:1rem;font-weight:700;color:#4f46e5;letter-spacing:-.02em">
                                {{ strtoupper(substr($patient->full_name, 0, 1)) }}{{ strtoupper(substr(strrchr($patient->full_name, ' ') ?: '', 1, 1)) }}
                            </span>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:.95rem;line-height:1.2">{{ $patient->full_name }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $patient->email }}</div>
                        </div>
                    </div>
                    @php $statusBadge = match($patient->status ?? 'active') { 'active' => 'bg-success', 'inactive' => 'bg-secondary', default => 'bg-warning text-dark' }; @endphp
                    <span class="badge flex-shrink-0 {{ $statusBadge }}">
                        {{ ucfirst($patient->status ?? 'active') }}
                    </span>
                </div>

                {{-- Contact & demographics --}}
                <div class="px-3 py-2" style="font-size:.82rem">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Phone</span>
                        <span>{{ $patient->phone ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">DOB</span>
                        <span>{{ $patient->date_of_birth?->format('M d, Y') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Gender</span>
                        <span>{{ ucfirst($patient->gender ?? '—') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">State</span>
                        <span>{{ implode(', ', array_filter([$patient->state, $patient->city, $patient->zip])) ?: '—' }}</span>
                    </div>
                    @if($patient->address)
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Address</span>
                        <span class="text-end" style="max-width:60%">{{ $patient->address }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Partner</span>
                        <span>{{ $patient->partner->name ?? '—' }}</span>
                    </div>
                    @php $latestSubSf = $patient->cases->first()?->subStorefront; @endphp
                    @if($latestSubSf)
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Sub-storefront</span>
                        <span class="text-end" style="max-width:60%">{{ $latestSubSf->name }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between mb-0">
                        <span class="text-muted">Joined</span>
                        <span>{{ $patient->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                {{-- Biometric stat strip --}}
                <div class="border-top border-bottom mx-0 px-3 py-2">
                    <div class="row g-0 text-center" style="font-size:.82rem">
                        <div class="col-4">
                            <div class="fw-semibold">{{ $patient->height ? (int)floor($patient->height/12)."'".round(fmod($patient->height,12)).'"' : '—' }}</div>
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.06em">Height</div>
                        </div>
                        <div class="col-4 border-start border-end">
                            <div class="fw-semibold">{{ $patient->weight ? number_format($patient->weight,1) : '—' }}</div>
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.06em">lbs</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-semibold">{{ $patient->bmi ? number_format($patient->bmi,1) : '—' }}</div>
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.06em">BMI</div>
                        </div>
                    </div>
                </div>

                {{-- Identifiers --}}
                <div class="px-3 py-2" style="font-size:.75rem">
                    @if($patient->external_id)
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">External ID</span>
                        <span class="font-monospace text-body-secondary">{{ $patient->external_id }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">UUID</span>
                        <span class="font-monospace text-body-secondary">{{ substr($patient->uuid, 0, 16) }}…</span>
                    </div>
                </div>

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

        {{-- E19: Collaborating Clinician --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Collaborating Clinician</h6></div>
            <div class="card-body">
                @if($patient->collaboratingClinician)
                    <p class="mb-2 small">
                        <strong>{{ $patient->collaboratingClinician->full_name }}</strong>
                        @if($patient->collaboratingClinician->license_state)
                            <span class="text-muted">({{ $patient->collaboratingClinician->license_state }})</span>
                        @endif
                    </p>
                @else
                    <p class="text-muted small mb-2">No collaborating clinician assigned.</p>
                @endif
                <form method="POST" action="{{ route('admin.patients.collaborating-clinician.update', $patient->id) }}">
                    @csrf
                    @method('PATCH')
                    <div class="input-group input-group-sm">
                        <select name="collaborating_clinician_id" class="form-select form-select-sm">
                            <option value="">— None —</option>
                            @foreach($clinicians as $c)
                                <option value="{{ $c->id }}" {{ $patient->collaborating_clinician_id == $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Orders summary --}}
        {{-- @todo un-comment when orders are wired up
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
        --}}
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
                                <th>Sub-storefront</th>
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
                                <td><small class="text-muted">{{ $case->subStorefront?->name ?? '—' }}</small></td>
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
                                <td colspan="8" class="text-center text-muted py-5">
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
