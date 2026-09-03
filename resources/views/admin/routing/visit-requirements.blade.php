@extends('layouts.admin')

@section('title', 'State Visit Requirements')

@section('content')
{{--
    Which states require a live video visit (Devin msg 2313 Q4: "we need to adjust
    as super admin as laws change frequently. the Sync is determined by states").

    This decides the case side of the visit-type axis. The doctor side is on each
    clinician's edit screen: whether they take synchronous visits, and the booking
    link a patient uses to reach them.
--}}

{{-- ── Flash messages ─────────────────────────────────────────────────── --}}
@foreach(['success','warning','danger','error'] as $type)
@if(session($type))
<div class="sv-flash sv-flash--{{ $type === 'error' ? 'danger' : $type }} mb-4" role="alert">
    <i class="bi {{ $type === 'success' ? 'bi-check-circle-fill' : ($type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill') }}"></i>
    <span>{{ session($type) }}</span>
    <button type="button" onclick="this.parentElement.remove()" class="sv-flash-close">&times;</button>
</div>
@endif
@endforeach

{{-- ── Page header ─────────────────────────────────────────────────────── --}}
<div class="sv-page-hd mb-4">
    <div>
        <h5 class="sv-page-title">State Visit Requirements</h5>
        <p class="sv-page-desc">
            Where the law requires a live video visit. A case in a state with a matching rule can only
            be routed to a doctor who takes synchronous visits and has a booking link.
        </p>
    </div>
    <a href="{{ route('admin.routing.index') }}" class="sv-back-link">
        <i class="bi bi-arrow-left me-1"></i>Case Routing
    </a>
</div>

{{-- ── Specificity info callout ────────────────────────────────────────── --}}
<div class="sv-callout mb-4">
    <div class="sv-callout-icon"><i class="bi bi-trophy"></i></div>
    <div>
        <p class="sv-callout-title">Most specific rule wins</p>
        <p class="sv-callout-body">
            A rule for one product beats a rule for its category, which beats a blanket rule for the state.
            With no matching rule, the video states already set on the product itself still apply —
            so nothing configured today stops working.
        </p>
    </div>
</div>

{{-- ── Add a rule form ─────────────────────────────────────────────────── --}}
<div class="sv-card mb-4">
    <div class="sv-section-hd">
        <div class="sv-icon" style="--c:#4361ee1a;--fc:#4361ee;"><i class="bi bi-plus-circle"></i></div>
        <div>
            <h6 class="sv-section-title">Add a Rule</h6>
            <p class="sv-section-desc">Define when a state requires a synchronous video visit for a product or category.</p>
        </div>
    </div>
    <div class="sv-section-body">
        <form method="POST" action="{{ route('admin.routing.visit-requirements.store') }}" id="addRuleForm">
            @csrf

            {{-- Row 1: State + Scope --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-4 col-lg-2">
                    <label class="sv-label" for="state">State</label>
                    <input type="text" id="state" name="state"
                           class="form-control sv-input sv-state-input @error('state') is-invalid @enderror"
                           maxlength="2" required
                           placeholder="TX"
                           value="{{ old('state') }}"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-8 col-lg-4">
                    <label class="sv-label" for="scope_type">Applies to</label>
                    <select id="scope_type" name="scope_type"
                            class="form-select sv-input @error('scope_type') is-invalid @enderror"
                            required onchange="updateScopeVisibility(this.value)">
                        @foreach($scopes as $value => $label)
                            <option value="{{ $value }}" {{ old('scope_type', 'ALL') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('scope_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6 col-lg-3" id="categoryWrap">
                    <label class="sv-label" for="offering_category_id">
                        Category <span class="sv-label-opt">if scoped</span>
                    </label>
                    <select id="offering_category_id" name="offering_category_id"
                            class="form-select sv-input @error('offering_category_id') is-invalid @enderror">
                        <option value="">— any category —</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}"
                                {{ (string) old('offering_category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('offering_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6 col-lg-3" id="productWrap">
                    <label class="sv-label" for="offering_id">
                        Product <span class="sv-label-opt">if scoped</span>
                    </label>
                    <select id="offering_id" name="offering_id"
                            class="form-select sv-input @error('offering_id') is-invalid @enderror">
                        <option value="">— any product —</option>
                        @foreach($offerings as $offering)
                            <option value="{{ $offering->id }}"
                                {{ (string) old('offering_id') === (string) $offering->id ? 'selected' : '' }}>
                                {{ $offering->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('offering_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Row 2: Dates + Note --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <label class="sv-label" for="effective_from">In force from</label>
                    <input type="date" id="effective_from" name="effective_from"
                           class="form-control sv-input @error('effective_from') is-invalid @enderror"
                           value="{{ old('effective_from') }}">
                    @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="sv-label" for="effective_to">
                        Until <span class="sv-label-opt">blank = ongoing</span>
                    </label>
                    <input type="date" id="effective_to" name="effective_to"
                           class="form-control sv-input @error('effective_to') is-invalid @enderror"
                           value="{{ old('effective_to') }}">
                    @error('effective_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="sv-label" for="note">Note</label>
                    <input type="text" id="note" name="note"
                           class="form-control sv-input @error('note') is-invalid @enderror"
                           maxlength="500"
                           placeholder="Which statute or ruling this reflects"
                           value="{{ old('note') }}">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Video requirement toggle --}}
            <div class="sv-toggle-block mb-4">
                <label class="sv-toggle-row" for="requiresSync">
                    <input type="hidden" name="requires_synchronous" value="0">
                    <input type="checkbox" id="requiresSync" name="requires_synchronous" value="1" checked>
                    <span class="sv-toggle-indicator"></span>
                    <div>
                        <span class="sv-toggle-label">Requires a synchronous video visit</span>
                        <p class="sv-toggle-hint">
                            Untick to record that this state explicitly does <em>not</em> require one —
                            this is how you turn off an older product-level video flag.
                        </p>
                    </div>
                </label>
            </div>

            <button type="submit" class="btn btn-primary sv-submit-btn">
                <i class="bi bi-plus-circle me-2"></i>Save Rule
            </button>

        </form>
    </div>
</div>

{{-- ── Rules table ─────────────────────────────────────────────────────── --}}
<div class="sv-card">
    <div class="sv-section-hd">
        <div class="sv-icon" style="--c:#2dc6531a;--fc:#2dc653;"><i class="bi bi-list-check"></i></div>
        <div>
            <h6 class="sv-section-title">
                Active Rules
                <span class="sv-count-chip">{{ $rules->count() }}</span>
            </h6>
            <p class="sv-section-desc">
                Ending a rule that has been in force keeps it on record with an end date —
                cases routed under it remain explainable.
            </p>
        </div>
    </div>

    @if($rules->isEmpty())
        <div class="sv-empty">
            <i class="bi bi-map"></i>
            <p>No rules configured. Every case defaults to asynchronous unless the product carries its own video states.</p>
        </div>
    @else
        <div class="sv-section-body p-0">
            <div class="table-responsive">
                <table class="sv-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">State</th>
                            <th>Applies to</th>
                            <th style="width:110px;">Video req.</th>
                            <th>In force</th>
                            <th>Note</th>
                            <th style="width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rules as $rule)
                        @php
                            $ended = $rule->effective_to && $rule->effective_to->isPast();
                            $active = !$ended && (!$rule->effective_from || $rule->effective_from->isPast());
                        @endphp
                        <tr class="{{ $ended ? 'sv-row-ended' : '' }}">
                            <td>
                                <span class="sv-state-chip {{ $ended ? 'sv-state-chip--dim' : ($rule->requires_synchronous ? 'sv-state-chip--video' : 'sv-state-chip--async') }}">
                                    {{ $rule->state }}
                                </span>
                            </td>
                            <td>
                                <span class="sv-scope-label">{{ $rule->scopeLabel() }}</span>
                                @if($rule->category)
                                    <span class="sv-scope-sub">{{ $rule->category->name }}</span>
                                @endif
                                @if($rule->offering)
                                    <span class="sv-scope-sub">{{ $rule->offering->name }}</span>
                                @endif
                            </td>
                            <td>
                                @if($rule->requires_synchronous)
                                    <span class="sv-pill sv-pill--video">Video</span>
                                @else
                                    <span class="sv-pill sv-pill--async">Async</span>
                                @endif
                            </td>
                            <td>
                                <span class="sv-date-range">
                                    {{ $rule->effective_from?->format('M j, Y') ?? 'Always' }}
                                    <i class="bi bi-arrow-right sv-date-arrow"></i>
                                    {{ $rule->effective_to?->format('M j, Y') ?? 'Ongoing' }}
                                </span>
                                @if($ended)
                                    <span class="sv-pill sv-pill--ended">Ended</span>
                                @elseif($active)
                                    <span class="sv-pill sv-pill--active">Active</span>
                                @else
                                    <span class="sv-pill sv-pill--future">Future</span>
                                @endif
                            </td>
                            <td class="sv-note-cell">{{ $rule->note ?: '—' }}</td>
                            <td class="text-end">
                                @unless($ended)
                                    <form method="POST" action="{{ route('admin.routing.visit-requirements.destroy', $rule->id) }}"
                                          onsubmit="return confirm('End the {{ $rule->state }} rule?\n\nIt will stay on record with today as the end date.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="sv-end-btn">
                                            <i class="bi bi-x-circle me-1"></i>End
                                        </button>
                                    </form>
                                @else
                                    <span class="sv-ended-tag">Ended</span>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@endsection

@section('scripts')
<script>
function updateScopeVisibility(val) {
    var catWrap  = document.getElementById('categoryWrap');
    var prodWrap = document.getElementById('productWrap');
    if (val === 'ALL') {
        catWrap.style.opacity  = '0.4';
        prodWrap.style.opacity = '0.4';
        catWrap.querySelector('select').disabled  = true;
        prodWrap.querySelector('select').disabled = true;
    } else if (val === 'CATEGORY') {
        catWrap.style.opacity  = '1';
        prodWrap.style.opacity = '0.4';
        catWrap.querySelector('select').disabled  = false;
        prodWrap.querySelector('select').disabled = true;
    } else {
        catWrap.style.opacity  = '1';
        prodWrap.style.opacity = '1';
        catWrap.querySelector('select').disabled  = false;
        prodWrap.querySelector('select').disabled = false;
    }
}
// Initialise on load
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('scope_type');
    if (sel) updateScopeVisibility(sel.value);
});
</script>
<style>
/* ─── Flash ──────────────────────────────────────────────────────────────── */
.sv-flash {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; border-radius:10px; font-size:.85rem; border-left:4px solid;
}
.sv-flash--success { background:#f0fdf4; border-color:#2dc653; color:#1a6b31; }
.sv-flash--warning { background:#fffbeb; border-color:#e6a800; color:#7a5800; }
.sv-flash--danger  { background:#fef2f2; border-color:#dc3545; color:#891c28; }
.sv-flash-close { margin-left:auto; background:none; border:none; font-size:1.2rem; cursor:pointer; color:inherit; opacity:.6; padding:0 4px; }
.sv-flash-close:hover { opacity:1; }

/* ─── Page header ────────────────────────────────────────────────────────── */
.sv-page-hd    { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.sv-page-title { font-size:1.1rem; font-weight:700; color:#1a1d23; margin:0 0 4px; }
.sv-page-desc  { font-size:.8rem; color:#6c757d; margin:0; max-width:600px; line-height:1.6; }
.sv-back-link  {
    display:inline-flex; align-items:center; gap:4px;
    font-size:.8rem; font-weight:600; color:#6c757d;
    text-decoration:none; padding:6px 12px;
    border:1.5px solid #e0e3e8; border-radius:8px;
    transition:border-color .15s, color .15s;
    white-space:nowrap; flex-shrink:0;
}
.sv-back-link:hover { border-color:#4361ee; color:#4361ee; }

/* ─── Info callout ───────────────────────────────────────────────────────── */
.sv-callout {
    display:flex; align-items:flex-start; gap:14px;
    background:#fff8e1; border:1.5px solid #ffc1071a;
    border-left:4px solid #e6a800;
    border-radius:12px; padding:16px 18px;
}
.sv-callout-icon {
    width:36px; height:36px; border-radius:8px;
    background:#ffc1071a; color:#e6a800;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0;
}
.sv-callout-title { font-size:.85rem; font-weight:700; color:#7a5800; margin:0 0 3px; }
.sv-callout-body  { font-size:.78rem; color:#7a5800; margin:0; line-height:1.6; opacity:.9; }

/* ─── Card / section shell ───────────────────────────────────────────────── */
.sv-card {
    background:#fff; border-radius:14px;
    box-shadow:0 1px 2px rgba(0,0,0,.05), 0 4px 16px rgba(0,0,0,.05);
    overflow:hidden;
}
.sv-section-hd {
    display:flex; align-items:flex-start; gap:14px;
    padding:20px 24px; background:#fafbfc;
    border-bottom:1px solid #f0f2f5;
}
.sv-icon {
    width:40px; height:40px; border-radius:10px;
    background:var(--c); color:var(--fc);
    display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; flex-shrink:0;
}
.sv-section-title {
    font-size:.9rem; font-weight:700; color:#1a1d23; margin:0 0 3px;
    display:flex; align-items:center; gap:8px;
}
.sv-section-desc { font-size:.78rem; color:#6c757d; margin:0; line-height:1.55; }
.sv-section-body { padding:24px; }
.sv-count-chip {
    display:inline-flex; align-items:center; justify-content:center;
    background:#4361ee12; color:#4361ee;
    font-size:.68rem; font-weight:700; border-radius:20px;
    padding:1px 8px; letter-spacing:.02em;
}

/* ─── Form elements ──────────────────────────────────────────────────────── */
.sv-label {
    display:block; font-size:.78rem; font-weight:650; color:#2c3040; margin-bottom:4px;
}
.sv-label-opt { font-size:.68rem; font-weight:400; color:#adb5bd; }
.sv-input {
    border-radius:8px !important; border-color:#e0e3e8 !important;
    font-size:.84rem !important;
    transition:border-color .15s, box-shadow .15s;
}
.sv-input:focus {
    border-color:#4361ee !important;
    box-shadow:0 0 0 3px #4361ee18 !important;
}
.sv-state-input {
    text-transform:uppercase; text-align:center;
    font-size:1.1rem !important; font-weight:700 !important;
    letter-spacing:.08em;
}

/* ─── Toggle block ───────────────────────────────────────────────────────── */
.sv-toggle-block {
    background:#f8f9fb; border:1.5px solid #eef0f4;
    border-radius:10px; padding:16px 18px;
}
.sv-toggle-row {
    display:flex; align-items:flex-start; gap:12px; cursor:pointer; margin:0;
}
.sv-toggle-row input[type="checkbox"] {
    width:17px; height:17px; margin-top:2px; cursor:pointer; flex-shrink:0;
    accent-color:#4361ee;
}
.sv-toggle-label { font-size:.85rem; font-weight:650; color:#1a1d23; display:block; }
.sv-toggle-hint  { font-size:.74rem; color:#6c757d; margin:4px 0 0; line-height:1.5; }

.sv-submit-btn { font-size:.875rem; font-weight:600; padding:8px 22px; border-radius:8px; }

/* ─── Empty state ────────────────────────────────────────────────────────── */
.sv-empty {
    display:flex; flex-direction:column; align-items:center;
    padding:48px 24px; text-align:center; color:#adb5bd;
}
.sv-empty i { font-size:2.2rem; margin-bottom:12px; }
.sv-empty p { font-size:.82rem; color:#6c757d; max-width:360px; line-height:1.6; margin:0; }

/* ─── Table ──────────────────────────────────────────────────────────────── */
.sv-table { width:100%; margin:0; border-collapse:separate; border-spacing:0; }
.sv-table thead tr { background:#fafbfc; }
.sv-table th {
    padding:10px 16px; font-size:.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.05em; color:#6c757d;
    border-bottom:1px solid #f0f2f5; white-space:nowrap;
}
.sv-table td {
    padding:14px 16px; font-size:.83rem; color:#2c3040;
    border-bottom:1px solid #f8f9fb; vertical-align:middle;
}
.sv-table tbody tr:last-child td { border-bottom:none; }
.sv-row-ended td { opacity:.55; }
.sv-row-ended:hover td { opacity:.75; }

/* State chip ── */
.sv-state-chip {
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:10px;
    font-size:.82rem; font-weight:800; letter-spacing:.06em;
}
.sv-state-chip--video { background:#ffc1071a; color:#9a6e00; }
.sv-state-chip--async { background:#6c757d14; color:#495057; }
.sv-state-chip--dim   { background:#f0f1f3; color:#adb5bd; }

/* Scope cell ── */
.sv-scope-label { display:block; font-size:.82rem; font-weight:600; color:#2c3040; }
.sv-scope-sub   { display:block; font-size:.74rem; color:#6c757d; margin-top:2px; }

/* Pill ── */
.sv-pill {
    display:inline-block; padding:2px 9px; border-radius:20px;
    font-size:.67rem; font-weight:700; letter-spacing:.02em; white-space:nowrap;
}
.sv-pill--video  { background:#ffc1071a; color:#9a6e00; }
.sv-pill--async  { background:#6c757d14; color:#495057; }
.sv-pill--active { background:#2dc6531a; color:#1a7a30; }
.sv-pill--ended  { background:#f0f1f3; color:#adb5bd; }
.sv-pill--future { background:#4361ee12; color:#4361ee; }

/* Date range ── */
.sv-date-range { display:block; font-size:.78rem; color:#2c3040; margin-bottom:4px; }
.sv-date-arrow { font-size:.7rem; color:#adb5bd; margin:0 3px; }

/* Note cell ── */
.sv-note-cell { font-size:.78rem; color:#6c757d; font-style:italic; max-width:200px; }

/* End button / tag ── */
.sv-end-btn {
    display:inline-flex; align-items:center;
    border:1.5px solid #dc354540; background:none;
    color:#dc3545; font-size:.75rem; font-weight:600;
    padding:4px 10px; border-radius:7px; cursor:pointer;
    transition:background .15s, border-color .15s;
}
.sv-end-btn:hover { background:#dc35450d; border-color:#dc3545; }
.sv-ended-tag { font-size:.72rem; color:#adb5bd; font-style:italic; }

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width:767.98px) {
    .sv-section-hd  { padding:16px; }
    .sv-section-body { padding:16px; }
    .sv-page-hd { gap:10px; }
    .sv-table th:nth-child(5), .sv-table td:nth-child(5) { display:none; }
}
@media (max-width:575.98px) {
    .sv-table th:nth-child(3), .sv-table td:nth-child(3) { display:none; }
    .sv-state-chip { width:34px; height:34px; font-size:.72rem; border-radius:8px; }
}
</style>
@endsection
