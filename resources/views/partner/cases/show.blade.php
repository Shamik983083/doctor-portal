@extends('layouts.partner')
@php $title = 'Case ' . substr($case->uuid, 0, 8) . '…'; @endphp
@section('title', $title)
@section('page-title', $title)

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <a href="{{ route('partner.cases.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Back to Cases
    </a>
    <span class="badge badge-{{ $case->status }} fs-6">{{ ucfirst($case->status) }}</span>
</div>

<div class="row g-4">
    <!-- Left: case summary -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Case Details</h6></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">UUID</dt>
                    <dd class="col-7"><code class="small">{{ $case->uuid }}</code></dd>

                    @if($case->external_id)
                    <dt class="col-5 text-muted">External ID</dt>
                    <dd class="col-7"><code>{{ $case->external_id }}</code></dd>
                    @endif

                    <dt class="col-5 text-muted">Patient</dt>
                    <dd class="col-7">
                        <a href="{{ route('partner.patients.show', $case->patient->id) }}" class="text-decoration-none">
                            {{ $case->patient->first_name }} {{ $case->patient->last_name }}
                        </a>
                    </dd>

                    <dt class="col-5 text-muted">Clinician</dt>
                    <dd class="col-7">{{ $case->clinician?->user->name ?? 'Unassigned' }}</dd>

                    <dt class="col-5 text-muted">Created</dt>
                    <dd class="col-7">{{ $case->created_at->format('M j, Y g:i A') }}</dd>

                    @if($case->assigned_at)
                    <dt class="col-5 text-muted">Assigned</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->assigned_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->approved_at)
                    <dt class="col-5 text-muted">Approved</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->approved_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->completed_at)
                    <dt class="col-5 text-muted">Completed</dt>
                    <dd class="col-7">{{ \Carbon\Carbon::parse($case->completed_at)->format('M j, Y g:i A') }}</dd>
                    @endif

                    @if($case->cancellation_reason)
                    <dt class="col-5 text-muted">Cancel Reason</dt>
                    <dd class="col-7 text-danger">{{ $case->cancellation_reason }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Patient Vitals -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-person-heart me-2"></i>Patient</h6></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Name</dt>
                    <dd class="col-7">
                        <a href="{{ route('partner.patients.show', $case->patient->id) }}" class="text-decoration-none">
                            {{ $case->patient->full_name }}
                        </a>
                    </dd>
                    <dt class="col-5 text-muted">DOB</dt>
                    <dd class="col-7">{{ $case->patient->date_of_birth?->format('M j, Y') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Gender</dt>
                    <dd class="col-7">{{ $case->patient->gender ? ucfirst($case->patient->gender) : '—' }}</dd>
                    <dt class="col-5 text-muted">Height</dt>
                    <dd class="col-7">{{ $case->patient->height ? (int)floor($case->patient->height/12)."' ".round(fmod($case->patient->height,12)).'"' : '—' }}</dd>
                    <dt class="col-5 text-muted">Weight</dt>
                    <dd class="col-7">{{ $case->patient->weight ? number_format($case->patient->weight,1).' lbs' : '—' }}</dd>
                    <dt class="col-5 text-muted">BMI</dt>
                    <dd class="col-7">{{ $case->patient->bmi ? number_format($case->patient->bmi,1) : '—' }}</dd>
                </dl>
            </div>
        </div>

        <!-- Offerings -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Offerings</h6></div>
            <div class="card-body p-0">
                @foreach($case->caseOfferings as $co)
                <div class="px-3 py-2 border-bottom">
                    <div class="fw-medium small">{{ $co->offering->name }}</div>
                    @if($co->dosage)<div class="text-muted small">{{ $co->dosage }}</div>@endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Support note from clinician --}}
        @if($case->support_note)
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning bg-opacity-10 py-2">
                <h6 class="mb-0 fw-semibold text-warning"><i class="bi bi-headset me-1"></i>Support Note from Clinician</h6>
            </div>
            <div class="card-body small">
                <p class="mb-0" style="white-space:pre-line;">{{ $case->support_note }}</p>
                @if($case->clinician)
                <p class="text-muted mb-0 mt-2">— {{ $case->clinician->user->name }}</p>
                @endif
            </div>
        </div>
        @endif

        {{-- Return to clinician --}}
        @if($case->status === 'support')
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary bg-opacity-10 py-2">
                <h6 class="mb-0 fw-semibold text-primary"><i class="bi bi-arrow-return-left me-1"></i>Return to Clinician</h6>
            </div>
            <div class="card-body">
                @if($errors->has('partner_note'))
                    <div class="alert alert-danger py-2 small">{{ $errors->first('partner_note') }}</div>
                @endif
                <form method="POST" action="{{ route('partner.cases.return-to-clinician', $case->uuid) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Your response / note <span class="text-danger">*</span></label>
                        <textarea name="partner_note" class="form-control form-control-sm" rows="3"
                                  placeholder="Provide the information requested by the clinician…" required>{{ old('partner_note') }}</textarea>
                    </div>
                    <button class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-arrow-return-left me-1"></i> Return to Clinician
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>

    <!-- Right: tabs -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-events">Timeline</a></li>
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
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notes">Clinical Notes</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-messages">Messages</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-orders">Orders</a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Timeline -->
                    <div class="tab-pane fade show active" id="tab-events">
                        @php
                        $eventLabel = function($event) {
                            $from = $event->payload['from'] ?? null;
                            $to   = $event->payload['to']   ?? null;

                            if ($event->event_type === 'clinician_reassigned') {
                                return ['label' => 'Clinician reassigned', 'color' => 'bg-primary'];
                            }

                            $map = [
                                'created→waiting'      => ['Case submitted',             'bg-info text-dark'],
                                'waiting→assigned'     => ['Case assigned to clinician', 'bg-primary'],
                                'created→assigned'     => ['Case assigned to clinician', 'bg-primary'],
                                'assigned→support'     => ['Sent to support',            'bg-warning text-dark'],
                                'support→assigned'     => ['Returned to clinician',      'bg-primary'],
                                'assigned→approved'    => ['Case approved',              'bg-success'],
                                'approved→processing'  => ['Sent to pharmacy',           'bg-success'],
                                'processing→completed' => ['Case completed',             'bg-success'],
                                'assigned→cancelled'   => ['Case cancelled',             'bg-danger'],
                                'waiting→cancelled'    => ['Case cancelled',             'bg-danger'],
                                'support→cancelled'    => ['Case cancelled',             'bg-danger'],
                                'approved→cancelled'   => ['Case cancelled',             'bg-danger'],
                                'created→cancelled'    => ['Case cancelled',             'bg-danger'],
                            ];

                            $key = $from . '→' . $to;
                            if (isset($map[$key])) {
                                return ['label' => $map[$key][0], 'color' => $map[$key][1]];
                            }

                            $label = $to ? ucfirst($from) . ' → ' . ucfirst($to) : ucfirst(str_replace('_', ' ', $event->event_type));
                            return ['label' => $label, 'color' => 'bg-secondary'];
                        };

                        $actorLabel = function($event) {
                            return match($event->actor_type) {
                                'admin'     => 'Admin',
                                'clinician' => 'Clinician',
                                'partner'   => 'Partner',
                                default     => 'System',
                            };
                        };
                        @endphp

                        @forelse($case->events->sortByDesc('created_at') as $event)
                        @php [$label, $color] = array_values($eventLabel($event)); @endphp
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                            <div class="text-muted text-nowrap" style="min-width:110px; font-size:.75rem; padding-top:2px;">
                                {{ $event->created_at->format('M d, H:i') }}
                            </div>
                            <div>
                                <span class="badge {{ $color }} mb-1">{{ $label }}</span>
                                <div class="small text-muted">{{ $actorLabel($event) }}</div>
                                @if($event->notes)
                                    <p class="small mb-0 mt-1 text-body">{{ $event->notes }}</p>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small">No events recorded.</p>
                        @endforelse
                    </div>

                    <!-- Questionnaires -->
                    <div class="tab-pane fade" id="tab-questionnaires">
                        @forelse($case->questionnaireResponses as $response)
                        <div class="card mb-3 {{ $response->is_disqualified ? 'border-danger' : 'border-0 shadow-sm' }}">
                            <div class="card-header d-flex justify-content-between align-items-center py-2
                                        {{ $response->is_disqualified ? 'bg-danger bg-opacity-10' : 'bg-light' }}">
                                <div>
                                    <span class="fw-semibold">{{ $response->questionnaire->name ?? 'Questionnaire' }}</span>
                                    @if($response->is_disqualified)
                                        <span class="badge bg-danger ms-2"><i class="bi bi-slash-circle me-1"></i>Disqualified</span>
                                    @else
                                        <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Qualified</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    {{ ($response->completed_at ?? $response->created_at)->format('M d, Y H:i') }}
                                </small>
                            </div>
                            <div class="card-body p-0">
                                @forelse($response->answers as $answer)
                                @php
                                    $qText   = $answer->question_text;
                                    $isLong  = mb_strlen($qText) > 200;
                                    $preview = $isLong ? mb_substr($qText, 0, 200) : $qText;
                                    $decoded = json_decode($answer->answer, true);
                                @endphp
                                <div class="d-flex px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}
                                            {{ $answer->is_disqualified ? 'bg-danger bg-opacity-5' : '' }}">
                                    <div class="text-muted small" style="min-width:45%; max-width:45%; padding-right:1rem;">
                                        @if($isLong)
                                            <span class="q-preview">{{ $preview }}<span class="text-muted">…</span></span>
                                            <span class="q-full d-none">{{ $qText }}</span>
                                            <br>
                                            <button type="button" class="btn btn-link btn-sm p-0 mt-1 q-toggle" style="font-size:.72rem;">Show more</button>
                                        @else
                                            {{ $qText }}
                                        @endif
                                        @if($answer->is_disqualified)
                                            <i class="bi bi-slash-circle text-danger ms-1" title="Disqualifying answer"></i>
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
                        </div>
                        @empty
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-ui-checks fs-2 d-block mb-2 opacity-25"></i>
                            No questionnaire responses linked to this case.
                        </div>
                        @endforelse
                    </div>

                    <!-- Prescriptions -->
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
                                        @if($med->refills !== null)<span><span class="text-muted">Refills:</span> {{ $med->refills }}</span>@endif
                                        @if($med->quantity !== null)<span><span class="text-muted">Qty:</span> {{ $med->quantity }}</span>@endif
                                        @if($med->days_supply !== null)<span><span class="text-muted">Days Supply:</span> {{ $med->days_supply }}</span>@endif
                                        @if($med->dispense_unit)<span><span class="text-muted">Unit:</span> {{ $med->dispense_unit }}</span>@endif
                                        @if($med->days_until_dispense !== null)<span><span class="text-muted">Days Until Dispense:</span> {{ $med->days_until_dispense }}</span>@endif
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

                    <!-- Notes -->
                    <div class="tab-pane fade" id="tab-notes">
                        @forelse($case->clinicalNotes as $note)
                        <div class="border rounded p-3 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-medium">{{ $note->clinician?->user->name ?? 'Clinician' }}</span>
                                <span class="text-muted">{{ $note->created_at->format('M j, Y g:i A') }}</span>
                            </div>
                            <p class="mb-0">{{ $note->note }}</p>
                        </div>
                        @empty
                        <p class="text-muted small">No clinical notes yet.</p>
                        @endforelse
                    </div>

                    <!-- Messages -->
                    <div class="tab-pane fade" id="tab-messages">
                        @forelse($case->messages->sortBy('created_at') as $msg)
                        @php
                            $isOwn  = $msg->sender_type === 'partner';
                            $sender = match($msg->sender_type) {
                                'clinician' => 'Dr ' . ($msg->clinician?->user->name ?? 'Clinician'),
                                'partner'   => 'You',
                                'patient'   => 'Patient',
                                default     => ucfirst($msg->sender_type),
                            };
                        @endphp
                        <div class="d-flex mb-3 {{ $isOwn ? 'justify-content-end' : '' }}">
                            <div class="rounded p-3 small {{ $isOwn ? 'bg-primary text-white' : 'bg-light border' }}"
                                 style="max-width:75%;">
                                <div class="fw-semibold mb-1 {{ $isOwn ? 'text-white-50' : 'text-muted' }}"
                                     style="font-size:.72rem;">
                                    {{ $sender }}
                                </div>
                                <p class="mb-1">{{ $msg->body }}</p>
                                <div class="text-end {{ $isOwn ? 'text-white-50' : 'text-muted' }}"
                                     style="font-size:.68rem;">
                                    {{ $msg->created_at->format('M j, g:i A') }}
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small text-center py-4">No messages yet.</p>
                        @endforelse
                    </div>

                    <!-- Orders -->
                    <div class="tab-pane fade" id="tab-orders">
                        @forelse($case->orders as $order)
                        <div class="border rounded p-3 mb-3 small">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-muted">Order #{{ $order->id }}</div>
                                    <div class="fw-medium">{{ $order->pharmacy?->name ?? 'Unknown pharmacy' }}</div>
                                </div>
                                <div class="col-3">
                                    <div class="text-muted">Status</div>
                                    <div>{{ ucfirst($order->status) }}</div>
                                </div>
                                <div class="col-3">
                                    <div class="text-muted">Tracking</div>
                                    <div>{{ $order->tracking_number ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small">No orders yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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
@endpush
