@verbatim
/* ---------------------------------------------------------------------------
   Design tokens lifted verbatim from MA-DOCPORTAL apps/portal/app/globals.css
   so this preview and the demo you already looked at share one palette.
   --------------------------------------------------------------------------- */
:root{
  --ink:#172033; --muted:#647188; --soft-muted:#8792a3;
  --line:#e5e9f0; --line-strong:#d8deea;
  --bg:#f5f7fb; --card:#ffffff; --surface:#fbfcfe;
  --accent:#2563eb; --accent-ink:#1d4ed8;
  --green:#17834e; --green-bg:#eaf8ef;
  --yellow:#9a6500; --yellow-bg:#fff7df;
  --red:#b42318; --red-bg:#fff0ef;
  --blue-bg:#eaf1ff;
  --shadow:0 14px 45px rgba(26,40,73,.08);
  --rail:264px; --rail-collapsed:68px;
}
*{box-sizing:border-box}
html{background:var(--bg)}
/* Type. MA names Inter first but never self-hosts it, so in practice it falls
   through to the platform UI face. Putting -apple-system first makes that
   explicit: SF Pro on Apple, Segoe UI on Windows, which is the look Devin asked
   for. Optical sizing, tabular numerals in data, and the tightened tracking on
   headings are the parts that actually read as "Apple" rather than the family
   name alone. */
body{
  margin:0; color:var(--ink);
  background:radial-gradient(circle at top left,#fff 0,var(--bg) 42%,#f3f6fb 100%);
  font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI Variable Text","Segoe UI",Inter,ui-sans-serif,system-ui,sans-serif;
  font-size:15px; line-height:1.47; letter-spacing:-.006em;
  -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
  font-variant-numeric:tabular-nums;
}
/* Display face for headings, and progressively tighter tracking as size grows,
   which is the single biggest tell of the Apple type ramp. */
h1,h2,h3,.metric-value,.brand-text{
  font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI Variable Display","Segoe UI",Inter,sans-serif;
}
h1{letter-spacing:-.032em}
h2{letter-spacing:-.024em}
h3{letter-spacing:-.016em}
/* Numbers in tables, metrics and counts align in columns. */
.tbl td,.review-grid td,.metric-value,.nav-count,.months-grid select{font-variant-numeric:tabular-nums}
/* Uppercase micro-labels need tracking OPENED, not tightened. */
.eyebrow,.kicker,.subheading,.field label,.months-head label,.rx-meta dt,.tbl th,.review-grid th,.nav-section{
  letter-spacing:.055em;
}
a{color:inherit;text-decoration:none}
button,input,select{font:inherit}
button{cursor:pointer}

/* ---------------------------------------------------------------- layout */
.app{display:grid;grid-template-columns:var(--rail) minmax(0,1fr);min-height:100vh;transition:grid-template-columns .18s ease}
.app.collapsed{grid-template-columns:var(--rail-collapsed) minmax(0,1fr)}

/* ---------------------------------------------------------------- sidebar */
.side{
  position:sticky;top:0;height:100vh;display:flex;flex-direction:column;
  background:#fff;border-right:1px solid var(--line);overflow:hidden;
}
.side-head{display:flex;align-items:center;gap:9px;padding:16px 14px;border-bottom:1px solid var(--line);min-height:61px}
.brand-mark{
  display:grid;place-items:center;width:28px;height:28px;border-radius:9px;color:#fff;
  background:linear-gradient(145deg,#2e70ed,#1747b6);font-size:13px;font-weight:800;
  box-shadow:0 5px 13px rgba(37,99,235,.28);flex:0 0 auto;
}
.brand-text{font-weight:780;letter-spacing:-.03em;font-size:14px;white-space:nowrap}
.side-scroll{overflow-y:auto;overflow-x:hidden;padding:10px 10px 18px;flex:1}
.nav-section{
  display:flex;align-items:center;justify-content:space-between;width:100%;
  background:none;border:0;padding:13px 10px 6px;color:var(--soft-muted);
  font-size:10px;font-weight:780;letter-spacing:.1em;text-transform:uppercase;
}
.nav-section .chev{transition:transform .16s ease;font-size:9px}
.nav-section.closed .chev{transform:rotate(-90deg)}
.nav-group{display:grid;overflow:hidden}
.nav-group.closed{display:none}
.nav-link{
  display:flex;align-items:center;gap:10px;padding:8px 10px;margin:1px 0;
  border-radius:9px;color:var(--muted);font-size:13px;font-weight:650;
  white-space:nowrap;border:0;background:none;width:100%;text-align:left;
}
.nav-link:hover{background:#f4f7fc;color:var(--ink)}
.nav-link.active{background:var(--blue-bg);color:var(--accent-ink);font-weight:730}
.nav-link.sub{padding-left:32px;font-size:12.5px}
.nav-ico{width:18px;flex:0 0 auto;display:grid;place-items:center;font-size:13px;opacity:.85}
.nav-badge{margin-left:auto;background:var(--yellow-bg);color:var(--yellow);border-radius:99px;padding:1px 7px;font-size:10px;font-weight:780}
/* Work-queue counts sit flush right, like the reference. A zero is greyed
   rather than hidden, so an empty queue still reads as "checked, nothing here". */
.nav-count{margin-left:auto;color:var(--muted);font-size:11.5px;font-weight:730;font-variant-numeric:tabular-nums}
.nav-count.zero{color:#b6bdc9;font-weight:650}
.nav-link.active .nav-count{color:var(--accent-ink)}
.side-foot{border-top:1px solid var(--line);padding:11px 12px;display:flex;align-items:center;gap:9px}
.avatar{width:28px;height:28px;border-radius:99px;background:#e7edf8;color:#41537a;display:grid;place-items:center;font-size:11px;font-weight:760;flex:0 0 auto}
.who{min-width:0}
.who strong{display:block;font-size:12.5px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.who span{display:block;color:var(--soft-muted);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* collapsed rail: icons only */
.app.collapsed .brand-text,
.app.collapsed .nav-link span.lbl,
.app.collapsed .nav-section span,
.app.collapsed .nav-badge,
.app.collapsed .who{display:none}
.app.collapsed .nav-section{justify-content:center;padding:12px 0 4px}
.app.collapsed .nav-link{justify-content:center;padding:9px 0}
.app.collapsed .nav-link.sub{padding-left:0}
.app.collapsed .side-head{justify-content:center;padding:16px 0}
.app.collapsed .side-foot{justify-content:center}
.app.collapsed .nav-group.closed{display:grid}

/* ---------------------------------------------------------------- topbar */
.main{min-width:0;display:flex;flex-direction:column}
/* Wraps rather than overflows. The tier switcher is a second control on this
   row, and at 390px the three of them do not fit on one line: without wrap the
   page picked up 47px of horizontal scroll. */
.topbar{
  display:flex;align-items:center;gap:14px;padding:12px 24px;flex-wrap:wrap;
  border-bottom:1px solid var(--line);background:rgba(255,255,255,.82);
  backdrop-filter:blur(7px);position:sticky;top:0;z-index:5;min-height:61px;
}
.icon-btn{
  display:grid;place-items:center;width:34px;height:34px;border-radius:9px;
  border:1px solid var(--line-strong);background:#fff;color:var(--muted);font-size:14px;
}
.icon-btn:hover{background:#f7f9fc;color:var(--ink)}
.role-switcher{display:flex;background:rgba(255,255,255,.78);border:1px solid var(--line);border-radius:12px;padding:4px;gap:3px}
.role-button{padding:7px 12px;border-radius:8px;color:var(--muted);font-size:12px;font-weight:700;border:0;background:none}
.role-button.active{color:var(--ink);background:#fff;box-shadow:0 1px 4px rgba(26,40,73,.12)}

/* Admin tier switcher. Visually subordinate to the role switcher on purpose:
   it selects an identity WITHIN the admin portal, not a different portal. */
.scope-switcher{display:flex;align-items:center;gap:3px;flex-wrap:wrap;
  border:1px dashed var(--line-strong);border-radius:12px;padding:4px 4px 4px 10px;background:rgba(255,255,255,.5)}
.scope-switcher[hidden]{display:none}
.scope-label{color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;margin-right:4px}
.scope-button{padding:6px 10px;border-radius:8px;color:var(--muted);font-size:12px;font-weight:700;border:0;background:none;white-space:nowrap}
.scope-button:hover{color:var(--ink)}
.scope-button.active{color:#fff;background:var(--accent)}
@media (max-width:820px){
  /* On a phone the tier switcher takes its own line and the banner drops to the
     end, so nothing is squeezed off the side. */
  .scope-switcher{order:3;width:100%;margin-left:0}
  .demo-banner{order:4;margin-left:0}
  .scope-button{padding:6px 8px;font-size:11px}
}

/* Banner explaining what the current tier can and cannot see. */
.scope-note{display:flex;gap:11px;align-items:flex-start;margin-bottom:18px;padding:12px 15px;
  border:1px solid var(--line);border-left:3px solid var(--accent);border-radius:10px;background:#fff;font-size:13px;line-height:1.55}
.scope-note.restricted{border-left-color:#c8871b;background:#fffdf7}
.scope-note.blocked{border-left-color:#c0392f;background:#fff8f7}
.scope-note strong{display:block;margin-bottom:2px}
.scope-note .sn-ico{font-size:15px;line-height:1.3;flex:0 0 auto}
.scope-note .sn-body{color:var(--muted)}
.scope-note .sn-body b{color:var(--ink);font-weight:750}

.empty-scope{text-align:center;color:var(--muted);padding:26px 14px;font-size:13px}

/* Medications: drug > variants > partners */
.cat-summary{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;
  padding:12px 16px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:13px;color:var(--muted)}
.cat-summary strong{color:var(--ink);font-weight:780}
.cat-summary .button-primary{margin-left:auto}
.cat-panel{margin-bottom:16px}
.cat-count{display:inline-grid;place-items:center;min-width:21px;height:21px;padding:0 6px;margin-left:7px;
  border-radius:999px;background:var(--bg);border:1px solid var(--line);color:var(--muted);
  font-size:11px;font-weight:780;vertical-align:middle}
.drug{padding:16px 18px;border-top:1px solid var(--line)}
.drug-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.drug-head strong{font-size:15px;margin-right:8px}
.drug-head .pill{margin-right:5px}
.drug-row{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;padding:9px 0}
.drug-row .subheading{flex:0 0 118px;padding-top:5px}
.partner-chips{display:flex;gap:7px;flex-wrap:wrap;flex:1;min-width:0}
.partner-chip{display:inline-flex;align-items:center;gap:7px;padding:5px 10px;border-radius:9px;
  border:1px solid var(--line);background:#fff;font-size:12px;font-weight:650;color:var(--ink);white-space:nowrap}
.partner-chip.add{border-style:dashed;color:var(--muted);font-weight:600}
.partner-chip.add:hover{color:var(--accent);border-color:var(--accent)}

/* Permission matrix */
.perm-tbl td.yes{color:#1d7a4d;font-weight:750}
.perm-tbl td.no{color:#b03a2e;font-weight:750}
.perm-note{margin-top:14px;padding:12px 15px;border:1px solid var(--line);border-radius:10px;
  background:#fffdf7;border-left:3px solid #c8871b;font-size:13px;line-height:1.55;color:var(--muted)}
.perm-note b{color:var(--ink)}
.demo-banner{
  display:flex;align-items:center;gap:8px;margin-left:auto;color:var(--muted);font-size:12px;
  padding:6px 11px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.78);white-space:nowrap;
}
.demo-dot{width:7px;height:7px;border-radius:999px;background:#31a56d;box-shadow:0 0 0 3px rgba(49,165,109,.13);flex:0 0 auto}

/* ---------------------------------------------------------------- content */
.wrap{padding:26px 24px 60px;max-width:1600px;width:100%}
.page-head{margin-bottom:22px}
.eyebrow{color:var(--muted);font-size:11px;font-weight:780;letter-spacing:.1em;text-transform:uppercase}
.page-head h1{font-size:29px;letter-spacing:-.042em;line-height:1.1;margin:7px 0 7px}
.page-head p{color:var(--muted);margin:0;max-width:760px;font-size:14px}
.metric-grid{display:grid;gap:16px;grid-template-columns:repeat(4,1fr);margin-bottom:20px}
.metric{background:rgba(255,255,255,.86);border:1px solid var(--line);border-radius:17px;padding:17px;box-shadow:0 1px 2px rgba(26,40,73,.03)}
.metric-value{font-size:29px;line-height:1;font-weight:790;letter-spacing:-.045em;margin:9px 0 7px}
.metric-note{color:var(--soft-muted);font-size:12px}
.panel{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);overflow:hidden}
.panel-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding:19px 20px 15px}
.panel-heading h2{margin:4px 0;font-size:18px;letter-spacing:-.028em}
.panel-heading p{color:var(--muted);margin:0;font-size:13px}
.toolbar{
  display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 20px;
  border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:#fcfdff;
  font-size:12px;color:var(--muted);flex-wrap:wrap;
}
.tbl-scroll{overflow-x:auto}
table.tbl{width:100%;border-collapse:separate;border-spacing:0;background:#fff;min-width:640px}
.tbl th{
  font-weight:780;font-size:10px;color:var(--muted);letter-spacing:.075em;text-transform:uppercase;
  text-align:left;padding:11px 14px;background:#fbfcfe;border-bottom:1px solid var(--line);white-space:nowrap;
}
.tbl td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:13px;color:#263248;white-space:nowrap}
.tbl tbody tr:last-child td{border-bottom:0}
.tbl tbody tr:hover td{background:#fafcff}
.pill{display:inline-flex;align-items:center;white-space:nowrap;padding:4px 9px;border-radius:99px;font-size:11px;font-weight:750;background:#eef4ff;color:#245ed8}
.pill.green{background:var(--green-bg);color:var(--green)}
.pill.yellow{background:var(--yellow-bg);color:var(--yellow)}
.pill.red{background:var(--red-bg);color:var(--red)}
.pill.neutral{background:#f0f3f8;color:#536175}
.button{display:inline-flex;justify-content:center;align-items:center;gap:8px;border-radius:10px;padding:9px 14px;font-weight:740;font-size:13px;border:1px solid transparent;background:var(--accent);color:#fff;box-shadow:0 7px 16px rgba(37,99,235,.16)}
.button:hover{background:var(--accent-ink)}
.button.sec{background:#fff;border-color:var(--line-strong);color:var(--ink);box-shadow:none}
.button.sec:hover{background:#f7f9fc}
.two{display:grid;grid-template-columns:1.4fr .6fr;gap:16px;margin-top:16px}
.stub{padding:34px 20px;text-align:center;color:var(--soft-muted);font-size:13px}
.stub strong{display:block;color:var(--ink);font-size:14px;margin-bottom:6px}
.note{border:1px solid #dce7fb;background:#f5f8ff;color:#47607f;padding:12px 14px;border-radius:12px;font-size:12.5px;margin-top:18px}
/* ---------------------------------------------------------------------------
   Provider review queue. Ported from MA-DOCPORTAL's practitioner demo:
   apps/portal/components/demo/PractitionerDemo.tsx plus the matching rules in
   apps/portal/app/globals.css. Same grid, same pinned columns, same drawer.
   --------------------------------------------------------------------------- */
.button-primary,.button-secondary,.button-danger{
  display:inline-flex;justify-content:center;align-items:center;gap:8px;border-radius:10px;
  padding:9px 14px;font-weight:740;font-size:13px;border:1px solid transparent;
  transition:transform .15s ease,box-shadow .15s ease,background .15s ease;
}
.button-primary{background:var(--accent);color:#fff;box-shadow:0 7px 16px rgba(37,99,235,.16)}
.button-primary:hover:not(:disabled){transform:translateY(-1px);background:var(--accent-ink)}
.button-primary:disabled{cursor:not-allowed;opacity:.5;transform:none}
.button-secondary{background:#fff;border-color:var(--line-strong);color:var(--ink)}
.button-secondary:hover{background:#f7f9fc}
.button-danger{background:#fff;border-color:#f0c3c1;color:var(--red)}
.button-danger:hover{background:var(--red-bg)}
.full-width{width:100%;margin-top:7px}

.queue-panel{padding:0;overflow:hidden}
.queue-actions{display:flex;gap:9px;flex-wrap:wrap}
.queue-toolbar{
  display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 20px;
  border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:#fcfdff;
  font-size:12px;color:var(--muted);flex-wrap:wrap;
}
.queue-count strong{color:var(--ink)}
.select-all{display:flex;gap:7px;align-items:center;color:var(--ink);font-weight:650;cursor:pointer}
.review-grid-scroll{overflow:auto}
table.review-grid{width:100%;border-collapse:separate;border-spacing:0;background:#fff;min-width:1800px}
.review-grid th{
  font-weight:780;font-size:10px;line-height:1.2;color:var(--muted);letter-spacing:.075em;
  text-transform:uppercase;text-align:left;padding:11px 12px;background:#fbfcfe;
  border-bottom:1px solid var(--line);position:sticky;top:0;z-index:1;white-space:nowrap;
}
.review-grid td{padding:11px 12px;border-bottom:1px solid var(--line);font-size:12px;color:#263248;vertical-align:middle;white-space:nowrap}
.review-grid tbody tr:last-child td{border-bottom:0}
.review-grid tr{cursor:pointer}
.review-grid tr:hover td{background:#fafcff}
.review-grid tr.selected-row td{background:#f2f6ff}
/* Pinned first four columns, so triage and identity stay on screen while you
   scroll the twenty-column grid sideways. */
.review-grid th.pin,.review-grid td.pin{position:sticky;z-index:2;background:#fff}
.review-grid th.pin{z-index:3;background:#fbfcfe}
.review-grid .pin-select{left:0;min-width:44px;width:44px}
.review-grid .pin-triage{left:44px;min-width:96px;width:96px}
.review-grid .pin-time{left:140px;min-width:110px;width:110px}
.review-grid .pin-name{left:250px;min-width:170px;width:170px;box-shadow:6px 0 8px -6px rgba(16,24,40,.14)}
.review-grid tr:hover td.pin{background:#fafcff}
.review-grid tr.selected-row td.pin{background:#f2f6ff}
/* Compact density. Twenty columns need ~2017px; collapsing the rail only buys
   196px, so on a 1440 screen six columns stay off-screen either way. This trades
   padding and type size for columns.

   Collapsing the rail now switches the grid into compact automatically, which is
   what "closing the toolbar should expand it" actually has to mean: the reclaimed
   196px is worth two columns on its own, and worth six once the padding shrinks
   with it. Ticking Compact by hand still works independently. */
.app.collapsed .review-grid,
.review-grid.compact{min-width:0}
.app.collapsed .review-grid th{padding:8px 7px;font-size:9.5px}
.app.collapsed .review-grid td{padding:7px 7px;font-size:11.5px}
.app.collapsed .review-grid .pin-select{min-width:34px;width:34px}
.app.collapsed .review-grid .pin-triage{left:34px;min-width:74px;width:74px}
.app.collapsed .review-grid .pin-time{left:108px;min-width:82px;width:82px}
.app.collapsed .review-grid .pin-name{left:190px;min-width:132px;width:132px}
.app.collapsed .review-grid .batch-reason{max-width:150px;font-size:10px}
.review-grid.compact th{padding:8px 7px;font-size:9.5px}
.review-grid.compact td{padding:7px 7px;font-size:11.5px}
.review-grid.compact .pin-triage{left:34px;min-width:74px;width:74px}
.review-grid.compact .pin-time{left:108px;min-width:82px;width:82px}
.review-grid.compact .pin-name{left:190px;min-width:132px;width:132px}
.review-grid.compact .pin-select{min-width:34px;width:34px}
.review-grid.compact .batch-reason{max-width:150px;font-size:10px}
.density{display:flex;align-items:center;gap:7px;color:var(--muted);font-weight:650;cursor:pointer}

/* --------------------------------------------------- approval / case review */
.modal-back{position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:60;display:grid;place-items:center;padding:24px}
/* ONE scrolling surface, not a pane inside a pane (Devin msg 2079). The right
   column used to have its own scrollbar and cut Administration frequency and
   Duration off the bottom on a 4-month case. The modal itself is now the only
   scroller, and the header and footer stay put, so Submit is never scrolled
   away. The field reflow below is what makes it FIT at 1440x950 so this scroll
   should not engage at all. */
.modal{background:#fff;border-radius:18px;box-shadow:0 30px 90px rgba(16,24,40,.3);width:min(1100px,100%);max-height:92vh;display:flex;flex-direction:column;overflow:auto}
.modal-head{display:flex;align-items:center;gap:16px;padding:18px 22px;border-bottom:1px solid var(--line);background:#fbfcfe;flex-wrap:wrap;position:sticky;top:0;z-index:3;flex:0 0 auto}
.modal-head h2{margin:0;font-size:19px;letter-spacing:-.03em}
.modal-who{display:flex;align-items:center;gap:11px}
.modal-avatar{width:40px;height:40px;border-radius:99px;background:#e7edf8;color:#41537a;display:grid;place-items:center;font-size:13px;font-weight:770;flex:0 0 auto}
.modal-demo{font-size:13px;font-weight:750}
.modal-demo span{display:block;color:var(--soft-muted);font-size:11.5px;font-weight:600}
.modal-facts{display:flex;gap:22px;margin-left:auto;flex-wrap:wrap}
.modal-facts div{font-size:11.5px}
.modal-facts dt{color:var(--muted);margin:0 0 2px}
.modal-facts dd{margin:0;font-weight:700}
.modal-body{display:grid;grid-template-columns:.85fr 1.15fr;gap:0;overflow:visible;min-height:0}
.modal-left{padding:20px;border-right:1px solid var(--line);background:#fcfdff}
.modal-right{padding:20px;display:grid;gap:14px;align-content:start}
.rx-meta{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
.rx-meta dt{color:var(--muted);font-size:11px;font-weight:760;letter-spacing:.06em;text-transform:uppercase;margin:0 0 4px}
.rx-meta dd{margin:0;font-size:13px;font-weight:650}
.flags-panel{border:1px solid #f2c9c4;background:#fff5f4;border-radius:14px;padding:15px;margin-top:4px}
.flags-panel.clean{border-color:#c9e7d5;background:#f2fbf6}
.flags-title{font-weight:790;font-size:13px;color:var(--red);margin:0 0 10px}
.flags-panel.clean .flags-title{color:var(--green)}
.flags-grid{display:grid;gap:7px}
.flags-grid > div{display:grid;grid-template-columns:130px 1fr;gap:10px;font-size:12px}
.flags-grid dt{color:#8a5a55;margin:0}
.flags-panel.clean .flags-grid dt{color:#4d7a62}
.flags-grid dd{margin:0;font-weight:650;color:#2c3444}

/* One tab per medication the storefront actually requested. */
.med-tabs{display:flex;gap:6px;flex-wrap:wrap}
.med-tab{border:1px solid var(--line-strong);background:#fff;border-radius:999px;padding:7px 13px;font-size:12.5px;font-weight:730;color:var(--muted);display:inline-flex;align-items:center;gap:7px}
.med-tab.active{background:var(--blue-bg);border-color:#bcd2f8;color:var(--accent-ink)}
.med-tab .dot{width:7px;height:7px;border-radius:99px;background:#cfd6e2}
.med-tab .dot.approve{background:#2ca66c}.med-tab .dot.deny{background:#d44e44}.med-tab .dot.none{background:#a8b1c0}
.decision-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.decision-btn{border-radius:10px;padding:9px 18px;font-weight:750;font-size:13px;border:1px solid var(--line-strong);background:#fff;color:var(--ink)}
.decision-btn.approve.on{background:#17834e;border-color:#17834e;color:#fff}
.decision-btn.deny.on{background:var(--red);border-color:var(--red);color:#fff}
.decision-btn.none.on{background:#5b6678;border-color:#5b6678;color:#fff}
.field{display:grid;gap:6px}
.field label{font-size:11px;font-weight:760;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
.field label .req{color:var(--red)}
/* Controls inherit the page face rather than the browser default, which is what
   makes a form stop looking like a form and start looking like the product. */
/* Text-entry controls only. A bare `.modal input` here also caught the
   checkboxes and painted them as full-width dropdowns with a chevron. */
.field select,.field input[type=text],.modal select,.modal input[type=text]{
  width:100%;padding:10px 11px;border-radius:10px;border:1px solid var(--line-strong);
  background:#fff;font-size:13px;color:var(--ink);font-family:inherit;letter-spacing:inherit;
  appearance:none;-webkit-appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5 6 6.5l5-5' fill='none' stroke='%23647188' stroke-width='1.6' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 11px center;background-size:11px;padding-right:32px;
}
.field select:focus-visible,.decision-btn:focus-visible,.button-primary:focus-visible,
.button-secondary:focus-visible,.med-tab:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.field input[type=checkbox],.check-line input,.select-all input,.density input{accent-color:var(--accent)}
.field select:disabled{background:#f4f6fa;color:var(--soft-muted)}
.field.bad select{border-color:#e6a29c;background:#fff8f7}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.check-line{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink)}
.check-stack{display:grid;gap:9px;align-content:end;padding-bottom:9px}
.term-warn{font-size:11.5px;color:var(--yellow);margin:0}
.months{display:grid;gap:8px}
.months-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap}
.months-head label{font-size:11px;font-weight:760;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
.months-note{font-size:11.5px;color:var(--soft-muted)}
.months-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}
.months-grid .field label{font-size:11px;color:var(--accent-ink);font-weight:780}
.modal-foot{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:15px 22px;border-top:1px solid var(--line);background:#fbfcfe;flex-wrap:wrap;position:sticky;bottom:0;z-index:3;flex:0 0 auto}
.foot-status{font-size:12px;color:var(--muted)}
.foot-status strong{color:var(--ink)}
.foot-actions{display:flex;gap:9px;margin-left:auto}
@media (max-width:900px){.modal-body{grid-template-columns:1fr}.modal-left{border-right:0;border-bottom:1px solid var(--line)}.field-row,.rx-meta{grid-template-columns:1fr}}
.patient-link{background:none;border:0;padding:0;color:var(--accent-ink);font-size:inherit;font-weight:700;cursor:pointer}
.batch-cell .batch-reason{margin-top:4px;font-size:11px;color:var(--soft-muted);white-space:normal;max-width:230px}
.allergy-detail-wrap{position:relative;display:inline-block}
.allergy-flag{background:none;border:0;padding:0;cursor:pointer;color:var(--yellow);font-weight:770}
.allergy-tooltip{
  display:none;position:absolute;bottom:calc(100% + 6px);left:0;z-index:4;width:220px;
  white-space:normal;background:#1d2433;color:#fff;font-size:11px;line-height:1.45;padding:8px 10px;border-radius:8px;
}
.allergy-detail-wrap:hover .allergy-tooltip,.allergy-flag:focus + .allergy-tooltip{display:block}
.notice{border:1px solid #dce7fb;background:#f5f8ff;color:#47607f;padding:13px 14px;border-radius:12px;font-size:12.5px}
.queue-notice{margin:12px 20px 18px}
.audit-verb{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px;font-weight:620;color:#31436a;background:#f2f5fa;border:1px solid var(--line);border-radius:6px;padding:2px 6px}

.quick-review{padding:20px;margin-top:16px}
.quick-pills{display:flex;gap:6px}
.vt-bar{display:flex;align-items:center;gap:6px;margin-bottom:10px;border-bottom:1px solid var(--line);padding-bottom:8px}
.vt-btn{padding:4px 12px;border-radius:6px;border:1px solid var(--line);background:#fff;color:var(--muted);font-size:12px;font-weight:650;cursor:pointer;transition:background .1s,color .1s}
.vt-btn:hover{background:var(--blue-bg);color:var(--accent-ink)}
.vt-btn.active{background:var(--blue-bg);color:var(--accent-ink);border-color:transparent}
.vt-panel{padding-top:4px}
@media (prefers-color-scheme:dark){.vt-btn{background:var(--surface)}}
:root[data-theme="dark"] .vt-btn{background:var(--surface)}
:root[data-theme="light"] .vt-btn{background:#fff}
.demo-chips{display:flex;flex-wrap:wrap;gap:5px;margin:5px 0 6px}
.demo-chip{display:inline-flex;align-items:center;padding:2px 9px;border-radius:6px;font-size:11px;font-weight:650;background:var(--blue-bg,#eef4ff);color:var(--soft-muted);border:1px solid rgba(37,99,235,.1)}
.demo-chip.chip-verified{background:var(--green-bg);color:var(--green);border-color:transparent}
.demo-chip.chip-unverified{background:var(--yellow-bg);color:var(--yellow);border-color:transparent}
.quick-review-grid{display:grid;grid-template-columns:1.25fr 1.15fr .75fr;gap:20px}
.subheading{color:var(--soft-muted);font-size:11px;font-weight:770;letter-spacing:.08em;text-transform:uppercase;margin-bottom:7px}
.finding-list{list-style:none;padding:0;margin:0;display:grid;gap:11px;font-size:13px}
.finding-list li{display:flex;gap:8px}
.finding-dot{width:8px;height:8px;border-radius:50%;margin-top:5px;flex:0 0 auto}
.finding-dot.green{background:#2ca66c}.finding-dot.yellow{background:#d7981b}.finding-dot.red{background:#d44e44}
.ai-draft-chip{margin-bottom:9px}
.summary-list{list-style:none;padding:0;margin:0 0 10px;display:grid;gap:9px;font-size:13px}
.source-keys{display:inline-flex;flex-wrap:wrap;gap:4px;margin-left:6px;vertical-align:middle}
/* Question labels, not slugs, so no monospace: this is prose, not code. */
.source-key{font-size:10.5px;background:#eef2f9;border:1px solid #dbe3f0;border-radius:6px;padding:1px 6px;color:#3d4a63;font-weight:640;white-space:nowrap}
.answer-sheet{display:grid;gap:16px;margin-top:12px}
.answer-group dl{margin:0;display:grid;gap:0}
.qa{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:14px;padding:8px 0;border-bottom:1px solid var(--line);align-items:baseline}
.qa:last-child{border-bottom:0}
.qa dt{margin:0;color:var(--muted);font-size:12.5px;line-height:1.4}
.qa dd{margin:0;color:var(--ink);font-size:12.5px;font-weight:650}
.qa.consent{grid-template-columns:minmax(0,1fr) auto}
.qa.consent dt{color:var(--ink)}
@media (max-width:700px){.qa,.qa.consent{grid-template-columns:1fr;gap:3px}}
.ai-honesty{font-size:11.5px;color:var(--soft-muted);margin:0 0 10px}
.source-answers{margin:10px 0 0;display:grid;gap:7px;font-size:12px}
.source-answers > div{display:grid;grid-template-columns:auto 1fr;gap:8px;align-items:baseline}
.source-answers dd{margin:0;color:#263248}
.protocol-version{font-size:12px;color:var(--muted);margin:0 0 9px}
.holds-heading{margin-top:14px}
.holds-list{list-style:none;padding:0;margin:0;display:grid;gap:6px}
.no-holds{font-size:12.5px;color:var(--soft-muted);margin:0}
.action-reason{font-size:11.5px;color:var(--soft-muted);margin:8px 0 0}
@media (max-width:1100px){.quick-review-grid{grid-template-columns:1fr}}

/* ------------------------------------------------------------ admin catalog */
/* Case routing */
.mode-list{display:grid;gap:9px;padding:0 20px 20px}
.mode-row{display:flex;gap:11px;align-items:flex-start;border:1px solid var(--line);border-radius:12px;padding:13px 14px;background:#fff}
.mode-row.on{border-color:#bcd2f8;background:var(--blue-bg)}
.mode-row input{margin-top:2px;accent-color:var(--accent);flex:0 0 auto}
.mode-row strong{font-size:13.5px;display:block}
.mode-note{display:block;color:var(--muted);font-size:12px;margin-top:2px}
.weight-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;padding:0 20px 20px}
.weight label{display:block;font-size:11px;font-weight:760;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin-bottom:5px}
.weight input{width:100%;padding:9px 11px;border-radius:10px;border:1px solid var(--line-strong);background:#fff;font-family:inherit;font-size:13px;color:var(--ink)}
.ladder{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:13px}
.rung{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line-strong);border-radius:10px;padding:7px 11px;font-size:12.5px;background:#fbfcfe}
.rung-n{display:grid;place-items:center;width:18px;height:18px;border-radius:99px;background:var(--blue-bg);color:var(--accent-ink);font-size:10px;font-weight:790}
.rung-arrow{color:var(--soft-muted);font-size:13px}
.combos .subheading{margin:0}

/* ------------------------------------------------- messages, iPhone styling */
.msg-layout{display:grid;grid-template-columns:.8fr 1.2fr;gap:16px;align-items:start}
.msg-list{padding-bottom:8px}
.msg-row{display:flex;align-items:center;gap:11px;width:100%;text-align:left;background:none;border:0;
  padding:11px 20px;border-top:1px solid var(--line)}
.msg-row:hover{background:#fafcff}
.msg-row.active{background:var(--blue-bg)}
.msg-avatar{display:grid;place-items:center;width:34px;height:34px;border-radius:99px;background:#e7edf8;color:#41537a;font-size:12px;font-weight:770;flex:0 0 auto}
.msg-avatar.big{width:40px;height:40px;font-size:13px}
.msg-row-body{min-width:0;flex:1}
.msg-row-body strong{display:block;font-size:13.5px}
.msg-row-body span{display:block;color:var(--soft-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-row-meta{display:flex;align-items:center;gap:7px;color:var(--soft-muted);font-size:11.5px;flex:0 0 auto}
.msg-unread{display:grid;place-items:center;min-width:19px;height:19px;border-radius:99px;background:var(--accent);color:#fff;font-size:10.5px;font-weight:780;padding:0 5px}

.chat{display:flex;flex-direction:column;overflow:hidden;min-height:520px}
.chat-head{display:flex;align-items:center;gap:11px;padding:14px 18px;border-bottom:1px solid var(--line);background:rgba(251,252,254,.9)}
.chat-head strong{display:block;font-size:14px}
.chat-head span{display:block;color:var(--soft-muted);font-size:11.5px}
.chat-scroll{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:3px;background:#fff}
.chat-time{align-self:center;color:var(--soft-muted);font-size:11px;font-weight:640;margin:12px 0 6px}
.bubble-row{display:flex;margin-top:2px}
.bubble-row.me{justify-content:flex-end}
/* iMessage geometry: 18px radius, tail corner squared to 5px, blue out / grey in. */
.bubble{max-width:74%;padding:8px 13px;border-radius:18px;font-size:13.5px;line-height:1.38;letter-spacing:-.004em;word-wrap:break-word}
.bubble.them{background:#e9e9eb;color:#000;border-bottom-left-radius:5px}
.bubble.me{background:#248bf5;color:#fff;border-bottom-right-radius:5px}
.chat-read{align-self:flex-end;color:var(--soft-muted);font-size:10.5px;margin-top:5px}
.chat-assist{padding:10px 16px;border-top:1px solid var(--line);background:#fbfcfe}
.assist-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.assist-tones{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto}
.tone-btn{border:1px solid var(--line-strong);background:#fff;border-radius:999px;padding:5px 11px;font-size:11.5px;font-weight:700;color:var(--muted)}
.tone-btn:hover{background:#f2f6ff;border-color:#bcd2f8;color:var(--accent-ink)}
.note-block{padding:16px 20px 4px;border-top:1px solid var(--line)}
.note-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:9px;flex-wrap:wrap}
.note-head > div{display:flex;align-items:center;gap:9px}
.note-head .subheading{margin:0}
.note-area{
  width:100%;border:1px solid var(--line-strong);border-radius:12px;padding:11px 12px;
  font-family:inherit;font-size:13px;line-height:1.5;color:var(--ink);resize:vertical;background:#fff;
}
.note-area:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.chat-compose{display:flex;align-items:center;gap:9px;padding:12px 16px;border-top:1px solid var(--line);background:#fbfcfe}
.chat-compose input{flex:1;border:1px solid var(--line-strong);border-radius:999px;padding:9px 15px;font-size:13.5px;font-family:inherit;background:#fff}
.chat-send{display:grid;place-items:center;width:32px;height:32px;border-radius:99px;border:0;background:#248bf5;color:#fff;font-size:15px;font-weight:800;flex:0 0 auto}
@media (max-width:900px){.msg-layout{grid-template-columns:1fr}}

.endpoints{display:grid;gap:10px;padding:16px 20px 20px}
.endpoint{border:1px solid var(--line);border-radius:12px;padding:14px;background:#fff}
.endpoint > div{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.endpoint code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:#2b3c61;overflow-wrap:anywhere}
.endpoint p{margin:7px 0 0;font-size:12.5px;color:var(--muted)}
.method{display:inline-flex;min-width:52px;justify-content:center;padding:3px 7px;border-radius:6px;background:#e9f8ef;color:#167846;font-size:10px;font-weight:790;letter-spacing:.04em}

@media (max-width:1100px){.metric-grid{grid-template-columns:repeat(2,1fr)}.two{grid-template-columns:1fr}}
@media (max-width:820px){
  .app,.app.collapsed{grid-template-columns:0 minmax(0,1fr)}
  .side{position:fixed;z-index:40;width:var(--rail);transform:translateX(-100%);transition:transform .18s ease}
  .app.mobile-open .side{transform:none;box-shadow:0 20px 60px rgba(16,24,40,.22)}
  .wrap{padding:20px 16px 48px}
  .metric-grid{grid-template-columns:1fr}
  .demo-banner{display:none}
  .page-head h1{font-size:24px}
}

@endverbatim
