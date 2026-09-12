# Garage Management App — AI Agent Context

**Single source of truth for AI agents working on this project.**

Paste this into new agent sessions or save as:
- GitHub Copilot: `.github/copilot-instructions.md`
- Any other: paste as first message or system prompt

**If a new request conflicts with anything below, STOP and ask — don't silently override a deliberate decision.**

---

## 1. What This Project Is

Single-location EV scooter workshop management system for Kakadiya EV Hub, Surat. Used by garage staff on local Windows PC via browser — not cloud/SaaS. **Single location only** — never add multi-tenant or `branch_id` fields without explicit request.

## 2. Fixed Tech Stack

- **Backend:** PHP 8.3+, Laravel 13, Eloquent ORM
- **Database:** SQLite (single file: `database/database.sqlite`)
- **Frontend:** Blade + Tailwind CSS + Alpine.js (NO React/Vue/Inertia — this was deliberately decided)
- **Auth:** Laravel Breeze (Blade stack) — email verification fully disabled
- **Build:** Vite (`npm run dev` for development, `npm run build` before testing)
- **PDF:** DomPDF with self-hosted fonts
- **Offline-first:** No Docker, no external DB, no cloud services, no CDN dependencies

## 3. Code Conventions

### Laravel Patterns
- Resource controllers: `php artisan make:model X -mcr`
- Routes via `Route::resource()` inside `auth` middleware (no `verified`)
- Server-side validation always (`$request->validate()` or Form Requests)
- Proper Eloquent relationships, never manual joins
- Timezone: `Asia/Kolkata` (via `APP_TIMEZONE`)

### Data Rules
- All money fields: `decimal(10,2)`, never float
- Money columns never nullable: `->default(0)`
- Delete actions require confirmation dialog
- Master records use `onDelete('restrict')` FK constraints
- Controllers catch `QueryException` and show friendly "Cannot delete — linked to X records" messages

### Component Reuse
Reusable Blade components **must be used**, not reinvented:
- `<x-button>` (primary/secondary/danger/ghost variants)
- `<x-badge>`
- `<x-input>`
- `<x-select>`
- `<x-card>`
- Shared data-table pattern with multi-select checkboxes

### Module Navigation
When modules are combined under a single nav item (e.g., Spare Parts + Labour Master under "Inventory"), **always redirect back to the parent page with correct tab** via query parameter. Never redirect to orphaned standalone routes.

## 4. Design System — Shadcn Visual Language (Tailwind + Alpine)

**Goal:** Look like shadcn/ui without React — same neutral colors, spacing, components via Tailwind + Alpine.

### Typography & Colors
- **Body font:** Inter (self-hosted via `@fontsource/inter`)
- **Wordmark:** "GMS" in Montserrat Black 900 Italic (self-hosted via `@fontsource/montserrat`)
- **Primary color:** Indigo-500 `#6366f1`
- **Color system:** Semantic CSS variables (`--background`, `--foreground`, `--primary`, `--border`, `--muted`, `--radius`, etc.) in `resources/css/app.css`
- Use `bg-primary text-primary-foreground` pattern, not hardcoded shades

### Visual Style
- **Border radius:** `--radius: 0.5rem` (shadcn default) via `rounded-lg`/`rounded-md`
- **Borders:** Subtle 1px borders (`border-border`) for separation
- **Shadows:** Restrained — only for floating elements (dropdowns/dialogs), not every card
- **Spacing:** Generous whitespace, muted secondary text (`text-muted-foreground`)
- **Icons:** Heroicons only via `blade-heroicons` (`<x-heroicon-o-{name}/>`)
- **Text size:** `text-sm` (14px) everywhere, including tables
- **Button spacing:** Minimum `gap-2` between adjacent buttons (never flush)

### Layout Components
- **Navigation:** Collapsible left sidebar (icon + label), persisted state via Alpine + localStorage
  - Sidebar footer: collapse toggle above divider, user identity below divider
- **List tables:** Multi-select via checkboxes with contextual action bar
  - 0 selected: page-level actions only (e.g., "+ Add")
  - 1 selected: single-record actions + bulk actions
  - 2+ selected: only bulk actions (Delete + Export minimum)
- **Table density:** Compact `py-2` padding, `align-middle` all cells
- **Pagination:** 10 per page default with editable page-size control (10/25/50/100)
- **Date format:** `d/m/Y` (DD/MM/YYYY) for display, e.g., `23/08/2026`

### PDF Design
- **Black and white only** — no color (print-friendly, toner-saving)
- Minimal functional layout: company header, Bill To, vehicle/job details, itemized lines (Parts & Labour separate), GST summary, totals, declaration/signature
- Company details from Company Profile module, never hardcoded
- Fonts: Open Sans or Noto Sans (self-hosted font files registered with DomPDF)

## 5. Business Logic — Locked Decisions

### Stock Tracking (DISABLED 2026-09-05)
- `spare_parts.current_stock_qty` field remains in DB but **automatic increment/decrement is disabled**
- Reason: Suppliers module not yet integrated
- Invoice/Job Card **line-item quantities still work** for billing and transactions
- To re-enable: Uncomment stock logic in `JobCardPart.php`, `CounterInvoiceItem.php`, and `StockValidator` calls in controllers

### GST Calculation
1. Apply discount to subtotal first
2. Calculate GST on discounted amount (per-line using each item's `gst_rate`)
3. Round grand total to nearest rupee (adjustment shown as visible "Round Off: ₹X" line)
4. Discount cannot exceed subtotal (validated server-side)

### Customer & Vehicle
- **Customer Type:** `regular` (labor charges apply) or `counter` (parts-only, no labor)
- **Vehicle registration optional:** "NON-RTO" toggle makes `registration_no` nullable

### Pricing & Labor
- **Part MRP locked server-side:** Always from `spare_parts` master, never client input
- **Labor charge editable per-line** and per-unit (multiplied by quantity)
- Two distinct labor types:
  1. Fixed fitting/labor charge on each Spare Part (auto-fills when part added)
  2. Labour Master — standalone services (not tied to specific parts)

### Job Card Workflow
Multi-step process:
1. **Create from Customer** — Select customer → choose vehicle → basic form (Job Date, Odometer, Job Type, Promised Delivery, Complaints, Remarks) → saves with status `received`
2. **Assign & Bill** — Separate screen to assign Technician/Supervisor, add Parts & Labour, calculate totals
3. **Generate Invoice/Estimate** — POST action generates PDF (no separate Estimate table)

**"Create Job Card" button only appears in Customers module** (index action bar after selecting 1 customer, or customer show page) — never on Dashboard or Job Cards list.

### Counter Invoices
- Parts-only direct sale, no Job Card required
- Customer optional (can be walk-in/anonymous)
- Never linked to Job Cards

### Payments
- Polymorphic relation (`payable_id`/`payable_type`) → attaches to Job Card or Counter Invoice
- Partial payments supported
- Payment status always **derived** from `grand_total - SUM(payments)`
- **No manual `payment_status` column** — never re-add one

### Master Data Protection
- **Cannot delete** Customer, Vehicle, Spare Part, or Labour Master if linked to documents
- Foreign keys use `onDelete('restrict')`, never `cascade`
- Controllers catch exception and show friendly message

### Other Rules
- **No email verification** — fully removed (no `MustVerifyEmail`, no `verified` middleware)
- **Complaints (Job Cards):** Description-only, no charge field, no Complaint Master table
- **Job Card numbering:** `JC-YYMMDD-NNN` format (e.g., `JC-260823-023`)
- **No CDN dependencies:** All JS/CSS libraries bundled via npm + Vite (offline-capable)

## 6. Modules — Current Structure

### Navigation Order (Sidebar, top to bottom)
1. Dashboard
2. Job Cards
3. Customers
4. Vehicle Catalog
5. Counter Invoice
6. Payments
7. Inventory
8. Analytics
9. Employees
10. Company Profile

### Module Details

**Dashboard**
- Stats + bento-style grid (varied tile sizes)
- Master search box (queries Customers, Vehicles, Job Cards, Counter Invoices)

**Customers** (+ Vehicles)
- Combined UI but two DB tables: `Customer hasMany Vehicles`
- Only entry point for creating Job Cards
- Includes Customer Statement (running-balance ledger with Jobs/Invoices/Payments)

**Job Cards**
- Core workflow (see §5)
- No "New Job Card" button on this module — creation starts from Customers only

**Counter Invoice**
- Parts-only direct sale, customer optional

**Payments**
- Polymorphic attachments, partial payments, editable/voidable
- Status always derived from actual payment records

**Analytics** (formerly "Reports")
- Read-only, chart-heavy KPIs
- Date-range filterable (default: current month)
- Includes: revenue, outstanding, top customers, technician performance, inventory usage

**Employees**
- Technicians/staff list

**Inventory**
- Combined UI (single nav item, tabs) for Spare Parts and Labour Master
- Two separate DB tables/CRUD backends
- Spare Parts shows: MRP, Labor Charge, Total (computed)
- Embedded CSV import/export (not separate module)

**Vehicle Catalog**
- Brand/Model master data (referenced by customer vehicles via `vehicle_model_id`)

**Company Profile**
- Single-record settings page (edit-only, no create/delete)
- Company details: name, address, phone, email, GSTIN, bank details, T&C, logo
- Source of company info printed on all PDFs

**Removed Modules**
- **Suppliers:** Fully removed, no table, no references
- **Separate Import/Export section:** Now embedded per-module

## 7. Security & Access

- **Single-user app** — one trusted admin (Dhruval) only
- **No roles/permissions system** — declined multiple times
- **Public registration closed** — one account ever
- **IDs in Alpine.js:** Cast to string consistently on both sides (`(string)` in PHP, handle in JS)

## 8. Performance Notes

Daily-use `.env` config:
```
APP_DEBUG=false
SESSION_DRIVER=file
CACHE_STORE=file
```

SQLite config (`config/database.php`):
```php
'journal_mode' => 'wal'
```

Cache icon resolution via Artisan command for daily use.

**Check these basics before assuming code bugs cause slowness.**

## 9. Testing & Build

- Run `npm run build` or keep `npm run dev` running after Blade/CSS changes
- Stale builds cause "invisible button" bugs
- Tests: `php artisan test --compact`
- Format: `vendor/bin/pint --format agent`

## 10. Laravel Boost Guidelines (MCP Integration)

This project uses Laravel Boost MCP server with specialized tools:

### Tools (Prefer over manual commands)
- `database-query`: Run read-only queries instead of tinker
- `database-schema`: Inspect table structure before migrations/models
- `get-absolute-url`: Resolve scheme/domain/port for project URLs
- `browser-logs`: Read browser logs/errors (recent only)

### Documentation Search (IMPORTANT)
- Use `search-docs` before changes depending on Laravel ecosystem APIs
- Skip for copy-only edits where package docs are irrelevant
- Pass `packages` array to scope results when relevant packages known
- Use multiple broad topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`
- Syntax:
  - Words for auto-stemmed AND: `rate limit` matches both "rate" AND "limit"
  - Quoted phrases for exact position: `"infinite scroll"`
  - Combine for mixed queries: `middleware "rate limit"`
  - Multiple queries for OR logic: `queries=["authentication", "middleware"]`

### Project Rules
- Area-grouped rules in `.ai/rules` when directory exists
- Read `.ai/rules/index.md` (maps file globs to rule files)
- Read every rule file matching your work scope
- Use `grep -rin 'keyword' .ai/rules` to catch additional matches
- Use `record-rule` to record durable decisions (never native memory/notes)

### Artisan
- Run directly via CLI: `php artisan route:list`
- Use `php artisan list` to discover commands
- Use `php artisan [command] --help` for parameters
- Inspect routes: `php artisan route:list --method=GET --name=users --path=api --except-vendor`
- Read config: `php artisan config:show app.name` or read `config/` files

### Tinker
- Use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
- Double quotes for PHP strings inside: `User::where("active", true)->count()`
- Prefer tests with factories over creating models in tinker

### PHP Standards
- Always use curly braces for control structures (even single-line)
- PHP 8 constructor property promotion: `public function __construct(public GitHub $github) {}`
- Explicit return types and type hints: `function isAccessible(User $user, ?string $path = null): bool`
- PHPDoc blocks over inline comments
- Array shape type definitions in PHPDoc

### Testing Requirements
- **Every change must be programmatically tested**
- Write new test or update existing, then run to verify
- Use `php artisan test --compact --filter=testName` for specific tests
- Use `php artisan make:test --phpunit {name}` for new tests
- All tests must be PHPUnit (convert any Pest tests)
- Tests should cover happy paths, failure paths, edge cases
- Never remove tests without approval

### Laravel Best Practices
- Use `php artisan make:` commands for new files (migrations, controllers, models)
- Pass `--no-interaction` to all Artisan commands
- For APIs: Use Eloquent API Resources and versioning
- Use `route()` function with named routes for links
- Use factories for test data: Check for custom states before manual setup
- Run `vendor/bin/pint --dirty --format agent` after PHP file changes

## 11. When in Doubt

**Ask a specific question and wait for the answer.** Don't assume the "obvious" interpretation. Incorrect assumptions require expensive schema/migration/UI pattern redos once real data exists.

---

**Last Updated:** 2026-09-11 (Latest: Cleaned up temporary files, synced documentation)
