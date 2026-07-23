@extends('layouts.admin')

@section('title', 'SLA Settings')

@section('content')
{{--
    The SLA node (Devin msg 2313 Q6: "SLA is going to be adjusted by doctor admin
    and pushed down so we need a node for that. it should bypass, or seek approval
    from Dr Admin").

    Scope of what this gates, stated on the screen because it is easy to assume it
    does more: it only ever affects a doctor PULLING work from the pool. It never
    stops a case being pushed to them and never stops a returning patient reaching
    their own doctor.
--}}
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">SLA Settings</h4>
        <p class="text-muted small mb-0">
            Your standard for the doctors you are over. It applies when one of them asks the pool for
            more cases while behind on the work they already hold.
        </p>
    </div>
    <a href="{{ route('admin.routing.pull-requests') }}" class="btn btn-outline-secondary btn-sm">Pull requests</a>
</div>

<div class="alert alert-secondary small">
    <strong>This gates one thing.</strong> A doctor asking the pool for more work. It never blocks a
    case being assigned to them, and never blocks a check-in going back to the doctor who treated
    that patient before. Care routes; requests for more of it are what get held.
    <div class="mt-2">
        Not to be confused with <a href="{{ route('admin.settings') }}">SLA Settings</a>, which are the
        house targets for how fast a case should be picked up and reviewed. Those measure cases. This
        measures a doctor, and only when they ask for more.
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Your SLA</h6>

        <form method="POST" action="{{ route('admin.routing.sla.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" maxlength="150"
                           value="{{ old('name', $mine->name ?? 'SLA policy') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">When a doctor is over it</label>
                    <select name="on_violation" class="form-select" required>
                        @foreach($onActions as $value => $label)
                            <option value="{{ $value }}"
                                {{ old('on_violation', $mine->on_violation ?? 'REQUIRE_APPROVAL') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Max open cases</label>
                    <input type="number" name="max_outstanding_cases" class="form-control" min="1" max="9999"
                           value="{{ old('max_outstanding_cases', $mine->max_outstanding_cases ?? '') }}">
                    <div class="form-text">Blank means no limit.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max overdue cases</label>
                    <input type="number" name="max_overdue_cases" class="form-control" min="1" max="9999"
                           value="{{ old('max_overdue_cases', $mine->max_overdue_cases ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Overdue after (hours)</label>
                    <input type="number" name="overdue_after_hours" class="form-control" min="1" max="720"
                           value="{{ old('overdue_after_hours', $mine->overdue_after_hours ?? '') }}">
                    <div class="form-text">Needed for the overdue limit to do anything.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Max decision time (minutes)</label>
                    <input type="number" name="max_median_decision_minutes" class="form-control" min="1"
                           value="{{ old('max_median_decision_minutes', $mine->max_median_decision_minutes ?? '') }}">
                    <div class="form-text">
                        Average time from a case arriving to a decision, over the last 30 days.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control" maxlength="500"
                           value="{{ old('note', $mine->note ?? '') }}">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="slaActive" name="is_active" value="1"
                               {{ old('is_active', $mine->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="slaActive">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Save SLA</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Doctors this covers ({{ $doctors->count() }})</div>
    @if($doctors->isEmpty())
        <div class="card-body text-muted small">
            You are not over any doctors, so this SLA governs nobody yet.
        </div>
    @else
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($doctors as $doctor)
                    <span class="badge bg-light text-dark border">{{ $doctor->user?->name ?? 'Doctor #' . $doctor->id }}</span>
                @endforeach
            </div>
            <div class="text-muted small mt-2">
                A doctor under two admins takes the stricter of the two SLAs, field by field, and needs
                approval if either admin asked for it.
            </div>
        </div>
    @endif
</div>

@if($policies->count() > 1 || (auth()->user()?->isSuperAdmin() && $policies->isNotEmpty()))
<div class="card">
    <div class="card-header bg-white fw-semibold">All SLA policies</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
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
                    <td>{{ $policy->owner?->name }}</td>
                    <td>{{ $policy->max_outstanding_cases ?? '—' }}</td>
                    <td>
                        @if($policy->max_overdue_cases)
                            {{ $policy->max_overdue_cases }} over {{ $policy->overdue_after_hours }}h
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $policy->max_median_decision_minutes ? $policy->max_median_decision_minutes . ' min' : '—' }}</td>
                    <td class="small">{{ \App\Models\SlaPolicy::ON_VIOLATION_LABELS[$policy->on_violation] ?? $policy->on_violation }}</td>
                    <td>{!! $policy->is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
