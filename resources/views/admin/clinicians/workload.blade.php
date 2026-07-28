@extends('layouts.admin')

@section('title', 'Provider Workload')
@section('page-title', 'Provider Workload')

@section('content')

@php
    $activeStatuses  = ['waiting', 'assigned', 'support', 'approved', 'processing'];
    $totalOpen       = 0;
    $overCapCount    = 0;
    $supportCount    = 0;
    $unavailCount    = 0;

    foreach ($clinicians as $c) {
        $statuses    = $byStatus[$c->id] ?? [];
        $open        = array_sum($statuses);
        $totalOpen  += $open;

        $cap = (int) ($c->max_open_cases ?: $c->max_daily_cases ?: 0);
        if ($cap > 0 && $open >= $cap) $overCapCount++;
        if (($statuses['support'] ?? 0) > 0) $supportCount++;

        $inCooldown = $c->pool_cooldown_until && $c->pool_cooldown_until->isFuture();
        if (! $c->is_available || ! $c->accepting_new_cases || $inCooldown) $unavailCount++;
    }
@endphp

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary">{{ number_format($totalOpen) }}</div>
                <div class="text-muted small">Active Cases</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 {{ $overCapCount > 0 ? 'border-danger' : '' }}">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold {{ $overCapCount > 0 ? 'text-danger' : '' }}">{{ $overCapCount }}</div>
                <div class="text-muted small">At / Over Cap</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 {{ $supportCount > 0 ? 'border-warning' : '' }}">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold {{ $supportCount > 0 ? 'text-warning' : '' }}">{{ $supportCount }}</div>
                <div class="text-muted small">Have Escalations</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold">{{ $unavailCount }}</div>
                <div class="text-muted small">Unavailable / Blocked</div>
            </div>
        </div>
    </div>
</div>

@if($clinicians->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-person-badge fs-2 d-block mb-2 opacity-25"></i>
            @if($user->isSuperAdmin())
                No clinicians have been added yet.
            @else
                No clinicians are in your managed group yet. Assign doctors to your account to see their workload here.
            @endif
        </div>
    </div>
@else

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Providers — sorted by active case load</h6>
        <a href="{{ route('admin.clinicians.bulk-reassign') }}"
           class="btn btn-sm btn-outline-secondary py-0 px-2 small">
            <i class="bi bi-arrow-left-right me-1"></i>Bulk Reassign
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:180px;">Provider</th>
                        <th class="text-center" title="Waiting">W</th>
                        <th class="text-center" title="Assigned">A</th>
                        <th class="text-center text-warning" title="Support escalations">S</th>
                        <th class="text-center" title="Approved">✓</th>
                        <th class="text-center" title="Processing">P</th>
                        <th class="text-center fw-semibold">Open</th>
                        <th style="min-width:150px;">Capacity</th>
                        <th class="text-center" title="Completed today">Done</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($clinicians as $clinician)
                @php
                    $statuses  = $byStatus[$clinician->id] ?? [];
                    $waiting   = $statuses['waiting']    ?? 0;
                    $assigned  = $statuses['assigned']   ?? 0;
                    $support   = $statuses['support']    ?? 0;
                    $approved  = $statuses['approved']   ?? 0;
                    $processing= $statuses['processing'] ?? 0;
                    $openTotal = $waiting + $assigned + $support + $approved + $processing;
                    $done      = (int) ($completedToday[$clinician->id] ?? 0);

                    // Capacity: prefer open-case cap, fall back to daily cap
                    $capType  = null;
                    $cap      = 0;
                    if ($clinician->max_open_cases) {
                        $cap     = (int) $clinician->max_open_cases;
                        $capType = 'open';
                    } elseif ($clinician->max_daily_cases) {
                        $cap     = (int) $clinician->max_daily_cases;
                        $capType = 'daily';
                    }

                    $pct      = ($cap > 0) ? min(100, (int) round($openTotal / $cap * 100)) : 0;
                    $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');

                    $inCooldown   = $clinician->pool_cooldown_until && $clinician->pool_cooldown_until->isFuture();
                    $isInactive   = $clinician->status !== 'active';
                    $isBusy       = ! $clinician->is_available;
                    $notAccepting = ! $clinician->accepting_new_cases;
                @endphp
                <tr class="{{ $isInactive ? 'opacity-50' : '' }}">

                    {{-- Provider name + flags --}}
                    <td>
                        <div class="fw-semibold">
                            <a href="{{ route('admin.clinicians.show', $clinician->id) }}"
                               class="text-decoration-none text-reset">
                                {{ $clinician->full_name }}
                            </a>
                        </div>
                        <div class="d-flex gap-1 flex-wrap mt-1">
                            @if($isInactive)
                                <span class="badge bg-secondary bg-opacity-75" style="font-size:.6rem;">
                                    {{ ucfirst($clinician->status) }}
                                </span>
                            @endif
                            @if($isBusy)
                                <span class="badge bg-warning text-dark" style="font-size:.6rem;">
                                    <i class="bi bi-pause-circle me-1"></i>Unavailable
                                </span>
                            @endif
                            @if($notAccepting)
                                <span class="badge bg-secondary" style="font-size:.6rem;">
                                    Not accepting new
                                </span>
                            @endif
                            @if($inCooldown)
                                <span class="badge bg-danger bg-opacity-75" style="font-size:.6rem;"
                                      title="Pool cooldown until {{ $clinician->pool_cooldown_until->format('d M H:i') }}">
                                    <i class="bi bi-clock me-1"></i>Cooldown
                                </span>
                            @endif
                        </div>
                    </td>

                    {{-- Status count cells --}}
                    <td class="text-center">
                        @if($waiting > 0)
                            <span class="badge bg-secondary">{{ $waiting }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($assigned > 0)
                            <span class="badge bg-primary">{{ $assigned }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($support > 0)
                            <span class="badge bg-warning text-dark">{{ $support }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($approved > 0)
                            <span class="badge bg-success">{{ $approved }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($processing > 0)
                            <span class="badge bg-info text-dark">{{ $processing }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    {{-- Open total --}}
                    <td class="text-center fw-bold {{ $openTotal > 0 ? 'text-primary' : 'text-muted' }}">
                        {{ $openTotal }}
                    </td>

                    {{-- Capacity bar + inline cap edit --}}
                    <td>
                        @if($cap > 0)
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.75rem;">
                                <span class="text-muted">
                                    {{ $capType === 'open' ? 'Open' : 'Daily' }} cap
                                </span>
                                <span class="fw-semibold {{ $pct >= 90 ? 'text-danger' : ($pct >= 70 ? 'text-warning' : '') }}">
                                    {{ $openTotal }} / {{ $cap }}
                                </span>
                            </div>
                            <div class="progress mb-1" style="height:5px;">
                                <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%;"></div>
                            </div>
                        @else
                            <span class="text-muted small">Uncapped</span>
                        @endif
                        {{-- Inline daily-cap edit (reuses existing PATCH endpoint) --}}
                        <div class="mt-1">
                            <form class="cap-form d-flex gap-1 align-items-center"
                                  data-clinician="{{ $clinician->id }}"
                                  data-url="{{ route('admin.clinicians.case-load', $clinician->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="max_daily_cases" min="1" max="999"
                                       value="{{ $clinician->max_daily_cases ?? '' }}"
                                       placeholder="Daily cap"
                                       class="form-control form-control-sm py-0"
                                       style="width:80px;height:22px;font-size:.72rem;">
                                <button type="submit" class="btn btn-outline-secondary py-0 px-1"
                                        style="height:22px;font-size:.72rem;line-height:1;">
                                    Set
                                </button>
                            </form>
                        </div>
                    </td>

                    {{-- Completed today --}}
                    <td class="text-center {{ $done > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                        {{ $done ?: '—' }}
                    </td>

                    {{-- Actions --}}
                    <td>
                        <a href="{{ route('admin.clinicians.bulk-reassign', ['from_clinician_id' => $clinician->id]) }}"
                           class="btn btn-sm btn-outline-secondary py-0 px-2"
                           title="Reassign cases from {{ $clinician->full_name }}"
                           style="font-size:.75rem;">
                            Reassign
                        </a>
                    </td>

                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-muted small">
        <span class="me-3"><strong>W</strong> = Waiting</span>
        <span class="me-3"><strong>A</strong> = Assigned</span>
        <span class="me-3"><strong>S</strong> = Support (escalated)</span>
        <span class="me-3"><strong>✓</strong> = Approved</span>
        <span class="me-3"><strong>P</strong> = Processing</span>
        <span class="text-muted">Daily cap is the cap for new cases per day. Set via the input or edit the clinician profile for open-case and daily-new caps.</span>
    </div>
</div>

@endif

@endsection

@section('scripts')
<script>
(function () {
    document.querySelectorAll('.cap-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var url   = form.dataset.url;
            var input = form.querySelector('input[name="max_daily_cases"]');
            var btn   = form.querySelector('button[type="submit"]');
            var val   = parseInt(input.value, 10);

            if (!val || val < 1) {
                input.classList.add('is-invalid');
                return;
            }
            input.classList.remove('is-invalid');

            var fd = new FormData(form);
            btn.disabled = true;
            btn.textContent = '…';

            fetch(url, {
                method: 'POST',          // FormData with _method=PATCH
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd,
            })
            .then(function (r) {
                if (!r.ok) throw new Error('Server error ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data.success) {
                    btn.textContent = '✓';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-outline-success');
                    setTimeout(function () {
                        btn.textContent = 'Set';
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-outline-secondary');
                        btn.disabled = false;
                    }, 1800);
                } else {
                    throw new Error('Unexpected response');
                }
            })
            .catch(function () {
                btn.textContent = '!';
                btn.classList.add('btn-outline-danger');
                btn.disabled = false;
            });
        });
    });
})();
</script>
@endsection
