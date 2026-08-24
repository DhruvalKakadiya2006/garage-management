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
  - **Minimum bulk action set for every module: Delete and Export (selected rows to CSV).** Individual modules may have additional bulk actions where genuinely sensible (decide per module, don't force one everywhere) — but Delete + Export is the baseline every data-table supports once 2+ rows are selected.
- **PDF design (Invoice, Estimate, Customer Statement, Analytics exports):** black and white only — no color anywhere in a PDF, ever (this is a deliberate print-friendly, toner-saving, purely functional choice, distinct from the web UI's indigo theme, which never appears in a PDF). Minimal and functional: clear sectioned layout (company details header, Bill To block, vehicle/job details block, itemized line items — Parts and Labour as visually distinct groups — a GST summary table when tax applies, totals, and a declaration/signature line), no decorative elements, no unnecessary borders/shading beyond what's needed for table readability. Company details printed on every PDF come from the Company Profile module, never hardcoded in a template. Reference real-world Indian GST tax invoice conventions for structure (HSN-wise summary table, amount-in-words line, declaration text) without copying any specific existing template verbatim — build the actual markup fresh.
- **Date display format: `d/m/Y` (DD/MM/YYYY) everywhere a date is shown to the user** — e.g. `23/08/2026`, not `23 Aug 2026` or `08/23/2026`. Exception: native `type="date"` HTML inputs always use the browser's own ISO (`Y-m-d`) value internally regardless of display locale — that's normal HTML behavior, not something to "fix," and the OS/browser renders its own locale-appropriate picker UI on top of it automatically. Month-year-only labels (e.g. a report heading like "August 2026") are not affected by this rule.
- **Every reusable form input/select/textarea is fully hand-styled already** (via `<x-input>`, `<x-select>`, `searchable-combobox`, etc.) — never add a CSS-reset/forms plugin (e.g. `@tailwindcss/forms`) on top of this, it causes a doubled border/ring effect. If one is ever found registered, remove it rather than trying to override it further.
- **Every data-table row uses compact vertical padding** (`py-2`, not `py-4`) — matches standard ERP/shadcn table density, not the more spacious padding used elsewhere (cards, form fields can stay more generous; this specifically applies to table `<td>`/`<th>` cells). **Employees and Inventory are the reference implementation** for this — match their exact row height everywhere else, don't re-derive it independently per module. Every cell (including the checkbox column) is vertically centered (`align-middle`), never top-aligned — browser default `<td>` alignment is top, which must be overridden explicitly.
- **Pagination shows 10 records per page by default, with a visible, editable page-size control** (e.g. a small select: 10/25/50/100) on every paginated table — never hardcode a page size with no way to change it.
- **Every data-table gets a Delete action** (individual row via the multi-select action bar per the row-selection rules above — not a separate per-row column), consistently showing a friendly, specific error when a record can't be deleted due to a `restrict` foreign key (e.g. "Cannot delete — linked to 3 job cards"), never a raw exception.
- **Search:** every list-heavy module (Job Cards, Customers, Counter Invoice, Payments, Inventory) has a text search box plus its existing filters (status/technician/date-range/etc. where already built) — search should hit the fields that make sense for that entity (e.g. Job Cards: job card no., customer name, registration no.; Customers: name, mobile; Counter Invoice: invoice no., customer/walk-in name). The Dashboard additionally has a single master search box that queries across multiple entity types at once (Customers, Vehicles, Job Cards, Counter Invoices) and shows grouped results linking to each record's page.
- **Job Card numbering format: `JC-YYMMDD-NNN`** (e.g. `JC-260823-023` — year-month-day of creation, then a zero-padded sequence number), replacing the previous format. Applies going forward; don't attempt to renumber existing historical records.
- **Sidebar navigation order (top to bottom):** Dashboard, Job Cards, Customers, Vehicle Catalog, Counter Invoice, Payments, Inventory, Analytics, Employees, Company Profile.
- **Dashboard only** uses a bento-style grid (varied tile sizes). Every other page uses regular stacked/table layouts.
- **PDF exports** (invoices, estimates, statements, reports) use Open Sans or Noto Sans, self-hosted as actual font files registered with DomPDF (not the `@fontsource` CSS approach used for the web UI — DomPDF renders server-side and needs real font files registered through its own font mechanism). One-time local asset, not a CDN dependency.

## 5. Modules — current structure

- **Dashboard** — stats + bento grid. "Create Job Card" is NOT a Dashboard quick action (see §6) — don't re-add it there.
- **Customers** (combined with Vehicles in the UI, but two separate DB tables — `Customer hasMany Vehicles`). One customer, many vehicles. The only entry point for creating a Job Card (see §6).
- **Job Cards** — the core workflow, see §7 for the full flow. No separate Estimate table (see §6). No "New Job Card" button on this module's own pages — creation only starts from Customers.
- **Counter Invoice** — parts-only direct sale, no Job Card required, customer optional.
- **Payments** — polymorphic (`payable_id`/`payable_type`), attaches to Job Card or Counter Invoice. Partial payments supported, editable/voidable. Status is always derived (`amount_paid` / `outstanding` / `payment_status_label` accessors) — **there is no manual `payment_status` column anywhere**, never re-add one.
- **Analytics** (renamed/expanded from the earlier "Reports" module — same underlying data, one unified section, not two separate things) — read-only, date-range filterable (default: current month), chart-heavy (revenue, outstanding, top customers, technician performance, inventory, expenses vs revenue, and further KPIs added over time).
- **Employees** — technicians/staff list, functionally stable.
- **Inventory** — combined UI (single nav item, tabs) for Spare Parts and Labour Master. Two separate DB tables/CRUD backends underneath — only the navigation/UI is unified. Spare Parts shows MRP, fixed Labor Charge, and computed Total. Both sub-modules' create/edit/delete must redirect back to the correct Inventory tab (see §3). Spare Parts has its own embedded CSV import/export (not a separate central module — see below).
- **Vehicle Catalog** — Brand/Model master data (separate from a customer's actual owned vehicle record, which references a catalog entry via `vehicle_model_id`).
- **Customer Statement** — a per-customer running-balance ledger (Job Cards + Counter Invoices + Payments merged chronologically), viewable on-screen and as a PDF.
- **Company Profile** — a single-record settings page (not a list/CRUD module — one row, edit-only, no create/delete): company name, address, city/state/pincode, phone, email, GSTIN, optional bank details (name/account/IFSC), Terms & Conditions text, and an optional logo. This is the source of the company details printed on every PDF (see §4's PDF design rules).
- **Import/Export — CSV only, embedded per module, no central/separate Import/Export section.** Each module that supports it (Spare Parts already does; extend the same embedded pattern to Customers and Job Cards, which used to live under a now-removed central Import/Export page) has its own Import/Export actions on its own page — a full "Export all" action, a CSV import action, and (new) a "Export selected" bulk action tied to the multi-select pattern below.
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