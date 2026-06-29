@extends('layouts.admin')

@section('title', 'Form Submission')
@section('page-title', 'Form Submission')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('admin.form-submissions.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Submissions
    </a>
    @if(!$submission->is_disqualified)
    <a href="{{ route('admin.form-submissions.create-case', $submission->id) }}"
       class="btn btn-sm btn-primary">
        <i class="bi bi-folder-plus me-1"></i>Create Case & Assign Doctor
    </a>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- Left: Meta --}}
    <div class="col-lg-4">

        {{-- Submission Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Submission Info</h6>
                @if($submission->is_disqualified)
                    <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Disqualified</span>
                @else
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Qualified</span>
                @endif
            </div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:45%">Token</th>
                        <td class="font-monospace">{{ substr($submission->token, 0, 8) }}…</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Questionnaire</th>
                        <td>{{ $submission->questionnaire->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Partner</th>
                        <td>{{ $submission->partner->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Ext. Patient ID</th>
                        <td>
                            @if($submission->external_patient_id)
                                <code>{{ $submission->external_patient_id }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Submitted</th>
                        <td>{{ $submission->completed_at?->format('M d, Y H:i') ?? $submission->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @if($submission->is_disqualified)
                    <tr>
                        <th class="text-muted">Disq. trigger</th>
                        <td class="text-danger">{{ $submission->disqualified_on ?? '—' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- Action Card --}}
        @if($submission->is_disqualified)
        <div class="alert alert-danger small">
            <i class="bi bi-slash-circle me-2"></i>
            This patient was <strong>disqualified</strong> based on their answers. A case cannot be created for disqualified submissions.
        </div>
        @else
        <div class="card border-primary">
            <div class="card-body text-center py-4">
                <i class="bi bi-folder-plus fs-2 text-primary d-block mb-2"></i>
                <p class="small mb-3">This patient qualifies. Create a case and assign a doctor to begin their care.</p>
                <a href="{{ route('admin.form-submissions.create-case', $submission->id) }}"
                   class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-folder-plus me-1"></i>Create Case & Assign Doctor
                </a>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: Q&A --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-ui-checks me-2"></i>Questionnaire Answers
                    <span class="badge bg-secondary ms-1">{{ $submission->answers->count() }}</span>
                </h6>
            </div>

            @forelse($submission->answers as $answer)
            <div class="d-flex px-4 py-3 {{ !$loop->last ? 'border-bottom' : '' }}
                        {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                <div class="text-muted small" style="min-width:45%; max-width:45%; padding-right:1.5rem; line-height:1.4;">
                    {{ $answer->question_text }}
                    @if($answer->is_disqualified)
                        <span class="badge bg-danger ms-1" style="font-size:.6rem">
                            <i class="bi bi-slash-circle"></i> Disqualifying
                        </span>
                    @endif
                </div>
                <div class="small fw-semibold">
                    @php
                        $decoded = json_decode($answer->answer, true);
                    @endphp
                    @if(is_array($decoded))
                        {{ implode(', ', $decoded) }}
                    @elseif($answer->answer !== '' && $answer->answer !== null)
                        {{ $answer->answer }}
                    @else
                        <span class="text-muted fst-italic">No answer</span>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                No answers recorded.
            </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
