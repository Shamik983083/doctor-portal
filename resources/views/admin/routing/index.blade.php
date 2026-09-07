@extends('layouts.admin')

@section('title', 'Case Routing')

@section('content')

{{-- ── Flash messages ─────────────────────────────────────────────────── --}}
@foreach(['success','warning','danger'] as $type)
@if(session($type))
<div class="r-flash r-flash--{{ $type }} mb-4" role="alert">
    <i class="bi {{ $type === 'success' ? 'bi-check-circle-fill' : ($type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill') }}"></i>
    <span>{{ session($type) }}</span>
    <button type="button" onclick="this.parentElement.remove()" class="r-flash-close">&times;</button>
</div>
@endif
@endforeach

{{-- ── Page header ─────────────────────────────────────────────────────── --}}
<div class="r-page-hd mb-4">
    <div>
        <h5 class="r-page-title">Case Routing</h5>
        <p class="r-page-desc">
            Which doctor a new case is auto-assigned to. Every change creates a new version;
            the active version is never edited in place, preserving a full audit trail.
        </p>
    </div>
</div>

{{-- ── Active policy banner ────────────────────────────────────────────── --}}
@if($active)
<div class="r-live-banner mb-4">
    <div class="r-live-pulse"></div>
    <div class="r-live-body">
        <div class="r-live-top">
            <span class="r-badge-live">Live</span>
            <span class="r-live-version">v{{ $active->version }} · {{ $active->modeLabel() }}</span>
        </div>
        <p class="r-live-note">{{ $modeNotes[$active->mode] ?? '' }}</p>
        @if($active->note)
            <p class="r-live-reason">"{{ $active->note }}"</p>
        @endif
    </div>
    <div class="r-live-meta">
        @if($active->activated_at)
            <span class="r-live-when">{{ $active->activated_at->diffForHumans() }}</span>
        @endif
        @if($active->activatedBy)
            <span class="r-live-by">by {{ $active->activatedBy->name }}</span>
        @endif
    </div>
</div>
@else
<div class="r-flash r-flash--warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><strong>No active routing policy.</strong> Cases will wait in the queue until a version is activated.</span>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     DRAFT FORM
══════════════════════════════════════════════════════════════════════ --}}
<form method="POST" action="{{ route('admin.routing.store') }}" id="draftForm">
    @csrf

    {{-- ── 1. Routing modes ──────────────────────────────────────────── --}}
    <div class="r-card mb-4">
        <div class="r-section-hd">
            <div class="r-icon" style="--c:#4361ee1a;--fc:#4361ee;"><i class="bi bi-diagram-3"></i></div>
            <div>
                <h6 class="r-section-title">Routing Modes</h6>
                <p class="r-section-desc">Choose how cases are assigned for each patient path. These two paths are independent.</p>
            </div>
        </div>
        <div class="r-section-body">
            <div class="row g-4">

                {{-- New cases --}}
                <div class="col-lg-6">
                    <p class="r-sublabel">New cases <span class="r-sublabel-tag">First visits</span></p>
                    <div class="r-mode-grid" id="newModeGrid">
                        @foreach($modes as $value => $label)
                        <label class="r-mode-card {{ old('new_mode', $active?->newMode() ?? '') === $value ? 'is-on' : '' }}"
                               for="new_mode_{{ $value }}">
                            <input type="radio" name="new_mode" id="new_mode_{{ $value }}" value="{{ $value }}"
                                   {{ old('new_mode', $active?->newMode() ?? '') === $value ? 'checked' : '' }}
                                   onchange="selectMode('newModeGrid', this)">
                            <span class="r-mode-name">{{ $label }}</span>
                            <span class="r-mode-desc">{{ $modeNotes[$value] ?? '' }}</span>
                            <span class="r-mode-check"><i class="bi bi-check-circle-fill"></i></span>
                        </label>
                        @endforeach
                    </div>
                    @error('new_mode')<p class="r-err">{{ $message }}</p>@enderror
                </div>

                {{-- Check-ins --}}
                <div class="col-lg-6">
                    <p class="r-sublabel">Check-ins that need a new doctor <span class="r-sublabel-tag">Fallback</span></p>
                    <p class="r-sublabel-hint">A check-in goes back to the treating doctor first. This mode applies only when that doctor cannot take it.</p>
                    <div class="r-mode-grid" id="refillModeGrid">
                        @foreach($modes as $value => $label)
                        <label class="r-mode-card {{ old('refill_mode', $active?->refillMode() ?? '') === $value ? 'is-on' : '' }}"
                               for="refill_mode_{{ $value }}">
                            <input type="radio" name="refill_mode" id="refill_mode_{{ $value }}" value="{{ $value }}"
                                   {{ old('refill_mode', $active?->refillMode() ?? '') === $value ? 'checked' : '' }}
                                   onchange="selectMode('refillModeGrid', this)">
                            <span class="r-mode-name">{{ $label }}</span>
                            <span class="r-mode-desc">{{ $modeNotes[$value] ?? '' }}</span>
                            <span class="r-mode-check"><i class="bi bi-check-circle-fill"></i></span>
                        </label>
                        @endforeach
                    </div>
                    @error('refill_mode')<p class="r-err">{{ $message }}</p>@enderror
                </div>

            </div>
        </div>
    </div>

    {{-- ── 2. Provider pool eligibility ──────────────────────────────── --}}
    <div class="r-card mb-4">
        <div class="r-section-hd">
            <div class="r-icon" style="--c:#2dc6531a;--fc:#2dc653;"><i class="bi bi-shield-check"></i></div>
            <div>
                <h6 class="r-section-title">Provider Pool Eligibility</h6>
                <p class="r-section-desc">Checked when a doctor requests work from the pool. Any of these blocks the request and tells them which rule fired. Leave blank to disable a criterion.</p>
            </div>
        </div>
        <div class="r-section-body">
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Max open cases</label>
                    <input type="number" min="1" class="form-control form-control-sm r-input"
                           name="pool_max_outstanding_cases"
                           placeholder="No limit"
                           value="{{ old('pool_max_outstanding_cases', $active->config['poolCriteria']['maxOutstandingCases'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Max overdue cases</label>
                    <input type="number" min="1" class="form-control form-control-sm r-input"
                           name="pool_max_overdue_cases"
                           placeholder="No limit"
                           value="{{ old('pool_max_overdue_cases', $active->config['poolCriteria']['maxOverdueCases'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Overdue after (hours)</label>
                    <input type="number" step="any" min="1" class="form-control form-control-sm r-input"
                           name="pool_overdue_after_hours"
                           placeholder="No limit"
                           value="{{ old('pool_overdue_after_hours', $active->config['poolCriteria']['overdueAfterHours'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Max patients awaiting reply</label>
                    <input type="number" min="1" class="form-control form-control-sm r-input"
                           name="pool_max_awaiting_reply"
                           placeholder="No limit"
                           value="{{ old('pool_max_awaiting_reply', $active->config['poolCriteria']['maxAwaitingReply'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Max cases per request</label>
                    <input type="number" min="1" class="form-control form-control-sm r-input"
                           name="pool_max_per_request"
                           placeholder="No limit"
                           value="{{ old('pool_max_per_request', $active->config['poolCriteria']['maxCasesPerRequest'] ?? '') }}">
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="r-field-label">Max pulled per day</label>
                    <input type="number" min="1" class="form-control form-control-sm r-input"
                           name="pool_max_per_day"
                           placeholder="No limit"
                           value="{{ old('pool_max_per_day', $active->config['poolCriteria']['maxCasesPerDay'] ?? '') }}">
                </div>
            </div>
            <p class="r-hint-text"><i class="bi bi-info-circle me-1"></i>A doctor's own caps still apply on top of these. SLA is separate and set per Doctor Admin.</p>
        </div>
    </div>

    {{-- ── 3. Workload score weights ───────────────────────────────────── --}}
    <div class="r-card mb-4">
        <div class="r-section-hd">
            <div class="r-icon" style="--c:#9c27b01a;--fc:#9c27b0;"><i class="bi bi-bar-chart-steps"></i></div>
            <div>
                <h6 class="r-section-title">Workload Score Weights</h6>
                <p class="r-section-desc">How heavily each signal counts when scoring a doctor's workload. Lower total score = lighter load, so higher weight = more impact. Leave blank for the default shown.</p>
            </div>
        </div>
        <div class="r-section-body">
            <div class="row g-3 mb-4">
                @foreach($weightKeys as $key => $label)
                <div class="col-sm-6 col-md-4 col-xl-3">
                    <label class="r-field-label">{{ $label }}</label>
                    <input type="number" step="any" class="form-control form-control-sm r-input"
                           name="weights[{{ $key }}]"
                           placeholder="{{ $defaults[$key] }}"
                           value="{{ old('weights.' . $key, $active->config['intelligentWeights'][$key] ?? '') }}">
                </div>
                @endforeach
            </div>

            <div class="r-rule-row">
                <label class="r-field-label mb-1">Block a doctor with a message older than (hours)</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="number" step="any" min="0" class="form-control form-control-sm r-input" style="max-width:160px;"
                           name="message_aging_hours"
                           placeholder="Off"
                           value="{{ old('message_aging_hours', $active->config['messageAgingThresholdHours'] ?? '') }}">
                    <span class="r-hint-text mb-0">Leave blank to disable.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 4. New-case blocking criteria ──────────────────────────────── --}}
    <div class="r-card mb-4">
        <div class="r-section-hd">
            <div class="r-icon" style="--c:#ffc1071a;--fc:#e6a800;"><i class="bi bi-slash-circle"></i></div>
            <div>
                <h6 class="r-section-title">New-Case Blocking Criteria</h6>
                <p class="r-section-desc">Stop giving a doctor <strong>new</strong> cases when these thresholds are met. Check-ins to an existing patient still come through. Leave any field blank to disable that criterion.</p>
            </div>
        </div>
        <div class="r-section-body">

            <div class="r-criterion-block mb-4">
                <p class="r-criterion-title"><i class="bi bi-hourglass-split me-2"></i>Delayed cases threshold</p>
                <p class="r-hint-text mb-3">Both fields are required together — a count with no window (or vice versa) has no effect.</p>
                <div class="d-flex flex-wrap gap-3 align-items-end">
                    <div>
                        <label class="r-field-label">Max delayed cases</label>
                        <input type="number" min="0" class="form-control form-control-sm r-input" style="max-width:160px;"
                               name="new_case_max_delayed_cases"
                               placeholder="Off"
                               value="{{ old('new_case_max_delayed_cases', $active->config['newCaseMaxDelayedCases'] ?? '') }}">
                    </div>
                    <div>
                        <label class="r-field-label">Counting delayed after (hours)</label>
                        <input type="number" step="any" min="1" class="form-control form-control-sm r-input" style="max-width:160px;"
                               name="new_case_delayed_after_hours"
                               placeholder="Off"
                               value="{{ old('new_case_delayed_after_hours', $active->config['newCaseDelayedAfterHours'] ?? '') }}">
                    </div>
                </div>
                <p class="r-hint-text mt-2 mb-0">Delayed is measured by how long a case has sat in the queue, not by unread messages.</p>
            </div>

            <div class="r-criterion-block mb-4">
                <p class="r-criterion-title"><i class="bi bi-chat-dots me-2"></i>Awaiting-reply threshold</p>
                <label class="r-field-label">Max cases awaiting a reply</label>
                <input type="number" min="0" class="form-control form-control-sm r-input" style="max-width:160px;"
                       name="new_case_max_awaiting_reply"
                       placeholder="Off"
                       value="{{ old('new_case_max_awaiting_reply', $active->config['newCaseMaxAwaitingReply'] ?? '') }}">
                <p class="r-hint-text mt-2 mb-0">Awaiting a reply means the newest patient message is newer than the doctor's newest reply. Only replying clears it — opening a case does not.</p>
            </div>

            <div class="r-toggle-row">
                <label class="r-toggle-wrap" for="require_recorded_licensure">
                    <input type="hidden" name="require_recorded_licensure" value="0">
                    <input type="checkbox" id="require_recorded_licensure"
                           name="require_recorded_licensure" value="1"
                           {{ old('require_recorded_licensure', $active->config['requireRecordedLicensure'] ?? false) ? 'checked' : '' }}>
                    <span class="r-toggle-label">Block doctors with no licensed states recorded</span>
                </label>
                <p class="r-hint-text mt-2 mb-0">
                    <span class="r-badge-warn">Off by default — read before enabling</span>
                    A doctor with no states recorded currently counts as licensed everywhere. Enable only after licensed states are filled in; enabling first will block every doctor with blank licence data.
                </p>
            </div>

        </div>
    </div>

    {{-- ── 5. Per-doctor weights ───────────────────────────────────────── --}}
    <div class="r-card mb-4">
        <div class="r-section-hd">
            <div class="r-icon" style="--c:#4361ee1a;--fc:#4361ee;"><i class="bi bi-person-badge"></i></div>
            <div>
                <h6 class="r-section-title">Per-Doctor Weights</h6>
                <p class="r-section-desc">Used by weighted allocation and normalises the intelligent score. <strong>0 removes a doctor from routing entirely.</strong> Leave blank for the default of 1.</p>
            </div>
        </div>
        <div class="r-section-body">
            <div class="row g-3">
                @foreach($clinicians as $clinician)
                <div class="col-sm-6 col-md-4 col-xl-3">
                    <label class="r-field-label">{{ $clinician->user->name ?? 'Doctor #' . $clinician->id }}</label>
                    <input type="number" step="any" min="0" class="form-control form-control-sm r-input"
                           name="provider_weights[{{ $clinician->id }}]"
                           placeholder="1"
                           value="{{ old('provider_weights.' . $clinician->id, $active->config['providerWeights'][$clinician->id] ?? '') }}">
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Save bar ────────────────────────────────────────────────────── --}}
    <div class="r-save-bar mb-5">
        <div class="r-save-note-wrap">
            <label class="r-field-label mb-1" for="draftNote">Version note <span class="r-optional">(optional)</span></label>
            <input type="text" id="draftNote" class="form-control form-control-sm r-input" name="note" maxlength="1000"
                   value="{{ old('note') }}"
                   placeholder="Reason for this change — recorded against the version for the audit trail."
                   style="max-width:480px;">
        </div>
        <div class="r-save-actions">
            <span class="r-save-hint">Drafting does not change routing — activate separately below.</span>
            <button type="submit" class="btn btn-primary r-save-btn">
                <i class="bi bi-floppy me-2"></i>Save as Draft
            </button>
        </div>
    </div>

</form>

{{-- ══════════════════════════════════════════════════════════════════════
     VERSION HISTORY
══════════════════════════════════════════════════════════════════════ --}}
<div class="r-card">
    <div class="r-section-hd">
        <div class="r-icon" style="--c:#6c757d1a;--fc:#6c757d;"><i class="bi bi-clock-history"></i></div>
        <div>
            <h6 class="r-section-title">Version History</h6>
            <p class="r-section-desc">Every drafted and activated version. Activating a non-live version immediately changes which doctor new cases are assigned to.</p>
        </div>
    </div>
    <div class="r-section-body p-0">
        <div class="table-responsive">
            <table class="r-table">
                <thead>
                    <tr>
                        <th style="width:72px;">Version</th>
                        <th>Mode</th>
                        <th style="width:100px;">Status</th>
                        <th>Activated</th>
                        <th>Note</th>
                        <th style="width:110px;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($policies as $policy)
                    <tr class="{{ $policy->status === 'ACTIVE' ? 'r-row-live' : '' }}">
                        <td><span class="r-version-num">v{{ $policy->version }}</span></td>
                        <td class="r-td-mode">{{ $policy->modeLabel() }}</td>
                        <td>
                            @if($policy->status === 'ACTIVE')
                                <span class="r-badge-live">Live</span>
                            @elseif($policy->status === 'DRAFT')
                                <span class="r-badge-draft">Draft</span>
                            @else
                                <span class="r-badge-old">Superseded</span>
                            @endif
                        </td>
                        <td class="r-td-meta">
                            @if($policy->activated_at)
                                {{ $policy->activated_at->format('M j, Y · H:i') }}
                                @if($policy->activatedBy)
                                    <span class="r-td-by">{{ $policy->activatedBy->name }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="r-td-note">{{ $policy->note ?: '—' }}</td>
                        <td class="text-end">
                            @if($policy->status !== 'ACTIVE')
                                <form method="POST" action="{{ route('admin.routing.activate', $policy->id) }}"
                                      onsubmit="return confirm('Activate v{{ $policy->version }}?\n\nThis immediately changes which doctor new cases are assigned to.')">
                                    @csrf
                                    <button class="btn btn-outline-primary btn-sm r-activate-btn">
                                        <i class="bi bi-lightning-charge me-1"></i>Activate
                                    </button>
                                </form>
                            @else
                                <span class="r-current-tag">Current</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4" style="font-size:.85rem;">
                            No versions yet. Save a draft above to create the first version.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function selectMode(gridId, input) {
    document.querySelectorAll('#' + gridId + ' .r-mode-card').forEach(function(card) {
        card.classList.remove('is-on');
    });
    input.closest('.r-mode-card').classList.add('is-on');
}
</script>
<style>
/* ─── Page header ────────────────────────────────────────────────────────── */
.r-page-hd    { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
.r-page-title { font-size:1.1rem; font-weight:700; color:#1a1d23; margin:0 0 4px; }
.r-page-desc  { font-size:.8rem; color:#6c757d; margin:0; max-width:640px; line-height:1.6; }

/* ─── Flash ──────────────────────────────────────────────────────────────── */
.r-flash {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; border-radius:10px; font-size:.85rem;
    border-left:4px solid;
}
.r-flash--success { background:#f0fdf4; border-color:#2dc653; color:#1a6b31; }
.r-flash--warning { background:#fffbeb; border-color:#e6a800; color:#7a5800; }
.r-flash--danger  { background:#fef2f2; border-color:#dc3545; color:#891c28; }
.r-flash-close {
    margin-left:auto; background:none; border:none; font-size:1.2rem;
    cursor:pointer; color:inherit; opacity:.6; line-height:1; padding:0 4px;
}
.r-flash-close:hover { opacity:1; }

/* ─── Live banner ────────────────────────────────────────────────────────── */
.r-live-banner {
    display:flex; align-items:center; gap:20px;
    background:#fff; border:1.5px solid #4361ee40;
    border-left:4px solid #4361ee;
    border-radius:12px; padding:18px 20px;
    box-shadow:0 1px 2px rgba(0,0,0,.04), 0 4px 12px rgba(67,97,238,.06);
    flex-wrap:wrap;
}
.r-live-pulse {
    width:10px; height:10px; border-radius:50%; background:#4361ee; flex-shrink:0;
    animation:r-pulse 2s ease-in-out infinite;
}
@keyframes r-pulse {
    0%,100% { box-shadow:0 0 0 0 rgba(67,97,238,.4); }
    50% { box-shadow:0 0 0 6px rgba(67,97,238,0); }
}
.r-live-body { flex:1 1 260px; }
.r-live-top  { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
.r-live-version { font-size:.95rem; font-weight:700; color:#1a1d23; }
.r-live-note   { font-size:.78rem; color:#6c757d; margin:0 0 3px; }
.r-live-reason { font-size:.78rem; color:#6c757d; font-style:italic; margin:0; }
.r-live-meta  { text-align:right; flex-shrink:0; }
.r-live-when  { display:block; font-size:.8rem; font-weight:600; color:#2c3040; }
.r-live-by    { display:block; font-size:.74rem; color:#adb5bd; }

/* ─── Badges ─────────────────────────────────────────────────────────────── */
.r-badge-live {
    display:inline-block; padding:2px 10px; border-radius:20px;
    font-size:.68rem; font-weight:700; letter-spacing:.04em;
    background:#4361ee; color:#fff;
}
.r-badge-draft {
    display:inline-block; padding:2px 10px; border-radius:20px;
    font-size:.68rem; font-weight:700; letter-spacing:.04em;
    background:#6c757d1a; color:#495057;
}
.r-badge-old {
    display:inline-block; padding:2px 10px; border-radius:20px;
    font-size:.68rem; font-weight:700; letter-spacing:.04em;
    background:#f0f1f3; color:#adb5bd;
}
.r-badge-warn {
    display:inline-block; padding:1px 8px; border-radius:10px;
    font-size:.68rem; font-weight:600;
    background:#ffc1071a; color:#9a6e00; margin-right:6px;
}

/* ─── Card / section shell ───────────────────────────────────────────────── */
.r-card {
    background:#fff; border-radius:14px;
    box-shadow:0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
    overflow:hidden;
}
.r-section-hd {
    display:flex; align-items:flex-start; gap:14px;
    padding:20px 24px; background:#fafbfc;
    border-bottom:1px solid #f0f2f5;
}
.r-icon {
    width:40px; height:40px; border-radius:10px;
    background:var(--c); color:var(--fc);
    display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; flex-shrink:0;
}
.r-section-title { font-size:.9rem; font-weight:700; color:#1a1d23; margin:0 0 3px; }
.r-section-desc  { font-size:.78rem; color:#6c757d; margin:0; line-height:1.55; }
.r-section-body  { padding:24px; }

/* ─── Common field elements ──────────────────────────────────────────────── */
.r-field-label { font-size:.78rem; font-weight:650; color:#2c3040; display:block; margin-bottom:4px; }
.r-hint-text   { font-size:.74rem; color:#6c757d; line-height:1.5; margin:0; }
.r-sublabel    { font-size:.82rem; font-weight:700; color:#2c3040; margin:0 0 10px; display:flex; align-items:center; gap:8px; }
.r-sublabel-tag {
    font-size:.64rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em;
    background:#4361ee12; color:#4361ee; padding:2px 7px; border-radius:10px;
}
.r-sublabel-hint { font-size:.74rem; color:#6c757d; margin:0 0 12px; line-height:1.5; }
.r-err  { font-size:.72rem; color:#dc3545; margin:6px 0 0; }
.r-optional { font-size:.72rem; font-weight:400; color:#adb5bd; }
.r-input { border-radius:7px !important; border-color:#e0e3e8 !important; font-size:.84rem !important; }
.r-input:focus { border-color:#4361ee !important; box-shadow:0 0 0 3px #4361ee18 !important; }

/* ─── Routing mode cards ─────────────────────────────────────────────────── */
.r-mode-grid { display:flex; flex-direction:column; gap:8px; }
.r-mode-card {
    display:flex; flex-direction:column; gap:3px;
    position:relative;
    border:1.5px solid #eef0f4; border-radius:10px;
    padding:14px 40px 14px 14px;
    cursor:pointer; background:#fff;
    transition:border-color .15s, background .15s, box-shadow .15s;
}
.r-mode-card input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
.r-mode-card:hover { border-color:#a0aeff; background:#f8f9ff; }
.r-mode-card.is-on  {
    border-color:#4361ee; background:#4361ee09;
    box-shadow:0 0 0 3px #4361ee14;
}
.r-mode-name { font-size:.83rem; font-weight:700; color:#1a1d23; }
.r-mode-desc { font-size:.73rem; color:#6c757d; line-height:1.45; }
.r-mode-check {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    font-size:.95rem; color:#4361ee; opacity:0; transition:opacity .15s;
}
.r-mode-card.is-on .r-mode-check { opacity:1; }

/* ─── Criterion blocks ───────────────────────────────────────────────────── */
.r-criterion-block {
    background:#fafbfc; border:1px solid #f0f2f5;
    border-radius:10px; padding:16px 18px;
}
.r-criterion-title { font-size:.82rem; font-weight:700; color:#2c3040; margin:0 0 6px; }
.r-rule-row { background:#fafbfc; border:1px solid #f0f2f5; border-radius:10px; padding:16px 18px; }

/* ─── Toggle row ─────────────────────────────────────────────────────────── */
.r-toggle-row { display:flex; flex-direction:column; }
.r-toggle-wrap {
    display:flex; align-items:center; gap:10px;
    cursor:pointer; font-size:.85rem; font-weight:600; color:#1a1d23;
}
.r-toggle-wrap input[type="checkbox"] {
    width:16px; height:16px; cursor:pointer; accent-color:#4361ee; flex-shrink:0;
}
.r-toggle-label { user-select:none; }

/* ─── Save bar ───────────────────────────────────────────────────────────── */
.r-save-bar {
    display:flex; align-items:flex-end; gap:20px;
    background:#fff; border-radius:14px;
    padding:20px 24px;
    box-shadow:0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
    flex-wrap:wrap;
}
.r-save-note-wrap { flex:1 1 300px; }
.r-save-actions { display:flex; align-items:center; gap:12px; flex-shrink:0; }
.r-save-hint { font-size:.74rem; color:#6c757d; max-width:200px; line-height:1.4; }
.r-save-btn { font-size:.875rem; font-weight:600; padding:8px 22px; border-radius:8px; white-space:nowrap; }

/* ─── Version history table ──────────────────────────────────────────────── */
.r-table { width:100%; margin:0; border-collapse:separate; border-spacing:0; }
.r-table thead tr { background:#fafbfc; }
.r-table th {
    padding:10px 16px; font-size:.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.05em; color:#6c757d;
    border-bottom:1px solid #f0f2f5; white-space:nowrap;
}
.r-table td {
    padding:14px 16px; font-size:.83rem; color:#2c3040;
    border-bottom:1px solid #f8f9fb; vertical-align:middle;
}
.r-table tbody tr:last-child td { border-bottom:none; }
.r-row-live { background:#4361ee05; }
.r-version-num { font-weight:700; color:#4361ee; font-size:.88rem; }
.r-td-mode { font-size:.83rem; color:#2c3040; max-width:180px; }
.r-td-meta { font-size:.78rem; color:#6c757d; white-space:nowrap; }
.r-td-by   { display:block; font-size:.72rem; color:#adb5bd; margin-top:2px; }
.r-td-note { font-size:.78rem; color:#6c757d; font-style:italic; max-width:220px; }
.r-activate-btn { font-size:.78rem; border-radius:7px; }
.r-current-tag { font-size:.74rem; color:#4361ee; font-weight:600; }

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width:767.98px) {
    .r-section-hd   { padding:16px; }
    .r-section-body  { padding:16px; }
    .r-save-bar      { flex-direction:column; align-items:stretch; }
    .r-save-actions  { flex-direction:column; align-items:stretch; }
    .r-save-hint     { max-width:100%; }
    .r-save-btn      { width:100%; }
    .r-live-meta     { text-align:left; width:100%; }
    .r-live-banner   { flex-direction:column; gap:12px; }
}
@media (max-width:575.98px) {
    .r-mode-card { padding:12px 38px 12px 12px; }
    .r-table th, .r-table td { padding:10px 12px; }
}
</style>
@endsection
