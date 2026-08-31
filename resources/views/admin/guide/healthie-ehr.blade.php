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
        how to set up a partner, and how to make sure it's all working — no technical background required.
    </p>
</div>

{{-- ══ WHAT IS HEALTHIE? ═══════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>What is Healthie and why do we use it?</h2></div>
    <div class="g-card-body" style="display:flex;flex-direction:column;gap:1rem;">
        <p style="font-size:.95rem;line-height:1.7;margin:0;">
            <strong>Healthie</strong> is an Electronic Health Record (EHR) system — think of it as a secure digital filing cabinet
            where clinical information about patients is stored. When a doctor approves a patient's case in MEDAXIS,
            we automatically send a summary of that patient's information and the clinical note to Healthie so the
            treating provider has a complete record on their end.
        </p>
        <div class="g-callout g-callout-blue">
            <i class="bi bi-lightbulb-fill"></i>
            <div>
                <strong>In plain terms:</strong> MEDAXIS is where cases are managed and approved.
                Healthie is where clinical records live for the long term. After every approval in MEDAXIS,
                we send a copy to Healthie automatically — no manual data entry needed.
            </div>
        </div>
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.65;margin:0;">
            Each partner (storefront) connects to Healthie with their <em>own</em> credentials. This means
            Partner A's patients can never appear in Partner B's Healthie account — the separation is built
            into the system structurally, not enforced by policy alone.
        </p>
    </div>
</div>

{{-- ══ HOW DATA FLOWS ══════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>How data flows — from patient to Healthie</h2></div>
    <div class="g-card-body">
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.6;margin-bottom:1.25rem;">
            Here is the full journey of a patient's information, from the moment they submit a request
            to the moment their record lands in Healthie.
        </p>

        {{-- Flow diagram --}}
        <div style="overflow-x:auto;">
        <div style="display:flex;align-items:flex-start;gap:0;min-width:560px;padding:0 .5rem;">
            @php
            $flow = [
                ['🧑', 'Patient submits', 'Via partner\'s website or form', 'done'],
                ['📋', 'Case created', 'Waiting for review', 'done'],
                ['👨‍⚕️', 'Doctor reviews & approves', 'Clinical decision recorded', 'done'],
                ['⚙️', 'MEDAXIS packages the record', 'Builds the EHR payload', 'done'],
                ['📡', 'Sent to Healthie', 'Via secure API', 'done'],
                ['🏥', 'Record appears in Healthie', 'Under the patient\'s profile', 'done'],
            ];
            @endphp
            @foreach($flow as $idx => $f)
                <div style="display:flex;flex-direction:column;align-items:center;flex:1;min-width:90px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;background:var(--g-green-bg);border:2px solid #b2e0c0;position:relative;z-index:1;">
                        {{ $f[0] }}
                    </div>
                    <div style="font-size:.73rem;font-weight:600;color:var(--g-text2);margin-top:.45rem;line-height:1.3;max-width:80px;">{{ $f[1] }}</div>
                    <div style="font-size:.67rem;color:var(--g-muted);margin-top:.2rem;line-height:1.3;max-width:80px;">{{ $f[2] }}</div>
                </div>
                @if(!$loop->last)
                <div style="height:2px;background:var(--g-border);flex:0 0 16px;align-self:center;margin-bottom:3rem;"></div>
                @endif
            @endforeach
        </div>
        </div>

        <div class="g-callout g-callout-green" style="margin-top:1.25rem;">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>This all happens automatically</strong> the moment a doctor clicks "Approve" in MEDAXIS.
                No one needs to manually copy anything into Healthie.
                If the push fails for any reason, the system retries automatically every 15 minutes — up to 5 times.
            </div>
        </div>

        <div style="margin-top:1rem;">
            <p style="font-size:.875rem;font-weight:600;color:var(--g-text);margin-bottom:.5rem;">What gets sent to Healthie?</p>
            <ul style="font-size:.86rem;color:var(--g-muted);padding-left:1.25rem;line-height:1.9;margin:0;">
                <li>Patient name, date of birth, sex, and email address</li>
                <li>The doctor's clinical note (the chart note written at approval)</li>
                <li>Vitals: weight, height, and BMI (when available)</li>
                <li>The partner's provider ID — so the record is assigned to the right clinician in Healthie</li>
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
            <span style="font-size:1.4rem;font-weight:700;color:var(--g-green);">83%</span>
        </div>
        <div class="g-progress-bar-track">
            <div class="g-progress-bar-fill" style="width:83%;"></div>
        </div>
        <ul class="g-checklist">
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>All core code is built and tested.</strong> The system can receive an approval, build the patient record, and send it to Healthie.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Vitals are included.</strong> Weight, height, and BMI push alongside the clinical note.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Automatic retries work.</strong> Failed pushes are retried every 15 minutes, up to 5 attempts.</span></li>
            <li><i class="bi bi-check-circle-fill g-check-done"></i><span><strong>Admin monitoring is live.</strong> You can see every EHR push attempt under Admin → EHR Records.</span></li>
            <li><i class="bi bi-circle g-check-pend"></i><span><strong>Still needed to go live:</strong> A partner must be fully configured with credentials and switched on. See the setup guide below.</span></li>
        </ul>
    </div>
</div>

{{-- ══ SETUP GUIDE ═════════════════════════════════════════════════ --}}
<div class="g-card">
    <div class="g-card-header"><h2>How to set up EHR for a partner — step by step</h2></div>
    <div class="g-card-body">
        <p style="font-size:.875rem;color:var(--g-muted);line-height:1.6;margin-bottom:1.5rem;">
            Follow these steps in order. Each partner gets its own independent setup — one partner's
            settings never affect another's.
        </p>

        <div class="g-steps">

            <div class="g-step">
                <div class="g-step-num">1</div>
                <div class="g-step-body">
                    <h3>Get the credentials from Healthie</h3>
                    <p>Log in to the partner's Healthie account and collect the following. You only need to do this once per partner.</p>
                    <div class="g-step-note">
                        <strong>What to collect:</strong><br>
                        • <strong>API Key</strong> — found in Healthie under Settings → Integrations → API Keys. It's a long string of letters and numbers.<br>
                        • <strong>Organization ID</strong> — found in Healthie org settings.<br>
                        • <strong>Provider ID</strong> — the Healthie user ID of the doctor or provider that chart notes will be assigned to.<br>
                        • <strong>Note Form ID</strong> — the ID of the "Free Text" charting template. Use the "Look up IDs" button on the edit screen to find this automatically (see Step 3).
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">2</div>
                <div class="g-step-body">
                    <h3>Open the partner in Admin and fill in the EHR section</h3>
                    <p>Go to <strong>Admin → Super Admin → Partners</strong>, find the partner, and click <strong>Edit</strong>.
                    Scroll down to the <em>Healthie EHR</em> section and fill in the fields.</p>
                    <div class="g-step-note">
                        Leave both <strong>Sandbox Validated</strong> and <strong>Push records to Healthie</strong> switched <em>off</em>
                        for now — you'll turn those on in Step 4 after testing.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">3</div>
                <div class="g-step-body">
                    <h3>Use "Look up Form & Group IDs" to find the Note Form ID</h3>
                    <p>Once the API key and endpoint are saved, a button appears on the edit screen:
                    <strong>"Look up Form & Group IDs from Healthie"</strong>. Click it to see a list of all available
                    charting forms in that Healthie account. Find the one called <strong>"Free Text"</strong>
                    and click <strong>Use</strong> — the Note Form ID field fills in automatically.</p>
                    <div class="g-step-note">
                        <strong>Why does this matter?</strong> The Note Form ID tells MEDAXIS which charting template
                        to use when creating records in Healthie. Without it, the clinical note may not appear
                        under the correct section in the patient's chart.
                    </div>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">4</div>
                <div class="g-step-body">
                    <h3>Save the settings and confirm everything looks correct</h3>
                    <p>Click <strong>Save Changes</strong>. The page will show a status badge at the top of the
                    Healthie section. If it says <strong>"Incomplete"</strong>, a message will tell you which
                    fields are still missing. Fix those before proceeding.</p>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">5</div>
                <div class="g-step-body">
                    <h3>Run a test push (optional but recommended)</h3>
                    <p>Before activating live pushes, you can submit a test case through the partner's storefront,
                    approve it in MEDAXIS, and check <strong>Admin → EHR Records</strong> to see the record
                    with status <em>Disabled</em> (meaning: built but not sent yet). This lets you verify the
                    payload looks correct before anything is actually transmitted.</p>
                </div>
            </div>

            <div class="g-step">
                <div class="g-step-num">6</div>
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
                        <div>Only tick these boxes after you have verified that records are going to the correct
                        Healthie account. Checking the wrong account, or using the wrong API key, will send
                        real patient data to the wrong organisation.</div>
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
                        It is stored encrypted in MEDAXIS and never displayed again after you save it.</td>
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
                        Found in Healthie → Settings → Organisation. Used to ensure records go to the correct account.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Default Provider ID</td>
                    <td><span class="g-field-req g-req-yes">Required</span></td>
                    <td>The Healthie ID of the provider (doctor or clinician) that new patient records will be assigned to by default.
                        In Healthie, each user has an ID — find it under Team Members in your Healthie account.
                        Chart notes will appear under this provider's name.</td>
                </tr>
                <tr>
                    <td class="g-field-name">Note Form ID</td>
                    <td><span class="g-field-req g-req-opt">Optional</span></td>
                    <td>Tells MEDAXIS which charting template to use when creating the clinical note in Healthie.
                        If left blank, the note still goes through but appears as a plain text entry rather than
                        a structured chart entry. Use the <strong>"Look up Form & Group IDs"</strong> button
                        on the edit screen to find this — look for the form called "Free Text".</td>
                </tr>
                <tr>
                    <td class="g-field-name">Default Group ID</td>
                    <td><span class="g-field-req g-req-opt">Optional</span></td>
                    <td>If the partner uses Healthie patient groups (e.g. to separate different programmes or care pathways),
                        new patients will automatically be added to this group.
                        Leave blank if groups aren't configured yet — this can be set at any time.</td>
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
                    <td>A safety confirmation that you have personally verified records are going to the correct
                        Healthie account without mixing with any other partner's data.
                        <strong>Tick this only after testing.</strong> Both this and "Push records" must be on before anything is sent.</td>
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
                    <span style="font-size:.86rem;color:var(--g-muted);line-height:1.55;">The partner's push is switched off. The record was built and stored (so you can inspect it) but nothing was sent to Healthie. To send it, enable the partner first, then retry the record.</span>
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
                'No — and this is intentional. Each partner must have its own Healthie API key. Sharing credentials would mean one partner\'s patients could appear in another\'s records, which is a compliance violation. The system will refuse to push if credentials are shared.',
            ],
            [
                'What happens if the API key is wrong or expired?',
                'The push will fail and the EHR Record will show status "Failed" with an error message explaining the Healthie API rejected the credentials. Update the API key on the partner\'s Edit screen and then manually retry the failed records.',
            ],
            [
                'A patient submitted through two different partners. Will they have two records in Healthie?',
                'Yes, intentionally. A patient who submits through Partner A and Partner B is treated as two separate Healthie clients — one in each partner\'s account. This prevents data from crossing between storefronts.',
            ],
            [
                'The "Push records" switch is on but records are still showing as Disabled. Why?',
                'Check that both the platform-level switches AND the partner-level switches are on. There are two layers: (1) the partner\'s own "Push records" and "Sandbox validated" checkboxes, and (2) platform-wide server settings. If the server settings are off, no pushes will go out regardless of partner settings — contact the technical team to check the server environment variables.',
            ],
            [
                'I clicked "Look up Form & Group IDs" but got an error.',
                'The lookup uses the API key and endpoint that are currently saved for the partner. Make sure you have already saved those two fields before clicking the button. If they are saved and you still get an error, verify the API key is correct and the endpoint URL is for the right environment (staging vs production).',
            ],
            [
                'How long does a push take?',
                'Usually under 5 seconds. MEDAXIS sends the request to Healthie immediately after an approval, and Healthie confirms receipt in the same response. If it takes longer, it may mean Healthie\'s API is slow — the system will still wait up to 30 seconds before marking it failed.',
            ],
            [
                'Can I see exactly what data was sent to Healthie?',
                'Yes. Click any EHR Record under Admin → EHR Records to open the detail view. It shows the complete payload that was built and sent, including all patient fields and the clinical note text.',
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
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.75rem;">Activation flags (two-layer gate)</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead>
                    <tr><th>Variable</th><th>Default</th><th>Staging</th><th>Purpose</th></tr>
                </thead>
                <tbody>
                    <tr><td><code class="g-code">EHR_ENABLED</code></td><td><code class="g-code">false</code></td><td><code class="g-code">true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Platform master switch — off by default everywhere.</td></tr>
                    <tr><td><code class="g-code">EHR_SANDBOX_VALIDATED</code></td><td><code class="g-code">false</code></td><td><code class="g-code">true</code></td><td style="font-size:.83rem;color:var(--g-muted);">Second gate — both must be true before any real push leaves the server.</td></tr>
                    <tr><td><code class="g-code">EHR_ADAPTER</code></td><td><code class="g-code">mock</code></td><td><code class="g-code">healthie</code></td><td style="font-size:.83rem;color:var(--g-muted);"><code class="g-code">mock</code> = no network calls. <code class="g-code">healthie</code> = live GraphQL.</td></tr>
                    <tr><td><code class="g-code">EHR_MAX_ATTEMPTS</code></td><td><code class="g-code">5</code></td><td><code class="g-code">5</code></td><td style="font-size:.83rem;color:var(--g-muted);">Retry ceiling for the <code class="g-code">ehr:retry</code> sweep.</td></tr>
                    <tr><td><code class="g-code">HEALTHIE_ENDPOINT</code></td><td><code class="g-code">staging-api…</code></td><td><em style="color:var(--g-muted);">(same)</em></td><td style="font-size:.83rem;color:var(--g-muted);">Can be overridden at server level; per-partner endpoint takes precedence.</td></tr>
                    <tr><td><code class="g-code">HEALTHIE_TIMEOUT</code></td><td><code class="g-code">30</code></td><td><code class="g-code">30</code></td><td style="font-size:.83rem;color:var(--g-muted);">HTTP timeout in seconds for GraphQL requests.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.75rem;">GraphQL mutation strategy</p>
            <div style="overflow-x:auto;">
            <table class="g-field-table">
                <thead><tr><th>Condition</th><th>Mutation</th><th>Result in Healthie</th></tr></thead>
                <tbody>
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
                        <td style="font-size:.83rem;color:var(--g-text2);">Client not found by email</td>
                        <td><code class="g-code">createClient</code> (first)</td>
                        <td style="font-size:.83rem;color:var(--g-muted);">New Healthie client · namespaced key in <code class="g-code">record_identifier</code> · <code class="g-code">dont_send_welcome: true</code></td>
                    </tr>
                    <tr>
                        <td style="font-size:.83rem;color:var(--g-text2);">Each vital metric</td>
                        <td><code class="g-code">createEntry</code> (per metric)</td>
                        <td style="font-size:.83rem;color:var(--g-muted);">Weight (lbs), Height (in.), BMI — best-effort, failures logged but never fail the record</td>
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
                    <tr><td><code class="g-code">app/Services/Ehr/HealthieEhrAdapter.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Live adapter — auth, findOrCreateClient, buildMutation, pushVitals, healthieLookup proxy.</td></tr>
                    <tr><td><code class="g-code">app/Services/EhrRecordService.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Outbox orchestrator — buildPayload, scopedPatientKey, recordApproval, push.</td></tr>
                    <tr><td><code class="g-code">app/Services/Ehr/EhrGatewayManager.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Resolves adapter for a partner — enforces dual-flag gate and isPushable().</td></tr>
                    <tr><td><code class="g-code">app/Services/Ehr/MockEhrAdapter.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">No-network mock, used when EHR_ADAPTER=mock. Safe default.</td></tr>
                    <tr><td><code class="g-code">app/Models/EhrRecord.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Outbox row — statuses: disabled · pending · sent · failed.</td></tr>
                    <tr><td><code class="g-code">app/Models/PartnerEhrSetting.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Per-partner credentials — isPushable(), missingValues(), authHeaders(). API key encrypted.</td></tr>
                    <tr><td><code class="g-code">app/Console/Commands/EhrRetry.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">Artisan command — retries failed records up to max_attempts, runs every 15 min.</td></tr>
                    <tr><td><code class="g-code">tests/Feature/EhrTenantSegregationTest.php</code></td><td style="font-size:.83rem;color:var(--g-muted);">10 unit tests covering all tenant segregation guarantees.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

        <div>
            <p style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--g-muted);margin-bottom:.5rem;">Tenant segregation enforcement</p>
            <ul style="font-size:.84rem;color:var(--g-muted);padding-left:1.25rem;line-height:2;margin:0;">
                <li>Each partner pushes with its own Healthie API key — no global key, no fallback.</li>
                <li>Patient matching uses namespaced key <code class="g-code">partner_uuid:patient_id</code> — never email, phone, or DOB alone.</li>
                <li>Three hard refusals enforce this at build-time, push-time, and send-time in the adapter.</li>
                <li>The <code class="g-code">EhrGatewayManager</code> resolves a different adapter instance per partner — a shared credential is architecturally impossible.</li>
            </ul>
        </div>

    </div>
</details>

</div>
@endsection
