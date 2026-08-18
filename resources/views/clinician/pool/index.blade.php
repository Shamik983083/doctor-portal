{{-- layouts.clinician-exact, matching every other clinician screen after the
     2026-07-23 unification (commit 7910e55). layouts.clinician is no longer used
     by any clinician view. --}}
@extends('layouts.clinician-exact')

@section('title', 'Request Cases')
@section('page-title', 'Request Cases')

@section('content')
{{--
    The provider pool, from the doctor's side (Devin msg 2308).

    There is deliberately no list of available cases here. The doctor asks for a
    number and the pool grants the oldest cases they are eligible for. Showing the
    queue would let it be cherry-picked, and the oldest-first guarantee is the only
    thing keeping the tail of the queue from going stale.
--}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">Request Cases</h4>
        <p class="text-muted small mb-0">
            Ask for a number of cases. You will be given the oldest ones in the queue that match your
            licensed states, the categories you accept and the visit types you take.
        </p>
    </div>
</div>

@if($eligibility->isBlocked())
    <div class="alert alert-danger">
        <strong>You cannot take cases from the pool right now.</strong>
        <ul class="mb-0 mt-2">
            @foreach($eligibility->blockingReasons as $code)
                <li>{{ $reasonLabels[$code] ?? $code }}</li>
            @endforeach
        </ul>
    </div>
@elseif($eligibility->requiresApproval)
    <div class="alert alert-warning">
        <strong>You are over an SLA your admin set.</strong>
        You can still ask, but the request goes to your Doctor Admin to approve rather than being
        granted straight away.
        <ul class="mb-0 mt-2">
            @foreach($eligibility->slaViolations as $code)
                <li>{{ $reasonLabels[$code] ?? $code }}</li>
            @endforeach
        </ul>
    </div>
@elseif($eligibility->slaViolations !== [])
    <div class="alert alert-warning">
        You are over an SLA your admin set, and their setting lets the request through anyway. They
        will be told each time this happens.
    </div>
@endif

<div class="card mb-4" style="border-radius:16px;border:1px solid var(--line,#e5e9f0);box-shadow:0 1px 3px rgba(0,0,0,.05),0 6px 20px rgba(14,20,36,.07);overflow:visible">
    <div style="padding:28px 32px">

        <div style="margin-bottom:20px">
            <div style="font-size:15px;font-weight:700;color:var(--text,#172033);margin-bottom:4px">How many cases?</div>
            <div style="font-size:13px;color:var(--soft-muted,#6b7a99)">You'll receive the oldest matching cases from the queue. Up to {{ $maxPerRequest }} per request.</div>
        </div>

        {{-- Quick-pick presets --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px" id="presetRow">
            @foreach([1, 3, 5, 10] as $p)
                <button type="button" onclick="pickPreset({{ $p }})"
                    id="preset-{{ $p }}"
                    style="padding:7px 18px;border-radius:8px;border:1.5px solid var(--line,#e5e9f0);background:#fff;font-size:13px;font-weight:650;color:var(--text,#172033);cursor:pointer;transition:border-color .15s,background .15s,color .15s"
                    onmouseover="if(!this.classList.contains('psel'))this.style.borderColor='#248bf5'"
                    onmouseout="if(!this.classList.contains('psel'))this.style.borderColor='var(--line,#e5e9f0)'">
                    {{ $p }}
                </button>
            @endforeach
        </div>

        <form method="POST" action="{{ route('clinician.pool.request') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            @csrf
            <input type="number" name="count" id="caseCount" min="1"
                   max="{{ $maxPerRequest }}" value="{{ old('count', 5) }}" required
                   @disabled($eligibility->isBlocked())
                   oninput="syncPresets()"
                   style="width:88px;padding:11px 14px;border:1.5px solid var(--line,#e5e9f0);border-radius:10px;font-size:22px;font-weight:700;text-align:center;color:var(--text,#172033);background:#fff;-moz-appearance:textfield;-webkit-appearance:none;appearance:none;outline:none"
                   onfocus="this.style.borderColor='#248bf5'"
                   onblur="this.style.borderColor='var(--line,#e5e9f0)'">

            <button type="submit" @disabled($eligibility->isBlocked())
                style="padding:12px 28px;background:#248bf5;color:#fff;border:0;border-radius:10px;font-size:14px;font-weight:650;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;transition:opacity .15s"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                Request cases
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-left:2px"><path d="M1 7h12M8 3l5 4-5 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            @if($remainingToday !== null)
                <span style="font-size:12px;color:var(--soft-muted,#6b7a99)">{{ $remainingToday }} left today</span>
            @endif
        </form>

    </div>
</div>

<style>
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
.psel { background:#ebf4ff !important; border-color:#248bf5 !important; color:#248bf5 !important; }
</style>
<script>
var PRESETS = [1,3,5,10];
function pickPreset(v) {
    document.getElementById('caseCount').value = v;
    syncPresets();
}
function syncPresets() {
    var val = parseInt(document.getElementById('caseCount').value, 10);
    PRESETS.forEach(function(p) {
        var el = document.getElementById('preset-' + p);
        if (!el) return;
        if (p === val) {
            el.classList.add('psel');
            el.style.borderColor = '#248bf5';
        } else {
            el.classList.remove('psel');
            el.style.borderColor = 'var(--line,#e5e9f0)';
            el.style.color = 'var(--text,#172033)';
            el.style.background = '#fff';
        }
    });
}
syncPresets();
</script>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-2">What you will be given</h6>
        <div class="row small text-muted">
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Your states</div>
                @php $states = collect($clinician->licensed_states ?? [])->pluck('state')->filter(); @endphp
                {{ $states->isEmpty() ? 'None recorded, so nothing can be assigned to you.' : $states->implode(', ') }}
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Categories you accept</div>
                {{ $clinician->acceptedCategories->pluck('name')->implode(', ') ?: 'None ticked, so nothing can be assigned to you.' }}
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark">Visit types</div>
                {{ $clinician->accepts_async_visits ? 'Asynchronous' : '' }}
                {{ $clinician->accepts_sync_visits && $clinician->hasSchedulingLink() ? ' · Synchronous' : '' }}
                @if($clinician->accepts_sync_visits && ! $clinician->hasSchedulingLink())
                    <div class="text-danger">
                        You take synchronous visits but have no booking link, so those cases cannot
                        reach you. Ask an admin to add it.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Your recent requests</div>
    @if($requests->isEmpty())
        <div class="card-body text-muted small">You have not requested any cases yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Asked</th>
                        <th>Given</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($requests as $request)
                    <tr>
                        <td class="small text-muted">{{ $request->created_at->diffForHumans() }}</td>
                        <td>{{ $request->requested_count }}</td>
                        <td>{{ $request->granted_count }}</td>
                        <td class="small">
                            <span class="badge bg-{{ $request->status === 'GRANTED' ? 'success' : 'secondary' }}">
                                {{ $request->statusLabel() }}
                            </span>
                            @if($request->shortfall_reason)
                                <div class="text-muted mt-1">{{ $request->shortfall_reason }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
