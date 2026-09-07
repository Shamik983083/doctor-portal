@extends('layouts.admin')

@section('title', 'Healthie EHR Guide')
@section('page-title', 'Healthie EHR Guide')

@push('head')
<style>
    :root {
        --g-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        --g-text:   #1d1d1f;
        --g-text2:  #3a3a3c;
        --g-muted:  #6e6e73;
        --g-border: #e5e5ea;
        --g-bg:     #f5f5f7;
        --g-surface:#ffffff;
        --g-accent: #0071e3;
        --g-green:  #1a8f3a;
        --g-green-bg: #e6f7ed;
        --g-blue-bg: #eaf3ff;
        --g-yellow-bg: #fef9e7;
        --g-yellow-border: #f5c542;
        --g-red-bg: #fff4f4;
        --g-red: #c0392b;
        --g-purple-bg: #f3f0ff;
        --g-purple: #6d4fc2;
        --g-radius: 12px;
        --g-shadow: 0 1px 4px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
    }
    .g-wrap {
        max-width: 860px;
        margin: 0 auto;
        font-family: var(--g-font);
        color: var(--g-text);
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding-bottom: 3rem;
    }

    /* ── Header ── */
    .g-hero { padding: 0 0 .5rem; }
    .g-eyebrow {
        font-size: .7rem; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; color: var(--g-muted); margin-bottom: .4rem;
    }
    .g-hero h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: .4rem; letter-spacing: -.02em; }
    .g-hero .g-lead { font-size: .975rem; color: var(--g-muted); max-width: 660px; line-height: 1.6; }

    /* ── Cards ── */
    .g-card {
        background: var(--g-surface);
        border: 1px solid var(--g-border);
        border-radius: var(--g-radius);
        box-shadow: var(--g-shadow);
        overflow: hidden;
    }
    .g-card-header {
        padding: .875rem 1.375rem;
        border-bottom: 1px solid var(--g-border);
        background: #f9f9fb;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .g-card-header h2 {
        font-size: .8rem; font-weight: 700; letter-spacing: .07em;
        text-transform: uppercase; color: var(--g-muted); margin: 0;
    }
    .g-card-body { padding: 1.375rem; }

    /* ── Callouts ── */
    .g-callout {
        border-radius: 10px;
        padding: .875rem 1.125rem;
        font-size: .875rem;
        line-height: 1.6;
        display: flex;
        gap: .75rem;
        align-items: flex-start;
    }
    .g-callout i { flex-shrink: 0; margin-top: .1rem; font-size: 1rem; }
    .g-callout-blue   { background: var(--g-blue-bg);   border: 1px solid #bdd5f5; color: #1a4a8c; }
    .g-callout-green  { background: var(--g-green-bg);  border: 1px solid #b2e0c0; color: #15602b; }
    .g-callout-yellow { background: var(--g-yellow-bg); border: 1px solid var(--g-yellow-border); color: #7a5c00; }
    .g-callout-red    { background: var(--g-red-bg);    border: 1px solid #f5bcbc; color: var(--g-red); }
    .g-callout-purple { background: var(--g-purple-bg); border: 1px solid #c9b8f5; color: var(--g-purple); }

    /* ── Flow diagram ── */
    .g-flow {
        display: flex;
        align-items: stretch;
        gap: 0;
        overflow-x: auto;
        padding: 1rem 0;
    }
    .g-flow-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 120px;
        flex: 1;
        text-align: center;
    }
    .g-flow-bubble {
        width: 52px; height: 52px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        border: 2px solid var(--g-border);
        background: var(--g-surface);
        position: relative;
        z-index: 1;
    }
    .g-flow-bubble.done  { background: var(--g-green-bg); border-color: #b2e0c0; }
    .g-flow-bubble.pend  { background: #f0f0f5; border-color: #d1d1d6; }
    .g-flow-connector {
        height: 2px;
        background: var(--g-border);
        flex: 1;
        align-self: center;
        min-width: 12px;
        position: relative;
        top: -24px;
    }
    .g-flow-label {
        font-size: .72rem;
        color: var(--g-text2);
        margin-top: .5rem;
        font-weight: 600;
        line-height: 1.35;
        max-width: 90px;
    }
    .g-flow-sub {
        font-size: .67rem;
        color: var(--g-muted);
        margin-top: .2rem;
        max-width: 90px;
        line-height: 1.3;
    }

    /* ── Step list ── */
    .g-steps { display: flex; flex-direction: column; gap: 1.25rem; }
    .g-step { display: flex; gap: 1rem; align-items: flex-start; }
    .g-step-num {
        width: 30px; height: 30px; border-radius: 50%;
        background: var(--g-accent); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: .78rem; font-weight: 700; flex-shrink: 0;
        margin-top: .1rem;
    }
    .g-step-num.done { background: var(--g-green); }
    .g-step-body h3 { font-size: .95rem; font-weight: 600; margin: 0 0 .25rem; color: var(--g-text); }
    .g-step-body p  { font-size: .875rem; color: var(--g-muted); margin: 0; line-height: 1.6; }
    .g-step-body .g-step-note {
        margin-top: .5rem;
        font-size: .8rem;
        background: var(--g-bg);
        border-radius: 7px;
        padding: .5rem .75rem;
        color: var(--g-text2);
        line-height: 1.55;
    }

    /* ── Field reference table ── */
    .g-field-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .g-field-table th {
        background: #f9f9fb;
        font-size: .72rem; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: var(--g-muted);
        padding: .6rem .875rem;
        border-bottom: 1px solid var(--g-border);
        text-align: left;
    }
    .g-field-table td {
        padding: .75rem .875rem;
        border-bottom: 1px solid var(--g-border);
        vertical-align: top;
        color: var(--g-text2);
        line-height: 1.55;
    }
    .g-field-table tr:last-child td { border-bottom: none; }
    .g-field-table tr:nth-child(even) td { background: #fafafa; }
    .g-field-name { font-weight: 600; color: var(--g-text); white-space: nowrap; }
    .g-field-req {
        display: inline-block;
        font-size: .65rem; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; padding: 1px 5px; border-radius: 4px;
        vertical-align: middle; margin-left: .25rem;
    }
    .g-req-yes  { background: #fde8e8; color: #b91c1c; }
    .g-req-opt  { background: #f0f0f5; color: var(--g-muted); }
    .g-req-auto { background: #e6f7ed; color: var(--g-green); }

    /* ── Status pills ── */
    .g-status {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .6rem; border-radius: 20px;
        font-size: .72rem; font-weight: 700; letter-spacing: .02em;
        text-transform: uppercase;
    }
    .g-status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .g-status-sent    { background: var(--g-green-bg); color: var(--g-green); }
    .g-status-sent .g-status-dot { background: var(--g-green); }
    .g-status-failed  { background: var(--g-red-bg); color: var(--g-red); }
    .g-status-failed .g-status-dot { background: var(--g-red); }
    .g-status-pending { background: #fff8e7; color: #92650a; }
    .g-status-pending .g-status-dot { background: #f59e0b; }
    .g-status-disabled{ background: #f0f0f5; color: var(--g-muted); }
    .g-status-disabled .g-status-dot { background: #aeaeb2; }

    /* ── FAQ ── */
    .g-faq-item { border-bottom: 1px solid var(--g-border); padding: 1rem 0; }
    .g-faq-item:last-child { border-bottom: none; }
    .g-faq-q { font-size: .9rem; font-weight: 600; color: var(--g-text); margin-bottom: .3rem; }
    .g-faq-a { font-size: .86rem; color: var(--g-muted); line-height: 1.6; }

    /* ── Progress bar ── */
    .g-progress-bar-track {
        height: 8px; border-radius: 99px;
        background: var(--g-border); overflow: hidden;
    }
    .g-progress-bar-fill {
        height: 100%; border-radius: 99px;
        background: var(--g-green);
        transition: width .4s ease;
    }

    /* ── Checklist ── */
    .g-checklist { list-style: none; padding: 0; margin: 0; }
    .g-checklist li {
        display: flex; align-items: flex-start; gap: .625rem;
        font-size: .875rem; color: var(--g-text2);
        padding: .4rem 0;
        line-height: 1.55;
    }
    .g-checklist li i { flex-shrink: 0; margin-top: .1rem; }
    .g-check-done { color: var(--g-green); }
    .g-check-pend { color: #aeaeb2; }

    /* ── Tech reference accordion ── */
    details.g-details {
        border: 1px solid var(--g-border);
        border-radius: 10px;
        overflow: hidden;
    }
    details.g-details summary {
        padding: .875rem 1.125rem;
        font-size: .875rem; font-weight: 600;
        cursor: pointer;
        list-style: none;
        display: flex; align-items: center; gap: .5rem;
        background: #f9f9fb;
        user-select: none;
    }
    details.g-details summary::-webkit-details-marker { display: none; }
    details.g-details summary .g-chevron { margin-left: auto; transition: transform .2s; }
    details.g-details[open] summary .g-chevron { transform: rotate(180deg); }
    details.g-details .g-details-body { padding: 1.125rem; }
    code.g-code {
        background: #f0f0f5; border-radius: 5px;
        padding: 2px 6px; font-size: .8em;
        font-family: 'SF Mono', 'Cascadia Mono', 'Consolas', monospace;
        color: #d63384;
    }

    /* ── Architecture diagram ── */
    .g-arch {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 1rem;
        align-items: center;
        margin: 1rem 0;
    }
    .g-arch-box {
        border: 1px solid var(--g-border);
        border-radius: 10px;
        padding: .875rem 1rem;
        text-align: center;
        background: var(--g-bg);
        font-size: .82rem;
    }
    .g-arch-box strong { display: block; font-size: .88rem; margin-bottom: .25rem; }
    .g-arch-arrow { color: var(--g-muted); font-size: 1.2rem; text-align: center; }
</style>
@endpush

@section('content')
<div class="g-wrap">

{{-- ══ HERO ════════════════════════════════════════════════════════ --}}
<div class="g-hero">
    <p class="g-eyebrow">MEDAXIS · EHR Integration</p>
    <h1>Healthie EHR — Complete Guide</h1>
    <p class="g-lead">
        Everything you need to understand how patient records flow from MEDAXIS into Healthie,
        how providers are synced automatically, and how sub-storefront segregation works through
        Healthie User Groups — no technical background required.
    </p>
</div>

{{-- ══ WHAT IS HEALTHIE? ═══════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>What is Healthie and why do we use it?</h2></div>
    <div class="g-card-body" style="display:flex;flex-direction:column;gap:1rem;">
        <p style="font-size:.95rem;line-height:1.7;margin:0;">
            <strong>Healthie</strong> is an Electronic Health Record (EHR) system — a secure digital system
            where clinical information about patients is stored long-term. When a doctor approves a patient's
            case in MEDAXIS, we automatically send a summary of that patient's information and the clinical note
            to Healthie so the treating provider has a complete record on their end.
        </p>
        <div class="g-callout g-callout-blue">
            <i class="bi bi-lightbulb-fill"></i>
            <div>
                <strong>In plain terms:</strong> MEDAXIS is where cases are managed and approved.
                Healthie is where clinical records live for the long term. After every approval in MEDAXIS,
                we send a copy to Healthie automatically — no manual data entry needed.
            </div>
        </div>

        <p style="font-size:.875rem;font-weight:600;color:var(--g-text);margin:0;">How partners and sub-storefronts are organised in Healthie</p>
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.65;margin:0;">
            Each partner (storefront) connects to Healthie with their <em>own</em> credentials — one API key,
            one organisation. This means Partner A's patients can never appear in Partner B's Healthie account.
        </p>
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.65;margin:0;">
            Within a partner, each <strong>sub-storefront</strong> gets its own <strong>User Group</strong> in Healthie.
            When MEDAXIS pushes a patient record, it automatically places that patient into the correct group
            for their sub-storefront. This keeps patient lists cleanly separated — for example, an AmeriLean
            patient would never appear in a MedSlim provider's view, even though both sub-storefronts share the
            same partner's Healthie organisation.
        </p>
        <div class="g-callout g-callout-purple">
            <i class="bi bi-diagram-3-fill"></i>
            <div>
                <strong>User Groups (current model):</strong> Each sub-storefront maps to one Healthie User Group.
                The group ID is stored automatically when the sub-storefront is created — no manual setup needed.
                Patients are assigned to their group at push time; providers are linked to patients via a care team entry.
            </div>
        </div>
    </div>
</div>

{{-- ══ HOW DATA FLOWS ══════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>How data flows — from patient to Healthie</h2></div>
    <div class="g-card-body">
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.6;margin-bottom:1.25rem;">
            Here is the full journey of a patient's information, from the moment they submit a request
            to the moment their record lands in Healthie — assigned to the right group and provider.
        </p>

        {{-- Flow diagram --}}
        <div style="overflow-x:auto;">
        <div style="display:flex;align-items:flex-start;gap:0;min-width:680px;padding:0 .5rem;">
            @php
            $flow = [
                ['🧑', 'Patient submits', 'Via sub-storefront website', 'done'],
                ['📋', 'Case created', 'Waiting for review', 'done'],
                ['👨‍⚕️', 'Doctor approves', 'Clinical decision recorded', 'done'],
                ['⚙️', 'Record built', 'Payload assembled', 'done'],
                ['🔑', 'Find or create patient', 'In Healthie via email', 'done'],
                ['👥', 'Assign to group', 'Sub-storefront user group', 'done'],
                ['🩺', 'Add to care team', 'Clinician linked as Provider', 'done'],
                ['📡', 'Push note + vitals', 'Sent to Healthie API', 'done'],
            ];
            @endphp
            @foreach($flow as $idx => $f)
                <div style="display:flex;flex-direction:column;align-items:center;flex:1;min-width:80px;text-align:center;">
                    <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.15rem;background:var(--g-green-bg);border:2px solid #b2e0c0;position:relative;z-index:1;">
                        {{ $f[0] }}
                    </div>
                    <div style="font-size:.69rem;font-weight:600;color:var(--g-text2);margin-top:.4rem;line-height:1.3;max-width:72px;">{{ $f[1] }}</div>
                    <div style="font-size:.63rem;color:var(--g-muted);margin-top:.15rem;line-height:1.3;max-width:72px;">{{ $f[2] }}</div>
                </div>
                @if(!$loop->last)
                <div style="height:2px;background:var(--g-border);flex:0 0 10px;align-self:center;margin-bottom:2.8rem;"></div>
                @endif
            @endforeach
        </div>
        </div>

        <div class="g-callout g-callout-green" style="margin-top:1.25rem;">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>This all happens automatically</strong> the moment a doctor clicks "Approve" in MEDAXIS.
                Steps 5–7 (find patient, assign to group, add clinician to care team) happen in sequence before
                the note is created, so the record is always correctly attributed from the start.
                If the push fails for any reason, the system retries automatically every 15 minutes — up to 5 times.
            </div>
        </div>

        <div style="margin-top:1rem;">
            <p style="font-size:.875rem;font-weight:600;color:var(--g-text);margin-bottom:.5rem;">What gets sent to Healthie?</p>
            <ul style="font-size:.86rem;color:var(--g-muted);padding-left:1.25rem;line-height:1.9;margin:0;">
                <li>Patient name, date of birth, sex, and email address</li>
                <li>The doctor's clinical note (the chart note written at approval)</li>
                <li>Vitals: weight, height, and BMI (when available)</li>
                <li>Group membership — patient is added to the sub-storefront's Healthie User Group</li>
                <li>Care team entry — the approving clinician is linked to the patient with the role "Provider"</li>
            </ul>
        </div>
    </div>
</div>

{{-- ══ CURRENT STATUS ═══════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>Current integration status</h2></div>
    <div class="g-card-body" style="display:flex;flex-direction:column;gap:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.8rem;font-weight:600;color:var(--g-muted);">Overall completion</span>
            <span style="font-size:1.4rem;font-weight:700;color:var(--g-green);">100%</span>
        </div>
        <div class="g-progress-bar-track">
            <div class="g-progress-bar-fill" style="width:100%;"></div>
        </div>
        <ul class="g-checklist">
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>All core code is built and tested.</strong> The system can receive an approval, build the patient record, and send it to Healthie.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>User Groups are in place.</strong> Each sub-storefront automatically gets its own Healthie User Group when created. Group ID is stored and used at push time.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Clinician provisioning is automated.</strong> When a clinician is saved, MEDAXIS automatically creates their provider account in Healthie and keeps NPI, credentials, specialty, and licensed states in sync.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Care team is assigned at push.</strong> The prescribing clinician is linked to the patient in Healthie with the role "Provider" automatically at every push.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Vitals are included.</strong> Weight, height, and BMI push alongside the clinical note.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Automatic retries work.</strong> Failed pushes are retried every 15 minutes, up to 5 attempts.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Admin monitoring is live.</strong> You can see every EHR push attempt under Admin → EHR Records.</span></li>
        </ul>
    </div>
</div>

{{-- ══ SETUP GUIDE ═════════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>How to set up EHR for a partner — step by step</h2></div>
    <div class="g-card-body">
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.6;margin-bottom:1.5rem;">
            Follow these steps in order. Credentials are set once at the <em>partner level</em> —
            sub-storefronts inherit the same Healthie organisation and are automatically separated
            into their own User Groups.
        </p>

        <div class="g-steps">

            <div class="g-step">
                <div class="g-step-num">1</div>
                <div class="g-step-body">
                    <h3>Get the partner-level credentials from Healthie</h3>
                    <p>Log in to the partner's Healthie account and collect the following. You only need to do this once per partner — these credentials apply to all sub-storefronts under this partner.</p>
                    <div class="g-step-note">
                        <strong>What to collect:</strong><br>
                        • <strong>API Key</strong> — found in Healthie under Settings → Integrations → API Keys. A long alphanumeric string.<br>
                        • <strong>GraphQL Endpoint</strong> — staging: <code style="background:#eee;padding:1px 4px;border-radius:3px;font-size:.85em;">https://staging-api.gethealthie.com/graphql</code> / production: <code style="background:#eee;padding:1px 4px;border-radius:3px;font-size:.85em;">https://api.gethealthie.com/graphql</code><br>
                        • <strong>Organization ID</strong> — found in Healthie org settings. Used to provision clinicians into the correct org.<br>
                        • <strong>Authorization Shard</strong> — only needed if Healthie support told you this account uses a specific data region. Leave blank otherwise.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">2</div>
                <div class="g-step-body">
                    <h3>Open the partner in Admin and fill in the EHR section</h3>
                    <p>Go to <strong>Admin → Super Admin → Partners</strong>, find the partner, and click <strong>Edit</strong>.
                    Scroll down to the <em>Healthie EHR</em> section and enter the API Key, Endpoint, and Organization ID from Step 1.</p>
                    <div class="g-step-note">
                        Leave both <strong>Sandbox Validated</strong> and <strong>Push records to Healthie</strong> switched <em>off</em>
                        for now — you'll turn those on in Step 6 after testing.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">3</div>
                <div class="g-step-body">
                    <h3>Create sub-storefronts — User Groups are created automatically</h3>
                    <p>When a sub-storefront is created under this partner (either from the admin panel or via the partner API),
                    MEDAXIS <strong>automatically</strong> calls Healthie to create a User Group named after that sub-storefront.
                    The resulting Group ID is saved directly to the sub-storefront record — no manual step is needed.</p>
                    <div class="g-step-note">
                        <strong>Where to verify:</strong> Open any sub-storefront's Edit screen
                        (<strong>Admin → Partners → [Partner] → Sub-Storefronts → Edit</strong>).
                        The <em>Healthie Default Group ID</em> field should be populated automatically.
                        If it is blank, the group creation may have failed (check server logs or recreate the sub-storefront).
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">4</div>
                <div class="g-step-body">
                    <h3>Configure per-sub-storefront defaults (optional)</h3>
                    <p>Each sub-storefront can optionally override the default provider and charting form.
                    Go to the sub-storefront's <strong>Edit</strong> screen and set:</p>
                    <div class="g-step-note">
                        • <strong>Default Provider ID</strong> — the Healthie user ID of the clinician that chart notes will be assigned to by default for this sub-storefront.<br>
                        • <strong>Note Form ID</strong> — the ID of the charting template (e.g. "Free Text"). Use the <strong>"Look up Form &amp; Group IDs"</strong> button on the edit screen to find this automatically.<br><br>
                        If these are left blank, the partner-level defaults apply (if set), or the note is created as a plain text entry.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">5</div>
                <div class="g-step-body">
                    <h3>Clinician provisioning — happens automatically when clinicians are saved</h3>
                    <p>You do not need to manually create provider accounts in Healthie. When any clinician is saved
                    in MEDAXIS, the system automatically runs three syncs in sequence:</p>
                    <div class="g-step-note">
                        <strong>1. Create or find the account</strong> — checks whether the clinician already exists in this partner's Healthie org. If not, creates one via <em>createOrganizationMembership</em>.<br><br>
                        <strong>2. Sync basic identity</strong> — pushes first name, last name, and phone number via <em>updateUser</em>.<br><br>
                        <strong>3. Sync professional details</strong> — pushes NPI number, credentials (MD, DO, NP…), and licensed states via <em>updateOrganizationMember</em>, then sets the <em>Is Dr. a provider?</em> flag to Yes via <em>updateOrganizationMembership</em> so the clinician appears in Healthie's scheduling and care-team selectors.<br><br>
                        All three steps run on every sync — including re-syncs — so Healthie always reflects the current values from MEDAXIS.<br><br>
                        <strong>Where to see status:</strong> Each clinician's profile shows their Healthie sync status (Synced / Pending / Failed) per partner under the <em>Healthie Provisioning</em> section. If it shows Failed, the error message is shown there — fix the clinician's profile and the next save will retry.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">6</div>
                <div class="g-step-body">
                    <h3>Run a test push (optional but recommended)</h3>
                    <p>Before activating live pushes, submit a test case through the sub-storefront, approve it in MEDAXIS,
                    and check <strong>Admin → EHR Records</strong> to see the record with status <em>Disabled</em>
                    (meaning: built but not sent yet). This lets you verify the payload looks correct before anything is
                    actually transmitted.</p>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">7</div>
                <div class="g-step-body">
                    <h3>Activate live pushes</h3>
                    <p>Go back to the partner's Edit screen. In the Healthie EHR section, tick both boxes:</p>
                    <div class="g-step-note">
                        ☑ <strong>Sandbox validated for this partner</strong> — confirms you've checked that the setup
                        works correctly and data goes to the right place.<br><br>
                        ☑ <strong>Push records to Healthie for this partner</strong> — turns on live transmission.
                        From this point on, every case approval sends a record to Healthie in real time.
                    </div>
                    <div class="g-callout g-callout-yellow" style="margin-top:.75rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>Three conditions must all be true before a push fires: (1) the partner must have
                        <em>Sandbox Validated</em> and <em>Push Records</em> both enabled, and (2) the sub-storefront
                        must have a Healthie Group ID stored. If the Group ID is missing, pushes for that
                        sub-storefront are skipped with status <em>Disabled</em>.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ══ FIELD REFERENCE ══════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>What each setting means — plain English</h2></div>
    <div class="g-card-body" style="padding:0;">

        <div style="padding:.875rem 1.375rem .5rem;font-size:.8rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--g-muted);">
            Partner-level settings (set once, apply to all sub-storefronts)
        </div>
        <div style="overflow-x:auto;">
        <table class="g-field-table">
            <thead>
                <tr>
                    <th style="width:26%;">Field name</th>
                    <th style="width:12%;">Required?</th>
                    <th>What it is and where to find it</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="g-field-name">API Key</td>
                    <td><span class="g-field-req g-req-yes">Required</span></td>
                    <td>A secret password that proves to Healthie we are authorised to push records on this partner's behalf.
                        Found in Healthie → Settings → Integrations → API Keys.
                        <strong>Treat this like a bank password</strong> — never share it publicly.
                        Stored encrypted in MEDAXIS and never displayed again after saving.</td>
                </tr>
                <tr>
                    <td class="g-field-name">GraphQL Endpoint</td>
                    <td><span class="g-field-req g-req-yes">Required</span></td>
                    <td>The web address MEDAXIS sends records to. For staging/testing use
                        <code class="g-code">https://staging-api.gethealthie.com/graphql</code>.
                        For production use
                        <code class="g-code">https://api.gethealthie.com/graphql</code>.
                        <strong>Do not mix staging and production</strong> — test data will go to real patient records.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Organization ID</td>
                    <td><span class="g-field-req g-req-yes">Required</span></td>
                    <td>A number that identifies this partner's organisation inside Healthie.
                        Found in Healthie → Settings → Organisation. Used to provision clinician accounts into the correct org.
                        All sub-storefronts under this partner share this same organisation — they are separated by User Groups, not by different orgs.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Authorization Shard</td>
                    <td><span class="g-field-req g-req-opt">Optional</span></td>
                    <td>A technical routing value only needed if the Healthie account is hosted in a specific data region ("shard").
                        The vast majority of accounts do not need this. Leave blank unless Healthie support has told you to use it.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Sandbox Validated</td>
                    <td><span class="g-field-req g-req-opt">Switch</span></td>
                    <td>A safety confirmation that you have personally verified records go to the correct
                        Healthie account. <strong>Tick this only after testing.</strong> Both this and "Push records" must be on before any live push fires.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Push records to Healthie</td>
                    <td><span class="g-field-req g-req-opt">Switch</span></td>
                    <td>The master on/off switch for this partner's live pushes.
                        When off: case approvals still create a preview record in Admin → EHR Records (so you can inspect the payload)
                        but <strong>nothing is transmitted to Healthie</strong>.
                        When on: every approval triggers an immediate push.</td>
                </tr>
            </tbody>
        </table>
        </div>

        <div style="padding:.875rem 1.375rem .5rem;font-size:.8rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--g-muted);border-top:1px solid var(--g-border);margin-top:.5rem;">
            Sub-storefront settings (per sub-storefront overrides)
        </div>
        <div style="overflow-x:auto;">
        <table class="g-field-table">
            <thead>
                <tr>
                    <th style="width:26%;">Field name</th>
                    <th style="width:12%;">Required?</th>
                    <th>What it is and where to find it</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="g-field-name">Healthie Group ID</td>
                    <td><span class="g-field-req g-req-auto">Auto-set</span></td>
                    <td>The ID of the Healthie User Group that represents this sub-storefront.
                        <strong>This is populated automatically</strong> when the sub-storefront is created —
                        MEDAXIS calls Healthie's <code class="g-code">createGroup</code> mutation and saves the result.
                        Patients approved through this sub-storefront are automatically added to this group at push time.
                        If this field is blank, pushes for this sub-storefront will be skipped.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Default Provider ID</td>
                    <td><span class="g-field-req g-req-opt">Optional</span></td>
                    <td>The Healthie user ID of the provider that chart notes will be assigned to by default for this sub-storefront.
                        If left blank, the prescribing clinician's own Healthie ID is used (set automatically when the clinician was provisioned).</td>
                </tr>
                <tr>
                    <td class="g-field-name">Note Form ID</td>
                    <td><span class="g-field-req g-req-opt">Optional</span></td>
                    <td>Tells MEDAXIS which charting template to use when creating the clinical note in Healthie for this sub-storefront.
                        If left blank, the note still goes through as a plain text entry.
                        Use the <strong>"Look up Form &amp; Group IDs"</strong> button on the edit screen — look for the form called "Free Text".</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- ══ MONITORING ═══════════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>Monitoring — how to check if pushes are working</h2></div>
    <div class="g-card-body" style="display:flex;flex-direction:column;gap:1rem;">
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.6;margin:0;">
            Every EHR push attempt is logged under <strong>Admin → EHR Records</strong>.
            The sidebar also shows a red badge with the count of failed records — if you see a number there,
            something needs attention.
        </p>

        <div>
            <p style="font-size:.875rem;font-weight:600;color:var(--g-text);margin-bottom:.75rem;">What the status labels mean:</p>
            <div style="display:flex;flex-direction:column;gap:.625rem;">
                <div style="display:flex;align-items:flex-start;gap:.875rem;">
                    <span class="g-status g-status-sent" style="min-width:80px;justify-content:center;"><span class="g-status-dot"></span>Sent</span>
                    <span style="font-size:.86rem;color:var(--g-muted);line-height:1.55;">The record was successfully delivered to Healthie. You can click it to see the Healthie reference ID confirming receipt.</span>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.875rem;">
                    <span class="g-status g-status-pending" style="min-width:80px;justify-content:center;"><span class="g-status-dot"></span>Pending</span>
                    <span style="font-size:.86rem;color:var(--g-muted);line-height:1.55;">A push attempt is in progress or queued. This usually resolves within seconds. If it stays on Pending for more than a few minutes, check the server logs.</span>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.875rem;">
                    <span class="g-status g-status-failed" style="min-width:80px;justify-content:center;"><span class="g-status-dot"></span>Failed</span>
                    <span style="font-size:.86rem;color:var(--g-muted);line-height:1.55;">The push did not reach Healthie. The system will retry automatically every 15 minutes, up to 5 times. You can also trigger a manual retry by opening the record and clicking <strong>Retry</strong>. If it keeps failing, the error detail is shown on the record page.</span>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.875rem;">
                    <span class="g-status g-status-disabled" style="min-width:80px;justify-content:center;"><span class="g-status-dot"></span>Disabled</span>
                    <span style="font-size:.86rem;color:var(--g-muted);line-height:1.55;">The partner's push is switched off, or the sub-storefront has no Healthie Group ID. The record was built and stored so you can inspect it, but nothing was sent to Healthie. Enable the partner's push switches and verify the sub-storefront has a Group ID, then retry.</span>
                </div>
            </div>
        </div>

        <div class="g-callout g-callout-blue">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>A failed push never cancels an approval.</strong>
                If the clinical decision was recorded in MEDAXIS, it stays recorded — the EHR push runs separately
                and does not undo anything clinical. The doctor's decision is always safe.
            </div>
        </div>
    </div>
</div>

{{-- ══ FAQ ══════════════════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>Common questions</h2></div>
    <div class="g-card-body" style="padding: 1rem 1.375rem;">
        @php
        $faqs = [
            [
                'Can two partners share the same Healthie account?',
                'No — and this is intentional. Each partner must have its own Healthie API key and organisation. Sharing credentials would mean one partner\'s patients could appear in another\'s records, which is a compliance violation. The system will refuse to push if credentials are shared.',
            ],
            [
                'Can two sub-storefronts under the same partner share the same Healthie account?',
                'Yes — sub-storefronts under one partner all use the same Healthie organisation (the partner\'s). They are separated inside Healthie by User Groups, not by separate accounts. Each sub-storefront gets its own group, so patients only appear in the group that matches where they submitted. This is the current architecture replacing the old approach of needing separate Healthie accounts per sub-storefront.',
            ],
            [
                'The sub-storefront\'s Healthie Group ID is blank. What do I do?',
                'The group should be created automatically when the sub-storefront is set up. If it\'s blank, it likely means the sub-storefront was created before the partner\'s Healthie credentials were configured, or the API call to Healthie failed. To fix: ensure the partner has a valid API key and endpoint saved, then edit and re-save the sub-storefront (or contact the technical team to trigger the group creation manually). Without a group ID, pushes for that sub-storefront will show as Disabled.',
            ],
            [
                'Do I need to create provider accounts in Healthie manually?',
                'No. MEDAXIS handles this automatically. When any clinician\'s profile is saved in the admin, the system provisions them into every partner\'s Healthie org they should belong to. Each sync runs three steps: create/find the account, sync name and phone, then sync NPI, credentials, licensed states, and the "Is Dr. a provider?" flag. All three steps run on every sync, so Healthie always reflects current MEDAXIS data. You can see the sync status on the clinician\'s profile page.',
            ],
            [
                'What happens if the API key is wrong or expired?',
                'The push will fail and the EHR Record will show status "Failed" with an error message explaining that Healthie rejected the credentials. Update the API key on the partner\'s Edit screen and then manually retry the failed records.',
            ],
            [
                'A patient submitted through two different sub-storefronts. Will they appear in two groups in Healthie?',
                'Yes — if the same patient (same email) submits through two sub-storefronts, they will be added to both groups in Healthie. Their single patient profile in Healthie (matched by email) will have membership in both groups. This is intentional and correct behaviour.',
            ],
            [
                'The "Push records" switch is on but records are still showing as Disabled. Why?',
                'Check three things: (1) the partner\'s own "Push records" and "Sandbox validated" checkboxes are both ticked, (2) the sub-storefront has a Healthie Group ID populated (check its Edit screen), and (3) platform-wide server settings are on. If the server environment variables are off, no pushes go out regardless of partner settings — contact the technical team to check.',
            ],
            [
                'I clicked "Look up Form & Group IDs" but got an error.',
                'The lookup uses the API key and endpoint currently saved for the partner. Make sure you have already saved those fields before clicking the button. If they are saved and you still get an error, verify the API key is correct and the endpoint URL matches the right environment (staging vs production).',
            ],
            [
                'How long does a push take?',
                'Usually under 5 seconds. MEDAXIS sends the request to Healthie immediately after an approval, and Healthie confirms receipt in the same response. If it takes longer, Healthie\'s API may be slow — the system waits up to 30 seconds before marking it failed.',
            ],
            [
                'Can I see exactly what data was sent to Healthie?',
                'Yes. Click any EHR Record under Admin → EHR Records to open the detail view. It shows the complete payload that was built and sent, including all patient fields, the sub-storefront group, and the clinical note text.',
            ],
        ];
        @endphp
        @foreach($faqs as $faq)
        <div class="g-faq-item">
            <div class="g-faq-q">{{ $faq[0] }}</div>
            <div class="g-faq-a">{{ $faq[1] }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- ══ TECHNICAL REFERENCE (collapsed) ════════════════════════════ --}}
<details class="g-details">
    <summary>
        <i class="bi bi-code-square" style="color:var(--g-muted);"></i>
        <span style="color:var(--g-text2);">Technical Reference</span>
        <span style="font-size:.78rem;color:var(--g-muted);font-weight:400;margin-left:.25rem;">— for developers only</span>
        <i class="bi bi-chevron-down g-chevron" style="color:var(--g-muted);font-size:.75rem;"></i>
    </summary>
    <div class="g-details-body" style="display:flex;flex-direction:column;gap:1.25rem;">

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.5rem;">Push gate — all three must be true</p>
            <p style="font-size:.84rem;color:var(--g-muted);line-height:1.6;margin-bottom:.75rem;">
                <code class="g-code">isPushable()</code> on <code class="g-code">PartnerEhrSetting</code> enforces the per-partner flags.
                The adapter also checks the sub-storefront's <code class="g-code">healthie_default_group_id</code> — a missing group ID short-circuits to <code class="g-code">disabled</code> status.
            </p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead>
                    <tr><th>Condition</th><th>Where set</th><th>Effect if false</th></tr>
                </thead>
                <tbody>
                    <tr><td><code class="g-code">healthie_is_enabled = true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Partner EHR Settings — "Push records to Healthie" toggle</td><td style="font-size:.83rem;color:var(--g-muted);">Record stored as <code class="g-code">disabled</code>, not sent</td></tr>
                    <tr><td><code class="g-code">healthie_sandbox_validated = true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Partner EHR Settings — "Sandbox validated" toggle</td><td style="font-size:.83rem;color:var(--g-muted);">Record stored as <code class="g-code">disabled</code>, not sent</td></tr>
                    <tr><td><code class="g-code">sub_storefront.healthie_default_group_id</code> present</td><td style="font-size:.83rem;color:var(--g-muted);">Auto-populated on sub-storefront creation via <code class="g-code">createGroup</code></td><td style="font-size:.83rem;color:var(--g-muted);">Record stored as <code class="g-code">disabled</code>, not sent</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.5rem;">Platform env variables</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead>
                    <tr><th>Variable</th><th>Default</th><th>Staging</th><th>Purpose</th></tr>
                </thead>
                <tbody>
                    <tr><td><code class="g-code">EHR_ENABLED</code></td><td><code class="g-code">false</code></td><td><code class="g-code">true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Platform master switch — off by default.</td></tr>
                    <tr><td><code class="g-code">EHR_SANDBOX_VALIDATED</code></td><td><code class="g-code">false</code></td><td><code class="g-code">true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Second platform gate — both env vars must be true before per-partner flags are checked.</td></tr>
                    <tr><td><code class="g-code">EHR_ADAPTER</code></td><td><code class="g-code">mock</code></td><td><code class="g-code">healthie</code></td><td style="font-size:.83rem;color:var(--g-muted);"><code class="g-code">mock</code> = no network calls. <code class="g-code">healthie</code> = live GraphQL.</td></tr>
                    <tr><td><code class="g-code">EHR_MAX_ATTEMPTS</code></td><td><code class="g-code">5</code></td><td><code class="g-code">5</code></td><td style="font-size:.83rem;color:var(--g-muted);">Retry ceiling for the <code class="g-code">ehr:retry</code> sweep.</td></tr>
                    <tr><td><code class="g-code">HEALTHIE_TIMEOUT</code></td><td><code class="g-code">30</code></td><td><code class="g-code">30</code></td><td style="font-size:.83rem;color:var(--g-muted);">HTTP timeout in seconds for all GraphQL requests.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.75rem;">GraphQL mutations used</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead><tr><th>When</th><th>Mutation</th><th>Result in Healthie</th></tr></thead>
                <tbody>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Sub-storefront created</td>
                        <td><code class="g-code">createGroup</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">New User Group created; ID stored as <code class="g-code">healthie_default_group_id</code></td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Clinician saved (new)</td>
                        <td><code class="g-code">createOrganizationMembership</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Provider account created in partner org</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Clinician saved (new or update) — step 1</td>
                        <td><code class="g-code">updateUser</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">First name, last name, phone number synced</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Clinician saved (new or update) — step 2</td>
                        <td><code class="g-code">updateOrganizationMember</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">NPI, qualifications (credentials), and licensed states synced. Uses clinician's Healthie user ID as identifier.</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Clinician saved (new or update) — step 3</td>
                        <td><code class="g-code">updateOrganizationMembership</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Sets <code class="g-code">is_provider: true</code> so the clinician appears in scheduling and care-team selectors. Membership record ID fetched first via <code class="g-code">organizationMemberships(user_ids: [...])</code>.</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Patient not found by email</td>
                        <td><code class="g-code">createClient</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">New Healthie client · namespaced key in <code class="g-code">record_identifier</code> · <code class="g-code">dont_send_welcome: true</code></td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Every patient push</td>
                        <td><code class="g-code">bulkUpdateClients</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Single call that assigns patient to sub-storefront User Group (<code class="g-code">user_group_id</code>) and links the prescribing clinician as a care team provider (<code class="g-code">other_provider_ids</code>). Best-effort — failures logged, push not failed.</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);"><code class="g-code">note_form_id</code> is set</td>
                        <td><code class="g-code">createFormAnswerGroup</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Charting record tied to form template · <code class="g-code">finished + marked_locked</code></td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);"><code class="g-code">note_form_id</code> is blank</td>
                        <td><code class="g-code">createNote</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Plain text note on patient timeline</td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Each vital metric</td>
                        <td><code class="g-code">createEntry</code></td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Weight (lbs), Height (in.), BMI — best-effort</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.75rem;">Key files</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead><tr><th>File</th><th>Role</th></tr></thead>
                <tbody>
                    <tr><td><code class="g-code">app/Services/Ehr/HealthieEhrAdapter.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Record push adapter — findOrCreateClient, bulkUpdateClientGroupAndCareTeam (single call sets user group + care team provider), buildMutation, pushVitals. Uses partner credentials + sub-storefront group override via <code class="g-code">forSubStorefront()</code> factory.</td></tr>
                    <tr><td><code class="g-code">app/Services/Ehr/HealthieProvisioningService.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Org provisioning — createUserGroup (on sub-storefront create), provisionClinician (createOrganizationMembership), updateProviderDetails (3 steps: updateUser for name/phone; updateOrganizationMember for NPI/credentials/states; updateOrganizationMembership for is_provider via membership ID from organizationMemberships lookup).</td></tr>
                    <tr><td><code class="g-code">app/Jobs/ProvisionClinicianInHealthieJob.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Dispatched on clinician save. Iterates all partner EHR settings, creates or updates the clinician account. Idempotent — already-synced rows skip create but always push updated details.</td></tr>
                    <tr><td><code class="g-code">app/Services/EhrRecordService.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Outbox orchestrator — buildPayload, scopedPatientKey, recordApproval, push.</td></tr>
                    <tr><td><code class="g-code">app/Services/Ehr/EhrGatewayManager.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Resolves adapter for a partner — enforces dual env-flag gate and isPushable().</td></tr>
                    <tr><td><code class="g-code">app/Services/Ehr/MockEhrAdapter.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">No-network mock, used when EHR_ADAPTER=mock. Safe default.</td></tr>
                    <tr><td><code class="g-code">app/Models/EhrRecord.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Outbox row — statuses: disabled · pending · sent · failed.</td></tr>
                    <tr><td><code class="g-code">app/Models/PartnerEhrSetting.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Per-partner credentials — isPushable(), missingValues(), authHeaders(). API key encrypted. One row per partner.</td></tr>
                    <tr><td><code class="g-code">app/Models/ClinicianHealthieMapping.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Tracks clinician → Healthie user ID per partner. Always partner-level (sub_storefront_id = NULL). Statuses: pending · synced · failed.</td></tr>
                    <tr><td><code class="g-code">app/Console/Commands/EhrRetry.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Artisan command — retries failed records up to max_attempts, runs every 15 min.</td></tr>
                    <tr><td><code class="g-code">tests/Feature/EhrTenantSegregationTest.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Unit tests covering tenant segregation guarantees.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.5rem;">Tenant segregation enforcement</p>
            <ul style="font-size:.84rem;color:var(--g-muted);padding-left:1.25rem;line-height:2;margin:0;">
                <li>Each partner pushes with its own Healthie API key — no global key, no fallback.</li>
                <li>Patient matching uses namespaced key <code class="g-code">partner_uuid:patient_id</code> — never email, phone, or DOB alone.</li>
                <li>Sub-storefront separation is enforced by Healthie User Groups — each sub-storefront has its own <code class="g-code">healthie_default_group_id</code> and every patient push calls <code class="g-code">bulkUpdateClients</code> to place the patient in the correct group and link the prescribing clinician as care team provider in one call.</li>
                <li>Clinician mappings are partner-level (<code class="g-code">sub_storefront_id = NULL</code>) — one Healthie account per clinician per partner, reused across all sub-storefronts.</li>
                <li>The <code class="g-code">EhrGatewayManager</code> resolves a different adapter instance per partner — a shared credential is architecturally impossible.</li>
            </ul>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.5rem;">Key DB columns added (User Groups migration)</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead><tr><th>Table</th><th>Column</th><th>Purpose</th></tr></thead>
                <tbody>
                    <tr><td><code class="g-code">sub_storefronts</code></td><td><code class="g-code">healthie_default_group_id</code></td><td style="font-size:.83rem;color:var(--g-muted);">Healthie User Group ID for this sub-storefront. Auto-populated on create.</td></tr>
                    <tr><td><code class="g-code">sub_storefronts</code></td><td><code class="g-code">healthie_default_provider_id</code></td><td style="font-size:.83rem;color:var(--g-muted);">Optional override provider for this sub-storefront's chart notes.</td></tr>
                    <tr><td><code class="g-code">sub_storefronts</code></td><td><code class="g-code">healthie_note_form_id</code></td><td style="font-size:.83rem;color:var(--g-muted);">Optional charting template override for this sub-storefront.</td></tr>
                    <tr><td><code class="g-code">clinician_healthie_mappings</code></td><td><code class="g-code">healthie_user_id</code></td><td style="font-size:.83rem;color:var(--g-muted);">Healthie provider account ID for this clinician in this partner's org.</td></tr>
                    <tr><td><code class="g-code">clinician_healthie_mappings</code></td><td><code class="g-code">status / last_error / synced_at</code></td><td style="font-size:.83rem;color:var(--g-muted);">Provisioning status tracking — pending · synced · failed.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

    </div>
</details>

</div>
@endsection
