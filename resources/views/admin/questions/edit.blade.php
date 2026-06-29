@extends('layouts.admin')

@section('title', 'Edit Question')
@section('page-title', 'Edit Question')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.questions.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Question Bank
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('admin.questions.update', $question->id) }}">
@csrf @method('PUT')

<div class="row g-4">

    {{-- Left: Settings --}}
    <div class="col-lg-4">
        <div class="card sticky-top" style="top:1rem">
            <div class="card-header"><h6 class="mb-0">Question Settings</h6></div>
            <div class="card-body">

                {{-- Questionnaire --}}
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assigned To (Questionnaire) <span class="text-danger">*</span></label>
                    <select name="questionnaire_id" class="form-select form-select-sm" required>
                        @foreach($questionnaires as $q)
                            <option value="{{ $q->id }}" {{ old('questionnaire_id', $question->questionnaire_id) == $q->id ? 'selected' : '' }}>
                                {{ $q->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Field Type --}}
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Field Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select form-select-sm" id="field-type" required>
                        @foreach($fieldTypes as $id => $label)
                            <option value="{{ $id }}" {{ old('type', $question->type) === $id ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Is Required --}}
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Is Required?</label>
                    <select name="is_required" class="form-select form-select-sm">
                        <option value="1" {{ old('is_required', $question->is_required ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('is_required', $question->is_required ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>

                {{-- Is Readonly --}}
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Is Readonly?</label>
                    <select name="is_readonly" class="form-select form-select-sm">
                        <option value="0" {{ old('is_readonly', $question->is_readonly ? '1' : '0') === '0' ? 'selected' : '' }}>No</option>
                        <option value="1" {{ old('is_readonly', $question->is_readonly ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>

                {{-- Status --}}
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="1" {{ old('is_active', $question->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('is_active', $question->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.questions.index') }}" class="btn btn-outline-secondary flex-grow-1">Cancel</a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Update
                </button>
            </div>
        </div>
    </div>

    {{-- Right: Question content --}}
    <div class="col-lg-8">

        {{-- Basic Information --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Basic Information</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Question / Label <span class="text-danger">*</span></label>
                    <input type="text" name="question" class="form-control"
                           value="{{ old('question', $question->question) }}"
                           placeholder="e.g. What was your sex assigned at birth?" required>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">
                            Field Key
                            <span class="text-muted fw-normal ms-1" style="font-size:.7rem">
                                (optional — leave blank to auto-generate)
                            </span>
                        </label>
                        <input type="text" name="key" class="form-control form-control-sm"
                               value="{{ old('key', $question->key) }}"
                               placeholder="e.g. patient_gender">
                    </div>

                    <div class="col-md-6 placeholder-wrap {{ in_array($question->type, $placeholderTypes) ? '' : 'd-none' }}">
                        <label class="form-label small fw-semibold">Placeholder</label>
                        <input type="text" name="placeholder" class="form-control form-control-sm"
                               value="{{ old('placeholder', $question->placeholder) }}"
                               placeholder="Enter placeholder text...">
                    </div>
                </div>
            </div>
        </div>

        {{-- Options --}}
        <div class="card options-card {{ in_array($question->type, $optionTypes) ? '' : 'd-none' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Options</h6>
                <span class="text-muted small">Toggle <strong>Disqualify</strong> to exclude patient if they pick that option.</span>
            </div>
            <div class="card-body">
                <div id="options-list">
                    @php
                        $opts = old('options', $question->options ?? []);
                        // normalize: options may be old() format [{value,is_disqualify}] or raw array
                    @endphp
                    @foreach($opts as $idx => $opt)
                    @php
                        $val   = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                        $disq  = is_array($opt) && !empty($opt['is_disqualify']);
                        $disqId = 'disq_' . $idx;
                    @endphp
                    <div class="option-row d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-grip-vertical text-muted" style="cursor:grab"></i>
                        <input type="text" name="options[{{ $idx }}][value]"
                               class="form-control form-control-sm"
                               placeholder="Option text" value="{{ $val }}">
                        <div class="form-check form-switch d-flex align-items-center gap-1 text-nowrap ms-1">
                            <input class="form-check-input mt-0" type="checkbox"
                                   name="options[{{ $idx }}][is_disqualify]"
                                   value="1" id="{{ $disqId }}" {{ $disq ? 'checked' : '' }}>
                            <label class="form-check-label small text-danger mb-0" for="{{ $disqId }}">Disqualify</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-opt px-2">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    @endforeach
                </div>
                <button type="button" id="add-opt" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="bi bi-plus me-1"></i>Add Option
                </button>
            </div>
        </div>

    </div>
</div>
</form>

@endsection

@section('scripts')
<script>
(function () {
    var OPTION_TYPES      = @json($optionTypes);
    var PLACEHOLDER_TYPES = @json($placeholderTypes);
    var optIdx = {{ count($question->options ?? []) }};

    var typeSelect    = document.getElementById('field-type');
    var optionsCard   = document.querySelector('.options-card');
    var placeholderW  = document.querySelector('.placeholder-wrap');
    var optionsList   = document.getElementById('options-list');
    var addOptBtn     = document.getElementById('add-opt');

    function applyType(type) {
        if (OPTION_TYPES.indexOf(type) !== -1) {
            optionsCard.classList.remove('d-none');
        } else {
            optionsCard.classList.add('d-none');
        }
        if (PLACEHOLDER_TYPES.indexOf(type) !== -1) {
            placeholderW.classList.remove('d-none');
        } else {
            placeholderW.classList.add('d-none');
        }
    }

    typeSelect.addEventListener('change', function () { applyType(this.value); });
    applyType(typeSelect.value);

    function optionRowHtml(idx) {
        var disqId = 'disq_edit_' + idx;
        return '<div class="option-row d-flex align-items-center gap-2 mb-2">'
            + '<i class="bi bi-grip-vertical text-muted" style="cursor:grab"></i>'
            + '<input type="text" name="options[' + idx + '][value]" class="form-control form-control-sm" placeholder="Option text">'
            + '<div class="form-check form-switch d-flex align-items-center gap-1 text-nowrap ms-1">'
            +   '<input class="form-check-input mt-0" type="checkbox" name="options[' + idx + '][is_disqualify]" value="1" id="' + disqId + '">'
            +   '<label class="form-check-label small text-danger mb-0" for="' + disqId + '">Disqualify</label>'
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-danger remove-opt px-2"><i class="bi bi-x"></i></button>'
            + '</div>';
    }

    addOptBtn.addEventListener('click', function () {
        optionsList.insertAdjacentHTML('beforeend', optionRowHtml(optIdx++));
        attachRemove(optionsList.lastElementChild);
    });

    function attachRemove(row) {
        row.querySelector('.remove-opt').addEventListener('click', function () { row.remove(); });
    }

    optionsList.querySelectorAll('.option-row').forEach(attachRemove);
})();
</script>
@endsection
