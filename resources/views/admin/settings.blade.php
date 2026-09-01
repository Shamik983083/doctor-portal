@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')

<div class="row g-4">
    <div class="col-lg-7">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:36px;height:36px;background:#4361ee1a;">
                    <i class="bi bi-clock-history" style="color:#4361ee;font-size:1rem;"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-semibold">Case SLA Targets</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">
                        House deadlines for case pickup, review, and end-to-end completion.
                        Not to be confused with <a href="{{ route('admin.routing.sla') }}" class="text-muted">Provider Pull SLA</a>,
                        which gates whether a provider may pull more cases from the pool.
                    </p>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf

                    @foreach($slaSettings as $setting)
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1">
                            {{ $setting->label }}
                        </label>
                        @if($setting->description)
                            <p class="text-muted mb-2" style="font-size:.78rem;">{{ $setting->description }}</p>
                        @endif
                        <div class="input-group" style="max-width:220px;">
                            <input type="number"
                                   name="{{ $setting->key }}"
                                   value="{{ old($setting->key, $setting->value) }}"
                                   min="1"
                                   max="{{ $setting->key === 'sla_total_hours' ? 720 : 168 }}"
                                   class="form-control @error($setting->key) is-invalid @enderror"
                                   required>
                            <span class="input-group-text text-muted">hours</span>
                            @error($setting->key)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @if($setting->key === 'sla_review_hours')
                            <p class="text-muted mt-1" style="font-size:.72rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                This is displayed on the clinician dashboard as the case review deadline.
                            </p>
                        @endif
                    </div>
                    @endforeach

                    <hr class="my-4">

                    <hr class="my-4">

                    {{-- C11: Medical necessity preset --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1">Medical Necessity Default Text</label>
                        <p class="text-muted mb-2" style="font-size:.78rem;">Pre-fills the Medical Necessity field on every prescription form. Clinicians can edit or clear it before submitting.</p>
                        <textarea name="medical_necessity_preset" class="form-control @error('medical_necessity_preset') is-invalid @enderror"
                                  rows="3" maxlength="2000"
                                  placeholder="e.g. Patient meets clinical criteria for the requested medication based on the submitted intake and triage findings.">{{ old('medical_necessity_preset', $medicalNecessityPreset) }}</textarea>
                        @error('medical_necessity_preset')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-4">

                    {{-- B11: Message routing mode --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1">Message Routing Mode</label>
                        <p class="text-muted mb-2" style="font-size:.78rem;"><strong>Direct</strong> — patient messages go to the assigned clinician's inbox (current behaviour). <strong>Pool</strong> — messages surface in a shared inbox (stub; no behaviour change yet).</p>
                        <div class="d-flex gap-3">
                            @foreach(['direct' => 'Direct (default)', 'pool' => 'Pool (stub)'] as $val => $lbl)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="message_routing_mode"
                                           id="mrm_{{ $val }}" value="{{ $val }}"
                                           {{ $messageRoutingMode === $val ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mrm_{{ $val }}">{{ $lbl }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-floppy me-2"></i>Save Settings
                        </button>
                        <span class="text-muted" style="font-size:.75rem;">
                            Changes take effect immediately across all clinician dashboards.
                        </span>
                    </div>
                </form>
            </div>
        </div>

    </div>

    {{-- Info panel --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold">How Case SLA Targets Work</h6>
            </div>
            <div class="card-body p-4">

                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                         style="width:32px;height:32px;background:#4361ee1a;color:#4361ee;font-size:.8rem;font-weight:700;">1</div>
                    <div>
                        <p class="fw-semibold mb-1 small">Queue Pickup Deadline</p>
                        <p class="text-muted mb-0" style="font-size:.78rem;">
                            Clock starts when a case is submitted. Tracks how quickly the waiting queue is cleared by clinicians claiming cases.
                        </p>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                         style="width:32px;height:32px;background:#ffc1071a;color:#e6a800;font-size:.8rem;font-weight:700;">2</div>
                    <div>
                        <p class="fw-semibold mb-1 small">Review & Approval Deadline</p>
                        <p class="text-muted mb-0" style="font-size:.78rem;">
                            Clock starts when a case is assigned to a clinician. This is the primary SLA shown on the clinician dashboard.
                        </p>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                         style="width:32px;height:32px;background:#2dc6531a;color:#2dc653;font-size:.8rem;font-weight:700;">3</div>
                    <div>
                        <p class="fw-semibold mb-1 small">End-to-End Deadline</p>
                        <p class="text-muted mb-0" style="font-size:.78rem;">
                            Clock starts at case creation and stops at completion. Tracks the total turnaround time seen by the patient and partner.
                        </p>
                    </div>
                </div>

                <hr>

                <div class="d-flex align-items-start gap-2 mt-3">
                    <span class="badge rounded-pill" style="background:#2dc6531a;color:#2dc653;font-size:.72rem;white-space:nowrap;padding:4px 8px;">On Track</span>
                    <span class="text-muted" style="font-size:.78rem;">Less than 70% of deadline elapsed</span>
                </div>
                <div class="d-flex align-items-start gap-2 mt-2">
                    <span class="badge rounded-pill" style="background:#ffc1071a;color:#e6a800;font-size:.72rem;white-space:nowrap;padding:4px 8px;">At Risk</span>
                    <span class="text-muted" style="font-size:.78rem;">Between 70% and 100% of deadline elapsed</span>
                </div>
                <div class="d-flex align-items-start gap-2 mt-2">
                    <span class="badge rounded-pill" style="background:#dc35451a;color:#dc3545;font-size:.72rem;white-space:nowrap;padding:4px 8px;">Breached</span>
                    <span class="text-muted" style="font-size:.78rem;">Past the deadline — requires immediate attention</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
