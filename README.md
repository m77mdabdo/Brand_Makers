<div align="center">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="public/logo-light.png">
  <img src="public/logo-dark.png" alt="Creative Mark — صنّاع العلامات التجارية" width="200">
</picture>

# Creative Mark · صنّاع العلامات التجارية

**A bilingual company-formation and investment platform for Saudi Arabia.**
Public website, live cost estimator, consultation booking, client order tracking
and a full admin dashboard, built on Laravel 12.

<p>
  <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="MySQL 8" src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white">
  <img alt="Tailwind CSS 3" src="https://img.shields.io/badge/Tailwind_CSS-3-06B6D4?logo=tailwindcss&logoColor=white">
  <img alt="Alpine.js 3" src="https://img.shields.io/badge/Alpine.js-3-8BC0D0?logo=alpinedotjs&logoColor=white">
  <img alt="Vite 7" src="https://img.shields.io/badge/Vite-7-646CFF?logo=vite&logoColor=white">
</p>
<p>
  <img alt="Tests" src="https://img.shields.io/badge/tests-1%2C366_passing-2EA44F">
  <img alt="Assertions" src="https://img.shields.io/badge/assertions-4%2C343-2EA44F">
  <img alt="Languages" src="https://img.shields.io/badge/i18n-Arabic_(RTL)_%7C_English-0E3B56">
</p>

<img src="docs/screenshots/home-en.png" alt="Creative Mark homepage" width="100%">

</div>

---

## Contents

- [Overview](#overview)
- [Features](#features)
- [Screenshots](#screenshots)
- [Architecture](#architecture)
- [Security and privacy](#security-and-privacy)
- [Quality and testing](#quality-and-testing)
- [Getting started](#getting-started)
- [Deployment](#deployment)
- [Project timeline](#project-timeline)
- [Roadmap](#roadmap)
- [Documentation](#documentation)
- [نبذة بالعربية](#نبذة-بالعربية)

---

## Overview

**Creative Mark** is a Saudi advisory firm that takes founders and foreign investors
from an idea to an active Commercial Registration: company formation, investment
licences, acquisition of existing companies, and every government procedure in between.

This repository is the firm's complete web platform. It covers the **whole funnel**:

```
 Visitor ──► Website (AR / EN) ──► Cost estimator · Package request · Booking · Contact
                                                   │
                                                   ▼
                                     Lead captured, with its marketing source
                                                   │
                                                   ▼
                     Admin dashboard ──► Order created ──► Client tracks progress with a private code
```

Everything a visitor reads, every price they see, and every slot they can book
is managed from the dashboard, with no code changes and no database access.

| | |
|---|---|
| **Languages** | Arabic (primary, right-to-left) and English, on every page and every content field |
| **Public pages** | Home, Services, Activities, Packages, Cost Estimator, Booking, Order Tracking, Countries, Blog, FAQs, About, Our Process, Why Saudi Arabia, Contact, Privacy, Terms |
| **Dashboard sections** | 23 sections: leads, orders, bookings, campaigns, visitor sessions, content, pricing, settings and more |
| **Data model** | 35 tables · 27 Eloquent models, all with factories |
| **Test suite** | 1,366 tests · 4,343 assertions, green on SQLite and MySQL |

---

## Features

### Public website

- **Fully bilingual, RTL-first.** Arabic at `/`, English at `/en/…`, with mirrored layouts,
  `hreflang` alternates and a language switch that keeps you on the same page.
- **Formation packages.** Three published packages (Basic, Integrated, Comprehensive) with
  prices and feature lists, plus a request form that pre-selects the chosen package.
- **Live cost estimator.** Visitors pick a formation type and add services from grouped
  selectors. The estimate updates instantly, flags items that are priced on request, and
  can be sent as a lead in one step.
- **Consultation booking.** A real availability engine: weekly hours, slot length,
  per-slot capacity, blackout dates and a minimum notice period. Times are shown in
  Riyadh time and stored in UTC, and double-booking a full slot is impossible.
- **Order tracking portal.** Clients follow their order's status, progress and timeline
  with a private tracking code, with no account needed.
- **Editorial content.** Services, activities, countries, blog articles with moderated
  comments, FAQs and editable static pages built from reorderable sections.
- **SEO built in.** Per-page meta and Open Graph tags, canonical URLs, an auto-generated
  `sitemap.xml` drawn from real routes, `robots.txt`, and branded 403/404/419/500/503 pages.

### Admin dashboard

| Area | What it does |
|---|---|
| **Leads** | Every enquiry from every form in one inbox, with its source, UTM campaign and handled state |
| **Contact messages** | A separate queue for contact-form messages, with unread badges |
| **Orders** | Create orders, set status and progress, write a client-visible timeline, issue or reissue tracking codes |
| **Bookings** | List and agenda views, status workflow, weekly availability and blackout dates |
| **Campaigns** | Marketing campaigns, linked back to the leads they produced |
| **Visitor sessions** | Consent-gated visitor journeys: entry page, pages viewed, device, source and conversion, with JSON export |
| **Pricing** | One price per item, set once, live on both the packages page and the estimator |
| **Content** | Services, packages, articles, comments, FAQs, testimonials, countries, pages and sections, menu ordering and visibility, media library |
| **Settings** | Contact details, social links, homepage stats, booking rules, and tracking pixels (GA4, GTM, Meta, TikTok) behind a consent gate |

The dashboard has its own language toggle (Arabic or English), independent of the public site.

### Marketing and analytics

- **Attribution.** UTM parameters and referrers are captured on the first visit and
  attached to the lead the visitor eventually submits.
- **Privacy-first visitor journeys.** Recorded server-side, off the critical rendering
  path, only after consent, and never when the browser sends Do Not Track or Global Privacy Control.
- **Consent-gated pixels.** Marketing scripts load only after the visitor accepts.
- **Automatic retention.** A scheduled job deletes visitor sessions older than the
  configured window (90 days by default).

---

## Screenshots

### Public website

| Homepage (Arabic, RTL) | Homepage (English) |
|---|---|
| <img src="docs/screenshots/home-ar.png" alt="Arabic homepage"> | <img src="docs/screenshots/home-en.png" alt="English homepage"> |

| Cost estimator, live total | Formation packages |
|---|---|
| <img src="docs/screenshots/estimator.png" alt="Cost estimator"> | <img src="docs/screenshots/packages.png" alt="Packages"> |

| Consultation booking | Order tracking portal |
|---|---|
| <img src="docs/screenshots/booking.png" alt="Booking"> | <img src="docs/screenshots/track.png" alt="Order tracking"> |

| Services | Blog |
|---|---|
| <img src="docs/screenshots/services.png" alt="Services"> | <img src="docs/screenshots/articles.png" alt="Blog"> |

| FAQs | Contact |
|---|---|
| <img src="docs/screenshots/faqs.png" alt="FAQs"> | <img src="docs/screenshots/contact.png" alt="Contact"> |

### Mobile

<p align="center">
  <img src="docs/screenshots/mobile-home.png" alt="Mobile homepage" width="30%">
  &nbsp;
  <img src="docs/screenshots/mobile-estimator.png" alt="Mobile estimator" width="30%">
  &nbsp;
  <img src="docs/screenshots/mobile-booking.png" alt="Mobile booking" width="30%">
</p>

### Admin dashboard

| Overview | Orders |
|---|---|
| <img src="docs/screenshots/dash-home.png" alt="Dashboard overview"> | <img src="docs/screenshots/dash-orders.png" alt="Orders"> |

| Leads | Bookings |
|---|---|
| <img src="docs/screenshots/dash-leads.png" alt="Leads"> | <img src="docs/screenshots/dash-bookings.png" alt="Bookings"> |

| Visitor sessions | Campaigns |
|---|---|
| <img src="docs/screenshots/dash-visitors.png" alt="Visitor sessions"> | <img src="docs/screenshots/dash-campaigns.png" alt="Campaigns"> |

| Estimator pricing | Packages |
|---|---|
| <img src="docs/screenshots/dash-estimator.png" alt="Estimator pricing"> | <img src="docs/screenshots/dash-packages.png" alt="Packages management"> |

| Pages and sections | Settings |
|---|---|
| <img src="docs/screenshots/dash-pages.png" alt="Pages"> | <img src="docs/screenshots/dash-settings.png" alt="Settings"> |

| Tracking and consent |
|---|
| <img src="docs/screenshots/dash-tracking.png" alt="Tracking settings" width="50%"> |

> Dashboard screenshots use the bundled demo data (`DemoDataSeeder`), so every name is marked *sample data*.

---

## Architecture

### Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 · PHP 8.2+ |
| Database | MySQL 8 (SQLite in-memory for tests) |
| Frontend | Blade · Tailwind CSS 3 · Alpine.js 3 (+ Focus) · Vite 7 |
| Translations | `spatie/laravel-translatable` (every human-readable field is a JSON column) |
| Rich text | `mews/purifier` (HTML Purifier) for all admin-authored HTML |
| Auth | Laravel Breeze, with public registration removed and an admin gate |
| Tooling | PHPUnit 11 · Laravel Pint · Laravel Pail |

### Project layout

```
app/
├── Console/Commands/      # visitor-sessions:prune, content:translate-to-english
├── Http/
│   ├── Controllers/
│   │   ├── Public/        # 15 public controllers (site, forms, estimator, booking, tracking)
│   │   └── Dashboard/     # 24 admin controllers
│   ├── Middleware/        # SetLocale, SetDashboardLocale, EnsureUserIsAdmin,
│   │                      # SecurityHeaders, TrackVisitorSession
│   └── Requests/          # Form Requests, one per write action
├── Models/                # 27 models + shared concerns (slugs, translations)
├── Services/              # all business logic lives here, not in controllers
│   ├── Analytics/         # consent gate, IP anonymiser, sessions, user-agent parsing
│   ├── Booking/           # slot generator, availability, status workflow, notifier
│   ├── Cms/               # content services, settings, menu, media, HTML sanitiser
│   ├── Crm/               # contact messages
│   ├── Estimator/         # quote calculation
│   ├── Marketing/         # campaigns, attribution
│   ├── Orders/            # orders, timeline, tracking-code lookup, reports
│   └── Seo/               # meta tags and SEO records
└── Support/               # CalendarDate, Honeypot
database/
├── migrations/            # 35 tables, indexes declared inside CREATE TABLE
├── factories/             # a factory for every model
└── seeders/               # production content, kept apart from Demo* seeders
lang/{ar,en}/              # site.php and dashboard.php
resources/views/
├── public/  dashboard/    # page templates
├── components/            # shared Blade components (public, dashboard, SEO, tracking)
└── errors/                # branded error pages
docs/                      # data-layer reference, deployment guide, screenshots
```

### Design principles

- **Thin controllers, explicit services.** Controllers validate through Form Requests
  and delegate. Every workflow (quoting, booking, order status, publishing) lives in one
  service with one responsibility.
- **One source of truth for prices.** A package or service price is set once in the
  dashboard and read by both the packages page and the estimator.
- **Content is data.** Navigation, page sections, FAQs, prices, contact details and
  pixel IDs are all managed from the dashboard.
- **Production and demo data never mix.** `migrate --seed` installs real content only.
  Demo records live in separate `Demo*` seeders, and a test scans every readable column
  to keep it that way.
- **Clean rollbacks.** Indexes are declared inside `CREATE TABLE`, so every `down()` is a
  plain drop. A test enforces this and runs the full migrate, rollback, migrate cycle.

---

## Security and privacy

| Concern | Implementation |
|---|---|
| **Admin access** | Public registration removed; every dashboard route is behind `auth` and an `EnsureUserIsAdmin` gate |
| **Mass assignment** | `is_admin`, statuses, order progress, references and tracking hashes are outside `$fillable` and change only through explicit model methods. A test guards each one |
| **Tracking codes** | Only the SHA-256 of a 32-byte random value is stored. The code is shown once, cannot be recovered, and lookup accepts the code, never the reference |
| **IP addresses** | Truncated in a model mutator, so no code path can store a full address |
| **Rich text** | All admin HTML passes through HTML Purifier before storage |
| **Spam** | Honeypot fields and rate limiting on every public form |
| **Headers** | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS in production, a Content Security Policy, and `X-Powered-By` removed |
| **Consent** | Visitor tracking and marketing pixels run only after opt-in, and never under DNT or GPC |
| **Retention** | Visitor sessions are pruned daily by the scheduler |
| **Sessions** | Session regenerated on login; secure cookies in production |

---

## Quality and testing

```
Tests:    1 skipped, 1366 passed (4343 assertions)
Duration: ~49s
```

The suite covers far more than happy paths. Some of the guards it includes:

- **Route sweep.** Every public and dashboard route renders in both languages without errors.
- **Language file integrity.** No missing, duplicate or leaked translation keys in `ar` or `en`.
- **Brand contrast.** The palette's text and background pairs are pinned to WCAG contrast ratios.
- **Mass-assignment guard.** Protected columns stay out of `$fillable`.
- **Schema indexes and migration cycle.** Indexes are declared correctly and every migration rolls back cleanly.
- **Demo data separation.** No sample record can reach a production seed.
- **Hardening.** Security headers, rate limits, honeypots and authorization on every write.
- **Booking.** Slot generation across timezones, capacity limits, blackout dates and concurrent requests.
- **Pricing regressions.** English package names, features and estimator totals are locked in place.

```bash
php artisan test                                                   # SQLite, in-memory
DB_CONNECTION=mysql DB_DATABASE=brand_makers_testing php artisan test   # against MySQL
./vendor/bin/pint                                                  # code style
```

---

## Getting started

### Requirements

PHP 8.2+ (with `intl`, `mbstring`, `pdo_mysql`), Composer 2, Node 18+, MySQL 8.

### Install

```bash
git clone https://github.com/m77mdabdo/Brand_Makers.git
cd Brand_Makers

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`. Outside a local environment,
also set `ADMIN_EMAIL` and `ADMIN_PASSWORD`; the admin seeder refuses to fall back to
placeholder credentials anywhere else.

```bash
php artisan migrate --seed       # schema + real production content
php artisan db:seed --class=DemoDataSeeder   # optional: sample leads, orders, bookings, campaigns
npm run build                    # or `npm run dev` while developing
php artisan serve
```

Open `http://127.0.0.1:8000` for the Arabic site, `/en` for English, and `/login` for the dashboard.

### Useful commands

| Command | Purpose |
|---|---|
| `composer dev` | Runs the server, queue, log viewer and Vite together |
| `php artisan visitor-sessions:prune` | Deletes visitor sessions past the retention window (scheduled daily at 03:10) |
| `php artisan content:translate-to-english` | Fills missing English translations for seeded content |
| `php artisan schedule:run` | Run every minute from cron in production |

---

## Deployment

A full step-by-step guide for Hostinger (hPanel, LiteSpeed, MySQL) is in
**[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**. It covers PHP settings, the document root,
the production `.env`, the storage link, cron and the queue worker, caching, and a
post-deploy checklist.

---

## Project timeline

The platform was built in focused phases between **11 and 17 August 2026**: 55 commits,
each one self-contained and tested.

| Phase | Delivered |
|---|---|
| **1 · Data layer** | Full schema (35 tables) and Eloquent models, a factory for every model, production and demo seeders kept separate, and the first data-layer test suite |
| **2 · Foundation** | Brand design system and frontend toolchain; bilingual routing, locale helpers and SEO tags; public layout and homepage; authentication with registration removed and the admin gate |
| **3 · CMS** | Dashboard shell and settings manager; branded error pages, sitemap and robots; content services with HTML sanitising; CRUD with tests for every content type |
| **4 · Public site and lead capture** | Public content pages with dynamic navigation; every public form with lead capture and marketing attribution |
| **5 · Privacy-first analytics** | Consent gate; server-side visitor journeys; consent-gated marketing pixels; linking journeys to enquiries; scheduled retention; the Visitor Sessions dashboard; privacy-policy disclosure |
| **6 · Cost estimator** | Quote service, public estimator with lead capture, and the dashboard pricing screen |
| **7 · Booking** | Booking references and timezones, slot generator and booking service, public booking flow, availability settings and the bookings dashboard |
| **8 · Client portal and operations** | Order tracking portal; order management; packages and campaigns CRUD with lead back-linking; one strict date validator shared by every dashboard filter; an accessible radio-group booking picker |
| **9 · Repositioning** | Moved the catalogue from branding services to company formation: priced formation packages, a rebuilt estimator catalogue, new editorial pages (Activities, Why Saudi Arabia), new FAQs and articles, and English translations of all seeded content |
| **10 · Funnel and pricing polish** | Rewrote the site chrome and wired the funnel end to end; working menu reordering; regression guards for language keys and English package content; estimator rebuilt as admin-managed grouped selectors; **one price per item, set once, live on both surfaces** |

---

## Roadmap

### Before public launch

- [ ] **Real business data.** Enter the contact phone, email, WhatsApp, address, social links
      and homepage stats in *Settings*, then set the remaining estimator prices that still read "on request".
- [ ] **Analytics IDs.** Add the GA4, GTM, Meta and TikTok IDs in *Tracking*, then activate them.
- [ ] **Legal review.** Have the Privacy Policy and Terms reviewed by a lawyer.
- [ ] **Booking hours.** Confirm the weekly hours, slot length and capacity (seeded as Sunday to Thursday, 09:00 to 17:00, 45-minute slots).
- [ ] **Production environment.** Admin credentials, `storage:link`, `expose_php = Off`, and the scheduler cron (see the deployment guide).

### Next features

- [ ] **Email notifications.** Configure a mail transport and send confirmations for new
      bookings, orders and enquiries, to the client and the team (the notifier is already in place).
- [ ] **Team management.** Invite and manage additional dashboard users and roles from the dashboard.
- [ ] **Enforcing Content Security Policy.** Switch to Alpine's CSP build with nonces so the
      policy can move from report-only to enforcing.
- [ ] **Absolute sitemap URL** in `robots.txt`, and an explicit rule for the tracking page.
- [ ] **Document `BOOKING_*` keys** in `.env.example`.

### Improvements

- [ ] Restore the index-backed query for the estimator catalogue.
- [ ] Merge the near-identical dashboard filter builders and the two status-graph implementations.
- [ ] Extract the submission sequence shared by five public controllers.
- [ ] Add intrinsic image dimensions on public pages to remove layout shift.
- [ ] Extend test coverage to the remaining edge paths.

---

## Documentation

| Document | Contents |
|---|---|
| [docs/data-layer.md](docs/data-layer.md) | Every table and model, guarded columns, tracking codes, IP anonymisation, morph map, rollback safety, production and demo seeding |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production deployment to Hostinger, end to end |

---

## نبذة بالعربية

**صنّاع العلامات التجارية (Creative Mark)** منصة متكاملة لشركة استشارية سعودية متخصصة في
تأسيس الشركات وتمكين الاستثمار في المملكة العربية السعودية.

- **موقع ثنائي اللغة** (العربية أولاً مع دعم كامل للاتجاه من اليمين لليسار، والإنجليزية).
- **حاسبة تكلفة تفاعلية** تعرض التقدير لحظياً، وتحوّل الزائر إلى عميل محتمل بخطوة واحدة.
- **حجز استشارات** بمواعيد حقيقية حسب ساعات العمل والسعة وأيام الإغلاق، بتوقيت الرياض.
- **بوابة تتبّع الطلبات** يتابع منها العميل مراحل طلبه برمز خاص، دون حساب.
- **لوحة تحكم كاملة** للطلبات والحجوزات والعملاء المحتملين والحملات وجلسات الزوار والمحتوى والأسعار والإعدادات.
- **خصوصية أولاً**: لا تتبّع إلا بعد الموافقة، وعناوين IP مقتطعة، وحذف تلقائي للبيانات القديمة.
- **جودة موثّقة**: 1,366 اختباراً آلياً ناجحاً على SQLite وMySQL.

---

<div align="center">

Designed and developed by **[Mohamed Abdo](https://github.com/m77mdabdo)**

<sub>© 2026 Creative Mark. All rights reserved.</sub>

</div>
