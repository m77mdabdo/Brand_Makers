# Creative Mark — Deployment to Hostinger

Target: Hostinger shared/business hosting (hPanel, LiteSpeed, MySQL).
Stack as built: **Laravel 12 · PHP 8.2+ · MySQL 8 · Vite/Tailwind 3 · Alpine 3**.

Read `docs/AUDIT.md` first — §1 lists three things that must be done before the site is publicly usable, one of which needs a code change.

> **Before anything:** the repository has **36 unpushed commits** and pushing currently fails (expired PAT for `m77mdabdo`; the local SSH key authenticates as `creativemarksa-afk`, which lacks write access). Nothing is backed up off the build machine. Fix that before deploying.

---

## 1. Requirements

### PHP

**8.2 minimum** (`composer.json` requires `^8.2`); built and tested on **8.5.2**. Select the highest 8.x Hostinger offers in **hPanel → Advanced → PHP Configuration**.

Extensions — all verified loaded in the build environment:

| Required by Laravel 12 | Required by this app specifically |
|---|---|
| `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` | `gd` — image uploads (services, articles, testimonials, countries, media, logo, OG images) |
| `bcmath` | `intl` — `translatedFormat()` for Arabic month/weekday names on every date shown |
| | `zip` — Composer |

In **PHP Configuration → PHP options**, set:

```ini
expose_php = Off        ; AUDIT §2.6 — otherwise X-Powered-By leaks the exact PHP version
memory_limit = 256M
upload_max_filesize = 4M ; uploads are capped at 2 MB in validation; leave headroom
post_max_size = 8M
max_execution_time = 60
```

### Composer & Node

Composer 2.x (Hostinger provides it). **Node is only needed to build assets, not to run the site** — see §5.

---

## 2. Database

hPanel → **Databases → MySQL Databases**. Create a database and a dedicated user; grant all privileges on that database only.

Keep the credentials for §4. The app uses MySQL for **sessions, cache and queue** as well as data (`sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches` — all created by the migrations), so no Redis is required.

---

## 3. Document root — the one setting people get wrong

Hostinger serves `public_html/` by default. Laravel must serve **`public/`** only; anything else exposes `.env`, `storage/` and `vendor/`.

**Preferred:** upload the application *outside* the web root and repoint the domain.

```
/home/uXXXXXXX/
├── creativemark/          ← the whole repository lives here
│   ├── app/  bootstrap/  config/  database/  resources/  routes/  storage/  vendor/
│   ├── public/            ← document root points HERE
│   └── .env
└── public_html/           ← unused, or a symlink to ../creativemark/public
```

In **hPanel → Websites → Manage → Advanced → Website root**, set the document root to `creativemark/public`.

If hPanel will not accept a root outside `public_html`, use a symlink over SSH:

```bash
rm -rf ~/public_html
ln -s ~/creativemark/public ~/public_html
```

**Verify before going live** — this must return 403 or 404, never file contents:

```bash
curl -sI https://YOURDOMAIN/.env
curl -sI https://YOURDOMAIN/storage/logs/laravel.log
curl -sI https://YOURDOMAIN/composer.json
```

---

## 4. Production `.env`

Create `.env` in the application root (not in `public/`). Complete list — every variable this application actually reads, with production values.

```dotenv
# ── Application ──────────────────────────────────────────────────────
APP_NAME="Creative Mark"
APP_ENV=production
APP_KEY=                          # php artisan key:generate --force  (§5)
APP_DEBUG=false                   # MUST be false — a stack trace exposes .env values
APP_URL=https://YOURDOMAIN        # https:// exactly; drives URL::forceScheme,
                                  # sitemap URLs, canonical tags and the storage disk URL
APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
APP_FAKER_LOCALE=ar_SA
APP_MAINTENANCE_DRIVER=file

# ── Database ─────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uXXXXXXX_creativemark
DB_USERNAME=uXXXXXXX_cmuser
DB_PASSWORD=

# ── Session / cache / queue (all MySQL-backed; no Redis needed) ──────
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true        # HTTPS only — without this the session cookie
                                  # travels in the clear
CACHE_STORE=database
QUEUE_CONNECTION=database         # nothing is queued today (no ShouldQueue classes);
                                  # required once mail lands — AUDIT §2.2

# ── Filesystem ───────────────────────────────────────────────────────
FILESYSTEM_DISK=public

# ── Mail ─────────────────────────────────────────────────────────────
# Nothing is sent today (AUDIT §2.2). Set these when the notifier is wired;
# until then password-reset emails will NOT arrive either.
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@YOURDOMAIN
MAIL_PASSWORD=
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS="noreply@YOURDOMAIN"
MAIL_FROM_NAME="${APP_NAME}"

# ── Logging ──────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily                   # daily, not single — 'single' grows unbounded
LOG_LEVEL=error                   # 'debug' would write a line per visitor-session
                                  # notification stub (BookingNotifier)
LOG_DAILY_DAYS=14
LOG_DEPRECATIONS_CHANNEL=null

# ── Proxies ──────────────────────────────────────────────────────────
# Only if a CDN/proxy (Cloudflare) sits in front. Behind LiteSpeed alone, leave unset.
# Wrong values let a client spoof its own IP, which reaches rate limiting
# and the (truncated) visitor-session IP.
# TRUSTED_PROXIES=*

# ── Admin bootstrap (REQUIRED — the seeder throws without these) ──────
# AdminUserSeeder refuses to fall back to its published dev credentials
# outside local/testing. Use a long unique password; it is hashed on seed.
ADMIN_EMAIL=you@YOURDOMAIN
ADMIN_PASSWORD=

# ── Booking (defaults are correct; listed for visibility — AUDIT §2.11) ─
BOOKING_TIMEZONE=Asia/Riyadh      # the studio's wall clock. scheduled_at stays UTC.
BOOKING_NOTIFICATIONS_ENABLED=false  # leave false until a transport exists
```

`chmod 600 .env`.

---

## 5. Build and install

`public/build/` is **gitignored and not committed**, so the compiled assets must be produced somewhere with Node. Hostinger shared plans generally have no Node — so **build locally and upload the output**.

### On your machine

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build          # writes public/build/{manifest.json,assets/*}
```

Upload the whole tree **including `vendor/` and `public/build/`**, excluding `node_modules/`, `.git/`, `tests/`, and your local `.env`.

> `composer install --no-dev` omits Pint and PHPUnit. Never run `--no-dev` on the machine you test on.

### On the server (SSH)

```bash
cd ~/creativemark

php artisan key:generate --force        # only if APP_KEY is empty
php artisan migrate --force             # --force is required in production
php artisan db:seed --force             # production content only — see below
php artisan storage:link                # AUDIT §2.10 — uploads 404 without this

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**What `db:seed` creates** (verified by row count on a fresh seed): 1 admin, 25 settings, 7 tracking rows, 7 booking-availability rows, 4 pages + 5 sections, 8 services, 4 packages, 24 estimator items, 8 countries, 10 FAQs, 3 articles, 27 SEO records, 8 menu items, 13 lead sources.

**What it does NOT create — zero rows in all of these, by design:** leads, testimonials, comments, campaigns, conversion events, bookings, orders, visitor sessions, media.

> **Never run `php artisan db:seed --class=DemoDataSeeder` on production.** It fabricates leads, testimonials, campaigns, revenue figures, bookings and orders. It is not called by `DatabaseSeeder`.

### Re-deploying later

```bash
cd ~/creativemark
php artisan down --render="errors::503"
# upload changed files
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache \
  && php artisan view:cache && php artisan event:cache
php artisan up
```

Seeders are safe to re-run — every one is idempotent and create-only, and none overwrites a price, opening hours or page copy an admin has edited. Content changes to existing installs ship as data migrations instead.

---

## 6. Permissions

```bash
cd ~/creativemark
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

Writable directories:

| Path | Why |
|---|---|
| `storage/framework/{cache,sessions,views}` | compiled views, file locks |
| `storage/logs` | daily log files |
| `storage/app/public` | every uploaded image |
| `storage/app/purifier` | **HTMLPurifier's serializer cache** — `config/purifier.php:24`. Missing or unwritable, every rich-text save fails |
| `bootstrap/cache` | config/route/view/event caches |

---

## 7. Scheduler cron

**Required.** One command is scheduled, and the privacy policy promises what it does.

hPanel → **Advanced → Cron Jobs** → add, running **every minute**:

```
* * * * * cd /home/uXXXXXXX/creativemark && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confirm the PHP binary path with `which php`; Hostinger often needs a versioned path such as `/opt/alt/php82/usr/bin/php`.

Currently scheduled (`php artisan schedule:list`):

| Command | When | Why it matters |
|---|---|---|
| `visitor-sessions:prune` | `10 3 * * *` (03:10 daily) | Deletes visitor sessions past the retention window (90 days by default, editable in Settings). **The privacy policy tells visitors this happens — without the cron, that sentence is untrue.** |

### Queue worker

**Not needed today.** No class implements `ShouldQueue` and nothing calls `dispatch()`. When mail lands (AUDIT §2.2), add:

```
* * * * * cd /home/uXXXXXXX/creativemark && /usr/bin/php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

(Shared hosting rarely allows a long-running daemon; `--stop-when-empty` with a per-minute cron is the usual substitute for `queue:work --daemon`.)

---

## 8. SSL / HTTPS

1. hPanel → **Security → SSL** → install the free Let's Encrypt certificate for the domain **and** `www`.
2. Enable **Force HTTPS**.
3. Confirm `APP_URL` starts with `https://` — `AppServiceProvider::forceHttpsInProduction()` only calls `URL::forceScheme('https')` when `APP_ENV=production` *and* `APP_URL` is https, so a stale `http://` here silently produces mixed-content URLs.
4. Set `SESSION_SECURE_COOKIE=true`.
5. HSTS is emitted automatically once the request is both secure and production (`SecurityHeaders::apply()`), `max-age=31536000; includeSubDomains`. It is deliberately not sent over plain http.

If Cloudflare is in front, set SSL mode to **Full (strict)** and set `TRUSTED_PROXIES` — otherwise every visitor shares one rate-limit bucket and `$request->secure()` reads false.

---

## 9. Post-deploy smoke test

Run in order. Everything here was verified working on the build machine, so a failure means an environment problem, not a code one.

### Serving and secrets

```bash
curl -sI https://YOURDOMAIN/ | head -1                    # 200
curl -sI https://YOURDOMAIN/.env                          # 403/404 — NOT 200
curl -sI https://YOURDOMAIN/storage/logs/laravel.log      # 403/404
curl -s  https://YOURDOMAIN/ | grep -c "Whoops\|stack trace"   # 0
```

### Security headers

```bash
curl -sI https://YOURDOMAIN/ | grep -iE "x-content-type|x-frame|referrer|permissions|strict-transport"
```

Expect `nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin`, a `Permissions-Policy`, and `Strict-Transport-Security`. Then confirm they survive an error:

```bash
curl -sI https://YOURDOMAIN/no-such-page | grep -iE "^HTTP|x-content-type"   # 404 + nosniff
curl -sI https://YOURDOMAIN/ | grep -i "x-powered-by"                       # expect NOTHING (§1)
```

### Bilingual routing and SEO

```bash
curl -s https://YOURDOMAIN/     | grep -o '<html[^>]*'      # lang="ar" dir="rtl"
curl -s https://YOURDOMAIN/en   | grep -o '<html[^>]*'      # lang="en" dir="ltr"
curl -s https://YOURDOMAIN/sitemap.xml | grep -c "<loc>"    # 13
curl -s https://YOURDOMAIN/robots.txt  | tail -2
curl -s https://YOURDOMAIN/cost-estimator | grep -o 'rel="canonical" href="[^"]*"'   # https://YOURDOMAIN/...
```

Canonical and OG URLs must be `https://YOURDOMAIN` — if they show `http://` or `localhost`, `APP_URL` is wrong and the config cache is stale.

### Error pages

```bash
curl -s https://YOURDOMAIN/no-such-page | grep -o "<title>[^<]*"     # branded Arabic 404
curl -s https://YOURDOMAIN/en/no-such-page | grep -o "<title>[^<]*"  # branded English 404
```

### The four features, in a browser

| # | Check |
|---|---|
| 1 | **Sign in** at `/login` with `ADMIN_EMAIL`/`ADMIN_PASSWORD`. Dashboard loads. `/register` is 404 (registration is removed). |
| 2 | **Settings** → save contact details → they appear in the public footer and on `/contact`. |
| 3 | **Estimator** → price and activate an item → `/cost-estimator` shows a calculator instead of "pricing in preparation" → tick two items → the total updates without a page load → submit → a lead of type `estimate` appears with the itemised breakdown. |
| 4 | **Booking** → open a weekday in Booking availability → `/book` offers days and times → book one → confirmation shows a `CM-YYYY-XXXXXX` reference → the booking appears in the dashboard and on the agenda **at the same clock time you picked** (Riyadh). Try the same slot again: it must be refused. |
| 5 | **Orders** → create an order → the tracking code is shown **once**, 64 hex characters → copy it → reload the page: the code is gone → `/track` with the reference + code opens that order → a wrong code gives the generic "not found" message. |
| 6 | **Consent** → the cookie banner appears → Decline → reload: no banner, no visitor-session row. Accept → a visitor session is recorded. With a tracking ID configured, confirm no pixel request fires before consent. |
| 7 | **Uploads** → upload an image in Media library → it renders (this is what `storage:link` fixes). |

### Scheduler

```bash
php artisan schedule:list                       # visitor-sessions:prune at 03:10
php artisan visitor-sessions:prune --dry-run    # reports a count, deletes nothing
```

Re-check after 24 h that the cron actually fired (`storage/logs/`).

### Timezone

```bash
php artisan tinker --execute="echo config('booking.timezone').' | '.now()->toDateTimeString();"
```

App time is **UTC**; booking times are rendered in `Asia/Riyadh` (+03:00, no DST). A booking made for 10:00 stores `07:00:00` UTC — that is correct, not a bug.

---

## 10. Rollback

```bash
php artisan down
# restore the previous file tree and the pre-deploy database dump
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
php artisan up
```

Take a database dump before every deploy:

```bash
mysqldump -u USER -p DATABASE > ~/backups/creativemark-$(date +%F-%H%M).sql
```

Every migration in this project has a working `down()` and is verified through two full `migrate → rollback → migrate → rollback` cycles (`tests/Feature/MigrationCycleTest.php`), so `php artisan migrate:rollback --step=N` is safe — but a dump is faster and does not risk data.

---

## 11. Ongoing

| Task | Cadence |
|---|---|
| Database dump | Daily (hPanel automatic backups + your own before each deploy) |
| `storage/logs/` review | Weekly — `LOG_LEVEL=error` means anything present is real |
| `composer outdated` / `npm audit` | Monthly |
| Confirm the prune ran | Monthly — it is a privacy commitment |
| Rotate `ADMIN_PASSWORD` | Per your policy; change it from the profile screen, not the seeder |
| Revoke a leaked order tracking code | Dashboard → the order → "Issue a new code". The old one stops working immediately; only the hash is stored, so it can never be recovered. |
