---
title: Garage Management App — Project Rules
inclusion: always
---

# Project Rules for AI Agent (Kiro)

These rules are permanent. Follow them in every task, every session, without being reminded. If a new request conflicts with a rule below, STOP and ask the user before proceeding — do not silently override or "improve" a decision that was already made deliberately.

## 1. What this project is

A single-location workshop management system for Dhruval's EV scooter service center (Kakadiya EV Hub). Used by garage staff on a local Windows PC via browser — not a hosted/cloud SaaS. Single location only — never add `branch_id` or multi-tenant fields unless explicitly asked.

## 2. Fixed tech stack — do not change or suggest alternatives

- Backend: PHP 8.3+, Laravel 13, Eloquent ORM
- Database: SQLite (single file, `database/database.sqlite`)
- Frontend: Blade templates + Tailwind CSS + Alpine.js (no React/Vue/Inertia)
- Auth: Laravel Breeze (Blade stack)
- Build tool: Vite (`npm run dev` while developing, `npm run build` for production)
- PDF generation (for invoices/estimates): DomPDF, when needed
- No Docker, no external DB server, no cloud services. Everything runs locally.

## 3. UI / code conventions — match what already exists

- Every CRUD module follows the same pattern already built for Customers: `index.blade.php` (table + "+ Add" button top-right), `create.blade.php`, `edit.blade.php`, all wrapped in `<x-app-layout>` with a `header` slot.
- Controllers are Laravel resource controllers (`php artisan make:model X -mcr`), routes registered via `Route::resource()` inside the `auth` + `verified` middleware group in `routes/web.php`.
- Tailwind styling only — same input/button/table classes already used in the Customers module. Don't introduce a new design system or component library without asking.
- All money fields: `decimal(10,2)` in migrations — never `float` or `double`.
- All form validation happens server-side (`$request->validate()` or Form Request classes) — never trust client-side validation alone.
- Every delete action requires a JS `confirm()` dialog before submitting.
- Model relationships use proper Eloquent (`belongsTo`, `hasMany`, etc.) — never raw manual joins for relational data.

## 4. Business logic rules — these are final decisions, not open questions

- **Estimates are always separate records from Job Cards/Invoices.** An estimate never mutates into a job card automatically. Approving an estimate creates a **new, linked** Job Card (via a "Convert to Job Card" action) — the original Estimate record stays intact with its own status history.
- **Estimates always require an existing saved Customer + Vehicle.** No quick/anonymous estimates.
- **Counter Invoices never require a Job Card.** They are a fast, parts-only, no-labour direct sale flow (for retailers/dealers or walk-in parts purchases). Customer is optional on a Counter Invoice — can select an existing Customer or leave blank.
- **Two distinct kinds of labor charge — never merge these:**
  1. **Fixed fitting/labor charge stored on each Spare Part** — auto-fills as a line item whenever that part is added to a Job Card or Estimate, editable per line.
  2. **Labour Master** — a separate, standalone list of services (general service, welding, washing, BMS reset, etc.) not tied to any specific part, added as its own line item.
- **Spare Parts / Inventory list must always show three price columns:** MRP (part price), Labor Charge (fixed fitting charge), and Total (MRP + Labor, computed).
- **Stock quantity is only deducted when:** a Job Card's "Parts Used" is saved, or a Counter Invoice is saved. Estimates never touch stock.
- **GST is always optional, per document, via a toggle** — never mandatory. Default toggle state = off. This applies to Estimates, Job Card Invoices, and Counter Invoices alike.
- Customer Type values are fixed: `walk-in`, `regular`, `fleet`, `dealer`. Don't add new types without asking.
- **Payments are tracked as their own records (partial payments supported), not a single flag.** The `payments` table uses a polymorphic relation (`payable_id`/`payable_type`) so it can attach to a Job Card, a Counter Invoice, or future document types without schema changes. Never add separate nullable foreign keys for each document type instead of using the polymorphic relation.
- **A document's payment status is always derived, never manually typed in.** "Paid / Partially Paid / Unpaid" and the outstanding amount are always calculated live from `grand_total - SUM(linked payments)` — there is no manual `payment_status` dropdown on the Job Card or Counter Invoice form for staff to set by hand.
- **Reports are strictly read-only.** No create/edit/delete actions live inside the Reports section. Every report supports a date-range filter (default: current month) passed via query string so filtered views are bookmarkable.
- **`job_cards` and `counter_invoices` have no `payment_status` column at all.** It was deliberately removed. Payment status is always shown via the derived `payment_status_label` accessor (built from actual `payments` records). Never re-add a manually-set payment status field to any form or migration.
- **Master records (Customer, Vehicle, Spare Part, Labour Master) can never be deleted if they have linked documents.** Foreign keys from documents/line-items back to these masters use `onDelete('restrict')`, never `cascade`. Controllers catch the resulting `QueryException` and show a friendly "Cannot delete — linked to existing records" message. Never let deleting a master record silently destroy billing history.
- **Job Card / Estimate complaints are free-text only** (description + charge) — there is no Complaint Master lookup table. Don't reintroduce a `complaint_master_id` column or a Complaint Master module.
- **GST is calculated per line item, never as a flat hardcoded rate.** Each Spare Part has its own `gst_rate`; each Labour Master service has its own `gst_rate` (applied only if `taxable` is true). Tax is split evenly into CGST/SGST per line, then summed at the document level — only when the document's `gst_applicable` toggle is on. Keep this logic in one shared `app/Services/GstCalculator.php`, not duplicated per controller.
- **No CDN dependencies for JS/CSS libraries** (e.g. Chart.js). This is a local/offline-capable tool — install packages via npm and bundle them through Vite.
- **Timezone is `Asia/Kolkata`**, not UTC.

## 7. Design system — locked, apply to every new page going forward

- **Font:** Inter, self-hosted via `@fontsource/inter` (no Google Fonts CDN).
- **Primary color:** `#145C9E` (Tailwind `primary-600`; a generated tonal scale from `primary-50` to `primary-900` exists in `tailwind.config.js` — always use `primary-*` instead of the default `blue-*`).
- **Visual style:** minimalist, Material-Design-influenced (soft shadow/elevation on cards, generous spacing, rounded corners, clear hover/focus states) — not flashy, not neumorphic/glassmorphic.
- **Icons:** Heroicons only, via `blade-ui-kit/blade-heroicons` (`<x-heroicon-o-{name}/>` outline by default, `<x-heroicon-s-{name}/>` solid only for active/selected states). Never use emoji as icons anywhere in the UI.
- **Navigation:** a collapsible left sidebar (not a top navbar) — icon + label per module, persisted expand/collapse state via Alpine + localStorage (UI state only, not application data — this doesn't conflict with the "no browser storage for data" rule elsewhere, which is about artifacts, not this app).
- **Reusable components exist and must be used, not reinvented per page:** `<x-button>`, `<x-badge>`, `<x-input>`, `<x-select>`, `<x-card>`, and the shared data-table pattern. Every new CRUD module built from here on must use these instead of raw HTML, so visual consistency never has to be retrofitted again.
- **Dashboard only** uses a bento-style grid (varied tile sizes via CSS grid). Every other page uses regular stacked/table layouts, not bento.
- **Always run `npm run build` (or keep `npm run dev` running) after adding new Blade views/classes** — a stale compiled CSS bundle has already caused an "invisible button" bug once; don't let it happen again.

## 5. Data safety

- Never drop or rename an existing table/column that's already migrated without explicit confirmation from the user — write a new migration instead.
- Never modify `database/database.sqlite` directly; all schema changes go through Laravel migrations.
- File uploads (expense receipts, etc.) are stored locally via Laravel Storage — not any cloud bucket.

## 6. When in doubt

If a request is ambiguous, or touches a decision not covered above, **ask a specific question and wait for the answer.** Do not assume the "obvious" interpretation and build it — incorrect assumptions here mean redoing schema/migrations, which is expensive to undo once real data exists.