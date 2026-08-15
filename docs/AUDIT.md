# Creative Mark — Launch-Readiness Audit

**Date:** 2026-08-15  ·  **Commit:** `7654bae`  ·  **Branch:** `main` (36 commits, clean tree, **unpushed**)
**Method:** static reading + executed probes (query counters, `EXPLAIN`, live HTTP against `artisan serve`, exploit attempts). Every finding below carries evidence. Where a claim could not be verified by measurement it is labelled **UNVERIFIED**.

---

## Verdict: 🟡 **Ready to deploy, not ready to launch**

The engineering is in good shape. The suite is green on both database engines, no N+1 exists anywhere I could provoke one, the security model holds up under direct attack, and every production seed is free of invented data. Nothing here says *don't ship the code*.

What blocks a **public launch** is not code, it is that the site currently cannot quote a price or be contacted, and one of those is structural rather than a data-entry task:

- **Packages have no admin screen at all.** Four packages ship with `price = NULL` and there is no route, controller or view to change that (§1.1). The pricing page and the estimator's base-package selector both read "on request" for every option, and no one can fix it without database access.
- **Every commercial and contact value is empty** — estimator prices, package prices, phone, email, WhatsApp, address, socials, logo, analytics IDs (§1.2).
- **The legal pages are drafts I wrote**, never reviewed by a lawyer (§1.3).

Those are a day of work plus one small build. Nothing below is a rewrite.

| Dimension | State |
|---|---|
| Completeness | 🔴 packages unmanageable; content empty |
| Code quality | 🟢 consistent; small duplication, no dead code |
| Performance | 🟢 measured: ≤10 queries/page, flat under 40× data |
| Responsive/visual | 🟡 sound in markup, **real-browser QA not done** |
| Accessibility | 🟡 contrast pinned by test; one incomplete ARIA pattern |
| Security | 🟢 attacked and held; two minor hardening items |
| SEO & production | 🟡 two small defects |
| Test health | 🟢 1,137 passing / 3,283 assertions |

---

## Evidence summary

```
Tests           1137 passed, 1 skipped by design, 3283 assertions   (SQLite)
                1138 passed,                      3284 assertions   (MySQL)
Pint            passed
Caches          config / route / view / event all build clean
Bundle          JS 81.1 kB → 27.6 kB gzipped · CSS 72.8 kB → 12.3 kB gzipped
Scale           161 routes · 32 migrations · 26 models · 122 views · 57 test files
Production seed leads=0 testimonials=0 campaigns=0 conversion_events=0
                bookings=0 orders=0 visitor_sessions=0 comments=0 media=0
```

### Measured query counts (cold page render)

| Page | Queries | | Page | Queries |
|---|---|---|---|---|
| `/` | 10 | | `/dashboard` | 10 |
| `/services` | 4 | | `/dashboard/orders` | 6 |
| `/packages` | 4 | | `/dashboard/bookings` | 7 |
| `/cost-estimator` | 5 | | `/dashboard/bookings/agenda` | 4 |
| `/book` | 9 | | `/dashboard/leads` | 7 |
| `/track` | 3 | | `/dashboard/visitor-sessions` | 5 |
| `/articles` | 5 | | `/dashboard/estimator-items` | 6 |

**N+1: none.** Measured at 5 / 50 / 200 rows per table; every dashboard list stayed flat (`/dashboard/orders` 7→7→7, `/dashboard/bookings` 8→8→8, `/dashboard/leads` 8→8→8, `/dashboard/visitor-sessions` 8→7→7).

### Query plans (500 rows/table, MySQL)

| Query | Plan |
|---|---|
| Portal lookup by tracking code | `type=const key=orders_tracking_code_hash_unique rows=1` |
| Booking capacity check (inside the lock) | `type=range key=bookings_scheduled_at_status_index rows=3` |
| Orders queue | `type=range key=orders_status_placed_at_index` |
| Bookings queue | `type=range key=bookings_status_scheduled_at_index` |
| Estimator catalogue | `type=ALL … Using filesort` ← §3.1 |

---

# Prioritised backlog

Ordered most-blocking first. **Dimension · Severity · Effort**.

---

## 1. Launch blockers

### 1.1 🔴 Packages cannot be created, priced or edited from the dashboard
**Completeness · 🔴 · M**

There is no `dashboard.packages.*` route, no `PackageController`, and no view. `php artisan route:list | grep dashboard.*package` returns nothing. `resources/views/components/dashboard/sidebar.blade.php:22` *does* list a Packages entry, but the sidebar filters on `Route::has()` so it is silently dropped — which is why the gap is invisible in the UI.

Consequences, all live today:
- `/packages` renders "On request" for all four (`PackageSeeder` ships `price = null` by design, `resources/views/public/packages/index.blade.php:32`).
- The estimator's step-1 base-package selector shows every package as "priced on request" (`resources/views/public/estimator.blade.php:96`), so a base package contributes nothing to any estimate.
- An admin creating an order can attach a package but cannot maintain the catalogue.

This is the one gap that cannot be closed by data entry. Every other content resource (services, countries, FAQs, testimonials, articles, pages, media, menu, estimator items, tracking, settings) has full CRUD.

**Fix:** a `PackageController` + Form Requests + service following the `EstimatorItemController` pattern exactly (`app/Http/Controllers/Dashboard/EstimatorItemController.php`), with `price` nullable so "on request" stays expressible.

---

### 1.2 🔴 No real commercial or contact data anywhere
**Completeness · 🔴 · S** (data entry, but §1.1 blocks the package half)

Measured on a fresh production seed:

```
packages          4 rows, price=NULL on all four
estimator_items  24 rows, 0 active, 0 priced above zero
settings         contact_phone / contact_email / contact_whatsapp /
                 contact_address / social_* / site_logo / home_stat_* all NULL
tracking_settings 7 rows, every value NULL, every row inactive
```

Effects: the estimator shows its "pricing in preparation" panel instead of a calculator (`app/Services/Estimator/EstimatorService.php:76-91`); the footer and `/contact` render no contact rows at all (`resources/views/public/contact.blade.php:15`); no analytics or ad pixel fires.

This is all deliberate — the codebase refuses to invent numbers — but it means the site cannot currently take a commercial enquiry with a price attached.

**Pre-launch content checklist**
- [ ] Package prices (blocked by §1.1) — or confirm "on request" is the intended commercial model
- [ ] Estimator: set a real price on each of the 24 items and activate them (Dashboard → حاسبة التكلفة). Items priced at zero **cannot** be activated (`app/Http/Requests/Dashboard/StoreEstimatorItemRequest.php:62-69`)
- [ ] Settings → contact phone, email, WhatsApp, address
- [ ] Settings → social links (empty ones are hidden, so partial is fine)
- [ ] Settings → site logo, homepage stats (hidden while empty — leave blank rather than invent)
- [ ] Tracking → GA4 / GTM / Meta / TikTok IDs, then activate
- [ ] Booking → open the weekly hours and confirm slot length/capacity (seeded Sun–Thu 09:00–17:00, 45 min, capacity 1)
- [ ] `ADMIN_EMAIL` / `ADMIN_PASSWORD` in production `.env` — the seeder **throws** rather than falling back outside local/testing (`database/seeders/AdminUserSeeder.php:25-37`)

---

### 1.3 🔴 Legal pages are unreviewed drafts
**Completeness · 🔴 · S** (external dependency)

`database/seeders/PageContentSeeder.php:9-31` states in its own docblock that the privacy policy and terms are "conservative professional drafts… a starting point for review, not a substitute for legal advice". They are factually accurate about what the software does (truncated IPs, consent gating, DNT/GPC, retention window) but have had no legal review.

The privacy policy also promises automatic deletion after the retention window — **which is only true if the scheduler cron is installed** (§7.4, `routes/console.php:14-24`).

---

## 2. Should fix before launch

### 2.1 🟡 Campaigns can never be created, so campaign attribution is inert
**Completeness · 🟡 · M**

`AttributionService` resolves `linked_campaign_id` by matching `campaign_id`/`utm_campaign` against `campaigns.external_campaign_id`, and `LeadsAndInboxTest` proves the linking works. But there is no dashboard route for campaigns, and `grep -rn "Campaign::create" app/ database/seeders/` finds nothing outside factories. The `campaigns` table will be empty forever, so `linked_campaign_id` is always null in production and the "Linked campaign" panel on the lead screen never populates.

The raw `campaign_id` string **is** still captured, so no attribution data is lost — only the resolved join is dead.

**Fix:** either a small Campaigns CRUD, or accept it and remove the dead panel.

---

### 2.2 🟡 No confirmation message reaches a client, for bookings or orders
**Completeness / Business · 🟡 · M**

`MAIL_MAILER=log` and `app/Services/Booking/BookingNotifier.php` writes a debug line instead of sending. This is deliberate and honestly surfaced — the confirmation screen says the studio will be in touch rather than claiming an email was sent (`resources/views/public/booking.blade.php:60-64`), and `BookingFlowTest::test_nothing_claims_a_message_was_sent` pins it.

But at launch: a client who books gets a reference on screen and **nothing afterwards**, and a client given a 64-character tracking code has no message containing it — the admin must copy it by hand from the one-time banner (`resources/views/dashboard/orders/show.blade.php:17-30`).

**Fix:** configure SMTP, then implement the three seams in `BookingNotifier` (`bookingReceived`, `bookingConfirmed`, `bookingCancelled`) and add an equivalent for order tracking codes. Queue them — `QUEUE_CONNECTION=database` and the `jobs` table already exist, but **nothing is queued today** (`grep -rn ShouldQueue app/` → no matches), so no worker is needed until this lands.

---

### 2.3 🟡 No user management
**Completeness · 🟡 · M**

`app/Http/Controllers/Dashboard/` has no `UserController`. A second admin can only be created by re-running `AdminUserSeeder` with different env values, or via tinker. There is also no way to revoke an admin, and `is_admin` is guarded so it cannot be set through any existing form.

---

### 2.4 🟡 The booking date/slot picker announces an ARIA pattern it does not implement
**Accessibility · 🟡 · S**

`resources/views/public/booking.blade.php:150` and `:186` wrap the day and time buttons in `role="radiogroup"` with `role="radio"` + `:aria-checked` on each `<button>`. The WAI-ARIA radiogroup pattern requires arrow-key navigation with a roving tabindex. `grep -n "keydown\|Arrow\|tabindex" resources/js/booking.js` finds nothing.

In practice every button is individually tabbable (default `<button>` behaviour), so the widget *is* keyboard-operable — but a screen-reader user told "radio group, 1 of 21" will press arrow keys and nothing will happen.

**Fix (pick one):**
- (S) Drop the ARIA roles and let them be plain buttons in a labelled group — honest and immediately correct; or
- (M) Implement arrow-key navigation + roving tabindex to match the announced role.

The estimator takes the better route already: native `<input type=radio>`/`<checkbox>` inside `<label>`, which is why it needs zero ARIA (`resources/views/public/estimator.blade.php:81,89,128`).

---

### 2.5 🟡 Malformed dates silently empty two dashboard lists instead of being ignored
**Code quality / UX · 🟡 · S**

`app/Services/Orders/OrderReportService.php:72` and `app/Services/Analytics/VisitorSessionReportService.php:99` validate date filters with a **shape** check, `preg_match('/^\d{4}-\d{2}-\d{2}$/')`. `2026-13-45` passes it. Measured:

```
/dashboard/orders?date_from=2026-13-45            → 200, 0 of 3 rows shown
/dashboard/orders?date_from=2026-02-31            → 200, 3 of 3 rows shown
/dashboard/visitor-sessions?date_from=2026-13-45  → 200, 0 of 3 rows shown
```

No crash (the value goes to `whereDate()` as a bind, not through Carbon), but the admin sees an empty list with no indication the filter was nonsense.

The booking module already solved this: `app/Services/Booking/BookingDate.php` does a strict `CarbonImmutable::hasFormat()` check and drops the filter. **The same helper should be used in all three places** — it exists precisely because the shape check was found to be wrong during the booking build.

---

### 2.6 🟡 `X-Powered-By: PHP/8.5.2` leaks the exact PHP version
**Security · 🟡 · S**

`app/Http/Middleware/SecurityHeaders.php:88` calls `$response->headers->remove('X-Powered-By')`, but the header is emitted by PHP itself at SAPI level, after Symfony has finished. Measured live:

```
$ curl -sI http://127.0.0.1:8891/
X-Powered-By: PHP/8.5.2      ← still present
```

**Fix:** `expose_php = Off` in the production `php.ini` (see `docs/DEPLOYMENT.md` §4). The PHP-level removal cannot do it.

---

### 2.7 🟡 `robots.txt` points at a relative sitemap; `/track` is neither listed nor disallowed
**SEO · 🟡 · S**

`public/robots.txt:24` ends with `Sitemap: /sitemap.xml`. The sitemaps.org spec requires an **absolute** URL; most crawlers ignore a relative one.

Separately, `/track` and `/en/track` are absent from `SitemapController::STATIC_ROUTES` (`app/Http/Controllers/Public/SitemapController.php:36-50`) and are not in `robots.txt` either — so the client portal is crawlable but unadvertised. Measured: sitemap has 13 URLs, `cost-estimator` ✓, `book` ✓, `track` ✗.

**Fix:** serve `robots.txt` from a route so `Sitemap:` can be `route('sitemap')` (absolute), and make a deliberate choice on `/track` — I would `Disallow` it, since it is a credential form with no indexable content.

---

### 2.8 🟡 CSP is Report-Only
**Security · 🟡 · M**

`app/Http/Middleware/SecurityHeaders.php:76-88` ships `Content-Security-Policy-Report-Only` with `'unsafe-inline' 'unsafe-eval'`, documented as deliberate: Alpine evaluates `x-*` expressions with `new Function()`, and the hero/marquee pass tuned durations through inline styles. Verified present on both a 200 and a 404.

Report-Only provides **no protection** — it only reports. Tightening needs Alpine's CSP build plus a nonce, at which point both `unsafe-*` can go and it becomes enforcing.

---

### 2.9 🟡 Real-browser QA has not been done — **UNVERIFIED**
**Responsive & visual · 🟡 · M**

Everything below was reviewed in Blade/Tailwind only. Headless screenshots at 375 render at a wider effective viewport in this environment (a known artifact reproduced across builds), so **375 px was never truly observed**. These need a real device or browser devtools:

| Surface | What to check |
|---|---|
| Estimator (`/cost-estimator`) | Sticky summary at `lg`; 2-col item grid collapsing at `sm`; live total updating on tap; "from X" wording when a package is on request |
| Booking picker (`/book`) | 21 day-buttons wrapping at 375; time buttons appearing after a day is tapped; the disabled submit re-enabling |
| Order timeline (`/track`) | Progress bar filling **from the right** in RTL; four stage rows at 375 |
| Order detail (dashboard) | Per-transition forms stacking; the 64-char code block wrapping rather than overflowing |
| Booking availability (dashboard) | 6-column week table scrolling inside its own box (`overflow-x-auto` confirmed present) |
| Marquees | RTL direction pinning (`direction: ltr` on track + items) — previously a real bug |
| Mobile drawer | Opens/closes at 375; static closed-state classes mean it must render without JS |

Structural checks that **did** pass: every wide dashboard table has an `overflow-x-auto` wrapper (grep over all `min-w-[` views found zero unwrapped); the new public pages use `sm:`/`lg:` grids with no fixed widths.

---

### 2.10 🟡 `public/storage` symlink is absent
**Production · 🟡 · S**

`ls -la public/storage` → not found. Every uploaded image (service covers, article covers, avatars, flags, OG images, media library, site logo) resolves through `Storage::url()` to `/storage/...` and will 404 until `php artisan storage:link` runs. Included as a deployment step, but flagged because nothing in the app surfaces the failure.

---

### 2.11 🟡 `.env.example` is missing the two booking config keys
**Production · 🟡 · S**

`config/booking.php` reads `BOOKING_TIMEZONE` and `BOOKING_NOTIFICATIONS_ENABLED`; neither appears in `.env.example`. Both have safe defaults (`Asia/Riyadh`, `false`), so nothing breaks — but a deployer copying the example will not know the timezone is configurable.

---

## 3. Nice to have

### 3.1 🟢 The estimator catalogue query no longer uses its index
**Performance · 🟢 · S**

`EstimatorItem::grouped()` (`app/Models/EstimatorItem.php:96-115`) orders by `sort_order, id`, but the index is `(is_active, group, sort_order)`. Measured:

```
EXPLAIN SELECT * FROM estimator_items WHERE is_active = 1 ORDER BY sort_order, id
→ type=ALL  key=NULL  rows=24  Extra: Using where; Using filesort
```

The sort was changed deliberately during the estimator build (grouping by slug put "content" above "visual identity" on the live page), and 24 rows makes this free. It is recorded only so the index's stated intent and its actual use do not silently diverge. Either widen the index to `(is_active, sort_order)` or note on the index that the group ordering is presentational.

### 3.2 🟢 `Comment.status` is mass-assignable
**Security · 🟢 · S**

`app/Models/Comment.php` includes `status` in `$fillable` — unlike every other privileged column in the schema (`Order::status/progress/reference/tracking_code_hash`, `Booking::status/reference`, `Lead::is_handled`, `User::is_admin` are all guarded and pinned by `tests/Unit/MassAssignmentGuardTest.php`).

**Not exploitable.** Probed directly: `POST /articles/{a}/comments` with `status=approved` → stored as `pending`, because the controller whitelists with `$request->safe()->only([...])` and then forces the value (`app/Http/Controllers/Public/CommentController.php:35-47`). But that controller's own comment claims "three things would each have to fail", and one of the three is not in fact a barrier.

### 3.3 🟢 `VisitorSession.lead_id` is mass-assignable
**Security · 🟢 · S**

Only ever written server-side by `VisitorSessionService::linkLead()`. No request path reaches it. Same asymmetry as 3.2.

### 3.4 🟢 Three near-identical `filtersFrom()` implementations
**Code quality · 🟢 · M**

`BookingReportService`, `OrderReportService` and `VisitorSessionReportService` each implement the same whitelist-then-validate shape with different field lists — and, as §2.5 shows, they have already drifted in strictness. A shared trait or small value object would have made that drift impossible.

### 3.5 🟢 Two status-graph implementations
**Code quality · 🟢 · S**

`BookingStatusService::TRANSITIONS` and `OrderService::TRANSITIONS` are the same construct with different rules (bookings terminal, orders reversible — both correct and both documented). Worth noting only if a third appears.

### 3.6 🟢 Five public controllers repeat the same submission sequence
**Code quality · 🟢 · M**

Honeypot → attribution → create lead → record visitor-session conversion → `lroute()` redirect appears in `ContactController`, `PackageEnquiryController`, `CommentController`, `EstimatorController`, `BookingController`. This is arguably correct — each differs in what it creates, and an abstraction would hide the honeypot check, which must run before *any* write. Listed for visibility, not as a defect.

### 3.7 🟢 Some public images lack intrinsic dimensions (CLS)
**Performance · 🟢 · S**

`resources/views/public/articles/index.blade.php` and `articles/show.blade.php`, plus `services/show.blade.php`, render `<img>` without `width`/`height`. Navbar and footer logos do carry them (`navbar.blade.php:38`, `footer.blade.php:22`). Article covers are the largest above-the-fold images on those pages, so this is where layout shift will show.

### 3.8 🟢 Test coverage gaps
**Test health · 🟢 · M**

1,137 passing, 1 skipped **by design** (`BookingRaceTest::test_the_capacity_check_takes_a_lock` skips on SQLite because it asserts `FOR UPDATE`, which SQLite ignores — it runs and passes on MySQL).

Not covered by a dedicated write-path test, though all are walked by `RouteSweepTest` for GET/gating:
- Page **sections** CRUD (`PageSectionController`) — create/update/delete
- Media library upload and delete (`MediaController`)
- Country and testimonial **writes** are covered generically by `ContentCrudTest`'s data provider, so this is fine
- `DashboardHomeController` — the overview stat tiles
- No end-to-end test drives a booking **from the public page through to a dashboard status change** (the two halves are tested separately)

---

## What was attacked and held

Recorded because these are the properties most worth re-checking after any change:

| Property | How it was verified | Result |
|---|---|---|
| Order enumeration | Wrong code + real ref vs. non-existent ref, live | Byte-identical `302 → /track#track-form` |
| Portal lookup plan | `EXPLAIN` with a real hash | `type=const`, `rows=1`, unique index |
| Tracking code storage | Created an order over HTTP, inspected the row | 64-hex shown once; only SHA-256 stored; second visit shows nothing |
| Booking double-booking | Filled a slot to capacity, live | Second attempt refused, 1 booking total |
| Booking lock | `DB::listen` on the real transaction | `SELECT … FOR UPDATE` present (MySQL) |
| Crafted slot time | `POST /book` with `time=03:17` | Rejected, 0 bookings |
| XSS through rich text | FAQ answer with `<script>` + `onerror` via the real write path | Stored as `<p>ok</p><img src="x" alt="x">`; neither string on the rendered page |
| Unescaped output | Every `{!! !!}` traced to its write path | All 10 map to HTMLPurifier-sanitised fields or developer-controlled SVG |
| `storage/{path}` PUT (no middleware) | Unsigned `PUT` of `.txt` and `.php`, live | `403`, nothing written — signature-gated |
| Login brute force | Read `LoginRequest` | Breeze rate-limits 5/attempt on email+IP (`app/Http/Requests/Auth/LoginRequest.php:61-77`) |
| Portal rate limit | 12 sequential lookups, live | 7 through, then `429` (10/min incl. 3 earlier from that IP) |
| Comment status escalation | `POST` with `status=approved` | Stored `pending` |
| Security headers on errors | `curl -I` on a 404 | All headers present on the exception path |
| Consent gating | `MarketingConsentTest` (11 tests) | No pixel loads pre-consent; DNT/GPC overrides a granted cookie |
| Production seed purity | Row counts after `migrate:fresh --seed` | 0 rows in all 9 fabricable tables |

---

## Suggested order of work

1. **§1.1** Packages CRUD — the only structural blocker (M)
2. **§1.2** Content entry, once §1.1 lands (S)
3. **§1.3** Send the legal pages for review (S, external)
4. **§2.6 · §2.7 · §2.10 · §2.11** Deployment-adjacent one-liners (S each)
5. **§2.5** Route the two remaining date filters through `BookingDate` (S)
6. **§2.4** Fix or drop the ARIA radiogroup (S)
7. **§2.9** Real-browser QA pass (M) — do this *after* content lands, so the pages are populated
8. **§2.2** Mail transport + notifier implementation (M)
9. **§2.1 · §2.3** Campaigns and user management (M each)
10. Everything in §3 as capacity allows

---

## Outstanding non-code blocker

`git push origin main` fails and has done for several build steps. 36 commits sit on local `main` with a clean tree.

```
$ git push origin main
fatal: could not read Username for 'https://github.com': Device not configured
```

Remote is `https://github.com/m77mdabdo/Brand_Makers.git`. The macOS keychain credential for `m77mdabdo` is expired, and the SSH key present on this machine authenticates as `creativemarksa-afk`, which is denied push access to that repository. **Nothing is backed up off this machine.** Resolve before any deployment: either refresh the PAT for `m77mdabdo` or repoint the remote at a repository the SSH identity can write to.
