@extends('layouts.admin')

@section('title', 'Webhook Deliveries')
@section('page-title', 'Webhook Deliveries')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Delivery Log</h6>
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <select name="partner_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Partners</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" {{ request('partner_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach(['pending','retrying','delivered','failed'] as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="event_type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Events</option>
                @foreach(['case_created','case_waiting','case_assigned_to_clinician','case_support','case_approved','case_processing','case_completed','case_cancelled','clinical_note_added','message_created'] as $e)
                    <option value="{{ $e }}" {{ request('event_type') == $e ? 'selected' : '' }}>{{ $e }}</option>
                @endforeach
            </select>
            @if(request()->hasAny(['partner_id','status','event_type']))
                <a href="{{ route('admin.webhooks.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.875rem">
                <thead class="table-light">
                    <tr>
                        <th>Partner</th>
                        <th>Endpoint</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Response</th>
                        <th>Last Tried</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    @php
                        $partner = $delivery->webhook?->partner;
                        $badgeClass = match($delivery->status) {
                            'delivered' => 'bg-success',
                            'failed'    => 'bg-danger',
                            'retrying'  => 'bg-warning text-dark',
                            default     => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td>{{ $partner?->name ?? '—' }}</td>
                        <td>
                            <span class="font-monospace text-truncate d-inline-block" style="max-width:220px" title="{{ $delivery->webhook?->url }}">
                                {{ $delivery->webhook?->url ?? '—' }}
                            </span>
                        </td>
                        <td><code class="text-body">{{ $delivery->event_type }}</code></td>
                        <td><span class="badge {{ $badgeClass }}">{{ $delivery->status }}</span></td>
                        <td>{{ $delivery->attempts }} / {{ $delivery->max_attempts }}</td>
                        <td>
                            @if($delivery->response_code)
                                <span class="{{ $delivery->response_code >= 200 && $delivery->response_code < 300 ? 'text-success' : 'text-danger' }}">
                                    {{ $delivery->response_code }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($delivery->last_attempted_at)
                                <span title="{{ $delivery->last_attempted_at }}">{{ $delivery->last_attempted_at->diffForHumans() }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $delivery->created_at->format('M j, H:i') }}</small></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                @if($delivery->response_body)
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0"
                                        data-bs-toggle="modal" data-bs-target="#payloadModal"
                                        data-payload="{{ e(json_encode($delivery->payload, JSON_PRETTY_PRINT)) }}"
                                        data-response="{{ e($delivery->response_body) }}"
                                        data-event="{{ $delivery->event_type }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @endif
                                @if($delivery->status !== 'delivered')
                                <form method="POST" action="{{ route('admin.webhooks.resend', $delivery->uuid) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary py-0">
                                        <i class="bi bi-arrow-repeat"></i> Resend
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No webhook deliveries found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deliveries->hasPages())
    <div class="card-footer">
        {{ $deliveries->links() }}
    </div>
    @endif
</div>

{{-- Payload / Response Modal --}}
<div class="modal fade" id="payloadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Delivery Detail — <span id="modalEventType"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1 fw-semibold">Payload sent</p>
                <pre class="bg-light p-3 rounded" style="font-size:.8rem;max-height:200px;overflow:auto" id="modalPayload"></pre>
                <p class="mb-1 fw-semibold mt-3">Response body</p>
                <pre class="bg-light p-3 rounded" style="font-size:.8rem;max-height:200px;overflow:auto" id="modalResponse"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('payloadModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('modalEventType').textContent = btn.dataset.event;
    document.getElementById('modalPayload').textContent   = btn.dataset.payload;
    document.getElementById('modalResponse').textContent  = btn.dataset.response;
});
</script>
@endsection
