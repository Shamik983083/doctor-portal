@extends('layouts.ma-portal')

@section('title', 'MA Portal · Admin')
@section('page-title', 'MA Portal — Admin')

@section('content')
<x-ma-styles />
<div class="ma-surface">

    <div class="card" style="margin-bottom:1rem">
        <div class="card-body"><div class="ma-eyebrow">MA-DOCPORTAL role view</div><div class="ma-title">Admin</div>
        <div class="ma-sub">Tenant-scoped operations. Users, offerings, providers and webhooks are functional from real data; the routing engine and primary-source credentialing are MA-preview (backend-heavy).</div></div>
    </div>

    {{-- Storefront workload (functional) --}}
    <div class="card">
        <div class="card-header"><div class="ma-eyebrow">Operations</div><div class="ma-title">Storefront workload</div><div class="ma-sub">Open-case load and triage mix per partner storefront.</div></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Storefront</th><th>Status</th><th>Open</th><th>Green</th><th>Yellow</th><th>Red</th></tr></thead>
            <tbody>
                @forelse($storefronts as $s)
                <tr><td><strong>{{ $s['name'] }}</strong></td><td><span class="ma-pill {{ $s['status'] === 'active' ? 'green' : 'neutral' }}">{{ ucfirst($s['status']) }}</span></td><td>{{ $s['open'] }}</td><td><span class="ma-pill green">{{ $s['green'] }}</span></td><td><span class="ma-pill yellow">{{ $s['yellow'] }}</span></td><td><span class="ma-pill red">{{ $s['red'] }}</span></td></tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No storefronts.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="row g-3">
        {{-- Weighted provider load (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Capacity &amp; routing</div><div class="ma-title">Weighted provider load</div><div class="ma-sub">Active cases against each provider's daily cap. Eligibility hard-blocks are never bypassed by capacity.</div></div>
            <div class="card-body">
                <div class="ma-provider-load">
                    @forelse($providerLoads as $pl)
                    <div>
                        <span class="pl-name">{{ $pl['name'] }}</span>
                        <span class="pl-num">{{ $pl['active'] }} / {{ $pl['cap'] ?: '—' }} · {{ $pl['percent'] }}%</span>
                        <div class="ma-load-track {{ $pl['percent'] >= 85 ? 'warning' : '' }}"><span style="width: {{ $pl['percent'] }}%"></span></div>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No providers on the roster.</p>
                    @endforelse
                </div>
            </div>
        </div></div>

        {{-- Exception center (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Exception center</div><div class="ma-title">Cases needing operational follow-up</div><div class="ma-sub">Each bucket maps to a real workflow condition — never a free-text status.</div></div>
            <div class="card-body">
                <div class="ma-stat-grid">
                    @foreach($exceptions as $ex)
                    <div><strong>{{ $ex['count'] }}</strong><span>{{ $ex['label'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </div></div>
    </div>

    <div class="row g-3">
        {{-- Operational report (functional) --}}
        <div class="col-lg-7"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Reporting</div><div class="ma-title">Operational report</div><div class="ma-sub">Derived from real case timestamps — TTFR, TTD, approval rate, throughput. Shows &ldquo;—&rdquo; until enough decisions exist.</div></div></div>
            <div class="card-body">
                <div class="ma-stat-grid">
                    @foreach($report as $stat)
                    <div><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </div></div>

        {{-- Triage volume chart (simple functional) --}}
        <div class="col-lg-5"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Reports &amp; charts</div><div class="ma-title">Open volume by triage</div></div><x-ma-preview-tag /></div>
            <div class="card-body">
                <div class="ma-barchart">
                    @foreach($triageVolume as $tv)
                    <div>
                        <span class="ma-pill {{ $tv['tone'] }}"><span class="ma-dot"></span>{{ $tv['label'] }}</span>
                        <div class="ma-bar {{ $tv['tone'] }}"><span style="width: {{ (int) round($tv['count'] / $triageVolumeMax * 100) }}%"></span></div>
                        <span class="bc-val">{{ $tv['count'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="ma-sub mt-2">Live open-case counts. Trend history over time is the MA reporting build.</div>
            </div>
        </div></div>
    </div>

    <div class="row g-3">
        {{-- Users & roles (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Access</div><div class="ma-title">Users &amp; roles</div></div><button class="btn btn-sm btn-outline-primary">Invite user</button></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Roles</th></tr></thead><tbody>
                @foreach($users as $u)
                <tr><td><strong>{{ $u->name }}</strong></td><td>@foreach($u->getRoleNames() as $r)<span class="ma-pill accent">{{ ucfirst($r) }}</span> @endforeach</td></tr>
                @endforeach
            </tbody></table></div></div>
        </div></div>

        {{-- Offerings & approval (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Catalog</div><div class="ma-title">Offerings &amp; approval</div></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Offering</th><th>Category</th><th>Approval</th></tr></thead><tbody>
                @foreach($offerings as $o)
                @php $st = $o->approval_status ?? ($o->is_active ? 'approved' : 'draft'); @endphp
                <tr><td><strong>{{ $o->name }}</strong>@if($o->is_controlled_substance)<span class="ma-pill red" title="Controlled substance">CS</span>@endif</td><td>{{ $o->category->name ?? '—' }}</td><td><span class="ma-pill {{ $st === 'approved' ? 'green' : ($st === 'rejected' ? 'red' : 'yellow') }}">{{ ucfirst($st) }}</span></td></tr>
                @endforeach
            </tbody></table></div></div>
        </div></div>
    </div>

    <div class="row g-3">
        {{-- Provider roster + credentialing (roster functional, PSV preview) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Providers</div><div class="ma-title">Roster &amp; credentialing</div><div class="ma-sub">Roster is live; primary-source verification (NPPES / state board / DEA / OIG-LEIE) is the MA regulatory build.</div></div><x-ma-preview-tag /></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Provider</th><th>States</th><th>Available</th></tr></thead><tbody>
                @forelse($clinicians as $c)
                <tr><td><strong>{{ optional($c->user)->name ?? 'Clinician' }}</strong><br><small class="text-muted">NPI {{ $c->npi ?? '—' }}</small></td><td>{{ is_array($c->licensed_states) ? implode(', ', array_slice($c->licensed_states, 0, 5)) : ($c->license_state ?? '—') }}</td><td><span class="ma-pill {{ $c->is_available ? 'green' : 'neutral' }}">{{ $c->is_available ? 'Available' : 'Off' }}</span></td></tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted py-4">No clinicians.</td></tr>
                @endforelse
            </tbody></table></div></div>
        </div></div>

        {{-- Webhooks & delivery log (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Integrations</div><div class="ma-title">Webhooks &amp; delivery log</div><div class="ma-sub">Outbound events with retry/backoff and a dead-letter path.</div></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Status</th><th>Code</th><th>When</th></tr></thead><tbody>
                @forelse($deliveries as $d)
                <tr><td><code class="audit-verb">{{ $d->event_type }}</code></td><td><span class="ma-pill {{ $d->status === 'delivered' ? 'green' : ($d->status === 'failed' ? 'red' : 'yellow') }}">{{ ucfirst($d->status) }}</span></td><td>{{ $d->response_code ?? '—' }}</td><td>{{ $d->created_at?->diffForHumans() }}</td></tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No webhook deliveries yet. {{ $webhooks->count() }} subscription(s) configured.</td></tr>
                @endforelse
            </tbody></table></div></div>
        </div></div>
    </div>

    {{-- Integrations config (functional) --}}
    <div class="card">
        <div class="card-header"><div class="ma-eyebrow">Integrations</div><div class="ma-title">Integrations config</div><div class="ma-sub">Configured partner webhook endpoints and their subscribed events. The delivery log above shows the runtime attempts.</div></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Partner</th><th>Endpoint</th><th>Event</th><th>Status</th></tr></thead><tbody>
            @forelse($webhooks as $w)
            <tr>
                <td><strong>{{ optional($w->partner)->name ?? 'Partner #'.$w->partner_id }}</strong></td>
                <td><code>{{ \Illuminate\Support\Str::limit($w->url, 48) }}</code></td>
                <td><span class="ma-pill neutral">{{ $w->event_type ?? 'all' }}</span></td>
                <td><span class="ma-pill {{ ($w->status ?? 'active') === 'active' ? 'green' : 'neutral' }}">{{ ucfirst($w->status ?? 'active') }}</span></td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No webhook subscriptions configured.</td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>

    {{-- Routing policy (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Capacity &amp; routing</div><div class="ma-title">Routing policy</div><div class="ma-sub">MA assigns across eligible providers by one of four modes (weighted, intelligent, round-robin, provider pool) with per-provider caps and locked eligibility hard-blocks. MEDAXIS auto-assign is simpler.</div></div><x-ma-preview-tag /></div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <span class="ma-pill green">Weighted capacity · active</span>
                <span class="ma-pill neutral">Intelligent match</span>
                <span class="ma-pill neutral">Round robin</span>
                <span class="ma-pill neutral">Provider pool (claim)</span>
            </div>
            <div class="ma-sub mt-2">Eligibility hard-blocks (license state, concurrency cap, workflow hold) are server-enforced and never bypassed by any routing setting. In production this is a versioned, audited configuration.</div>
        </div>
    </div>
</div>
@endsection
