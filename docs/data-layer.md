# Creative Mark — Data Layer

Reference for the schema, models and seeders. Everything here is
implemented; nothing is aspirational.

> **This is an independent project.** It shares a data-layer design with
> Bawabat Taasees but has its own repository, its own database and its own
> `.env`. Nothing in this repo reads from or writes to that project.

---

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_DATABASE, and ADMIN_EMAIL / ADMIN_PASSWORD if not local
php artisan migrate --seed
```

`migrate --seed` gives you real content and nothing else. For a populated
dashboard in development:

```bash
php artisan db:seed --class=DemoDataSeeder
```

`DemoDataSeeder` prints each sample order's tracking code once, at seed
time. Those plaintexts exist nowhere else — see [Tracking codes](#tracking-codes).

---

## Tables (35)

### Framework (9)
`users` · `password_reset_tokens` · `sessions` · `cache` · `cache_locks` ·
`jobs` · `job_batches` · `failed_jobs` · `migrations`

### Content (13)
| Table | Notes |
|---|---|
| `pages` | translatable title/body/meta, unique slug |
| `page_sections` | JSON blob per section, unique `(page_id, key)`, cascades with its page |
| `services` | translatable, **soft-deletes**, unique slug |
| `countries` | translatable, unique slug |
| `faqs` | translatable |
| `testimonials` | translatable quote; name/title are not translated |
| `articles` | translatable, **soft-deletes**, unique slug |
| `comments` | moderated; `ip_address` is truncated by the model |
| `media` | `uploaded_by` nullOnDelete — files outlive staff accounts |
| `seo_meta` | polymorphic, **enforced morph map**, one record per owner |
| `settings` | JSON value: scalar, boolean, number or per-locale map |
| `tracking_settings` | pixel IDs, each pinned to its platform's published format |
| `menu_items` | self-referencing, `parent_id` nullOnDelete (reparents, never orphans) |

### CRM and analytics (5)
| Table | Notes |
|---|---|
| `leads` | **soft-deletes**; two campaign columns, see below |
| `conversion_events` | `occurred_at` NOT NULL with no default, deliberately |
| `campaigns` | unique `external_campaign_id` — the lead-resolution key |
| `lead_sources` | unique `key`; `source_platform` stores the key as a string |
| `visitor_sessions` | consent-gated journeys; `ip_prefix` only, never a raw address |

### The four new features (8)
| Table | Notes |
|---|---|
| `packages` | translatable, **soft-deletes**, unique slug, `features` JSON, nullable price |
| `estimator_items` | unique `key`, `price` NOT NULL, grouped for rendering |
| `bookings` | **soft-deletes**, `scheduled_at` datetime, guarded status |
| `booking_availability` | one row per weekday: hours, slot length, per-slot capacity |
| `booking_blackout_dates` | unique `date`, one-off closures |
| `orders` | **soft-deletes**, unique `reference` and `tracking_code_hash` |
| `order_events` | status timeline, cascades with its order |

---

## Models (26)

`Article` `Booking` `BookingAvailability` `BookingBlackoutDate` `Campaign`
`Comment` `ConversionEvent` `Country` `EstimatorItem` `Faq` `Lead`
`LeadSource` `Media` `MenuItem` `Order` `OrderEvent` `Package` `Page`
`PageSection` `SeoMeta` `Service` `Setting` `Testimonial` `TrackingSetting`
`User` `VisitorSession`

Every one uses `HasFactory` and has a factory exercised against the real
schema.

**Concerns**

| Trait | What it does |
|---|---|
| `HasSlug` | fills `slug` from a translatable field; uniqueness checked `withTrashed()` |
| `HasTrackingCode` | mints a CSPRNG code, stores only its digest, resolves by hash |
| `AnonymizesIp` | truncates any IP column on write, in the model |

---

## Security, enforced in the data layer

### Guarded columns

Never mass-assignable. Each entry is a concrete escalation, not a style
preference:

| Column | What mass-assignment would allow |
|---|---|
| `users.is_admin` | registering yourself as an administrator |
| `leads.is_handled` | a submission marking its own enquiry already dealt with, hiding it from the inbox |
| `lead_sources.is_system` · `menu_items.is_system` | a row claiming the status that protects it from deletion |
| `bookings.status` | an unauthenticated visitor confirming their own booking, or cancelling one to free a slot it still holds |
| `orders.status` · `orders.progress` | a client marking their own project complete |
| `orders.reference` | choosing a system-issued identifier |
| `orders.tracking_code_hash` | choosing a known code, then reading the order back with it |

Each is set through an explicit method — `markHandled()`,
`markConfirmed()`, `transitionTo()` — or by direct assignment.
`MassAssignmentGuardTest` asserts every one.

### Tracking codes

`orders.tracking_code_hash` holds the SHA-256 of a 32-byte
`random_bytes()` value. The plaintext is returned once from
`issueTrackingCode()` and is then unrecoverable — a database dump yields no
working portal links.

SHA-256 rather than bcrypt because the code carries a full 256 bits from a
CSPRNG: there is no dictionary to slow down, and a plain digest keeps
lookup to **one indexed equality read**. That is what makes the portal
enumeration-resistant — no scan, no per-row comparison, and no timing
difference between "no such order" and "wrong code".

`reference` is deliberately *not* a credential. It is short, quoted over
the phone and printed on invoices, so `findByTrackingCode()` refuses it.

```php
$order = Order::create([...]);
$code  = $order->trackingCode();          // shown once, never stored
Order::findByTrackingCode($code);         // the only supported lookup
Order::findByTrackingCode($order->reference); // always null
$order->issueTrackingCode();              // revokes a leaked link
```

### IP anonymisation

`IpAnonymizer` truncates IPv4 to /24 and IPv6 to /48. Truncation, not
hashing: the IPv4 space is 2^32, so any unsalted hash is brute-forceable
back to the original in seconds, and a salted one is useless for the thing
the column exists for.

It is wired in through the `AnonymizesIp` model mutator, so **no code path
can store a raw address by forgetting a step** — not a controller, not a
seeder, not a tinker session.

### Morph map

`Relation::enforceMorphMap()` pins `seo_meta` owners to short aliases
(`page`, `article`, `service`, `country`, `package`). Renaming or moving a
class never orphans its rows, and attaching SEO metadata to an unmapped
model throws immediately instead of writing a row that resolves to nothing.

### Soft-delete relations (BUG-2)

The rule, applied from the first commit:

- **`belongsTo` → soft-deleting parent uses `withTrashed()`.** The child is
  a historical record whose FK survives archival. Without it a lead shows a
  blank service, a revenue row shows no lead, and a converted session reads
  as unconverted.
- **`hasMany` stays scoped.** An archived lead must not inflate a service's
  enquiry count.

### Slug uniqueness (BUG-1)

The unique index on `slug` counts soft-deleted rows, but the global scope
hides them — so `HasSlug` checks `withTrashed()` on soft-deleting models,
and a plain query on those without (calling `withTrashed()` there throws).

### Rollback safety

Every index and column is declared **inside** its `CREATE TABLE`. No
migration adds an index by `ALTER`, so every `down()` is a plain drop and
the errno-1061 trap — a rollback stranding a duplicate index and dying on
the second cycle — is unreachable by construction.
`MigrationCycleTest` runs two full cycles and diffs the schema after each,
and separately fails any migration that reintroduces the pattern.

---

## Production vs demo

`DatabaseSeeder` is production. It creates **zero** testimonials, leads,
campaigns, conversions, bookings, orders, visitor sessions or blackout
dates.

`DemoDataSeeder` is never called by it. Every row it writes carries the
literal `(SAMPLE DATA)` marker in human-readable text, and
`DemoDataSeparationTest` scans every readable column to assert production
emits that string nowhere.

**Two production seeders ship a catalogue without figures**, on purpose:

- `PackageSeeder` sets `price = null` — "on request", which is exactly what
  the nullable column is for. `0.00` would mean *free*.
- `EstimatorItemSeeder` seeds its real line items **inactive** at zero.
  `price` is NOT NULL by design (an unpriced line cannot be summed), so
  `is_active = false` is the honest way to say "not priced yet".

The names and line items are real and belong in production; the prices are
a business decision nobody has made. A public estimator quoting invented
numbers is worse than an empty one. Neither seeder re-applies a price on
re-run, so a real figure entered in the dashboard survives.

`DemoEstimatorPricingSeeder` prices and activates the same rows for local
work, and skips anything already priced.

### Idempotency

All seeders are safe to re-run. Specifically, re-seeding never:

- overwrites a price an admin entered
- overwrites page content (`PageContentSeeder` is create-only — `db:seed`
  must never destroy prose someone refined in the dashboard)
- reopens booking hours an admin closed
- rotates an issued tracking code

---

## Tests

```bash
php artisan test                    # SQLite, in-memory
DB_CONNECTION=mysql DB_DATABASE=brand_makers_testing php artisan test
```

**251 tests, 667 assertions**, green on both engines.

| Suite | Covers |
|---|---|
| `ModelsTest` | relations, casts, scopes, translations, business rules |
| `FactoriesTest` | all 26 factories persist against the real schema |
| `MassAssignmentGuardTest` | every guarded column, as the escalation it prevents |
| `TrackingCodeTest` | entropy, non-recoverability, one-query lookup, revocation |
| `SoftDeleteRelationsTest` | BUG-2, both directions |
| `HasSlugTest` | BUG-1, including titles that slugify to nothing |
| `IpAnonymizerTest` | truncation, and that no path can store a raw address |
| `MigrationCycleTest` | two full up/down cycles, schema diff, no-ALTER-index guard |
| `SchemaIndexesTest` | every hot index and uniqueness constraint |
| `MorphMapTest` | aliases stored, unmapped models rejected |
| `SeedersTest` | baseline counts, idempotency, no-overwrite guarantees |
| `DemoDataSeparationTest` | the production/demo split, marker scan included |
