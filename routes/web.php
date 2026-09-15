<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CounterInvoiceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerVehicleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\LabourMasterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\VehicleBrandController;
use App\Http\Controllers\VehicleModelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Lightweight JSON lookups for the searchable comboboxes. These are
    // deliberately registered BEFORE the resource route so the literal
    // "search" segment is not captured by the {customer} wildcard.
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::get('customers/{customer}/vehicles', [CustomerController::class, 'vehicles'])->name('customers.vehicles');
    Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-delete');
    Route::post('customers/bulk-export', [CustomerController::class, 'bulkExport'])->name('customers.bulk-export');

    Route::resource('customers', CustomerController::class);
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');

    // Customer-owned vehicles (managed from the Customers page; the standalone
    // Vehicles list page was replaced by the Vehicle Catalog master data page).
    Route::get('vehicles/create', [CustomerVehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [CustomerVehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{vehicle}/edit', [CustomerVehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('vehicles/{vehicle}', [CustomerVehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{vehicle}', [CustomerVehicleController::class, 'destroy'])->name('vehicles.destroy');

    // Vehicle Catalog: master data of brands and models.
    Route::post('vehicle-brands/bulk-delete', [VehicleBrandController::class, 'bulkDestroy'])->name('vehicle-brands.bulk-delete');
    Route::post('vehicle-brands/bulk-export', [VehicleBrandController::class, 'bulkExport'])->name('vehicle-brands.bulk-export');
    Route::post('vehicle-models/bulk-delete', [VehicleModelController::class, 'bulkDestroy'])->name('vehicle-models.bulk-delete');
    Route::post('vehicle-models/bulk-export', [VehicleModelController::class, 'bulkExport'])->name('vehicle-models.bulk-export');
    Route::get('vehicle-catalog', [VehicleBrandController::class, 'index'])->name('vehicle-catalog.index');
    Route::get('vehicle-brands/create', [VehicleBrandController::class, 'create'])->name('vehicle-brands.create');
    Route::post('vehicle-brands', [VehicleBrandController::class, 'store'])->name('vehicle-brands.store');
    Route::get('vehicle-brands/{vehicleBrand}/edit', [VehicleBrandController::class, 'edit'])->name('vehicle-brands.edit');
    Route::put('vehicle-brands/{vehicleBrand}', [VehicleBrandController::class, 'update'])->name('vehicle-brands.update');
    Route::delete('vehicle-brands/{vehicleBrand}', [VehicleBrandController::class, 'destroy'])->name('vehicle-brands.destroy');
    Route::get('vehicle-models/create', [VehicleModelController::class, 'create'])->name('vehicle-models.create');
    Route::post('vehicle-models', [VehicleModelController::class, 'store'])->name('vehicle-models.store');
    Route::get('vehicle-models/{vehicleModel}/edit', [VehicleModelController::class, 'edit'])->name('vehicle-models.edit');
    Route::put('vehicle-models/{vehicleModel}', [VehicleModelController::class, 'update'])->name('vehicle-models.update');
    Route::delete('vehicle-models/{vehicleModel}', [VehicleModelController::class, 'destroy'])->name('vehicle-models.destroy');

    Route::post('employees/bulk-delete', [EmployeeController::class, 'bulkDestroy'])->name('employees.bulk-delete');
    Route::post('employees/bulk-export', [EmployeeController::class, 'bulkExport'])->name('employees.bulk-export');
    Route::resource('employees', EmployeeController::class);
    Route::post('labour-masters/bulk-delete', [LabourMasterController::class, 'bulkDestroy'])->name('labour-masters.bulk-delete');
    Route::post('labour-masters/bulk-export', [LabourMasterController::class, 'bulkExport'])->name('labour-masters.bulk-export');
    Route::resource('labour-masters', LabourMasterController::class);
    Route::get('spare-parts/search', [SparePartController::class, 'search'])->name('spare-parts.search');
    Route::post('spare-parts/bulk-delete', [SparePartController::class, 'bulkDestroy'])->name('spare-parts.bulk-delete');
    Route::post('spare-parts/bulk-export', [SparePartController::class, 'bulkExport'])->name('spare-parts.bulk-export');
    Route::resource('spare-parts', SparePartController::class);
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('job-cards', [JobCardController::class, 'index'])->name('job-cards.index');
    Route::post('job-cards/bulk-delete', [JobCardController::class, 'bulkDestroy'])->name('job-cards.bulk-delete');
    Route::post('job-cards/bulk-export', [JobCardController::class, 'bulkExport'])->name('job-cards.bulk-export');
    Route::get('job-cards/create', [JobCardController::class, 'create'])->name('job-cards.create');
    Route::post('job-cards', [JobCardController::class, 'store'])->name('job-cards.store');
    Route::get('job-cards/{jobCard}/assign', [JobCardController::class, 'assign'])->name('job-cards.assign');
    Route::post('job-cards/{jobCard}/assign', [JobCardController::class, 'updateAssign'])->name('job-cards.assign-update');
    // PDF documents mutate audit timestamps (invoice/estimate_generated_at),
    // so they are POST routes, not GET links.
    Route::post('job-cards/{jobCard}/invoice', [JobCardController::class, 'generateInvoice'])->name('job-cards.invoice');
    Route::post('job-cards/{jobCard}/estimate', [JobCardController::class, 'generateEstimate'])->name('job-cards.estimate');
    // WhatsApp share routes
    Route::get('job-cards/{jobCard}/share-invoice-whatsapp', [JobCardController::class, 'shareInvoiceWhatsApp'])->name('job-cards.share-invoice-whatsapp');
    Route::get('job-cards/{jobCard}', [JobCardController::class, 'show'])->name('job-cards.show');
    Route::get('job-cards/{jobCard}/edit', [JobCardController::class, 'edit'])->name('job-cards.edit');
    Route::put('job-cards/{jobCard}', [JobCardController::class, 'update'])->name('job-cards.update');
    Route::delete('job-cards/{jobCard}', [JobCardController::class, 'destroy'])->name('job-cards.destroy');
    Route::post('counter-invoices/bulk-delete', [CounterInvoiceController::class, 'bulkDestroy'])->name('counter-invoices.bulk-delete');
    Route::post('counter-invoices/bulk-export', [CounterInvoiceController::class, 'bulkExport'])->name('counter-invoices.bulk-export');
    Route::get('counter-invoices/{counterInvoice}/pdf', [CounterInvoiceController::class, 'pdf'])->name('counter-invoices.pdf');
    Route::get('counter-invoices/{counterInvoice}/share-whatsapp', [CounterInvoiceController::class, 'shareWhatsApp'])->name('counter-invoices.share-whatsapp');
    Route::resource('counter-invoices', CounterInvoiceController::class);

    // Payments routes
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/bulk-delete', [PaymentController::class, 'bulkDestroy'])->name('payments.bulk-delete');
    Route::post('payments/bulk-export', [PaymentController::class, 'bulkExport'])->name('payments.bulk-export');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // Analytics routes (read-only)
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/revenue', [AnalyticsController::class, 'revenue'])->name('analytics.revenue');
    Route::get('analytics/job-cards', [AnalyticsController::class, 'jobCards'])->name('analytics.job_cards');
    Route::get('analytics/inventory', [AnalyticsController::class, 'inventory'])->name('analytics.inventory');
    Route::get('analytics/payments', [AnalyticsController::class, 'payments'])->name('analytics.payments');
    Route::get('analytics/customers', [AnalyticsController::class, 'customers'])->name('analytics.customers');

    // Import/Export foundation — each module hosts its own import/export
    // actions on its index page; these are the shared workflow routes.
    Route::post('import-export/preview', [ImportExportController::class, 'preview'])->name('import-export.preview');
    Route::get('import-export/preview/{token}', [ImportExportController::class, 'showPreview'])->name('import-export.preview.show');
    Route::post('import-export/confirm/{token}', [ImportExportController::class, 'confirm'])->name('import-export.confirm');
    Route::get('import-export/export', [ImportExportController::class, 'export'])->name('import-export.export');
    Route::get('import-export/template', [ImportExportController::class, 'template'])->name('import-export.template');
    Route::get('import-export/error-report/{token}', [ImportExportController::class, 'errorReport'])->name('import-export.error-report');

    // Company Profile settings (single-record)
    Route::get('company-profile', [CompanyProfileController::class, 'index'])->name('company-profile.index');
    Route::put('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
