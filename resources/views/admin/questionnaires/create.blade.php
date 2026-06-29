@extends('layouts.admin')

@section('title', 'Create Questionnaire')
@section('page-title', 'Create Questionnaire')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.questionnaires.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Questionnaires
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('admin.questionnaires.store') }}">
@csrf
<div class="row g-4">

    {{-- Left: Meta --}}
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 1rem;">
            <div class="card-header"><h6 class="mb-0">Details</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           placeholder="e.g. Weight Loss Intake Form" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Optional description...">{{ old('description') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Partner</label>
                    <select name="partner_id" class="form-select">
                        <option value="">Global (not partner-specific)</option>
                        @foreach($partners as $p)
                            <option value="{{ $p->id }}" {{ old('partner_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Assign to a specific partner, or leave blank for global use.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Form Mode</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" value="single"
                                   id="mode-single" {{ old('mode', 'single') === 'single' ? 'checked' : '' }}>
                            <label class="form-check-label small" for="mode-single">Single Page</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" value="multi"
                                   id="mode-multi" {{ old('mode', 'single') === 'multi' ? 'checked' : '' }}>
                            <label class="form-check-label small" for="mode-multi">Multi-Step</label>
                        </div>
                    </div>
                    <div class="form-text">Multi-step shows questions grouped by step number.</div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                               id="is_active" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold small" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.questionnaires.index') }}" class="btn btn-outline-secondary flex-grow-1">Cancel</a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Save
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
@include('admin.questionnaires._builder_js', ['existingQuestions' => []])
@endsection
