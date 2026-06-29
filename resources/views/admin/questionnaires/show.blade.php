@extends('layouts.admin')

@section('title', $questionnaire->name)
@section('page-title', $questionnaire->name)

@section('content')

@php
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

        {{-- Share & Embed --}}
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-share me-2"></i>Share & Embed</h6>
            </div>
            <div class="card-body">

                {{-- Public URL (no partner) --}}
                <p class="small fw-semibold text-muted mb-1">Public Form URL</p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" id="share-url" class="form-control font-monospace" readonly
                           value="{{ url('/forms/' . $questionnaire->uuid) }}">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="copyField('share-url', this)" title="Copy URL">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>

                {{-- Partner selector --}}
                <p class="small fw-semibold text-muted mb-1">Generate Partner Embed Code</p>
                <select id="partner-selector" class="form-select form-select-sm mb-2">
                    <option value="">— Select a partner —</option>
                    @foreach($partners as $p)
                        <option value="{{ $p->uuid }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <div class="form-text mb-2">
                    The <strong>partner token</strong> is the partner's unique ID. It links form submissions to that partner's account so responses appear in their case records.
                </div>

                {{-- Generated iFrame code --}}
                <div class="input-group input-group-sm">
                    <textarea id="embed-code" class="form-control font-monospace" rows="4"
                              readonly style="font-size:.7rem; resize:none"
                    >{{ '<iframe src="' . url('/forms/' . $questionnaire->uuid) . '?partner_token=SELECT_PARTNER_ABOVE&external_id=PATIENT_ID" width="100%" height="680" frameborder="0" allow="camera"></iframe>' }}</textarea>
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="copyField('embed-code', this)" title="Copy embed code">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="form-text mt-1">
                    Replace <code>PATIENT_ID</code> with the patient's ID from your system (used to match submissions to patients).
                </div>

                {{-- What to share --}}
                <div class="alert alert-light border mt-3 mb-0 small">
                    <strong>What to send the third party:</strong>
                    <ol class="mb-0 mt-1 ps-3">
                        <li>The <strong>iFrame embed code</strong> above (with their patient ID variable substituted)</li>
                        <li>OR the <strong>public URL</strong> to link directly</li>
                        <li>OR use the <strong>API</strong>: <code>GET /api/partner/offerings/{uuid}/questionnaires</code> to fetch the question schema and render their own UI</li>
                    </ol>
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
var baseFormUrl = '{{ url('/forms/' . $questionnaire->uuid) }}';

document.getElementById('partner-selector').addEventListener('change', function () {
    var token    = this.value;
    var embedEl  = document.getElementById('embed-code');
    var tokenVal = token || 'SELECT_PARTNER_ABOVE';
    embedEl.value = '<iframe src="' + baseFormUrl + '?partner_token=' + tokenVal + '&external_id=PATIENT_ID" width="100%" height="680" frameborder="0" allow="camera"></iframe>';
});

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
