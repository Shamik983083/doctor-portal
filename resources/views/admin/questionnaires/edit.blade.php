@extends('layouts.admin')

@section('title', 'Edit Questionnaire')
@section('page-title', 'Edit: ' . $questionnaire->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.questionnaires.show', $questionnaire->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('admin.questionnaires.update', $questionnaire->id) }}">
@csrf @method('PUT')
<div class="row g-4">

    {{-- Left: Meta --}}
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 1rem;">
            <div class="card-header"><h6 class="mb-0">Details</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $questionnaire->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $questionnaire->description) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Partner</label>
                    <select name="partner_id" class="form-select">
                        <option value="">Global (not partner-specific)</option>
                        @foreach($partners as $p)
                            <option value="{{ $p->id }}"
                                {{ old('partner_id', $questionnaire->partner_id) == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Form Mode</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" value="single"
                                   id="mode-single" {{ old('mode', $questionnaire->mode ?? 'single') === 'single' ? 'checked' : '' }}>
                            <label class="form-check-label small" for="mode-single">Single Page</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" value="multi"
                                   id="mode-multi" {{ old('mode', $questionnaire->mode ?? 'single') === 'multi' ? 'checked' : '' }}>
                            <label class="form-check-label small" for="mode-multi">Multi-Step</label>
                        </div>
                    </div>
                    <div class="form-text">Multi-step shows questions grouped by step number.</div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                               id="is_active" {{ old('is_active', $questionnaire->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold small" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.questionnaires.show', $questionnaire->id) }}" class="btn btn-outline-secondary flex-grow-1">Cancel</a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Update
                </button>
            </div>
        </div>
    </div>

    {{-- Right: Question Builder --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Questions</h6>
                <button type="button" id="add-question" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Add Question
                </button>
            </div>
            <div class="card-body">
                <div id="questions-container"></div>
                <div id="no-questions" class="text-center text-muted py-4">
                    <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                    <span class="small">No questions yet. Click <strong>+ Add Question</strong> to start building.</span>
                </div>
            </div>
        </div>
    </div>

</div>
</form>
@endsection

@section('scripts')
@php
$sortedQuestions = $questionnaire->questions->sortBy('sort_order')->values();

// Build a map of question DB id → array index so we can resolve depends_on_question_id
$idToIdx = $sortedQuestions->pluck(null)->mapWithKeys(fn($q, $i) => [$q->id => $i])->all();

$existingQuestions = $sortedQuestions->map(fn($q, $i) => [
    'question'           => $q->question,
    'key'                => $q->key,
    'type'               => $q->type,
    'placeholder'        => $q->placeholder,
    'is_required'        => $q->is_required,
    'is_readonly'        => $q->is_readonly,
    'step_number'        => $q->step_number ?? 1,
    'options'            => $q->options ?? [],
    'depends_on_idx'     => $q->depends_on_question_id ? ($idToIdx[$q->depends_on_question_id] ?? null) : null,
    'depends_on_operator'=> $q->depends_on_operator,
    'depends_on_value'   => $q->depends_on_value,
])->values()->all();
@endphp
@include('admin.questionnaires._builder_js', ['existingQuestions' => $existingQuestions])
@endsection
