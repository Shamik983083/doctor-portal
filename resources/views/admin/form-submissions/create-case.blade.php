@extends('layouts.admin')

@section('title', 'Create Case from Submission')
@section('page-title', 'Create Case from Submission')

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.form-submissions.show', $submission->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Submission
    </a>
</div>

{{-- Summary banner --}}
<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>Creating a case from:</strong> {{ $submission->questionnaire->name ?? 'Questionnaire' }}
        &nbsp;|&nbsp; <strong>Partner:</strong> {{ $submission->partner->name ?? '—' }}
        @if($submission->external_patient_id)
            &nbsp;|&nbsp; <strong>Ext. Patient ID:</strong> <code>{{ $submission->external_patient_id }}</code>
        @endif
        <br>
        The form submission will be linked to the new case automatically.
    </div>
</div>

<form method="POST" action="{{ route('admin.form-submissions.store-case', $submission->id) }}">
@csrf

<div class="row g-4">

    {{-- Left: Patient & Assignment --}}
    <div class="col-lg-5">

        {{-- Patient Details --}}
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person-circle me-2"></i>Patient Details</h6>
                <small class="text-muted">Enter the patient's information. If they already exist (matched by email + partner), their record will be reused.</small>
            </div>
            <div class="card-body">

                @if($errors->any())
                <div class="alert alert-danger small mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
                @endif

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control form-control-sm @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name', $keyedAnswers['first_name'] ?? '') }}" required>
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control form-control-sm @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $keyedAnswers['last_name'] ?? '') }}" required>
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                               value="{{ old('email', $keyedAnswers['email'] ?? '') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm"
                               value="{{ old('phone', $keyedAnswers['phone'] ?? '') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control form-control-sm"
                               value="{{ old('date_of_birth', $keyedAnswers['date_of_birth'] ?? '') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">Gender</label>
                        <select name="gender" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            @foreach(['male'=>'Male','female'=>'Female','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $val => $label)
                                <option value="{{ $val }}" {{ old('gender', $keyedAnswers['gender'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm fw-semibold">State (2-letter)</label>
                        <input type="text" name="state" maxlength="2" placeholder="e.g. CA"
                               class="form-control form-control-sm @error('state') is-invalid @enderror"
                               value="{{ old('state', $keyedAnswers['state'] ?? '') }}">
                        @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Assign Doctor --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Assign Doctor</h6>
                <small class="text-muted">Optional — you can assign later from the case page.</small>
            </div>
            <div class="card-body">
                <select name="clinician_id" class="form-select form-select-sm">
                    <option value="">— Assign later —</option>
                    @foreach($clinicians as $c)
                        <option value="{{ $c->id }}" {{ old('clinician_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->full_name }}
                            @if($c->specialty) · {{ $c->specialty }}@endif
                        </option>
                    @endforeach
                </select>
                @if($clinicians->isEmpty())
                <div class="form-text text-warning mt-1">
                    <i class="bi bi-exclamation-triangle me-1"></i>No available clinicians right now.
                </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Right: Offerings + Q&A preview --}}
    <div class="col-lg-7">

        {{-- Offerings --}}
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-capsule me-2"></i>Attach Offerings</h6>
                <small class="text-muted">Select one or more offerings for this case.</small>
            </div>
            <div class="card-body" style="max-height:220px; overflow-y:auto;">
                @foreach($offerings as $offering)
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox"
                           name="offering_ids[]" value="{{ $offering->id }}"
                           id="off{{ $offering->id }}"
                           {{ in_array($offering->id, old('offering_ids', [])) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="off{{ $offering->id }}">
                        {{ $offering->name }}
                        <span class="text-muted">({{ ucfirst($offering->type) }})</span>
                    </label>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Q&A Preview --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-ui-checks me-2"></i>Patient's Answers Preview</h6>
            </div>
            <div style="max-height:320px; overflow-y:auto;">
                @forelse($submission->answers as $answer)
                <div class="d-flex px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}
                            {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                    <div class="text-muted small" style="min-width:50%; max-width:50%; padding-right:1rem;">
                        {{ $answer->question_text }}
                    </div>
                    <div class="small fw-semibold">
                        @php $decoded = json_decode($answer->answer, true); @endphp
                        @if(is_array($decoded))
                            {{ implode(', ', $decoded) }}
                        @elseif($answer->answer !== '' && $answer->answer !== null)
                            {{ $answer->answer }}
                        @else
                            <span class="text-muted fst-italic">—</span>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-muted small px-3 py-2 mb-0">No answers.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.form-submissions.show', $submission->id) }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-folder-plus me-1"></i>Create Case
    </button>
</div>

</form>
@endsection
