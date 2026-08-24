# Garage Management System

<p align="center">
<img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="360" alt="Laravel Logo">
</p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Laravel Version"></a>
<img src="https://img.shields.io/badge/PHP-8.3-777BB4" alt="PHP 8.3">
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

Single-tenant garage / workshop management system for an EV service centre (Surat). Manages customers, vehicles, job cards, counter (walk-in) invoices, payments, spare-parts inventory, labour, employees and analytics. Built on **Laravel 13.23 / PHP 8.3 / SQLite / Blade + Alpine.js / Tailwind CSS 4**.

> **Status:** Under active development. `274` feature tests, `1500` assertions — see [Testing](#testing).

---

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Roles & permissions](#roles--permissions)
- [Domain rules](#domain-rules)
- [Import / Export](#import--export)
- [MCP (Model Context Protocol)](#mcp-model-context-protocol)
- [Performance / daily use](#performance--daily-use)
- [Development](#development)
- [Testing](#testing)
- [Deployment](#deployment)
- [Project structure](#project-structure)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features

| Area | What it does |
|---|---|
| **Dashboard** | Today's jobs by status, month revenue, low-stock count, outstanding dues (clamped per-document), 5 recent job cards — `DashboardController` |
| **Customers** | CRUD + searchable combobox (`customers/search`, `customers/{customer}/vehicles`), 360° page (`customers/{id}?tab=vehicles|job_cards|financial`) with billed/paid/outstanding aggregates, statement PDF (`customers/{id}/statement?from_date&to_date`) |
| **Vehicles** | Per-customer vehicles + Vehicle Catalog (brands → models) master data |
| **Job Cards** | `JC-0001` sequence, `received → in_progress → waiting_for_parts → ready_for_delivery → delivered / cancelled`, assign technician + parts/labour + GST/discount, stock validation, invoice & estimate PDFs (`barryvdh/laravel-dompdf`) |
| **Counter Invoices** | `CI-0001` walk-in or customer-linked parts-only sales, per-line discounts, GST split, stock deduction |
| **Payments** | Polymorphic `payable` → `JobCard | CounterInvoice`, `cash/upi/card/bank_transfer/cheque`, outstanding guard + `lockForUpdate` + post-write invariant |
| **Inventory** | Spare parts (MRP + labour charge, `BRA-001` per-category codes, stock/qty, reorder level, low-stock tab) + Labour masters (rate, taxable, GST) |
| **Employees** | Technicians / supervisors / helpers / front-desk / admin, specialization, active flag |
| **Analytics** | `analytics/*` — revenue (daily + 6-month trend), job-cards by status/type, inventory usage & stock value, payments by mode, customers ranked by paid + revenue split by `regular/counter/walk_in` |
| **Import / Export** | CSV preview → confirm workflow (`import-export/*`) per entity (customers, job cards, spare parts, brands), bulk delete/export, error-report CSV |
| **Company Profile** | Single-row settings (name, address, GSTIN, bank, terms, logo `image|max:2MB` on `local` disk) at `company-profile` |
| **Auth** | Breeze login, `throttle:5,1` on `POST login/forgot-password/reset-password`, `is_admin` gate |

---

## Tech stack

- **Backend:** `laravel/framework ^13.8`, `php ^8.3`, `barryvdh/laravel-dompdf ^3.1`, `laravel/mcp ^0.9.4`, `laravel/breeze ^2.4`, `laravel/boost ^2.5` (dev)
- **Frontend:** Vite 8 + `laravel-vite-plugin ^3.1`, `@tailwindcss/vite ^4`, `tailwindcss ^3.1`, `alpinejs ^3.4.2`, `chart.js ^4.5.1`, `@fontsource/inter`
- **DB:** SQLite (WAL, `busy_timeout 5000`, FK enforcement) — default, no external DB required
- **Tooling:** Pint 1.30, PHPUnit 12.5, Pail, PAO

---

## Requirements

- PHP `^8.3` with `sqlite3`, `gd` (dompdf), `mbstring`, `openssl`
- Composer 2
- Node 20+ & npm
- No MySQL/Postgres required for local dev (SQLite file `database/database.sqlite`)

---

## Quick start

```bash
# 1. Install deps (also copies .env, generates key, migrates, builds)
composer setup
# — or step by step:
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
npm install
npm run build

# 2. Seed a login (factory default is_admin=true)
php artisan tinker --execute "App\Models\User::factory()->create(['email'=>'admin@example.com','password'=>bcrypt('password')]);"

# 3. Run
composer run dev
# → server  http://127.0.0.1:8000  (also queue + pail + vite, see composer.json:scripts.dev)
# — or individually:
php artisan serve
npm run dev
```

Default login: `admin@example.com / password` (if seeded as above).

> `composer setup` is defined in `composer.json:scripts.setup` — it runs `composer install`, `.env` copy, `key:generate`, `migrate --force`, `npm install`, `npm run build`.

---

## Configuration

All env keys are in `.env.example`. Notable:

| Key | Default | Notes |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | WAL + FK enabled in `config/database.php` (`journal_mode=wal`, `foreign_keys=1`, `busy_timeout=5000`) |
| `SESSION_DRIVER` / `CACHE_STORE` | `file` | Do **not** use `database` on SQLite — causes major slowness (see `AI_AGENT_CONTEXT.md §9` if present) |
| `QUEUE_CONNECTION` | `database` | `composer run dev` also runs `queue:listen --timeout=0` |
| `APP_TIMEZONE` | `Asia/Kolkata` | Statements, `from_date/to_date` parsing use this |
| `COMPANY_*` | `Kakadiya EV Hub / Surat / …` | Header on PDFs — `config/garage.php:company` reads these |
| `PAGINATION_PER_PAGE` | `15` | `config/garage.php:pagination.per_page` — main list pages (`job-cards`, `customers`, `payments`, etc.). Tab paginates stay fixed (`job_page 10`, `invoice_page 8`) |
| `FILESYSTEM_DISK` | `local` | `storage/app/private` — company logo + import uploads/sessions/error-reports |
| `MAIL_MAILER` | `log` | Dev default |

Import limits (`config/import-export.php`): `max_file_size_bytes 2MB`, `allowed_extensions csv`, `allowed_mime_types` includes `text/plain`/`vnd.ms-excel` (browsers), `max_rows 5000`.

---

## Architecture

### Routing

All app routes live in `routes/web.php` under `auth` middleware. `routes/ai.php` registers the MCP server (`Mcp::web('weather', WeatherServer::class)`), `routes/auth.php` is Breeze (login throttled). PDF routes are `POST` (they mutate `invoice_generated_at`/`estimate_generated_at`).

### Models (16)

`User`, `Customer`, `Vehicle`, `VehicleBrand`, `VehicleModel`, `JobCard` (SoftDeletes), `JobCardComplaint`, `JobCardPart`, `JobCardLabour`, `CounterInvoice`, `CounterInvoiceItem`, `Payment` (SoftDeletes, `payable` morph), `SparePart`, `LabourMaster`, `Employee`, `CompanyProfile` — see `app/Models/*`.

Key relations:
- `Customer → vehicles / jobCards / counterInvoices`
- `JobCard → customer / vehicle.vehicleModel.vehicleBrand / assignedTechnician / supervisor / complaints / parts.sparePart / labours.labourMaster+technician / payments`
- `Payment → payable (morphTo JobCard|CounterInvoice)`, accessors `document_type/document_no/customer_label`

### Services

- `OutstandingBalanceService::total(): float` — single canonical clamped per-document outstanding (`SUM(CASE WHEN grand_total - COALESCE(paid,0) >0 …)`) shared by dashboard + `analytics.index`.
- `GstCalculator` — per-line `gst_rate` split into `cgst/sgst`, `discount_percent`+`discount_amount` (`DiscountWithinSubtotal` rule), `round_off`.
- `PartPriceResolver` — MRP always from `spare_parts` master, never client-submitted `mrp`.
- `StockValidator` — reserves already-held qty (`validate($items, $parent, $key)`) inside transactions.
- `BulkDeleteService` — per-record try/catch, `skipped` on FK `restrict`, logs `Bulk delete skipped a record`.

### Controllers

Use `BulkDeletesRecords` + `BulkExportsRecords` traits; destructive `destroy()`/`bulkDestroy()`/`Payment::update` gate `admin` (`AppServiceProvider:32` `Gate::define('admin', fn(User $u)=>(bool)$u->is_admin)`). Views hide controls with `@can('admin')`. `JobCardController` document numbering is `MAX(CAST(substr(job_card_no,…)))` + skip-loop + unique index backstop + `QueryException` catch → friendly retry message; same for `CounterInvoice` + per-prefix `SparePart::nextPartCode()`.

### Migrations & indexes

Secondary indexes for FK/filter columns live in `database/migrations/2026_08_23_164637_add_performance_indexes_to_business_tables.php` (`job_cards.customer_id/vehicle_id/assigned_technician_id/supervisor_id/status/job_date`, `counter_invoices.customer_id/invoice_date`, `payments.payment_date`, `job_card_parts`/`labours`/`counter_invoice_items` FKs — morph composite already via `morphs()`).

---

## Roles & permissions

- `users.is_admin` (`boolean`, factory default `true`, real DB default `false`). Project rules in `.ai/rules/controllers.md`.
- **Admin:** `destroy()` on all masters/customers/vehicles, `payments.update/destroy`, every `bulkDestroy` (via `BulkDeletesRecords::bulkDestroy:57`), `import-export/confirm/{token}`, `company-profile` update.
- **Staff:** create + edit everywhere, `import-export/preview` upload + `showPreview`, exports, payments `store`.

Tests use `User::factory()->create()` (admin) and `User::factory()->staff()->create()` for `403` cases.

---

## Domain rules

- **Job card billing:** `grand_total IS NULL` ⇔ `is_billed=false` ⇔ `payment_status_label=unbilled`. Totals computed only in `updateAssign()` (parts+labours → `GstCalculator`). A billed card's `customer_id`/`vehicle_id` are frozen (`JobCardController:433`).
- **Outstanding:** `outstanding = max(0, round(grand_total,2) - round(amount_paid,2))` per doc, clamped — one doc's overpayment never offsets another (`CustomerController:103` `outstanding`, `OutstandingBalanceService`).
- **Soft deletes:** Only `JobCard` + `Payment`. Soft-deleting a card keeps parts/stock/payments; `forceDeleted` cascades `forceDelete()` payments but is blocked by `job_card_parts` `restrict` FK until parts removed one-by-one (stock returned via `JobCardPart::deleting`). `CounterInvoice` hard-deletes, its `deleting` hook `forceDelete()`s payments.
- **Numbering:** `JC-0001` / `CI-0001` via `MAX(CAST(substr(no)))` with skip-loop; `SparePart` codes are per-category `XXX-001` (`BRA`/`BAT`/…), not global lexicographic max.
- **Payments:** `store()` `lockForUpdate()` + post-write `SUM(amount) > grand_total` invariant; `update()` guards orphaned payable (`$payable` null when doc deleted).

---

## Import / Export

Foundation in `app/ImportExport/*` (`ImportRegistry`, `ImportService`, `ExportService`, `AbstractImportDefinition` + 9 `Definitions/*`, `Readers/FileFormatDetector`). Flow:

`POST import-export/preview` (validate `entity/mode/file max:2MB/csv`) → `Storage::putFileAs(import-export/uploads/{uuid}.csv)` → `ImportService::storeSession({entity_key,mode,original_filename,upload_path,user_id})` → `302` → `GET import-export/preview/{token}` (dry-run `previewForToken`, refresh-safe) → `POST import-export/confirm/{token}` (admin, re-runs pipeline from stored upload inside transaction, deletes upload+session on success, writes `error_reports/{token}.csv` if issues) → `GET import-export/error-report/{token}` (owner-or-admin check via `assertTokenOwnership`) → `GET import-export/export|template?entity=`.

Exports are streaming CSV via `ExportService` (no import). Bulk delete/export: `POST {resource}/bulk-delete|bulk-export {ids: int[]}` → `{deleted_count,skipped_count,deleted_ids,skipped_ids,message}`.

Orphan cleanup: `php artisan imports:purge --hours=24` deletes `uploads/sessions/error_reports` older than cutoff; scheduled daily in `routes/console.php` (`Schedule::command('imports:purge --hours=24')->daily()`).

---

## MCP (Model Context Protocol)

Configured via `laravel/mcp ^0.9.4`. Server `app/Mcp/Servers/WeatherServer.php` (`#[Name('Garage Weather Server')] #[Version('1.0.0')]`) is registered at `routes/ai.php:6` as `Mcp::web('weather', WeatherServer::class)` → HTTP at `/mcp/weather` (use `php artisan mcp:inspector mcp/weather`). It is intentionally empty (`$tools/$resources/$prompts = []`) — add tools via `php artisan make:mcp-tool` and register in `WeatherServer::$tools`.

Pitfalls: do not run `php artisan mcp:start` manually (blocks), do not use HTTPS locally with Node clients, `routes/ai.php` is auto-registered (do not add to `bootstrap/app.php`).

---

## Performance / daily use

- **SQLite WAL** is enabled (`'journal_mode' => 'wal'` in `config/database.php`). No action needed; applied on every connection. Significantly improves write concurrency over default `DELETE` journal mode.
- **Icons cache** — run before daily use and after any icon change:

  ```bash
  php artisan icons:cache
  php artisan icons:clear   # to remove manifest
  ```

- **Purge imports** — if `schedule:work` is not running, run manually:

  ```bash
  php artisan imports:purge --hours=24
  ```

- Keep `SESSION_DRIVER=file` / `CACHE_STORE=file` (not `database`) on SQLite — routing them through SQLite causes major slowness.

---

## Development

```bash
composer run dev   # serve + queue:listen + pail + vite (concurrently, see composer.json)
# or
php artisan serve
npm run dev        # Vite HMR
```

**Code style:** Pint — `vendor/bin/pint --dirty` (run after every PHP edit per `.ai/rules`). Config in `pint.json` (Laravel preset).

**Icons:** `blade-ui-kit/blade-heroicons` + `blade-ui-kit/blade-icons`. After adding `<x-heroicon-* />`, re-run `php artisan icons:cache`.

**MCP:** Add tools/resources/prompts via `php artisan make:mcp-*`, register in `WeatherServer`, test with `php artisan mcp:inspector mcp/weather`.

---

## Testing

```bash
php artisan test --compact                 # all 274 tests
php artisan test --compact --filter=test_analytics_index_shows_outstanding_kpis
php artisan test --compact tests/Feature/PaymentOverpayGuardTest.php
```

- Framework: PHPUnit 12.5 (`phpunit/phpunit:*` — pin before prod), `RefreshDatabase` (SQLite `:memory:`), `CACHE_STORE=array` in `phpunit.xml`.
- Factories: `UserFactory` defaults `is_admin=true` (admin), `->staff()` for 403 tests. Helpers `SeedsVehicleCatalog::makeCatalogVehicle($customer,$reg,$brand,$model)` + per-test `makeCustomer/makeJobCard/makeCounterInvoice/makePayment`.
- Coverage: customers 360 + statement, payments overpay guards (`PaymentOverpayGuardTest`), analytics N+1 tripwire, `PerformanceIndexesTest`, `SparePartStoreTest`, job-card numbering collision (SQLite trigger `force_jc_collision`), import/export preview/confirm + admin gate, dashboard clamped dues, rounding residue `0.14+0.44` vs `0.58`.

---

## Deployment

- **Laravel Cloud** is the recommended path (per `AGENTS.md`).
- Env: set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, generate `APP_KEY`, keep `DB_CONNECTION=sqlite` (ensure `database/database.sqlite` is writable and outside public root) or switch to MySQL/Postgres (analytics `strftime('%Y-%m',…)` + `DATE(…)` are SQLite-specific — adapt `AnalyticsController` if switching).
- Build: `composer install --no-dev --optimize-autoloader && npm run build && php artisan migrate --force && php artisan icons:cache && php artisan config:cache`.
- Queue: `queue:work --queue=default --tries=1` (or `horizon`). Scheduler: `php artisan schedule:work` (for `imports:purge` daily).
- Storage: `storage/app/private` must be persistent; `php artisan storage:link` if using `public` disk for logos (currently `local`).

---

## Project structure

```
app/
  Http/Controllers/      16 controllers (JobCard/CounterInvoice/Customer/Payment/SparePart/VehicleBrand/Model/Employee/LabourMaster/Inventory/Dashboard/Analytics/ImportExport/CompanyProfile + Concerns/BulkDeletesRecords+ExportsRecords)
  Http/Requests/         StoreJobCardRequest / StoreCustomerRequest (+ Auth/*)
  ImportExport/          Registry/Service/ExportService + Definitions/* + Readers + Support
  Models/                16 models (see Architecture)
  Services/              OutstandingBalanceService / GstCalculator / PartPriceResolver / StockValidator / BulkDeleteService
  Mcp/Servers/           WeatherServer
  Console/Commands/      PurgeExpiredImports
resources/views/         89 Blade files (layouts/app+navigation, components/data-table+table-checkbox+*, customers/job_cards/counter_invoices/payments/spare_parts/labour_masters/employees/vehicle_catalog/dashboard/analytics/pdf)
config/                  garage.php (option_lists, company, pagination) / import-export.php (entities, limits, storage)
database/migrations/     … + 2026_08_23_164637_add_performance_indexes_to_business_tables.php
routes/                  web.php (132 lines, all auth) / ai.php (Mcp::web) / auth.php (Breeze, throttled) / console.php
tests/Feature/           25+ suites (Analytics/Dashboard/JobCardCreateEdit/CounterInvoiceCreateEdit/Payment*/BulkDelete*/ImportExport/Inventory/…)
```

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `Vite manifest not found` | `npm run build` or `composer run dev` (runs `npm run dev`) |
| `SQLSTATE … index … already exists` on migrate | `morphs('payable')` already creates `payments_payable_type_payable_id_index` — performance migration skips it (only adds `payment_date`) |
| `Unsupported import file type` on `.txt` | `allowed_extensions` is `['csv']` (`config/import-export.php:94`) |
| `403` on `import-export/confirm/{token}` as staff | Expected — `authorize('admin')` (`CustomerImportExportTest::test_confirm_is_admin_only`) |
| `Call to member function payments() on null` on payment edit | Payable hard-deleted (`Payment::update` now guards `if($payable && …)`) — payment stays editable, outstanding guard skipped |
| `LogicException … temporaryUrl` on company logo | `local` disk does not support `temporaryUrl` — `CompanyProfileController` now falls back to `url()` |
| Outstanding dues seem too low/high | Clamped per-document `max(0, grand_total - paid)` — overpayment on one doc never offsets another; check `OutstandingBalanceService` |
| Analytics `strftime` errors on MySQL | `AnalyticsController` uses SQLite `strftime('%Y-%m',…)` / `DATE(…)` — replace with `DATE_FORMAT` / `whereMonth` if migrating DB |

---

## License

MIT — see `LICENSE` (Laravel framework MIT).
