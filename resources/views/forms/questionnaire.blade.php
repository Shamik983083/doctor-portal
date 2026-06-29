<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $questionnaire->name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        .form-step { display: none; }
        .form-step.active { display: block; }
        .progress { height: 4px; border-radius: 0; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width:700px">
    <div class="card shadow-sm border-0">

        {{-- Header --}}
        <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
            <h4 class="mb-1 fw-semibold">{{ $questionnaire->name }}</h4>
            @if($questionnaire->description)
                <p class="text-muted small mb-0">{{ $questionnaire->description }}</p>
            @endif

            @if($totalSteps > 1)
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted" id="step-indicator">Step 1 of {{ $totalSteps }}</small>
                    <small class="text-muted" id="step-pct">{{ round(100 / $totalSteps) }}%</small>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-primary" id="progress-bar"
                         style="width:{{ round(100 / $totalSteps) }}%"></div>
                </div>
            </div>
            @endif
        </div>

        {{-- Body --}}
        <div class="card-body px-4 py-4">

            @if($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif

            <form method="POST" action="{{ route('forms.submit', $questionnaire->uuid) }}" id="questionnaire-form">
                @csrf
                {{-- Pass partner tracking params through --}}
                <input type="hidden" name="partner_token" value="{{ request()->query('partner_token') }}">
                <input type="hidden" name="external_id"   value="{{ request()->query('external_id') }}">

                @php $stepIndex = 0; @endphp
                @foreach($grouped as $stepNum => $stepQuestions)
                <div class="form-step {{ $stepIndex === 0 ? 'active' : '' }}" data-step="{{ $stepIndex }}">

                    @if($totalSteps > 1)
                    <h6 class="text-muted fw-semibold text-uppercase small mb-3 border-bottom pb-2"
                        style="letter-spacing:.05em">
                        Step {{ $stepNum }}
                    </h6>
                    @endif

                    @foreach($stepQuestions as $q)
                    @php $fieldName = 'answers[' . $q->id . ']'; @endphp
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            {{ $q->question }}
                            @if($q->is_required)
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            @endif
                        </label>

                        @switch($q->type)

                            @case('textarea')
                                <textarea name="{{ $fieldName }}"
                                          class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                          placeholder="{{ $q->placeholder }}"
                                          rows="4"
                                          {{ $q->is_required ? 'required' : '' }}
                                          {{ $q->is_readonly ? 'readonly' : '' }}>{{ old('answers.' . $q->id) }}</textarea>
                                @break

                            @case('select')
                                <select name="{{ $fieldName }}"
                                        class="form-select @error('answers.' . $q->id) is-invalid @enderror"
                                        {{ $q->is_required ? 'required' : '' }}>
                                    <option value="">{{ $q->placeholder ?: 'Select an option…' }}</option>
                                    @foreach($q->options ?? [] as $opt)
                                        <option value="{{ $opt['value'] }}"
                                            {{ old('answers.' . $q->id) === $opt['value'] ? 'selected' : '' }}>
                                            {{ $opt['value'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @break

                            @case('multiselect')
                                <select name="{{ $fieldName }}[]"
                                        class="form-select @error('answers.' . $q->id) is-invalid @enderror"
                                        multiple {{ $q->is_required ? 'required' : '' }}>
                                    @foreach($q->options ?? [] as $opt)
                                        <option value="{{ $opt['value'] }}"
                                            {{ in_array($opt['value'], (array) old('answers.' . $q->id, [])) ? 'selected' : '' }}>
                                            {{ $opt['value'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Hold Ctrl / Cmd to select multiple.</div>
                                @break

                            @case('radio')
                                @foreach($q->options ?? [] as $opt)
                                <div class="form-check">
                                    <input class="form-check-input @error('answers.' . $q->id) is-invalid @enderror"
                                           type="radio"
                                           name="{{ $fieldName }}"
                                           value="{{ $opt['value'] }}"
                                           id="q{{ $q->id }}_{{ $loop->index }}"
                                           {{ $q->is_required ? 'required' : '' }}
                                           {{ old('answers.' . $q->id) === $opt['value'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="q{{ $q->id }}_{{ $loop->index }}">
                                        {{ $opt['value'] }}
                                    </label>
                                </div>
                                @endforeach
                                @break

                            @case('checkbox')
                                @foreach($q->options ?? [] as $opt)
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="{{ $fieldName }}[]"
                                           value="{{ $opt['value'] }}"
                                           id="q{{ $q->id }}_{{ $loop->index }}"
                                           {{ in_array($opt['value'], (array) old('answers.' . $q->id, [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="q{{ $q->id }}_{{ $loop->index }}">
                                        {{ $opt['value'] }}
                                    </label>
                                </div>
                                @endforeach
                                @break

                            @case('date')
                                <input type="date"
                                       name="{{ $fieldName }}"
                                       class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                       value="{{ old('answers.' . $q->id) }}"
                                       {{ $q->is_required ? 'required' : '' }}>
                                @break

                            @case('number')
                            @case('height')
                            @case('weight')
                            @case('bmi')
                                <input type="number"
                                       name="{{ $fieldName }}"
                                       class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                       placeholder="{{ $q->placeholder }}"
                                       value="{{ old('answers.' . $q->id) }}"
                                       step="any"
                                       {{ $q->is_required ? 'required' : '' }}
                                       {{ $q->is_readonly ? 'readonly' : '' }}>
                                @break

                            @case('email')
                                <input type="email"
                                       name="{{ $fieldName }}"
                                       class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                       placeholder="{{ $q->placeholder }}"
                                       value="{{ old('answers.' . $q->id) }}"
                                       {{ $q->is_required ? 'required' : '' }}>
                                @break

                            @case('file')
                                <input type="file"
                                       name="{{ $fieldName }}"
                                       class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                       {{ $q->is_required ? 'required' : '' }}>
                                @break

                            @case('hidden')
                                <input type="hidden" name="{{ $fieldName }}"
                                       value="{{ old('answers.' . $q->id) }}">
                                @break

                            @default
                                <input type="text"
                                       name="{{ $fieldName }}"
                                       class="form-control @error('answers.' . $q->id) is-invalid @enderror"
                                       placeholder="{{ $q->placeholder }}"
                                       value="{{ old('answers.' . $q->id) }}"
                                       {{ $q->is_required ? 'required' : '' }}
                                       {{ $q->is_readonly ? 'readonly' : '' }}>

                        @endswitch

                        @error('answers.' . $q->id)
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    @endforeach

                </div>
                @php $stepIndex++; @endphp
                @endforeach

                {{-- Navigation --}}
                @if($totalSteps > 1)
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary" id="btn-prev" style="display:none">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <div></div>
                    <button type="button" class="btn btn-primary" id="btn-next">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-submit" style="display:none">
                        <i class="bi bi-check-lg me-1"></i>Submit
                    </button>
                </div>
                @else
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg me-1"></i>Submit
                    </button>
                </div>
                @endif

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var totalSteps = {{ $totalSteps }};
    if (totalSteps <= 1) return;

    var steps       = document.querySelectorAll('.form-step');
    var btnPrev     = document.getElementById('btn-prev');
    var btnNext     = document.getElementById('btn-next');
    var btnSubmit   = document.getElementById('btn-submit');
    var indicator   = document.getElementById('step-indicator');
    var pct         = document.getElementById('step-pct');
    var progressBar = document.getElementById('progress-bar');
    var current     = 0;

    function showStep(n) {
        steps.forEach(function (s, i) {
            s.classList.toggle('active', i === n);
        });
        var pctVal = Math.round(((n + 1) / totalSteps) * 100);
        indicator.textContent  = 'Step ' + (n + 1) + ' of ' + totalSteps;
        pct.textContent        = pctVal + '%';
        progressBar.style.width = pctVal + '%';
        btnPrev.style.display  = n === 0 ? 'none' : '';

        var isLast = n === totalSteps - 1;
        btnNext.style.display   = isLast ? 'none' : '';
        btnSubmit.style.display = isLast ? '' : 'none';
    }

    function validateCurrent() {
        var step   = steps[current];
        var valid  = true;
        /* clear previous invalid markers */
        step.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });

        step.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (inp) {
            var ok;
            if (inp.type === 'radio') {
                ok = !!step.querySelector('input[name="' + inp.name + '"]:checked');
            } else if (inp.type === 'checkbox') {
                ok = !!step.querySelector('input[name="' + inp.name + '"]:checked');
            } else {
                ok = inp.value.trim() !== '';
            }
            if (!ok) {
                inp.classList.add('is-invalid');
                valid = false;
            }
        });
        return valid;
    }

    btnNext.addEventListener('click', function () {
        if (validateCurrent()) showStep(++current);
        else window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    btnPrev.addEventListener('click', function () { showStep(--current); });

    showStep(0);
})();
</script>
</body>
</html>
