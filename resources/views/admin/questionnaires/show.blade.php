@extends('layouts.admin')

@section('title', $questionnaire->name)
@section('page-title', $questionnaire->name)

@section('content')

@php
$operatorLabels = [
    'equals'      => 'equals',
    'not_equals'  => 'does not equal',
    'is_answered' => 'is answered',
    'contains'    => 'contains',
];
$typeMap = [
    'hidden'      => ['Hidden',      'bg-secondary'],
    'input'       => ['Input',       'bg-secondary'],
    'email'       => ['Email',       'bg-secondary'],
    'textarea'    => ['Textarea',    'bg-secondary'],
    'date'        => ['Date',        'bg-info text-dark'],
    'select'      => ['Select',      'bg-primary'],
    'multiselect' => ['Multi Select','bg-primary'],
    'radio'       => ['Radio',       'bg-warning text-dark'],
    'checkbox'    => ['Checkbox',    'bg-warning text-dark'],
    'file'        => ['File',        'bg-secondary'],
    'number'      => ['Number',      'bg-secondary'],
    'height'      => ['Height',      'bg-success'],
    'weight'      => ['Weight',      'bg-success'],
    'bmi'         => ['BMI',         'bg-success'],
];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('admin.questionnaires.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.questionnaires.edit', $questionnaire->id) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <form method="POST" action="{{ route('admin.questionnaires.destroy', $questionnaire->id) }}"
              onsubmit="return confirm('Delete this questionnaire? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- Left: Meta --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Details</h6>
                @if($questionnaire->is_active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Name</th>
                        <td class="fw-semibold">{{ $questionnaire->name }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Partner</th>
                        <td>{{ $questionnaire->partner?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Questions</th>
                        <td><span class="badge bg-primary">{{ $questionnaire->questions->count() }}</span></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Created</th>
                        <td>{{ $questionnaire->created_at->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Updated</th>
                        <td>{{ $questionnaire->updated_at->format('M d, Y') }}</td>
                    </tr>
                    @if($questionnaire->linkedQuestionnaire)
                    <tr>
                        <th class="text-muted">Includes</th>
                        <td>
                            <span class="badge border"
                                  style="background:#f0fdf4; color:#166534; border-color:#bbf7d0 !important; font-size:.75rem;">
                                <i class="bi bi-link-45deg me-1"></i>{{ $questionnaire->linkedQuestionnaire->name }}
                            </span>
                        </td>
                    </tr>
                    @endif
                </table>
                @if($questionnaire->description)
                    <hr class="my-2">
                    <p class="text-muted small mb-0">{{ $questionnaire->description }}</p>
                @endif
            </div>
        </div>

    </div>

    {{-- Right: Questions --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-list-check me-2"></i>Questions
                    <span class="badge bg-primary ms-1">{{ $questionnaire->questions->count() }}</span>
                </h6>
            </div>

            @forelse($questionnaire->questions as $i => $q)
            @php
                [$typeLabel, $typeBadge] = $typeMap[$q->type] ?? ['Unknown', 'bg-secondary'];
                $optionTypes = ['select','multiselect','radio','checkbox'];
            @endphp
            <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="d-flex gap-3">

                    {{-- Number badge --}}
                    <div class="flex-shrink-0 text-center" style="width:28px;">
                        <span class="badge bg-light text-secondary border fw-normal" style="font-size:.75rem;">
                            {{ $i + 1 }}
                        </span>
                    </div>

                    <div class="flex-grow-1">

                        {{-- Question text --}}
                        <p class="fw-semibold mb-1">{{ $q->question }}</p>

                        {{-- Meta badges --}}
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge {{ $typeBadge }} bg-opacity-75">{{ $typeLabel }}</span>

                            @if($q->is_required)
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                    <i class="bi bi-asterisk me-1" style="font-size:.6rem"></i>Required
                                </span>
                            @endif

                            @if($q->is_readonly)
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                    <i class="bi bi-lock me-1" style="font-size:.6rem"></i>Read-only
                                </span>
                            @endif

                            @if($q->key)
                                <span class="badge bg-light text-dark border font-monospace" style="font-size:.7rem;">
                                    key: {{ $q->key }}
                                </span>
                            @endif

                            @if($q->slug)
                                <span class="badge border font-monospace"
                                      style="font-size:.7rem; background:#f0fdf4; color:#166534; border-color:#bbf7d0 !important;"
                                      title="Stable slug — use this in API answers instead of question_id">
                                    slug: {{ $q->slug }}
                                </span>
                            @endif

                            @if($q->placeholder)
                                <span class="badge bg-light text-muted border" style="font-size:.7rem;">
                                    placeholder: "{{ $q->placeholder }}"
                                </span>
                            @endif
                        </div>

                        {{-- Conditional logic indicator --}}
                        @if($q->depends_on_question_id && $q->dependsOn)
                        @php
                            $opLabel = $operatorLabels[$q->depends_on_operator] ?? $q->depends_on_operator;
                        @endphp
                        <div class="d-flex align-items-center gap-1 mt-1 mb-1">
                            <i class="bi bi-arrow-return-right text-warning" style="font-size:.8rem;"></i>
                            <span class="badge border fw-normal"
                                  style="background:#fff8e1; color:#7c5f00; border-color:#f0c940 !important; font-size:.7rem;">
                                Shows only if:
                                <span class="fw-semibold">"{{ $q->dependsOn->question }}"</span>
                                {{ $opLabel }}
                                @if($q->depends_on_operator !== 'is_answered' && $q->depends_on_value)
                                    "<span class="fw-semibold">{{ $q->depends_on_value }}</span>"
                                @endif
                            </span>
                        </div>
                        @endif

                        {{-- Options (for choice types) --}}
                        @if(in_array($q->type, $optionTypes) && $q->options && count($q->options))
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($q->options as $opt)
                            @php
                                $val   = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                                $disq  = is_array($opt) && !empty($opt['is_disqualify']);
                            @endphp
                            <span class="badge border fw-normal {{ $disq ? 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25' : 'bg-light text-dark' }}"
                                  style="font-size:.75rem">
                                {{ $val }}
                                @if($disq)
                                    <i class="bi bi-slash-circle ms-1" title="Disqualifying option"></i>
                                @endif
                            </span>
                            @endforeach
                        </div>
                        @endif

                    </div>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                No questions yet.
                <a href="{{ route('admin.questionnaires.edit', $questionnaire->id) }}">Add some</a>.
            </div>
            @endforelse
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
function copyPre(id, btn) {
    var text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text).then(function () {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(function () { btn.innerHTML = orig; }, 1800);
    });
}

function copyField(id, btn) {
    var el = document.getElementById(id);
    navigator.clipboard.writeText(el.value).then(function () {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(function () { btn.innerHTML = orig; }, 1800);
    }).catch(function () {
        el.select();
        document.execCommand('copy');
    });
}
</script>
@endsection
