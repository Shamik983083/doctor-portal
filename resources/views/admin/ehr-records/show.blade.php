@extends('layouts.admin')

@section('title', 'EHR Record')
@section('page-title', 'EHR Record')

@section('content')
@php
    $statusColor = match($record->status) {
        'sent'     => ['badge' => 'bg-success',              'dot' => '#16863f'],
        'failed'   => ['badge' => 'bg-danger',               'dot' => '#dc3545'],
        'pending'  => ['badge' => 'bg-warning text-dark',    'dot' => '#e6a817'],
        default    => ['badge' => 'bg-secondary',            'dot' => '#6c757d'],
    };
    $partnerName = $record->payload['company']['name'] ?? ('Partner #' . $record->partner_id);
    $caseUuid    = $record->payload['source']['case_id'] ?? null;
@endphp

{{-- ── Top bar ──────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.ehr-records.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>EHR Records
    </a>

    <div class="d-flex align-items-center gap-2">
        @if($canRetry)
        <form method="POST" action="{{ route('admin.ehr-records.retry', $record->uuid) }}" id="retryForm">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm px-3"
                    onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Retrying…';this.form.submit();">
                <i class="bi bi-arrow-repeat me-1"></i>Retry Now
            </button>
        </form>
        @elseif($record->status === 'failed')
        <span class="text-muted small">
            @if(! config('ehr.enabled'))
                <i class="bi bi-lock me-1"></i>EHR disabled — set <code>EHR_ENABLED=true</code>
            @else
                <i class="bi bi-x-circle me-1"></i>Budget exhausted ({{ $record->attempts }}/{{ $maxAttempts }})
            @endif
        </span>
        @endif
    </div>
</div>

{{-- ── Identity strip ───────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="font-monospace fw-semibold" style="font-size:.82rem;color:#495057;">{{ $record->uuid }}</span>
            <span class="badge {{ $statusColor['badge'] }}">{{ $record->status }}</span>
            <span class="text-muted" style="font-size:.8rem;"><i class="bi bi-hospital me-1"></i>{{ $record->adapter }}</span>
            <span class="text-muted" style="font-size:.8rem;"><i class="bi bi-building me-1"></i>{{ $partnerName }}</span>
            <span class="text-muted" style="font-size:.8rem;"><i class="bi bi-clock me-1"></i>{{ $record->created_at->format('M j, Y H:i') }}</span>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Two-column layout ────────────────────────────────── --}}
<div class="row g-3" style="align-items:start;">

    {{-- Left: metadata --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-bottom py-2 px-3">
                <span style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6c757d;">Record Details</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:.84rem;">
                    <tbody>
                        <tr>
                            <th class="ps-3 text-muted fw-normal" style="width:42%;white-space:nowrap;">Status</th>
                            <td class="pe-3"><span class="badge {{ $statusColor['badge'] }}">{{ $record->status }}</span></td>
                        </tr>
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Adapter</th>
                            <td class="pe-3"><code>{{ $record->adapter }}</code></td>
                        </tr>
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Attempts</th>
                            <td class="pe-3">
                                {{ $record->attempts }} / {{ $maxAttempts }}
                                @if($record->attempts > 0)
                                <div class="progress mt-1" style="height:4px;">
                                    <div class="progress-bar {{ $record->status === 'sent' ? 'bg-success' : 'bg-danger' }}"
                                         style="width:{{ min(100, ($record->attempts / $maxAttempts) * 100) }}%"></div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Partner</th>
                            <td class="pe-3">{{ $partnerName }}</td>
                        </tr>
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Case</th>
                            <td class="pe-3">
                                @if($record->case)
                                    <a href="{{ route('admin.cases.show', $record->case->uuid) }}"
                                       class="font-monospace" style="font-size:.78rem;">
                                        {{ substr($record->case->uuid, 0, 8) }}&hellip;
                                    </a>
                                @elseif($caseUuid)
                                    <span class="font-monospace text-muted" style="font-size:.78rem;">
                                        {{ substr($caseUuid, 0, 8) }}&hellip;
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @if($record->reference)
                        <tr>
                            <th class="ps-3 text-muted fw-normal">EHR Ref</th>
                            <td class="pe-3"><code class="text-success" style="font-size:.75rem;">{{ $record->reference }}</code></td>
                        </tr>
                        @endif
                        @if($record->sent_at)
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Sent At</th>
                            <td class="pe-3">{{ $record->sent_at->format('M j, Y H:i') }}</td>
                        </tr>
                        @endif
                        @if($record->response_code)
                        <tr>
                            <th class="ps-3 text-muted fw-normal">HTTP Code</th>
                            <td class="pe-3">
                                <span class="{{ $record->response_code >= 200 && $record->response_code < 300 ? 'text-success' : 'text-danger' }} fw-semibold">
                                    {{ $record->response_code }}
                                </span>
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <th class="ps-3 text-muted fw-normal">Created</th>
                            <td class="pe-3">{{ $record->created_at->format('M j, Y H:i') }}</td>
                        </tr>
                    </tbody>
                </table>

                @if($record->last_error)
                <div class="border-top px-3 py-2">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size:.8rem;"></i>
                        <span style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#dc3545;">Last Error</span>
                    </div>
                    <pre class="mb-0 p-2 rounded"
                         style="font-size:.78rem;white-space:pre-wrap;word-break:break-all;background:#fff5f5;border:1px solid #f5c2c7;color:#842029;max-height:120px;overflow-y:auto;">{{ $record->last_error }}</pre>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right: payload + response in tabs --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-0 px-3">
                <ul class="nav nav-tabs border-0" id="ehrTabs" role="tablist" style="margin-bottom:-1px;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-3" id="payload-tab"
                                data-bs-toggle="tab" data-bs-target="#payload-panel"
                                type="button" role="tab" style="font-size:.82rem;">
                            <i class="bi bi-code-square me-1"></i>Payload
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">PHI</span>
                        </button>
                    </li>
                    @if($record->response_body)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-3" id="response-tab"
                                data-bs-toggle="tab" data-bs-target="#response-panel"
                                type="button" role="tab" style="font-size:.82rem;">
                            <i class="bi bi-arrow-return-left me-1"></i>Response
                        </button>
                    </li>
                    @endif
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="payload-panel" role="tabpanel">
                    <div class="d-flex justify-content-end px-3 pt-2">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                style="font-size:.75rem;"
                                onclick="copyCode('payloadPre', this)">
                            <i class="bi bi-clipboard me-1"></i>Copy
                        </button>
                    </div>
                    <pre id="payloadPre"
                         class="mb-0 px-3 pb-3"
                         style="font-size:.78rem;line-height:1.6;max-height:520px;overflow:auto;color:#212529;">{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>

                @if($record->response_body)
                <div class="tab-pane fade" id="response-panel" role="tabpanel">
                    <div class="d-flex justify-content-end px-3 pt-2">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                style="font-size:.75rem;"
                                onclick="copyCode('responsePre', this)">
                            <i class="bi bi-clipboard me-1"></i>Copy
                        </button>
                    </div>
                    <pre id="responsePre"
                         class="mb-0 px-3 pb-3"
                         style="font-size:.78rem;line-height:1.6;max-height:520px;overflow:auto;color:#212529;">{{ $record->response_body }}</pre>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    var resp = document.getElementById('responsePre');
    if (resp) {
        try { resp.textContent = JSON.stringify(JSON.parse(resp.textContent), null, 2); } catch (_) {}
    }

    // Switch to response tab automatically if record failed and response exists
    @if($record->status === 'failed' && $record->response_body)
    var respTab = document.getElementById('response-tab');
    if (respTab) respTab.click();
    @endif
})();

function copyCode(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function () {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied';
        btn.classList.replace('btn-outline-secondary', 'btn-outline-success');
        setTimeout(function () {
            btn.innerHTML = orig;
            btn.classList.replace('btn-outline-success', 'btn-outline-secondary');
        }, 1800);
    });
}
</script>
@endsection
