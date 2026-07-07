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
                    <tr>
                        <th>Height</th>
                        <td>{{ $case->patient->height ? (int)floor($case->patient->height/12)."' ".round(fmod($case->patient->height,12)).'"' : '—' }}</td>
                    </tr>
                    <tr><th>Weight</th><td>{{ $case->patient->weight ? number_format($case->patient->weight,1).' lbs' : '—' }}</td></tr>
                    <tr><th>BMI</th><td>{{ $case->patient->bmi ? number_format($case->patient->bmi,1) : '—' }}</td></tr>
                </table>
            </div>
        </div>

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
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages
    @if($unreadMessageCount > 0)
        <span class="badge bg-warning text-dark">{{ $unreadMessageCount }} new</span>
    @elseif($case->messages->count() > 0)
        <span class="badge bg-secondary">{{ $case->messages->count() }}</span>
    @endif
</a></li>
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
                    @php
                        $qText   = $answer->question_text;
                        $isLong  = mb_strlen($qText) > 200;
                        $preview = $isLong ? mb_substr($qText, 0, 200) : $qText;
                        $decoded = json_decode($answer->answer, true);
                    @endphp
                    <div class="d-flex px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}
                                {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                        <div class="text-muted small" style="min-width:45%; max-width:45%; padding-right:1rem; line-height:1.4;">
                            @if($isLong)
                                <span class="q-preview">{{ $preview }}<span class="text-muted">…</span></span>
                                <span class="q-full d-none">{{ $qText }}</span>
                                <br>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 mt-1 q-toggle"
                                        style="font-size:.72rem;">
                                    Show more
                                </button>
                            @else
                                {{ $qText }}
                            @endif
                            @if($answer->is_disqualified)
                                <span class="badge bg-danger ms-1" style="font-size:.6rem">
                                    <i class="bi bi-slash-circle"></i> Disqualifying
                                </span>
                            @endif
                        </div>
                        <div class="small fw-semibold">
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
                @php
                    $msgs          = $case->messages->sortBy('created_at');
                    $clinicianName = $case->clinician?->user->name ?? 'You';
                    $patientName   = $case->patient->full_name ?? 'Patient';

                    $initials = function(string $name): string {
                        $parts = explode(' ', trim($name));
                        return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
                    };
                @endphp

                @if($msgs->isEmpty())
                    <div class="text-center text-muted py-5 mb-3">
                        <i class="bi bi-chat-dots" style="font-size:2.5rem;opacity:.2"></i>
                        <p class="mt-3 mb-0 small">No messages on this case yet.</p>
                    </div>
                @else
                {{-- Thread --}}
                <div id="clinThread"
                     style="max-height:420px; overflow-y:auto; background:#f8f9fc;
                            border-radius:12px; padding:16px 12px; scroll-behavior:smooth; margin-bottom:16px;">
                    @php $prevDate = null; @endphp
                    @foreach($msgs as $msg)
                    @php
                        $msgDate   = $msg->created_at->format('Y-m-d');
                        $isClinic  = $msg->sender_type === 'clinician';
                        $isPatient = $msg->sender_type === 'patient';

                        if ($isClinic) {
                            $avatarBg  = '#4361ee';
                            $avatarStr = $initials($clinicianName);
                            $name      = 'You';
                        } elseif ($isPatient) {
                            $avatarBg  = '#2dc653';
                            $avatarStr = $initials($patientName);
                            $name      = $patientName;
                        } else {
                            $avatarBg  = '#6c757d';
                            $avatarStr = 'SY';
                            $name      = 'System';
                        }
                    @endphp

                    {{-- Date separator --}}
                    @if($msgDate !== $prevDate)
                    @php $prevDate = $msgDate; @endphp
                    <div class="d-flex align-items-center gap-2 my-3">
                        <hr class="flex-grow-1 my-0" style="border-color:#dee2e6;">
                        <span style="font-size:.7rem;color:#adb5bd;white-space:nowrap;font-weight:500;letter-spacing:.04em;text-transform:uppercase;">
                            {{ $msg->created_at->isToday() ? 'Today' : ($msg->created_at->isYesterday() ? 'Yesterday' : $msg->created_at->format('M j, Y')) }}
                        </span>
                        <hr class="flex-grow-1 my-0" style="border-color:#dee2e6;">
                    </div>
                    @endif

                    {{-- Message row --}}
                    <div class="d-flex align-items-end gap-2 mb-3 {{ $isClinic ? 'flex-row-reverse' : '' }}">
                        {{-- Avatar --}}
                        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-semibold"
                             style="width:34px;height:34px;background:{{ $avatarBg }};color:#fff;
                                    font-size:.68rem;letter-spacing:.02em;">
                            {{ $avatarStr }}
                        </div>

                        {{-- Bubble + meta --}}
                        <div style="max-width:68%;">
                            <div class="d-flex align-items-baseline gap-1 mb-1 {{ $isClinic ? 'justify-content-end' : '' }}">
                                <span style="font-size:.72rem;font-weight:600;color:#495057;">{{ $name }}</span>
                                <span style="font-size:.67rem;color:#adb5bd;">{{ $msg->created_at->format('H:i') }}</span>
                            </div>

                            @if($isClinic)
                            <div style="background:#4361ee;color:#fff;padding:10px 14px;
                                        border-radius:16px 4px 16px 16px;
                                        font-size:.875rem;line-height:1.5;word-break:break-word;
                                        box-shadow:0 2px 8px rgba(67,97,238,.2);">
                                {{ $msg->body }}
                            </div>
                            @elseif($isPatient)
                            <div style="background:#fff;color:#212529;padding:10px 14px;
                                        border-radius:4px 16px 16px 16px;
                                        border:1px solid #e9ecef;
                                        font-size:.875rem;line-height:1.5;word-break:break-word;
                                        box-shadow:0 1px 4px rgba(0,0,0,.06);">
                                {{ $msg->body }}
                            </div>
                            @else
                            <div style="background:#f1f3f5;color:#495057;padding:8px 12px;
                                        border-radius:8px;border:1px dashed #dee2e6;
                                        font-size:.8rem;line-height:1.5;word-break:break-word;">
                                {{ $msg->body }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

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

                {{-- Upload form --}}
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0"><i class="bi bi-upload me-2"></i>Upload File</h6></div>
                    <div class="card-body">
                        <form method="POST"
                              action="{{ route('clinician.cases.files.store', $case->uuid) }}"
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
                        <form method="POST" action="{{ route('clinician.cases.files.destroy', [$case->uuid, $file->uuid]) }}"
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

@section('scripts')
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.q-toggle');
    if (!btn) return;
    var row     = btn.closest('.text-muted');
    var preview = row.querySelector('.q-preview');
    var full    = row.querySelector('.q-full');
    var expanded = full.classList.contains('d-none');
    preview.classList.toggle('d-none', expanded);
    full.classList.toggle('d-none', !expanded);
    btn.textContent = expanded ? 'Hide' : 'Show more';
});
</script>
@endsection
