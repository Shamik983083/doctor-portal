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
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-semibold mb-1">State Visit Requirements</h4>
        <p class="text-muted small mb-0">
            Where the law requires a live video visit. A case in a state with a matching rule can
            only be routed to a doctor who takes synchronous visits and has a booking link.
        </p>
    </div>
    <a href="{{ route('admin.routing.index') }}" class="btn btn-outline-secondary btn-sm">Routing policy</a>
</div>

<div class="alert alert-secondary small">
    <strong>Most specific rule wins.</strong> A rule for one product beats a rule for its category,
    which beats a blanket rule for the state. With no matching rule, the video states already set on
    the product itself still apply, so nothing configured today stops working.
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Add a rule</h6>

        <form method="POST" action="{{ route('admin.routing.visit-requirements.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" maxlength="2" required
                           placeholder="TN" value="{{ old('state') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Applies to</label>
                    <select name="scope_type" class="form-select" required>
                        @foreach($scopes as $value => $label)
                            <option value="{{ $value }}" {{ old('scope_type') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category <span class="text-muted small">(if scoped)</span></label>
                    <select name="offering_category_id" class="form-select">
                        <option value="">—</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) old('offering_category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product <span class="text-muted small">(if scoped)</span></label>
                    <select name="offering_id" class="form-select">
                        <option value="">—</option>
                        @foreach($offerings as $offering)
                            <option value="{{ $offering->id }}" {{ (string) old('offering_id') === (string) $offering->id ? 'selected' : '' }}>
                                {{ $offering->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">In force from</label>
                    <input type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Until <span class="text-muted small">(blank = ongoing)</span></label>
                    <input type="date" name="effective_to" class="form-control" value="{{ old('effective_to') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control" maxlength="500"
                           placeholder="Which rule or statute this reflects" value="{{ old('note') }}">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="requires_synchronous" value="0">
                        <input class="form-check-input" type="checkbox" id="requiresSync"
                               name="requires_synchronous" value="1" checked>
                        <label class="form-check-label" for="requiresSync">
                            Requires a synchronous video visit.
                            <span class="text-muted small">
                                Leave unticked to record that this state explicitly does NOT require one,
                                which is how you turn off an older product-level video flag.
                            </span>
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary">Save rule</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Rules ({{ $rules->count() }})</div>
    @if($rules->isEmpty())
        <div class="card-body text-muted small">
            No rules. Every case resolves asynchronous unless a product carries its own video states.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>State</th>
                        <th>Applies to</th>
                        <th>Requires video</th>
                        <th>In force</th>
                        <th>Note</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rules as $rule)
                    @php
                        $ended = $rule->effective_to && $rule->effective_to->isPast();
                    @endphp
                    <tr class="{{ $ended ? 'text-muted' : '' }}">
                        <td class="fw-semibold">{{ $rule->state }}</td>
                        <td class="small">
                            {{ $rule->scopeLabel() }}
                            @if($rule->category)<div class="text-muted">{{ $rule->category->name }}</div>@endif
                            @if($rule->offering)<div class="text-muted">{{ $rule->offering->name }}</div>@endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $rule->requires_synchronous ? 'warning text-dark' : 'secondary' }}">
                                {{ $rule->requires_synchronous ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="small">
                            {{ $rule->effective_from?->toDateString() ?? 'always' }}
                            to
                            {{ $rule->effective_to?->toDateString() ?? 'ongoing' }}
                            @if($ended)<div class="badge bg-light text-muted">Ended</div>@endif
                        </td>
                        <td class="small">{{ $rule->note }}</td>
                        <td class="text-end">
                            @unless($ended)
                                <form method="POST" action="{{ route('admin.routing.visit-requirements.destroy', $rule->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">End</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body text-muted small border-top">
            Ending a rule that has already been in force keeps it on the record with an end date,
            because cases routed under it need to stay explainable.
        </div>
    @endif
</div>
@endsection
