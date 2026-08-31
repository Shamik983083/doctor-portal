@extends('layouts.admin')

@section('title', 'EHR Record')
@section('page-title', 'EHR Record')

@section('content')
@php
    $statusBadge = match($record->status) {
        'sent'    => 'bg-success',
        'failed'  => 'bg-danger',
        'pending' => 'bg-warning text-dark',
        default   => 'bg-secondary',
    };
    $partnerName = $record->payload['company']['name'] ?? ('Partner #' . $record->partner_id);
    $caseUuid    = $record->payload['source']['case_id'] ?? null;
    $payloadJson = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<style>
.ehr-page { max-width: 100%; overflow-x: hidden; }

/* ── action bar ── */
.ehr-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
    padding: .6rem .9rem;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    margin-bottom: .75rem;
}
.ehr-bar .uuid-text {
    font-family: ui-monospace, monospace;
    font-size: .78rem;
    color: #495057;
    word-break: break-all;
}

/* ── stat chips ── */
.chip-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .28rem .65rem;
    border-radius: 99px;
    border: 1px solid #dee2e6;
    background: #fff;
    font-size: .78rem;
    color: #495057;
    white-space: nowrap;
}
.chip i { font-size: .8rem; color: #6c757d; }
.chip .chip-val { font-weight: 600; color: #212529; }

/* ── two col layout ── */
.ehr-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: .75rem;
    min-width: 0;
}
@media (max-width: 900px) {
    .ehr-grid { grid-template-columns: 1fr; }
}

/* ── detail card ── */
.detail-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    overflow: hidden;
    min-width: 0;
}
.detail-card .card-head {
    padding: .5rem .75rem;
    border-bottom: 1px solid #dee2e6;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #6c757d;
    background: #f8f9fa;
}
.detail-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.detail-table tr { border-bottom: 1px solid #f0f0f0; }
.detail-table tr:last-child { border-bottom: none; }
.detail-table th {
    padding: .45rem .75rem;
    font-weight: 500;
    color: #6c757d;
    width: 44%;
    white-space: nowrap;
    vertical-align: middle;
}
.detail-table td {
    padding: .45rem .75rem;
    color: #212529;
    vertical-align: middle;
    word-break: break-all;
}
.err-box {
    margin: .5rem .75rem .75rem;
    padding: .5rem .65rem;
    background: #fff5f5;
    border: 1px solid #f5c2c7;
    border-radius: .375rem;
    font-size: .77rem;
    color: #842029;
    font-family: ui-monospace, monospace;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 110px;
    overflow-y: auto;
}

/* ── code panel ── */
.code-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.code-tabs {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 .75rem;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    flex-wrap: wrap;
    gap: .25rem;
}
.code-tab-list { display: flex; gap: 0; }
.code-tab-btn {
    padding: .45rem .75rem;
    font-size: .79rem;
    font-weight: 500;
    color: #6c757d;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: color .15s, border-color .15s;
    white-space: nowrap;
}
.code-tab-btn.active { color: #0d6efd; border-bottom-color: #0d6efd; }
.code-tab-btn:hover:not(.active) { color: #212529; }
.copy-btn {
    padding: .25rem .55rem;
    font-size: .73rem;
    border: 1px solid #dee2e6;
    border-radius: .3rem;
    background: #fff;
    color: #6c757d;
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.copy-btn:hover { background: #f0f0f0; }
.copy-btn.copied { border-color: #198754; color: #198754; }

.code-pane { display: none; min-width: 0; }
.code-pane.active { display: block; }
.code-pre {
    margin: 0;
    padding: .75rem 1rem;
    font-size: .77rem;
    line-height: 1.65;
    font-family: ui-monospace, "Cascadia Code", "Fira Code", monospace;
    color: #212529;
    overflow: auto;
    max-height: calc(100vh - 320px);
    min-height: 200px;
    white-space: pre;
    word-break: normal;
    tab-size: 2;
}
</style>

<div class="ehr-page">

{{-- Alerts --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert" style="font-size:.85rem;">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert" style="font-size:.85rem;">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Action bar --}}
<div class="ehr-bar">
    <div style="display:flex;align-items:center;gap:.65rem;min-width:0;">
        <a href="{{ route('admin.ehr-records.index') }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
            <i class="bi bi-arrow-left"></i>
        </a>
        <span class="uuid-text">{{ $record->uuid }}</span>
        <span class="badge {{ $statusBadge }} flex-shrink-0">{{ $record->status }}</span>
    </div>

    <div class="flex-shrink-0">
        @if($canRetry)
        <form method="POST" action="{{ route('admin.ehr-records.retry', $record->uuid) }}" id="retryForm">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm px-3" id="retryBtn">
                <i class="bi bi-arrow-repeat me-1"></i>Retry Now
            </button>
        </form>
        @elseif($record->status === 'failed')
        <span class="text-muted" style="font-size:.78rem;">
            @if(! config('ehr.enabled'))
                <i class="bi bi-lock me-1"></i>EHR disabled
            @else
                <i class="bi bi-x-circle me-1"></i>Budget exhausted ({{ $record->attempts }}/{{ $maxAttempts }})
            @endif
        </span>
        @elseif($record->status === 'sent')
        <span class="text-success" style="font-size:.78rem;"><i class="bi bi-check-circle me-1"></i>Sent successfully</span>
        @endif
    </div>
</div>

{{-- Stat chips --}}
<div class="chip-row">
    <div class="chip"><i class="bi bi-hospital"></i> <span>{{ $record->adapter }}</span></div>
    <div class="chip"><i class="bi bi-building"></i> <span>{{ $partnerName }}</span></div>
    <div class="chip"><i class="bi bi-arrow-repeat"></i> Attempts: <span class="chip-val">{{ $record->attempts }}/{{ $maxAttempts }}</span></div>
    @if($record->response_code)
    <div class="chip"><i class="bi bi-wifi"></i> HTTP: <span class="chip-val {{ $record->response_code < 300 ? 'text-success' : 'text-danger' }}">{{ $record->response_code }}</span></div>
    @endif
    <div class="chip"><i class="bi bi-clock"></i> <span>{{ $record->created_at->format('M j, Y · H:i') }}</span></div>
    @if($record->sent_at)
    <div class="chip"><i class="bi bi-send-check"></i> Sent: <span class="chip-val">{{ $record->sent_at->format('M j · H:i') }}</span></div>
    @endif
</div>

{{-- Main grid --}}
<div class="ehr-grid">

    {{-- Left: details --}}
    <div class="detail-card">
        <div class="card-head">Record Details</div>
        <table class="detail-table">
            <tbody>
                <tr>
                    <th>Status</th>
                    <td><span class="badge {{ $statusBadge }}">{{ $record->status }}</span></td>
                </tr>
                <tr>
                    <th>Partner</th>
                    <td>{{ $partnerName }}</td>
                </tr>
                <tr>
                    <th>Case</th>
                    <td>
                        @if($record->case)
                            <a href="{{ route('admin.cases.show', $record->case->uuid) }}"
                               class="font-monospace" style="font-size:.76rem;">
                                {{ substr($record->case->uuid, 0, 8) }}&hellip;
                            </a>
                        @elseif($caseUuid)
                            <span class="font-monospace text-muted" style="font-size:.76rem;">{{ substr($caseUuid, 0, 8) }}&hellip;</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Adapter</th>
                    <td><code style="font-size:.78rem;">{{ $record->adapter }}</code></td>
                </tr>
                <tr>
                    <th>Attempts</th>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span>{{ $record->attempts }} / {{ $maxAttempts }}</span>
                        </div>
                        <div class="progress mt-1" style="height:3px;">
                            <div class="progress-bar {{ $record->status === 'sent' ? 'bg-success' : 'bg-danger' }}"
                                 style="width:{{ $maxAttempts > 0 ? min(100, ($record->attempts / $maxAttempts) * 100) : 0 }}%"></div>
                        </div>
                    </td>
                </tr>
                @if($record->reference)
                <tr>
                    <th>EHR Ref</th>
                    <td><code class="text-success" style="font-size:.74rem;word-break:break-all;">{{ $record->reference }}</code></td>
                </tr>
                @endif
                @if($record->response_code)
                <tr>
                    <th>HTTP Code</th>
                    <td class="{{ $record->response_code < 300 ? 'text-success' : 'text-danger' }} fw-semibold">{{ $record->response_code }}</td>
                </tr>
                @endif
                <tr>
                    <th>Created</th>
                    <td style="font-size:.78rem;">{{ $record->created_at->format('M j, Y H:i') }}</td>
                </tr>
                @if($record->sent_at)
                <tr>
                    <th>Sent At</th>
                    <td style="font-size:.78rem;">{{ $record->sent_at->format('M j, Y H:i') }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        @if($record->last_error)
        <div style="padding:.5rem .75rem .25rem;font-size:.69rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#dc3545;">
            <i class="bi bi-exclamation-circle me-1"></i>Last Error
        </div>
        <div class="err-box">{{ $record->last_error }}</div>
        @endif
    </div>

    {{-- Right: payload + response tabs --}}
    <div class="code-card">
        <div class="code-tabs">
            <div class="code-tab-list">
                <button class="code-tab-btn active" onclick="switchTab('payload', this)">
                    <i class="bi bi-code-square me-1"></i>Payload
                    <span class="badge bg-secondary ms-1" style="font-size:.62rem;vertical-align:middle;">PHI</span>
                </button>
                @if($record->response_body)
                <button class="code-tab-btn {{ $record->status === 'failed' ? 'active' : '' }}"
                        id="responseTabBtn"
                        onclick="switchTab('response', this)">
                    <i class="bi bi-arrow-return-left me-1"></i>Response
                </button>
                @endif
            </div>
            <button class="copy-btn" id="copyBtn" onclick="copyActive()">
                <i class="bi bi-clipboard me-1"></i>Copy
            </button>
        </div>

        <div class="code-pane {{ $record->status !== 'failed' || !$record->response_body ? 'active' : '' }}"
             id="pane-payload">
            <pre class="code-pre" id="pre-payload">{{ $payloadJson }}</pre>
        </div>

        @if($record->response_body)
        <div class="code-pane {{ $record->status === 'failed' ? 'active' : '' }}"
             id="pane-response">
            <pre class="code-pre" id="pre-response">{{ $record->response_body }}</pre>
        </div>
        @endif
    </div>

</div>
</div>
@endsection

@section('scripts')
<script>
var _activePane = '{{ ($record->status === "failed" && $record->response_body) ? "response" : "payload" }}';

function switchTab(name, btn) {
    document.querySelectorAll('.code-pane').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.code-tab-btn').forEach(function(b) { b.classList.remove('active'); });
    var pane = document.getElementById('pane-' + name);
    if (pane) pane.classList.add('active');
    btn.classList.add('active');
    _activePane = name;
}

function copyActive() {
    var pre = document.getElementById('pre-' + _activePane);
    if (!pre) return;
    var btn = document.getElementById('copyBtn');
    navigator.clipboard.writeText(pre.textContent).then(function () {
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied';
        btn.classList.add('copied');
        setTimeout(function () {
            btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
            btn.classList.remove('copied');
        }, 1800);
    });
}

// Pretty-print response if JSON
(function () {
    var resp = document.getElementById('pre-response');
    if (resp) {
        try { resp.textContent = JSON.stringify(JSON.parse(resp.textContent), null, 2); } catch (_) {}
    }
})();

// Retry spinner
var retryBtn = document.getElementById('retryBtn');
if (retryBtn) {
    retryBtn.closest('form').addEventListener('submit', function () {
        retryBtn.disabled = true;
        retryBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Retrying…';
    });
}
</script>
@endsection
