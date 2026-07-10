@extends('layouts.clinician')

@section('title', 'Clinician Dashboard')
@section('page-title', 'Dashboard')

@section('content')

@php
    $rate = $stats['completion_rate'];
    // SVG ring: circumference of r=28 circle ≈ 175.9
    $circ  = 2 * M_PI * 28;
    $dash  = round($circ * ($rate / 100), 2);
    $gap   = round($circ - $dash, 2);

    $rateColor = match(true) {
        $rate >= 75 => '#2dc653',
        $rate >= 50 => '#ffc107',
        default     => '#fd7e14',
    };
@endphp

{{-- ── Stat Cards ─────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Queue size --}}
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('clinician.queue') }}" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #4361ee !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:#4361ee1a;">
                    <i class="bi bi-hourglass-split" style="font-size:1.3rem;color:#4361ee;"></i>
                </div>
                <div>
                    <p class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">Waiting Queue</p>
                    <h3 class="fw-bold mb-0" style="color:#4361ee;">{{ $stats['queue'] }}</h3>
                    <p class="text-muted mb-0" style="font-size:.7rem;">cases available to claim</p>
                </div>
            </div>
        </div>
        </a>
    </div>

    {{-- My active --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #ffc107 !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:#ffc1071a;">
                    <i class="bi bi-person-check" style="font-size:1.3rem;color:#ffc107;"></i>
                </div>
                <div>
                    <p class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">My Active Cases</p>
                    <h3 class="fw-bold mb-0" style="color:#ffc107;">{{ $stats['my_active'] }}</h3>
                    <p class="text-muted mb-0" style="font-size:.7rem;">assigned &amp; approved</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Completed this month --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #2dc653 !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:#2dc6531a;">
                    <i class="bi bi-clipboard-check" style="font-size:1.3rem;color:#2dc653;"></i>
                </div>
                <div>
                    <p class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">Completed This Month</p>
                    <h3 class="fw-bold mb-0" style="color:#2dc653;">{{ $stats['completed_month'] }}</h3>
                    <p class="text-muted mb-0" style="font-size:.7rem;">{{ now()->format('F Y') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- SLA status --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"
             style="border-left:4px solid {{ $slaBreached > 0 ? '#dc3545' : ($slaAtRisk > 0 ? '#ffc107' : '#2dc653') }} !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:{{ $slaBreached > 0 ? '#dc35451a' : ($slaAtRisk > 0 ? '#ffc1071a' : '#2dc6531a') }};">
                    <i class="bi bi-{{ $slaBreached > 0 ? 'exclamation-triangle' : ($slaAtRisk > 0 ? 'clock-history' : 'check-circle') }}"
                       style="font-size:1.3rem;color:{{ $slaBreached > 0 ? '#dc3545' : ($slaAtRisk > 0 ? '#ffc107' : '#2dc653') }};"></i>
                </div>
                <div>
                    <p class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">SLA Status</p>
                    @if($slaBreached > 0)
                        <h3 class="fw-bold mb-0 text-danger">{{ $slaBreached }} Breached</h3>
                        <p class="mb-0" style="font-size:.7rem;color:#dc3545;">{{ $slaAtRisk }} more at risk</p>
                    @elseif($slaAtRisk > 0)
                        <h3 class="fw-bold mb-0 text-warning">{{ $slaAtRisk }} At Risk</h3>
                        <p class="text-muted mb-0" style="font-size:.7rem;">{{ $slaReviewHours }}h review deadline</p>
                    @else
                        <h3 class="fw-bold mb-0 text-success">On Track</h3>
                        <p class="text-muted mb-0" style="font-size:.7rem;">All within {{ $slaReviewHours }}h deadline</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Completion rate with SVG ring --}}
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $rateColor }} !important;">
            <div class="card-body d-flex align-items-center gap-3">
                {{-- SVG radial ring --}}
                <div class="flex-shrink-0" style="position:relative;width:56px;height:56px;">
                    <svg width="56" height="56" viewBox="0 0 72 72" style="transform:rotate(-90deg);">
                        <circle cx="36" cy="36" r="28" fill="none" stroke="#f1f3f5" stroke-width="7"/>
                        <circle cx="36" cy="36" r="28" fill="none"
                                stroke="{{ $rateColor }}" stroke-width="7"
                                stroke-dasharray="{{ $dash }} {{ $gap }}"
                                stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                                font-size:.65rem;font-weight:700;color:{{ $rateColor }};">
                        {{ $rate }}%
                    </div>
                </div>
                <div>
                    <p class="text-muted mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">Completion Rate</p>
                    <h3 class="fw-bold mb-0" style="color:{{ $rateColor }};">{{ $rate }}%</h3>
                    <p class="text-muted mb-0" style="font-size:.7rem;">{{ $stats['total_my_cases'] }} total cases</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Charts Row ──────────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">

    {{-- Dual-line: Assigned vs Completed (30 days) --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="mb-0 fw-semibold">My Case Activity</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">Cases assigned to me vs cases I completed — last 30 days</p>
                </div>
                <div class="d-flex gap-3 align-items-center" style="font-size:.72rem;">
                    <span class="d-flex align-items-center gap-1">
                        <span style="width:12px;height:3px;background:#4361ee;border-radius:2px;display:inline-block;"></span>
                        Assigned
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <span style="width:12px;height:3px;background:#2dc653;border-radius:2px;display:inline-block;"></span>
                        Completed
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding:20px 16px 12px;">
                <canvas id="activityChart" style="width:100%;max-height:230px;"></canvas>
            </div>
        </div>
    </div>

    {{-- Visit Type Breakdown --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="mb-0 fw-semibold">Visit Type Breakdown</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">My cases by visit type (all time)</p>
                </div>
                <i class="bi bi-bar-chart-horizontal text-muted opacity-50 fs-5"></i>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                @if(count($visitTypeCounts) > 0)
                <canvas id="visitTypeChart" style="width:100%;max-height:{{ max(140, count($visitTypeLabels) * 38) }}px;"></canvas>
                @else
                <div class="text-center text-muted py-4">
                    <i class="bi bi-bar-chart" style="font-size:2rem;opacity:.2;"></i>
                    <p class="mt-2 small mb-0">No visit type data yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Active Cases Table ──────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
        <div>
            <h6 class="mb-0 fw-semibold">My Active Cases</h6>
            <p class="text-muted mb-0" style="font-size:.72rem;">Cases currently assigned or approved</p>
        </div>
        <a href="{{ route('clinician.queue') }}" class="btn btn-sm btn-outline-primary">View Queue</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead style="background:#f8f9fc;">
                    <tr>
                        <th class="ps-4 py-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Patient</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Partner</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Offerings</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Status</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">SLA</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Created</th>
                        <th class="pe-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentCases as $case)
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                                     style="width:32px;height:32px;background:#4361ee1a;color:#4361ee;font-size:.72rem;">
                                    {{ strtoupper(substr($case->patient->full_name ?? 'P', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-medium">{{ $case->patient->full_name ?? 'N/A' }}</div>
                                    <div class="text-muted" style="font-size:.7rem;">{{ $case->patient->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $case->partner->name ?? '—' }}</td>
                        <td>
                            @foreach($case->caseOfferings->take(2) as $co)
                                <span class="badge bg-light text-dark border" style="font-size:.7rem;">{{ $co->offering->name ?? 'Unknown' }}</span>
                            @endforeach
                            @if($case->caseOfferings->count() > 2)
                                <span class="text-muted" style="font-size:.7rem;">+{{ $case->caseOfferings->count() - 2 }} more</span>
                            @endif
                        </td>
                        <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                        <td style="min-width:140px;">
                            @php
                                $barColor = $case->sla_breached ? '#dc3545' : ($case->sla_at_risk ? '#ffc107' : '#2dc653');
                                $barPct   = min($case->sla_pct, 100);
                                $label    = $case->sla_breached
                                    ? 'Breached +' . round($case->sla_elapsed_h - $slaReviewHours, 1) . 'h'
                                    : $case->sla_remaining . 'h left';
                            @endphp
                            <div class="d-flex align-items-center gap-2">
                                <div style="flex:1;background:#f1f3f5;border-radius:4px;height:6px;overflow:hidden;">
                                    <div style="width:{{ $barPct }}%;height:100%;background:{{ $barColor }};border-radius:4px;transition:width .4s;"></div>
                                </div>
                                <span style="font-size:.68rem;white-space:nowrap;color:{{ $barColor }};font-weight:600;">
                                    {{ $label }}
                                </span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $case->created_at->diffForHumans() }}</td>
                        <td class="pe-4">
                            <a href="{{ route('clinician.cases.show', $case->uuid) }}"
                               class="btn btn-sm btn-outline-primary">Review</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size:2rem;opacity:.2;display:block;margin-bottom:.5rem;"></i>
                            No active cases right now.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";

    // ── Dual-line: Assigned vs Completed ────────────────────────────
    const activityEl = document.getElementById('activityChart');
    if (activityEl) {
        const labels    = @json($trendLabels);
        const assigned  = @json($trendAssigned);
        const completed = @json($trendCompleted);
        const maxVal    = Math.max(...assigned, ...completed, 1);

        const makeGradient = (ctx, colorStart, colorEnd) => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 230);
            g.addColorStop(0, colorStart);
            g.addColorStop(1, colorEnd);
            return g;
        };

        new Chart(activityEl, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label:            'Assigned to Me',
                        data:             assigned,
                        borderColor:      '#4361ee',
                        borderWidth:      2.5,
                        pointRadius:      assigned.map(v => v > 0 ? 4 : 0),
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        fill:             true,
                        backgroundColor:  ctx => makeGradient(ctx, 'rgba(67,97,238,.15)', 'rgba(67,97,238,0)'),
                        tension:          0.42,
                        order:            2,
                    },
                    {
                        label:            'Completed by Me',
                        data:             completed,
                        borderColor:      '#2dc653',
                        borderWidth:      2.5,
                        pointRadius:      completed.map(v => v > 0 ? 4 : 0),
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2dc653',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        fill:             true,
                        backgroundColor:  ctx => makeGradient(ctx, 'rgba(45,198,83,.12)', 'rgba(45,198,83,0)'),
                        tension:          0.42,
                        order:            1,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        grid:   { display: false },
                        border: { display: false },
                        ticks:  { color: '#adb5bd', font: { size: 10 }, maxTicksLimit: 10 }
                    },
                    y: {
                        beginAtZero:  true,
                        suggestedMax: maxVal + 1,
                        grid:         { color: '#f1f3f5' },
                        border:       { display: false },
                        ticks:        { color: '#adb5bd', font: { size: 10 }, precision: 0, maxTicksLimit: 5 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#212529',
                        titleColor:      '#fff',
                        bodyColor:       '#adb5bd',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}`
                        }
                    }
                },
                animation: { duration: 900, easing: 'easeInOutQuart' }
            }
        });
    }

    // ── Visit Type horizontal bar ────────────────────────────────────
    const vtEl = document.getElementById('visitTypeChart');
    if (vtEl) {
        const vtLabels = @json($visitTypeLabels);
        const vtCounts = @json($visitTypeCounts);
        const vtMax    = Math.max(...vtCounts, 1);

        // Indigo → teal palette
        const vtColors = vtLabels.map((_, i) => {
            const ratio = vtLabels.length > 1 ? i / (vtLabels.length - 1) : 0;
            const r = Math.round(67  + ratio * (32  - 67));
            const g = Math.round(97  + ratio * (178 - 97));
            const b = Math.round(238 + ratio * (170 - 238));
            return `rgba(${r},${g},${b},0.85)`;
        });

        new Chart(vtEl, {
            type: 'bar',
            data: {
                labels: vtLabels,
                datasets: [{
                    label:           'Cases',
                    data:            vtCounts,
                    backgroundColor: vtColors,
                    borderRadius:    6,
                    borderSkipped:   false,
                    barThickness:    22,
                }]
            },
            options: {
                indexAxis:   'y',
                responsive:  true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero:  true,
                        suggestedMax: vtMax + 1,
                        grid:   { color: '#f1f3f5' },
                        border: { display: false },
                        ticks:  { color: '#adb5bd', font: { size: 10 }, precision: 0, maxTicksLimit: 5 }
                    },
                    y: {
                        grid:   { display: false },
                        border: { display: false },
                        ticks:  { color: '#495057', font: { size: 11, weight: '500' } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#212529',
                        titleColor:      '#fff',
                        bodyColor:       '#adb5bd',
                        padding:         10,
                        cornerRadius:    8,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x} case${ctx.parsed.x !== 1 ? 's' : ''}`
                        }
                    }
                },
                animation: { duration: 900, easing: 'easeInOutQuart' }
            }
        });
    }
})();
</script>
@endsection
