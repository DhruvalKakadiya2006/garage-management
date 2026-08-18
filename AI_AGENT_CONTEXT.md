# Garage Management App — Persistent Context

Paste this whole file into a new AI agent session before giving it any task, or keep it as the agent's project-level context file.

This is the single source of truth for project constraints and deliberate product decisions. Do not silently override these decisions. If a requested change conflicts with a locked decision below, call out the conflict before changing schema, architecture, or business logic.

---

## 1. Product and operating environment

- This is a single-location workshop/garage management system for an EV scooter service center in Surat, Gujarat.
- It is primarily used by trusted garage staff on a local Windows PC through a browser.
- It is not being designed as a public SaaS or multi-tenant product.
- The core goal is operational speed, clarity, reliability, and low-friction daily use.
- The application should feel like a practical business admin tool rather than a marketing/SaaS dashboard.

## 2. Fixed technology stack

- Backend: PHP 8.3+, Laravel 13, Eloquent ORM.
- Database: SQLite (`database/database.sqlite`) for the current deployment and development.
- Frontend: Blade + Tailwind CSS + Alpine.js.
- Do NOT rewrite the frontend to React, Vue, Inertia, Livewire, or another SPA stack as a performance solution.
- Authentication: Laravel Breeze Blade stack.
- Email verification is disabled and removed entirely.
- Build tool: Vite.
- Development build: `npm run dev`.
- Production/test build: `npm run build` before final UI verification.
- PDF generation: DomPDF.
- Icons: `blade-ui-kit/blade-heroicons`.
- No CDN-hosted JS/CSS/fonts/icons. The app must work offline.
- Valkey is intentionally NOT a current application dependency. Keep the architecture compatible with future cache/queue adoption, but do not add Valkey merely to solve current page latency.

### Database direction

- SQLite is intentionally retained for this phase.
- Do not migrate to MySQL/MariaDB as part of a performance/UI refactor unless explicitly requested.
- Optimize the current SQLite/Eloquent implementation first: queries, indexes, eager loading, aggregates, pagination, and data-loading strategy.
- A future production move to MySQL/MariaDB may be considered if concurrency or database scale requires it, but that is outside the current deadline-focused scope.

---

## 3. Current priority order

The project is close to feature-ready but currently suffers from perceived slowness, UI inconsistency/glitches, and missing high-value customer workflows.

Always prioritize work in this order unless explicitly told otherwise:

1. Performance and correctness of existing flows.
2. UI consistency and removal of glitches.
3. High-value operational workflows.
4. Secondary features and reporting polish.
5. Infrastructure/caching improvements only when justified by measurement.

Do not use a large rewrite to solve a small implementation problem.

---

## 4. Performance rules — critical

The application must feel immediate for routine admin actions. A user currently experiences roughly 2–3 second delays on some button clicks and page/tab navigation. Treat this as a real product defect.

### Performance principles

- Do not load entire database tables merely to populate a dropdown when a searchable/paginated selector can be used.
- Avoid `Model::all()` and unbounded `get()` on large/master tables in user-facing pages unless the table is provably tiny and static.
- Use database-side `COUNT`, `SUM`, `EXISTS`, scoped aggregates, and constrained eager loading instead of loading large related collections into PHP and calculating totals there.
- Paginate list pages.
- Search/filter in SQL, not in PHP collections.
- Eager-load only relationships actually rendered on the page.
- Avoid N+1 queries.
- Add appropriate database indexes for frequently searched/filtered/foreign-key columns.
- Prefer `withCount`, `withSum`, constrained relationship queries, and query scopes where appropriate.
- Do not cache blindly. First remove unnecessary work, then measure, then cache only genuinely expensive/repeated reads.
- Do not make the UI wait for non-critical work that can be deferred.
- Avoid unnecessary Alpine watchers, effects, repeated DOM work, and large inline data payloads.
- Keep page transitions and buttons free of artificial delays.
- Do not introduce heavy client-side state management.

### Known areas to inspect first

- Customer index and customer-related aggregates.
- Job Card create/assign screens that currently need customers, vehicles, spare parts, labour, technicians, or supervisors.
- Counter Invoice create/edit screens with large master-data lists.
- Any page that loads all related records just to display counts, balances, or select options.

### Measurement requirement

Before and after meaningful performance work, inspect query count/time and identify the actual expensive operations. Do not claim a performance improvement without evidence from code inspection, query logging, timing, or repeatable testing.

---

## 5. UI/UX direction

### Overall principle

Use the usability philosophy of the WordPress admin as the reference point, but do not clone WordPress literally.

Target experience:

- Dense but readable.
- Predictable.
- Fast to scan.
- Table-first for administration.
- Clear primary actions.
- Filters/search directly above records.
- Contextual actions close to the selected record.
- Minimal animation.
- Minimal decorative UI.
- Strong information hierarchy.
- Consistent forms/tables/actions across modules.

Do NOT turn the application into a WordPress clone, and do NOT replace the existing stack merely to achieve this look.

### Admin shell

- Persistent collapsible left sidebar.
- Icon + label per module.
- Sidebar state may persist in Alpine/localStorage.
- Top bar may contain page context/search/user controls.
- Do not replace the sidebar with a generic top navigation.

### Lists/tables

- List pages should use a consistent admin-table pattern.
- Table/list content uses `text-xs` (12px) for dense operational data.
- Sidebar/top navigation remains `text-sm` (14px).
- Use available horizontal space; avoid unnecessarily narrow fixed-width content areas.
- Maintain responsive behavior for smaller screens.
- Avoid per-row action-column clutter where the contextual row-selection pattern applies.
- Single row selection is the standard for Customers, Job Cards, and similar list pages.
- Selecting a row reveals a contextual action bar above the table.

### Components

Use existing reusable Blade components instead of creating one-off equivalents:

- `<x-button>`
- `<x-badge>`
- `<x-input>`
- `<x-select>`
- `<x-card>`
- shared data-table patterns

Keep spacing, typography, focus states, button hierarchy, badges, and empty states consistent.

### Visual design

- Font: Inter, self-hosted via `@fontsource/inter`.
- Primary color: `#145C9E`, represented by the generated `primary-50` through `primary-900` Tailwind scale.
- Use `primary-*`, not arbitrary `blue-*` classes.
- Material-inspired, minimalist visual language.
- Soft elevation, restrained rounding, clear hover/focus states.
- No flashy gradients, glassmorphism, neumorphism, or decorative motion.
- Icons are Heroicons. Never use emoji as interface icons.
- Dashboard may use the existing bento-style layout. Do not redesign the dashboard during unrelated work.

---

## 6. Core product information architecture

The most important user workflow is:

**Customer → Vehicle → Job Card → Parts/Labour → Invoice/Estimate → Payment → Statement**

The Customer record should become the central operational view, rather than treating every module as a disconnected silo.

### Desired Customer 360 experience

A customer detail page should ultimately provide:

- customer profile/contact information
- vehicles owned by the customer
- job card history
- invoice/financial history
- total paid
- total outstanding
- clear next actions
- one-click statement generation
- one-click new Job Card
- one-click add Vehicle
- one-click receive Payment where appropriate

Do not create duplicate business data merely to support this page. Use the existing relationships and derived financial values.

### One-click statement

The intended statement is a customer-level financial/history document, not a new accounting ledger table unless a future explicit requirement demands one.

A statement should be able to show, as applicable:

- customer information
- date range
- vehicles
- job cards
- invoices
- payments
- outstanding amount
- useful opening/closing balance information where the existing data model can support it cleanly

Keep the implementation derived from existing transactional data.

---

## 7. Modules — current structure

- **Dashboard** — stats + bento grid. Considered stable; do not restructure unless explicitly requested.
- **Customers** — UI combines customer/vehicle management, while `customers` and `vehicles` remain separate tables. One customer has many vehicles.
- **Job Cards** — core service workflow, see §9.
- **Counter Invoice** — parts-only direct sale; customer optional; no Job Card required.
- **Payments** — polymorphic payment records attached to Job Card or Counter Invoice; partial payments supported; payment status is derived from amounts.
- **Reports** — read-only, date-range filterable, default current month.
- **Employees** — technicians/staff master list.
- **Inventory** — combined navigation/UI for Spare Parts and Labour Master; separate underlying tables/CRUD.
- **Vehicle Catalog** — brand/model master data.
- **Suppliers** — removed entirely. Do not recreate it.

---

## 8. Locked business decisions

Do not re-litigate these unless explicitly instructed.

- No email verification — fully removed.
- No public self-registration. `/register` must not be reachable. There is only one trusted admin account.
- No roles/permissions/RBAC/policies. Do not introduce authorization architecture.
- Money fields use `decimal(10,2)`; never float.
- Timezone is `Asia/Kolkata` via `APP_TIMEZONE`.
- GST calculation order: Discount → GST on discounted amount → Round-off.
- Discount is allocated proportionally across taxable lines.
- Each line uses its own GST rate.
- Grand total rounds to nearest whole rupee; round-off is visible and stored as its own adjustment, never silently absorbed.
- Stock is deducted when Job Card parts or Counter Invoice items are saved and correctly returned on delete/edit.
- Foreign keys from documents/line-items to master records use restrictive deletion behavior rather than destructive cascades.
- Controllers show friendly errors when a master cannot be deleted because billing history depends on it.
- Job Card complaints are description-only. No complaint master table and no complaint charge field.
- Customer Type has exactly two values: `regular` and `counter`.
- `regular` allows labor charges; `counter` is parts-only/no labor.
- Vehicle registration is optional. `NON-RTO` means the vehicle has no official registration and `registration_no` may be null.
- Part MRP is always fetched fresh from the Spare Part master on save. Never trust a client-submitted MRP.
- Labor charge remains editable per line and is multiplied by quantity.

---

## 9. Job Card workflow

This is deliberately a multi-step flow.

1. From Customers, select a customer and click Create Job Card.
2. Select a vehicle if needed; auto-select when the customer has only one vehicle.
3. Basic Job Card creation is intentionally lightweight: job date, odometer, job type (default Paid), promised delivery date/time, complaint descriptions, remarks.
4. Initial save uses status `received`.
5. Assign & Bill is a separate action/screen where Technician/Supervisor are assigned and Parts/Labour are added.
6. Parts auto-fill MRP and fixed labor charge from the part master. MRP remains server-controlled. Labor remains editable per line and is per-unit.
7. Labour lines use Labour Master.
8. Totals use the locked GST/discount/round-off rules.
9. Generate Invoice or Generate Estimate from the same current Job Card data.
10. There is no separate Estimate database record. Estimate is a differently labeled/styled PDF generated from the Job Card state.
11. `invoice_generated_at` and `estimate_generated_at` are audit timestamps only.
12. Generating a PDF does not lock the Job Card.

---

## 10. Security/access model

- Single-user trusted admin only.
- No RBAC.
- No public registration.
- All application routes except login-related routes are authenticated.
- Keep server-side validation even though the app is used locally.
- Every delete action requires a JS `confirm()` dialog.

---

## 11. Alpine.js data-bound ID rule

IDs passed from PHP to Alpine must be explicitly cast consistently, preferably to strings on both sides of comparisons.

When passing IDs through `@json()` or into `x-data`, ensure the PHP value is `(string)` before comparison.

Do not rely on JavaScript loose equality to hide type mismatches. A previous real bug caused apparently blank pre-filled Job Card fields because PHP integers were compared with JS strings using strict equality.

This rule is especially important for cascading selects, prefilled query-string values, and customer/vehicle/job-card selectors.

---

## 12. PDF rules

- PDF exports use Open Sans, self-hosted as actual font files for DomPDF.
- No CDN fonts.
- Four TTFs (`OpenSans-Regular/Bold/Italic/BoldItalic.ttf`) live in `storage/fonts/` with source copies and license tracked.
- Generated DomPDF font caches are gitignored and self-healing.
- Shared PDF template: `resources/views/pdf/job-card.blade.php`.
- `JobCardController::generateDocument()` calls `Pdf::setBasePath(base_path())` before loading the view so relative storage font URLs resolve correctly.
- All PDF body text uses Open Sans.

---

## 13. Coding-agent operating rules

Before changing code:

1. Read the relevant controller, model, migration, route, Blade view, and JS used by the affected flow.
2. Identify existing reusable components before creating new ones.
3. Understand existing business logic before modifying it.
4. Check whether the change affects stock, payment totals, GST, PDF output, or foreign-key restrictions.
5. Prefer the smallest coherent change that solves the problem.

For performance work:

- Inspect query behavior rather than guessing.
- Do not replace one slow query with a large cache as the first response.
- Preserve SQLite compatibility.
- Add migrations only when there is a clear schema/index requirement.
- Avoid speculative abstraction.

For UI work:

- Preserve the established sidebar and component system.
- Make pages consistent instead of making each page individually fancy.
- Follow the WordPress-admin-style usability principles described above.
- Avoid introducing different table, filter, or form patterns unless there is a concrete workflow reason.

For feature work:

- Prefer customer-centric workflows.
- Reuse existing transactions and relationships.
- Do not duplicate financial facts across tables just to make reporting easier.
- Keep high-frequency tasks one or two clicks away where practical.

For testing:

- Run relevant Laravel tests.
- Run `php artisan migrate:fresh --seed` only when it is safe for the development database and the task requires it; never destroy real user data merely to test.
- Run `npm run build` after frontend changes.
- Check browser console and obvious UI errors.
- Verify important flows manually when possible.
- Do not report success if only syntax checks were performed.

---

## 14. Out of scope for the current stabilization phase

Do NOT introduce any of the following unless explicitly requested:

- React/Vue/Inertia/SPA rewrite
- Livewire migration
- MySQL/PostgreSQL migration
- Valkey/Redis as a mandatory dependency
- multi-tenant architecture
- roles/permissions/RBAC
- public registration
- cloud/CDN dependencies
- separate Estimate database model
- supplier module recreation
- large dashboard redesign
- unrelated feature expansion while performance bugs remain

---

## 15. Definition of success for the current phase

The app should feel like a fast local administrative tool:

- navigation and routine actions respond promptly
- list pages do not load unnecessary data
- forms do not preload entire master tables unnecessarily
- customer workflows are obvious and fast
- table/UI patterns are consistent
- no visible console errors or stale-build glitches
- business calculations remain correct
- PDF output remains correct
- existing data is preserved
- the next feature can be added without a frontend rewrite

