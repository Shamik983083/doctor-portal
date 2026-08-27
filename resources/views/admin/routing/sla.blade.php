@extends('layouts.admin')

@section('title', 'Provider Pull SLA')

@section('content')
<style>
.sla-page { max-width: 780px; }
.sla-section { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.sla-section-header { padding:16px 20px 12px; border-bottom:1px solid #f1f3f5; }
.sla-section-header h6 { font-size:.78rem; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:#6b7280; margin:0; }
.sla-body { padding:20px; }
.field-row { display:grid; gap:16px; margin-bottom:16px; }
.field-row.cols-2 { grid-template-columns:1fr 1fr; }
.field-row.cols-3 { grid-template-columns:1fr 1fr 1fr; }
.field-row.cols-1 { grid-template-columns:1fr; }
.field-group label { display:block; font-size:.72rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#6b7280; margin-bottom:6px; }
.field-group .form-control,
.field-group .form-select { font-size:.85rem; border-radius:8px; border-color:#d1d5db; background:#fafafa; padding:8px 12px; transition:border-color .15s,box-shadow .15s; }
.field-group .form-control:focus,
.field-group .form-select:focus { background:#fff; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
.field-hint { font-size:.72rem; color:#9ca3af; margin-top:4px; }
.sla-toggle { display:flex; align-items:center; gap:10px; padding:14px 20px; background:#f9fafb; border-top:1px solid #f1f3f5; }
.sla-toggle label { font-size:.82rem; font-weight:500; color:#374151; margin:0; cursor:pointer; }
.sla-actions { padding:16px 20px; border-top:1px solid #f1f3f5; display:flex; justify-content:flex-end; }
.sla-save-btn { font-size:.82rem; font-weight:600; padding:8px 22px; border-radius:8px; background:#1d1d1f; color:#fff; border:none; cursor:pointer; transition:opacity .15s; }
.sla-save-btn:hover { opacity:.85; }
.info-callout { background:#f0f4ff; border:1px solid #c7d2fe; border-radius:10px; padding:14px 18px; margin-bottom:16px; font-size:.82rem; color:#3730a3; line-height:1.55; }
.info-callout strong { color:#312e81; }
.info-callout a { color:#4f46e5; text-decoration:none; font-weight:500; }
.info-callout a:hover { text-decoration:underline; }
.doctor-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; background:#f3f4f6; border:1px solid #e5e7eb; font-size:.78rem; color:#374151; font-weight:500; }
.policy-table th { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:#9ca3af; font-weight:600; padding:10px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
.policy-table td { font-size:.8rem; padding:10px 16px; border-bottom:1px solid #f3f4f6; color:#374151; }
.policy-table tr:last-child td { border-bottom:none; }
</style>

<div class="sla-page">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-semibold mb-1" style="font-size:1.2rem;color:#111827;letter-spacing:-.02em">Provider Pull SLA</h4>
            <p class="mb-0" style="font-size:.82rem;color:#6b7280">Controls when a doctor can pull new cases from the pool while behind on existing work.</p>
        </div>
        <a href="{{ route('admin.routing.pull-requests') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;border-radius:8px">
            <i class="bi bi-inbox me-1"></i>Pull requests
        </a>
    </div>

    {{-- Info callout --}}
    <div class="info-callout mb-4">
        <div><strong><i class="bi bi-info-circle me-1"></i>This gates one thing</strong> — a doctor asking the pool for more work.</div>
        <div class="mt-1">It never blocks a case being <em>assigned</em> to them, and never blocks a returning patient reaching their own doctor. Care routes freely; requests for more of it are what get held.</div>
        <div class="mt-1 pt-1" style="border-top:1px solid #c7d2fe">Not to be confused with <a href="{{ route('admin.settings') }}">Case SLA Targets</a>, which measure how fast a case is picked up. This measures a doctor, and only when they ask for more.</div>
    </div>


    {{-- SLA form --}}
    <div class="sla-section">
        <div class="sla-section-header">
            <h6>Your Policy</h6>
        </div>
        <form method="POST" action="{{ route('admin.routing.sla.store') }}">
            @csrf
            <div class="sla-body">

                <div class="field-row cols-2">
                    <div class="field-group">
                        <label>Policy name</label>
                        <input type="text" name="name" class="form-control" maxlength="150"
                               value="{{ old('name', $mine->name ?? 'SLA policy') }}">
                    </div>
                    <div class="field-group">
                        <label>When a doctor is over it</label>
                        <select name="on_violation" class="form-select" required>
                            @foreach($onActions as $value => $label)
                                <option value="{{ $value }}"
                                    {{ old('on_violation', $mine->on_violation ?? 'REQUIRE_APPROVAL') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="font-size:.7rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:#9ca3af;margin-bottom:10px">Thresholds — leave blank for no limit</div>
                <div class="field-row cols-3">
                    <div class="field-group">
                        <label>Max open cases</label>
                        <input type="number" name="max_outstanding_cases" class="form-control" min="1" max="9999"
                               value="{{ old('max_outstanding_cases', $mine->max_outstanding_cases ?? '') }}">
                    </div>
                    <div class="field-group">
                        <label>Max overdue cases</label>
                        <input type="number" name="max_overdue_cases" class="form-control" min="1" max="9999"
                               value="{{ old('max_overdue_cases', $mine->max_overdue_cases ?? '') }}">
                    </div>
                    <div class="field-group">
                        <label>Overdue after (hours)</label>
                        <input type="number" name="overdue_after_hours" class="form-control" min="1" max="720"
                               value="{{ old('overdue_after_hours', $mine->overdue_after_hours ?? '') }}">
                        <div class="field-hint">Required for the overdue limit to apply.</div>
                    </div>
                </div>

                <div class="field-row cols-2" style="margin-bottom:0">
                    <div class="field-group">
                        <label>Max decision time (minutes)</label>
                        <input type="number" name="max_median_decision_minutes" class="form-control" min="1"
                               value="{{ old('max_median_decision_minutes', $mine->max_median_decision_minutes ?? '') }}">
                        <div class="field-hint">Median over the last 30 days, case arrival → decision.</div>
                    </div>
                    <div class="field-group">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control" maxlength="500"
                               value="{{ old('note', $mine->note ?? '') }}">
                    </div>
                </div>
            </div>

            <div class="sla-toggle">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input mt-0" type="checkbox" id="slaActive" name="is_active" value="1"
                       {{ old('is_active', $mine->is_active ?? true) ? 'checked' : '' }}>
                <label for="slaActive">Policy is active</label>
            </div>

            <div class="sla-actions">
                <button type="submit" class="sla-save-btn">Save policy</button>
            </div>
        </form>
    </div>

    {{-- Doctors covered --}}
    <div class="sla-section">
        <div class="sla-section-header d-flex justify-content-between align-items-center" style="padding-bottom:12px">
            <h6 style="margin:0">Doctors this covers</h6>
            <span style="font-size:.78rem;color:#6b7280;font-weight:500">{{ $doctors->count() }} {{ Str::plural('doctor', $doctors->count()) }}</span>
        </div>
        <div class="sla-body" style="padding:16px 20px">
            @if($doctors->isEmpty())
                <p class="mb-0" style="font-size:.82rem;color:#9ca3af">You are not over any doctors yet — this policy governs nobody.</p>
            @else
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($doctors as $doctor)
                        <span class="doctor-pill">
                            <i class="bi bi-person" style="font-size:.7rem"></i>
                            {{ $doctor->user?->name ?? 'Doctor #' . $doctor->id }}
                        </span>
                    @endforeach
                </div>
                <p class="mb-0" style="font-size:.75rem;color:#9ca3af">A doctor under two admins takes the stricter of the two SLAs, field by field, and needs approval if either admin required it.</p>
            @endif
        </div>
    </div>

    {{-- All policies (super admin) --}}
    @if($policies->count() > 1 || (auth()->user()?->isSuperAdmin() && $policies->isNotEmpty()))
    <div class="sla-section">
        <div class="sla-section-header">
            <h6>All Policies</h6>
        </div>
        <table class="policy-table w-100">
            <thead>
                <tr>
                    <th>Owner</th>
                    <th>Open</th>
                    <th>Overdue</th>
                    <th>Decision</th>
                    <th>On breach</th>
                    <th>Active</th>
                </tr>
            </thead>
            <tbody>
            @foreach($policies as $policy)
                <tr>
                    <td class="fw-medium">{{ $policy->owner?->name }}</td>
                    <td>{{ $policy->max_outstanding_cases ?? '—' }}</td>
                    <td>
                        @if($policy->max_overdue_cases)
                            {{ $policy->max_overdue_cases }} over {{ $policy->overdue_after_hours }}h
                        @else —
                        @endif
                    </td>
                    <td>{{ $policy->max_median_decision_minutes ? $policy->max_median_decision_minutes . ' min' : '—' }}</td>
                    <td>{{ \App\Models\SlaPolicy::ON_VIOLATION_LABELS[$policy->on_violation] ?? $policy->on_violation }}</td>
                    <td>{!! $policy->is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
@endsection
