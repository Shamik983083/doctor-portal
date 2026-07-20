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

section("Compact density gains real columns");
{
  await page.click('.role-button:text-is("Clinician")');
  await page.locator('.nav-link[title="Case Queue"]').click();
  const countVisible = () => page.evaluate(() => {
    const s = document.querySelector(".review-grid-scroll").getBoundingClientRect();
    return [...document.querySelectorAll(".review-grid thead th")]
      .filter((th) => { const r = th.getBoundingClientRect(); return r.left >= s.left - 1 && r.right <= s.right + 1; }).length;
  });
  const before = await countVisible();
  await page.click("#compact");
  await page.waitForTimeout(220);
  const after = await countVisible();
  ok(after > before, `compact reveals more columns (${before} -> ${after})`);
  await page.click("#compact");
  await page.waitForTimeout(200);
  ok((await countVisible()) === before, "turning compact off restores the original density");
}

section("Approval: one decision per requested medication");
{
  await page.click('.review-grid tr[data-row="demo-001"] .pin-name');   // Semaglutide + Zofran
  await page.click("#openApproval");
  ok(await page.locator(".modal").isVisible(), "the review modal opens from the drawer");

  const tabs = await page.locator(".med-tab").count();
  ok(tabs === 2, `a case requesting two medications shows two tabs (got ${tabs})`);
  const tabText = await page.locator(".med-tab").allTextContents();
  ok(tabText.some((t) => t.includes("Semaglutide")) && tabText.some((t) => t.includes("Zofran")),
     "tabs name the primary product and the add-on");

  ok((await page.locator("#modalSubmit").getAttribute("disabled")) !== null, "submit starts disabled");
  ok((await page.locator(".foot-status").textContent()).includes("0 of 2"), "footer counts decisions made");

  // Approving needs the required fields; the term must come from the catalog.
  await page.click(".decision-btn.approve");
  const terms = await page.locator('.field select[data-f="term"]').allTextContents();
  ok(!terms.join(" ").includes("2 month"), "duration offers only the catalog terms");
  const termVal = await page.locator('.field select[data-f="term"]').inputValue();
  ok(termVal === "3 months", "duration is prefilled from the requested 3M term");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) !== null,
     "approving one medication is not enough to submit while another is undecided");

  // This is a 3M case, so the primary needs a dose for each of its three months.
  ok((await page.locator(".months-grid .field").count()) === 3, "the 3M primary asks for three monthly doses");
  await page.selectOption('.field select[data-f="month1"]', "L2 · 5 mg");
  await page.selectOption('.field select[data-f="month2"]', "L3 · 7.5 mg");

  // Second medication, and its dosage list must belong to that drug.
  await page.click('.med-tab[data-med="med2"]');
  await page.click(".decision-btn.approve");
  const doseOpts = await page.locator('.field select[data-f="month0"]').allTextContents();
  ok(doseOpts.join(" ").includes("8 mg") && !doseOpts.join(" ").includes("12.5 mg"),
     "dosage options follow the selected medication, not the primary one");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) !== null,
     "still blocked while the add-on is missing a required dosage");
  await page.selectOption('.field select[data-f="month0"]', "8 mg");
  await page.selectOption('.field select[data-f="month1"]', "8 mg");
  await page.selectOption('.field select[data-f="month2"]', "8 mg");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) !== null,
     "an add-on still needs its own frequency, it is not inherited from the primary");
  await page.selectOption('.field select[data-f="freq"]', "As needed");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) === null,
     "submit unlocks once every requested medication is complete");

  // Deny needs no prescription fields at all.
  await page.click(".decision-btn.deny");
  ok((await page.locator('.field select[data-f="month0"]').getAttribute("disabled")) !== null,
     "denying disables the prescription fields");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) === null,
     "a denied medication needs no dosage to submit");

  // Changing the term away from what was sold must be called out, not silent.
  await page.click(".decision-btn.approve");
  await page.selectOption('.field select[data-f="term"]', "1 month");
  ok((await page.locator(".term-warn").count()) === 1, "changing duration warns that it will not match the order");
  await page.selectOption('.field select[data-f="term"]', "3 months");
  ok((await page.locator(".term-warn").count()) === 0, "and the warning clears when it matches again");

  // Shrinking to 1M dropped months 2 and 3; widening continues the ladder from
  // the last month set, so the form is complete again without retyping.
  const regrown = await Promise.all([0, 1, 2].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(regrown.every(Boolean), `widening the term refills the months from the ladder (${regrown.join(" / ")})`);

  await page.click("#modalSubmit");
  ok((await page.locator(".modal").count()) === 0, "submitting closes the modal");
  ok(await page.locator("#submitNote").isVisible(), "and says plainly that nothing was really submitted");

  // A single-medication case must not show add-on tabs.
  await page.click('.review-grid tr[data-row="demo-003"] .pin-name');
  await page.click("#openApproval");
  ok((await page.locator(".med-tab").count()) === 1, "a single-medication case shows one tab");
  await page.click("#modalCancel");
  ok((await page.locator(".modal").count()) === 0, "cancel closes without recording anything");
}

section("Consistent view, different content");
{
  // Every clinician case screen must be the SAME grid, only filtered. Messages
  // is the one deliberate exception.
  const caseViews = ["Case Queue","My Cases","My Escalations","SLA breached","Approaching deadline",
    "Video visit required","Support thread open","Red","Yellow","Green"];
  let shapesOk = 0, counts = {};
  for(const name of caseViews){
    await page.locator(`.nav-link[title="${name}"]`).click();
    const grid = await page.locator(".review-grid").count();
    const heads = await page.locator(".review-grid thead th").allTextContents();
    const rows = await page.locator(".review-grid tbody tr").count();
    counts[name] = rows;
    if(grid === 1 && heads.length === 20 && rows > 0) shapesOk++;
  }
  ok(shapesOk === caseViews.length,
     `all ${caseViews.length} case screens render the identical 20-column grid (${shapesOk} did)`);

  ok(counts["Case Queue"] !== counts["My Cases"], "but the content differs between views");
  ok(counts["Red"] < counts["Case Queue"], "a triage filter narrows the set");

  // Filters must be honest: a Red view may not contain a Green row.
  await page.locator('.nav-link[title="Red"]').click();
  const triages = await page.locator(".review-grid tbody .pin-triage").allTextContents();
  ok(triages.every((t) => t.trim() === "Red"), "the Red view contains only Red cases");
  await page.locator('.nav-link[title="Green"]').click();
  const g = await page.locator(".review-grid tbody .pin-triage").allTextContents();
  ok(g.every((t) => t.trim() === "Green"), "the Green view contains only Green cases");

  // Counts in the toolbar must describe THIS view, not the whole queue.
  const toolbar = await page.locator(".queue-count span").textContent();
  const shown = await page.locator(".review-grid tbody tr").count();
  const elig = Number(toolbar.match(/(\d+) batch-eligible/)[1]);
  const blk = Number(toolbar.match(/(\d+) rows blocked/)[1]);
  ok(elig + blk === shown, `toolbar counts describe the filtered view (${elig}+${blk} = ${shown} rows)`);

  // The drawer must belong to the view you are looking at.
  const drawerName = await page.locator(".quick-review h2").textContent();
  const names = await page.locator(".review-grid tbody .pin-name").allTextContents();
  ok(names.some((n) => n.trim() === drawerName.trim()),
     "the open drawer is a case from this view, not one left over from the last");

  ok((await page.locator('.nav-link[title="Messages For Provider"]').count()) === 1, "Messages is still in the nav");
  await page.locator('.nav-link[title="Messages For Provider"]').click();
  ok((await page.locator(".review-grid").count()) === 0, "and Messages is deliberately NOT the case grid");
}

section("Multi-month terms take a dose per month");
{
  await page.locator('.nav-link[title="Case Queue"]').click();
  await page.click('.review-grid tr[data-row="demo-003"] .pin-name');   // Riley Chen, 1M
  await page.click("#openApproval");
  await page.click(".decision-btn.approve");
  ok((await page.locator(".months-grid").count()) === 0, "a 1 month term keeps a single dosage field");
  ok((await page.locator('.field select[data-f="month0"]').count()) === 1, "and that field is month 1");

  // Grow the term: the form must grow with it.
  await page.selectOption('.field select[data-f="term"]', "3 months");
  ok((await page.locator(".months-grid .field").count()) === 3, "switching to 3M gives three month fields");
  const labels = await page.locator(".months-grid .field label").allTextContents();
  ok(labels.join(",") === "M1,M2,M3", `months are labelled M1..M3 (got ${labels.join(",")})`);
  ok((await page.locator('.field select[data-f="month0"]').inputValue()) !== "", "month 1 keeps the requested dose");
  ok((await page.locator('.field select[data-f="month1"]').inputValue()) !== "",
     "later months are prefilled from the plan rather than left blank");

  // Prefill is a starting point, not a guarantee. Switching the medication
  // invalidates every strength that belonged to the old drug, which must empty
  // those months and block submit rather than carrying a wrong dose forward.
  await page.selectOption('.field select[data-f="medication"]', "Zofran");
  const cleared = await Promise.all([0, 1, 2].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(cleared.every((x) => x === ""), "changing the medication empties doses that belonged to the old drug");
  ok((await page.locator("#modalSubmit").getAttribute("disabled")) !== null, "and that blocks submit");
  await page.selectOption('.field select[data-f="medication"]', "Semaglutide");
  for(const [i, v] of [[0,"L1 · 2.5 mg"],[1,"L2 · 5 mg"],[2,"L3 · 7.5 mg"]])
    await page.selectOption(`.field select[data-f="month${i}"]`, v);

  // Shrinking drops the extra months rather than keeping stale ones.
  await page.selectOption('.field select[data-f="term"]', "1 month");
  ok((await page.locator(".months-grid").count()) === 0, "back to 1M collapses to a single dosage field");
  await page.selectOption('.field select[data-f="term"]', "4 months");
  ok((await page.locator(".months-grid .field").count()) === 4, "4M gives four month fields");
  ok((await page.locator('.field select[data-f="month3"]').inputValue()) !== "",
     "and the new fourth month continues the ladder");
  await page.click("#modalCancel");
}

section("Closing the rail expands the grid");
{
  await page.locator('.nav-link[title="Case Queue"]').click();
  const countVisible = () => page.evaluate(() => {
    const s = document.querySelector(".review-grid-scroll").getBoundingClientRect();
    return [...document.querySelectorAll(".review-grid thead th")]
      .filter((th) => { const r = th.getBoundingClientRect(); return r.left >= s.left - 1 && r.right <= s.right + 1; }).length;
  });
  const open = await countVisible();
  await page.click("#toggle");
  await page.waitForTimeout(340);
  const shut = await countVisible();
  ok(shut >= open + 4, `closing the rail reveals materially more columns (${open} -> ${shut})`);
  await page.click("#toggle");
  await page.waitForTimeout(340);
  ok((await countVisible()) === open, "reopening restores the original density");
}

section("Clinician sidebar is a work queue, with counts");
{
  const labels = await page.locator(".nav-section span").allTextContents();
  ok(labels.includes("Priority"), "sidebar has the Priority section");
  ok(labels.includes("Tasks (General)"), "sidebar has the Tasks section");
  ok(labels.includes("Triage"), "sidebar has the Triage section");
  const counts = await page.locator(".nav-link .nav-count").count();
  ok(counts >= 10, `work items carry counts (${counts} of them)`);
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

section("Months prefill from the requested dose and the plan");
{
  await page.click('.role-button:text-is("Clinician")');
  await page.locator('.nav-link[title="Case Queue"]').click();

  // demo-001: Semaglutide, L1, 3M, Titration -> should step L1, L2, L3.
  await page.click('.review-grid tr[data-row="demo-001"] .pin-name');
  await page.click("#openApproval");
  await page.click(".decision-btn.approve");
  const tit = await Promise.all([0, 1, 2].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(tit[0] === "L1 · 2.5 mg", "titration month 1 is the requested level");
  ok(tit[1] === "L2 · 5 mg" && tit[2] === "L3 · 7.5 mg",
     `titration steps up one level per month (${tit.join(" / ")})`);
  ok((await page.locator(".foot-status").textContent()).includes("1 of 2"),
     "the prefilled primary counts as decided without further typing");
  await page.click("#modalCancel");

  // demo-009: Tirzepatide, L3, 3M, HOLD -> should repeat L3 three times.
  await page.locator('.nav-link[title="My Cases"]').click();
  await page.click('.review-grid tr[data-row="demo-009"] .pin-name');
  await page.click("#openApproval");
  await page.click(".decision-btn.approve");
  const hold = await Promise.all([0, 1, 2].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(hold.every((x) => x === "L3 · 7.5 mg"),
     `a hold repeats the requested level for the whole term (${hold.join(" / ")})`);

  // Growing the term continues the ladder rather than leaving a hole.
  await page.selectOption('.field select[data-f="term"]', "4 months");
  const grown = await Promise.all([0, 1, 2, 3].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(grown.every(Boolean), `widening the term continues the ladder, no empty months (${grown.join(" / ")})`);
  ok(grown[3] === "L3 · 7.5 mg", "and a hold keeps holding when the term grows");
  await page.click("#modalCancel");

  // The blocked hold case still computes correctly, even though its approval
  // screen cannot be opened. Checked through the same function the UI calls.
  const blockedHold = await page.evaluate(() =>
    prefillMonths("Tirzepatide", "L3 · 7.5 mg", "Hold", "4M"));
  ok(blockedHold.length === 4 && blockedHold.every((x) => x === "L3 · 7.5 mg"),
     "the 4M hold on the blocked Yellow case would prefill correctly too");

  // Titration must stop at the top of the ladder, not run off the end.
  await page.locator('.nav-link[title="My Cases"]').click();
  await page.click('.review-grid tr[data-row="demo-007"] .pin-name');   // Tirzepatide L2, 4M titration
  await page.click("#openApproval");
  await page.click(".decision-btn.approve");
  const cap = await Promise.all([0, 1, 2, 3].map((i) =>
    page.locator(`.field select[data-f="month${i}"]`).inputValue()));
  ok(cap.every(Boolean) && cap[3] === "L5 · 12.5 mg", `titration walks to the top of the ladder (${cap.join(" / ")})`);
  await page.click("#modalCancel");
}

section("Source answers show the whole intake");
{
  await page.locator('.nav-link[title="Case Queue"]').click();
  await page.click('.review-grid tr[data-row="demo-001"] .pin-name');
  await page.click("#srcToggle");
  const keys = await page.locator(".source-answers .source-key").count();
  ok(keys >= 25, `the full intake is shown, not a handful of cited fields (${keys} answers)`);
  const text = await page.locator(".source-answers").textContent();
  for(const field of ["thyroidCancer", "pregnant", "consentTelehealth", "shippingState", "goalWeight"]){
    ok(text.includes(field), `intake includes ${field}`);
  }
}

section("AI summary and triage are derived from the answers");
{
  // Every statement in the draft must be traceable, and triage must agree with
  // the rules rather than being a label pinned on by hand.
  const agree = await page.evaluate(() =>
    QUEUE.every((r) => r.triageEval.result === r.triage));
  ok(agree, "every row's declared triage matches what the rule set computes");

  const cited = await page.evaluate(() => {
    const r = QUEUE.find((x) => x.id === "demo-001");
    const keys = Object.keys(r.answers);
    return r.summary.every(([, ks]) => ks.every((k) => keys.includes(k)));
  });
  ok(cited, "every answer key cited by the draft exists in the intake record");

  // A Red case must name the rule that made it Red.
  await page.locator('.nav-link[title="Red"]').click();
  await page.click('.review-grid tr[data-row="demo-004"] .pin-name');
  const sum = await page.locator(".summary-list").textContent();
  ok(/RED/.test(sum) && /TR-0\d/.test(sum), "a Red case names the triage rule that fired");
  ok(/NOT verified/.test(sum), "and the statement reflects the actual answer");

  // Turning a rule off must change the outcome, which is what "admin can set it" means.
  const flipped = await page.evaluate(() => {
    const rule = TRIAGE_RULES.find((r) => r.id === "TR-02");
    rule.active = false;
    const after = evaluateTriage(QUEUE.find((x) => x.id === "demo-004").answers).result;
    rule.active = true;
    return after;
  });
  ok(flipped !== "Red", `disabling the identity rule changes that case's triage (became ${flipped})`);
}

section("Admin can see products, levels and combinations");
{
  await page.click('.role-button:text-is("Admin")');
  const openAll = async () => { for(let i = 0; i < 12; i++){
    const c = await page.locator(".nav-section.closed").count(); if(!c) break;
    await page.locator(".nav-section.closed").first().click(); } };
  await openAll();
  await page.locator('.nav-link[title="Products & Levels"]').click();
  ok((await page.locator(".catalog-card").count()) === 4, "every catalog product is listed");
  const rungs = await page.locator(".catalog-card").first().locator(".rung").count();
  ok(rungs === 4, `Semaglutide shows its four ordered levels (got ${rungs})`);
  const combos = await page.locator(".catalog-card").first().locator(".combos .pill").allTextContents();
  ok(combos.includes("Zofran"), "and the add-ons it may be combined with");

  // The catalog is the same source the approval screen reads.
  const same = await page.evaluate(() =>
    JSON.stringify(DOSAGES.Semaglutide) === JSON.stringify(CATALOG.Semaglutide.levels));
  ok(same, "the provider dropdowns read this same catalog, not a second copy");
}

section("Messages open an iPhone-style thread");
{
  await page.click('.role-button:text-is("Clinician")');
  await page.locator('.nav-link[title="Messages For Provider"]').click();
  ok((await page.locator(".msg-row").count()) === 3, "conversations are listed");
  ok(await page.locator(".chat").isVisible(), "and a thread opens beside them");
  ok((await page.locator(".bubble").count()) >= 3, "the thread shows its history");

  const tones = await page.evaluate(() => {
    const me = getComputedStyle(document.querySelector(".bubble.me"));
    const them = getComputedStyle(document.querySelector(".bubble.them"));
    return { me:me.backgroundColor, them:them.backgroundColor,
             meRadius:me.borderBottomRightRadius, themRadius:them.borderBottomLeftRadius,
             meAlign:document.querySelector(".bubble-row.me").style.justifyContent || getComputedStyle(document.querySelector(".bubble-row.me")).justifyContent };
  });
  ok(tones.me !== tones.them, "outgoing and incoming bubbles are visually distinct");
  ok(tones.meAlign === "flex-end", "outgoing messages sit on the right, as on a phone");
  ok(parseFloat(tones.meRadius) < 10 && parseFloat(tones.themRadius) < 10,
     "each bubble squares off its tail corner, the iMessage detail");
  ok(await page.locator(".chat-compose input").isVisible(), "there is a compose box");

  await page.click('[data-thread="t2"]');
  ok((await page.locator(".chat-head strong").textContent()) === "Meridian Wellness",
     "picking another conversation swaps the thread");
  ok((await page.locator(".msg-row.active").count()) === 1, "and the list shows which one is open");
}

section("Typography is one system across the portal");
{
  await page.click('.role-button:text-is("Clinician")');
  await page.locator('.nav-link[title="Case Queue"]').click();
  const bodyFont = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
  ok(/-apple-system|BlinkMacSystemFont/.test(bodyFont), "body leads with the platform UI face, so Apple devices get SF");

  // Controls must not fall back to the browser's default form font.
  await page.click('.review-grid tr[data-row="demo-001"] .pin-name');
  await page.click("#openApproval");
  await page.click(".decision-btn.approve");
  const selFont = await page.evaluate(() =>
    getComputedStyle(document.querySelector('.field select[data-f="month0"]')).fontFamily);
  ok(selFont === bodyFont, "form controls inherit the page face rather than the browser default");
  const selApp = await page.evaluate(() =>
    getComputedStyle(document.querySelector('.field select[data-f="month0"]')).appearance);
  ok(selApp === "none", "and use the custom chevron, not the OS dropdown arrow");
  // Checkboxes must stay checkboxes. Styling every input in the modal as a text
  // control turned these into full-width dropdowns with a chevron.
  const cb = await page.evaluate(() => {
    const el = document.querySelector('.check-line input[type=checkbox]');
    const s = getComputedStyle(el);
    return { w: el.getBoundingClientRect().width, appearance: s.appearance, img: s.backgroundImage };
  });
  ok(cb.w < 40, `a checkbox is still checkbox-sized (${Math.round(cb.w)}px)`);
  ok(cb.img === "none", "and carries no dropdown chevron");
  await page.click("#modalCancel");

  // Headings tighten as they grow; uppercase micro-labels open up.
  const track = await page.evaluate(() => {
    const h1 = document.querySelector(".page-head h1");
    const eyebrow = document.querySelector(".eyebrow");
    const px = (el, p) => parseFloat(getComputedStyle(el)[p]);
    return { h1: px(h1, "letterSpacing") / px(h1, "fontSize"),
             eye: px(eyebrow, "letterSpacing") / px(eyebrow, "fontSize") };
  });
  ok(track.h1 < -0.02, `headings are tracked tight (${track.h1.toFixed(3)}em)`);
  ok(track.eye > 0.04, `uppercase labels are tracked open (${track.eye.toFixed(3)}em)`);

  const nums = await page.evaluate(() =>
    getComputedStyle(document.querySelector(".metric-value")).fontVariantNumeric);
  ok(/tabular-nums/.test(nums), "figures are tabular so columns of numbers line up");
}

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
