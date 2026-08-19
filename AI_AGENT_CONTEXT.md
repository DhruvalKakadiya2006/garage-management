# Garage Management App — Persistent Context

Paste this whole file into a new AI agent session before giving it any task, or drop it in the file path your agent auto-loads:

- **Claude Code:** `CLAUDE.md` at the project root
- **Cursor:** `.cursor/rules/project.mdc` (or legacy `.cursorrules`)
- **Windsurf:** `.windsurfrules`
- **GitHub Copilot:** `.github/copilot-instructions.md`
- **Kiro (if you come back to it):** `.kiro/steering/project-rules.md`
- **Anything else:** paste it as the first message of a new session, or as a system prompt if the tool supports one.

This is the single source of truth for how this project works. If a new request conflicts with anything below, the agent should stop and ask before proceeding — not silently override a decision that was made deliberately.

---

## 1. What this project is

A single-location workshop management system for Dhruval's EV scooter service center (Kakadiya EV Hub, Surat). Used by garage staff on a local Windows PC via browser — not a hosted/cloud SaaS. Single location only.

## 2. Fixed tech stack

- Backend: PHP 8.3+, Laravel 13, Eloquent ORM
- Database: SQLite (single file, `database/database.sqlite`)
- **Frontend: Blade + Tailwind CSS + Alpine.js — no React/Vue/Inertia.** (A full React + Inertia + shadcn/ui migration was scoped and then deliberately reverted — the team chose to keep this stack and instead recreate shadcn's visual language directly in Tailwind, per §4. Don't revisit the framework question again without being explicitly asked.)
- Auth: Laravel Breeze (Blade stack) — **email verification is disabled entirely**, not just unused (see §8).
- Build tool: Vite (`npm run dev` while developing, `npm run build` before testing — a stale build has already caused an "invisible button" bug once; don't let it happen again).
- PDF generation: DomPDF.
- No Docker, no external DB server, no cloud services, no CDN-hosted JS/CSS (this app must work fully offline — fonts, icons, charts, everything bundled locally via npm/Vite or Composer).

## 3. UI / code conventions

- Resource controllers (`php artisan make:model X -mcr`), routes via `Route::resource()` inside the `auth` middleware group (no `verified` — see §8).
- Reusable Blade components already exist and must be used, not reinvented per page: `<x-button>` (primary/secondary/danger/ghost variants), `<x-badge>`, `<x-input>`, `<x-select>`, `<x-card>`, and a shared data-table pattern (now multi-select checkbox based — see §4). Restyle in place per §4's tokens, don't rebuild from scratch.
- All money fields: `decimal(10,2)`, never float.
- Server-side validation always (`$request->validate()` or Form Requests).
- Every delete requires a confirmation dialog (bulk deletes get a count-aware confirmation — see §4).
- Proper Eloquent relationships, never manual joins.
- Timezone: `Asia/Kolkata` (via `APP_TIMEZONE` env var), not UTC.
- **Any module presented under a combined/tabbed parent page** (e.g. Spare Parts and Labour Master living under the single "Inventory" nav item) **must always redirect back to that parent page** (with the correct tab preserved via a query parameter) on create/update/delete/cancel — never to an orphaned standalone index route left over from before the modules were combined. This exact bug happened once (Inventory) — check for it whenever combining modules under a shared parent page in the future.

## 4. Design system — shadcn visual language, Tailwind + Alpine implementation

The goal is to look like shadcn/ui without adopting React — same neutral color system, border radius, spacing, and component visual patterns, powered by plain Tailwind classes with Alpine.js handling any interactivity a Radix primitive would otherwise provide (dropdowns, dialogs, tabs, popovers).

- **Font (body/UI):** Inter, self-hosted via `@fontsource/inter` — no Google Fonts or any other font CDN.
- **Brand wordmark:** no logo image in the sidebar header. Instead, plain text **"GMS"**, self-hosted **Montserrat, Black 900 Italic weight** (via `@fontsource/montserrat`, the 900-italic subset specifically — this is a separate font used only for this wordmark, not a site-wide font change), rendered in solid black (not the primary/accent color).
- **Color system:** shadcn's semantic CSS variable approach (`--background`, `--foreground`, `--primary`, `--primary-foreground`, `--border`, `--muted`, `--muted-foreground`, `--radius`, etc.), defined in `resources/css/app.css` and wired into `tailwind.config.js` the way shadcn's own generated config does (`hsl(var(--primary))` pattern), so components read `bg-primary text-primary-foreground` etc. instead of hardcoded shade names.
- **Primary color: Indigo-500, `#6366f1`** (`--primary: 238 84% 67%` in HSL — recompute and double check this conversion before trusting it blindly). This replaced the original `#145C9E` blue — don't revert to the old blue. Use Tailwind's own built-in `indigo` palette (`indigo-50`–`indigo-900`) for anywhere a specific shade is needed, rather than a hand-rolled custom scale — `indigo-500` is already exactly this brand color natively in Tailwind, no need to regenerate a tonal scale by hand.
- **Border radius:** `--radius: 0.5rem` (shadcn's default) — apply consistently via `rounded-lg`/`rounded-md` mapped to this variable.
- **Visual style:** shadcn's actual look — subtle single-pixel borders (`border-border`) doing most of the visual separation work, restrained shadow use (shadows for floating elements like dropdowns/dialogs, not every card), generous whitespace, muted secondary text (`text-muted-foreground`).
- **Icons:** Heroicons only, via `blade-ui-kit/blade-heroicons` (`<x-heroicon-o-{name}/>` outline by default, solid only for active/selected states). Never emoji.
- **Text size:** `text-sm` (14px) everywhere, including tables/tab content — there is no reduced-density (12px) tier; that was tried and reverted. Don't reintroduce a smaller size for data-table content.
- **Spacing in button groups/action bars:** every horizontal group of adjacent buttons (page-header action rows, contextual action bars, form Cancel/Save pairs) needs a minimum `gap-2` (0.5rem) between buttons — buttons must never sit flush against each other with zero margin. This was a recurring visual glitch across multiple pages (e.g. the Customer detail page's header row) — treat "buttons touching with no gap" as a bug wherever spotted, not just on the pages explicitly reported.
- **Navigation:** collapsible left sidebar, not a top navbar. Icon + label per module, expand/collapse state persisted via Alpine + localStorage (UI state only — not application data).
  - **Sidebar footer layout:** the collapse/expand toggle sits in its own row **above** a horizontal divider line; the user identity block (avatar + name + email + dropdown) sits **below** that divider, in its own row beneath it. The divider separates the toggle from the identity block — it does not sit above both of them as one combined unit.
  - The collapse toggle icon itself is small (compact icon size, not a large bordered square button) — a subtle, minor control, not a prominent one.
- **List/table row selection: multi-select via checkboxes.** Every data-table has a header "select all" checkbox (indeterminate state when some-but-not-all rows are selected) and a per-row checkbox. A "N selected" indicator plus a "Clear selection" control appears once anything is checked. The contextual action bar above the table adapts to selection count:
  - **0 selected:** no contextual action bar, or page-level actions only (e.g. "+ Add").
  - **Exactly 1 selected:** both single-record actions (Edit, and record-specific actions like Customers' Create Job Card / Add Vehicle / Statement / Receive Payment) and any bulk actions are shown.
  - **2+ selected:** single-record-only actions disappear entirely (never shown disabled); only true bulk actions remain (currently Delete).
  - Bulk delete requires a count-aware confirmation and must handle partial failure gracefully (some selected records may be protected by a `restrict` foreign key) — report what was deleted vs. skipped and why, never fail the whole batch silently.
- **Shared multi-select table pattern (BUILT — Phase 4 foundation, DONE — Phase 5 applies it to every module).** The shared building blocks live in `resources/views/components/` (`table-select-all`, `table-checkbox`, `table-selection-bar`, `bulk-delete-confirm`) plus the `tableSelection` Alpine component in `resources/js/app.js`. To adopt it in a module: wrap the table container in `x-data="tableSelection({ bulkEndpoint: '{{ route('x.bulk-delete') }}' })"` (or merge via `{ ...tableSelection({...}), ...moduleList() }`), put `<x-table-select-all />` in the first `<th>` and `<x-table-checkbox :id="..."/>` in each row's first `<td>`, and place `<x-table-selection-bar>` (with `single`/`bulk` slots for that module's actions) above the table. Selection is an array of ID strings in Alpine state (may span pages); the header checkbox reflects the current page only. **Phase 5 status: ALL 8 modules converted** (Customers, Vehicle Catalog [brands + per-brand models], Job Cards, Counter Invoices, Payments [Void wording], Spare Parts, Labour Master, Employees) — do not add a radio/single-select back to any of them.
  - **Two+ independent selection scopes on one page:** give each scope a unique `modalName` and `rowAttr` — e.g. Vehicle Catalog has `{ rowAttr: 'data-brand-id', modalName: 'bulk-delete-confirm-brands' }` for brands and per-expanded-brand `{ rowAttr: 'data-model-id', modalName: 'bulk-delete-confirm-models-<brandId>' }` for models (`<x-table-checkbox attr="...">` matches the scope's `rowAttr`). Inventory hosts two scopes (Spare Parts + Labour) as sibling `tableSelection(...)` wrappers inside the tab container; switching tabs keeps `?tab=` in the URL (the inventory `setTab` rewrites the query string) so a post-delete reload lands on the right tab.
  - **Backend:** each module gets ONE route `POST /{resource}/bulk-delete` (`ids[]`) using the `BulkDeletesRecords` controller trait, which delegates to `App\Services\BulkDeleteService`. The trait exposes `bulkDestroy()`; the controller MUST implement `protected function bulkDeleteModel(): string` (a trait property named `$bulkDeleteModel` causes a fatal "same property" conflict — the trait uses an abstract method instead) and MAY override `bulkDeleteDeleter(): ?callable` for modules whose plain `delete()` is insufficient. The service attempts each delete individually and catches any `\Throwable` per record (restrict-FK violations and missing ids count as skipped), never aborting the batch. Modules needing a custom deleter because a child FK is `restrict` and rows must be deleted via Eloquent first (so stock returns): **Job Cards** (`job_card_parts.job_card_id`) and **Counter Invoices** (`counter_invoice_items.counter_invoice_id`) — delete item rows one-by-one inside a `DB::transaction`, then the parent; the model `deleting` boot events clean up payments, labours/complaints cascade. Shared JSON response shape (identical for every module): `{ deleted_count, skipped_count, deleted_ids, skipped_ids, message }`; the frontend reports "X deleted, Y skipped (linked to existing records)".
  - **Validation failures on bulk-delete** return 422 JSON directly from `bulkDestroy()` (try/catch `ValidationException`); the app's global exception handler renders JSON only for `api/*` routes, so don't rely on it for the web bulk-delete endpoints.
- **Layouts should use available space fully and responsively** — avoid cramped fixed-width containers with excess empty margin on wider screens; tables and content panels should expand to fill their container, with sensible responsive behavior down to mobile widths.
- **Dashboard only** uses a bento-style grid (varied tile sizes). Every other page uses regular stacked/table layouts.
- **PDF exports** (invoices, estimates, statements, reports) use Open Sans or Noto Sans, self-hosted as actual font files registered with DomPDF (not the `@fontsource` CSS approach used for the web UI — DomPDF renders server-side and needs real font files registered through its own font mechanism). One-time local asset, not a CDN dependency.

## 5. Modules — current structure

- **Dashboard** — stats + bento grid. "Create Job Card" is NOT a Dashboard quick action (see §6) — don't re-add it there.
- **Customers** (combined with Vehicles in the UI, but two separate DB tables — `Customer hasMany Vehicles`). One customer, many vehicles. The only entry point for creating a Job Card (see §6).
- **Job Cards** — the core workflow, see §7 for the full flow. No separate Estimate table (see §6). No "New Job Card" button on this module's own pages — creation only starts from Customers.
- **Counter Invoice** — parts-only direct sale, no Job Card required, customer optional.
- **Payments** — polymorphic (`payable_id`/`payable_type`), attaches to Job Card or Counter Invoice. Partial payments supported, editable/voidable. Status is always derived (`amount_paid` / `outstanding` / `payment_status_label` accessors) — **there is no manual `payment_status` column anywhere**, never re-add one.
- **Reports** — read-only, date-range filterable (default: current month).
- **Employees** — technicians/staff list, functionally stable.
- **Inventory** — combined UI (single nav item, tabs) for Spare Parts and Labour Master. Two separate DB tables/CRUD backends underneath — only the navigation/UI is unified. Spare Parts shows MRP, fixed Labor Charge, and computed Total. Both sub-modules' create/edit/delete must redirect back to the correct Inventory tab (see §3).
- **Vehicle Catalog** — Brand/Model master data (separate from a customer's actual owned vehicle record, which references a catalog entry via `vehicle_model_id`).
- **Customer Statement** — a per-customer running-balance ledger (Job Cards + Counter Invoices + Payments merged chronologically), viewable on-screen and as a PDF.
- **Import/Export — CSV only.** Excel (.xlsx) was scoped but never actually implemented and has been removed entirely (the UI had dead buttons pointing at an unimplemented path) — don't reintroduce Excel support unless explicitly asked again.
- **Suppliers — removed entirely.** No table, no nav link, no references anywhere. `spare_parts.supplier_id` has been dropped.

## 6. Locked business decisions — do not re-litigate these

- **No email verification, at all.** Fully removed (no `MustVerifyEmail`, no `verified` middleware, no verification routes/views). Simple trusted local tool, single-shop.
- **GST calculation order: Discount → GST on the discounted amount → Round-off.** Each line's taxable amount is reduced proportionally by the document's discount ratio before tax is calculated on it (using that line's own `gst_rate`). After summing tax, the grand total is rounded to the nearest whole rupee, with the adjustment stored and displayed as its own visible "Round Off: ₹X" line — never silently absorbed. Discount can never exceed the subtotal (validated/capped server-side).
- **Stock rules:** deducted only when Job Card parts or Counter Invoice items are saved; returned correctly on delete/edit (via Eloquent model events firing — cascade deletes must use `onDelete('restrict')`, not `cascade`, on any FK from a document/line-item back to a master record, so deleting a master never silently destroys billing history — controllers catch the resulting exception and show a friendly error instead). `spare_parts.current_stock_qty` has a DB-level `CHECK >= 0` constraint as a final safety net.
- **Complaints (on Job Cards) are description-only** — no charge field, no Complaint Master lookup table.
- **Customer Type is just two values:** `regular` (labor charges apply) and `counter` (parts-only, no labor).
- **Vehicle registration is optional** — a "NON-RTO" toggle marks a vehicle as not having an official registration, making `registration_no` nullable for that record instead of required.
- **Part MRP is locked server-side** — always pulled fresh from the `spare_parts` master record when a line is saved, never trusted from client input.
- **Labor charge stays editable per line, and is per-unit** (multiplied by quantity). Different treatment from MRP.
- **Money columns are never `null`.** Every money column has a DB-level `->default(0)` and is never nullable — always `0` for "not yet billed," never `NULL`.
- **"Create Job Card" is only ever reachable from the Customers module** (the Customers index contextual action bar after selecting exactly one customer, and the Customer show page) — never add this button anywhere else. Job cards always start from a customer context.

## 7. Job Card workflow (current)

Multi-step, not a single create form:

1. **From the Customers list**, staff selects a customer and clicks "Create Job Card." If the customer has multiple vehicles, they choose which one; if only one, it's auto-selected.
2. **Basic Job Card creation** — a lightweight form: Job Date, Odometer Reading, Job Type (default: Paid), Promised Delivery Date/Time, Complaints (free-text only), Remarks. No technician, no parts, no labour, no totals yet. Saves with status `received`.
3. **Assign & Bill** — a separate screen where staff assigns a Technician and Supervisor, and adds Parts (MRP locked, labor charge editable/per-unit) and Labour line items. Totals (with GST per §6) are calculated here.
4. **Generate Invoice or Generate Estimate** — a POST action (never GET) generates a PDF from the same Job Card data; there is no separate Estimate database record. Once any payment exists against the job card, further edits to billed items are blocked.

## 8. Access & security — locked decisions

- **Single-user app.** Only one trusted admin (Dhruval) ever uses this. No roles, no permissions system. Declined twice already — don't re-suggest.
- **Public self-registration is closed regardless** — there's only ever one account.
- **IDs passed from PHP into Alpine.js `x-data` must be cast consistently** (always `(string)` on both sides being compared) — a real bug here caused blank pre-filled fields once (`$request->integer()` produced a PHP int, `@json()` serialized it as a JS number, and a strict `===` comparison against string IDs elsewhere silently failed).

## 9. Performance baseline (daily-use config)

`.env` should have `APP_DEBUG=false`, `SESSION_DRIVER=file`, `CACHE_STORE=file` for daily use — routing session/cache through SQLite on every request is a confirmed major slowness cause. SQLite should run in WAL journal mode (`'journal_mode' => 'wal'` in `config/database.php`). Icon resolution should be cached via its Artisan command for daily use. Never assume a slow page is a code bug before checking these basics first.

## 10. When in doubt

Ask a specific question and wait for the answer rather than assuming. Incorrect assumptions here mean redoing schema/migrations or UI patterns, which is expensive once real data/screens exist.