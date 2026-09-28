<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScalingController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\CheckRole;

// Public Landing Page
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Authentication Routes (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Routes (Admin Scaler Staff & Super Admin)
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Scaling Module Routes
    Route::get('/scaling/reports/pdf', [ScalingController::class, 'exportPdfReport'])->name('scaling.reports.pdf');
    Route::get('/scaling/reports/print', [ScalingController::class, 'printSummaryReport'])->name('scaling.reports.print');
    Route::get('/scaling/{truckLoad}/invoice', [ScalingController::class, 'printInvoice'])->name('scaling.invoice.print');
    Route::get('/scaling/{truckLoad}/invoice/pdf', [ScalingController::class, 'downloadInvoicePdf'])->name('scaling.invoice.pdf');
    
    Route::resource('scaling', ScalingController::class);
    // Live price matrix JSON for scaler form (authenticated users)
    Route::get('/api/price-matrix', [AdminController::class, 'apiPriceMatrix'])->name('api.price-matrix');
    // Get single rate on demand
    Route::get('/api/get-rate', [AdminController::class, 'apiGetRate'])->name('api.get-rate');
});

// Super Admin MASTER Override Routes (Super Admin Only)
Route::middleware(['auth', CheckRole::class . ':super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Dedicated Price Matrix management page
    Route::get('/prices', [AdminController::class, 'prices'])->name('prices.index');
    Route::match(['post', 'put'], '/prices', [AdminController::class, 'updatePriceMatrix'])->name('prices.update');
    Route::post('/prices/add', [AdminController::class, 'addPriceMatrixRow'])->name('prices.add');
    Route::delete('/prices/{priceMatrix}', [AdminController::class, 'destroyPriceMatrixRow'])->name('prices.destroy');
    Route::delete('/prices', [AdminController::class, 'destroyPriceMatrixRow']);
    // Category CRUD for dynamic categories
    Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');
    Route::post('/suppliers', [AdminController::class, 'storeSupplier'])->name('suppliers.store');
    Route::delete('/suppliers/{supplier}', [AdminController::class, 'destroySupplier'])->name('suppliers.destroy');
    Route::post('/staff', [AdminController::class, 'storeStaff'])->name('staff.store');
    Route::post('/staff/{user}/toggle', [AdminController::class, 'toggleUserStatus'])->name('staff.toggle');
    Route::post('/scaling/{truckLoad}/unlock', [AdminController::class, 'unlockScaleSheet'])->name('scaling.unlock');
    Route::post('/scaling/{truckLoad}/status', [AdminController::class, 'updateScaleSheetStatus'])->name('scaling.status');
    Route::delete('/scaling/{truckLoad}', [AdminController::class, 'destroyScaleSheet'])->name('scaling.destroy');
    // Archive Center
    Route::get('/archive', [\App\Http\Controllers\ArchiveController::class, 'index'])->name('archive.index');
    Route::post('/archive/truckloads/{id}/restore', [\App\Http\Controllers\ArchiveController::class, 'restoreTruckLoad'])->name('archive.truckloads.restore');
    Route::post('/archive/truckloads/{id}/force-delete', [\App\Http\Controllers\ArchiveController::class, 'forceDeleteTruckLoad'])->name('archive.truckloads.force_delete');
    Route::post('/archive/suppliers/{id}/restore', [\App\Http\Controllers\ArchiveController::class, 'restoreSupplier'])->name('archive.suppliers.restore');
    Route::post('/archive/suppliers/{id}/force-delete', [\App\Http\Controllers\ArchiveController::class, 'forceDeleteSupplier'])->name('archive.suppliers.force_delete');
});
