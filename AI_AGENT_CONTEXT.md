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
- Frontend: Blade + Tailwind CSS + Alpine.js (no React/Vue/Inertia)
- Auth: Laravel Breeze (Blade stack) — **email verification is disabled entirely**, not just unused (see §6)
- Build tool: Vite (`npm run dev` while developing, `npm run build` before testing — a stale build has already caused an "invisible button" bug once; don't let it happen again)
- PDF generation: DomPDF
- No Docker, no external DB server, no cloud services, no CDN-hosted JS/CSS (this app must work fully offline — fonts, icons, charts, everything bundled locally via npm/Vite or Composer)

## 3. UI / code conventions

- Resource controllers (`php artisan make:model X -mcr`), routes via `Route::resource()` inside the `auth` middleware group (no `verified` — see §6).
- Reusable Blade components already exist and must be used, not reinvented per page: `<x-button>` (primary/secondary/danger/ghost variants), `<x-badge>`, `<x-input>`, `<x-select>`, `<x-card>`, and a shared data-table pattern.
- All money fields: `decimal(10,2)`, never float.
- Server-side validation always (`$request->validate()` or Form Requests).
- Every delete requires a JS `confirm()` dialog.
- Proper Eloquent relationships, never manual joins.
- Timezone: `Asia/Kolkata` (via `APP_TIMEZONE` env var), not UTC.

## 4. Design system

- **Font:** Inter, self-hosted via `@fontsource/inter` — no Google Fonts or any other font CDN.
- **Primary color:** `#145C9E` as Tailwind `primary-600`, with a full generated tonal scale `primary-50`–`primary-900` in `tailwind.config.js`. Use `primary-*` everywhere instead of default `blue-*`.
- **Visual style:** minimalist, Material-Design-influenced (soft shadow/elevation, generous spacing, rounded corners, clear hover/focus states) — not flashy, not neumorphic/glassmorphic.
- **Icons:** Heroicons only, via `blade-ui-kit/blade-heroicons` (`<x-heroicon-o-{name}/>` outline by default, solid only for active/selected states). Never emoji.
- **Navigation:** collapsible left sidebar, not a top navbar. Icon + label per module, expand/collapse state persisted via Alpine + localStorage (UI state only — not application data).
- **List/table row selection pattern:** single-select (radio-style), one record at a time. Selecting a row reveals a contextual action bar above the table (not a per-row Actions column) with buttons relevant to that record type (e.g. Customers: Create Job Card / Add Vehicle / Edit / Delete). This pattern applies consistently across Customers, Job Cards, and any future list page — don't reintroduce per-row action columns.
- **Dashboard only** uses a bento-style grid (varied tile sizes). Every other page uses regular stacked/table layouts.

## 5. Modules — current structure

- **Dashboard** — stats + bento grid, considered stable, don't restructure without being asked.
- **Customers** (combined with Vehicles in the UI, but two separate DB tables — `Customer hasMany Vehicles`). One customer, many vehicles.
- **Job Cards** — the core workflow, see §7 for the full flow. No separate Estimate table (see §6).
- **Counter Invoice** — parts-only direct sale, no Job Card required, customer optional. Unchanged for now.
- **Payments** — polymorphic (`payable_id`/`payable_type`), attaches to Job Card or Counter Invoice. Partial payments supported. Status is always derived (`amount_paid` / `outstanding` / `payment_status_label` accessors) — **there is no manual `payment_status` column anywhere**, never re-add one.
- **Reports** — read-only, date-range filterable (default: current month).
- **Employees** — technicians/staff list, functionally stable.
- **Inventory** — combined UI (single nav item, tabs) for Spare Parts and Labour Master. These remain two separate DB tables/CRUD backends — only the navigation/UI is unified, same principle as Customers+Vehicles. Spare Parts shows MRP, fixed Labor Charge, and computed Total.
- **Suppliers — removed entirely.** No table, no nav link, no references anywhere. `spare_parts.supplier_id` has been dropped.

## 6. Locked business decisions — do not re-litigate these

- **No email verification, at all.** Not disabled-but-present — fully removed (no `MustVerifyEmail`, no `verified` middleware, no verification routes/views). Simple trusted local tool, single-shop.
- **GST calculation order: Discount → GST on the discounted amount → Round-off.** Each line's taxable amount is reduced proportionally by the document's discount ratio before tax is calculated on it (using that line's own `gst_rate`). After summing tax, the grand total is rounded to the nearest whole rupee, with the adjustment stored and displayed as its own visible "Round Off: ₹X" line — never silently absorbed.
- **Stock rules:** deducted only when Job Card parts or Counter Invoice items are saved; returned correctly on delete/edit (via Eloquent model events firing — cascade deletes must use `onDelete('restrict')`, not `cascade`, on any FK from a document/line-item back to a master record, so deleting a master never silently destroys billing history — controllers catch the resulting exception and show a friendly error instead).
- **Complaints (on Job Cards) are description-only** — no charge field, no Complaint Master lookup table. Purely diagnostic/descriptive text.
- **Customer Type is now just two values:** `regular` (labor charges apply) and `counter` (parts-only, no labor) — replacing the old four-value enum (`walk-in`/`regular`/`fleet`/`dealer`).
- **Vehicle registration is optional** — a "NON-RTO" toggle marks a vehicle as not having an official registration (common for low-speed EVs), which makes `registration_no` nullable for that record instead of required.

## 7. Job Card workflow (current, as of this phase)

This is a multi-step flow, not a single create form:

1. **From the Customers list**, staff selects a customer (radio-select) and clicks "Create Job Card." If the customer has multiple vehicles, they choose which one; if only one, it's auto-selected.
2. **Basic Job Card creation** — a lightweight form: Job Date, Odometer Reading, Job Type (default: Paid), Promised Delivery Date/Time, Complaints (free-text descriptions only), Remarks. No technician, no parts, no labour, no totals at this stage. Saves with status `received`.
3. **Assign & Bill** — a separate screen/action reached from the Job Card list, where staff assigns a Technician and Supervisor, and adds Parts and Labour line items (same subform pattern as before: parts auto-fill MRP + fixed labor charge from the part master, editable; labour lines pull from Labour Master). Totals (with GST per §6) are calculated here.
4. **Generate Invoice or Generate Estimate** — once the job card has line items, staff can generate either document as a PDF from the *same* Job Card data. There is no separate Estimate database record — "Estimate" is just a differently-labeled/styled PDF output of the current Job Card state (header says "ESTIMATE" instead of "TAX INVOICE"), for giving a customer a quote before final billing. Track `invoice_generated_at` / `estimate_generated_at` timestamps on the Job Card for audit purposes only — generating either does not lock or change the record's editable state.

## 8. When in doubt

Ask a specific question and wait for the answer rather than assuming. Incorrect assumptions here mean redoing schema/migrations, which is expensive once real data exists.