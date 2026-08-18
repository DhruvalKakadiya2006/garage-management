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
- Reusable Blade components already exist and must be used, not reinvented per page: `<x-button>` (primary/secondary/danger/ghost variants), `<x-badge>`, `<x-input>`, `<x-select>`, `<x-card>`, and a shared data-table pattern. These get restyled per §4's shadcn-look update — same components, updated visual tokens, not rebuilt from scratch.
- All money fields: `decimal(10,2)`, never float.
- Server-side validation always (`$request->validate()` or Form Requests).
- Every delete requires a JS `confirm()` dialog.
- Proper Eloquent relationships, never manual joins.
- Timezone: `Asia/Kolkata` (via `APP_TIMEZONE` env var), not UTC.

## 4. Design system — shadcn visual language, Tailwind + Alpine implementation

The goal is to look like shadcn/ui without adopting React — same neutral color system, border radius, spacing, and component visual patterns, powered by plain Tailwind classes with Alpine.js handling any interactivity a Radix primitive would otherwise provide (dropdowns, dialogs, tabs, popovers).

- **Font:** Inter, self-hosted via `@fontsource/inter` — no Google Fonts or any other font CDN.
- **Color system:** shadcn's approach uses semantic CSS variables (`--background`, `--foreground`, `--primary`, `--primary-foreground`, `--border`, `--muted`, `--muted-foreground`, `--radius`, etc.) rather than reaching for raw Tailwind color names directly in markup. Recreate this same pattern: define these as CSS custom properties in `resources/css/app.css` (a neutral/slate-based palette for background/foreground/border/muted, with `--primary` set to `#145C9E`), then reference them via Tailwind's `theme.extend.colors` using the `hsl(var(--primary))`-style pattern shadcn itself uses, so components read `bg-primary text-primary-foreground` etc. instead of hardcoded shade names. Keep the existing `primary-50`–`primary-900` scale available too for anywhere a specific shade (not the semantic token) is genuinely needed.
- **Border radius:** `--radius: 0.5rem` (shadcn's default) — apply consistently via `rounded-lg`/`rounded-md` mapped to this variable rather than mixing arbitrary radius values across components.
- **Visual style:** shadcn's actual look — subtle single-pixel borders (`border-border`) doing most of the visual separation work, restrained shadow use (shadows for floating elements like dropdowns/dialogs, not for every card), generous whitespace, muted secondary text (`text-muted-foreground`). This replaces the earlier vaguer "Material-Design-influenced" description with the specific, actual shadcn aesthetic.
- **Icons:** Heroicons only, via `blade-ui-kit/blade-heroicons` (`<x-heroicon-o-{name}/>` outline by default, solid only for active/selected states). Never emoji.
- **Navigation:** collapsible left sidebar, not a top navbar. Icon + label per module, expand/collapse state persisted via Alpine + localStorage (UI state only — not application data).
- **List/table row selection pattern:** single-select (radio-style), one record at a time. Selecting a row reveals a contextual action bar above the table (not a per-row Actions column) with buttons relevant to that record type (e.g. Customers: Create Job Card / Add Vehicle / Edit / Delete). Applies consistently across Customers, Job Cards, and any future list page — don't reintroduce per-row action columns.
- **Table/list/tab content uses `text-xs` (14px)**, not the default `text-sm` (14px) — applies specifically to data-table rows/cells and tab panel content. The sidebar/navbar stays at `text-sm` (14px) — don't shrink navigation text.
- **Layouts should use available space fully and responsively** — avoid cramped fixed-width containers with excess empty margin on wider screens; tables and content panels should expand to fill their container, with sensible responsive behavior down to mobile widths.
- **Dashboard only** uses a bento-style grid (varied tile sizes). Every other page uses regular stacked/table layouts.
- **PDF exports** (invoices, estimates, reports) use Open Sans or Noto Sans, self-hosted as actual font files registered with DomPDF (not the `@fontsource` CSS approach used for the web UI — DomPDF renders server-side and needs real font files registered through its own font mechanism). One-time local asset, not a CDN dependency.

## 5. Modules — current structure

- **Dashboard** — stats + bento grid, considered stable, don't restructure without being asked.
- **Customers** (combined with Vehicles in the UI, but two separate DB tables — `Customer hasMany Vehicles`). One customer, many vehicles.
- **Job Cards** — the core workflow, see §7 for the full flow. No separate Estimate table (see §6).
- **Counter Invoice** — parts-only direct sale, no Job Card required, customer optional. Unchanged for now.
- **Payments** — polymorphic (`payable_id`/`payable_type`), attaches to Job Card or Counter Invoice. Partial payments supported, editable/voidable. Status is always derived (`amount_paid` / `outstanding` / `payment_status_label` accessors) — **there is no manual `payment_status` column anywhere**, never re-add one.
- **Reports** — read-only, date-range filterable (default: current month).
- **Employees** — technicians/staff list, functionally stable.
- **Inventory** — combined UI (single nav item, tabs) for Spare Parts and Labour Master. These remain two separate DB tables/CRUD backends — only the navigation/UI is unified, same principle as Customers+Vehicles. Spare Parts shows MRP, fixed Labor Charge, and computed Total.
- **Vehicle Catalog** — Brand/Model master data (separate from a customer's actual owned vehicle record, which references a catalog entry via `vehicle_model_id`).
- **Customer Statement** — a per-customer running-balance ledger (Job Cards + Counter Invoices + Payments merged chronologically), viewable on-screen and as a PDF.
- **Suppliers — removed entirely.** No table, no nav link, no references anywhere. `spare_parts.supplier_id` has been dropped.

## 6. Locked business decisions — do not re-litigate these

- **No email verification, at all.** Not disabled-but-present — fully removed (no `MustVerifyEmail`, no `verified` middleware, no verification routes/views). Simple trusted local tool, single-shop.
- **GST calculation order: Discount → GST on the discounted amount → Round-off.** Each line's taxable amount is reduced proportionally by the document's discount ratio before tax is calculated on it (using that line's own `gst_rate`). After summing tax, the grand total is rounded to the nearest whole rupee, with the adjustment stored and displayed as its own visible "Round Off: ₹X" line — never silently absorbed. Discount can never exceed the subtotal (validated/capped server-side).
- **Stock rules:** deducted only when Job Card parts or Counter Invoice items are saved; returned correctly on delete/edit (via Eloquent model events firing — cascade deletes must use `onDelete('restrict')`, not `cascade`, on any FK from a document/line-item back to a master record, so deleting a master never silently destroys billing history — controllers catch the resulting exception and show a friendly error instead). `spare_parts.current_stock_qty` has a DB-level `CHECK >= 0` constraint as a final safety net.
- **Complaints (on Job Cards) are description-only** — no charge field, no Complaint Master lookup table. Purely diagnostic/descriptive text.
- **Customer Type is just two values:** `regular` (labor charges apply) and `counter` (parts-only, no labor).
- **Vehicle registration is optional** — a "NON-RTO" toggle marks a vehicle as not having an official registration (common for low-speed EVs), which makes `registration_no` nullable for that record instead of required.
- **Part MRP is locked server-side** — always pulled fresh from the `spare_parts` master record when a line is saved, never trusted from client input, regardless of what the form submits.
- **Labor charge stays editable per line, and is per-unit** (multiplied by quantity — fitting 2 brake pads costs the labor charge × 2, not once). Different treatment from MRP — don't apply the same lock to labor charge.
- **Money columns are never `null`.** Every money column (`grand_total`, `amount_paid`, discount/tax fields, etc.) has a DB-level `->default(0)` and is never nullable — always `0` for "not yet billed," never `NULL`, anywhere in application code.

## 7. Job Card workflow (current)

This is a multi-step flow, not a single create form:

1. **From the Customers list**, staff selects a customer (radio-select) and clicks "Create Job Card." If the customer has multiple vehicles, they choose which one; if only one, it's auto-selected.
2. **Basic Job Card creation** — a lightweight form: Job Date, Odometer Reading, Job Type (default: Paid), Promised Delivery Date/Time, Complaints (free-text descriptions only), Remarks. No technician, no parts, no labour, no totals at this stage. Saves with status `received`.
3. **Assign & Bill** — a separate screen/action reached from the Job Card list, where staff assigns a Technician and Supervisor, and adds Parts and Labour line items (parts auto-fill MRP, locked, + fixed labor charge from the part master, editable; labour lines pull from Labour Master). Totals (with GST per §6) are calculated here.
4. **Generate Invoice or Generate Estimate** — once the job card has line items, staff can generate either document as a PDF from the *same* Job Card data via a POST action (not GET — document generation mutates `invoice_generated_at`/`estimate_generated_at` and must not be a side-effecting GET route). There is no separate Estimate database record — "Estimate" is just a differently-labeled/styled PDF output of the current Job Card state. Generating either does not lock or change the record's editable state, except that once any payment exists against the job card, further edits to billed items (parts/labour/discount) are blocked.

## 8. Access & security — locked decisions

- **Single-user app.** Only one trusted admin (Dhruval) ever uses this. No roles, no permissions system, no policies/gates. Do not add role-based authorization — this was deliberately declined, twice.
- **Public self-registration is closed regardless.** Even with no RBAC, `/register` should not be reachable — there's only ever one account, so an open registration endpoint is pure attack surface with zero benefit.
- **IDs passed from PHP into Alpine.js `x-data` must be cast consistently** (always to string on both sides being compared) — a real bug here caused blank-looking pre-filled fields on the Job Card create form (`$request->integer()` produced a PHP int, `@json()` serialized it as a JS number, and it was then compared with `===` against string IDs elsewhere, silently failing to match). When building any cascading-select or pre-filled-from-query-string Alpine component, cast every ID to `(string)` before it reaches `@json()`, and never rely on loose equality to paper over it.

## 9. Performance baseline (daily-use config)

For actual daily use (not active development with an agent), `.env` should have `APP_DEBUG=false`, `SESSION_DRIVER=file`, `CACHE_STORE=file` — routing session/cache through the SQLite database on every request is a confirmed major slowness cause on this stack. SQLite should run in WAL journal mode (`'journal_mode' => 'wal'` in `config/database.php`'s sqlite connection) for better write performance. Icon resolution (`blade-ui-kit/blade-heroicons`) should be cached via its Artisan cache command for daily use. Never assume a slow page is a code bug before checking these environment basics first — this has already been the actual cause once.

## 10. When in doubt

Ask a specific question and wait for the answer rather than assuming. Incorrect assumptions here mean redoing schema/migrations, which is expensive once real data exists.