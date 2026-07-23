@extends('layouts.clinician-exact')

@section('title', 'Case Review')
@section('page-title', 'Case Review')

{{--
    Case detail, rebuilt pixel-to-the-design-system on the exact shell (Devin msgs
    2296/2298): panels, preview tabs, pills, and the chat matching the Messages
    screen's bubbles. Vanilla tabs and modals (no Bootstrap here). All the real
    functionality is preserved: the 7 tabs, the real-time Echo chat with a polling
    fallback, add-note, file upload/download/delete, escalate and decline, and the
    Approve and review opening as a modal like the grid.
--}}

@php
    $initials = fn ($name) => strtoupper(collect(explode(' ', trim($name ?: '')))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    $clinicianName = $case->clinician?->user->name ?? 'You';
    $patientName   = $case->patient?->full_name ?? 'Patient';
@endphp

@section('view')
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div>
        <div class="eyebrow">Clinician</div>
        <h1>Case Review for {{ $patientName }}</h1>
        <p>{{ $case->partner?->name ?? '-' }} · case {{ $case->external_id ?? \Illuminate\Support\Str::limit($case->uuid, 8, '') }}</p>
    </div>
    @if(in_array($case->status, ['waiting', 'assigned']))
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @if($case->status === 'waiting')
        <form method="POST" action="{{ route('clinician.cases.assign', $case->uuid) }}">@csrf
            <button class="button-primary">Claim case</button>
        </form>
        @endif
        @if($case->status === 'assigned')
        <a class="button-primary" href="{{ route('clinician.cases.prescribe.form', $case->uuid) }}" data-review-url="{{ route('clinician.cases.prescribe.form', $case->uuid) }}?modal=1">Approve &amp; prescribe</a>
        <button type="button" class="button-secondary" data-open-modal="supportModal">Escalate to support</button>
        <button type="button" class="button-danger" data-open-modal="cancelModal">Decline</button>
        @endif
    </div>
    @endif
</div>

<section class="panel" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 20px">
        <div>
            <div class="eyebrow">Triage classification</div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
                <span class="pill {{ $case->triage }}">{{ ucfirst($case->triage ?? 'unclassified') }}</span>
                <span style="color:var(--muted);font-size:13px">{{ $case->triageMeaning() }}</span>
            </div>
        </div>
        @if(!empty($case->triage_reasons))
        <div style="margin-left:auto;max-width:60%">
            <div class="eyebrow" style="margin-bottom:4px">Signals ({{ $case->triage_ruleset }})</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($case->triage_reasons as $reason)
                    <span class="pill neutral" title="{{ $reason }}">{{ \Illuminate\Support\Str::before($reason, ':') }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>

<div class="case-grid">
    {{-- Left: patient + case info --}}
    <div>
        <section class="panel" style="margin-bottom:16px">
            <div class="panel-heading"><div><h2>{{ $patientName }}</h2><p>{{ $case->patient?->email }}</p></div></div>
            <dl class="rx-meta" style="padding:0 20px 16px">
                <div><dt>DOB</dt><dd>{{ $case->patient?->date_of_birth?->format('M d, Y') ?? '-' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ ucfirst($case->patient?->gender ?? '-') }}</dd></div>
                <div><dt>State</dt><dd>{{ $case->patient_state ?? $case->patient?->state ?? '-' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $case->patient?->phone ?? '-' }}</dd></div>
                <div><dt>Height</dt><dd>{{ $case->patient?->height ? (int)floor($case->patient->height/12)."' ".round(fmod($case->patient->height,12)).'"' : '-' }}</dd></div>
                <div><dt>Weight</dt><dd>{{ $case->patient?->weight ? number_format($case->patient->weight,1).' lbs' : '-' }}</dd></div>
                <div><dt>BMI</dt><dd>{{ $case->patient?->bmi ? number_format($case->patient->bmi,1) : '-' }}</dd></div>
            </dl>
        </section>

        <section class="panel">
            <div class="panel-heading"><div><h2>Case info</h2></div></div>
            <dl class="rx-meta" style="padding:0 20px 16px">
                <div><dt>Status</dt><dd><span class="pill {{ in_array($case->status, ['completed','approved','processing']) ? 'green' : (in_array($case->status, ['cancelled']) ? 'red' : ($case->status === 'support' ? 'yellow' : 'neutral')) }}">{{ ucfirst($case->status) }}</span></dd></div>
                @if($case->visit_type)<div><dt>Visit type</dt><dd>{{ $case->visit_type }}</dd></div>@endif
                <div><dt>Partner</dt><dd>{{ $case->partner?->name }}</dd></div>
                <div><dt>Clinician</dt><dd>{{ $case->clinician?->full_name ?? '-' }}</dd></div>
                <div><dt>Chargeable</dt><dd>{{ $case->is_chargeable ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Created</dt><dd>{{ $case->created_at->format('M d, Y H:i') }}</dd></div>
                @if($case->assigned_at)<div><dt>Assigned</dt><dd>{{ $case->assigned_at->format('M d, Y H:i') }}</dd></div>@endif
            </dl>
        </section>
    </div>

    {{-- Right: tabs --}}
    <div>
        <div class="case-tabs" role="tablist">
            <button class="case-tab active" data-tab="offerings">Offerings</button>
            <button class="case-tab" data-tab="prescriptions">Prescriptions @if($case->casePrescriptions->count())<span class="pill green">{{ $case->casePrescriptions->count() }}</span>@endif</button>
            <button class="case-tab" data-tab="questionnaires">Questionnaires @if($case->questionnaireResponses->count())<span class="pill neutral">{{ $case->questionnaireResponses->count() }}</span>@endif</button>
            <button class="case-tab" data-tab="notes">Notes <span class="pill neutral">{{ $case->clinicalNotes->count() }}</span></button>
            <button class="case-tab" data-tab="messages">Messages @if($unreadMessageCount > 0)<span class="pill yellow">{{ $unreadMessageCount }}</span>@elseif($case->messages->count())<span class="pill neutral">{{ $case->messages->count() }}</span>@endif</button>
            <button class="case-tab" data-tab="files">Files <span class="pill neutral">{{ $case->files->count() }}</span></button>
            <button class="case-tab" data-tab="timeline">Timeline</button>
        </div>

        {{-- Offerings --}}
        <div class="case-pane" data-pane="offerings">
            @forelse($case->caseOfferings as $co)
            <section class="panel" style="margin-bottom:10px"><div style="padding:14px 18px">
                <div style="display:flex;justify-content:space-between;gap:12px">
                    <div><strong>{{ $co->offering->name }}</strong><div style="color:var(--muted);font-size:12px">{{ ucfirst($co->offering->type ?? '') }} · Qty: {{ $co->quantity }}</div></div>
                    <span class="pill neutral">{{ ucfirst($co->status) }}</span>
                </div>
                @if($co->dosage)<div style="font-size:13px;margin-top:6px"><strong>Dosage:</strong> {{ $co->dosage }}</div>@endif
                @if($co->frequency)<div style="font-size:13px"><strong>Frequency:</strong> {{ $co->frequency }}</div>@endif
            </div></section>
            @empty
            <p class="ai-honesty">No offerings attached.</p>
            @endforelse
        </div>

        {{-- Prescriptions --}}
        <div class="case-pane" data-pane="prescriptions" hidden>
            @forelse($case->casePrescriptions->sortByDesc('prescribed_at') as $rx)
            <section class="panel" style="margin-bottom:12px">
                <div class="panel-heading"><div><h2>Prescription</h2><p>by {{ $rx->clinician->full_name ?? '-' }} · {{ $rx->prescribed_at->format('M d, Y H:i') }}</p></div></div>
                <div style="padding:0 20px 16px">
                    <dl class="rx-meta">
                        <div><dt>Diagnoses</dt><dd style="white-space:pre-line">{{ $rx->diagnoses }}</dd></div>
                        @if($rx->directions)<div><dt>Directions</dt><dd style="white-space:pre-line">{{ $rx->directions }}</dd></div>@endif
                        @if($rx->medical_necessity)<div><dt>Medical necessity</dt><dd style="white-space:pre-line">{{ $rx->medical_necessity }}</dd></div>@endif
                    </dl>
                    @if($rx->medications->count())
                    <div class="subheading" style="margin:12px 0 8px">Medications</div>
                    @foreach($rx->medications as $med)
                    <div style="border:1px solid var(--line);border-radius:10px;padding:12px;margin-bottom:8px">
                        <strong>{{ $med->name }}</strong>
                        @if($med->compound_formula)<div style="color:var(--muted);font-size:12px;margin:2px 0 6px">{{ $med->compound_formula }}</div>@endif
                        <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:13px">
                            @if($med->refills !== null)<span><span style="color:var(--muted)">Refills:</span> {{ $med->refills }}</span>@endif
                            @if($med->quantity !== null)<span><span style="color:var(--muted)">Qty:</span> {{ $med->quantity }}</span>@endif
                            @if($med->days_supply !== null)<span><span style="color:var(--muted)">Days supply:</span> {{ $med->days_supply }}</span>@endif
                            @if($med->dispense_unit)<span><span style="color:var(--muted)">Unit:</span> {{ $med->dispense_unit }}</span>@endif
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
            </section>
            @empty
            <div class="stub"><strong>No prescription yet</strong>No prescription has been submitted for this case.</div>
            @endforelse
        </div>

        {{-- Questionnaires --}}
        <div class="case-pane" data-pane="questionnaires" hidden>
            @forelse($case->questionnaireResponses as $response)
            <section class="panel" style="margin-bottom:12px">
                <div class="panel-heading">
                    <div><h2>{{ $response->questionnaire->name ?? 'Questionnaire' }}</h2><p>{{ ($response->completed_at ?? $response->created_at)->format('M d, Y H:i') }}</p></div>
                    <div class="quick-pills">
                        @if($response->is_disqualified)<span class="pill red">Disqualified</span>@else<span class="pill green">Qualified</span>@endif
                    </div>
                </div>
                <div class="qa-sheet" style="padding:0 8px 12px;margin:0">
                    @forelse($response->answers as $answer)
                        @php $decoded = json_decode($answer->answer, true); @endphp
                        <div class="qa {{ $answer->is_disqualified ? 'consent' : '' }}">
                            <dt>{{ $answer->question_text }}</dt>
                            <dd>@if(is_array($decoded)){{ implode(', ', $decoded) }}@elseif(filled($answer->answer)){{ $answer->answer }}@else <span style="color:var(--muted)">-</span>@endif</dd>
                        </div>
                    @empty
                        <p class="ai-honesty" style="padding:8px 12px">No answers recorded.</p>
                    @endforelse
                </div>
            </section>
            @empty
            <div class="stub"><strong>No questionnaires</strong>No questionnaire responses are linked to this case.</div>
            @endforelse
        </div>

        {{-- Notes --}}
        <div class="case-pane" data-pane="notes" hidden>
            <section class="panel" style="margin-bottom:12px"><div style="padding:16px 18px">
                <form method="POST" action="{{ route('clinician.cases.notes.store', $case->uuid) }}">@csrf
                    <div class="field-row">
                        <div class="field"><label>Type</label>
                            <select name="type" id="noteType">
                                <option value="general">General</option>
                                <option value="soap">SOAP</option>
                                <option value="progress">Progress</option>
                            </select>
                        </div>
                        <div class="field"><label>&nbsp;</label>
                            <label class="check-line"><input type="checkbox" name="is_private" value="1"> Private note</label>
                        </div>
                    </div>
                    {{-- General (default) --}}
                    <div id="note-fields-general">
                        <textarea name="note" class="note-area" rows="3" placeholder="Add a clinical note." required></textarea>
                    </div>
                    {{-- SOAP: Subjective / Objective / Assessment / Plan --}}
                    <div id="note-fields-soap" hidden>
                        <div class="field-row" style="margin-bottom:8px">
                            <div class="field"><label style="font-size:12px">Subjective <span style="font-weight:400;color:var(--muted)">(patient-reported symptoms)</span></label>
                                <textarea name="soap_s" class="note-area" rows="2" placeholder="What the patient reports — pain, complaints, history…"></textarea>
                            </div>
                            <div class="field"><label style="font-size:12px">Objective <span style="font-weight:400;color:var(--muted)">(measurable findings)</span></label>
                                <textarea name="soap_o" class="note-area" rows="2" placeholder="Vitals, exam findings, lab results…"></textarea>
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field"><label style="font-size:12px">Assessment <span style="font-weight:400;color:var(--muted)">(clinical diagnosis)</span></label>
                                <textarea name="soap_a" class="note-area" rows="2" placeholder="Diagnosis or differential…"></textarea>
                            </div>
                            <div class="field"><label style="font-size:12px">Plan <span style="font-weight:400;color:var(--muted)">(treatment)</span></label>
                                <textarea name="soap_p" class="note-area" rows="2" placeholder="Medications, referrals, follow-up…"></textarea>
                            </div>
                        </div>
                    </div>
                    {{-- Progress: Status / Changes / Response / Next Steps --}}
                    <div id="note-fields-progress" hidden>
                        <div class="field-row" style="margin-bottom:8px">
                            <div class="field"><label style="font-size:12px">Current Status</label>
                                <textarea name="prog_status" class="note-area" rows="2" placeholder="Patient's current clinical status…"></textarea>
                            </div>
                            <div class="field"><label style="font-size:12px">Changes Since Last Visit</label>
                                <textarea name="prog_changes" class="note-area" rows="2" placeholder="Improvements, regressions, new symptoms…"></textarea>
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field"><label style="font-size:12px">Treatment Response</label>
                                <textarea name="prog_response" class="note-area" rows="2" placeholder="How the patient is responding to treatment…"></textarea>
                            </div>
                            <div class="field"><label style="font-size:12px">Next Steps</label>
                                <textarea name="prog_next" class="note-area" rows="2" placeholder="Upcoming interventions, referrals, goals…"></textarea>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:10px"><button class="button-primary">Add note</button></div>
                </form>
            </div></section>
            @php($myClinicianId = Auth::user()->clinician?->id)
            @forelse($case->clinicalNotes->filter(fn($n) => !$n->is_private || $n->clinician_id === $myClinicianId)->sortByDesc('created_at') as $note)
            <section class="panel" style="margin-bottom:8px;{{ $note->is_private ? 'border-color:#e5c07a' : '' }}"><div style="padding:12px 16px">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <strong>{{ $note->clinician->full_name ?? 'Unknown' }} <span style="color:var(--muted)">· {{ ucfirst($note->type) }}</span></strong>
                    <span style="color:var(--muted)">{{ $note->created_at->diffForHumans() }} {{ $note->is_private ? '· private' : '' }}</span>
                </div>
                @php
                    $nd = null;
                    if (in_array($note->type, ['soap','progress'])) {
                        $dec = json_decode($note->note, true);
                        if (is_array($dec)) $nd = $dec;
                    }
                @endphp
                @if($nd && $note->type === 'soap')
                <div style="font-size:13px;display:grid;grid-template-columns:1fr 1fr;gap:6px 14px">
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Subjective</span><p style="margin:2px 0 0">{{ $nd['s'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Objective</span><p style="margin:2px 0 0">{{ $nd['o'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Assessment</span><p style="margin:2px 0 0">{{ $nd['a'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Plan</span><p style="margin:2px 0 0">{{ $nd['p'] ?? '' }}</p></div>
                </div>
                @elseif($nd && $note->type === 'progress')
                <div style="font-size:13px;display:grid;grid-template-columns:1fr 1fr;gap:6px 14px">
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Current Status</span><p style="margin:2px 0 0">{{ $nd['status'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Changes Since Last Visit</span><p style="margin:2px 0 0">{{ $nd['changes'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Treatment Response</span><p style="margin:2px 0 0">{{ $nd['response'] ?? '' }}</p></div>
                    <div><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em">Next Steps</span><p style="margin:2px 0 0">{{ $nd['next'] ?? '' }}</p></div>
                </div>
                @else
                <div style="font-size:13px">{{ $note->note }}</div>
                @endif
            </div></section>
            @empty
            <p class="ai-honesty">No notes yet.</p>
            @endforelse
        </div>

        {{-- Messages: same chat style as the Messages screen --}}
        <div class="case-pane" data-pane="messages" hidden>
            <section class="panel chat" style="min-height:auto">
                <div class="chat-head">
                    <span class="msg-avatar big">{{ $initials($patientName) }}</span>
                    <div><strong>{{ $patientName }}</strong><span>{{ $case->partner?->name ?? '' }}</span></div>
                </div>
                <div class="chat-scroll" id="clinThread" style="max-height:440px"
                     data-last-id="{{ $case->messages->sortBy('created_at')->last()?->id ?? 0 }}"
                     data-poll-url="{{ route('clinician.cases.messages.poll', $case->uuid) }}">
                    @php $lastSide = null; @endphp
                    @forelse($case->messages->sortBy('created_at') as $msg)
                        @php $side = $msg->sender_type === 'clinician' ? 'me' : 'them'; @endphp
                        @if($side !== $lastSide)
                            <div class="chat-time" data-date="{{ $msg->created_at->format('Y-m-d') }}">{{ $msg->created_at->format('M j, g:i A') }}</div>
                            @php $lastSide = $side; @endphp
                        @endif
                        <div class="bubble-row {{ $side }}"><div class="bubble {{ $side }}">{{ $msg->body }}</div></div>
                    @empty
                        <div class="chat-time" id="clinThreadEmpty">No messages on this case yet. Send the first one below.</div>
                    @endforelse
                </div>
                <div class="chat-compose">
                    <input type="text" id="clinMsgInput" placeholder="Message" aria-label="Message" autocomplete="off"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();clinSendMessage();}">
                    <button type="button" class="chat-send" aria-label="Send" onclick="clinSendMessage()">&uarr;</button>
                </div>
            </section>
        </div>

        {{-- Files --}}
        <div class="case-pane" data-pane="files" hidden>
            <section class="panel" style="margin-bottom:12px"><div style="padding:16px 18px">
                <div class="subheading" style="margin-bottom:10px">Upload file</div>
                <form method="POST" action="{{ route('clinician.cases.files.store', $case->uuid) }}" enctype="multipart/form-data">@csrf
                    <div class="field-row">
                        <div class="field"><label>File</label><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
                        <div class="field"><label>Type</label>
                            <select name="type"><option value="other">Other</option><option value="lab_result">Lab result</option><option value="id_doc">ID document</option><option value="consent">Consent</option><option value="medical_necessity">Medical necessity</option><option value="intake">Intake</option></select>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field"><label>Note</label><input type="text" name="notes" placeholder="Optional note"></div>
                        <div class="field" style="display:flex;align-items:flex-end"><button class="button-primary">Upload</button></div>
                    </div>
                    <div class="ai-honesty">PDF, JPG or PNG, max 10 MB.</div>
                </form>
            </div></section>
            @forelse($case->files->sortByDesc('created_at') as $file)
            <div style="display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-bottom:8px;background:#fff">
                <div style="flex:1;min-width:0">
                    <div style="font-weight:650;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $file->original_name }}</div>
                    <div style="color:var(--muted);font-size:12px">{{ ucfirst(str_replace('_', ' ', $file->type)) }} · {{ number_format($file->size / 1024, 1) }} KB · {{ $file->created_at->format('M d, Y H:i') }} · <span class="pill {{ $file->status === 'processed' ? 'green' : ($file->status === 'failed' ? 'red' : 'neutral') }}">{{ ucfirst($file->status) }}</span></div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0">
                    @if($file->status !== 'failed')
                        @if(in_array($file->mime_type, ['image/png','image/jpeg','image/jpg','image/webp','application/pdf']))
                        <a class="button-secondary" style="padding:4px 10px" target="_blank" href="{{ route('clinician.cases.files.preview', [$case->uuid, $file->uuid]) }}">View</a>
                        @endif
                        <a class="button-secondary" style="padding:4px 10px" href="{{ route('clinician.cases.files.download', [$case->uuid, $file->uuid]) }}">Download</a>
                    @endif
                    <form method="POST" action="{{ route('clinician.cases.files.destroy', [$case->uuid, $file->uuid]) }}" onsubmit="return confirm('Delete this file?')">@csrf @method('DELETE')
                        <button class="button-danger" style="padding:4px 10px">Delete</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="stub"><strong>No files</strong>No files are attached to this case yet.</div>
            @endforelse
        </div>

        {{-- Timeline --}}
        <div class="case-pane" data-pane="timeline" hidden>
            @php
                $eventLabel = function($event) {
                    $from = $event->payload['from'] ?? null; $to = $event->payload['to'] ?? null;
                    if ($event->event_type === 'clinician_reassigned') return ['Clinician reassigned', 'neutral'];
                    $map = [
                        'created→waiting'=>['Case submitted','neutral'],'waiting→assigned'=>['Case assigned','neutral'],
                        'created→assigned'=>['Case assigned','neutral'],'assigned→support'=>['Sent to support','yellow'],
                        'support→assigned'=>['Returned to clinician','neutral'],'assigned→approved'=>['Case approved','green'],
                        'approved→processing'=>['Sent to pharmacy','green'],'processing→completed'=>['Case completed','green'],
                        'assigned→cancelled'=>['Case cancelled','red'],'waiting→cancelled'=>['Case cancelled','red'],
                        'support→cancelled'=>['Case cancelled','red'],'approved→cancelled'=>['Case cancelled','red'],'created→cancelled'=>['Case cancelled','red'],
                    ];
                    $key = $from.'→'.$to;
                    if (isset($map[$key])) return $map[$key];
                    return [$to ? ucfirst($from).' -> '.ucfirst($to) : ucfirst(str_replace('_',' ',$event->event_type)), 'neutral'];
                };
                $actorLabel = fn($e) => match($e->actor_type) { 'admin'=>'Admin','clinician'=>'Clinician','partner'=>'Partner', default=>'System' };
            @endphp
            @forelse($case->events->sortByDesc('created_at') as $event)
                @php [$label, $tone] = $eventLabel($event); @endphp
                <div style="display:flex;gap:14px;padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid var(--line)">
                    <div style="color:var(--muted);font-size:12px;min-width:110px">{{ $event->created_at->format('M d, H:i') }}</div>
                    <div>
                        <span class="pill {{ $tone }}">{{ $label }}</span>
                        <div style="color:var(--muted);font-size:12px;margin-top:4px">{{ $actorLabel($event) }}</div>
                        @if($event->notes)<div style="font-size:13px;margin-top:4px">{{ $event->notes }}</div>@endif
                    </div>
                </div>
            @empty
            <p class="ai-honesty">No timeline events.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Escalate / Decline modals (vanilla .modal-back overlays) --}}
<div class="modal-back" id="supportModal" hidden>
    <div class="modal" style="width:min(520px,94vw);padding:0">
        <form method="POST" action="{{ route('clinician.cases.support', $case->uuid) }}">@csrf
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line)"><strong>Escalate to support</strong><button type="button" class="icon-btn" data-close-modal>&times;</button></div>
            <div style="padding:16px 18px">
                <p style="color:var(--muted);font-size:13px">Moves the case to Support and makes it visible to the partner so they can add information.</p>
                <div class="field"><label>Support note <span class="req">*</span></label><textarea name="support_note" class="note-area" rows="4" required placeholder="What is needed from the partner?"></textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--line)"><button type="button" class="button-secondary" data-close-modal>Cancel</button><button class="button-primary">Escalate</button></div>
        </form>
    </div>
</div>
<div class="modal-back" id="cancelModal" hidden>
    <div class="modal" style="width:min(520px,94vw);padding:0">
        <form method="POST" action="{{ route('clinician.cases.cancel', $case->uuid) }}">@csrf
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line)"><strong>Decline case</strong><button type="button" class="icon-btn" data-close-modal>&times;</button></div>
            <div style="padding:16px 18px"><div class="field"><label>Reason <span class="req">*</span></label><textarea name="reason" class="note-area" rows="3" required placeholder="Reason for declining."></textarea></div></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--line)"><button type="button" class="button-secondary" data-close-modal>Go back</button><button class="button-danger">Decline</button></div>
        </form>
    </div>
</div>

{{-- Review-and-approve modal host (same pop-up as the grid) --}}
<div class="modal-back" id="reviewOverlay" hidden>
    <div class="modal" style="width:min(1080px,94vw);height:88vh;padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--line)"><strong style="font-size:15px">Review and approve</strong><button type="button" class="icon-btn" id="reviewClose">&times;</button></div>
        <iframe id="reviewFrame" title="Review and approve" style="flex:1;width:100%;border:0"></iframe>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .case-grid { display:grid; grid-template-columns:340px minmax(0,1fr); gap:16px; align-items:start; }
    @media (max-width:1000px){ .case-grid { grid-template-columns:1fr; } }
    .case-tabs { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid var(--line); margin-bottom:16px; }
    .case-tab { border:0; border-bottom:2px solid transparent; background:none; color:var(--muted); font-weight:680; font-size:13px; padding:9px 12px; display:inline-flex; align-items:center; gap:6px; }
    .case-tab:hover { color:var(--ink); }
    .case-tab.active { color:var(--accent-ink); border-bottom-color:var(--accent); }
    .case-pane[hidden] { display:none; }
    .modal-back[hidden] { display:none; }
    .chat .chat-scroll { border-radius:0; }
    #note-fields-general[hidden], #note-fields-soap[hidden], #note-fields-progress[hidden] { display:none; }
</style>
<script>
    // Note type switcher — shows the matching field group and toggles required.
    (function () {
        var sel = document.getElementById('noteType');
        if (!sel) return;
        var groups = { general: 'note-fields-general', soap: 'note-fields-soap', progress: 'note-fields-progress' };
        function switchType(type) {
            Object.keys(groups).forEach(function (k) {
                var el = document.getElementById(groups[k]);
                if (!el) return;
                var active = k === type;
                el.hidden = !active;
                el.querySelectorAll('textarea').forEach(function (t) { t.required = active; });
            });
        }
        sel.addEventListener('change', function () { switchType(this.value); });
        switchType(sel.value);
    })();

    // Vanilla tabs. Fires a custom event so the chat can start/stop polling.
    (function () {
        var tabs  = document.querySelectorAll('.case-tab');
        var panes = document.querySelectorAll('.case-pane');
        function show(name) {
            tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-tab') === name); });
            panes.forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== name; });
            document.dispatchEvent(new CustomEvent('case-tab', { detail: name }));
        }
        tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.getAttribute('data-tab')); }); });
        // Deep link: #tab-messages etc. opens that tab.
        var h = (window.location.hash || '').replace('#tab-', '');
        if (h) { var m = document.querySelector('.case-tab[data-tab="' + h + '"]'); if (m) show(h); }
    })();

    // Vanilla modals.
    (function () {
        function open(id) { var m = document.getElementById(id); if (m) m.removeAttribute('hidden'); }
        function close(m) { if (m) m.setAttribute('hidden', ''); }
        document.addEventListener('click', function (e) {
            var o = e.target.closest('[data-open-modal]'); if (o) { open(o.getAttribute('data-open-modal')); return; }
            var c = e.target.closest('[data-close-modal]'); if (c) { close(c.closest('.modal-back')); return; }
            if (e.target.classList && e.target.classList.contains('modal-back')) close(e.target);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') document.querySelectorAll('.modal-back:not([hidden])').forEach(close);
        });
    })();

    // Review-and-approve modal (iframe of the prescribe form, bare).
    (function () {
        var overlay = document.getElementById('reviewOverlay');
        var frame   = document.getElementById('reviewFrame');
        if (!overlay || !frame) return;
        function open(url) { frame.src = url; overlay.removeAttribute('hidden'); document.body.style.overflow = 'hidden'; }
        function close(reload) { overlay.setAttribute('hidden', ''); frame.src = 'about:blank'; document.body.style.overflow = ''; if (reload) window.location.reload(); }
        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-review-url]'); if (!link) return;
            e.preventDefault(); open(link.getAttribute('data-review-url'));
        });
        window.addEventListener('message', function (e) { if (e.data === 'close-review') close(false); });
        frame.addEventListener('load', function () {
            var href; try { href = frame.contentWindow.location.href; } catch (err) { return; }
            if (!href || href === 'about:blank') return;
            if (href.indexOf('modal=1') === -1 && href.indexOf('/clinician/cases/') !== -1) close(true);
        });
        document.getElementById('reviewClose').addEventListener('click', function () { close(false); });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false); });
    })();
</script>

<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/2.2.4/echo.iife.min.js"></script>
<script>
(function () {
    var thread = document.getElementById('clinThread');
    if (!thread) return;
    var caseId = {{ $case->id }};

    function scrollToBottom() { thread.scrollTop = thread.scrollHeight; }
    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
    function buildBubble(msg) {
        var side = msg.sender_type === 'clinician' ? 'me' : 'them';
        return '<div class="bubble-row ' + side + '"><div class="bubble ' + side + '">' + esc(msg.body) + '</div></div>';
    }
    function appendMessage(msg) {
        var empty = document.getElementById('clinThreadEmpty'); if (empty) empty.remove();
        thread.insertAdjacentHTML('beforeend', buildBubble(msg));
        scrollToBottom();
    }

    window.clinSendMessage = function () {
        var input = document.getElementById('clinMsgInput'); if (!input) return;
        var body = input.value.trim(); if (!body) return;
        input.value = '';
        appendMessage({ body: body, sender_type: 'clinician' });
        fetch("{{ route('clinician.cases.messages.store', $case->uuid) }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ body: body }),
        }).catch(function (err) { console.error('[Chat] Send failed:', err); });
    };

    var EchoConstructor = null;
    if (typeof Echo === 'object' && typeof Echo.default === 'function') EchoConstructor = Echo.default;
    else if (typeof Echo === 'function') EchoConstructor = Echo;

    if (EchoConstructor) {
        window.Pusher = Pusher;
        window.Echo = new EchoConstructor({
            broadcaster: 'pusher',
            key: "{{ config('reverb.apps.apps.0.key') }}",
            wsHost: "{{ config('reverb.apps.apps.0.options.host') }}",
            wsPort: {{ config('reverb.apps.apps.0.options.port') }},
            wssPort: {{ config('reverb.apps.apps.0.options.port') }},
            disableStats: true,
            forceTLS: {{ config('reverb.apps.apps.0.options.useTLS') }},
            cluster: 'mt1',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: "/broadcasting/auth",
            auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest' } }
        });
        window.Echo.private('case.' + caseId).listen('.CaseMessageSent', function (e) {
            if (e.sender_type === 'clinician') return;
            appendMessage({ body: e.body, sender_type: e.sender_type });
        });
    } else {
        fallbackPolling();
    }

    function fallbackPolling() {
        var pollUrl = thread.dataset.pollUrl;
        var lastId  = parseInt(thread.dataset.lastId, 10) || 0;
        var interval = null;
        function isMessagesTab() { var p = document.querySelector('[data-pane="messages"]'); return p && !p.hidden; }
        function poll() {
            if (!isMessagesTab() || document.visibilityState !== 'visible') return;
            fetch(pollUrl + '?after=' + lastId)
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.messages || !data.messages.length) return;
                    data.messages.forEach(function (msg) { appendMessage(msg); lastId = msg.id; });
                    thread.dataset.lastId = lastId;
                }).catch(function () {});
        }
        function start() { if (!interval) { poll(); interval = setInterval(poll, 5000); } }
        function stop() { if (interval) { clearInterval(interval); interval = null; } }
        document.addEventListener('case-tab', function (e) { if (e.detail === 'messages') { scrollToBottom(); start(); } else stop(); });
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') stop(); else if (isMessagesTab()) start(); });
        if (isMessagesTab()) start();
    }

    // Scroll on first open of the messages tab.
    document.addEventListener('case-tab', function (e) { if (e.detail === 'messages') scrollToBottom(); });
    scrollToBottom();
})();
</script>
@endsection
