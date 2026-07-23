@extends('layouts.admin')

@section('title', 'Case Routing')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">Case Routing</h4>
        <p class="text-muted small mb-0">
            Which doctor a new case is auto-assigned to. Every change is a new version, and the
            active version is never edited in place, so it stays possible to say which rules a case
            was routed under.
        </p>
    </div>
</div>

@if($active)
    <div class="card mb-4 border-primary">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge bg-primary mb-2">Live</span>
                    <h6 class="fw-semibold mb-1">v{{ $active->version }} · {{ $active->modeLabel() }}</h6>
                    <p class="text-muted small mb-1">{{ $modeNotes[$active->mode] ?? '' }}</p>
                    @if($active->note)
                        <p class="small mb-0">{{ $active->note }}</p>
                    @endif
                </div>
                <div class="text-muted small text-end">
                    @if($active->activated_at)
                        Activated {{ $active->activated_at->diffForHumans() }}<br>
                    @endif
                    @if($active->activatedBy)
                        by {{ $active->activatedBy->name }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-warning">
        <strong>No active routing policy.</strong> Nothing is being auto-assigned. Cases will wait in
        the queue until a version is activated.
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Draft a new version</h6>

        <form method="POST" action="{{ route('admin.routing.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold">Mode</label>
                @foreach($modes as $value => $label)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mode" id="mode_{{ $value }}"
                               value="{{ $value }}" {{ old('mode', $active->mode ?? '') === $value ? 'checked' : '' }}>
                        <label class="form-check-label" for="mode_{{ $value }}">
                            <strong>{{ $label }}</strong>
                            <span class="d-block text-muted small">{{ $modeNotes[$value] ?? '' }}</span>
                        </label>
                    </div>
                @endforeach
            </div>

            <hr>

            <h6 class="fw-semibold mb-1">Intelligent score weights</h6>
            <p class="text-muted small">
                Only used by the intelligent mode. Lower total score wins, so a bigger number here
                means that signal pushes work away from a doctor harder. Blank keeps the default.
            </p>
            <div class="row">
                @foreach($weightKeys as $key => $label)
                    <div class="col-md-3 mb-3">
                        <label class="form-label small fw-semibold">{{ $label }}</label>
                        <input type="number" step="any" class="form-control form-control-sm"
                               name="weights[{{ $key }}]"
                               placeholder="{{ $defaults[$key] }}"
                               value="{{ old('weights.' . $key, $active->config['intelligentWeights'][$key] ?? '') }}">
                    </div>
                @endforeach
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Block a doctor with a message older than (hours)</label>
                <input type="number" step="any" min="0" class="form-control form-control-sm" style="max-width:220px"
                       name="message_aging_hours"
                       value="{{ old('message_aging_hours', $active->config['messageAgingThresholdHours'] ?? '') }}">
                <div class="form-text">Leave blank to switch this rule off.</div>
            </div>

            {{-- New-case criteria (Devin msg 2248). All stop NEW cases only; a
                 returning patient still reaches their own doctor. --}}
            <div class="border rounded p-3 mb-3 bg-light">
                <div class="fw-semibold small mb-2">Stop giving a doctor NEW cases when</div>
                <div class="form-text mb-3">
                    These pause first visits only. A doctor's own check-ins still come through.
                    Leave any field blank to switch that rule off.
                </div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-auto">
                        <label class="form-label small mb-1">They have this many delayed cases</label>
                        <input type="number" min="0" class="form-control form-control-sm" style="max-width:160px"
                               name="new_case_max_delayed_cases"
                               value="{{ old('new_case_max_delayed_cases', $active->config['newCaseMaxDelayedCases'] ?? '') }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-1">counting a case delayed after (hours)</label>
                        <input type="number" step="any" min="1" class="form-control form-control-sm" style="max-width:160px"
                               name="new_case_delayed_after_hours"
                               value="{{ old('new_case_delayed_after_hours', $active->config['newCaseDelayedAfterHours'] ?? '') }}">
                    </div>
                </div>
                <div class="form-text mb-3">
                    Delayed is measured on how long a case has sat in the queue, not on unread
                    messages. Both fields are needed for this rule to apply.
                </div>

                <div>
                    <label class="form-label small mb-1">They have this many cases awaiting a reply</label>
                    <input type="number" min="0" class="form-control form-control-sm" style="max-width:160px"
                           name="new_case_max_awaiting_reply"
                           value="{{ old('new_case_max_awaiting_reply', $active->config['newCaseMaxAwaitingReply'] ?? '') }}">
                    <div class="form-text">
                        Awaiting a reply means the newest patient message is newer than the doctor's
                        newest reply. Opening a case does not clear it, only replying does.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="hidden" name="require_recorded_licensure" value="0">
                    <input class="form-check-input" type="checkbox" id="require_recorded_licensure"
                           name="require_recorded_licensure" value="1"
                           {{ old('require_recorded_licensure', $active->config['requireRecordedLicensure'] ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="require_recorded_licensure">
                        Block doctors with no licensed states recorded
                    </label>
                </div>
                <div class="form-text">
                    <strong>Off by default, and this matters.</strong> A doctor with no licensed states
                    recorded currently counts as licensed everywhere, which means the state-licence
                    check does nothing for them. Turn this on once licensed states are actually filled
                    in. Turning it on before that will block every doctor with blank licence data.
                </div>
            </div>

            <hr>

            <h6 class="fw-semibold mb-1">Per-doctor weight</h6>
            <p class="text-muted small">
                Used by weighted allocation, and it also normalises the intelligent score.
                <strong>0 takes a doctor out of routing entirely.</strong> Blank means the default of 1.
            </p>
            <div class="row">
                @foreach($clinicians as $clinician)
                    <div class="col-md-4 mb-2">
                        <label class="form-label small mb-1">{{ $clinician->user->name ?? 'Doctor #' . $clinician->id }}</label>
                        <input type="number" step="any" min="0" class="form-control form-control-sm"
                               name="provider_weights[{{ $clinician->id }}]"
                               placeholder="1"
                               value="{{ old('provider_weights.' . $clinician->id, $active->config['providerWeights'][$clinician->id] ?? '') }}">
                    </div>
                @endforeach
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Why this version</label>
                <input type="text" class="form-control form-control-sm" name="note" maxlength="1000"
                       value="{{ old('note') }}" placeholder="Recorded against the version so the reason survives.">
            </div>

            <button class="btn btn-primary btn-sm">Save as draft</button>
            <span class="text-muted small ms-2">Drafting does not change routing. You activate it separately.</span>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Version history</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Version</th><th>Mode</th><th>Status</th><th>Activated</th><th>Note</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($policies as $policy)
                    <tr>
                        <td class="fw-semibold">v{{ $policy->version }}</td>
                        <td>{{ $policy->modeLabel() }}</td>
                        <td>
                            @if($policy->status === 'ACTIVE')
                                <span class="badge bg-primary">Live</span>
                            @elseif($policy->status === 'DRAFT')
                                <span class="badge bg-secondary">Draft</span>
                            @else
                                <span class="badge bg-light text-muted">Superseded</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $policy->activated_at?->format('M j, Y H:i') ?? ' · ' }}
                            {{ $policy->activatedBy ? ' · ' . $policy->activatedBy->name : '' }}
                        </td>
                        <td class="small">{{ $policy->note }}</td>
                        <td class="text-end">
                            @if($policy->status !== 'ACTIVE')
                                <form method="POST" action="{{ route('admin.routing.activate', $policy->id) }}"
                                      onsubmit="return confirm('Activate v{{ $policy->version }}? This changes which doctor new cases are assigned to.');">
                                    @csrf
                                    <button class="btn btn-outline-primary btn-sm">Activate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
