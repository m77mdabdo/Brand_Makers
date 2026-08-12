# Creative Mark — صنّاع العلامات التجارية

Laravel 12 application for Creative Mark, a branding studio: visual
identity, brand strategy, packages, a cost estimator, consultation
bookings and a client portal.

Bilingual throughout (Arabic primary, English), with every human-readable
field stored as a translatable JSON column via
`spatie/laravel-translatable`.

> An independent project with its own repository, database and `.env`. It
> shares a data-layer design with Bawabat Taasees but is not connected to
> it in any way.

---

## Current state

**The data layer is complete.** Schema, models, factories, seeders and
tests are in place; no UI has been built yet.

| | |
|---|---|
| Tables | 35 |
| Models | 26, all with factories |
| Tests | 251 (667 assertions), green on SQLite and MySQL |

See **[docs/data-layer.md](docs/data-layer.md)** for the full reference.

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# set DB_DATABASE, and ADMIN_EMAIL / ADMIN_PASSWORD outside local
php artisan migrate --seed
```

`migrate --seed` produces real content only — no sample testimonials,
leads, bookings or orders. For a populated dashboard in development:

```bash
php artisan db:seed --class=DemoDataSeeder
```

---

## Tests

```bash
php artisan test          # SQLite, in-memory
./vendor/bin/pint         # code style
```

To run the suite against MySQL as well:

```bash
DB_CONNECTION=mysql DB_DATABASE=brand_makers_testing php artisan test
```

---

## Design notes worth knowing before you change anything

- **Guarded columns.** `is_admin`, `is_handled`, `is_system`,
  booking/order `status`, order `progress`, `reference` and
  `tracking_code_hash` are all absent from `$fillable`, each for a stated
  reason. Set them through the models' explicit methods.
- **Tracking codes.** Only the SHA-256 of a 32-byte CSPRNG value is stored.
  The plaintext is returned once and is then unrecoverable. Order lookup
  accepts the code, never the reference.
- **IPs are truncated in a model mutator**, so no code path can store a raw
  address.
- **Indexes live inside `CREATE TABLE`.** No migration adds one by `ALTER`
  — that is what keeps `down()` a plain drop and rollbacks clean. A test
  enforces it.
- **Production and demo seed data are strictly separate**, and a test scans
  every readable column to keep them that way.

---

## Stack

Laravel 12 · PHP 8.2+ · MySQL (SQLite for tests) ·
`spatie/laravel-translatable` · Pint · PHPUnit
