{{--
    Slice A — MA-DOCPORTAL design language, scoped.

    Everything here is namespaced under `.ma-surface`, so dropping
    `<x-ma-styles />` at the top of a view and wrapping that view's markup in
    `<div class="ma-surface">…</div>` restyles ONLY that view. Admin and partner
    views are untouched. This is deliberately localized so their team can review
    the look on the clinician surface before adopting it portal-wide.

    Design tokens mirror MA-DOCPORTAL's globals.css (teal accent, soft cards,
    tinted status pills, generous spacing). No CDN assets — self-contained.
--}}
<style>
    .ma-surface {
        --ma-bg:      #f6f8fb;
        --ma-surface: #ffffff;
        --ma-border:  #e6eaf1;
        --ma-ink:     #0f172a;
        --ma-muted:   #64748b;
        --ma-accent:  #0d9488;
        --ma-accent-ink: #0b7c72;
        --ma-accent-bg:  #ecfdf5;
        --ma-radius:  14px;
        --ma-shadow:  0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
        color: var(--ma-ink);
        font-feature-settings: "cv02","cv03","cv04";
    }

    /* ── Cards ──────────────────────────────────────────────── */
    .ma-surface .card {
        border: 1px solid var(--ma-border);
        border-radius: var(--ma-radius);
        box-shadow: var(--ma-shadow);
        overflow: hidden;
    }
    .ma-surface .card + .card { margin-top: 1rem; }
    .ma-surface .card-header {
        background: var(--ma-surface);
        border-bottom: 1px solid var(--ma-border);
        padding: .9rem 1.1rem;
        font-weight: 600;
    }
    .ma-surface .card-body { padding: 1.1rem; }
    .ma-surface .card-footer { background: var(--ma-surface); border-top: 1px solid var(--ma-border); }

    /* ── Eyebrow + headings ─────────────────────────────────── */
    .ma-surface .ma-eyebrow {
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: .62rem;
        font-weight: 700;
        color: var(--ma-accent-ink);
    }
    .ma-surface .ma-title { font-size: 1.05rem; font-weight: 700; margin: .1rem 0 0; }
    .ma-surface .ma-sub   { color: var(--ma-muted); font-size: .85rem; margin: .15rem 0 0; }

    /* ── Metric row (MA demo-metric-grid) ───────────────────── */
    .ma-metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: .85rem;
        margin-bottom: 1.1rem;
    }
    .ma-metric {
        background: var(--ma-surface);
        border: 1px solid var(--ma-border);
        border-radius: var(--ma-radius);
        box-shadow: var(--ma-shadow);
        padding: .85rem 1rem;
    }
    .ma-metric .ma-metric-label { color: var(--ma-muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
    .ma-metric .ma-metric-value { font-size: 1.6rem; font-weight: 700; line-height: 1.15; margin-top: .15rem; }
    .ma-metric.accent .ma-metric-value { color: var(--ma-accent-ink); }

    /* ── Tables (MA review-grid) ────────────────────────────── */
    .ma-surface .table { margin: 0; }
    .ma-surface .table > thead th {
        background: #f8fafc;
        border-bottom: 1px solid var(--ma-border);
        color: var(--ma-muted);
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        padding: .6rem .75rem;
    }
    .ma-surface .table > tbody td { padding: .7rem .75rem; vertical-align: middle; border-color: #eef1f6; }
    .ma-surface .table-hover > tbody > tr:hover > * { background: var(--ma-accent-bg); }

    /* ── Pills (MA .pill) ───────────────────────────────────── */
    .ma-pill {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .15rem .55rem;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 600;
        line-height: 1.5;
        border: 1px solid transparent;
    }
    .ma-pill.green   { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .ma-pill.yellow  { background: #fef9c3; color: #854d0e; border-color: #fde68a; }
    .ma-pill.red     { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .ma-pill.neutral { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .ma-pill.accent  { background: var(--ma-accent-bg); color: var(--ma-accent-ink); border-color: #ccfbf1; }
    .ma-pill .ma-dot { width: .5rem; height: .5rem; border-radius: 999px; background: currentColor; }

    /* ── Buttons ────────────────────────────────────────────── */
    .ma-surface .btn-primary {
        background: var(--ma-accent);
        border-color: var(--ma-accent);
    }
    .ma-surface .btn-primary:hover { background: var(--ma-accent-ink); border-color: var(--ma-accent-ink); }
    .ma-surface .btn-outline-primary { color: var(--ma-accent-ink); border-color: #cbd5e1; }
    .ma-surface .btn-outline-primary:hover { background: var(--ma-accent); border-color: var(--ma-accent); color: #fff; }
    .ma-surface .form-control:focus,
    .ma-surface .form-select:focus { border-color: var(--ma-accent); box-shadow: 0 0 0 .18rem rgba(13,148,136,.15); }

    /* ── Triage legend ──────────────────────────────────────── */
    .ma-legend { display: flex; flex-wrap: wrap; gap: .5rem 1rem; font-size: .74rem; color: var(--ma-muted); }
    .ma-legend .ma-pill { font-size: .68rem; }

    /* ── Batch steps ────────────────────────────────────────── */
    .ma-batch-steps { list-style: none; padding: 0; margin: 0 0 .9rem; display: grid; gap: .5rem; }
    .ma-batch-steps li { display: flex; align-items: center; gap: .7rem; padding: .6rem .8rem; border: 1px solid var(--ma-border); border-radius: 11px; background: #fbfcff; }
    .ma-batch-steps li > div { flex: 1; min-width: 0; }
    .ma-batch-steps strong { display: block; font-size: .82rem; }
    .ma-batch-steps li > div > span { display: block; color: var(--ma-muted); font-size: .75rem; margin-top: .1rem; }
    .ma-batch-dot { flex: 0 0 auto; width: .55rem; height: .55rem; border-radius: 999px; background: #cbd5e1; }
    .ma-batch-steps li.done .ma-batch-dot { background: #16a34a; }
    .ma-batch-steps li.active .ma-batch-dot { background: #d9a400; }

    /* ── Health dots + observability grid ───────────────────── */
    .ma-health-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 999px; margin-right: .4rem; vertical-align: middle; }
    .ma-health-dot.green { background: #16a34a; } .ma-health-dot.yellow { background: #d9a400; } .ma-health-dot.red { background: #dc2626; }
    .ma-obs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
    .ma-obs-grid > div { border: 1px solid var(--ma-border); border-radius: 11px; padding: .6rem .8rem; background: #fbfcff; }
    .ma-obs-grid strong { display: block; font-size: 1.2rem; }
    .ma-obs-grid span { color: var(--ma-muted); font-size: .72rem; }

    /* ── Provider load bars (MA .provider-load) ─────────────── */
    .ma-provider-load { display: grid; gap: .8rem; }
    .ma-provider-load > div { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: .15rem .6rem; }
    .ma-provider-load .pl-name { font-size: .82rem; font-weight: 600; }
    .ma-provider-load .pl-num { font-size: .8rem; color: var(--ma-muted); }
    .ma-load-track { grid-column: 1 / -1; height: .5rem; border-radius: 999px; background: #eef1f6; overflow: hidden; }
    .ma-load-track > span { display: block; height: 100%; background: var(--ma-accent); border-radius: 999px; }
    .ma-load-track.warning > span { background: #dc2626; }

    /* ── Big-number stat grid (MA .exception-grid / report tiles) ── */
    .ma-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .7rem; }
    .ma-stat-grid > div { border: 1px solid var(--ma-border); border-radius: 11px; padding: .7rem .85rem; background: #fbfcff; }
    .ma-stat-grid strong { display: block; font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    .ma-stat-grid span { color: var(--ma-muted); font-size: .74rem; }

    /* ── Coverage list (MA .coverage-list) ──────────────────── */
    .ma-coverage-list { display: grid; gap: .55rem; }
    .ma-coverage-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; border: 1px solid var(--ma-border); border-radius: 11px; padding: .6rem .8rem; background: #fbfcff; }
    .ma-coverage-row strong { display: block; font-size: .85rem; }
    .ma-coverage-row .cov-detail { color: var(--ma-muted); font-size: .75rem; }

    /* ── State policy matrix (MA .matrix-grid) ──────────────── */
    .ma-matrix { display: grid; gap: .5rem; }
    .ma-matrix-head, .ma-matrix-row { display: grid; grid-template-columns: 1.5fr .95fr 1fr 1.3fr; gap: .6rem; align-items: center; }
    .ma-matrix-head { font-size: .64rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ma-muted); font-weight: 700; padding: 0 .8rem; }
    .ma-matrix-row { border: 1px solid var(--ma-border); border-radius: 11px; padding: .6rem .8rem; background: #fbfcff; }
    .ma-matrix-row strong { display: block; font-size: .85rem; }
    .ma-matrix-row .mx-detail { color: var(--ma-muted); font-size: .73rem; }
    .ma-matrix-meta { color: var(--ma-muted); font-size: .71rem; line-height: 1.35; }

    /* ── Patient inbox (MA .inbox-list) ─────────────────────── */
    .ma-inbox-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
    .ma-inbox-thread { display: flex; gap: .7rem; align-items: flex-start; border: 1px solid var(--ma-border); border-radius: 11px; padding: .6rem .8rem; background: #fff; }
    .ma-inbox-thread.unread { background: var(--ma-accent-bg); border-color: #ccfbf1; }
    .ma-inbox-avatar { flex: 0 0 auto; width: 2rem; height: 2rem; border-radius: 999px; background: #e2e8f0; color: #475569; font-size: .72rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
    .ma-inbox-body { flex: 1; min-width: 0; }
    .ma-inbox-top { display: flex; align-items: center; gap: .5rem; }
    .ma-inbox-top strong { font-size: .84rem; }
    .ma-inbox-waiting { margin-left: auto; color: var(--ma-muted); font-size: .72rem; white-space: nowrap; }
    .ma-inbox-snippet { display: block; color: var(--ma-muted); font-size: .78rem; margin-top: .15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* ── Quick-review panel (MA .quick-review-grid) ─────────── */
    .ma-quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.1rem; }
    .ma-subheading { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ma-muted); font-weight: 700; margin: .1rem 0 .5rem; }
    .ma-summary-list, .ma-finding-list, .ma-simple-list { list-style: none; margin: 0 0 .6rem; padding: 0; display: grid; gap: .4rem; }
    .ma-summary-list li { font-size: .82rem; padding-left: 1rem; position: relative; }
    .ma-summary-list li::before { content: "•"; position: absolute; left: .15rem; color: var(--ma-accent-ink); font-weight: 700; }
    .ma-finding-list li { font-size: .82rem; display: flex; align-items: flex-start; gap: .45rem; }
    .ma-finding-dot { flex: 0 0 auto; width: .5rem; height: .5rem; border-radius: 999px; margin-top: .35rem; background: #cbd5e1; }
    .ma-finding-dot.green { background: #16a34a; } .ma-finding-dot.yellow { background: #d9a400; } .ma-finding-dot.red { background: #dc2626; }
    .ma-source-answers { margin: .5rem 0 0; display: grid; gap: .45rem; }
    .ma-source-answers > div { border-top: 1px solid var(--ma-border); padding-top: .45rem; }
    .ma-source-answers dt { font-size: .74rem; font-weight: 600; color: var(--ma-ink); margin: 0; }
    .ma-source-answers dd { margin: .1rem 0 0; font-size: .8rem; color: var(--ma-muted); }
    .ma-honesty { font-size: .74rem; color: var(--ma-muted); margin: .35rem 0 .55rem; }
    .ma-surface .ma-btn-danger { color: #991b1b; border-color: #fecaca; }
    .ma-surface .ma-btn-danger:hover { background: #dc2626; border-color: #dc2626; color: #fff; }

    /* ── Simple bar chart (report volume) ───────────────────── */
    .ma-barchart { display: grid; gap: .55rem; }
    .ma-barchart > div { display: grid; grid-template-columns: 4.5rem 1fr 2.5rem; align-items: center; gap: .6rem; font-size: .8rem; }
    .ma-barchart .bc-val { text-align: right; color: var(--ma-muted); }
    .ma-bar { height: .7rem; border-radius: 999px; background: #eef1f6; overflow: hidden; }
    .ma-bar > span { display: block; height: 100%; border-radius: 999px; background: var(--ma-accent); }
    .ma-bar.green > span { background: #16a34a; } .ma-bar.yellow > span { background: #d9a400; } .ma-bar.red > span { background: #dc2626; }

    /* ── Holds chips + reason codes ─────────────────────────── */
    .ma-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ma-reason-codes { list-style: none; margin: 0; padding: 0; display: grid; gap: .35rem; }
    .ma-reason-codes li { font-size: .78rem; color: var(--ma-muted); display: flex; align-items: center; gap: .4rem; }
    .ma-reason-codes li::before { content: ""; width: .35rem; height: .35rem; border-radius: 999px; background: var(--ma-accent); flex: 0 0 auto; }

    /* ── Responsive overrides ────────────────────────────────── */
    @media (max-width: 575.98px) {
        /* Stack obs-grid to single column on mobile */
        .ma-obs-grid { grid-template-columns: 1fr; }

        /* Scroll matrix horizontally rather than overflow on mobile */
        .ma-matrix { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .ma-matrix-head, .ma-matrix-row { min-width: 480px; }

        /* Stack triage banner vertically on mobile */
        .ma-surface .card-body.d-flex { flex-direction: column; align-items: flex-start !important; }
        .ma-surface .card-body.d-flex .ms-auto { margin-left: 0 !important; max-width: 100% !important; }

        /* Tighten metric grid on very small screens */
        .ma-metric-grid { grid-template-columns: 1fr 1fr; }
        .ma-metric .ma-metric-value { font-size: 1.3rem; }

        /* Quick-review grid: single column */
        .ma-quick-grid { grid-template-columns: 1fr; }
    }
</style>
