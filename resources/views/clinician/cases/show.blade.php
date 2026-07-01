@extends('layouts.clinician')

@section('title', 'Case Review')
@section('page-title', 'Case Review — ' . substr($case->uuid, 0, 8))

@section('content')
<div class="row g-4">

    {{-- Left: Patient + Case Info --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-circle me-2"></i>Patient</h6></div>
            <div class="card-body">
                <h5 class="mb-1">{{ $case->patient->full_name }}</h5>
                <p class="text-muted mb-2">{{ $case->patient->email }}</p>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><th>DOB</th><td>{{ $case->patient->date_of_birth?->format('M d, Y') ?? '—' }}</td></tr>
                    <tr><th>Gender</th><td>{{ ucfirst($case->patient->gender ?? '—') }}</td></tr>
                    <tr><th>State</th><td>{{ $case->patient_state ?? $case->patient->state ?? '—' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $case->patient->phone ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Case Info</h6></div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th>Status</th><td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td></tr>
                    <tr><th>Partner</th><td>{{ $case->partner->name }}</td></tr>
                    <tr><th>Clinician</th><td>{{ $case->clinician?->full_name ?? '—' }}</td></tr>
                    <tr><th>Chargeable</th><td>{{ $case->is_chargeable ? 'Yes' : 'No' }}</td></tr>
                    <tr><th>Created</th><td>{{ $case->created_at->format('M d, Y H:i') }}</td></tr>
                    @if($case->assigned_at)<tr><th>Assigned</th><td>{{ $case->assigned_at->format('M d, Y H:i') }}</td></tr>@endif
                </table>
            </div>
        </div>

        {{-- Case Actions --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Actions</h6></div>
            <div class="card-body d-grid gap-2">
                @if($case->status === 'waiting')
                <form method="POST" action="{{ route('clinician.cases.assign', $case->uuid) }}">
                    @csrf
                    <button class="btn btn-warning w-100"><i class="bi bi-hand-index me-1"></i>Claim Case</button>
                </form>
                @endif

                @if($case->status === 'assigned')
                <a href="{{ route('clinician.cases.prescribe.form', $case->uuid) }}" class="btn btn-success">
                    <i class="bi bi-clipboard2-pulse me-1"></i>Approve &amp; Prescribe
                </a>
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#supportModal">
                    <i class="bi bi-headset me-1"></i>Escalate to Support
                </button>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-lg me-1"></i>Decline Case
                </button>
                @endif

                @if($case->status === 'approved')
                <form method="POST" action="{{ route('clinician.cases.processing', $case->uuid) }}"
                      onsubmit="return confirm('Send this case to pharmacy for processing?')">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-truck me-1"></i>Send to Pharmacy
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Right: Tabs --}}
    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3" id="caseTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-offerings">Offerings</a></li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-prescriptions">
                    Prescriptions
                    @if($case->casePrescriptions->count())
                        <span class="badge bg-success">{{ $case->casePrescriptions->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-intake">Intake</a></li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-questionnaires">
                    Questionnaires
                    @if($case->questionnaireResponses->count())
                        <span class="badge bg-primary">{{ $case->questionnaireResponses->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notes">Notes <span class="badge bg-secondary">{{ $case->clinicalNotes->count() }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages <span class="badge bg-secondary">{{ $case->messages->count() }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-files">Files <span class="badge bg-secondary">{{ $case->files->count() }}</span></a></li>
        </ul>

        <div class="tab-content">
            {{-- Offerings --}}
            <div class="tab-pane fade show active" id="tab-offerings">
                @forelse($case->caseOfferings as $co)
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-1">{{ $co->offering->name }}</h6>
                                <small class="text-muted">{{ ucfirst($co->offering->type ?? '') }} &bull; Qty: {{ $co->quantity }}</small>
                            </div>
                            <span class="badge badge-status-{{ $co->status }}">{{ ucfirst($co->status) }}</span>
                        </div>
                        @if($co->dosage)<p class="small mb-0 mt-1"><strong>Dosage:</strong> {{ $co->dosage }}</p>@endif
                        @if($co->frequency)<p class="small mb-0"><strong>Frequency:</strong> {{ $co->frequency }}</p>@endif
                    </div>
                </div>
                @empty
                <p class="text-muted">No offerings attached.</p>
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

            {{-- Intake Questions --}}
            <div class="tab-pane fade" id="tab-intake">
                @forelse($case->caseQuestions as $q)
                <div class="mb-3">
                    <p class="fw-semibold mb-1 small">{{ $q->question }}</p>
                    <p class="text-muted small ms-2">{{ $q->answer ?: '—' }}</p>
                </div>
                @empty
                <p class="text-muted">No intake questions.</p>
                @endforelse
            </div>

            {{-- Questionnaires --}}
            <div class="tab-pane fade" id="tab-questionnaires">
                @forelse($case->questionnaireResponses as $response)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold">{{ $response->questionnaire->name ?? 'Questionnaire' }}</span>
                            <small class="text-muted ms-2">
                                {{ ($response->completed_at ?? $response->created_at)->format('M d, Y H:i') }}
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($response->is_disqualified)
                                <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Disqualified</span>
                                @if($response->disqualified_on)
                                    <small class="text-danger">trigger: {{ $response->disqualified_on }}</small>
                                @endif
                            @else
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Qualified</span>
                            @endif
                        </div>
                    </div>
                    @forelse($response->answers as $answer)
                    <div class="d-flex px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}
                                {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                        <div class="text-muted small" style="min-width:45%; max-width:45%; padding-right:1rem; line-height:1.4;">
                            {{ $answer->question_text }}
                            @if($answer->is_disqualified)
                                <span class="badge bg-danger ms-1" style="font-size:.6rem">
                                    <i class="bi bi-slash-circle"></i> Disqualifying
                                </span>
                            @endif
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
                    <p class="text-muted small px-3 py-2 mb-0">No answers recorded.</p>
                    @endforelse
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                    No questionnaire responses linked to this case.
                </div>
                @endforelse
            </div>

            {{-- Clinical Notes --}}
            <div class="tab-pane fade" id="tab-notes">
                <form method="POST" action="{{ route('clinician.cases.notes.store', $case->uuid) }}" class="mb-4">
                    @csrf
                    <div class="mb-2">
                        <select name="type" class="form-select form-select-sm w-auto d-inline-block">
                            <option value="general">General</option>
                            <option value="soap">SOAP</option>
                            <option value="progress">Progress</option>
                        </select>
                    </div>
                    <textarea name="note" class="form-control mb-2" rows="3" placeholder="Add clinical note..." required></textarea>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm">Add Note</button>
                        <div class="form-check align-self-center">
                            <input type="checkbox" name="is_private" class="form-check-input" id="private">
                            <label class="form-check-label small" for="private">Private</label>
                        </div>
                    </div>
                </form>
                @forelse($case->clinicalNotes->sortByDesc('created_at') as $note)
                <div class="card mb-2 {{ $note->is_private ? 'border-warning' : '' }}">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-semibold">{{ $note->clinician->full_name ?? 'Unknown' }} &bull; <span class="text-muted">{{ ucfirst($note->type) }}</span></small>
                            <small class="text-muted">{{ $note->created_at->diffForHumans() }} {{ $note->is_private ? '🔒' : '' }}</small>
                        </div>
                        <p class="mb-0 small">{{ $note->note }}</p>
                    </div>
                </div>
                @empty
                <p class="text-muted">No notes yet.</p>
                @endforelse
            </div>

            {{-- Messages --}}
            <div class="tab-pane fade" id="tab-messages">
                <div class="mb-3" style="max-height:300px; overflow-y:auto;">
                    @forelse($case->messages->sortBy('created_at') as $msg)
                    <div class="d-flex mb-2 {{ $msg->sender_type === 'clinician' ? 'justify-content-end' : '' }}">
                        <div class="card p-2 px-3 {{ $msg->sender_type === 'clinician' ? 'bg-primary text-white' : 'bg-light' }}" style="max-width:80%; border-radius:12px;">
                            <small class="fw-semibold d-block">{{ ucfirst($msg->sender_type ?? 'System') }}</small>
                            <p class="mb-0 small">{{ $msg->body }}</p>
                            <small class="opacity-75">{{ $msg->created_at->format('H:i') }}</small>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No messages yet.</p>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('clinician.cases.messages.store', $case->uuid) }}">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="body" class="form-control" placeholder="Type a message..." required>
                        <button class="btn btn-primary"><i class="bi bi-send"></i></button>
                    </div>
                </form>
            </div>

            {{-- Files --}}
            <div class="tab-pane fade" id="tab-files">
                @forelse($case->files as $file)
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <i class="bi bi-file-earmark me-2"></i>
                        <span>{{ $file->original_name }}</span>
                        <span class="badge bg-secondary ms-2">{{ $file->type }}</span>
                    </div>
                    <small class="text-muted">{{ number_format($file->size / 1024, 1) }} KB</small>
                </div>
                @empty
                <p class="text-muted">No files attached.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Support Modal --}}
<div class="modal fade" id="supportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('clinician.cases.support', $case->uuid) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="bi bi-headset me-2"></i>Escalate to Support</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">This will move the case to <strong>Support</strong> status and make it visible to the partner so they can provide additional information.</p>
                    <div class="mb-3">
                        <label class="form-label">Support Note <span class="text-danger">*</span></label>
                        <textarea name="support_note" class="form-control" rows="4"
                            placeholder="Describe what information is needed from the partner..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Escalate to Support</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Cancel Modal --}}
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('clinician.cases.cancel', $case->uuid) }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Decline Case</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for declining..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="submit" class="btn btn-danger">Decline Case</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
