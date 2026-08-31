@extends('layouts.admin')

@section('content')
<div class="container-fluid px-4 py-4">
<div style="max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:2rem;">

{{-- ── Header ─────────────────────────────────────────── --}}
<div>
    <p style="font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;margin-bottom:.4rem;">
        MEDAXIS · EHR Integration
    </p>
    <h1 class="h3 mb-1 fw-bold">Healthie EHR — Go-Live Checklist</h1>
    <p class="text-muted mb-2" style="font-size:.9rem;">
        The integration is 83 % complete. Phases 1–5 are fully implemented and tested.
        This page documents exactly what remains to enable Healthie live on staging.
    </p>
    <div class="d-flex flex-wrap gap-3" style="font-size:.82rem;color:#6c757d;border-top:1px solid #dee2e6;padding-top:.65rem;">
        <span><i class="bi bi-layers me-1"></i>6 phases · 21 tasks</span>
        <span><i class="bi bi-check2-circle me-1" style="color:#16863f;"></i>17 tasks done</span>
        <span><i class="bi bi-circle me-1"></i>4 tasks remaining (Phase 6)</span>
    </div>
</div>

{{-- ── Progress bar ────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-size:.75rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#6c757d;">Overall Progress</span>
            <span style="font-size:1.3rem;font-weight:700;color:#16863f;font-variant-numeric:tabular-nums;">83%</span>
        </div>
        <div class="progress" style="height:8px;border-radius:99px;background:#dee2e6;">
            <div class="progress-bar" style="width:83%;background:#16863f;border-radius:99px;"></div>
        </div>
        <div class="d-flex gap-4 mt-2" style="font-size:.8rem;color:#6c757d;">
            <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#16863f;margin-right:.3rem;"></span>Phases 1–5 complete</span>
            <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#adb5bd;margin-right:.3rem;"></span>Phase 6 pending</span>
        </div>
    </div>
</div>

{{-- ── Tenant segregation rule ─────────────────────────── --}}
<div class="card border-0 border-start border-3 border-primary shadow-sm">
    <div class="card-body">
        <h6 class="text-primary fw-bold mb-2" style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;">
            Non-Negotiable — Tenant Segregation
        </h6>
        <p class="text-muted mb-2" style="font-size:.88rem;">
            Healthie data must never cross between storefronts. The same person coming through Partner A
            and Partner B is two separate records. Every EHR push uses the sending partner's own credential.
        </p>
        <ul class="list-unstyled mb-0" style="font-size:.84rem;color:#6c757d;">
            <li class="mb-1"><i class="bi bi-dash me-1"></i>Each partner pushes with its own Healthie API key — no global key, no fallback.</li>
            <li class="mb-1"><i class="bi bi-dash me-1"></i>Patient matching uses only the namespaced key <code>partner_uuid:patient_id</code> — never email, phone, or DOB.</li>
            <li><i class="bi bi-dash me-1"></i>Three hard refusals enforce this at build-time, push-time, and send-time in the adapter.</li>
        </ul>
    </div>
</div>

{{-- ── Phases 1–5 summary ──────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        Phases 1–5 — Already Implemented
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.85rem;">
            <thead class="table-light">
                <tr>
                    <th style="width:30%">Phase</th>
                    <th>What was built</th>
                    <th style="width:80px">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold">1 — Foundation</td>
                    <td class="text-muted">
                        <code>ehr_records</code> + <code>partner_ehr_settings</code> tables · <code>EhrRecord</code> + <code>PartnerEhrSetting</code> models ·
                        <code>EhrGatewayManager</code> with dual-flag gate · <code>MockEhrAdapter</code> · <code>config/ehr.php</code> · Admin partner forms (6 Healthie fields)
                    </td>
                    <td><span class="badge" style="background:#e8f5ed;color:#16863f;">Done</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">2 — Healthie Adapter</td>
                    <td class="text-muted">
                        <code>HealthieEhrAdapter</code>: auth headers, <code>findOrCreateClient()</code> (email query + <code>record_identifier</code> namespaced key verify),
                        <code>buildMutation()</code> (<code>createFormAnswerGroup</code> or <code>createNote</code>), <code>extractReference()</code> · 10 unit tests all passing
                    </td>
                    <td><span class="badge" style="background:#e8f5ed;color:#16863f;">Done</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">3 — Approval wiring</td>
                    <td class="text-muted">
                        <code>EhrRecordService.recordApproval()</code> called in all three approval paths:
                        <code>approve()</code>, <code>prescribeConfirm()</code>, <code>batchSubmit()</code>.
                        Failure never rolls back a clinical decision.
                    </td>
                    <td><span class="badge" style="background:#e8f5ed;color:#16863f;">Done</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">4 — Retry sweep</td>
                    <td class="text-muted">
                        <code>ehr:retry</code> Artisan command streams <code>failed</code> rows via <code>->lazy()</code> ·
                        Scheduled <code>everyFifteenMinutes()->withoutOverlapping()</code> in <code>routes/console.php</code>
                    </td>
                    <td><span class="badge" style="background:#e8f5ed;color:#16863f;">Done</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">5 — Admin visibility</td>
                    <td class="text-muted">
                        EHR Records list (filterable by partner/status/date) · Detail + payload inspector · Manual retry button ·
                        Sidebar link with live <span class="badge bg-danger" style="font-size:.65rem;">failed count</span> badge
                    </td>
                    <td><span class="badge" style="background:#e8f5ed;color:#16863f;">Done</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── Phase 6 — Pending tasks ──────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
        <span style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
            Phase 6 — Enable on Staging (4 pending tasks)
        </span>
        <span class="badge" style="background:#eceff5;color:#4a5570;font-size:.7rem;">Pending</span>
    </div>
    <div class="list-group list-group-flush">

        {{-- Task 1 --}}
        <div class="list-group-item border-0 py-3">
            <div class="d-flex align-items-start gap-3">
                <div style="width:22px;height:22px;border-radius:50%;background:#eceff5;border:1.5px solid #adb5bd;flex-shrink:0;margin-top:.1rem;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold" style="font-size:.9rem;">Configure pilot partner with staging credentials</span>
                        <span class="badge ms-2" style="background:#eceff5;color:#4a5570;font-size:.68rem;white-space:nowrap;">Pending</span>
                    </div>
                    <p class="text-muted mb-2 mt-1" style="font-size:.83rem;">
                        In Admin → Partners → Edit, fill all six Healthie fields for one pilot partner.
                        Leave both toggle flags <strong>off</strong> until the form is saved and confirmed.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr><th>Field (admin form)</th><th>Column</th><th>Value / Notes</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>healthie_api_key</code></td><td><code>api_key</code></td><td>Encrypted at rest. Get from Healthie dashboard → API keys.</td></tr>
                                <tr><td><code>healthie_endpoint</code></td><td><code>endpoint</code></td><td><code>https://staging-api.gethealthie.com/graphql</code></td></tr>
                                <tr><td><code>healthie_organization_id</code></td><td><code>organization_id</code></td><td>From Healthie org settings (used for cross-check only).</td></tr>
                                <tr><td><code>healthie_default_provider_id</code></td><td><code>default_provider_id</code></td><td>ID of the provider new clients are assigned to on create.</td></tr>
                                <tr><td><code>healthie_authorization_shard</code></td><td><code>authorization_shard</code></td><td>Only if the account is sharded. Leave blank otherwise.</td></tr>
                                <tr><td><code>healthie_note_form_id</code></td><td><code>note_form_id</code></td><td>Optional. If set → <code>createFormAnswerGroup</code>; blank → <code>createNote</code>.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Task 2 --}}
        <div class="list-group-item border-0 py-3" style="background:#f8f9fa;">
            <div class="d-flex align-items-start gap-3">
                <div style="width:22px;height:22px;border-radius:50%;background:#eceff5;border:1.5px solid #adb5bd;flex-shrink:0;margin-top:.1rem;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold" style="font-size:.9rem;">Set env vars on the staging server and flip the enable flags</span>
                        <span class="badge ms-2" style="background:#eceff5;color:#4a5570;font-size:.68rem;white-space:nowrap;">Pending</span>
                    </div>
                    <p class="text-muted mb-2 mt-1" style="font-size:.83rem;">
                        SSH into staging and update <code>.env</code>. Both platform flags must be <code>true</code> before
                        any real push will leave the server. Then flip the pilot partner's
                        <strong>Is Enabled</strong> and <strong>Sandbox Validated</strong> toggles in Admin.
                    </p>
                    <div class="bg-light border rounded p-3 mb-2" style="font-family:monospace;font-size:.8rem;line-height:1.8;">
                        <span class="text-muted"># --- SSH into staging ---</span><br>
                        <span class="text-muted"># Edit .env (nano, vim, etc.)</span><br>
                        <br>
                        <span style="color:#155724;">EHR_ENABLED</span>=<span style="color:#0f5132;">true</span><br>
                        <span style="color:#155724;">EHR_SANDBOX_VALIDATED</span>=<span style="color:#0f5132;">true</span><br>
                        <span style="color:#155724;">EHR_ADAPTER</span>=<span style="color:#0f5132;">healthie</span><br>
                        <span style="color:#155724;">EHR_MAX_ATTEMPTS</span>=<span style="color:#0f5132;">5</span><br>
                        <br>
                        <span class="text-muted"># Healthie staging endpoint (default in config/ehr.php)</span><br>
                        <span style="color:#155724;">HEALTHIE_ENDPOINT</span>=<span style="color:#0f5132;">https://staging-api.gethealthie.com/graphql</span><br>
                        <span style="color:#155724;">HEALTHIE_TIMEOUT</span>=<span style="color:#0f5132;">30</span><br>
                        <br>
                        <span class="text-muted"># Then reload config cache</span><br>
                        php artisan config:clear<br>
                        php artisan cache:clear
                    </div>
                    <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.8rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Also run migrations on staging</strong> if not already applied:
                        <code>php artisan migrate</code> — this creates the <code>ehr_records</code> and
                        <code>partner_ehr_settings</code> tables.
                    </div>
                </div>
            </div>
        </div>

        {{-- Task 3 --}}
        <div class="list-group-item border-0 py-3">
            <div class="d-flex align-items-start gap-3">
                <div style="width:22px;height:22px;border-radius:50%;background:#eceff5;border:1.5px solid #adb5bd;flex-shrink:0;margin-top:.1rem;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold" style="font-size:.9rem;">Verify tenant segregation — do not skip</span>
                        <span class="badge ms-2" style="background:#eceff5;color:#4a5570;font-size:.68rem;white-space:nowrap;">Pending</span>
                    </div>
                    <p class="text-muted mb-2 mt-1" style="font-size:.83rem;">
                        This failure is invisible until it has already happened in production. Run the check below before declaring the integration live.
                    </p>
                    <ol style="font-size:.84rem;color:#495057;padding-left:1.2rem;line-height:1.9;">
                        <li>Submit a case through <strong>Partner A</strong> with a test patient email.</li>
                        <li>Approve the case in the doctor portal → confirm one <code>sent</code> EHR record appears.</li>
                        <li>Submit a case through <strong>Partner B</strong> using the <em>same</em> test patient email.</li>
                        <li>Approve that case → confirm a second <code>sent</code> EHR record appears (different UUID).</li>
                        <li>In Healthie, verify that <strong>two separate client records</strong> exist, each with a distinct
                            <code>record_identifier</code> value matching
                            <code>partner_a_uuid:patient_id</code> and <code>partner_b_uuid:patient_id</code>.</li>
                        <li>Confirm that neither record's <code>record_identifier</code> appears in the other partner's org.</li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- Task 4 --}}
        <div class="list-group-item border-0 py-3" style="background:#f8f9fa;">
            <div class="d-flex align-items-start gap-3">
                <div style="width:22px;height:22px;border-radius:50%;background:#eceff5;border:1.5px solid #adb5bd;flex-shrink:0;margin-top:.1rem;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold" style="font-size:.9rem;">Roll out to additional partners</span>
                        <span class="badge ms-2" style="background:#eceff5;color:#4a5570;font-size:.68rem;white-space:nowrap;">Pending</span>
                    </div>
                    <p class="text-muted mb-0 mt-1" style="font-size:.83rem;">
                        Each additional partner is opt-in and independent. Fill credentials in Admin → Partners → Edit,
                        then flip <strong>Is Enabled</strong> and <strong>Sandbox Validated</strong> for that partner only.
                        One partner's credential never touches another partner's records — the architecture enforces this structurally.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ── How a push works ─────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        How a Push Works — Step by Step
    </div>
    <div class="card-body py-3 px-4">
        @php
        $steps = [
            ['Provider approves case', 'ClinicalNote is saved. Prescription decisions are recorded. Status → approved.'],
            ['EhrRecordService.recordApproval() is called', 'Idempotent on case_id + clinical_note_id. Payload built with whitelisted PHI fields and three cross-tenant refusals. If EHR push is disabled, row stored as disabled — nothing leaves the system.'],
            ['EhrGatewayManager resolves this partner\'s adapter', 'Requires both platform flags (EHR_ENABLED + EHR_SANDBOX_VALIDATED) AND the partner\'s isPushable(). No partner → no adapter. No shared credential possible.'],
            ['HealthieEhrAdapter.findOrCreateClient()', 'Queries Healthie users by email within partner\'s org. Verifies record_identifier = namespaced key. Not found → createClient with partner_uuid:patient_id in record_identifier.'],
            ['buildMutation() posts the note', 'createFormAnswerGroup if note_form_id is set (charting record); otherwise createNote. GraphQL errors at HTTP 200 are caught and treated as failures.'],
            ['EhrRecord updated with result', 'Status → sent or failed. Healthie reference ID stored. Failure never rolls back the approval. Retry sweep picks up failed rows every 15 minutes, up to max_attempts.'],
        ];
        @endphp
        @foreach($steps as $i => $s)
        <div class="d-flex align-items-start gap-3 {{ !$loop->last ? 'mb-3' : '' }}" style="position:relative;">
            @if(!$loop->last)
            <div style="position:absolute;left:13px;top:28px;bottom:-12px;width:1px;background:#dee2e6;"></div>
            @endif
            <div style="width:28px;height:28px;border-radius:50%;background:#fff;border:1px solid #dee2e6;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#6c757d;flex-shrink:0;z-index:1;">
                {{ $i + 1 }}
            </div>
            <div style="font-size:.86rem;padding-bottom:.5rem;">
                <strong>{{ $s[0] }}</strong>
                <p class="text-muted mb-0 mt-1" style="font-size:.81rem;line-height:1.55;">{{ $s[1] }}</p>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Mutation strategy ────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        Mutation Strategy
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
                <thead class="table-light">
                    <tr>
                        <th>Condition</th>
                        <th>Mutation used</th>
                        <th>Record type in Healthie</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>note_form_id</code> is set on partner settings</td>
                        <td><code>createFormAnswerGroup</code></td>
                        <td>Charting record tied to a form template · <code>finished + marked_locked</code></td>
                    </tr>
                    <tr>
                        <td><code>note_form_id</code> is blank</td>
                        <td><code>createNote</code></td>
                        <td>Plain text note on patient timeline · no form dependency</td>
                    </tr>
                    <tr>
                        <td>Client not found by email</td>
                        <td><code>createClient</code> (before note)</td>
                        <td>New Healthie client · namespaced key in <code>record_identifier</code> · <code>dont_send_welcome: true</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Code gaps (non-blocking) ─────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        Code Gaps — Non-Blocking, Nice to Have
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.84rem;">
            <thead class="table-light">
                <tr><th>Gap</th><th>Detail</th><th>Priority</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold">.env.example missing Healthie vars</td>
                    <td class="text-muted">
                        <code>EHR_ENABLED</code>, <code>EHR_SANDBOX_VALIDATED</code>, <code>EHR_ADAPTER</code>,
                        <code>HEALTHIE_ENDPOINT</code>, <code>HEALTHIE_TIMEOUT</code>, <code>EHR_MAX_ATTEMPTS</code>
                        are not documented in <code>.env.example</code>. Causes confusion on new server setup.
                    </td>
                    <td><span class="badge" style="background:#fff3cd;color:#856404;font-size:.68rem;">Low</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">No PartnerEhrSettingSeeder</td>
                    <td class="text-muted">
                        There is no seeder to auto-populate staging partner credentials.
                        Currently all six Healthie fields must be filled manually via the admin UI each time a fresh
                        environment is provisioned.
                    </td>
                    <td><span class="badge" style="background:#fff3cd;color:#856404;font-size:.68rem;">Low</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">No alert when retry budget exhausted</td>
                    <td class="text-muted">
                        When an EHR record reaches <code>max_attempts</code> it silently stays <code>failed</code>.
                        There is no email, Slack, or admin notification. The sidebar badge is the only signal.
                    </td>
                    <td><span class="badge" style="background:#fff3cd;color:#856404;font-size:.68rem;">Medium</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">No feature/integration test</td>
                    <td class="text-muted">
                        10 unit tests for tenant segregation all pass.
                        No end-to-end test that exercises <code>approve()</code> → <code>recordApproval()</code> → <code>graphql()</code> against a stub HTTP server.
                    </td>
                    <td><span class="badge" style="background:#fff3cd;color:#856404;font-size:.68rem;">Medium</span></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Docs out of date</td>
                    <td class="text-muted">
                        <code>docs/integrations/HEALTHIE-SETUP.md</code> states "<code>buildMutation()</code> throws
                        <code>RuntimeException</code>" — but the implementation is fully complete and has been for some time.
                        The docs were written before the code landed.
                    </td>
                    <td><span class="badge" style="background:#d1ecf1;color:#0c5460;font-size:.68rem;">Cleanup</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ── Env var quick reference ──────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        Environment Variable Reference
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:.83rem;">
                <thead class="table-light">
                    <tr><th>Variable</th><th>Default</th><th>Staging value</th><th>Purpose</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>EHR_ENABLED</code></td>
                        <td><code>false</code></td>
                        <td><code>true</code></td>
                        <td>Master switch. Off everywhere by default.</td>
                    </tr>
                    <tr>
                        <td><code>EHR_SANDBOX_VALIDATED</code></td>
                        <td><code>false</code></td>
                        <td><code>true</code></td>
                        <td>Second gate. Both flags must be true before a real push sends.</td>
                    </tr>
                    <tr>
                        <td><code>EHR_ADAPTER</code></td>
                        <td><code>mock</code></td>
                        <td><code>healthie</code></td>
                        <td><code>mock</code> = no network, safe. <code>healthie</code> = live GraphQL calls.</td>
                    </tr>
                    <tr>
                        <td><code>EHR_MAX_ATTEMPTS</code></td>
                        <td><code>5</code></td>
                        <td><code>5</code></td>
                        <td>Retry ceiling for the <code>ehr:retry</code> sweep.</td>
                    </tr>
                    <tr>
                        <td><code>HEALTHIE_ENDPOINT</code></td>
                        <td><code>https://staging-api.gethealthie.com/graphql</code></td>
                        <td><em>(same as default)</em></td>
                        <td>GraphQL endpoint. Staging and production are different hosts.</td>
                    </tr>
                    <tr>
                        <td><code>HEALTHIE_TIMEOUT</code></td>
                        <td><code>30</code></td>
                        <td><code>30</code></td>
                        <td>HTTP timeout in seconds for GraphQL requests.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 text-muted" style="font-size:.78rem;border-top:1px solid #dee2e6;">
            <i class="bi bi-info-circle me-1"></i>
            With shipped defaults (<code>EHR_ENABLED=false</code>), approving a case builds the full payload and stores an EHR record with status <code>disabled</code> — visible in Admin → EHR Records as a preview of what <em>would</em> be sent. Nothing leaves the system.
        </div>
    </div>
</div>

{{-- ── Key files reference ──────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom" style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6c757d;">
        Key Files
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.83rem;">
            <thead class="table-light">
                <tr><th>File</th><th>Role</th></tr>
            </thead>
            <tbody>
                <tr><td><code>config/ehr.php</code></td><td class="text-muted">Master config — all env var defaults, adapter name, retry ceiling.</td></tr>
                <tr><td><code>app/Services/Ehr/HealthieEhrAdapter.php</code></td><td class="text-muted">Live Healthie adapter — GraphQL transport, <code>findOrCreateClient</code>, <code>buildMutation</code>.</td></tr>
                <tr><td><code>app/Services/Ehr/MockEhrAdapter.php</code></td><td class="text-muted">No-network mock, used by default. Safe to leave on while building.</td></tr>
                <tr><td><code>app/Services/EhrRecordService.php</code></td><td class="text-muted">Outbox orchestrator — <code>buildPayload()</code>, <code>scopedPatientKey()</code>, <code>recordApproval()</code>, <code>push()</code>.</td></tr>
                <tr><td><code>app/Services/Ehr/EhrGatewayManager.php</code></td><td class="text-muted">Resolves the adapter for a given company — enforces dual-flag gate and per-partner <code>isPushable()</code>.</td></tr>
                <tr><td><code>app/Models/EhrRecord.php</code></td><td class="text-muted">Outbox row — statuses: <code>disabled · pending · sent · failed</code>.</td></tr>
                <tr><td><code>app/Models/PartnerEhrSetting.php</code></td><td class="text-muted">Per-partner credentials — <code>isPushable()</code>, <code>missingValues()</code>, <code>authHeaders()</code>. API key encrypted.</td></tr>
                <tr><td><code>app/Console/Commands/EhrRetry.php</code></td><td class="text-muted">Artisan command — retries <code>failed</code> records up to <code>max_attempts</code>, every 15 min.</td></tr>
                <tr><td><code>tests/Feature/EhrTenantSegregationTest.php</code></td><td class="text-muted">10 unit tests covering all tenant segregation guarantees.</td></tr>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
@endsection
