@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')

@php
$cards = [
    ['label' => 'Partners',        'value' => $stats['partners'],        'icon' => 'building',        'color' => '#4361ee', 'link' => route('admin.partners.index')],
    ['label' => 'Patients',        'value' => $stats['patients'],        'icon' => 'people',          'color' => '#0dcaf0', 'link' => route('admin.patients.index')],
    ['label' => 'Active Cases',    'value' => $stats['active_cases'],    'icon' => 'folder2-open',    'color' => '#ffc107', 'link' => route('admin.cases.index')],
    ['label' => 'Clinicians',      'value' => $stats['clinicians'],      'icon' => 'person-badge',    'color' => '#2dc653', 'link' => route('admin.clinicians.index')],
    ['label' => 'Orders Today',    'value' => $stats['orders_today'],    'icon' => 'cart-check',      'color' => '#6c757d', 'link' => null],
    ['label' => 'Completed Today', 'value' => $stats['completed_today'], 'icon' => 'clipboard-check', 'color' => '#20c997', 'link' => route('admin.cases.index') . '?status=completed'],
];

// Status config: label, color, icon
$statusConfig = [
    'created'    => ['label' => 'Created',    'color' => '#adb5bd'],
    'waiting'    => ['label' => 'Waiting',    'color' => '#4361ee'],
    'assigned'   => ['label' => 'Assigned',   'color' => '#ffc107'],
    'approved'   => ['label' => 'Approved',   'color' => '#2dc653'],
    'processing' => ['label' => 'Processing', 'color' => '#0dcaf0'],
    'support'    => ['label' => 'Support',    'color' => '#fd7e14'],
    'completed'  => ['label' => 'Completed',  'color' => '#20c997'],
    'cancelled'  => ['label' => 'Cancelled',  'color' => '#dc3545'],
];

$donutLabels = [];
$donutData   = [];
$donutColors = [];
foreach ($casesByStatus as $status => $count) {
    $cfg = $statusConfig[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d'];
    $donutLabels[] = $cfg['label'];
    $donutData[]   = $count;
    $donutColors[] = $cfg['color'];
}
$totalCases = array_sum($donutData);
@endphp

{{-- ── Stat Cards ─────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    @foreach($cards as $card)
    <div class="col-sm-6 col-xl-4">
        @if($card['link'])<a href="{{ $card['link'] }}" class="text-decoration-none">@endif
        <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-left:4px solid {{ $card['color'] }} !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:{{ $card['color'] }}1a;">
                    <i class="bi bi-{{ $card['icon'] }}" style="font-size:1.3rem;color:{{ $card['color'] }};"></i>
                </div>
                <div>
                    <p class="text-muted small mb-0" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ $card['label'] }}</p>
                    <h3 class="fw-bold mb-0" style="color:{{ $card['color'] }};">{{ $card['value'] }}</h3>
                </div>
            </div>
        </div>
        @if($card['link'])</a>@endif
    </div>
    @endforeach
</div>

{{-- ── Charts Row ──────────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">

    {{-- Doughnut: Cases by Status --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="mb-0 fw-semibold">Cases by Status</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">{{ $totalCases }} total cases</p>
                </div>
                <i class="bi bi-pie-chart text-muted opacity-50 fs-4"></i>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                @if($totalCases > 0)
                <div style="position:relative;width:200px;height:200px;">
                    <canvas id="donutChart"></canvas>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                        <div class="fw-bold" style="font-size:1.6rem;line-height:1;color:#212529;">{{ $totalCases }}</div>
                        <div style="font-size:.65rem;color:#adb5bd;text-transform:uppercase;letter-spacing:.06em;">Total</div>
                    </div>
                </div>
                {{-- Legend --}}
                <div class="mt-4 w-100" style="display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;">
                    @foreach($casesByStatus as $status => $count)
                    @php $cfg = $statusConfig[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d']; @endphp
                    <a href="{{ route('admin.cases.index') }}?status={{ $status }}" class="text-decoration-none"
                       style="display:flex;align-items:center;gap:6px;font-size:.76rem;color:#495057;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $cfg['color'] }};flex-shrink:0;"></span>
                        <span class="text-truncate">{{ $cfg['label'] }}</span>
                        <span class="ms-auto fw-semibold text-dark">{{ $count }}</span>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-pie-chart" style="font-size:2.5rem;opacity:.2;"></i>
                    <p class="mt-3 small mb-0">No cases yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Line: Cases over last 30 days --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="mb-0 fw-semibold">Cases Created</h6>
                    <p class="text-muted mb-0" style="font-size:.72rem;">Last 30 days</p>
                </div>
                <span class="badge" style="background:#4361ee1a;color:#4361ee;font-size:.72rem;">
                    {{ array_sum($trendCounts) }} new
                </span>
            </div>
            <div class="card-body" style="padding:20px 16px 12px;">
                <canvas id="trendChart" style="width:100%;max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Clinician Workload Chart ─────────────────────────────────────── --}}
@if($casesByClinician->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
        <div>
            <h6 class="mb-0 fw-semibold">Clinician Workload</h6>
            <p class="text-muted mb-0" style="font-size:.72rem;">Active cases per clinician (excluding completed &amp; cancelled)</p>
        </div>
        <a href="{{ route('admin.clinicians.index') }}" class="btn btn-sm btn-outline-primary">All Clinicians</a>
    </div>
    <div class="card-body" style="padding:20px 24px;">
        <canvas id="clinicianChart" style="width:100%;max-height:{{ max(160, $casesByClinician->count() * 40) }}px;"></canvas>
    </div>
</div>
@endif

{{-- ── Recent Cases Table ──────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
        <div>
            <h6 class="mb-0 fw-semibold">Recent Cases</h6>
            <p class="text-muted mb-0" style="font-size:.72rem;">Latest 10 submitted cases</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-primary">All Cases</a>
            <a href="{{ route('admin.patients.index') }}" class="btn btn-sm btn-outline-secondary">Patients</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead style="background:#f8f9fc;">
                    <tr>
                        <th class="ps-4 py-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Patient</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Partner</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Clinician</th>
                        <th class="py-3"       style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Status</th>
                        <th class="pe-4 py-3"  style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#adb5bd;font-weight:600;">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentCases as $case)
                    <tr style="cursor:pointer;" onclick="window.location='{{ route('admin.cases.show', $case->uuid) }}'">
                        <td class="ps-4 py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                                     style="width:32px;height:32px;background:#4361ee1a;color:#4361ee;font-size:.72rem;">
                                    {{ strtoupper(substr($case->patient->full_name ?? 'P', 0, 1)) }}
                                </div>
                                <span class="fw-medium">{{ $case->patient->full_name ?? '—' }}</span>
                            </div>
                        </td>
                        <td>{{ $case->partner->name ?? '—' }}</td>
                        <td>{!! $case->clinician?->full_name ?? '<span class="text-muted small">Unassigned</span>' !!}</td>
                        <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                        <td class="pe-4 text-muted">{{ $case->created_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No cases yet.</td></tr>
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

    // ── Clinician workload horizontal bar ───────────────────────────
    const clinicianEl = document.getElementById('clinicianChart');
    if (clinicianEl) {
        const cData = @json($casesByClinician);
        const cNames  = cData.map(r => r.name);
        const cCounts = cData.map(r => r.count);
        const maxLoad = Math.max(...cCounts, 1);

        // Generate shades from indigo → violet across the bars
        const palette = cNames.map((_, i) => {
            const ratio = cNames.length > 1 ? i / (cNames.length - 1) : 0;
            const r = Math.round(67  + ratio * (124 - 67));
            const g = Math.round(97  + ratio * (58  - 97));
            const b = Math.round(238 + ratio * (237 - 238));
            return `rgba(${r},${g},${b},0.85)`;
        });

        new Chart(clinicianEl, {
            type: 'bar',
            data: {
                labels: cNames,
                datasets: [{
                    label:           'Active Cases',
                    data:            cCounts,
                    backgroundColor: palette,
                    borderRadius:    6,
                    borderSkipped:   false,
                    barThickness:    24,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: maxLoad + 1,
                        grid:   { color: '#f1f3f5', drawBorder: false },
                        border: { display: false },
                        ticks:  { color: '#adb5bd', font: { size: 11 }, precision: 0, maxTicksLimit: 6 }
                    },
                    y: {
                        grid:   { display: false },
                        border: { display: false },
                        ticks:  { color: '#495057', font: { size: 12, weight: '500' } }
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
                            title: items => items[0].label,
                            label: ctx  => ` ${ctx.parsed.x} active case${ctx.parsed.x !== 1 ? 's' : ''}`
                        }
                    }
                },
                animation: { duration: 900, easing: 'easeInOutQuart' }
            }
        });
    }

    // ── Doughnut ────────────────────────────────────────────────────
    const donutEl = document.getElementById('donutChart');
    if (donutEl) {
        new Chart(donutEl, {
            type: 'doughnut',
            data: {
                labels: @json($donutLabels),
                datasets: [{
                    data:            @json($donutData),
                    backgroundColor: @json($donutColors),
                    borderWidth:     3,
                    borderColor:     '#ffffff',
                    hoverOffset:     6,
                }]
            },
            options: {
                cutout: '72%',
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} cases`
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 800,
                    easing: 'easeInOutQuart',
                }
            }
        });
    }

    // ── Trend line ──────────────────────────────────────────────────
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        const labels = @json($trendLabels);
        const counts = @json($trendCounts);
        const max    = Math.max(...counts, 1);

        new Chart(trendEl, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label:           'Cases',
                    data:            counts,
                    borderColor:     '#4361ee',
                    borderWidth:     2.5,
                    pointRadius:     counts.map(v => v > 0 ? 4 : 0),
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#4361ee',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill:            true,
                    backgroundColor: (ctx) => {
                        const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
                        g.addColorStop(0,   'rgba(67,97,238,.18)');
                        g.addColorStop(1,   'rgba(67,97,238,0)');
                        return g;
                    },
                    tension:         0.42,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        grid:   { display: false },
                        border: { display: false },
                        ticks: {
                            color:    '#adb5bd',
                            font:     { size: 10 },
                            maxTicksLimit: 10,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: max + 1,
                        grid:   { color: '#f1f3f5', drawBorder: false },
                        border: { display: false, dash: [4,4] },
                        ticks: {
                            color:     '#adb5bd',
                            font:      { size: 10 },
                            precision: 0,
                            maxTicksLimit: 5,
                        }
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
                            title: items => items[0].label,
                            label: ctx  => ` ${ctx.parsed.y} case${ctx.parsed.y !== 1 ? 's' : ''}`
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
