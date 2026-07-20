import { chromium } from "file:///C:/Agent/workspace/forged-elite/node_modules/playwright/index.mjs";
import http from "node:http";
import fs from "node:fs";
import path from "node:path";

const DIR = path.resolve("C:/Users/AgentService/AppData/Local/Temp/claude/C--Agent-workspace-dp-agent/1b4b27d9-75ea-4c1a-a84c-66f32fe7e36f/scratchpad/medaxis-preview");
const BASE = process.env.LIVE || null;

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log("  ok    " + m); } else { fail++; console.log("  FAIL  " + m); } };
const section = (s) => console.log("\n== " + s + " ==");

// Serve over http. file:// breaks relative behaviour and is not what S3 will do.
let server = null, origin = BASE;
if (!BASE) {
  server = http.createServer((req, res) => {
    const f = path.join(DIR, req.url === "/" ? "index.html" : decodeURIComponent(req.url.split("?")[0]));
    if (!fs.existsSync(f)) { res.writeHead(404); return res.end("nope"); }
    res.writeHead(200, { "content-type": f.endsWith(".html") ? "text/html" : "application/octet-stream" });
    res.end(fs.readFileSync(f));
  });
  await new Promise((r) => server.listen(0, r));
  origin = "http://127.0.0.1:" + server.address().port;
}
console.log("verifying " + origin);

const browser = await chromium.launch({
  executablePath: "C:/Users/AgentService/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe",
});
const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });

const errors = [];
page.on("pageerror", (e) => errors.push(String(e)));
page.on("console", (m) => { if (m.type() === "error") errors.push(m.text()); });

await page.goto(origin, { waitUntil: "networkidle" });

section("Shell renders");
ok(await page.locator(".side").isVisible(), "sidebar is present");
ok((await page.locator(".role-button").count()) === 3, "three portal switches (Admin / Clinician / Partner)");
ok(await page.locator(".demo-banner").isVisible(), "fictional-data banner is visible");

section("Every nav item in every portal resolves to a real view");
const portals = await page.locator(".role-button").allTextContents();
let stubs = [];
let visited = 0;
for (const label of portals) {
  await page.click(`.role-button:text-is("${label}")`);
  const openAllSections = async () => {
    for (let g = 0; g < 12; g++) {
      const c = await page.locator(".nav-section.closed").count();
      if (!c) break;
      await page.locator(".nav-section.closed").first().click();
    }
  };
  await openAllSections();
  const links = await page.locator(".nav-link").count();
  ok(links > 0, `${label}: sidebar has ${links} nav items`);
  for (let i = 0; i < links; i++) {
    // A section the previous click may have left shut must not hide the next item.
    await openAllSections();
    const link = page.locator(".nav-link").nth(i);
    const name = (await link.getAttribute("title")) || String(i);
    await link.click();
    visited++;
    const isStub = await page.locator(".stub").count();
    const h1 = await page.locator(".page-head h1").count();
    if (isStub) stubs.push(`${label} / ${name}`);
    else if (!h1) stubs.push(`${label} / ${name} (no heading)`);
  }
}
ok(stubs.length === 0, `all ${visited} nav items render a built view` + (stubs.length ? " -> MISSING: " + stubs.join(", ") : ""));

section("Clinician queue matches the MA practitioner surface");
await page.click('.role-button:text-is("Clinician")');
await page.locator('.nav-link[title="Case Queue"]').click();
{
  const heads = await page.locator(".review-grid thead th").allTextContents();
  ok(heads.length === 20, `grid has MA's 20 columns (got ${heads.length})`);
  const expected = ["", "Triage", "Time in Queue", "Full Name", "ID Verified", "Sex", "Age", "BMI", "On GLP",
    "Req Term", "Req Dose", "Titration or Hold", "Medication 2 Req", "Medication 3 Req", "Medication 4 Req",
    "Company", "Concerning Allergies", "Standing Zofran", "Video Visit", "Batch Eligibility"];
  ok(JSON.stringify(heads) === JSON.stringify(expected), "column headers match MA's practitioner grid exactly");

  ok((await page.locator(".review-grid th.pin").count()) === 4, "four pinned header columns");
  const pinPos = await page.evaluate(() =>
    getComputedStyle(document.querySelector(".review-grid td.pin-name")).position);
  ok(pinPos === "sticky", "the name column is genuinely sticky, not just classed");

  // Blocked rows must be unselectable. This is the rule that matters clinically.
  const boxes = page.locator(".review-grid [data-check]");
  ok((await boxes.count()) === 5, "five queue rows");
  // MA's fixtures are 2 Eligible, 1 Review, 2 Blocked, so 3 of the 5 refuse selection.
  const disabled = await page.locator(".review-grid [data-check][disabled]").count();
  ok(disabled === 3, `the three non-eligible rows are disabled (got ${disabled})`);

  await page.click("#selectAll");
  ok((await page.locator(".queue-count strong").textContent()) === "2", "select-all takes exactly the 2 eligible rows");
  const checkedAfterAll = await page.locator(".review-grid [data-check]:checked").count();
  ok(checkedAfterAll === 2, "and never ticks a blocked row");

  // Preflight button reflects the selection.
  ok((await page.locator("#preflight").textContent()).includes("(2)"), "preflight button counts the selection");
  await page.click("#preflight");
  ok(await page.locator(".queue-notice").isVisible(), "preflight shows the it-is-a-demo notice");
  ok((await page.locator(".queue-notice .audit-verb").textContent()).includes("batch/preflight"), "notice names the real endpoint");

  // Defence in depth: if the disabled attribute were ever lost, the handler must
  // still refuse. Strip it at runtime and click the blocked row for real.
  await page.evaluate(() => {
    document.querySelector('[data-check="demo-004"]').removeAttribute("disabled");
  });
  await page.click('.review-grid tr[data-row="demo-004"] [data-check]');
  ok((await page.locator(".queue-count strong").textContent()) === "2",
     "a blocked row cannot enter the selection even with its disabled attribute stripped");

  await page.click("#selectAll");
  ok((await page.locator(".queue-count strong").textContent()) === "0", "select-all toggles back off");
  ok((await page.locator("#preflight").getAttribute("disabled")) !== null, "preflight disables at zero selected");

  // A blocked row must still be reviewable, it just cannot be batched.
  await page.click('.review-grid tr[data-row="demo-004"] .pin-name');
  ok((await page.locator(".quick-review h2").textContent()) === "Casey Rivera", "clicking a blocked row still opens quick review");
  ok((await page.locator(".quick-review .button-primary").getAttribute("disabled")) !== null, "approve is disabled for a blocked case");
  ok((await page.locator(".action-reason").textContent()).includes("identity not verified"), "and the reason is shown, not hidden");

  await page.click('.review-grid tr[data-row="demo-001"] .pin-name');
  ok((await page.locator(".quick-review h2").textContent()) === "Avery Morgan", "clicking an eligible row switches the drawer");
  ok((await page.locator(".quick-review .button-primary").getAttribute("disabled")) === null, "approve is enabled for an eligible case");
  ok((await page.locator(".no-holds").count()) === 1, "a case with no holds says so explicitly");

  // Source answers toggle.
  ok((await page.locator(".source-answers").count()) === 0, "source answers start hidden");
  await page.click("#srcToggle");
  ok((await page.locator(".source-answers").count()) === 1, "the toggle reveals the source answers");
  ok((await page.locator(".source-answers .source-key").count()) > 3, "and lists the intake answer keys");
  await page.click("#srcToggle");
  ok((await page.locator(".source-answers").count()) === 0, "and hides them again");

  // Ticking a checkbox must not also open the drawer underneath it.
  await page.click('.review-grid tr[data-row="demo-003"] [data-check]');
  ok((await page.locator(".quick-review h2").textContent()) === "Avery Morgan", "ticking a checkbox does not hijack the drawer");
  ok((await page.locator(".queue-count strong").textContent()) === "1", "but it does register the selection");

  // Workflow holds render as the real hold constant.
  await page.click('.review-grid tr[data-row="demo-005"] .pin-name');
  ok((await page.locator(".holds-list .audit-verb").textContent()).includes("SYNCHRONOUS_VIDEO_VISIT_REQUIRED"),
     "an operationally-held case shows its hold constant");
  ok((await page.locator(".quick-pills .pill").first().textContent()) === "Green",
     "triage and batch state stay separate axes (Green triage, still blocked)");

  // Allergy detail is reachable.
  await page.click('.review-grid tr[data-row="demo-002"] .pin-name');
  ok((await page.locator(".allergy-tooltip").count()) === 1, "the allergy flag carries a detail tooltip");
}

section("Sidebar collapses and restores");
await page.click('.role-button:text-is("Admin")');
const wideBefore = (await page.locator(".side").boundingBox()).width;
await page.click("#toggle");
await page.waitForTimeout(320);
const wideAfter = (await page.locator(".side").boundingBox()).width;
ok(wideAfter < wideBefore - 100, `collapse shrinks the rail (${Math.round(wideBefore)}px -> ${Math.round(wideAfter)}px)`);
ok(!(await page.locator(".brand-text").isVisible()), "brand wordmark hides when collapsed");
const icoVisible = await page.locator(".nav-link .nav-ico").first().isVisible();
ok(icoVisible, "icons stay visible when collapsed, so the rail is still navigable");
await page.click("#toggle");
await page.waitForTimeout(320);
ok(Math.round((await page.locator(".side").boundingBox()).width) === Math.round(wideBefore), "expanding restores the original width");

section("Collapsed rail still switches views");
await page.click("#toggle");
await page.waitForTimeout(320);
await page.locator(".nav-link").nth(1).click();
ok((await page.locator(".page-head h1").count()) === 1, "a click in the collapsed rail still routes");
await page.click("#toggle");
await page.waitForTimeout(320);

section("Palette is MA-DOCPORTAL's, not invented");
const tok = await page.evaluate(() => {
  const s = getComputedStyle(document.documentElement);
  return { accent: s.getPropertyValue("--accent").trim(), ink: s.getPropertyValue("--ink").trim(), bg: s.getPropertyValue("--bg").trim() };
});
ok(tok.accent === "#2563eb", "--accent matches MA (#2563eb)");
ok(tok.ink === "#172033", "--ink matches MA (#172033)");
ok(tok.bg === "#f5f7fb", "--bg matches MA (#f5f7fb)");

section("Mobile 390px");
await page.setViewportSize({ width: 390, height: 844 });
await page.waitForTimeout(220);
const h = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
ok(h.sw <= h.cw + 1, `no horizontal scroll at 390px (scrollWidth ${h.sw} vs client ${h.cw})`);
const offscreen = await page.evaluate(() => document.querySelector(".side").getBoundingClientRect().right <= 1);
ok(offscreen, "sidebar is off-canvas on mobile rather than crushing the content");
await page.click("#toggle");
await page.waitForTimeout(300);
ok(await page.evaluate(() => document.querySelector(".side").getBoundingClientRect().right > 100), "the toggle opens the drawer on mobile");

section("Screenshots");
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(origin, { waitUntil: "networkidle" });
await page.screenshot({ path: path.join(DIR, "shot-mobile.png") });
await page.setViewportSize({ width: 1440, height: 950 });
await page.goto(origin, { waitUntil: "networkidle" });
await page.screenshot({ path: path.join(DIR, "shot-desktop.png") });
await page.click('.role-button:text-is("Clinician")');
await page.locator('.nav-link[title="Case Queue"]').click();
await page.screenshot({ path: path.join(DIR, "shot-queue.png") });
ok(true, "captured mobile, desktop and clinician-queue screenshots");

ok(errors.length === 0, "no page errors across the whole run" + (errors.length ? " -> " + errors.slice(0, 3).join(" | ") : ""));

await browser.close();
if (server) server.close();
console.log("\n" + (fail ? `FAILED  ${fail} failed, ${pass} passed` : `all green  (${pass} checks)`));
process.exit(fail ? 1 : 0);
