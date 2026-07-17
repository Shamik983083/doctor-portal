@extends('layouts.ma-portal')

@section('title', 'MA Portal · Super Admin')
@section('page-title', 'MA Portal — Super Admin')

@section('content')
<x-ma-styles />
<div class="ma-surface">

    <div class="card" style="margin-bottom:1rem">
        <div class="card-body"><div class="ma-eyebrow">MA-DOCPORTAL role view</div><div class="ma-title">Super Admin</div>
        <div class="ma-sub">Cross-tenant / global control. Audit activity and pipeline signals are functional; multi-tenancy, feature flags and full observability are MA-preview (backend-heavy). MEDAXIS is a single-install, partner-based system.</div></div>
    </div>

    {{-- State visit requirement matrix (preview) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">State visit policy</div><div class="ma-title">State visit requirement matrix</div><div class="ma-sub">One cell per state and scope (ALL / CATEGORY / PROGRAM / PRODUCT), each versioned and effective-dated. When more than one active cell matches, the <strong>strictest active cell wins</strong>. Active versions are immutable; only a Super Admin activates a draft and every activation is audited.</div></div><x-ma-preview-tag /></div>
        <div class="card-body">
            <div class="ma-matrix">
                <div class="ma-matrix-head"><span>State · Scope</span><span>Version</span><span>Requirement</span><span>Effective · Compliance</span></div>
                @foreach($stateMatrix as $cell)
                <div class="ma-matrix-row">
                    <div><strong>{{ $cell['state'] }}</strong><span class="mx-detail">{{ $cell['scope'] }} · {{ $cell['detail'] }}</span></div>
                    <span class="ma-pill {{ $cell['status'] === 'ACTIVE' ? 'accent' : 'neutral' }}">{{ $cell['version'] }} {{ $cell['status'] }}</span>
                    <span class="ma-pill {{ $cell['video'] ? 'yellow' : 'green' }}">{{ $cell['video'] ? 'Video required' : 'No requirement' }}</span>
                    <span class="ma-matrix-meta">{{ $cell['effective'] }}<br>Compliance ref {{ $cell['ref'] }}</span>
                </div>
                @endforeach
            </div>
            <div class="d-flex gap-2 mt-3"><button class="btn btn-sm btn-outline-primary" disabled title="Preview only">View version history</button><button class="btn btn-sm btn-primary" disabled title="Preview only — activation is audited in MA">Activate draft (audited)</button></div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Protocol category coverage (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Clinical rules</div><div class="ma-title">Protocol category coverage</div><div class="ma-sub">Coverage across the real offering catalog categories. &ldquo;Needs review&rdquo; flags a category with no offerings mapped.</div></div>
            <div class="card-body">
                <div class="ma-coverage-list">
                    @forelse($categoryCoverage as $cat)
                    <div class="ma-coverage-row"><div><strong>{{ $cat['name'] }}</strong><span class="cov-detail">{{ $cat['count'] }} offering{{ $cat['count'] === 1 ? '' : 's' }}</span></div><span class="ma-pill {{ $cat['status'] === 'Configured' ? 'green' : 'yellow' }}">{{ $cat['status'] }}</span></div>
                    @empty
                    <p class="text-muted mb-0">No offering categories defined yet.</p>
                    @endforelse
                </div>
            </div>
        </div></div>

        {{-- Global user / role administration (functional) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Access · global</div><div class="ma-title">User &amp; role administration</div><div class="ma-sub">Every user across the install with their assigned roles. In MA this is scoped by tenant; here it is the single-install roster.</div></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Roles</th></tr></thead><tbody>
                @forelse($allUsers as $u)
                <tr><td><strong>{{ $u->name }}</strong></td><td><small class="text-muted">{{ $u->email }}</small></td><td>@forelse($u->getRoleNames() as $r)<span class="ma-pill accent">{{ ucfirst($r) }}</span> @empty<span class="ma-pill neutral">none</span>@endforelse</td></tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted py-4">No users.</td></tr>
                @endforelse
            </tbody></table></div></div>
        </div></div>
    </div>

    {{-- Tenants & storefronts (preview — partners shown as tenants) --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Platform</div><div class="ma-title">Tenants &amp; storefronts</div><div class="ma-sub">In MA each tenant is isolated by Postgres row-level security. Here partners stand in for tenants.</div></div><x-ma-preview-tag /></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Tenant / partner</th><th>Offerings</th><th>Clinicians</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($tenants as $t)
                <tr><td><strong>{{ $t['name'] }}</strong></td><td>{{ $t['offerings'] }}</td><td>{{ $t['clinicians'] }}</td><td><span class="ma-pill {{ $t['status'] === 'active' ? 'green' : 'yellow' }}">{{ ucfirst($t['status']) }}</span></td></tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No partners.</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="row g-3">
        {{-- Audit activity (functional) --}}
        <div class="col-lg-7"><div class="card h-100">
            <div class="card-header"><div class="ma-eyebrow">Audit &amp; control</div><div class="ma-title">Audit activity</div><div class="ma-sub">Every state change writes an event. In MA the audit trail is immutable and PHI-free.</div></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Actor</th><th>When</th></tr></thead><tbody>
                @forelse($auditEvents as $e)
                <tr><td><code class="audit-verb">{{ $e->event_type }}</code></td><td>{{ ucfirst($e->actor_type ?? 'system') }}</td><td>{{ $e->created_at?->diffForHumans() }}</td></tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted py-4">No events yet.</td></tr>
                @endforelse
            </tbody></table></div></div>
        </div></div>

        {{-- System observability (partly functional) --}}
        <div class="col-lg-5"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Reliability</div><div class="ma-title">System observability</div></div><x-ma-preview-tag /></div>
            <div class="card-body">
                <div class="ma-obs-grid">
                    <div><strong><span class="ma-health-dot {{ $observability['dlq'] > 0 ? 'red' : 'green' }}"></span>{{ $observability['dlq'] }}</strong><span>Webhook dead-letter</span></div>
                    <div><strong><span class="ma-health-dot {{ $observability['pending'] > 5 ? 'yellow' : 'green' }}"></span>{{ $observability['pending'] }}</strong><span>Pending deliveries</span></div>
                    <div><strong><span class="ma-health-dot green"></span>{{ $observability['partners'] }}</strong><span>Active partners</span></div>
                    <div><strong><span class="ma-health-dot green"></span>820ms</strong><span>Dispatch p95 (sample)</span></div>
                </div>
                <div class="ma-sub mt-2">Dead-letter and pending counts are live from the webhook queue. Full outbox depth and dispatch-latency metrics are the MA observability build.</div>
            </div>
        </div></div>
    </div>

    <div class="row g-3">
        {{-- Feature flags (preview) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Configuration</div><div class="ma-title">Feature flags</div></div><x-ma-preview-tag /></div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between"><span>Direct LifeFile dispatch <small class="text-muted">· per-tenant</small></span><span class="ma-pill neutral">Off</span></div>
                    <div class="d-flex justify-content-between"><span>AI note drafts <small class="text-muted">· per-tenant</small></span><span class="ma-pill neutral">Off</span></div>
                    <div class="d-flex justify-content-between"><span>Provider pool claim <small class="text-muted">· global</small></span><span class="ma-pill green">Enabled</span></div>
                    <div class="d-flex justify-content-between"><span>Batch review <small class="text-muted">· global</small></span><span class="ma-pill green">Enabled</span></div>
                </div>
            </div>
        </div></div>

        {{-- Integration health (preview) --}}
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><div><div class="ma-eyebrow">Integrations</div><div class="ma-title">Integration health</div></div><x-ma-preview-tag /></div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between"><span>VRIO dispatch</span><span class="ma-pill yellow">Mock adapter</span></div>
                    <div class="d-flex justify-content-between"><span>LifeFile direct</span><span class="ma-pill neutral">Disabled · sandbox</span></div>
                    <div class="d-flex justify-content-between"><span>Identity proofing</span><span class="ma-pill neutral">Not connected</span></div>
                    <div class="d-flex justify-content-between"><span>Cognito auth</span><span class="ma-pill neutral">Adapter seam</span></div>
                </div>
            </div>
        </div></div>
    </div>
</div>
@endsection
