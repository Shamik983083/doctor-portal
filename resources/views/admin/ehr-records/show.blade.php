@extends('layouts.admin')

@section('title', 'EHR Record')
@section('page-title', 'EHR Record')

@section('content')
@php
    $badgeClass = match($record->status) {
        'sent'     => 'bg-success',
        'failed'   => 'bg-danger',
        'pending'  => 'bg-warning text-dark',
        default    => 'bg-secondary',
    };
@endphp

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <a href="{{ route('admin.ehr-records.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <h5 class="mb-0 font-monospace">{{ $record->uuid }}</h5>
        <small class="text-muted">Created {{ $record->created_at->format('M j, Y H:i') }}</small>
    </div>
    @if($canRetry)
    <form method="POST" action="{{ route('admin.ehr-records.retry', $record->uuid) }}">
        @csrf
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-repeat"></i> Retry Now
        </button>
    </form>
    @elseif($record->status === 'failed')
    <span class="text-muted small">
        @if(! config('ehr.enabled'))
            EHR push is disabled — enable <code>EHR_ENABLED</code> to retry.
        @else
            Retry budget exhausted ({{ $record->attempts }}/{{ $maxAttempts }} attempts).
        @endif
    </span>
    @endif
</div>

<div class="row g-3">
    {{-- Metadata --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Record Details</h6></div>
            <div class="card-body" style="font-size:.875rem">
                <dl class="row mb-0">
                    <dt class="col-5">Status</dt>
                    <dd class="col-7"><span class="badge {{ $badgeClass }}">{{ $record->status }}</span></dd>

                    <dt class="col-5">Adapter</dt>
                    <dd class="col-7"><code>{{ $record->adapter }}</code></dd>

                    <dt class="col-5">Attempts</dt>
                    <dd class="col-7">{{ $record->attempts }} / {{ $maxAttempts }}</dd>

                    <dt class="col-5">Partner</dt>
                    <dd class="col-7">{{ $record->payload['company']['name'] ?? ('ID ' . $record->partner_id) }}</dd>

                    <dt class="col-5">Case</dt>
                    <dd class="col-7">
                        @if($record->case)
                            <a href="{{ route('admin.cases.show', $record->case->uuid) }}" class="font-monospace">
                                {{ substr($record->case->uuid, 0, 8) }}&hellip;
                            </a>
                        @else
                            <span class="font-monospace text-muted">
                                {{ substr($record->payload['source']['case_id'] ?? '?', 0, 8) }}&hellip;
                            </span>
                        @endif
                    </dd>

                    @if($record->reference)
                    <dt class="col-5">EHR Ref</dt>
                    <dd class="col-7"><code class="text-success">{{ $record->reference }}</code></dd>
                    @endif

                    @if($record->sent_at)
                    <dt class="col-5">Sent At</dt>
                    <dd class="col-7">{{ $record->sent_at->format('M j, Y H:i') }}</dd>
                    @endif

                    @if($record->response_code)
                    <dt class="col-5">HTTP Code</dt>
                    <dd class="col-7">
                        <span class="{{ $record->response_code >= 200 && $record->response_code < 300 ? 'text-success' : 'text-danger' }}">
                            {{ $record->response_code }}
                        </span>
                    </dd>
                    @endif

                    @if($record->last_error)
                    <dt class="col-12 text-danger mt-2">Last Error</dt>
                    <dd class="col-12 mt-1">
                        <pre class="bg-light p-2 rounded mb-0"
                             style="font-size:.8rem;white-space:pre-wrap;word-break:break-all">{{ $record->last_error }}</pre>
                    </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Payload + response --}}
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Payload <span class="text-muted fw-normal">(PHI — read only)</span></h6>
            </div>
            <div class="card-body p-0">
                <pre class="mb-0 p-3"
                     style="font-size:.8rem;max-height:420px;overflow:auto;border-radius:0 0 .375rem .375rem"
                     id="payloadPre">{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>

        @if($record->response_body)
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Response Body</h6></div>
            <div class="card-body p-0">
                <pre class="mb-0 p-3"
                     style="font-size:.8rem;max-height:220px;overflow:auto;border-radius:0 0 .375rem .375rem"
                     id="responsePre">{{ $record->response_body }}</pre>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // Pretty-print response body if it is valid JSON
    var resp = document.getElementById('responsePre');
    if (resp) {
        try { resp.textContent = JSON.stringify(JSON.parse(resp.textContent), null, 2); } catch (_) {}
    }
})();
</script>
@endsection
