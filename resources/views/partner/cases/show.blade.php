@extends('layouts.partner')
@php $title = 'Case ' . substr($case->uuid, 0, 8) . '…'; @endphp
@section('title', $title)
@section('page-title', $title)

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <a href="{{ route('partner.cases.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Cases
    </a>
    <span class="badge badge-{{ $case->status }} fs-6">{{ ucfirst($case->status) }}</span>
</div>

<div class="row g-4">
    <!-- Left: case summary -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Case Details</h6></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">UUID</dt>
                    <dd class="col-7"><code class="small">{{ $case->uuid }}</code></dd>

                    @if($case->external_id)
                    <dt class="col-5 text-muted">External ID</dt>
                    <dd class="col-7"><code>{{ $case->external_id }}</code></dd>
                    @endif

                    <dt class="col-5 text-muted">Patient</dt>
                    <dd class="col-7">
                        <a href="{{ route('partner.patients.show', $case->patient->id) }}" class="text-decoration-none">
                            {{ $case->patient->first_name }} {{ $case->patient->last_name }}
                        </a>
                    </dd>

                    <dt class="col-5 text-muted">Clinician</dt>
                    <dd class="col-7">{{ $case->clinician?->user->name ?? 'Unassigned' }}</dd>

                    <dt class="col-5 text-muted">Created</dt>
                    <dd class="col-7">{{ $case->created_at->format('M j, Y g:i A') }}</dd>

                    @if($case->assigned_at)
                    <dt class="col-5 text-muted">Assigned</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->assigned_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->approved_at)
                    <dt class="col-5 text-muted">Approved</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->approved_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->completed_at)
                    <dt class="col-5 text-muted">Completed</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->completed_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->cancellation_reason)
                    <dt class="col-5 text-muted">Cancel Reason</dt>
                    <dd class="col-7 text-danger">{{ $case->cancellation_reason }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Offerings -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Offerings</h6></div>
            <div class="card-body p-0">
                @foreach($case->caseOfferings as $co)
                <div class="px-3 py-2 border-bottom">
                    <div class="fw-medium small">{{ $co->offering->name }}</div>
                    @if($co->dosage)<div class="text-muted small">{{ $co->dosage }}</div>@endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Cancel action -->
        @if(in_array($case->status, ['created','waiting','support','assigned','approved']))
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 py-2">
                <h6 class="mb-0 fw-semibold text-danger">Cancel Case</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('partner.cases.cancel', $case->uuid) }}">
                    @csrf
                    <div class="mb-2">
                        <textarea name="reason" class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                  rows="2" placeholder="Reason for cancellation…" required></textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-sm btn-danger w-100"
                            onclick="return confirm('Cancel this case?')">
                        <i class="bi bi-x-circle me-1"></i> Cancel Case
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>

    <!-- Right: tabs -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-events">Timeline</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notes">Clinical Notes</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-orders">Orders</a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Timeline -->
                    <div class="tab-pane fade show active" id="tab-events">
                        @forelse($case->events as $event)
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0 mt-1">
                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                     style="width:32px;height:32px">
                                    <i class="bi bi-arrow-right-circle text-primary small"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fw-medium small">{{ ucfirst(str_replace('_',' ',$event->event_type)) }}</div>
                                @if($event->notes)
                                <div class="text-muted small">{{ $event->notes }}</div>
                                @endif
                                <div class="text-muted x-small">{{ $event->created_at->format('M j, Y g:i A') }}</div>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small">No events recorded.</p>
                        @endforelse
                    </div>

                    <!-- Notes -->
                    <div class="tab-pane fade" id="tab-notes">
                        @forelse($case->clinicalNotes as $note)
                        <div class="border rounded p-3 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-medium">{{ $note->clinician?->user->name ?? 'Clinician' }}</span>
                                <span class="text-muted">{{ $note->created_at->format('M j, Y g:i A') }}</span>
                            </div>
                            <p class="mb-0">{{ $note->note }}</p>
                        </div>
                        @empty
                        <p class="text-muted small">No clinical notes yet.</p>
                        @endforelse
                    </div>

                    <!-- Messages -->
                    <div class="tab-pane fade" id="tab-messages">
                        @forelse($case->messages as $msg)
                        <div class="border rounded p-3 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-medium">{{ $msg->sender_type === 'partner' ? 'Partner' : 'Clinician' }}</span>
                                <span class="text-muted">{{ $msg->created_at->format('M j, Y g:i A') }}</span>
                            </div>
                            <p class="mb-0">{{ $msg->message }}</p>
                        </div>
                        @empty
                        <p class="text-muted small">No messages yet.</p>
                        @endforelse
                    </div>

                    <!-- Orders -->
                    <div class="tab-pane fade" id="tab-orders">
                        @forelse($case->orders as $order)
                        <div class="border rounded p-3 mb-3 small">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-muted">Order #{{ $order->id }}</div>
                                    <div class="fw-medium">{{ $order->pharmacy?->name ?? 'Unknown pharmacy' }}</div>
                                </div>
                                <div class="col-3">
                                    <div class="text-muted">Status</div>
                                    <div>{{ ucfirst($order->status) }}</div>
                                </div>
                                <div class="col-3">
                                    <div class="text-muted">Tracking</div>
                                    <div>{{ $order->tracking_number ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small">No orders yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
