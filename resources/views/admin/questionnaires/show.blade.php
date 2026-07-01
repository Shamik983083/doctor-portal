@extends('layouts.admin')

@section('title', $questionnaire->name)
@section('page-title', $questionnaire->name)

@section('content')

@php
// Build API example payload from real question IDs
$exampleAnswers = [];
foreach ($questionnaire->questions as $q) {
    if ($q->type === 'file') continue;
    $opts = is_array($q->options) ? $q->options : [];
    if (in_array($q->type, ['radio', 'select'])) {
        $firstOpt = $opts[0] ?? null;
        $val = $firstOpt ? ($firstOpt['value'] ?? (string)$firstOpt) : 'option_value';
    } elseif (in_array($q->type, ['multiselect', 'checkbox'])) {
        $firstOpt = $opts[0] ?? null;
        $val = [$firstOpt ? ($firstOpt['value'] ?? (string)$firstOpt) : 'option_value'];
    } elseif ($q->type === 'date') {
        $val = '1990-01-15';
    } elseif ($q->type === 'email') {
        $val = 'patient@example.com';
    } elseif ($q->type === 'height') {
        $val = '70';
    } elseif ($q->type === 'weight') {
        $val = '150';
    } elseif ($q->type === 'bmi') {
        $val = '28.5';
    } elseif ($q->type === 'number') {
        $val = '25';
    } elseif ($q->type === 'hidden') {
        $val = 'value';
    } else {
        $val = 'Sample text';
    }
    $exampleAnswers[] = ['question_id' => $q->id, 'answer' => $val];
}

$authJson = json_encode([
    'grant_type'    => 'client_credentials',
    'client_id'     => '<your_client_id>',
    'client_secret' => '<your_client_secret>',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Build annotated case JSON with // comments so partners know what each question_id means
$answersAnnotated = '';
$allQuestions = $questionnaire->questions->filter(fn($q) => $q->type !== 'file')->values();
foreach ($allQuestions as $idx => $q) {
    $opts = is_array($q->options) ? $q->options : [];
    if (in_array($q->type, ['radio', 'select'])) {
        $firstOpt = $opts[0] ?? null;
        $val = $firstOpt ? ($firstOpt['value'] ?? (string)$firstOpt) : 'option_value';
    } elseif (in_array($q->type, ['multiselect', 'checkbox'])) {
        $firstOpt = $opts[0] ?? null;
        $val = [$firstOpt ? ($firstOpt['value'] ?? (string)$firstOpt) : 'option_value'];
    } elseif ($q->type === 'date')        { $val = '1990-01-15'; }
    elseif ($q->type === 'email')         { $val = 'patient@example.com'; }
    elseif ($q->type === 'height')        { $val = '70'; }
    elseif ($q->type === 'weight')        { $val = '150'; }
    elseif ($q->type === 'bmi')           { $val = '28.5'; }
    elseif ($q->type === 'number')        { $val = '25'; }
    elseif ($q->type === 'hidden')        { $val = 'value'; }
    else                                  { $val = 'Sample text'; }

    $comma   = $idx < count($allQuestions) - 1 ? ',' : '';
    $typeTag = $q->is_required ? "[{$q->type}, required]" : "[{$q->type}]";
    $answersAnnotated .= "        // Q{$q->id}: {$q->question} {$typeTag}\n";
    $answersAnnotated .= '        {"question_id":' . $q->id . ',"answer":' . json_encode($val) . '}' . $comma . "\n";
}

$caseJson = '{' . "\n"
    . '  "patient": {' . "\n"
    . '    "first_name": "John",' . "\n"
    . '    "last_name": "Doe",' . "\n"
    . '    "email": "john@example.com",' . "\n"
    . '    "phone": "555-0100",' . "\n"
    . '    "date_of_birth": "1990-01-15",' . "\n"
    . '    "gender": "male",' . "\n"
    . '    "state": "TX",' . "\n"
    . '    "external_id": "YOUR_PATIENT_ID"' . "\n"
    . '  },' . "\n"
    . '  "external_id": "YOUR_CASE_REF",' . "\n"
    . '  "patient_state": "TX",' . "\n"
    . '  "questionnaire_responses": [' . "\n"
    . '    {' . "\n"
    . '      "questionnaire_id": "' . $questionnaire->uuid . '",' . "\n"
    . '      "answers": [' . "\n"
    . $answersAnnotated
    . '      ]' . "\n"
    . '    }' . "\n"
    . '  ]' . "\n"
    . '}';
@endphp

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
                </table>
                @if($questionnaire->description)
                    <hr class="my-2">
                    <p class="text-muted small mb-0">{{ $questionnaire->description }}</p>
                @endif
            </div>
        </div>

        {{-- Share & Embed (commented out — replaced by inline API Integration below) --}}
        {{-- <div class="card mt-4">Share & Embed content removed</div> --}}

        {{-- API Integration --}}
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-braces me-2"></i>API Integration</h6>
            </div>
            <div class="card-body p-0">

                {{-- Step 0: Discover Questions --}}
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold">
                            <span class="badge bg-dark me-1">0</span>Discover Question IDs
                        </span>
                        <code style="font-size:.65rem;" class="text-muted">GET /api/partner/questionnaires/{uuid}</code>
                    </div>
                    <div class="position-relative">
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-1"
                                onclick="copyPre('discover-json-inline', this)" title="Copy" style="z-index:1;padding:.15rem .35rem;">
                            <i class="bi bi-clipboard" style="font-size:.7rem;"></i>
                        </button>
                        <pre id="discover-json-inline" class="bg-dark text-light rounded p-2 mb-0"
                             style="font-size:.68rem; overflow-x:auto;">GET {{ url('/api/partner/questionnaires/' . $questionnaire->uuid) }}
Authorization: Bearer {access_token}</pre>
                    </div>
                    <p class="form-text mt-1 mb-0">
                        Returns all questions with their <code>id</code>, <code>type</code>, <code>key</code>, and <code>options</code>.
                        Call this once to build your <code>question_id</code> mapping — then cache it.
                    </p>
                </div>

                {{-- Step 1: Auth --}}
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold">
                            <span class="badge bg-secondary me-1">1</span>Get Access Token
                        </span>
                        <code style="font-size:.65rem;" class="text-muted">POST /api/partner/auth/token</code>
                    </div>
                    <div class="position-relative">
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-1"
                                onclick="copyPre('auth-json-inline', this)" title="Copy" style="z-index:1;padding:.15rem .35rem;">
                            <i class="bi bi-clipboard" style="font-size:.7rem;"></i>
                        </button>
                        <pre id="auth-json-inline" class="bg-dark text-light rounded p-2 mb-0"
                             style="font-size:.68rem; overflow-x:auto;">{!! htmlspecialchars($authJson) !!}</pre>
                    </div>
                    <p class="form-text mt-1 mb-0">
                        Find <code>client_id</code> &amp; <code>client_secret</code> in
                        <a href="{{ route('admin.partners.index') }}" target="_blank">Partners → API Credentials</a>.
                        Returns <code>access_token</code> valid for 24 h.
                    </p>
                </div>

                {{-- Step 2: Create Case --}}
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold">
                            <span class="badge bg-primary me-1">2</span>Create Case
                        </span>
                        <code style="font-size:.65rem;" class="text-muted">POST /api/partner/cases</code>
                    </div>
                    <div class="position-relative">
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-1"
                                onclick="copyPre('case-json-inline', this)" title="Copy" style="z-index:1;padding:.15rem .35rem;">
                            <i class="bi bi-clipboard" style="font-size:.7rem;"></i>
                        </button>
                        <pre id="case-json-inline" class="bg-dark text-light rounded p-2 mb-0"
                             style="font-size:.68rem; overflow-x:auto; max-height:260px; overflow-y:auto;">{!! htmlspecialchars($caseJson) !!}</pre>
                    </div>
                    <p class="form-text mt-1 mb-0">
                        Header: <code>Authorization: Bearer {access_token}</code>.
                        <span class="text-warning-emphasis">Lines starting with <code>//</code> are reference comments — remove them before sending.</span>
                    </p>
                </div>

                {{-- Question ID Reference --}}
                <div class="p-3">
                    <p class="small fw-semibold mb-2">
                        <i class="bi bi-table me-1"></i>Question ID Reference
                        <span class="text-muted fw-normal">— use these IDs in <code>answers[].question_id</code></span>
                    </p>
                    <div class="table-responsive" style="max-height:220px; overflow-y:auto;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.72rem;">
                            <thead class="table-light" style="position:sticky;top:0;">
                                <tr>
                                    <th style="width:28px">#</th>
                                    <th style="width:44px">ID</th>
                                    <th>Question</th>
                                    <th style="width:70px">Type</th>
                                    <th style="width:32px">Req</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($questionnaire->questions as $i => $q)
                                @php [$typeLabel2, $typeBadge2] = $typeMap[$q->type] ?? ['Unknown', 'bg-secondary']; @endphp
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td><code class="text-primary">{{ $q->id }}</code></td>
                                    <td style="white-space:normal;">{{ $q->question }}</td>
                                    <td><span class="badge {{ $typeBadge2 }} bg-opacity-75" style="font-size:.62rem;">{{ $typeLabel2 }}</span></td>
                                    <td class="text-center">
                                        @if($q->is_required)
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

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
