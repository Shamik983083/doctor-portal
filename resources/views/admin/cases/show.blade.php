@extends('layouts.admin')

@section('title', 'Case — ' . substr($case->uuid, 0, 8))
@section('page-title', 'Case — ' . substr($case->uuid, 0, 8))

@section('content')
<div class="row g-4">

    {{-- Left sidebar --}}
    <div class="col-lg-4">

        {{-- Status & Assignment --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Case Info</h6></div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th>Status</th><td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td></tr>
                    <tr><th>Partner</th><td>{{ $case->partner->name ?? '—' }}</td></tr>
                    <tr><th>Clinician</th><td>{{ $case->clinician?->full_name ?? '—' }}</td></tr>
                    <tr><th>Created</th><td>{{ $case->created_at->format('M d, Y H:i') }}</td></tr>
                    @if($case->assigned_at)<tr><th>Assigned</th><td>{{ $case->assigned_at->format('M d, Y H:i') }}</td></tr>@endif
                    @if($case->support_at)<tr><th>Support since</th><td>{{ $case->support_at->format('M d, Y H:i') }}</td></tr>@endif
                    @if($case->approved_at)<tr><th>Approved</th><td>{{ $case->approved_at->format('M d, Y H:i') }}</td></tr>@endif
                    @if($case->completed_at)<tr><th>Completed</th><td>{{ $case->completed_at->format('M d, Y H:i') }}</td></tr>@endif
                    @if($case->cancelled_at)<tr><th>Cancelled</th><td>{{ $case->cancelled_at->format('M d, Y H:i') }}</td></tr>@endif
                </table>
            </div>
        </div>

        {{-- Patient --}}
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-circle me-2"></i>Patient</h6></div>
            <div class="card-body small">
                <h6 class="mb-1">{{ $case->patient->full_name }}</h6>
                <p class="text-muted mb-2">{{ $case->patient->email }}</p>
                <table class="table table-sm table-borderless mb-0">
                    <tr><th>DOB</th><td>{{ $case->patient->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
                    <tr><th>State</th><td>{{ $case->patient_state ?? $case->patient->state ?? '—' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $case->patient->phone ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Assign / Reassign Clinician --}}
        @if(in_array($case->status, ['created','waiting','assigned']))
        @php $isReassign = $case->status === 'assigned'; @endphp
        <div class="card border-warning mb-3">
            <div class="card-header bg-warning bg-opacity-10 border-warning d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-person-check me-2"></i>
                    {{ $isReassign ? 'Reassign Clinician' : 'Assign to Clinician' }}
                </h6>
                @if($isReassign)
                    <span class="badge bg-info text-dark small">Auto-assigned</span>
                @endif
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success py-1 small">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger py-1 small">{{ session('error') }}</div>
                @endif
                @if($isReassign)
                    <p class="text-muted small mb-2">
                        Currently assigned to <strong>{{ $case->clinician?->full_name ?? '—' }}</strong>.
                        Select a different clinician to reassign.
                    </p>
                @endif
                <form method="POST" action="{{ route('admin.cases.assign', $case->uuid) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Clinician</label>
                        <select name="clinician_id" class="form-select form-select-sm" required>
                            <option value="">— Choose clinician —</option>
                            @foreach($clinicians as $c)
                                <option value="{{ $c->id }}" {{ $case->clinician_id == $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }}
                                    @if($c->specialty) ({{ $c->specialty }})@endif
                                    @if(!$c->is_available) — Unavailable @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100">
                        <i class="bi bi-person-check me-1"></i>
                        {{ $isReassign ? 'Reassign Clinician' : 'Assign Clinician' }}
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                @if(session('success'))
                    <div class="alert alert-success py-1 small mb-0">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger py-1 small mb-0">{{ session('error') }}</div>
                @endif
            </div>
        </div>
        @endif

    </div>

    {{-- Right: Tabs --}}
    <div class="col-lg-8">

        {{-- Back link --}}
        <div class="mb-3">
            <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Cases
            </a>
        </div>

        <ul class="nav nav-tabs mb-3" id="caseTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-intake">Intake</a></li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-questionnaires">
                    Questionnaires
                    @if($case->questionnaireResponses->count())
                        <span class="badge bg-primary">{{ $case->questionnaireResponses->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-prescriptions">
                    Prescriptions
                    @if($case->casePrescriptions->count())
                        <span class="badge bg-success">{{ $case->casePrescriptions->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notes">Clinical Notes <span class="badge bg-secondary">{{ $case->clinicalNotes->count() }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages <span class="badge bg-secondary">{{ $case->messages->count() }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-timeline">Timeline</a></li>
        </ul>

        <div class="tab-content">
            {{-- Intake Questions --}}
            <div class="tab-pane fade show active" id="tab-intake">
                @forelse($case->caseQuestions as $q)
                <div class="mb-3">
                    <p class="fw-semibold mb-1 small">{{ $q->question }}</p>
                    <p class="text-muted small ms-2">{{ $q->answer ?: '—' }}</p>
                </div>
                @empty
                <p class="text-muted">No intake questions.</p>
                @endforelse
            </div>

            {{-- Questionnaire Responses --}}
            <div class="tab-pane fade" id="tab-questionnaires">
                @forelse($case->questionnaireResponses as $response)
                <div class="card mb-3 {{ $response->is_disqualified ? 'border-danger' : 'border-0 shadow-sm' }}">
                    <div class="card-header d-flex justify-content-between align-items-center py-2
                                {{ $response->is_disqualified ? 'bg-danger bg-opacity-10' : 'bg-light' }}">
                        <div>
                            <span class="fw-semibold">{{ $response->questionnaire->name ?? 'Questionnaire' }}</span>
                            @if($response->is_disqualified)
                                <span class="badge bg-danger ms-2">
                                    <i class="bi bi-slash-circle me-1"></i>Disqualified
                                </span>
                                @if($response->disqualified_on)
                                    <small class="text-danger ms-1">on "{{ $response->disqualified_on }}"</small>
                                @endif
                            @else
                                <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Qualified</span>
                            @endif
                        </div>
                        <small class="text-muted">
                            {{ $response->completed_at?->format('M d, Y H:i') ?? $response->created_at->format('M d, Y H:i') }}
                        </small>
                    </div>
                    <div class="card-body p-0">
                        @forelse($response->answers as $answer)
                        <div class="d-flex px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}
                                    {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                            <div class="text-muted small" style="min-width:45%; max-width:45%; padding-right:1rem;">
                                {{ $answer->question_text }}
                                @if($answer->is_disqualified)
                                    <i class="bi bi-slash-circle text-danger ms-1" title="Disqualifying answer"></i>
                                @endif
                            </div>
                            <div class="small fw-semibold">
                                @php
                                    $decoded = json_decode($answer->answer, true);
                                @endphp
                                @if(is_array($decoded))
                                    {{ implode(', ', $decoded) }}
                                @elseif($answer->answer !== '')
                                    {{ $answer->answer }}
                                @else
                                    <span class="text-muted fst-italic">—</span>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small px-3 py-2 mb-0">No answers recorded.</p>
                        @endforelse
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                    No questionnaire responses linked to this case.
                </div>
                @endforelse
            </div>

            {{-- Prescriptions --}}
            <div class="tab-pane fade" id="tab-prescriptions">
                @forelse($case->casePrescriptions->sortByDesc('prescribed_at') as $rx)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold">Prescription</span>
                            <small class="text-muted ms-2">by {{ $rx->clinician->full_name ?? '—' }}</small>
                        </div>
                        <small class="text-muted">{{ $rx->prescribed_at->format('M d, Y H:i') }}</small>
                    </div>
                    <div class="card-body pb-2">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <p class="text-muted small fw-semibold mb-1 text-uppercase" style="font-size:.7rem;">Diagnoses</p>
                                <p class="small mb-0" style="white-space:pre-line;">{{ $rx->diagnoses }}</p>
                            </div>
                            @if($rx->directions)
                            <div class="col-md-6">
                                <p class="text-muted small fw-semibold mb-1 text-uppercase" style="font-size:.7rem;">Directions</p>
                                <p class="small mb-0" style="white-space:pre-line;">{{ $rx->directions }}</p>
                            </div>
                            @endif
                            @if($rx->medical_necessity)
                            <div class="col-12">
                                <p class="text-muted small fw-semibold mb-1 text-uppercase" style="font-size:.7rem;">Medical Necessity</p>
                                <p class="small mb-0" style="white-space:pre-line;">{{ $rx->medical_necessity }}</p>
                            </div>
                            @endif
                        </div>

                        @if($rx->medications->count())
                        <p class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:.7rem;">Medications</p>
                        @foreach($rx->medications as $med)
                        <div class="border rounded p-3 mb-2 bg-light">
                            <div class="fw-semibold mb-1">{{ $med->name }}</div>
                            @if($med->compound_formula)
                                <div class="small text-muted mb-2">{{ $med->compound_formula }}</div>
                            @endif
                            <div class="d-flex flex-wrap gap-3 small">
                                @if($med->refills !== null)
                                    <span><span class="text-muted">Refills:</span> {{ $med->refills }}</span>
                                @endif
                                @if($med->quantity !== null)
                                    <span><span class="text-muted">Qty:</span> {{ $med->quantity }}</span>
                                @endif
                                @if($med->days_supply !== null)
                                    <span><span class="text-muted">Days Supply:</span> {{ $med->days_supply }}</span>
                                @endif
                                @if($med->dispense_unit)
                                    <span><span class="text-muted">Unit:</span> {{ $med->dispense_unit }}</span>
                                @endif
                                @if($med->days_until_dispense !== null)
                                    <span><span class="text-muted">Days Until Dispense:</span> {{ $med->days_until_dispense }}</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2 opacity-25"></i>
                    No prescription submitted yet.
                </div>
                @endforelse
            </div>

            {{-- Clinical Notes --}}
            <div class="tab-pane fade" id="tab-notes">
                @forelse($case->clinicalNotes->sortByDesc('created_at') as $note)
                <div class="card mb-2 {{ $note->is_private ? 'border-warning' : '' }}">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-semibold">
                                {{ $note->clinician->full_name ?? 'Unknown' }}
                                &bull; <span class="text-muted">{{ ucfirst($note->type) }}</span>
                            </small>
                            <small class="text-muted">{{ $note->created_at->diffForHumans() }}</small>
                        </div>
                        <p class="mb-0 small">{{ $note->note }}</p>
                    </div>
                </div>
                @empty
                <p class="text-muted">No clinical notes yet.</p>
                @endforelse
            </div>

            {{-- Messages --}}
            <div class="tab-pane fade" id="tab-messages">
                <div style="max-height:400px; overflow-y:auto;">
                    @forelse($case->messages->sortBy('created_at') as $msg)
                    <div class="d-flex mb-2 {{ $msg->sender_type === 'clinician' ? 'justify-content-end' : '' }}">
                        <div class="card p-2 px-3 {{ $msg->sender_type === 'clinician' ? 'bg-primary text-white' : 'bg-light' }}"
                             style="max-width:80%; border-radius:12px;">
                            <small class="fw-semibold d-block">{{ ucfirst($msg->sender_type ?? 'System') }}</small>
                            <p class="mb-0 small">{{ $msg->body }}</p>
                            <small class="opacity-75">{{ $msg->created_at->format('M d H:i') }}</small>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No messages.</p>
                    @endforelse
                </div>
            </div>

            {{-- Timeline --}}
            <div class="tab-pane fade" id="tab-timeline">
                @forelse($case->events->sortByDesc('created_at') as $event)
                <div class="d-flex gap-3 mb-3">
                    <div class="text-muted" style="min-width:120px; font-size:.75rem;">{{ $event->created_at->format('M d, H:i') }}</div>
                    <div>
                        <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                        @if($event->payload)
                            @if(isset($event->payload['from'], $event->payload['to']))
                                <small class="text-muted ms-1">{{ $event->payload['from'] }} → {{ $event->payload['to'] }}</small>
                            @endif
                        @endif
                        <div class="small text-muted">{{ ucfirst($event->actor_type ?? 'system') }}</div>
                        @if($event->notes)<p class="small mb-0">{{ $event->notes }}</p>@endif
                    </div>
                </div>
                @empty
                <p class="text-muted">No timeline events.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
