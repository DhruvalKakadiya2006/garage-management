<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LabourMasterController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\CounterInvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::resource('customers', CustomerController::class);
    Route::resource('vehicles', VehicleController::class);
    Route::resource('employees', EmployeeController::class);
    Route::resource('labour-masters', LabourMasterController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('spare-parts', SparePartController::class);
    Route::resource('estimates', EstimateController::class);
    Route::post('estimates/{estimate}/convert-to-job-card', [EstimateController::class, 'convertToJobCard'])->name('estimates.convert-to-job-card');
    Route::resource('job-cards', JobCardController::class);
    Route::resource('counter-invoices', CounterInvoiceController::class);
    
    // Payments routes
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    
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