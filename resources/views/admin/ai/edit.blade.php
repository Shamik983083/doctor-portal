@extends('layouts.admin')

@section('title', 'AI Instructions – ' . $label)
@section('page-title', 'AI Instructions')

@section('content')

{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb" style="font-size:.8rem;">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.ai.index') }}" class="text-decoration-none">AI Instruction Sets</a>
        </li>
        <li class="breadcrumb-item active">{{ $label }}</li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-12 col-xl-8">

        {{-- ── Instruction Set ─────────────────────────────────── --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:36px;height:36px;background:#4361ee1a;">
                        <i class="bi bi-file-text" style="color:#4361ee;font-size:1rem;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-semibold">{{ $label }}</h6>
                        <p class="text-muted mb-0" style="font-size:.72rem;">
                            <code>{{ $context }}</code>
                            @if($set)
                                &middot; version {{ $set->version }}
                                @if($set->updatedBy)
                                    &middot; last saved by {{ $set->updatedBy->name }}
                                @endif
                            @endif
                        </p>
                    </div>
                </div>
                @if($set)
                    <span class="badge rounded-pill"
                          style="background:#d1fae5;color:#065f46;font-size:.72rem;font-weight:600;">
                        Active &middot; v{{ $set->version }}
                    </span>
                @else
                    <span class="badge rounded-pill"
                          style="background:#fee2e2;color:#991b1b;font-size:.72rem;font-weight:600;">
                        Not configured
                    </span>
                @endif
            </div>

            <div class="card-body p-4">

                @if(! $set)
                    <div class="alert alert-info d-flex gap-2 mb-4" style="font-size:.82rem;">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                        <div>
                            No active instruction set exists for this context yet.
                            Fill in the form below and save to create one.
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.ai.update', $context) }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1" for="ai-name">Name</label>
                        <p class="text-muted mb-2" style="font-size:.78rem;">
                            A short label for this instruction set (for your reference only — not sent to the AI).
                        </p>
                        <input type="text"
                               id="ai-name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $set?->name ?? '') }}"
                               maxlength="120"
                               placeholder="e.g. Clinical note v3 — concise tone"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1" for="ai-instructions">Instructions</label>
                        <p class="text-muted mb-2" style="font-size:.78rem;">
                            The system instructions sent to the AI for this context. Be specific about format,
                            length, and clinical standards. These apply to every draft request in this context.
                        </p>
                        <textarea id="ai-instructions"
                                  name="instructions"
                                  rows="14"
                                  class="form-control font-monospace @error('instructions') is-invalid @enderror"
                                  style="font-size:.82rem;resize:vertical;"
                                  maxlength="8000"
                                  placeholder="You are a licensed medical practitioner reviewing a patient case..."
                                  required>{{ old('instructions', $set?->instructions ?? '') }}</textarea>
                        @error('instructions')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="d-flex justify-content-end mt-1">
                            <span class="text-muted" id="ai-instructions-count" style="font-size:.72rem;">
                                {{ strlen(old('instructions', $set?->instructions ?? '')) }} / 8000
                            </span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-1" for="ai-tone">Tone guidance <span class="text-muted fw-normal">(optional)</span></label>
                        <p class="text-muted mb-2" style="font-size:.78rem;">
                            A short phrase describing the tone: e.g. <em>concise and clinical</em>,
                            <em>empathetic but professional</em>. Appended after the instructions.
                        </p>
                        <input type="text"
                               id="ai-tone"
                               name="tone"
                               class="form-control @error('tone') is-invalid @enderror"
                               value="{{ old('tone', $set?->tone ?? '') }}"
                               maxlength="200"
                               placeholder="concise and clinical">
                        @error('tone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-floppy me-1"></i>
                            {{ $set ? 'Save (bumps to v' . ($set->version + 1) . ')' : 'Create instruction set' }}
                        </button>
                        <a href="{{ route('admin.ai.index') }}" class="btn btn-link text-muted text-decoration-none">
                            Back to list
                        </a>
                    </div>

                </form>
            </div>
        </div>

        {{-- ── Worked Examples ─────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:36px;height:36px;background:#0ea5e91a;">
                        <i class="bi bi-list-check" style="color:#0ea5e9;font-size:1rem;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-semibold">Worked Examples</h6>
                        <p class="text-muted mb-0" style="font-size:.72rem;">
                            Concrete situation / good output / bad output triples that guide the model's style.
                        </p>
                    </div>
                </div>
                @if($set)
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill" style="font-size:.72rem;">
                        {{ $set->examples->count() }} {{ Str::plural('example', $set->examples->count()) }}
                    </span>
                @endif
            </div>
            <div class="card-body p-4">

                @if(! $set)
                    <p class="text-muted mb-0" style="font-size:.85rem;">
                        <i class="bi bi-lock me-1"></i>
                        Save the instruction set above before adding worked examples.
                    </p>

                @else

                    @if($set->examples->isEmpty())
                        <p class="text-muted mb-4" style="font-size:.85rem;">
                            No examples yet. Add the first one below.
                        </p>
                    @else
                        @foreach($set->examples as $i => $example)
                            <div class="border rounded-2 p-3 mb-3" style="font-size:.83rem;">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <span class="fw-semibold text-muted" style="font-size:.72rem; letter-spacing:.04em;">
                                        EXAMPLE {{ $i + 1 }}
                                    </span>
                                    <form method="POST"
                                          action="{{ route('admin.ai.examples.destroy', [$context, $example]) }}"
                                          onsubmit="return confirm('Remove this example?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-link btn-sm text-danger p-0 text-decoration-none"
                                                style="font-size:.75rem;">
                                            <i class="bi bi-trash3"></i> Remove
                                        </button>
                                    </form>
                                </div>

                                <div class="mb-2">
                                    <div class="text-muted mb-1" style="font-size:.72rem;font-weight:600;letter-spacing:.04em;">SITUATION</div>
                                    <div style="white-space:pre-wrap;">{{ $example->situation }}</div>
                                </div>

                                <div class="mb-2">
                                    <div class="mb-1" style="font-size:.72rem;font-weight:600;letter-spacing:.04em;color:#059669;">GOOD OUTPUT</div>
                                    <div style="white-space:pre-wrap;background:#f0fdf4;border-radius:6px;padding:8px 10px;border:1px solid #bbf7d0;">{{ $example->good_output }}</div>
                                </div>

                                @if($example->bad_output)
                                    <div>
                                        <div class="mb-1" style="font-size:.72rem;font-weight:600;letter-spacing:.04em;color:#dc2626;">AVOID</div>
                                        <div style="white-space:pre-wrap;background:#fef2f2;border-radius:6px;padding:8px 10px;border:1px solid #fecaca;">{{ $example->bad_output }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    {{-- Add example form --}}
                    <div class="border rounded-2 p-3 mt-3" style="background:#f8fafc;">
                        <div class="fw-semibold mb-3" style="font-size:.82rem;">
                            <i class="bi bi-plus-circle me-1 text-primary"></i> Add Example
                        </div>

                        <form method="POST" action="{{ route('admin.ai.examples.store', $context) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">
                                    Situation <span class="text-danger">*</span>
                                </label>
                                <textarea name="situation"
                                          rows="3"
                                          class="form-control @error('situation') is-invalid @enderror"
                                          style="font-size:.82rem;resize:vertical;"
                                          maxlength="2000"
                                          placeholder="Describe the patient scenario or context the AI should respond to…"
                                          required>{{ old('situation') }}</textarea>
                                @error('situation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1" style="font-size:.8rem; color:#059669;">
                                    Good output <span class="text-danger">*</span>
                                </label>
                                <textarea name="good_output"
                                          rows="4"
                                          class="form-control @error('good_output') is-invalid @enderror"
                                          style="font-size:.82rem;resize:vertical;"
                                          maxlength="4000"
                                          placeholder="The ideal draft the AI should produce for this situation…"
                                          required>{{ old('good_output') }}</textarea>
                                @error('good_output')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;color:#dc2626;">
                                    Avoid (bad output) <span class="text-muted fw-normal" style="font-size:.75rem;">optional</span>
                                </label>
                                <textarea name="bad_output"
                                          rows="3"
                                          class="form-control @error('bad_output') is-invalid @enderror"
                                          style="font-size:.82rem;resize:vertical;"
                                          maxlength="4000"
                                          placeholder="An example of what the AI should NOT produce…">{{ old('bad_output') }}</textarea>
                                @error('bad_output')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus me-1"></i> Add Example
                            </button>
                        </form>
                    </div>

                @endif
            </div>
        </div>

    </div>

    {{-- Right: context help --}}
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm" style="font-size:.82rem;">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold" style="font-size:.85rem;">
                    <i class="bi bi-question-circle me-1 text-muted"></i> About this context
                </h6>
            </div>
            <div class="card-body p-4">
                @switch($context)
                    @case('clinical_note')
                        <p>Guides the AI when drafting a clinical approval note. The note is appended to the case approval and may be included in patient-facing communications.</p>
                        <p class="text-muted">Tip: instruct the model to use ICD-10 terminology, avoid speculation, and stay within the information available in the case record.</p>
                        @break
                    @case('patient_message')
                        <p>Guides the AI when drafting a direct message to a patient. These messages appear inside the patient portal inbox.</p>
                        <p class="text-muted">Tip: instruct the model to use plain language, avoid medical jargon, and stay HIPAA-appropriate.</p>
                        @break
                    @case('storefront_message')
                        <p>Guides the AI when drafting a message addressed to a storefront partner rather than a patient directly.</p>
                        <p class="text-muted">Tip: tone can be more formal; include expectations around format and required case identifiers.</p>
                        @break
                    @case('case_summary')
                        <p>Guides the AI when generating a short summary of a case for the quick-review panel. The clinician sees this before pulling the full case.</p>
                        <p class="text-muted">Tip: instruct the model to surface key risk signals (disqualified answers, prior rejections) at the top.</p>
                        @break
                    @case('rejection_reason')
                        <p>Guides the AI when drafting a reason for declining a case. The reason is shown to the partner and may reach the patient.</p>
                        <p class="text-muted">Tip: instruct the model to be factual and compassionate, and to avoid speculative clinical language.</p>
                        @break
                @endswitch

                <hr class="my-3">

                <p class="text-muted mb-1" style="font-size:.78rem;font-weight:600;letter-spacing:.04em;">TIPS</p>
                <ul class="text-muted ps-3 mb-0" style="font-size:.78rem;">
                    <li class="mb-1">Each save bumps the version number — you can trace which instructions produced a specific draft.</li>
                    <li class="mb-1">Worked examples have more influence than instruction prose alone.</li>
                    <li>The AI adapter must be enabled in <code>.env</code> (<code>AI_ASSIST_ENABLED=true</code>) before drafts are generated.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const ta    = document.getElementById('ai-instructions');
    const count = document.getElementById('ai-instructions-count');
    if (!ta || !count) return;

    ta.addEventListener('input', function () {
        count.textContent = this.value.length + ' / 8000';
    });
})();
</script>
@endsection
