<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerVehicleController;
use App\Http\Controllers\VehicleBrandController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LabourMasterController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\CounterInvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::resource('customers', CustomerController::class);

    // Customer-owned vehicles (managed from the Customers page; the standalone
    // Vehicles list page was replaced by the Vehicle Catalog master data page).
    Route::get('vehicles/create', [CustomerVehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [CustomerVehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{vehicle}/edit', [CustomerVehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('vehicles/{vehicle}', [CustomerVehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{vehicle}', [CustomerVehicleController::class, 'destroy'])->name('vehicles.destroy');

    // Vehicle Catalog: master data of brands and models.
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

    Route::resource('employees', EmployeeController::class);
    Route::resource('labour-masters', LabourMasterController::class);
    Route::resource('spare-parts', SparePartController::class);
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('job-cards', [JobCardController::class, 'index'])->name('job-cards.index');
    Route::get('job-cards/create', [JobCardController::class, 'create'])->name('job-cards.create');
    Route::post('job-cards', [JobCardController::class, 'store'])->name('job-cards.store');
    Route::get('job-cards/{jobCard}/assign', [JobCardController::class, 'assign'])->name('job-cards.assign');
    Route::post('job-cards/{jobCard}/assign', [JobCardController::class, 'updateAssign'])->name('job-cards.assign-update');
    Route::get('job-cards/{jobCard}/invoice', [JobCardController::class, 'generateInvoice'])->name('job-cards.invoice');
    Route::get('job-cards/{jobCard}/estimate', [JobCardController::class, 'generateEstimate'])->name('job-cards.estimate');
    Route::get('job-cards/{jobCard}', [JobCardController::class, 'show'])->name('job-cards.show');
    Route::get('job-cards/{jobCard}/edit', [JobCardController::class, 'edit'])->name('job-cards.edit');
    Route::put('job-cards/{jobCard}', [JobCardController::class, 'update'])->name('job-cards.update');
    Route::delete('job-cards/{jobCard}', [JobCardController::class, 'destroy'])->name('job-cards.destroy');
    Route::resource('counter-invoices', CounterInvoiceController::class);
    
    // Payments routes
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    
    // Reports routes (read-only)
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/job-cards', [ReportController::class, 'jobCards'])->name('reports.job_cards');
    Route::get('reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
    Route::get('reports/customers', [ReportController::class, 'customers'])->name('reports.customers');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';