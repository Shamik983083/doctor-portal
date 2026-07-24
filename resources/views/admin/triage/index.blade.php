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

{{-- Page header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <p class="text-muted mb-0" style="font-size:.85rem;">
            Disqualifier rules are defined per questionnaire. Any case where a patient selects a disqualifying answer
            is automatically classified <span class="text-danger fw-semibold">Red</span>.
            To add, remove, or change a disqualifying option, edit the question in the
            <a href="{{ route('admin.questions.index') }}" class="text-decoration-none">Question Bank</a>.
        </p>
    </div>
    <span class="badge bg-secondary ms-3 flex-shrink-0">{{ $version }}</span>
</div>

{{-- Stats row --}}
@php
    $totalDisqQuestions = $questionnairesWithRules->sum(fn($q) => $q->disqualifierQuestions->count());
    $totalDisqOptions   = $questionnairesWithRules->sum(function ($q) {
        return $q->disqualifierQuestions->sum(function ($question) {
            return collect($question->options ?? [])->filter(fn($o) => !empty($o['is_disqualify']) || !empty($o['disqualifies']))->count();
        });
    });
@endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:#4361ee1a;">
                    <i class="bi bi-ui-checks" style="color:#4361ee;font-size:1.1rem;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 lh-1">{{ $questionnairesWithRules->count() }}</div>
                    <div class="text-muted" style="font-size:.73rem;">Questionnaires</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:#e639461a;">
                    <i class="bi bi-question-circle" style="color:#e63946;font-size:1.1rem;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 lh-1">{{ $totalDisqQuestions }}</div>
                    <div class="text-muted" style="font-size:.73rem;">Screened Questions</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:#e639461a;">
                    <i class="bi bi-x-octagon" style="color:#e63946;font-size:1.1rem;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 lh-1">{{ $totalDisqOptions }}</div>
                    <div class="text-muted" style="font-size:.73rem;">Disqualifying Options</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:#2dc6531a;">
                    <i class="bi bi-shield-check" style="color:#2dc653;font-size:1.1rem;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 lh-1">{{ $questionnairesWithoutRules->count() }}</div>
                    <div class="text-muted" style="font-size:.73rem;">Baseline Only</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════ Per-questionnaire disqualifier sections ═══════════════ --}}
@forelse($questionnairesWithRules as $questionnaire)
@php
    $colors = ['#4361ee','#2f9e73','#c98a2f','#a7566b','#7b5ea7','#e63946'];
    $color  = $colors[$loop->index % count($colors)];
@endphp
<div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid {{ $color }} !important;">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:34px;height:34px;background:{{ $color }}1a;">
                <i class="bi bi-ui-checks" style="color:{{ $color }};font-size:.9rem;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-semibold">{{ $questionnaire->name }}</h6>
                <p class="text-muted mb-0" style="font-size:.71rem;">
                    {{ $questionnaire->disqualifierQuestions->count() }} screened
                    {{ Str::plural('question', $questionnaire->disqualifierQuestions->count()) }}
                    &middot; all disqualifying answers → <span class="text-danger fw-semibold">Red</span>
                </p>
            </div>
        </div>
        <a href="{{ route('admin.questions.index') }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit in Question Bank
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:22%">Question Key</th>
                        <th style="width:28%">Question</th>
                        <th>Disqualifying Options → <span class="text-danger">Red</span></th>
                        <th style="width:12%">Safe Options</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($questionnaire->disqualifierQuestions as $question)
                    @php
                        $disqOpts = collect($question->options ?? [])->filter(fn($o) => !empty($o['is_disqualify']) || !empty($o['disqualifies']));
                        $safeOpts = collect($question->options ?? [])->reject(fn($o) => !empty($o['is_disqualify']) || !empty($o['disqualifies']));
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <code class="text-secondary" style="font-size:.8rem;">{{ $question->key }}</code>
                            <div class="text-muted" style="font-size:.7rem;">Step {{ $question->step_number }}</div>
                        </td>
                        <td class="text-muted small">{{ $question->question }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($disqOpts as $opt)
                                <span class="badge rounded-pill"
                                      style="background:#fdf0f0;color:#8a3b3b;border:1px solid #f0d8d8;font-size:.72rem;font-weight:500;">
                                    <i class="bi bi-x-circle me-1" style="font-size:.65rem;"></i>{{ $opt['value'] }}
                                </span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <span class="text-muted small">{{ $safeOpts->count() }} safe</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    No questionnaires have disqualifying options defined yet.
    Go to the <a href="{{ route('admin.questions.index') }}" class="alert-link">Question Bank</a>
    and mark options as disqualifying to define the rule set.
</div>
@endforelse

{{-- ═══════════════ Questionnaires with no disqualifier rules ═══════════════ --}}
@if($questionnairesWithoutRules->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:34px;height:34px;background:#6c757d1a;">
            <i class="bi bi-dash-circle" style="color:#6c757d;font-size:.9rem;"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-semibold text-muted">No Disqualifier Rules Yet</h6>
            <p class="text-muted mb-0" style="font-size:.71rem;">
                These questionnaires have no disqualifying options — every answer passes the screen
            </p>
        </div>
    </div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach($questionnairesWithoutRules as $q)
            <span class="badge bg-light text-secondary border" style="font-size:.8rem;font-weight:500;">
                <i class="bi bi-ui-checks me-1"></i>{{ $q->name }}
            </span>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ══════════════════ Config-Managed Rules (read-only) ══════════════════ --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:34px;height:34px;background:#6c757d1a;">
            <i class="bi bi-lock" style="color:#6c757d;font-size:.9rem;"></i>
        </div>
        <div>
            <h6 class="mb-0 fw-semibold text-muted">Config-Managed Rules</h6>
            <p class="text-muted mb-0" style="font-size:.71rem;">
                Non-questionnaire signals managed in <code>config/triage.php</code>
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

{{-- ═══════════════════════════════ How It Works ═══════════════════════════════ --}}
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
                        <li class="mb-1">
                            <strong>Questionnaire disqualifiers</strong> — any answer with
                            <code>is_disqualify: true</code> in its question's options → <span class="text-danger">Red</span>
                        </li>
                        <li class="mb-1">Identity Verification status (config-managed)</li>
                        <li class="mb-1">Workflow Hold flag (config-managed)</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <p class="fw-semibold small mb-2"><i class="bi bi-shield-exclamation me-1 text-warning"></i>Severity Priority</p>
                    <p class="text-muted small mb-2">
                        All matching signals are evaluated. The <strong>highest severity</strong> result wins:<br>
                        <span class="text-danger fw-semibold">Red</span> &gt;
                        <span class="text-warning fw-semibold">Yellow</span> &gt;
                        <span class="text-success fw-semibold">Green</span>.
                    </p>
                    <p class="text-muted small mb-2">
                        All matching reasons are recorded in the case's triage log regardless of final band.
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-gear me-1"></i>
                        To change which answers are disqualifying, edit the question's options in the
                        <a href="{{ route('admin.questions.index') }}">Question Bank</a>.
                        Changes take effect immediately on the next case submission.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
window.toggleInfo = function () {
    const body    = document.getElementById('infoBody');
    const chevron = document.getElementById('infoChevron');
    const showing = body.style.display !== 'none';
    body.style.display = showing ? 'none' : '';
    chevron.className  = showing ? 'bi bi-chevron-down text-muted' : 'bi bi-chevron-up text-muted';
};
</script>
@endsection
