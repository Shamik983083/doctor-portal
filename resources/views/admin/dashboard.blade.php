@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')
<x-ma-styles />
<div class="ma-surface">

@php
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

    {{-- Metric row --}}
    <div class="ma-metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); margin-bottom:1rem;">
        <a href="{{ route('admin.partners.index') }}" class="text-decoration-none">
            <div class="ma-metric"><div class="ma-metric-label">Partners</div><div class="ma-metric-value">{{ $stats['partners'] }}</div></div>
        </a>
        <a href="{{ route('admin.patients.index') }}" class="text-decoration-none">
            <div class="ma-metric"><div class="ma-metric-label">Patients</div><div class="ma-metric-value">{{ $stats['patients'] }}</div></div>
        </a>
        <a href="{{ route('admin.cases.index') }}" class="text-decoration-none">
            <div class="ma-metric accent"><div class="ma-metric-label">Active Cases</div><div class="ma-metric-value">{{ $stats['active_cases'] }}</div></div>
        </a>
        <a href="{{ route('admin.clinicians.index') }}" class="text-decoration-none">
            <div class="ma-metric"><div class="ma-metric-label">Clinicians</div><div class="ma-metric-value">{{ $stats['clinicians'] }}</div></div>
        </a>
        <div class="ma-metric"><div class="ma-metric-label">Orders Today</div><div class="ma-metric-value">{{ $stats['orders_today'] }}</div></div>
        <a href="{{ route('admin.cases.index') }}?status=completed" class="text-decoration-none">
            <div class="ma-metric"><div class="ma-metric-label">Completed</div><div class="ma-metric-value">{{ $stats['completed_today'] }}</div></div>
        </a>
    </div>

    {{-- Storefront workload --}}
    <div class="card">
        <div class="card-header">
            <div class="ma-eyebrow">Operations</div>
            <div class="ma-title">Storefront workload</div>
            <div class="ma-sub">Open-case load and triage mix per partner storefront.</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Storefront</th><th>Status</th><th>Open</th><th>Green</th><th>Yellow</th><th>Red</th></tr></thead>
                    <tbody>
                        @forelse($storefronts as $s)
                        <tr>
                            <td><strong>{{ $s['name'] }}</strong></td>
                            <td><span class="ma-pill {{ $s['status'] === 'active' ? 'green' : 'neutral' }}">{{ ucfirst($s['status']) }}</span></td>
                            <td>{{ $s['open'] }}</td>
                            <td><span class="ma-pill green">{{ $s['green'] }}</span></td>
                            <td><span class="ma-pill yellow">{{ $s['yellow'] }}</span></td>
                            <td><span class="ma-pill red">{{ $s['red'] }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No storefronts configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Provider load --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="ma-eyebrow">Capacity &amp; routing</div>
                    <div class="ma-title">Weighted provider load</div>
                    <div class="ma-sub">Active cases vs each provider's daily cap. &ge;85% shown in red.</div>
                </div>
                <div class="card-body">
                    <div class="ma-provider-load">
                        @forelse($providerLoads as $pl)
                        <div>
                            <span class="pl-name">{{ $pl['name'] }}</span>
                            <span class="pl-num">{{ $pl['active'] }} / {{ $pl['cap'] ?: '—' }} · {{ $pl['percent'] }}%</span>
                            <div class="ma-load-track {{ $pl['percent'] >= 85 ? 'warning' : '' }}">
                                <span style="width: {{ $pl['percent'] }}%"></span>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted mb-0">No providers on the roster.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Exception center --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <div class="ma-eyebrow">Exception center</div>
                    <div class="ma-title">Cases needing follow-up</div>
                    <div class="ma-sub">Each bucket maps to a real workflow condition.</div>
                </div>
                <div class="card-body">
                    <div class="ma-stat-grid">
                        @foreach($exceptions as $ex)
                        <div>
                            <strong class="{{ $ex['count'] > 0 ? ($ex['tone'] === 'red' ? 'text-danger' : 'text-warning') : '' }}">{{ $ex['count'] }}</strong>
                            <span>{{ $ex['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Operational report --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <div class="ma-eyebrow">Reporting</div>
                    <div class="ma-title">Operational report</div>
                    <div class="ma-sub">Derived from real case timestamps. Shows "—" until enough decisions exist.</div>
                </div>
                <div class="card-body">
                    <div class="ma-stat-grid">
                        @foreach($report as $stat)
                        <div><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Triage volume bar chart --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <div class="ma-eyebrow">Reports &amp; charts</div>
                    <div class="ma-title">Open volume by triage</div>
                </div>
                <div class="card-body">
                    <div class="ma-barchart">
                        @foreach($triageVolume as $tv)
                        <div>
                            <span class="ma-pill {{ $tv['tone'] }}"><span class="ma-dot"></span>{{ $tv['label'] }}</span>
                            <div class="ma-bar {{ $tv['tone'] }}"><span style="width: {{ $triageVolumeMax > 0 ? (int) round($tv['count'] / $triageVolumeMax * 100) : 0 }}%"></span></div>
                            <span class="bc-val">{{ $tv['count'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div class="ma-sub mt-2">Live open-case counts by triage band.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Cases by status (doughnut) --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ma-eyebrow">Pipeline</div>
                        <div class="ma-title">Cases by status</div>
                        <div class="ma-sub">{{ $totalCases }} total cases</div>
                    </div>
                    <i class="bi bi-pie-chart text-muted opacity-50 fs-4"></i>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                    @if($totalCases > 0)
                    <div style="position:relative;width:180px;height:180px;">
                        <canvas id="donutChart"></canvas>
                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                            <div class="fw-bold" style="font-size:1.5rem;line-height:1;">{{ $totalCases }}</div>
                            <div style="font-size:.65rem;color:#adb5bd;text-transform:uppercase;letter-spacing:.06em;">Total</div>
                        </div>
                    </div>
                    <div class="mt-3 w-100" style="display:grid;grid-template-columns:1fr 1fr;gap:5px 10px;">
                        @foreach($casesByStatus as $status => $count)
                        @php $cfg = $statusConfig[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d']; @endphp
                        <a href="{{ route('admin.cases.index') }}?status={{ $status }}" class="text-decoration-none"
                           style="display:flex;align-items:center;gap:6px;font-size:.74rem;color:#495057;">
                            <span style="width:9px;height:9px;border-radius:50%;background:{{ $cfg['color'] }};flex-shrink:0;"></span>
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

        {{-- Cases over last 30 days (trend line) --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <div class="ma-eyebrow">Reporting</div>
                        <div class="ma-title">Cases created</div>
                        <div class="ma-sub">Last 30 days</div>
                    </div>
                    <span class="ma-pill accent">{{ array_sum($trendCounts) }} new</span>
                </div>
                <div class="card-body" style="padding:20px 16px 12px;">
                    <canvas id="trendChart" style="width:100%;max-height:200px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Clinician workload --}}
    @if($casesByClinician->isNotEmpty())
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <div class="ma-eyebrow">Providers</div>
                <div class="ma-title">Clinician workload</div>
                <div class="ma-sub">Active cases per clinician (excluding completed &amp; cancelled).</div>
            </div>
            <a href="{{ route('admin.clinicians.index') }}" class="btn btn-sm btn-outline-primary">All clinicians</a>
        </div>
        <div class="card-body" style="padding:20px 24px;">
            <canvas id="clinicianChart" style="width:100%;max-height:{{ max(160, $casesByClinician->count() * 40) }}px;"></canvas>
        </div>
    </div>
    @endif

    {{-- Webhook delivery log --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <div class="ma-eyebrow">Integrations</div>
                <div class="ma-title">Webhook delivery log</div>
                <div class="ma-sub">Outbound events with retry/backoff and a dead-letter path.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($webhookFailedCount > 0)
                    <span class="ma-pill red">{{ $webhookFailedCount }} failed</span>
                @endif
                <a href="{{ route('admin.webhooks.index') }}" class="btn btn-sm btn-outline-primary">Full log</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Event</th><th>Status</th><th>Code</th><th>When</th></tr></thead>
                    <tbody>
                        @forelse($webhookDeliveries as $d)
                        <tr>
                            <td><code style="font-size:.78rem;">{{ $d->event_type }}</code></td>
                            <td><span class="ma-pill {{ $d->status === 'delivered' ? 'green' : ($d->status === 'failed' ? 'red' : 'yellow') }}">{{ ucfirst($d->status) }}</span></td>
                            <td>{{ $d->response_code ?? '—' }}</td>
                            <td><small class="text-muted">{{ $d->created_at?->diffForHumans() }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No webhook deliveries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent cases --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <div class="ma-eyebrow">Case management</div>
                <div class="ma-title">Recent cases</div>
                <div class="ma-sub">Latest 10 submitted cases.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.cases.index') }}" class="btn btn-sm btn-outline-primary">All cases</a>
                <a href="{{ route('admin.patients.index') }}" class="btn btn-sm btn-outline-secondary">Patients</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Partner</th>
                            <th>Clinician</th>
                            <th>Triage</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentCases as $case)
                        <tr style="cursor:pointer;" onclick="window.location='{{ route('admin.cases.show', $case->uuid) }}'">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                                         style="width:30px;height:30px;background:var(--ma-accent-bg);color:var(--ma-accent-ink);font-size:.7rem;">
                                        {{ strtoupper(substr($case->patient?->full_name ?? 'P', 0, 1)) }}
                                    </div>
                                    <span class="fw-medium">{{ $case->patient?->full_name ?? '—' }}</span>
                                </div>
                            </td>
                            <td><small>{{ $case->partner?->name ?? '—' }}</small></td>
                            <td>
                                @if($case->clinician)
                                    <small>{{ $case->clinician->full_name }}</small>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if($case->triage)
                                    <span class="ma-pill {{ $case->triage }}"><span class="ma-dot"></span>{{ ucfirst($case->triage) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><span class="badge badge-status-{{ $case->status }}">{{ ucfirst($case->status) }}</span></td>
                            <td><small class="text-muted">{{ $case->created_at->diffForHumans() }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No cases yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";

    // ── Clinician workload ───────────────────────────────────────────
    const clinicianEl = document.getElementById('clinicianChart');
    if (clinicianEl) {
        const cData   = @json($casesByClinician);
        const cNames  = cData.map(r => r.name);
        const cCounts = cData.map(r => r.count);
        const maxLoad = Math.max(...cCounts, 1);
        const palette = cNames.map((_, i) => {
            const ratio = cNames.length > 1 ? i / (cNames.length - 1) : 0;
            const r = Math.round(67  + ratio * (124 - 67));
            const g = Math.round(97  + ratio * (58  - 97));
            const b = Math.round(238 + ratio * (237 - 238));
            return `rgba(${r},${g},${b},0.85)`;
        });
        new Chart(clinicianEl, {
            type: 'bar',
            data: { labels: cNames, datasets: [{ label: 'Active Cases', data: cCounts, backgroundColor: palette, borderRadius: 6, borderSkipped: false, barThickness: 24 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, suggestedMax: maxLoad + 1, grid: { color: '#f1f3f5', drawBorder: false }, border: { display: false }, ticks: { color: '#adb5bd', font: { size: 11 }, precision: 0, maxTicksLimit: 6 } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: '#495057', font: { size: 12, weight: '500' } } }
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#212529', titleColor: '#fff', bodyColor: '#adb5bd', padding: 10, cornerRadius: 8, callbacks: { title: i => i[0].label, label: c => ` ${c.parsed.x} active case${c.parsed.x !== 1 ? 's' : ''}` } } },
                animation: { duration: 900, easing: 'easeInOutQuart' }
            }
        });
    }

    // ── Doughnut ────────────────────────────────────────────────────
    const donutEl = document.getElementById('donutChart');
    if (donutEl) {
        new Chart(donutEl, {
            type: 'doughnut',
            data: { labels: @json($donutLabels), datasets: [{ data: @json($donutData), backgroundColor: @json($donutColors), borderWidth: 3, borderColor: '#ffffff', hoverOffset: 6 }] },
            options: {
                cutout: '72%', responsive: true, maintainAspectRatio: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.label}: ${c.parsed} cases` } } },
                animation: { animateRotate: true, duration: 800, easing: 'easeInOutQuart' }
            }
        });
    }

    // ── Trend line ──────────────────────────────────────────────────
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        const counts = @json($trendCounts);
        const max    = Math.max(...counts, 1);
        new Chart(trendEl, {
            type: 'line',
            data: { labels: @json($trendLabels), datasets: [{ label: 'Cases', data: counts, borderColor: '#0d9488', borderWidth: 2.5, pointRadius: counts.map(v => v > 0 ? 4 : 0), pointHoverRadius: 6, pointBackgroundColor: '#0d9488', pointBorderColor: '#fff', pointBorderWidth: 2, fill: true, backgroundColor: ctx => { const g = ctx.chart.ctx.createLinearGradient(0,0,0,200); g.addColorStop(0,'rgba(13,148,136,.18)'); g.addColorStop(1,'rgba(13,148,136,0)'); return g; }, tension: 0.42 }] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#adb5bd', font: { size: 10 }, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, suggestedMax: max + 1, grid: { color: '#f1f3f5', drawBorder: false }, border: { display: false }, ticks: { color: '#adb5bd', font: { size: 10 }, precision: 0, maxTicksLimit: 5 } }
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#212529', titleColor: '#fff', bodyColor: '#adb5bd', padding: 10, cornerRadius: 8, callbacks: { title: i => i[0].label, label: c => ` ${c.parsed.y} case${c.parsed.y !== 1 ? 's' : ''}` } } },
                animation: { duration: 900, easing: 'easeInOutQuart' }
            }
        });
    }
})();
</script>
@endsection
