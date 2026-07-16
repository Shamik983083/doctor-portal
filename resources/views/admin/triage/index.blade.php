@extends('layouts.admin')

@section('title', 'Triage Rule Set')
@section('page-title', 'Triage Rule Set')

@section('content')

{{-- Flash --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Page header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <p class="text-muted mb-0" style="font-size:.85rem;">
            Define which clinical signals push a case to <span class="text-warning fw-semibold">Yellow</span> or
            <span class="text-danger fw-semibold">Red</span> review priority.
            Rules are evaluated automatically when a case is submitted via the partner API.
        </p>
    </div>
    <span class="badge bg-secondary ms-3 flex-shrink-0">{{ $version }}</span>
</div>

{{-- Stats row --}}
<div class="row g-3 mb-4">
    @php
        $statCards = [
            ['label' => 'BMI Rules',     'count' => $bmiRules->count(),      'icon' => 'bi-speedometer',      'color' => '#4361ee'],
            ['label' => 'Age Rules',     'count' => $ageRules->count(),      'icon' => 'bi-person-badge',     'color' => '#7209b7'],
            ['label' => 'Keywords',      'count' => $keywordRules->count(),  'icon' => 'bi-chat-square-text', 'color' => '#e63946'],
            ['label' => 'Offerings',     'count' => $offeringRules->count(), 'icon' => 'bi-capsule',          'color' => '#2dc653'],
        ];
    @endphp
    @foreach($statCards as $s)
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:{{ $s['color'] }}1a;">
                    <i class="bi {{ $s['icon'] }}" style="color:{{ $s['color'] }};font-size:1.1rem;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 lh-1">{{ $s['count'] }}</div>
                    <div class="text-muted" style="font-size:.73rem;">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ═══════════════════════════════ BMI Thresholds ═══════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:#4361ee1a;">
                <i class="bi bi-speedometer" style="color:#4361ee;font-size:.9rem;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold">BMI Thresholds</h6>
                <p class="text-muted mb-0" style="font-size:.71rem;">
                    Matches <code>patient.bmi</code> from the case API payload
                </p>
            </div>
        </div>
        <button class="btn btn-sm btn-primary btn-add-rule" data-type="bmi_threshold">
            <i class="bi bi-plus-lg me-1"></i>Add BMI Rule
        </button>
    </div>
    <div class="card-body p-0">
        @if($bmiRules->isEmpty())
            <p class="text-muted text-center py-4 mb-0"><i class="bi bi-info-circle me-1"></i>No BMI rules defined.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Label</th>
                        <th>Condition</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bmiRules as $rule)
                    <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
                        <td class="ps-4 fw-semibold">{{ $rule->label }}</td>
                        <td>
                            <span class="font-monospace text-secondary">
                                BMI {{ $rule->operatorSymbol() }} {{ $rule->value }}
                            </span>
                        </td>
                        <td>@include('admin.triage._result_badge', ['result' => $rule->triage_result])</td>
                        <td>@include('admin.triage._active_toggle', ['rule' => $rule])</td>
                        <td class="text-end pe-4">@include('admin.triage._actions', ['rule' => $rule])</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════ Age Thresholds ═══════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:#7209b71a;">
                <i class="bi bi-person-badge" style="color:#7209b7;font-size:.9rem;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold">Age Thresholds</h6>
                <p class="text-muted mb-0" style="font-size:.71rem;">
                    Matches <code>patient.age</code> (falls back to <code>patient.date_of_birth</code>)
                </p>
            </div>
        </div>
        <button class="btn btn-sm btn-primary btn-add-rule" data-type="age_threshold">
            <i class="bi bi-plus-lg me-1"></i>Add Age Rule
        </button>
    </div>
    <div class="card-body p-0">
        @if($ageRules->isEmpty())
            <p class="text-muted text-center py-4 mb-0"><i class="bi bi-info-circle me-1"></i>No age rules defined.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Label</th>
                        <th>Condition</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ageRules as $rule)
                    <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
                        <td class="ps-4 fw-semibold">{{ $rule->label }}</td>
                        <td>
                            <span class="font-monospace text-secondary">
                                Age {{ $rule->operatorSymbol() }} {{ $rule->value }}
                            </span>
                        </td>
                        <td>@include('admin.triage._result_badge', ['result' => $rule->triage_result])</td>
                        <td>@include('admin.triage._active_toggle', ['rule' => $rule])</td>
                        <td class="text-end pe-4">@include('admin.triage._actions', ['rule' => $rule])</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ════════════════════════════ Red Flag Keywords ════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:#e639461a;">
                <i class="bi bi-chat-square-text" style="color:#e63946;font-size:.9rem;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold">Red Flag Keywords</h6>
                <p class="text-muted mb-0" style="font-size:.71rem;">
                    Case-insensitive substring match over <code>questionnaire_responses[].answers[].answer</code>
                </p>
            </div>
        </div>
        <button class="btn btn-sm btn-primary btn-add-rule" data-type="keyword">
            <i class="bi bi-plus-lg me-1"></i>Add Keyword
        </button>
    </div>
    <div class="card-body p-0">
        @if($keywordRules->isEmpty())
            <p class="text-muted text-center py-4 mb-0"><i class="bi bi-info-circle me-1"></i>No keyword rules defined.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Label</th>
                        <th>Pattern</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($keywordRules->sortBy(['triage_result', 'sort_order']) as $rule)
                    <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
                        <td class="ps-4 fw-semibold">{{ $rule->label }}</td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">{{ $rule->value }}</span>
                        </td>
                        <td>@include('admin.triage._result_badge', ['result' => $rule->triage_result])</td>
                        <td>@include('admin.triage._active_toggle', ['rule' => $rule])</td>
                        <td class="text-end pe-4">@include('admin.triage._actions', ['rule' => $rule])</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════ Elevated Offerings ═══════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:#2dc6531a;">
                <i class="bi bi-capsule" style="color:#2dc653;font-size:.9rem;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold">Elevated Offerings</h6>
                <p class="text-muted mb-0" style="font-size:.71rem;">
                    Substring match on <code>case_offerings[].offering.name</code> — e.g. flag all compounded GLP-1s
                </p>
            </div>
        </div>
        <button class="btn btn-sm btn-primary btn-add-rule" data-type="offering">
            <i class="bi bi-plus-lg me-1"></i>Add Offering Rule
        </button>
    </div>
    <div class="card-body p-0">
        @if($offeringRules->isEmpty())
            <p class="text-muted text-center py-4 mb-0">
                <i class="bi bi-info-circle me-1"></i>No offering rules — all offerings pass without elevation.
            </p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Label</th>
                        <th>Name Pattern</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($offeringRules as $rule)
                    <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
                        <td class="ps-4 fw-semibold">{{ $rule->label }}</td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace">{{ $rule->value }}</span>
                        </td>
                        <td>@include('admin.triage._result_badge', ['result' => $rule->triage_result])</td>
                        <td>@include('admin.triage._active_toggle', ['rule' => $rule])</td>
                        <td class="text-end pe-4">@include('admin.triage._actions', ['rule' => $rule])</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════ Config-Managed Rules (read-only reference) ══════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:34px;height:34px;background:#6c757d1a;">
            <i class="bi bi-lock" style="color:#6c757d;font-size:.9rem;"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-semibold text-muted">Config-Managed Rules</h6>
            <p class="text-muted mb-0" style="font-size:.71rem;">
                These rules use multi-value set logic and are managed in <code>config/triage.php</code>
            </p>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            {{-- IDV --}}
            <div class="col-md-6">
                <p class="fw-semibold small mb-2">
                    <i class="bi bi-shield-check me-1 text-muted"></i>
                    Identity Verification
                    <span class="text-muted" style="font-size:.71rem;">(patient.id_verified_status)</span>
                </p>
                <div class="d-flex flex-column gap-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;">PASS</span>
                        <span class="text-muted small">Status is: <code>{{ implode(', ', $cfg['id_verification']['cleared']) }}</code></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:.7rem;">YELLOW</span>
                        <span class="text-muted small">Unverified / unknown status</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">RED</span>
                        <span class="text-muted small">Status is: <code>{{ implode(', ', $cfg['id_verification']['failed_values']) }}</code></span>
                    </div>
                </div>
            </div>
            {{-- Hold --}}
            <div class="col-md-6">
                <p class="fw-semibold small mb-2">
                    <i class="bi bi-pause-circle me-1 text-muted"></i>
                    Workflow Hold
                    <span class="text-muted" style="font-size:.71rem;">(case.hold_status)</span>
                </p>
                <div class="d-flex align-items-center gap-2">
                    @if($cfg['hold_is_at_least'] === 'red')
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">RED</span>
                    @else
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:.7rem;">YELLOW</span>
                    @endif
                    <span class="text-muted small">Any case with an active hold is elevated to at least <strong>{{ strtoupper($cfg['hold_is_at_least']) }}</strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════ Add / Edit Modal ═══════════════════════════════ --}}
<div class="modal fade" id="ruleModal" tabindex="-1" aria-labelledby="ruleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-semibold" id="ruleModalLabel">
                    <i class="bi bi-funnel me-2"></i>Add Triage Rule
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ruleForm" method="POST" action="{{ route('admin.triage-rules.store') }}">
                @csrf
                <input type="hidden" name="_method" id="ruleFormMethod" value="POST">

                <div class="modal-body">
                    {{-- Rule Type --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Rule Type <span class="text-danger">*</span></label>
                        <select name="type" id="ruleType" class="form-select" required>
                            <option value="">— Select rule type —</option>
                            <option value="bmi_threshold">BMI Threshold  (patient.bmi)</option>
                            <option value="age_threshold">Age Threshold  (patient.age)</option>
                            <option value="keyword">Red Flag Keyword  (questionnaire answers)</option>
                            <option value="offering">Elevated Offering  (offering name)</option>
                        </select>
                        <div class="form-text" id="typeHelp"></div>
                    </div>

                    {{-- Threshold: operator + value --}}
                    <div id="thresholdRow" style="display:none;">
                        <div class="row g-3 mb-4">
                            <div class="col-5">
                                <label class="form-label fw-semibold">Operator <span class="text-danger">*</span></label>
                                <select name="operator" id="ruleOperator" class="form-select">
                                    <option value="gte">≥ at or above</option>
                                    <option value="lte">≤ at or below</option>
                                    <option value="gt">&gt; above (strict)</option>
                                    <option value="lt">&lt; below (strict)</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <label class="form-label fw-semibold">Threshold Value <span class="text-danger">*</span></label>
                                <input type="number" name="value" id="ruleValueNum"
                                       class="form-control" step="0.1" min="0"
                                       placeholder="e.g. 40">
                            </div>
                        </div>
                    </div>

                    {{-- Keyword / Offering: text value --}}
                    <div id="textRow" style="display:none;">
                        <div class="mb-4">
                            <label class="form-label fw-semibold" id="textValueLabel">Pattern <span class="text-danger">*</span></label>
                            <input type="text" name="value" id="ruleValueText"
                                   class="form-control" placeholder="">
                            <div class="form-text" id="textValueHelp"></div>
                        </div>
                    </div>

                    {{-- Triage Result --}}
                    <div id="resultRow" style="display:none;">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Triage Result <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="triage_result"
                                           value="yellow" id="resultYellow">
                                    <label class="form-check-label" for="resultYellow">
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Yellow — Elevated Review
                                        </span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="triage_result"
                                           value="red" id="resultRed">
                                    <label class="form-check-label" for="resultRed">
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                            <i class="bi bi-exclamation-circle me-1"></i>Red — Urgent Review
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Label & Active (shown when type selected) --}}
                    <div id="metaRow" style="display:none;">
                        <hr class="my-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">
                                    Label
                                    <span class="text-muted fw-normal">(optional — auto-generated if blank)</span>
                                </label>
                                <input type="text" name="label" id="ruleLabel"
                                       class="form-control" placeholder="e.g. Critical BMI threshold">
                                <div class="form-text">Appears in the case triage reasons log.</div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           name="is_active" id="ruleActive" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="ruleActive">Active</label>
                                </div>
                                <div class="form-text">Inactive rules are saved but not evaluated.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ruleSubmitBtn">
                        <i class="bi bi-floppy me-1"></i>Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════ How It Works info panel (collapsible) ═══════════════ --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between"
         style="cursor:pointer;" onclick="toggleInfo()">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle text-muted"></i>
            <span class="fw-semibold small">How Triage Rules Work</span>
        </div>
        <i class="bi bi-chevron-down text-muted" id="infoChevron"></i>
    </div>
    <div id="infoBody" style="display:none;">
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <p class="fw-semibold small mb-2"><i class="bi bi-diagram-3 me-1 text-primary"></i>Evaluation Order</p>
                    <ol class="text-muted small ps-3 mb-0">
                        <li class="mb-1">BMI Threshold rules against <code>patient.bmi</code></li>
                        <li class="mb-1">Age Threshold rules against <code>patient.age</code></li>
                        <li class="mb-1">Identity Verification (config-managed)</li>
                        <li class="mb-1">Workflow Hold flag (config-managed)</li>
                        <li class="mb-1">Elevated Offering rules against offering names</li>
                        <li class="mb-1">Keyword rules against all questionnaire answers</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <p class="fw-semibold small mb-2"><i class="bi bi-shield-exclamation me-1 text-warning"></i>Severity Priority</p>
                    <p class="text-muted small mb-2">
                        All matching rules are evaluated. The <strong>highest severity</strong> result wins:<br>
                        <span class="text-danger fw-semibold">Red</span> &gt; <span class="text-warning fw-semibold">Yellow</span> &gt; <span class="text-success fw-semibold">Green</span>.
                    </p>
                    <p class="text-muted small mb-2">
                        All matching rule reasons are recorded in the case's triage log regardless of final band.
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-clock-history me-1"></i>
                        Rule changes take effect within 5 minutes (cached). Re-triaging existing cases requires a manual action.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{-- Partials are inlined below to avoid extra file dependencies --}}
<script>
(function () {
    // ── Info toggle ────────────────────────────────────────────────────────
    window.toggleInfo = function () {
        const body    = document.getElementById('infoBody');
        const chevron = document.getElementById('infoChevron');
        const showing = body.style.display !== 'none';
        body.style.display    = showing ? 'none' : '';
        chevron.className     = showing ? 'bi bi-chevron-down text-muted' : 'bi bi-chevron-up text-muted';
    };

    // ── Modal helpers ──────────────────────────────────────────────────────
    const modalEl       = document.getElementById('ruleModal');
    const ruleForm      = document.getElementById('ruleForm');
    const ruleMethod    = document.getElementById('ruleFormMethod');
    const modalTitle    = document.getElementById('ruleModalLabel');
    const ruleType      = document.getElementById('ruleType');
    const thresholdRow  = document.getElementById('thresholdRow');
    const textRow       = document.getElementById('textRow');
    const resultRow     = document.getElementById('resultRow');
    const metaRow       = document.getElementById('metaRow');
    const ruleValueNum  = document.getElementById('ruleValueNum');
    const ruleValueText = document.getElementById('ruleValueText');
    const ruleLabel     = document.getElementById('ruleLabel');
    const ruleActive    = document.getElementById('ruleActive');
    const typeHelp      = document.getElementById('typeHelp');
    const textValueLabel = document.getElementById('textValueLabel');
    const textValueHelp  = document.getElementById('textValueHelp');
    const storeUrl = '{{ route('admin.triage-rules.store') }}';

    const TYPE_HELP = {
        bmi_threshold: 'Applies to the <strong>patient.bmi</strong> field in the case API payload.',
        age_threshold: 'Applies to <strong>patient.age</strong> (falls back to date_of_birth) in the case API payload.',
        keyword:       'Scans all <strong>questionnaire_responses[].answers[].answer</strong> values for this substring.',
        offering:      'Scans <strong>case_offerings[].offering.name</strong> for this substring.',
    };

    function updateFields(type) {
        const isThreshold = ['bmi_threshold', 'age_threshold'].includes(type);
        const isText      = ['keyword', 'offering'].includes(type);
        const hasType     = isThreshold || isText;

        thresholdRow.style.display = isThreshold ? '' : 'none';
        textRow.style.display      = isText      ? '' : 'none';
        resultRow.style.display    = hasType     ? '' : 'none';
        metaRow.style.display      = hasType     ? '' : 'none';
        typeHelp.innerHTML         = TYPE_HELP[type] || '';

        // Toggle required on value inputs
        ruleValueNum.required  = isThreshold;
        ruleValueText.required = isText;
        ruleValueNum.name      = isThreshold ? 'value' : '';
        ruleValueText.name     = isText      ? 'value' : '';

        if (type === 'bmi_threshold') {
            ruleValueNum.step        = '0.1';
            ruleValueNum.placeholder = 'e.g. 40 or 18.5';
        } else {
            ruleValueNum.step        = '1';
            ruleValueNum.placeholder = 'e.g. 65';
        }

        if (type === 'keyword') {
            textValueLabel.innerHTML = 'Keyword / Pattern <span class="text-danger">*</span>';
            textValueHelp.textContent = 'Case-insensitive. Partial match — "pregnan" catches "pregnant", "pregnancy", etc.';
            ruleValueText.placeholder = 'e.g. pregnan';
        } else if (type === 'offering') {
            textValueLabel.innerHTML = 'Offering Name Pattern <span class="text-danger">*</span>';
            textValueHelp.textContent = 'Case-insensitive substring match against offering.name in the case payload.';
            ruleValueText.placeholder = 'e.g. semaglutide';
        }
    }

    ruleType.addEventListener('change', () => updateFields(ruleType.value));

    function openModal(mode, data) {
        const isEdit = mode === 'edit';

        modalTitle.innerHTML = isEdit
            ? '<i class="bi bi-pencil-square me-2"></i>Edit Triage Rule'
            : '<i class="bi bi-funnel me-2"></i>Add Triage Rule';

        ruleForm.action  = isEdit ? data.route : storeUrl;
        ruleMethod.value = isEdit ? 'PUT' : 'POST';

        // Reset form
        ruleType.value      = '';
        ruleValueNum.value  = '';
        ruleValueText.value = '';
        ruleLabel.value     = '';
        ruleActive.checked  = true;
        document.querySelectorAll('input[name="triage_result"]').forEach(r => r.checked = false);
        updateFields('');

        if (data.type) {
            ruleType.value = data.type;
            updateFields(data.type);
        }

        if (isEdit) {
            ruleValueNum.value  = data.value || '';
            ruleValueText.value = data.value || '';
            ruleLabel.value     = data.label || '';
            ruleActive.checked  = data.active === '1';
            document.querySelector('input[name="triage_result"][value="' + data.result + '"]').checked = true;
            if (data.operator) {
                document.getElementById('ruleOperator').value = data.operator;
            }
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // "Add" buttons per section
    document.querySelectorAll('.btn-add-rule').forEach(btn => {
        btn.addEventListener('click', () => openModal('add', { type: btn.dataset.type }));
    });

    // Edit buttons
    document.querySelectorAll('.btn-edit-rule').forEach(btn => {
        btn.addEventListener('click', () => openModal('edit', {
            route:    btn.dataset.route,
            type:     btn.dataset.type,
            operator: btn.dataset.operator,
            value:    btn.dataset.value,
            result:   btn.dataset.result,
            label:    btn.dataset.label,
            active:   btn.dataset.active,
        }));
    });
})();
</script>
@endsection
