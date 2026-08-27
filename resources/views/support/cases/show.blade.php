@extends('layouts.support')

@section('title', 'Support — Case #' . $case->id)
@section('page-title', 'Case #' . $case->id)

@section('content')


{{-- Back link --}}
<div class="mb-3">
    <a href="{{ route('support.cases.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Back to Support Queue
    </a>
</div>

<div class="row g-3">

    {{-- ── Left column: Case details + Q&A + Prescriptions ── --}}
    <div class="col-lg-5">

        {{-- Case summary --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Case Details</h6>
            </div>
            <div class="card-body small">
                <dl class="row mb-0" style="row-gap:.25rem;">
                    <dt class="col-5 text-muted fw-normal">Status</dt>
                    <dd class="col-7">
                        <span class="badge badge-status-{{ $case->status }}">{{ $case->status }}</span>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Patient</dt>
                    <dd class="col-7 fw-semibold">{{ $case->patient?->first_name }} {{ $case->patient?->last_name }}</dd>
                    <dt class="col-5 text-muted fw-normal">DOB</dt>
                    <dd class="col-7">{{ $case->patient?->dob?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Partner</dt>
                    <dd class="col-7">{{ $case->partner?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Offering(s)</dt>
                    <dd class="col-7">
                        {{ $case->caseOfferings->pluck('offering.name')->filter()->join(', ') ?: '—' }}
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Assigned to</dt>
                    <dd class="col-7">{{ $case->clinician?->user?->name ?? 'Unassigned' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Submitted</dt>
                    <dd class="col-7">{{ $case->created_at->format('d M Y H:i') }}</dd>
                    @if($case->support_note)
                    <dt class="col-5 text-muted fw-normal">Support note</dt>
                    <dd class="col-7 fst-italic">{{ $case->support_note }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Questionnaire answers (read-only) --}}
        @if($case->questionnaireResponses->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Intake Answers</h6>
            </div>
            <div class="card-body p-0">
                @foreach($case->questionnaireResponses as $qr)
                    <div class="px-3 py-2 border-bottom">
                        <div class="text-muted small fw-semibold mb-1">{{ $qr->questionnaire?->name }}</div>
                        @foreach($qr->answers as $answer)
                            <div class="small mb-1">
                                <span class="text-muted">{{ $answer->question_text }}:</span>
                                <span class="fw-semibold">{{ is_array($answer->answer) ? implode(', ', $answer->answer) : ($answer->answer ?? '—') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Prescriptions (read-only) --}}
        @if($case->casePrescriptions->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Prescriptions</h6>
            </div>
            <div class="card-body p-0">
                @foreach($case->casePrescriptions as $rx)
                <div class="px-3 py-2 border-bottom small">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">{{ $rx->clinician?->user?->name ?? 'Unknown clinician' }}</span>
                        <span class="text-muted">{{ $rx->prescribed_at?->format('d M Y') }}</span>
                    </div>
                    @foreach($rx->medications as $med)
                        <div class="text-muted">
                            {{ $med->name }}
                            @if($med->dose) <span class="ms-1">{{ $med->dose }}</span>@endif
                        </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Files (read-only download list) --}}
        @if($case->files->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">Files</h6>
            </div>
            <ul class="list-group list-group-flush">
                @foreach($case->files as $file)
                <li class="list-group-item d-flex justify-content-between align-items-center small py-2">
                    <div>
                        <i class="bi bi-paperclip me-1 text-muted"></i>
                        <span>{{ $file->original_name }}</span>
                        @if($file->type)
                            <span class="badge bg-light text-dark border ms-1" style="font-size:.65rem;">{{ $file->type }}</span>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Escalate to clinician --}}
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-10">
                <h6 class="mb-0 text-warning-emphasis">Escalate to Clinician</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('support.cases.escalate', $case->uuid) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assign to clinician</label>
                        <select name="clinician_id" class="form-select form-select-sm" required>
                            <option value="">— select clinician —</option>
                            @foreach($clinicians as $clinician)
                                <option value="{{ $clinician->id }}">{{ $clinician->user?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Handoff notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"
                                  placeholder="What should the clinician know?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">
                        <i class="bi bi-arrow-up-right-circle me-1"></i>Escalate to Clinician
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- ── Right column: Messages ── --}}
    <div class="col-lg-7">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h6 class="mb-0">Messages</h6>
            </div>

            {{-- Message thread --}}
            <div class="card-body p-3 overflow-auto" style="max-height:520px;" id="messageThread">
                @forelse($case->messages->sortBy('id') as $msg)
                    <div class="mb-3 d-flex {{ $msg->sender_type !== 'patient' ? 'flex-row-reverse' : '' }}">
                        <div class="rounded px-3 py-2 small"
                             style="max-width:75%;
                             {{ $msg->sender_type === 'patient'
                                  ? 'background:#f1f5f9;color:#1e293b;'
                                  : 'background:#0d6efd;color:#fff;' }}">
                            <div style="font-size:.72rem;opacity:.7;margin-bottom:.2rem;">
                                @if($msg->sender_type === 'patient') Patient
                                @elseif($msg->sender_type === 'support') Support
                                @else Clinician
                                @endif
                                &middot; {{ $msg->created_at->format('d M H:i') }}
                            </div>
                            {!! nl2br(e($msg->body)) !!}
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center small py-4">No messages yet.</p>
                @endforelse
            </div>

            <div class="card-footer border-top bg-white">
                <form method="POST" action="{{ route('support.cases.messages.store', $case->uuid) }}">
                    @csrf
                    <div class="d-flex gap-2">
                        <textarea name="body" class="form-control form-control-sm"
                                  rows="2" placeholder="Write a message to the patient…"
                                  required style="resize:none;"></textarea>
                        <button type="submit" class="btn btn-primary btn-sm align-self-end" style="white-space:nowrap;">
                            <i class="bi bi-send me-1"></i>Send
                        </button>
                    </div>
                    @error('body')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </form>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
// Auto-scroll message thread to bottom on load
(function () {
    var thread = document.getElementById('messageThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
})();
</script>
@endsection
