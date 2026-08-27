@extends('layouts.admin')

@section('title', 'Triage Rule Set')
@section('page-title', 'Triage Rule Set')

@section('content')


{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">
        Disqualifier rules are defined per questionnaire. Any case where a patient selects a disqualifying
        answer is automatically classified <span class="text-danger fw-semibold">Red</span>.
    </p>
    <span class="badge bg-secondary ms-3 flex-shrink-0">{{ $version }}</span>
</div>

{{-- Stats --}}
@php
    $totalDisqOptions = $questionnairesWithRules->sum(function ($q) {
        return $q->disqualifierQuestions->sum(function ($question) {
            return collect($question->options ?? [])->filter(fn($o) => !empty($o['is_disqualify']) || !empty($o['disqualifies']))->count();
        });
    });
@endphp
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-2 py-2 px-3">
                <i class="bi bi-ui-checks text-primary" style="font-size:1.2rem;"></i>
                <div>
                    <div class="fw-bold lh-1">{{ $questionnairesWithRules->count() }}</div>
                    <div class="text-muted" style="font-size:.7rem;">Questionnaires</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-2 py-2 px-3">
                <i class="bi bi-question-circle text-danger" style="font-size:1.2rem;"></i>
                <div>
                    <div class="fw-bold lh-1">{{ $questionnairesWithRules->sum(fn($q) => $q->disqualifierQuestions->count()) }}</div>
                    <div class="text-muted" style="font-size:.7rem;">Screened Questions</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-2 py-2 px-3">
                <i class="bi bi-x-octagon text-danger" style="font-size:1.2rem;"></i>
                <div>
                    <div class="fw-bold lh-1">{{ $totalDisqOptions }}</div>
                    <div class="text-muted" style="font-size:.7rem;">Disqualifying Options</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-2 py-2 px-3">
                <i class="bi bi-shield-check text-success" style="font-size:1.2rem;"></i>
                <div>
                    <div class="fw-bold lh-1">{{ $questionnairesWithoutRules->count() }}</div>
                    <div class="text-muted" style="font-size:.7rem;">Baseline Only</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════ Per-questionnaire rule tables ═══════════════ --}}
@forelse($questionnairesWithRules as $questionnaire)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between">
        <div>
            <span class="fw-semibold">{{ $questionnaire->name }}</span>
            <span class="text-muted ms-2" style="font-size:.75rem;">
                {{ $questionnaire->disqualifierQuestions->count() }} question(s) &middot;
                {{ $questionnaire->disqualifierQuestions->sum(fn($q) => collect($q->options ?? [])->filter(fn($o) => !empty($o['is_disqualify']))->count()) }} rules
                &middot; all trigger <span class="text-danger">Red</span>
            </span>
        </div>
        <button class="btn btn-sm btn-primary py-1"
                onclick="openAddModal({{ $questionnaire->id }}, '{{ addslashes($questionnaire->name) }}', {{ $questionnaire->questions->toJson() }})">
            <i class="bi bi-plus-lg me-1"></i>Add Rule
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.83rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:22%;">Question Key</th>
                        <th style="width:30%;">Question</th>
                        <th>Disqualifying Option</th>
                        <th style="width:8%;">Result</th>
                        <th style="width:10%;">Status</th>
                        <th class="text-end pe-3" style="width:10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($questionnaire->disqualifierQuestions as $question)
                        @php
                            $dqOpts = collect($question->options ?? [])->filter(fn($o) => !empty($o['is_disqualify']) || !empty($o['disqualifies']))->values();
                        @endphp
                        @foreach($dqOpts as $optIdx => $opt)
                        <tr>
                            @if($optIdx === 0)
                            <td class="ps-3" rowspan="{{ $dqOpts->count() }}" style="border-right:1px solid #f0f0f0;vertical-align:top;padding-top:10px;">
                                <code style="font-size:.78rem;color:#5b6b7c;">{{ $question->key }}</code>
                                <div class="text-muted" style="font-size:.68rem;">Step {{ $question->step_number }}</div>
                            </td>
                            <td rowspan="{{ $dqOpts->count() }}" class="text-muted" style="border-right:1px solid #f0f0f0;vertical-align:top;padding-top:10px;font-size:.78rem;">
                                {{ Str::limit($question->question, 70) }}
                            </td>
                            @endif
                            <td class="fw-semibold">{{ $opt['value'] }}</td>
                            <td>
                                <span class="badge" style="background:#fdf0f0;color:#c0392b;border:1px solid #f5c6c6;font-size:.7rem;">
                                    <i class="bi bi-circle-fill me-1" style="font-size:.45rem;vertical-align:1px;"></i>Red
                                </span>
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('admin.triage-rules.option.toggle', $question->id) }}"
                                      style="display:inline;">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="option_value" value="{{ $opt['value'] }}">
                                    <button type="submit"
                                            class="btn btn-sm py-0 px-2 {{ ($opt['is_disqualify'] ?? false) ? 'btn-success' : 'btn-secondary' }}"
                                            style="font-size:.72rem;">
                                        {{ ($opt['is_disqualify'] ?? false) ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1"
                                        style="font-size:.72rem;"
                                        onclick="openEditModal({{ $question->id }}, '{{ addslashes($opt['value']) }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST"
                                      action="{{ route('admin.triage-rules.option.destroy', $question->id) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Remove \'{{ addslashes($opt['value']) }}\' from disqualifiers?')">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="option_value" value="{{ $opt['value'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.72rem;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
<div class="alert alert-info small">
    <i class="bi bi-info-circle me-2"></i>
    No questionnaires have disqualifying options defined yet.
</div>
@endforelse

{{-- Questionnaires with no rules --}}
@if($questionnairesWithoutRules->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-dash-circle text-muted"></i>
        <span class="text-muted small fw-semibold me-2">Baseline Only (no disqualifier rules):</span>
        @foreach($questionnairesWithoutRules as $q)
        <span class="badge bg-light text-secondary border" style="font-size:.75rem;">{{ $q->name }}</span>
        @endforeach
    </div>
</div>
@endif

{{-- Config-managed rules --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-lock text-muted"></i>
        <span class="fw-semibold text-muted small">Config-Managed Rules</span>
        <span class="text-muted" style="font-size:.71rem;">(managed in <code>config/triage.php</code>)</span>
    </div>
    <div class="card-body py-3 px-3">
        <div class="row g-3">
            <div class="col-md-6">
                <p class="fw-semibold small mb-2">
                    <i class="bi bi-shield-check me-1 text-muted"></i>Identity Verification
                    <span class="text-muted fw-normal" style="font-size:.71rem;">(patient.id_verified_status)</span>
                </p>
                <div class="d-flex flex-column gap-1" style="font-size:.8rem;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.68rem;">PASS</span>
                        <span class="text-muted">Status is: <code>{{ implode(', ', $cfg['id_verification']['cleared']) }}</code></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:.68rem;">YELLOW</span>
                        <span class="text-muted">Unverified / unknown status</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.68rem;">RED</span>
                        <span class="text-muted">Status is: <code>{{ implode(', ', $cfg['id_verification']['failed_values']) }}</code></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <p class="fw-semibold small mb-2">
                    <i class="bi bi-pause-circle me-1 text-muted"></i>Workflow Hold
                    <span class="text-muted fw-normal" style="font-size:.71rem;">(case.hold_status)</span>
                </p>
                <div class="d-flex align-items-center gap-2" style="font-size:.8rem;">
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:.68rem;">{{ strtoupper($cfg['hold_is_at_least']) }}</span>
                    <span class="text-muted">Any case on hold is elevated to at least <strong>{{ strtoupper($cfg['hold_is_at_least']) }}</strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- How it works (collapsed) --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-2 px-3 d-flex align-items-center justify-content-between"
         style="cursor:pointer;" onclick="toggleInfo()">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle text-muted small"></i>
            <span class="fw-semibold small">How Triage Rules Work</span>
        </div>
        <i class="bi bi-chevron-down text-muted small" id="infoChevron"></i>
    </div>
    <div id="infoBody" style="display:none;">
        <div class="card-body py-3 px-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <p class="fw-semibold small mb-1"><i class="bi bi-diagram-3 me-1 text-primary"></i>Evaluation Order</p>
                    <ol class="text-muted small ps-3 mb-0" style="font-size:.8rem;">
                        <li class="mb-1"><strong>Questionnaire disqualifiers</strong> — any answer with is_disqualify=true → <span class="text-danger">Red</span></li>
                        <li class="mb-1">Identity Verification status (config)</li>
                        <li>Workflow Hold flag (config)</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <p class="fw-semibold small mb-1"><i class="bi bi-shield-exclamation me-1 text-warning"></i>Severity Priority</p>
                    <p class="text-muted mb-1" style="font-size:.8rem;">
                        <span class="text-danger fw-semibold">Red</span> &gt;
                        <span class="text-warning fw-semibold">Yellow</span> &gt;
                        <span class="text-success fw-semibold">Green</span>.
                        All signals are recorded even if a higher band already wins.
                    </p>
                    <p class="text-muted mb-0" style="font-size:.8rem;">
                        Use <strong>Add Rule</strong> to mark an existing question option as disqualifying,
                        or the <strong>Active</strong> toggle to temporarily suspend a rule without deleting it.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════ Add Rule Modal ═══════════════ --}}
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-semibold" id="addRuleModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add Disqualifier Rule
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRuleForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Questionnaire</label>
                        <div id="addModalQuestionnaireName" class="form-control bg-light text-muted" style="font-size:.85rem;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Question <span class="text-danger">*</span></label>
                        <select id="addModalQuestion" class="form-select form-select-sm" required>
                            <option value="">— Select a question —</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold small">Disqualifying Option Value <span class="text-danger">*</span></label>
                        <input type="text" name="option_value" id="addModalOptionValue"
                               class="form-control form-control-sm" placeholder="e.g. Semaglutide" required>
                        <div class="form-text small">
                            If this value already exists as an option on the question, it will be flagged as disqualifying.
                            Otherwise it will be added as a new option.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════ Edit Rule Modal ═══════════════ --}}
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-labelledby="editRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-semibold" id="editRuleModalLabel">
                    <i class="bi bi-pencil me-2"></i>Edit Disqualifier Rule
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRuleForm" method="POST" action="">
                @csrf @method('PUT')
                <div class="modal-body">
                    <input type="hidden" name="old_value" id="editOldValue">
                    <div class="mb-1">
                        <label class="form-label fw-semibold small">Option Value <span class="text-danger">*</span></label>
                        <input type="text" name="new_value" id="editNewValue"
                               class="form-control form-control-sm" required>
                        <div class="form-text small">Renames this option across the question's option list.</div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-floppy me-1"></i>Update Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    // ── Info toggle ────────────────────────────────────────────────────────
    window.toggleInfo = function () {
        const body    = document.getElementById('infoBody');
        const chevron = document.getElementById('infoChevron');
        const showing = body.style.display !== 'none';
        body.style.display = showing ? 'none' : '';
        chevron.className  = showing ? 'bi bi-chevron-down text-muted small' : 'bi bi-chevron-up text-muted small';
    };

    // ── Add Rule Modal ─────────────────────────────────────────────────────
    const addForm       = document.getElementById('addRuleForm');
    const addQName      = document.getElementById('addModalQuestionnaireName');
    const addQSelect    = document.getElementById('addModalQuestion');
    const addOptionVal  = document.getElementById('addModalOptionValue');
    const storeBaseUrl  = '{{ rtrim(route("admin.triage-rules.index"), "/") }}/option/';

    window.openAddModal = function (qId, qName, questions) {
        addQName.textContent = qName;
        addQSelect.innerHTML = '<option value="">— Select a question —</option>';
        addOptionVal.value   = '';

        questions.forEach(function (q) {
            const opt = document.createElement('option');
            opt.value       = q.id;
            opt.textContent = (q.key || ('Q' + q.id)) + ' — ' + q.question.substring(0, 60);
            addQSelect.appendChild(opt);
        });

        // Update form action when question changes
        addQSelect.onchange = function () {
            if (this.value) {
                addForm.action = storeBaseUrl + this.value;
            }
        };

        bootstrap.Modal.getOrCreateInstance(document.getElementById('addRuleModal')).show();
    };

    addForm.addEventListener('submit', function (e) {
        if (!addQSelect.value) {
            e.preventDefault();
            addQSelect.classList.add('is-invalid');
            return;
        }
        addQSelect.classList.remove('is-invalid');
        addForm.action = storeBaseUrl + addQSelect.value;
    });

    // ── Edit Modal ─────────────────────────────────────────────────────────
    const editForm     = document.getElementById('editRuleForm');
    const editOldValue = document.getElementById('editOldValue');
    const editNewValue = document.getElementById('editNewValue');
    const updateBaseUrl = '{{ rtrim(route("admin.triage-rules.index"), "/") }}/option/';

    window.openEditModal = function (questionId, optionValue) {
        editOldValue.value = optionValue;
        editNewValue.value = optionValue;
        editForm.action    = updateBaseUrl + questionId;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editRuleModal')).show();
    };
})();
</script>
@endsection
