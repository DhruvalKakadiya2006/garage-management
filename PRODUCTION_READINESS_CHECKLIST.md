# GMS Production Readiness Checklist

**Version:** 1.0.0  
**Last Updated:** 2026-09-07  
**Status:** 🔄 In Progress

---

## 0. Release Gate (P0/P1 Blocker Check)

- [ ] **0 known P0 bugs** (Data loss, security vulnerability, app cannot start)
- [ ] **0 known P1 bugs** (Core workflow broken, incorrect invoice/payment/PDF)
- [ ] All automated tests pass
- [ ] Production build succeeds
- [ ] No browser console errors
- [ ] Database migrations work from fresh database
- [ ] Existing database upgrades successfully
- [ ] Backup + restore verified
- [ ] `.exe` installs successfully on clean Windows machine
- [ ] App works after reboot
- [ ] App works without internet
- [ ] Uninstall does not corrupt/accidentally delete user data
- [ ] Upgrade from previous version works
- [ ] Release artifact is versioned and reproducible

**Bug Priority Classification:**

| Priority | Meaning                                             | Release? |
|----------|-----------------------------------------------------|----------|
| **P0**   | Data loss, security vulnerability, app cannot start | ❌ No     |
| **P1**   | Core workflow broken, incorrect invoice/payment/PDF | ❌ No     |
| **P2**   | Significant but workaround exists                   | Usually no for v1.0 |
| **P3**   | Cosmetic/minor UX                                   | Can ship |

---

## 1. Code Quality & Standards (Automated)

### PHP Code Quality
- [ ] All PHP files pass syntax check (`php -l`)
- [ ] Laravel Pint formatting passes (`vendor/bin/pint --test`)
- [ ] No commented-out code blocks in production files
- [ ] No debug statements (`dd()`, `dump()`, `var_dump()`)
- [ ] No `TODO` comments in critical paths

### JavaScript/Frontend
- [ ] No JavaScript syntax errors
- [ ] Vite build succeeds (`npm run build`)
- [ ] No unused imports
- [ ] No console.log statements in production code
- [ ] Alpine.js components work without errors

### Dependencies
- [ ] `composer.lock` is up to date
- [ ] `package-lock.json` is up to date
- [ ] No vulnerable dependencies (`composer audit`)
- [ ] No vulnerable npm packages (`npm audit`)
- [ ] All dependencies compatible with production PHP version

---

## 2. Database & Migrations (Automated + Manual)

### Fresh Installation
- [ ] Fresh migration works (`php artisan migrate:fresh`)
- [ ] All migrations run without errors
- [ ] Seeders work if present (`php artisan db:seed`)
- [ ] Default admin user created correctly
- [ ] All tables created with correct schema
- [ ] All indexes created
- [ ] All foreign keys created

### Migration Safety
- [ ] No `dropColumn` without backup warning
- [ ] No `dropTable` without confirmation
- [ ] Migrations are reversible with `down()` methods
- [ ] No data loss on rollback where expected

### Database Integrity
- [ ] All foreign keys have proper `onDelete` constraints
- [ ] No orphaned records possible
- [ ] Soft deletes work correctly
- [ ] Timestamps are automatic
- [ ] `deleted_at` respected in queries

---

## 3. Automated Test Suite (Automated)

### PHPUnit Tests
- [ ] All unit tests pass (`php artisan test --testsuite=Unit`)
- [ ] All feature tests pass (`php artisan test --testsuite=Feature`)
- [ ] Test coverage >70% on critical paths
- [ ] No skipped tests without justification
- [ ] No test warnings

### Critical Test Areas
- [ ] Customer CRUD tests pass
- [ ] Vehicle CRUD tests pass
- [ ] Job Card workflow tests pass
- [ ] Counter Invoice tests pass
- [ ] Payment calculation tests pass
- [ ] GST calculation tests pass
- [ ] Stock integrity tests pass
- [ ] Authentication tests pass
- [ ] Authorization tests pass

---

## 4. Authentication & Security (Manual + Automated)

### Login & Session
- [ ] **MANUAL:** Correct credentials → login
- [ ] **MANUAL:** Wrong password → rejected
- [ ] **MANUAL:** Empty credentials → validation
- [ ] **MANUAL:** Logout works
- [ ] **MANUAL:** Back button doesn't expose authenticated pages
- [ ] **AUTOMATED:** Unauthenticated user cannot access protected routes
- [ ] **MANUAL:** Session expires correctly
- [ ] **AUTOMATED:** CSRF protection works

### Authorization
- [ ] Only admin account can access admin functions
- [ ] Public registration is disabled
- [ ] No accidental public routes exposed

### Production Configuration
- [ ] `APP_ENV=production` in production
- [ ] `APP_DEBUG=false` in production
- [ ] Correct `APP_URL` configured
- [ ] Unique production `APP_KEY`
- [ ] `.env` not in version control
- [ ] `.env.example` has safe defaults
- [ ] Error pages don't expose stack traces
- [ ] Logs don't expose passwords/credentials

---

## 5. Customer Module (Manual)

### Create
- [ ] **MANUAL:** Create regular customer
- [ ] **MANUAL:** Required fields validated
- [ ] **MANUAL:** Invalid phone rejected
- [ ] **MANUAL:** Duplicate handling works

### Edit
- [ ] **MANUAL:** Edit customer
- [ ] **MANUAL:** Changes persist
- [ ] **MANUAL:** Existing vehicles remain attached

### Delete
- [ ] **MANUAL:** Customer with no dependencies can be deleted
- [ ] **MANUAL:** Customer with vehicles/job cards protected
- [ ] **AUTOMATED:** Database doesn't silently orphan records

### Search & Filter
- [ ] **MANUAL:** Search by name works
- [ ] **MANUAL:** Search by phone works
- [ ] **MANUAL:** Empty search returns all
- [ ] **MANUAL:** Special characters handled
- [ ] **MANUAL:** Pagination works
- [ ] **MANUAL:** Outstanding filter works
- [ ] **MANUAL:** Sorting by name works
- [ ] **MANUAL:** Sorting by outstanding works
- [ ] **MANUAL:** Sorting by visits works

---

## 6. Vehicle Workflow (Manual - P1 Critical)

### Customer → Vehicle
- [ ] **MANUAL:** Customer with zero vehicles
- [ ] **MANUAL:** Customer with one vehicle
- [ ] **MANUAL:** Customer with multiple vehicles
- [ ] **MANUAL:** Add vehicle works
- [ ] **MANUAL:** Edit vehicle works
- [ ] **MANUAL:** Delete vehicle protected when used
- [ ] **MANUAL:** Vehicle-customer association correct
- [ ] **MANUAL:** Vehicle model/brand relationships work
- [ ] **MANUAL:** NON-RTO vehicle works
- [ ] **MANUAL:** Vehicle registration nullable works

### Critical Workflow (P1)
**Test: Customers → Select Customer → Create Job Card**

- [ ] **MANUAL:** Vehicle dropdown loads correctly
- [ ] **MANUAL:** Customer's vehicles appear
- [ ] **MANUAL:** One vehicle pre-selected
- [ ] **MANUAL:** Multiple vehicles shown in dropdown
- [ ] **MANUAL:** Vehicle can be selected
- [ ] **MANUAL:** Selected vehicle persists through validation
- [ ] **MANUAL:** Selected vehicle submitted correctly
- [ ] **MANUAL:** No Alpine console errors
- [ ] **MANUAL:** No "missing ) after argument list" error

### Browser Console (P0)
- [ ] **MANUAL:** Open DevTools on every page
- [ ] **MANUAL:** Console has 0 application errors
- [ ] **MANUAL:** No Alpine expression errors
- [ ] **MANUAL:** No failed JS module loads
- [ ] **MANUAL:** No failed fetch requests
- [ ] **MANUAL:** No missing assets (404s)

---

## 7. Job Card Module (Manual - P1 Critical)

### Creation
- [ ] **MANUAL:** Job Card created through Customer workflow
- [ ] **MANUAL:** Customer selected correctly
- [ ] **MANUAL:** Vehicle selected correctly
- [ ] **MANUAL:** Job date defaults correctly
- [ ] **MANUAL:** Complaints can be added
- [ ] **MANUAL:** Multiple complaints work
- [ ] **MANUAL:** Status initially `received`
- [ ] **MANUAL:** Job Card number format: `JC-YYMMDD-NNN`
- [ ] **MANUAL:** First job card of day: `JC-260907-001`
- [ ] **MANUAL:** Multiple cards same day increment correctly
- [ ] **MANUAL:** No duplicate job card numbers

### Assignment
- [ ] **MANUAL:** Technician can be assigned
- [ ] **MANUAL:** Supervisor can be assigned
- [ ] **MANUAL:** Invalid employee IDs rejected
- [ ] **MANUAL:** Assignment can be changed
- [ ] **MANUAL:** Nullable assignments work

### Labour Lines
- [ ] **MANUAL:** Labour line can be added
- [ ] **MANUAL:** Labour master selection works
- [ ] **MANUAL:** Quantity works
- [ ] **MANUAL:** Editable labour rate works
- [ ] **MANUAL:** Decimal amounts work
- [ ] **MANUAL:** Zero quantity rejected
- [ ] **MANUAL:** Negative values rejected

### Spare Parts Lines
- [ ] **MANUAL:** Spare part can be added
- [ ] **MANUAL:** Quantity works
- [ ] **MANUAL:** MRP is correctly used
- [ ] **MANUAL:** Part price is server-controlled
- [ ] **MANUAL:** Negative quantity rejected
- [ ] **MANUAL:** Zero quantity handled correctly
- [ ] **MANUAL:** Multiple parts work

### Stock (Intentionally Disabled)
- [ ] **MANUAL:** Adding Job Card part does NOT auto-decrement stock
- [ ] **MANUAL:** Editing quantity does NOT modify stock
- [ ] **MANUAL:** Removing part does NOT modify stock
- [ ] **MANUAL:** `current_stock_qty` remains intact

### Search & Filter
- [ ] **MANUAL:** Search by job card no works
- [ ] **MANUAL:** Search by customer name works
- [ ] **MANUAL:** Search by vehicle registration works
- [ ] **MANUAL:** Date range filter works
- [ ] **MANUAL:** Technician filter works
- [ ] **MANUAL:** Status filter works
- [ ] **MANUAL:** Brand filter works
- [ ] **MANUAL:** Payment status filter works
- [ ] **MANUAL:** Job type filter works
- [ ] **MANUAL:** Multiple filters work together
- [ ] **MANUAL:** Sorting by date works
- [ ] **MANUAL:** Sorting by status works
- [ ] **MANUAL:** Sorting by total works

---

## 8. GST & Invoice Calculations (Manual - P1 Critical)

### Calculation Order
**Formula: Subtotal → Discount → GST → Grand Total → Round Off**

- [ ] **MANUAL:** No discount calculation correct
- [ ] **MANUAL:** Percentage discount works
- [ ] **MANUAL:** Discount = 0 works
- [ ] **MANUAL:** Discount exactly equals subtotal works
- [ ] **MANUAL:** Discount > subtotal rejected
- [ ] **MANUAL:** Multiple line items calculate correctly
- [ ] **MANUAL:** Different GST rates work
- [ ] **MANUAL:** 0% GST works
- [ ] **MANUAL:** Decimal prices work (e.g., 99.95)
- [ ] **MANUAL:** Decimal quantities work (e.g., 2.5)
- [ ] **MANUAL:** GST rounding correct
- [ ] **MANUAL:** Grand total nearest rupee
- [ ] **MANUAL:** Round Off visible and correct

### Money Precision Test Cases
Test these values:
- [ ] **MANUAL:** ₹0.01
- [ ] **MANUAL:** ₹0.10
- [ ] **MANUAL:** ₹99.99
- [ ] **MANUAL:** ₹999.95
- [ ] **MANUAL:** ₹12,345.67

Verify:
- [ ] No floating-point artifacts
- [ ] Database stores correct decimals
- [ ] Display uses 2 decimal precision
- [ ] Invoice totals match expected

---

## 9. Counter Invoice Module (Manual)

### Creation
- [ ] **MANUAL:** Create parts-only Counter Invoice
- [ ] **MANUAL:** Customer optional (walk-in)
- [ ] **MANUAL:** Customer can be attached
- [ ] **MANUAL:** Never linked to Job Card incorrectly
- [ ] **MANUAL:** Multiple parts work
- [ ] **MANUAL:** Quantities work
- [ ] **MANUAL:** MRP used correctly
- [ ] **MANUAL:** GST calculation correct
- [ ] **MANUAL:** Discount works
- [ ] **MANUAL:** Round Off correct
- [ ] **MANUAL:** Grand Total correct
- [ ] **MANUAL:** Invoice number unique

### Validation (Security Test)
- [ ] **MANUAL:** Invalid spare part ID rejected
- [ ] **MANUAL:** Fake price rejected
- [ ] **MANUAL:** Negative quantity rejected
- [ ] **MANUAL:** Excessive discount rejected
- [ ] **MANUAL:** Invalid GST rate rejected

### Search & Filter
- [ ] **MANUAL:** Date range filter works
- [ ] **MANUAL:** Payment status filter works
- [ ] **MANUAL:** Customer type filter works
- [ ] **MANUAL:** Sorting by date works
- [ ] **MANUAL:** Sorting by invoice no works
- [ ] **MANUAL:** Sorting by total works

---

## 10. Payment Module (Manual - P1 Critical)

### Payment Lifecycle
- [ ] **MANUAL:** Payment against Job Card
- [ ] **MANUAL:** Payment against Counter Invoice
- [ ] **MANUAL:** Full payment (100%)
- [ ] **MANUAL:** Partial payment
- [ ] **MANUAL:** Multiple payments
- [ ] **MANUAL:** Edit payment
- [ ] **MANUAL:** Void payment
- [ ] **MANUAL:** Invalid amount rejected
- [ ] **MANUAL:** Negative amount rejected
- [ ] **MANUAL:** Payment > outstanding handled correctly
- [ ] **MANUAL:** Outstanding calculated correctly
- [ ] **MANUAL:** Status derived from (total - payments)
- [ ] **MANUAL:** No `payment_status` column in database
- [ ] **MANUAL:** Voided payment doesn't count

### Critical Edge Cases (P1)
**Invoice = ₹10,000 - Test each:**

- [ ] **MANUAL:** ₹0 paid → Status: Unpaid
- [ ] **MANUAL:** ₹1 paid → Status: Partially Paid
- [ ] **MANUAL:** ₹5,000 paid → Status: Partially Paid
- [ ] **MANUAL:** ₹9,999 paid → Status: Partially Paid
- [ ] **MANUAL:** ₹10,000 paid → Status: Paid
- [ ] **MANUAL:** Multiple payments totaling ₹10,000 → Status: Paid

### Search & Filter
- [ ] **MANUAL:** Payment mode filter works
- [ ] **MANUAL:** Date range filter works
- [ ] **MANUAL:** Document type filter works
- [ ] **MANUAL:** Sorting by date works
- [ ] **MANUAL:** Sorting by amount works

---

## 11. PDF Generation (Manual - P1 Critical)

### Job Card PDFs
- [ ] **MANUAL:** Job Card invoice PDF generates
- [ ] **MANUAL:** Estimate PDF generates
- [ ] **MANUAL:** Company profile info appears
- [ ] **MANUAL:** Customer info correct
- [ ] **MANUAL:** Vehicle info correct
- [ ] **MANUAL:** Line items correct
- [ ] **MANUAL:** GST correct
- [ ] **MANUAL:** Discount correct
- [ ] **MANUAL:** Round Off correct
- [ ] **MANUAL:** Grand Total correct

### Counter Invoice PDFs
- [ ] **MANUAL:** Counter Invoice PDF generates
- [ ] **MANUAL:** All calculations correct
- [ ] **MANUAL:** Walk-in customer handled

### Layout & Rendering
- [ ] **MANUAL:** Fonts render correctly
- [ ] **MANUAL:** Self-hosted fonts work (no CDN)
- [ ] **MANUAL:** PDFs work offline
- [ ] **MANUAL:** No missing glyphs
- [ ] **MANUAL:** Long customer name doesn't break layout
- [ ] **MANUAL:** Long part name doesn't break layout
- [ ] **MANUAL:** Many line items (20+) don't break layout

### Print Test
- [ ] **MANUAL:** Print preview works
- [ ] **MANUAL:** A4 page size correct
- [ ] **MANUAL:** No clipped content
- [ ] **MANUAL:** Tables fit properly
- [ ] **MANUAL:** Footer/header correct
- [ ] **MANUAL:** Black & white appearance acceptable

---

## 12. Inventory Module (Manual)

### Spare Parts
- [ ] **MANUAL:** Create spare part
- [ ] **MANUAL:** Edit spare part
- [ ] **MANUAL:** Delete protected when used
- [ ] **MANUAL:** MRP field works
- [ ] **MANUAL:** GST rate works
- [ ] **MANUAL:** `current_stock_qty` stable
- [ ] **MANUAL:** Search by name works
- [ ] **MANUAL:** Search by part code works
- [ ] **MANUAL:** Category filter works
- [ ] **MANUAL:** Sorting works

### Labour Masters
- [ ] **MANUAL:** Create labour master
- [ ] **MANUAL:** Edit labour master
- [ ] **MANUAL:** Delete protected when used
- [ ] **MANUAL:** Standard rate works
- [ ] **MANUAL:** Taxable flag works
- [ ] **MANUAL:** Search works
- [ ] **MANUAL:** Taxable filter works
- [ ] **MANUAL:** Sorting works

---

## 13. Master Data (Manual)

### Vehicle Catalog
- [ ] **MANUAL:** Create vehicle brand
- [ ] **MANUAL:** Edit vehicle brand
- [ ] **MANUAL:** Delete protected when used
- [ ] **MANUAL:** Create vehicle model
- [ ] **MANUAL:** Edit vehicle model
- [ ] **MANUAL:** Delete protected when used
- [ ] **MANUAL:** Brand-model relationship works
- [ ] **MANUAL:** Sorting works

### Employees
- [ ] **MANUAL:** Create employee
- [ ] **MANUAL:** Edit employee
- [ ] **MANUAL:** Delete protected when assigned
- [ ] **MANUAL:** Active/inactive status works
- [ ] **MANUAL:** Role selection works
- [ ] **MANUAL:** Active filter works
- [ ] **MANUAL:** Role filter works
- [ ] **MANUAL:** Sorting works

### Company Profile
- [ ] **MANUAL:** Edit company profile
- [ ] **MANUAL:** Company name saved
- [ ] **MANUAL:** Address saved
- [ ] **MANUAL:** Contact details saved
- [ ] **MANUAL:** GST number saved
- [ ] **MANUAL:** Logo upload works (if implemented)
- [ ] **MANUAL:** Changes appear in PDFs

---

## 14. Foreign Key & Data Integrity (Automated + Manual)

### Delete Protection Tests
- [ ] **AUTOMATED:** Cannot delete customer with vehicles
- [ ] **AUTOMATED:** Cannot delete customer with job cards
- [ ] **AUTOMATED:** Cannot delete vehicle with job cards
- [ ] **AUTOMATED:** Cannot delete spare part in use
- [ ] **AUTOMATED:** Cannot delete labour master in use
- [ ] **AUTOMATED:** Cannot delete vehicle model in use
- [ ] **AUTOMATED:** Cannot delete vehicle brand in use
- [ ] **MANUAL:** User sees friendly error message
- [ ] **MANUAL:** No silent cascade deletion

---

## 15. Analytics Module (Manual)

### Basic Functionality
- [ ] **MANUAL:** Current month defaults
- [ ] **MANUAL:** Month changes work
- [ ] **MANUAL:** No mutation possible (read-only)
- [ ] **MANUAL:** Charts load with no data
- [ ] **MANUAL:** Charts load with one record
- [ ] **MANUAL:** Charts load with large data
- [ ] **MANUAL:** Date filtering correct
- [ ] **MANUAL:** Totals match underlying records
- [ ] **MANUAL:** No JavaScript console errors

---

## 16. UI/UX Production Test (Manual)

### Layout Testing
**Test on each page:**

- [ ] **MANUAL:** Desktop 1920×1080
- [ ] **MANUAL:** Laptop 1366×768
- [ ] **MANUAL:** Smaller screen 1280×720
- [ ] **MANUAL:** Responsive behavior
- [ ] **MANUAL:** Empty state displays correctly
- [ ] **MANUAL:** Validation errors clear
- [ ] **MANUAL:** Success messages clear
- [ ] **MANUAL:** Delete confirmation works
- [ ] **MANUAL:** Loading states visible
- [ ] **MANUAL:** Pagination works
- [ ] **MANUAL:** Long text doesn't break layout
- [ ] **MANUAL:** Long numbers don't break layout
- [ ] **MANUAL:** Modals work correctly
- [ ] **MANUAL:** Keyboard navigation works
- [ ] **MANUAL:** Buttons have correct states

### All Pages Test
Go through each page in order:

- [ ] **MANUAL:** Dashboard
- [ ] **MANUAL:** Job Cards
- [ ] **MANUAL:** Customers
- [ ] **MANUAL:** Vehicle Catalog
- [ ] **MANUAL:** Counter Invoice
- [ ] **MANUAL:** Payments
- [ ] **MANUAL:** Inventory (Spare Parts tab)
- [ ] **MANUAL:** Inventory (Labour Masters tab)
- [ ] **MANUAL:** Analytics
- [ ] **MANUAL:** Employees
- [ ] **MANUAL:** Company Profile

---

## 17. Offline Functionality (Manual)

### Disconnect Internet and Test
- [ ] **MANUAL:** Login works
- [ ] **MANUAL:** Dashboard loads
- [ ] **MANUAL:** Create customer works
- [ ] **MANUAL:** Create vehicle works
- [ ] **MANUAL:** Create job card works
- [ ] **MANUAL:** Generate invoice works
- [ ] **MANUAL:** Record payment works
- [ ] **MANUAL:** Generate PDF works
- [ ] **MANUAL:** View inventory works
- [ ] **MANUAL:** View analytics works
- [ ] **MANUAL:** Edit company profile works
- [ ] **MANUAL:** No external CDN/API required
- [ ] **MANUAL:** All fonts/assets load locally

---

## 18. SQLite & Database Integrity (Manual)

### Basic Integrity
- [ ] **MANUAL:** WAL mode enabled
- [ ] **MANUAL:** Database survives app restart
- [ ] **MANUAL:** Database survives Windows restart
- [ ] **MANUAL:** Database location documented

### Stress Testing
- [ ] **MANUAL:** Create 100+ customers
- [ ] **MANUAL:** Create 100+ job cards
- [ ] **MANUAL:** Create 100+ invoices
- [ ] **MANUAL:** No performance degradation
- [ ] **MANUAL:** No database corruption
- [ ] **MANUAL:** No "database is locked" errors

---

## 19. Production Build Test (Automated)

### Build Process
- [ ] **AUTOMATED:** `composer install --no-dev` succeeds
- [ ] **AUTOMATED:** `npm run build` succeeds
- [ ] **AUTOMATED:** `php artisan config:cache` succeeds
- [ ] **AUTOMATED:** `php artisan route:cache` succeeds
- [ ] **AUTOMATED:** `php artisan view:cache` succeeds
- [ ] **AUTOMATED:** No dev dependencies in production
- [ ] **AUTOMATED:** Vite assets in `public/build/`
- [ ] **AUTOMATED:** No source maps in production

### Production Runtime
- [ ] **MANUAL:** Test built application (not dev)
- [ ] **MANUAL:** No Vite dev server required
- [ ] **MANUAL:** No Node.js required at runtime
- [ ] **MANUAL:** No Composer required at runtime
- [ ] **MANUAL:** Debug disabled (`APP_DEBUG=false`)
- [ ] **MANUAL:** Production assets load correctly
- [ ] **MANUAL:** PDFs work in production build

---

## 20. Crash & Recovery Testing (Manual)

### Force Failures
- [ ] **MANUAL:** Kill app during customer creation
- [ ] **MANUAL:** Kill app during job card creation
- [ ] **MANUAL:** Kill app during invoice generation
- [ ] **MANUAL:** Kill app during payment
- [ ] **MANUAL:** Restart and verify database intact
- [ ] **MANUAL:** No partial/corrupt records

### Error Handling
- [ ] **MANUAL:** Invalid configuration → friendly error
- [ ] **MANUAL:** Missing storage directory → friendly error
- [ ] **MANUAL:** No write permission → friendly error
- [ ] **MANUAL:** No stack traces exposed to user

---

## 21. Backup & Restore (Manual - P1 Critical)

### Backup
- [ ] **MANUAL:** Manual backup button works
- [ ] **MANUAL:** Database file backed up
- [ ] **MANUAL:** Storage files backed up
- [ ] **MANUAL:** Backup timestamp in filename
- [ ] **MANUAL:** Backup location documented
- [ ] **MANUAL:** Backup can be copied to USB/external drive

### Restore
- [ ] **MANUAL:** Create test data
- [ ] **MANUAL:** Create backup
- [ ] **MANUAL:** Delete test data
- [ ] **MANUAL:** Restore backup
- [ ] **MANUAL:** Verify customers restored
- [ ] **MANUAL:** Verify vehicles restored
- [ ] **MANUAL:** Verify job cards restored
- [ ] **MANUAL:** Verify invoices restored
- [ ] **MANUAL:** Verify payments restored
- [ ] **MANUAL:** Verify company profile restored

---

## 22. Windows `.exe` Installer Testing (Manual - Release Blocker)

### Fresh Installation
- [ ] **MANUAL:** Clean Windows machine test
- [ ] **MANUAL:** No PHP installed
- [ ] **MANUAL:** No Composer installed
- [ ] **MANUAL:** No Node.js installed
- [ ] **MANUAL:** `GMS-Setup.exe` runs
- [ ] **MANUAL:** Installer doesn't trigger Windows warnings
- [ ] **MANUAL:** Installation directory correct
- [ ] **MANUAL:** Required runtime files installed
- [ ] **MANUAL:** SQLite database created
- [ ] **MANUAL:** Storage directories created
- [ ] **MANUAL:** Application starts
- [ ] **MANUAL:** Login page loads
- [ ] **MANUAL:** Dashboard loads
- [ ] **MANUAL:** No manual setup required

### Reboot Test
- [ ] **MANUAL:** Restart Windows
- [ ] **MANUAL:** Launch GMS
- [ ] **MANUAL:** Existing data intact
- [ ] **MANUAL:** Application starts normally

### Upgrade Test
- [ ] **MANUAL:** Install v1.0
- [ ] **MANUAL:** Create test data
- [ ] **MANUAL:** Install v1.1 over v1.0
- [ ] **MANUAL:** Existing data preserved
- [ ] **MANUAL:** Migrations run automatically
- [ ] **MANUAL:** New features work
- [ ] **MANUAL:** Old features still work

### Uninstall Test
- [ ] **MANUAL:** Uninstall succeeds
- [ ] **MANUAL:** Application files removed
- [ ] **MANUAL:** User data handling explicit
- [ ] **MANUAL:** Database NOT deleted accidentally
- [ ] **MANUAL:** User warned about data

---

## 23. Real-World End-to-End Test (Manual - Final Gate)

### Complete Workflow on Clean Machine
Using only `GMS-Setup-v1.0.0.exe`:

1. [ ] **MANUAL:** Install GMS
2. [ ] **MANUAL:** Configure company profile
3. [ ] **MANUAL:** Create employee (technician)
4. [ ] **MANUAL:** Create vehicle brand
5. [ ] **MANUAL:** Create vehicle model
6. [ ] **MANUAL:** Create spare parts
7. [ ] **MANUAL:** Create labour masters
8. [ ] **MANUAL:** Create customer
9. [ ] **MANUAL:** Add vehicle to customer
10. [ ] **MANUAL:** Create job card
11. [ ] **MANUAL:** Assign technician
12. [ ] **MANUAL:** Add labour lines
13. [ ] **MANUAL:** Add part lines
14. [ ] **MANUAL:** Generate invoice PDF
15. [ ] **MANUAL:** Receive partial payment
16. [ ] **MANUAL:** Verify outstanding balance
17. [ ] **MANUAL:** Receive final payment
18. [ ] **MANUAL:** Verify paid status
19. [ ] **MANUAL:** Create counter invoice
20. [ ] **MANUAL:** Record counter invoice payment
21. [ ] **MANUAL:** Check inventory
22. [ ] **MANUAL:** Check analytics
23. [ ] **MANUAL:** Restart Windows
24. [ ] **MANUAL:** Launch GMS
25. [ ] **MANUAL:** Verify all data intact
26. [ ] **MANUAL:** Create backup
27. [ ] **MANUAL:** Restore backup on test copy
28. [ ] **MANUAL:** Verify restored data

---

## 24. Documentation Check

- [ ] README.md exists and is accurate
- [ ] Installation instructions clear
- [ ] User guide available
- [ ] Admin guide available
- [ ] Backup/restore instructions documented
- [ ] Troubleshooting guide available
- [ ] API documentation if applicable
- [ ] Changelog maintained

---

## 25. Final Release Checklist

- [ ] Version number finalized
- [ ] CHANGELOG.md updated
- [ ] Git tag created (`v1.0.0`)
- [ ] Production `.env.example` updated
- [ ] All P0 bugs fixed
- [ ] All P1 bugs fixed
- [ ] Installer tested on clean machine
- [ ] Release notes written
- [ ] User documentation complete
- [ ] Support contact information provided

---

## Automated Test Summary

**Run this command to execute all automated checks:**

```bash
php artisan test && vendor/bin/pint --test && npm run build
```

**Current Status:**
- ✅ Unit Tests: Passing
- ✅ Feature Tests: Mostly passing (some inventory stock tests failing - expected)
- ✅ Code Formatting: Passing
- ✅ Frontend Build: Passing

**Known Issues:**
1. Inventory stock tests failing (by design - stock tracking disabled)
2. Some tests expect stock mutations that no longer happen
3. Need to update/remove obsolete stock-related tests

---

## Priority Order for Testing

### Phase 1: Fix Current Blockers (IMMEDIATE)
1. Fix Alpine serialization issues
2. Remove obsolete stock mutation tests
3. Verify zero browser console errors
4. Complete search/filter/sort UI for all modules

### Phase 2: Core Workflow Verification (HIGH PRIORITY)
1. Customer → Vehicle → Job Card workflow
2. Job Card → Labour/Parts → Invoice → Payment
3. Counter Invoice → Payment
4. PDF generation for all document types
5. GST calculations

### Phase 3: Data Integrity (HIGH PRIORITY)
1. Foreign key constraints
2. Soft delete behavior
3. Payment calculations
4. Outstanding balance accuracy

### Phase 4: Production Build (MEDIUM PRIORITY)
1. Production build succeeds
2. Offline functionality
3. Performance with large data sets

### Phase 5: Installer & Distribution (BEFORE v1.0)
1. `.exe` installer creation
2. Clean machine installation
3. Upgrade testing
4. Backup/restore testing
5. Uninstaller behavior

---

**Last Updated:** 2026-09-07  
**Next Review:** After completing remaining view updates
