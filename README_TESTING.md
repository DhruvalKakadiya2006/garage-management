# GMS Testing Guide

## Quick Start

### Run All Automated Checks
```powershell
.\run-automated-checks.ps1
```

This script will check:
- ✅ PHP syntax
- ✅ Code style (Laravel Pint)
- ✅ Debug statements
- ✅ Console.log statements
- ✅ Security vulnerabilities
- ✅ Environment configuration
- ✅ Database migrations
- ✅ PHPUnit tests (440 tests)
- ✅ Frontend build
- ✅ Dependencies

---

## Current Status (2026-09-07)

### ✅ Automated Tests: PASSING
- **440 PHPUnit tests** passed
- **1,835 assertions** passed
- **0 security vulnerabilities** found
- **231 files** formatted correctly
- **65 migrations** working
- **Frontend build** successful

### ⚠️ Manual Testing: IN PROGRESS
- **2 of 8 views** updated with search/filter UI
  - ✅ Customers
  - ✅ Job Cards
  - ⏳ Inventory
  - ⏳ Counter Invoices
  - ⏳ Payments
  - ⏳ Employees
  - ⏳ Vehicle Catalog

---

## Files Created

1. **`PRODUCTION_READINESS_CHECKLIST.md`** - Complete 300+ item checklist
2. **`AUTOMATED_TEST_RESULTS.md`** - Results from automated testing
3. **`run-automated-checks.ps1`** - PowerShell script to run all checks
4. **`README_TESTING.md`** - This file

---

## Testing Phases

### Phase 1: Automated Testing ✅ COMPLETE
All automated checks pass. Run `.\run-automated-checks.ps1` to verify.

### Phase 2: Manual Browser Testing 🔄 IN PROGRESS
1. Start development server: `php artisan serve`
2. Visit: http://127.0.0.1:8000
3. Open browser DevTools (F12)
4. Follow **Section 5-13** in `PRODUCTION_READINESS_CHECKLIST.md`

**Critical tests:**
- [ ] Browser console has 0 errors on all pages
- [ ] Customer → Vehicle → Job Card workflow works
- [ ] Search bars appear on Customers and Job Cards
- [ ] Filters work correctly
- [ ] Sorting works correctly
- [ ] PDF generation works
- [ ] Payment calculations correct
- [ ] GST calculations correct

### Phase 3: Data Integrity Testing ⏳ PENDING
- [ ] Foreign key constraints prevent invalid deletions
- [ ] Soft deletes work correctly
- [ ] No orphaned records
- [ ] Payment status calculations accurate

### Phase 4: Production Build ⏳ PENDING
```bash
# Test production build
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Phase 5: Offline Testing ⏳ PENDING
- [ ] Disconnect internet
- [ ] Test all core workflows
- [ ] PDFs generate offline

### Phase 6: Windows Installer ⏳ PENDING
- [ ] Create `.exe` installer
- [ ] Test on clean Windows machine
- [ ] Test upgrade path
- [ ] Test backup/restore

---

## Known Issues

### Non-Blocking Issues
1. **13 skipped tests** - Stock-related tests (stock tracking intentionally disabled)
   - These tests need to be updated or removed
   - Does not block release

2. **Environment set to development**
   - `APP_ENV=local`
   - `APP_DEBUG=true`
   - Change to production values before release

3. **Remaining 6 views need search/filter UI**
   - Backend logic is complete
   - Just need to add Blade components to views

### No Blocking Issues (P0/P1)
✅ No critical bugs found in automated tests

---

## Browser Testing Checklist (Your Part)

### 1. Open Every Page and Check Console
Visit each page and press F12 to open DevTools Console:

- [ ] Dashboard - http://127.0.0.1:8000/dashboard
- [ ] Customers - http://127.0.0.1:8000/customers
- [ ] Job Cards - http://127.0.0.1:8000/job-cards
- [ ] Vehicle Catalog - http://127.0.0.1:8000/vehicle-catalog
- [ ] Counter Invoice - http://127.0.0.1:8000/counter-invoices
- [ ] Payments - http://127.0.0.1:8000/payments
- [ ] Inventory - http://127.0.0.1:8000/inventory
- [ ] Analytics - http://127.0.0.1:8000/analytics
- [ ] Employees - http://127.0.0.1:8000/employees
- [ ] Company Profile - http://127.0.0.1:8000/company-profile

**For each page, verify:**
- ✅ 0 errors in console (red messages)
- ✅ Page loads correctly
- ✅ No missing assets (404s)
- ✅ Alpine.js works (no expression errors)

### 2. Test Search/Filter/Sort (Where Implemented)

**Customers Page:**
- [ ] Search by customer name works
- [ ] Search by mobile number works
- [ ] Outstanding filter works (All / Has Outstanding / No Outstanding)
- [ ] Sort by Display Name works (click column header)
- [ ] Sort by Outstanding works
- [ ] Sort by Total Visits works
- [ ] Pagination preserves search/filters
- [ ] Clear button works
- [ ] Active filters bar shows current filters

**Job Cards Page:**
- [ ] Search by job card number works
- [ ] Search by customer name works
- [ ] Search by vehicle registration works
- [ ] Date range filter works
- [ ] Technician filter works
- [ ] Status filter works
- [ ] Vehicle brand filter works
- [ ] Payment status filter works
- [ ] Job type filter works
- [ ] Multiple filters work together
- [ ] Sorting works
- [ ] Clear button works

### 3. Critical Workflow Test

**Customer → Vehicle → Job Card:**
1. [ ] Go to Customers page
2. [ ] Click "Add Customer"
3. [ ] Fill in customer details
4. [ ] Save customer
5. [ ] Click "Add Vehicle" for that customer
6. [ ] Fill in vehicle details
7. [ ] Save vehicle
8. [ ] Click "Create Job Card" for that customer
9. [ ] **CRITICAL**: Vehicle dropdown should appear
10. [ ] Select the vehicle
11. [ ] Fill in job card details
12. [ ] Save job card
13. [ ] Verify no JavaScript errors in console
14. [ ] Verify job card created successfully

### 4. Invoice & Payment Test
1. [ ] Open a job card
2. [ ] Click "Assign / Add Items"
3. [ ] Add at least one labour line
4. [ ] Add at least one part line
5. [ ] Save
6. [ ] Click "Generate Invoice"
7. [ ] PDF downloads successfully
8. [ ] PDF has correct calculations
9. [ ] Record a payment
10. [ ] Payment status updates correctly

### 5. GST Calculation Edge Cases
Test invoices with:
- [ ] No discount → Total correct
- [ ] 10% discount → GST calculated on discounted amount
- [ ] Multiple items with different GST rates
- [ ] Decimal quantities (e.g., 2.5 units)
- [ ] Decimal prices (e.g., ₹99.95)

### 6. Data Integrity Tests
Try to delete (should be blocked):
- [ ] Customer with vehicles
- [ ] Customer with job cards
- [ ] Vehicle with job cards
- [ ] Spare part used in invoice
- [ ] Labour master used in job card
- [ ] Vehicle model in use
- [ ] Vehicle brand in use

**Expected:** Friendly error message, not silent failure

---

## What to Report

### If You Find a Bug
Create an issue with:
1. **Priority** (P0/P1/P2/P3)
2. **Page/Module** where bug occurs
3. **Steps to reproduce**
4. **Expected behavior**
5. **Actual behavior**
6. **Browser console errors** (if any)
7. **Screenshots** (if helpful)

### Bug Priority Guide
- **P0**: Data loss, security issue, app crashes, cannot start
- **P1**: Core workflow broken, incorrect calculations, PDF fails
- **P2**: Significant but has workaround
- **P3**: Cosmetic, minor UX issue

---

## Before Calling it v1.0

### Must Have (Release Blockers)
- [ ] 0 P0 bugs
- [ ] 0 P1 bugs
- [ ] All 8 modules have search/filter/sort UI
- [ ] Browser console clean on all pages
- [ ] Customer → Vehicle → Job Card workflow works perfectly
- [ ] PDF generation works
- [ ] GST calculations correct
- [ ] Payment calculations correct
- [ ] Windows `.exe` installer works on clean machine

### Nice to Have (Can defer to v1.1)
- [ ] Performance optimization for 1000+ records
- [ ] Advanced reporting
- [ ] Backup/restore UI
- [ ] Automatic updates
- [ ] Mobile responsive design

---

## Quick Commands

```bash
# Start development server
php artisan serve

# Run all automated checks
.\run-automated-checks.ps1

# Run only tests
php artisan test

# Format code
vendor/bin/pint

# Build frontend
npm run build

# Fresh migration
php artisan migrate:fresh

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

## Getting Help

1. Review `PRODUCTION_READINESS_CHECKLIST.md` for detailed test procedures
2. Check `AUTOMATED_TEST_RESULTS.md` for test status
3. Run `.\run-automated-checks.ps1` to verify code quality

---

**Last Updated:** 2026-09-07  
**Status:** Automated tests passing, ready for manual browser testing
