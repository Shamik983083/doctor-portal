# Preview vs live app: gap analysis

What the design preview (`docs/design-preview/index.html`, served at
`/medaxis-preview/`) shows, what the live app actually has, and what stands
between them. Written for Devin's ask (msg 2263): "I want to know what's missing
and what issues we have that can't run everything from the preview link. All
functionality must be there."

## 0. Why it looks "off and large", the root cause

The preview is a **single static HTML file with its own hand-written CSS and no
framework.** Fonts are 15px, tables are 13px with 10px uppercase headers, and
every component (`.tbl`, `.pill`, `.panel`, `.metric`, the drawer, the chat) is
styled from scratch.

The live app is **Bootstrap 5.** Bootstrap sets its own base font size (16px),
its own table padding, card styles, button sizes and form controls. When I remap
the app's classes to the preview's values, Bootstrap's own rules are still
loaded and still win in places, so the result is close but never identical, and
generally larger, because Bootstrap's defaults are larger.

**There is no "match" that gets to identical while Bootstrap is in the page.**
The only way to be exact is to stop styling on top of Bootstrap for these screens
and instead use the preview's own stylesheet and its own DOM. That is a rebuild
of each view's markup, not a CSS tweak. It is the correct fix and it is the plan
in section 3.

## 1. The preview is a design spec, not runnable app code

Two things about the preview that mean it cannot simply "run":

- **All its data is mock.** The `QUEUE`, the admin tables, the chat threads and
  the AI drafts are hard-coded JS arrays. None of it talks to a database or an
  API. Wiring each screen to real data is per-screen work.
- **All its interactivity is local.** The approve/reject buttons, batch
  preflight, chat send, role switching all mutate JS state in the page. Making
  them real means the existing (or new) backend endpoints, permissions and state
  machine behind each one.

So "run everything from the preview" is not a switch. The preview tells us the
exact look and the exact feature set; the app has to provide each feature for
real. Much of it already does (section 2b).

## 2a. Screens: what the preview has vs the app

The preview defines ~40 screens across three roles.

### Admin (preview has 19)

| Preview screen | Live app | Note |
|---|---|---|
| Dashboard | REAL | `admin.dashboard` |
| Cases | REAL | `admin.cases.*`, scoped |
| Patients | REAL | `admin.patients.*` |
| Partners | REAL | `admin.partners.*` (super admin) |
| Clinicians | REAL | `admin.clinicians.*` |
| Assignment Priority | REAL | `admin.clinicians.priority` |
| Medications (Offerings) | REAL | `admin.offerings.*` |
| Categories | REAL | `admin.categories.*` |
| Questionnaires | REAL | `admin.questionnaires.*` |
| Question Bank | REAL | `admin.questions.*` |
| Messaging API guide | REAL | `admin.guide.messaging` |
| Weight Loss API guide | REAL | `admin.guide.weightloss-api` |
| Anti-Aging API guide | REAL | `admin.guide.antiaging-api` |
| Webhook guide | REAL | `admin.guide.webhooks` |
| Webhook Logs | REAL | `admin.webhooks.*` |
| SLA Settings | REAL | `admin.settings` |
| Case Routing | REAL | `admin.routing.*` |
| Triage Rule Set | REAL | `admin.triage-rules.*` |
| Admin Users | REAL | `admin.admins.*` (super admin) |
| Roles & Permissions | MISSING as a screen | The data exists in the seeder; there is no read-only permissions view |

**Admin is almost entirely real already.** The gap is look (Bootstrap), not
features, plus one missing read-only permissions view.

### Clinician (preview has 13)

| Preview screen | Live app | Note |
|---|---|---|
| Dashboard | REAL | `clinician.dashboard` |
| Case Queue | REAL | `clinician.queue` |
| My Cases | REAL | `clinician.cases.my-cases` |
| Messages For Provider | REAL (new) | `clinician.messages.index`, built msg 2256 |
| My Escalations | DATA ONLY | No dedicated screen; is the queue filtered to `support` |
| SLA breached | DATA ONLY | No screen; derivable from case age vs SLA setting |
| Approaching deadline | DATA ONLY | No screen; same source |
| Video visit required | DATA ONLY | No screen; derivable from offering video rule |
| Support thread open | DATA ONLY | No screen; queue filtered to `support` |
| Triage Red / Yellow / Green | DATA ONLY | Sidebar links filter the queue by triage; not 3 dedicated screens |
| Notifications | REAL | `clinician.notifications.*` |

The clinician "missing" screens are all the **same case grid filtered
differently.** In the preview they are separate sidebar entries; in the app they
are one queue with filters. Making them dedicated screens is small (each is a
controller method returning the grid with a preset filter).

### Partner (preview has 6)

| Preview screen | Live app | Note |
|---|---|---|
| Dashboard | REAL | `partner.dashboard` |
| Cases | REAL | `partner.cases.*` |
| Patients | REAL | `partner.patients.*` |
| Medications | REAL | `partner.offerings.*` |
| API Credentials | REAL | `partner.credentials` |
| Notifications | REAL | `partner.notifications.*` |

**Partner is fully real** feature-wise. Gap is look only.

## 2b. Interactive functionality: preview vs app

| Preview feature | Live app | Note |
|---|---|---|
| Case Queue grid, pinned columns, med columns | REAL (data), look pending | Columns now fed by `clinical_intake` (storefront push) |
| Batch preflight + submit | REAL | `clinician.cases.batch.preflight` / `batch.submit` |
| Approve / reject **per medication** with decisions | REAL | `approve` takes a `decisions[]` array |
| Prescribe | REAL | `clinician.cases.prescribe` |
| Escalate to support | REAL | `clinician.cases.support` |
| Messages thread: send + poll | REAL | `messages.store`, `messages.poll` |
| AI draft summary | REAL but MOCK adapter | `draftNote` + `AiAssistService`; default adapter is `mock`, no PHI leaves. Real OpenAI is behind two flags + a BAA |
| Review **drawer** (slides over the grid) | DIFFERENT UX | App does review on the case **page** (`cases.show` + `prescribe`), not a drawer over the grid |
| Chat assist / suggested replies | MOCK only | Preview shows canned suggestions; app has none |
| Role switcher (Admin/Clinician/Partner tabs) | N/A by design | Preview convenience. In the real app your role comes from login; you do not switch roles in a tab |
| Admin scope switcher (which Doctor Admin) | PARTIAL | Real scoping exists (`visibleTo`); there is no in-page "act as" switcher |

**Almost every real feature exists.** The two genuine functionality gaps are the
**review drawer UX** (the app reviews on a page, the preview in a slide-over) and
**chat suggested replies** (mock only). The AI draft is real but runs on the mock
adapter until a BAA is executed (correct, safe default).

## 3. The plan to get to exact

Because the look cannot be identical while Bootstrap fights the preview
(section 0), the fix is to adopt the preview's own CSS and DOM for these screens,
view by view, wiring real data into the preview's exact markup.

1. **Lift the preview's stylesheet into the app** as a real asset, unchanged, so
   the exact tokens, table, pills, panels, drawer and chat CSS are present.
2. **Rebuild each view's DOM to the preview's structure**, starting with the
   clinician **Case Queue** (the screen in front of you), then the review
   **drawer**, then **Messages/chat**, then the clinician filtered screens, then
   admin and partner.
3. **Wire real data** into each ported view (the queue already has its data via
   `clinical_intake`).
4. **Close the two real feature gaps**: the review drawer, and chat suggested
   replies.

Each ported view gets a screenshot check with Devin before the next, because the
build machine cannot render and only his eye confirms the pixel match.

## 4. Honest blockers

- **No rendering here.** The build machine has no browser and no PHP. Every port
  is verified by Devin's screenshot, not locally. This is the single biggest
  drag on getting to "identical" quickly.
- **The preview's role/scope switcher does not map to the real app.** Role comes
  from login; there is no tab to switch role. This one preview affordance is not
  a target.
- **AI and chat assist need the BAA** before they do anything real with PHI. The
  UI can be built now; the live model call stays gated.
