@extends('layouts.admin')

@section('title', 'AI Integration Settings')
@section('page-title', 'AI Integration Settings')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 px-3 small mb-3" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 px-3 small mb-3" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>
    @foreach($errors->all() as $err) {{ $err }}<br> @endforeach
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info py-2 px-3 small mb-4 d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
        <strong>Environment vs. database settings.</strong>
        Adapter type, API key, model, and base URI are configured via server environment variables and require a deployment to change.
        The <strong>Enabled</strong> and <strong>BAA Confirmed</strong> toggles are stored in the database and take effect immediately.
    </div>
</div>

{{-- Integration cards --}}
<div class="row g-4 mb-4">
@forelse($integrations as $key => $cfg)
@php
    $label    = ucfirst($key);
    $adapter  = $cfg['adapter'] ?? 'mock';
    $model    = $cfg['model'] ?? null;
    $apiKey   = $cfg['api_key'] ?? null;
    $baseUri  = $cfg['base_uri'] ?? null;
    $enabled  = $dbSettings[$key]['enabled'] ?? false;
    $baa      = $dbSettings[$key]['baa_confirmed'] ?? false;
    $isLive   = $enabled && $baa && $adapter !== 'mock';
    $contexts = collect($contextIntegrations)->filter(fn($v) => $v === $key)->keys();
@endphp
<div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:36px;height:36px;background:#4361ee1a;">
                    <i class="bi bi-plug" style="color:#4361ee;font-size:1rem;"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-semibold">{{ $label }} Integration</h6>
                    <span class="text-muted" style="font-size:.72rem;">key: <code>{{ $key }}</code></span>
                </div>
            </div>
            @if($isLive)
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25" style="font-size:.72rem;">
                    <i class="bi bi-broadcast me-1"></i>Live
                </span>
            @elseif($adapter === 'mock')
                <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.72rem;">
                    Mock only
                </span>
            @else
                <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-25" style="font-size:.72rem;">
                    Disabled
                </span>
            @endif
        </div>

        <div class="card-body">

            {{-- Env-only fields (read-only) --}}
            <h6 class="text-muted text-uppercase fw-semibold mb-2" style="font-size:.68rem;letter-spacing:.04em;">
                Environment-configured (read-only)
            </h6>
            <div class="mb-3">
                <div class="row g-2" style="font-size:.82rem;">
                    <div class="col-5 text-muted">Adapter</div>
                    <div class="col-7">
                        <span class="badge {{ $adapter === 'mock' ? 'bg-secondary' : 'bg-primary' }} bg-opacity-10 {{ $adapter === 'mock' ? 'text-secondary' : 'text-primary' }} border" style="font-size:.75rem;">
                            {{ $adapter }}
                        </span>
                    </div>
                    <div class="col-5 text-muted">API Key</div>
                    <div class="col-7 font-monospace" style="font-size:.78rem;">
                        @if($apiKey)
                            <span class="text-success">{{ str_repeat('•', 8) }}{{ substr($apiKey, -4) }}</span>
                        @else
                            <span class="text-muted fst-italic">not set</span>
                        @endif
                    </div>
                    <div class="col-5 text-muted">Model</div>
                    <div class="col-7">{{ $model ?? '—' }}</div>
                    @if($baseUri)
                    <div class="col-5 text-muted">Base URI</div>
                    <div class="col-7 text-truncate" style="font-size:.78rem;" title="{{ $baseUri }}">{{ $baseUri }}</div>
                    @endif
                    @if($contexts->isNotEmpty())
                    <div class="col-5 text-muted">Contexts</div>
                    <div class="col-7">
                        @foreach($contexts as $ctx)
                            <span class="badge bg-light text-dark border me-1" style="font-size:.7rem;">
                                {{ $contextLabels[$ctx] ?? $ctx }}
                            </span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            <hr class="my-3">

            {{-- DB-controlled toggles --}}
            <h6 class="text-muted text-uppercase fw-semibold mb-3" style="font-size:.68rem;letter-spacing:.04em;">
                Database-controlled toggles
            </h6>

            <form method="POST" action="{{ route('admin.ai.settings.update', $key) }}"
                  id="form-{{ $key }}">
                @csrf

                {{-- Enabled toggle --}}
                <div class="d-flex align-items-start justify-content-between mb-3 gap-3">
                    <div>
                        <div class="fw-semibold" style="font-size:.85rem;">AI Enabled</div>
                        <div class="text-muted" style="font-size:.75rem;">
                            Allows AI drafting for contexts assigned to this integration.
                            No PHI is sent without BAA also confirmed.
                        </div>
                    </div>
                    <div class="form-check form-switch flex-shrink-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="enabled-{{ $key }}" name="enabled" value="1"
                               {{ $enabled ? 'checked' : '' }}
                               onchange="document.getElementById('form-{{ $key }}').dispatchEvent(new Event('toggle-enabled'))">
                    </div>
                </div>

                {{-- BAA toggle --}}
                <div class="d-flex align-items-start justify-content-between mb-3 gap-3">
                    <div>
                        <div class="fw-semibold" style="font-size:.85rem;">BAA Confirmed</div>
                        <div class="text-muted" style="font-size:.75rem;">
                            Attests that a Business Associate Agreement is in place for this integration.
                            Required before any PHI can be sent to the model.
                        </div>
                    </div>
                    <div class="form-check form-switch flex-shrink-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="baa-{{ $key }}" name="baa_confirmed" value="1"
                               {{ $baa ? 'checked' : '' }}
                               onchange="toggleBaaConfirm('{{ $key }}', this.checked)">
                    </div>
                </div>

                {{-- BAA confirmation box (shown only when toggling BAA on) --}}
                <div id="baa-confirm-{{ $key }}" class="{{ $baa ? 'd-none' : 'd-none' }}">
                    <div class="alert alert-warning py-2 px-3 mb-2" style="font-size:.8rem;">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        <strong>Legal attestation required.</strong>
                        By confirming, you attest that a signed BAA covering this integration is in place.
                        This allows patient information to be transmitted to the AI provider.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="baa-text-{{ $key }}">
                            Type <strong>I CONFIRM</strong> to proceed
                        </label>
                        <input type="text" class="form-control form-control-sm @error('baa_confirm_text') is-invalid @enderror"
                               id="baa-text-{{ $key }}" name="baa_confirm_text"
                               placeholder="I CONFIRM"
                               autocomplete="off" style="max-width:180px;">
                        @error('baa_confirm_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-floppy me-1"></i> Save {{ $label }} Settings
                </button>
            </form>

        </div>
    </div>
</div>
@empty
<div class="col-12">
    <div class="alert alert-secondary">
        No integrations are defined in <code>config/ai.php</code>.
    </div>
</div>
@endforelse
</div>

{{-- Context → Integration mapping (read-only) --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:36px;height:36px;background:#4361ee1a;">
            <i class="bi bi-diagram-3" style="color:#4361ee;font-size:1rem;"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-semibold">Context → Integration Mapping</h6>
            <p class="text-muted mb-0" style="font-size:.72rem;">
                Configured in <code>config/ai.php → context_integrations</code>. Requires a deployment to change.
                Click <strong>Edit</strong> to manage instruction sets for each context.
            </p>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.875rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Context</th>
                        <th class="py-3">Integration</th>
                        <th class="py-3">Status</th>
                        <th class="py-3"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($contextLabels as $ctx => $ctxLabel)
                @php
                    $intKey   = $contextIntegrations[$ctx] ?? 'clinical';
                    $intCfg   = $integrations[$intKey] ?? [];
                    $intBaa   = $dbSettings[$intKey]['baa_confirmed'] ?? (bool) ($intCfg['baa_confirmed'] ?? false);
                    $intOn    = $dbSettings[$intKey]['enabled']       ?? (bool) ($intCfg['enabled'] ?? false);
                    $intAdapt = $intCfg['adapter'] ?? 'mock';
                    $ctxLive  = $intOn && $intBaa && $intAdapt !== 'mock';
                @endphp
                <tr>
                    <td class="ps-4 py-3">
                        <div class="fw-semibold">{{ $ctxLabel }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $ctx }}</div>
                    </td>
                    <td class="py-3">
                        <span class="badge bg-light text-dark border" style="font-size:.75rem;">{{ $intKey }}</span>
                    </td>
                    <td class="py-3">
                        @if($ctxLive)
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.72rem;">Live</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.72rem;">Mock / off</span>
                        @endif
                    </td>
                    <td class="py-3">
                        <a href="{{ route('admin.ai.edit', $ctx) }}"
                           class="btn btn-sm btn-outline-secondary py-0 px-2">
                            <i class="bi bi-pencil me-1"></i>Edit instructions
                        </a>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function toggleBaaConfirm(key, checked) {
    var box = document.getElementById('baa-confirm-' + key);
    if (!box) return;
    if (checked) {
        box.classList.remove('d-none');
        var inp = document.getElementById('baa-text-' + key);
        if (inp) inp.focus();
    } else {
        box.classList.add('d-none');
        var inp = document.getElementById('baa-text-' + key);
        if (inp) inp.value = '';
    }
}

// On page load: if BAA is currently OFF and the checkbox is checked (fresh error
// redirect with old input), show the confirm box so the user can re-type.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="baa-"]').forEach(function (el) {
        if (el.tagName === 'INPUT' && el.type === 'checkbox' && el.id.startsWith('baa-') && !el.id.startsWith('baa-confirm') && !el.id.startsWith('baa-text')) {
            var key = el.id.replace('baa-', '');
            // If old input came back (validation failure) show the confirm box.
            var textInput = document.getElementById('baa-text-' + key);
            if (textInput && el.checked && textInput.value === '') {
                toggleBaaConfirm(key, true);
            }
        }
    });
});
</script>
@endsection
