@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')

@if(session('success'))
<div class="alert alert-dismissible fade show d-flex align-items-center gap-2 mb-4 shadow-sm border-0 rounded-3"
     role="alert"
     style="background:#f0fdf4;border-left:4px solid #2dc653!important;color:#1a6b31;">
    <i class="bi bi-check-circle-fill" style="color:#2dc653;font-size:1rem;flex-shrink:0;"></i>
    <span style="font-size:.875rem;font-weight:500;">{{ session('success') }}</span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size:.75rem;"></button>
</div>
@endif

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf

    <div class="row g-4">

        {{-- ══════ SLA TARGETS ══════════════════════════════════════════════ --}}
        <div class="col-xl-8 col-lg-7">
            <div class="s-card">
                <div class="s-section-hd">
                    <div class="s-icon" style="--c:#4361ee1a;--fc:#4361ee;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <h6 class="s-section-title">Case SLA Targets</h6>
                        <p class="s-section-desc">
                            Deadlines for case pickup, review, and end-to-end completion.
                            Not to be confused with
                            <a href="{{ route('admin.routing.sla') }}">Provider Pull SLA</a>,
                            which gates whether a provider may pull more cases from the pool.
                        </p>
                    </div>
                </div>

                <div class="s-section-body">
                    <div class="row g-3">
                        @foreach($slaSettings as $setting)
                        <div class="col-md-4">
                            <div class="sla-card @error($setting->key) has-error @enderror">
                                <span class="sla-label">{{ $setting->label }}</span>
                                @if($setting->description)
                                    <span class="sla-desc">{{ $setting->description }}</span>
                                @endif
                                <div class="sla-stepper-wrap">
                                    <button type="button" class="sla-step-btn"
                                            onclick="stepSla('{{ $setting->key }}', -1)">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number"
                                           id="sla_{{ $setting->key }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}"
                                           min="1"
                                           max="{{ $setting->key === 'sla_total_hours' ? 720 : 168 }}"
                                           class="sla-step-input"
                                           required>
                                    <button type="button" class="sla-step-btn"
                                            onclick="stepSla('{{ $setting->key }}', 1)">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <span class="sla-unit">hours</span>
                                @error($setting->key)
                                    <span class="sla-err">{{ $message }}</span>
                                @enderror
                                @if($setting->key === 'sla_review_hours')
                                    <span class="sla-note">
                                        <i class="bi bi-info-circle me-1"></i>Shown to clinicians as their review deadline
                                    </span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ SLA INFO PANEL ═══════════════════════════════════════════ --}}
        <div class="col-xl-4 col-lg-5">
            <div class="info-panel h-100">
                <p class="info-panel-title">How SLA Targets Work</p>

                <div class="info-step">
                    <div class="info-num" style="--bg:#4361ee1a;--fc:#4361ee;">1</div>
                    <div>
                        <p class="info-step-hd">Queue Pickup</p>
                        <p class="info-step-bd">Starts at case submission. Tracks how fast the waiting queue is cleared by clinicians claiming cases.</p>
                    </div>
                </div>
                <div class="info-step">
                    <div class="info-num" style="--bg:#ffc1071a;--fc:#e6a800;">2</div>
                    <div>
                        <p class="info-step-hd">Review &amp; Approval</p>
                        <p class="info-step-bd">Starts when assigned to a clinician. This is the primary SLA shown on the clinician dashboard.</p>
                    </div>
                </div>
                <div class="info-step" style="margin-bottom:0;">
                    <div class="info-num" style="--bg:#2dc6531a;--fc:#2dc653;">3</div>
                    <div>
                        <p class="info-step-hd">End-to-End</p>
                        <p class="info-step-bd">Total turnaround from creation to completion. Visible to the patient and partner.</p>
                    </div>
                </div>

                <div class="info-divider"></div>

                <div class="info-badge-row">
                    <span class="s-pill green">On Track</span>
                    <span>Less than 70% of deadline elapsed</span>
                </div>
                <div class="info-badge-row">
                    <span class="s-pill amber">At Risk</span>
                    <span>Between 70–100% of deadline elapsed</span>
                </div>
                <div class="info-badge-row">
                    <span class="s-pill red">Breached</span>
                    <span>Past the deadline — immediate attention needed</span>
                </div>
            </div>
        </div>

        {{-- ══════ CLINICAL DEFAULTS ════════════════════════════════════════ --}}
        <div class="col-12">
            <div class="s-card">
                <div class="s-section-hd">
                    <div class="s-icon" style="--c:#2dc6531a;--fc:#2dc653;">
                        <i class="bi bi-file-medical"></i>
                    </div>
                    <div>
                        <h6 class="s-section-title">Clinical Defaults</h6>
                        <p class="s-section-desc">Pre-populated values applied to new prescription workflows. Clinicians can override these per case.</p>
                    </div>
                </div>
                <div class="s-section-body">
                    <div class="row">
                        <div class="col-xl-6 col-lg-7">
                            <label class="s-field-label" for="medical_necessity_preset">Medical Necessity Default Text</label>
                            <p class="s-field-hint">Pre-fills the Medical Necessity field on every prescription form. Clinicians can edit or clear it before submitting.</p>
                            <textarea id="medical_necessity_preset"
                                      name="medical_necessity_preset"
                                      class="form-control @error('medical_necessity_preset') is-invalid @enderror"
                                      rows="4"
                                      maxlength="2000"
                                      placeholder="e.g. Patient meets clinical criteria for the requested medication based on the submitted intake and triage findings.">{{ old('medical_necessity_preset', $medicalNecessityPreset) }}</textarea>
                            @error('medical_necessity_preset')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="s-char-count">Max 2,000 characters</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ COMMUNICATION ════════════════════════════════════════════ --}}
        <div class="col-12">
            <div class="s-card">
                <div class="s-section-hd">
                    <div class="s-icon" style="--c:#9c27b01a;--fc:#9c27b0;">
                        <i class="bi bi-chat-square-dots"></i>
                    </div>
                    <div>
                        <h6 class="s-section-title">Communication</h6>
                        <p class="s-section-desc">Controls how inbound patient messages are routed within the platform.</p>
                    </div>
                </div>
                <div class="s-section-body">
                    <label class="s-field-label mb-3">Message Routing Mode</label>
                    <div class="routing-options">
                        @foreach(['direct' => ['label'=>'Direct','desc'=>"Messages go directly to the assigned clinician's inbox.",'icon'=>'bi-person-check'], 'pool' => ['label'=>'Pool','desc'=>'Messages surface in a shared inbox (stub — no behaviour change yet).','icon'=>'bi-people']] as $val => $opt)
                        <label class="routing-card {{ $messageRoutingMode === $val ? 'is-selected' : '' }}"
                               for="mrm_{{ $val }}"
                               onclick="document.querySelectorAll('.routing-card').forEach(el=>el.classList.remove('is-selected'));this.classList.add('is-selected')">
                            <input type="radio" name="message_routing_mode"
                                   id="mrm_{{ $val }}" value="{{ $val }}"
                                   {{ $messageRoutingMode === $val ? 'checked' : '' }}>
                            <i class="bi {{ $opt['icon'] }} routing-icon"></i>
                            <span class="routing-label">{{ $opt['label'] }}</span>
                            <span class="routing-desc">{{ $opt['desc'] }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ SAVE BAR ══════════════════════════════════════════════════ --}}
        <div class="col-12">
            <div class="save-bar">
                <span class="save-bar-note">
                    <i class="bi bi-lightning-charge-fill me-1" style="color:#f9a825;"></i>
                    Changes take effect immediately across all clinician dashboards.
                </span>
                <button type="submit" class="btn btn-primary save-btn">
                    <i class="bi bi-floppy me-2"></i>Save Settings
                </button>
            </div>
        </div>

    </div>
</form>

@endsection

@section('scripts')
<script>
function stepSla(key, delta) {
    var el = document.getElementById('sla_' + key);
    if (!el) return;
    var val = parseInt(el.value, 10) || 1;
    el.value = Math.max(parseInt(el.min, 10), Math.min(parseInt(el.max, 10), val + delta));
}
</script>
<style>
/* ─── Card / Section shell ───────────────────────────────────────────────── */
.s-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
    overflow: hidden;
}
.s-section-hd {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 20px 24px;
    background: #fafbfc;
    border-bottom: 1px solid #f0f2f5;
}
.s-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--c);
    color: var(--fc);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.s-section-title {
    font-size: .9rem; font-weight: 700;
    color: #1a1d23; margin: 0 0 3px;
}
.s-section-desc {
    font-size: .78rem; color: #6c757d;
    margin: 0; line-height: 1.55;
}
.s-section-desc a { color: #4361ee; text-decoration: none; }
.s-section-desc a:hover { text-decoration: underline; }
.s-section-body { padding: 24px; }
.s-field-label { font-size: .83rem; font-weight: 650; color: #2c3040; display: block; }
.s-field-hint { font-size: .76rem; color: #6c757d; margin: 3px 0 10px; line-height: 1.5; }
.s-char-count { font-size: .7rem; color: #adb5bd; margin: 5px 0 0; }

/* ─── SLA Stepper Cards ──────────────────────────────────────────────────── */
.sla-card {
    background: #f8f9fb;
    border: 1.5px solid #eef0f4;
    border-radius: 12px;
    padding: 18px 16px 14px;
    display: flex; flex-direction: column;
    height: 100%;
    transition: border-color .18s, box-shadow .18s;
}
.sla-card:hover { border-color: #c0c8f5; box-shadow: 0 2px 8px rgba(67,97,238,.07); }
.sla-card.has-error { border-color: #dc3545; }
.sla-label {
    font-size: .8rem; font-weight: 700; color: #2c3040; margin-bottom: 4px;
}
.sla-desc {
    font-size: .72rem; color: #868e96;
    line-height: 1.5; flex: 1; margin-bottom: 14px;
}
.sla-stepper-wrap {
    display: flex; align-items: center;
    background: #fff;
    border: 1.5px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    width: fit-content;
}
.sla-step-btn {
    border: none; background: none;
    padding: 7px 11px; cursor: pointer;
    color: #4361ee; font-size: .9rem;
    transition: background .12s;
    line-height: 1;
}
.sla-step-btn:hover  { background: #4361ee12; }
.sla-step-btn:active { background: #4361ee22; }
.sla-step-input {
    border: none;
    border-left: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    width: 58px; text-align: center;
    font-size: 1rem; font-weight: 700; color: #1a1d23;
    padding: 6px 4px; outline: none;
    -moz-appearance: textfield;
}
.sla-step-input::-webkit-inner-spin-button,
.sla-step-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.sla-unit {
    font-size: .68rem; color: #6c757d;
    font-weight: 600; text-transform: uppercase;
    letter-spacing: .05em; margin-top: 6px;
}
.sla-note, .sla-err {
    font-size: .7rem; margin-top: 8px; line-height: 1.4;
}
.sla-note { color: #6c757d; }
.sla-err  { color: #dc3545; }

/* ─── Info panel ─────────────────────────────────────────────────────────── */
.info-panel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
    padding: 22px 20px 18px;
}
.info-panel-title {
    font-size: .88rem; font-weight: 700; color: #1a1d23; margin-bottom: 16px;
}
.info-step {
    display: flex; gap: 12px;
    align-items: flex-start; margin-bottom: 14px;
}
.info-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--bg); color: var(--fc);
    font-size: .72rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.info-step-hd { font-size: .8rem; font-weight: 650; color: #2c3040; margin: 0 0 2px; }
.info-step-bd { font-size: .73rem; color: #6c757d; margin: 0; line-height: 1.5; }
.info-divider { border-top: 1px solid #f0f2f5; margin: 16px 0; }
.info-badge-row {
    display: flex; align-items: center; gap: 8px;
    font-size: .74rem; color: #6c757d; margin-bottom: 8px;
}
.s-pill {
    display: inline-block; padding: 2px 9px;
    border-radius: 20px; font-size: .67rem;
    font-weight: 600; white-space: nowrap; letter-spacing: .02em;
}
.s-pill.green { background: #2dc6531a; color: #1a7a30; }
.s-pill.amber { background: #ffc1071a; color: #9a6e00; }
.s-pill.red   { background: #dc35451a; color: #b02a37; }

/* ─── Routing Cards ──────────────────────────────────────────────────────── */
.routing-options { display: flex; gap: 12px; flex-wrap: wrap; }
.routing-card {
    flex: 1 1 200px; max-width: 300px;
    border: 1.5px solid #eef0f4;
    border-radius: 12px; padding: 18px 16px;
    cursor: pointer; background: #fff;
    display: flex; flex-direction: column; gap: 4px;
    position: relative;
    transition: border-color .18s, background .18s, box-shadow .18s;
}
.routing-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.routing-card:hover    { border-color: #a0aeff; background: #f8f9ff; }
.routing-card.is-selected {
    border-color: #4361ee;
    background: #4361ee09;
    box-shadow: 0 0 0 3px #4361ee18;
}
.routing-icon {
    font-size: 1.5rem; color: #adb5bd;
    margin-bottom: 6px; transition: color .18s;
}
.routing-card.is-selected .routing-icon { color: #4361ee; }
.routing-label { font-size: .88rem; font-weight: 700; color: #1a1d23; }
.routing-desc  { font-size: .74rem; color: #6c757d; line-height: 1.5; }

/* ─── Save bar ───────────────────────────────────────────────────────────── */
.save-bar {
    display: flex; align-items: center; gap: 16px;
    background: #fff;
    border-radius: 14px;
    padding: 16px 24px;
    box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
}
.save-bar-note {
    font-size: .78rem; color: #6c757d; margin-right: auto;
}
.save-btn { font-size: .875rem; font-weight: 600; padding: 8px 22px; border-radius: 8px; }

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 991.98px) {
    .routing-card { max-width: 100%; }
    .save-bar { flex-direction: column; align-items: stretch; }
    .save-bar-note { margin-right: 0; text-align: center; }
    .save-btn { width: 100%; }
}
@media (max-width: 575.98px) {
    .s-section-hd { padding: 16px; }
    .s-section-body { padding: 16px; }
    .sla-stepper-wrap { width: 100%; justify-content: center; }
    .sla-card { align-items: center; text-align: center; }
}
</style>
@endsection
