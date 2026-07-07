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
                    @if($case->visit_type)
                    <tr>
                        <th>Visit Type</th>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:.75rem">{{ $case->visit_type }}</span></td>
                    </tr>
                    @endif
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
                    <tr>
                        <th>Height</th>
                        <td>{{ $case->patient->height ? (int)floor($case->patient->height/12)."' ".round(fmod($case->patient->height,12)).'"' : '—' }}</td>
                    </tr>
                    <tr><th>Weight</th><td>{{ $case->patient->weight ? number_format($case->patient->weight,1).' lbs' : '—' }}</td></tr>
                    <tr><th>BMI</th><td>{{ $case->patient->bmi ? number_format($case->patient->bmi,1) : '—' }}</td></tr>
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
                @if($case->patient_state)
                    @php
                        $unlicensedCount = collect($clinicians)->filter(fn($c) => !$c->isLicensedInState($case->patient_state))->count();
                    @endphp
                    @if($unlicensedCount > 0)
                        <div class="alert alert-warning py-2 px-2 mb-2 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ $unlicensedCount }} clinician(s) not licensed in <strong>{{ $case->patient_state }}</strong> — marked ⚠ below.
                        </div>
                    @endif
                @endif
                <form method="POST" action="{{ route('admin.cases.assign', $case->uuid) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Clinician</label>
                        <select name="clinician_id" class="form-select form-select-sm" required>
                            <option value="">— Choose clinician —</option>
                            @foreach($clinicians as $c)
                                @php $unlicensed = $case->patient_state && !$c->isLicensedInState($case->patient_state); @endphp
                                <option value="{{ $c->id }}" {{ $case->clinician_id == $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }}
                                    @if($c->specialty) ({{ $c->specialty }})@endif
                                    @if(!$c->is_available) — Unavailable @endif
                                    @if($unlicensed) ⚠ Not licensed in {{ $case->patient_state }} @endif
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
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tab-questionnaires">
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
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-messages">
                    <i class="bi bi-chat-dots me-1"></i>Communication
                    @php $unreadPatientMsgs = $case->messages->where('direction','inbound')->where('is_read',false)->count(); @endphp
                    @if($unreadPatientMsgs > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $unreadPatientMsgs }} new</span>
                    @elseif($case->messages->count())
                        <span class="badge bg-secondary ms-1">{{ $case->messages->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tab-files">
                    Files
                    @if($case->files->count())
                        <span class="badge bg-secondary">{{ $case->files->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-timeline">Timeline</a></li>
        </ul>

        <div class="tab-content">
            {{-- Questionnaire Responses --}}
            <div class="tab-pane fade show active" id="tab-questionnaires">
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
                @if($case->messages->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-dots" style="font-size:2rem;opacity:.3"></i>
                        <p class="mt-2 mb-0">No messages yet on this case.</p>
                    </div>
                @else
                {{-- Summary bar --}}
                @php
                    $msgs          = $case->messages->sortBy('created_at');
                    $totalMsgs     = $msgs->count();
                    $clinicianMsgs = $msgs->where('sender_type','clinician')->count();
                    $patientMsgs   = $msgs->where('sender_type','patient')->count();
                    $unreadCount   = $msgs->where('direction','inbound')->where('is_read',false)->count();
                @endphp
                <div class="d-flex gap-3 mb-3 pb-2 border-bottom flex-wrap" style="font-size:.8rem">
                    <span class="text-muted"><i class="bi bi-chat-square-dots me-1"></i>{{ $totalMsgs }} total</span>
                    <span class="text-primary"><i class="bi bi-person-badge me-1"></i>{{ $clinicianMsgs }} from clinician</span>
                    <span class="text-success"><i class="bi bi-person me-1"></i>{{ $patientMsgs }} from patient</span>
                    @if($unreadCount > 0)
                        <span class="text-warning fw-semibold"><i class="bi bi-envelope me-1"></i>{{ $unreadCount }} unread by clinician</span>
                    @endif
                </div>

                {{-- Thread --}}
                <div style="max-height:480px; overflow-y:auto; padding-right:4px;" id="commThread">
                    @php $prevDate = null; @endphp
                    @foreach($msgs as $msg)
                        @php
                            $msgDate     = $msg->created_at->format('Y-m-d');
                            $isClinic    = $msg->sender_type === 'clinician';
                            $isPatient   = $msg->sender_type === 'patient';
                            $bubbleAlign = $isClinic ? 'justify-content-end' : 'justify-content-start';
                            $bubbleBg    = $isClinic ? 'bg-primary text-white' : ($isPatient ? 'bg-light border' : 'bg-warning-subtle border');
                            $senderLabel = match($msg->sender_type) {
                                'clinician' => '🩺 ' . ($case->clinician?->user->name ?? 'Clinician'),
                                'patient'   => '👤 Patient',
                                default     => '⚙ System',
                            };
                        @endphp

                        {{-- Date separator --}}
                        @if($msgDate !== $prevDate)
                        <div class="text-center my-2">
                            <span class="badge bg-light text-muted border" style="font-size:.72rem">
                                {{ $msg->created_at->isToday() ? 'Today' : ($msg->created_at->isYesterday() ? 'Yesterday' : $msg->created_at->format('M j, Y')) }}
                            </span>
                        </div>
                        @php $prevDate = $msgDate; @endphp
                        @endif

                        <div class="d-flex {{ $bubbleAlign }} mb-2">
                            <div style="max-width:75%">
                                {{-- Sender label --}}
                                <div class="mb-1 {{ $isClinic ? 'text-end' : '' }}" style="font-size:.72rem; color:#6c757d">
                                    {{ $senderLabel }}
                                    &nbsp;·&nbsp;{{ $msg->created_at->format('H:i') }}
                                    @if($isPatient)
                                        @if($msg->is_read)
                                            &nbsp;<i class="bi bi-check2-all text-primary" title="Read by clinician at {{ $msg->read_at?->format('M j H:i') }}"></i>
                                        @else
                                            &nbsp;<i class="bi bi-check2 text-muted" title="Not yet read by clinician"></i>
                                        @endif
                                    @endif
                                </div>
                                {{-- Bubble --}}
                                <div class="{{ $bubbleBg }} p-2 px-3" style="border-radius:12px; font-size:.875rem; word-break:break-word">
                                    {{ $msg->body }}
                                </div>
                                {{-- Channel badge --}}
                                @if($msg->channel && $msg->channel !== 'portal')
                                <div class="{{ $isClinic ? 'text-end' : '' }} mt-1" style="font-size:.68rem;color:#adb5bd">
                                    via {{ $msg->channel }}
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Files --}}
            <div class="tab-pane fade" id="tab-files">

                {{-- Upload form --}}
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0"><i class="bi bi-upload me-2"></i>Upload File</h6></div>
                    <div class="card-body">
                        <form method="POST"
                              action="{{ route('admin.cases.files.store', $case->uuid) }}"
                              enctype="multipart/form-data"
                              class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold mb-1">File <span class="text-danger">*</span></label>
                                <input type="file" name="file" class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="form-text">PDF, JPG or PNG — max 10 MB</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold mb-1">Type</label>
                                <select name="type" class="form-select form-select-sm">
                                    <option value="other">Other</option>
                                    <option value="lab_result">Lab Result</option>
                                    <option value="id_doc">ID Document</option>
                                    <option value="consent">Consent</option>
                                    <option value="medical_necessity">Medical Necessity</option>
                                    <option value="intake">Intake</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold mb-1">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional note">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-upload"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- File list --}}
                @forelse($case->files->sortByDesc('created_at') as $file)
                <div class="d-flex align-items-center gap-3 border rounded px-3 py-2 mb-2 bg-white">
                    <i class="bi bi-{{ str_ends_with($file->original_name, '.pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary' }} fs-5 flex-shrink-0"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate">{{ $file->original_name }}</div>
                        <div class="text-muted" style="font-size:.72rem;">
                            {{ ucfirst(str_replace('_', ' ', $file->type)) }}
                            &bull; {{ number_format($file->size / 1024, 1) }} KB
                            &bull; {{ $file->created_at->format('M d, Y H:i') }}
                            &bull; <span class="badge
                                @if($file->status === 'processed') bg-success
                                @elseif($file->status === 'failed') bg-danger
                                @elseif($file->status === 'processing') bg-warning text-dark
                                @else bg-secondary
                                @endif" style="font-size:.65rem;">{{ ucfirst($file->status) }}</span>
                            @if($file->notes) &bull; {{ $file->notes }} @endif
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        @if($file->status !== 'failed')
                        <a href="{{ Storage::url($file->path) }}" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2">
                            <i class="bi bi-download"></i>
                        </a>
                        @endif
                        <form method="POST" action="{{ route('admin.cases.files.destroy', [$case->uuid, $file->uuid]) }}"
                              onsubmit="return confirm('Delete this file?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm py-0 px-2">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-folder2-open fs-2 d-block mb-2 opacity-25"></i>
                    No files attached to this case yet.
                </div>
                @endforelse
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

@section('scripts')
<script>
// Scroll communication thread to bottom when the tab is shown
document.querySelectorAll('[href="#tab-messages"]').forEach(function(tab) {
    tab.addEventListener('shown.bs.tab', function () {
        var thread = document.getElementById('commThread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    });
});
</script>
@endsection
