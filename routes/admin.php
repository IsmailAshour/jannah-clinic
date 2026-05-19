<?php

use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:manager,doctor,receptionist'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

        // Catalog index – readable by all staff
        Route::get('catalog/categories', [ServiceCategoryController::class, 'index'])->name('catalog.categories');
        Route::get('catalog/services', [ServiceController::class, 'index'])->name('catalog.services');

        // Doctors list – readable by all staff
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');

        // Catalog mutations – manager only
        Route::middleware('role:manager')->group(function () {
            Route::post('catalog/categories', [ServiceCategoryController::class, 'store'])->name('catalog.categories.store');
            Route::put('catalog/categories/{category}', [ServiceCategoryController::class, 'update'])->name('catalog.categories.update');
            Route::delete('catalog/categories/{category}', [ServiceCategoryController::class, 'destroy'])->name('catalog.categories.destroy');

            Route::post('catalog/services', [ServiceController::class, 'store'])->name('catalog.services.store');
            Route::put('catalog/services/{service}', [ServiceController::class, 'update'])->name('catalog.services.update');
            Route::delete('catalog/services/{service}', [ServiceController::class, 'destroy'])->name('catalog.services.destroy');

            // Doctor mutations – manager only
            Route::post('doctors', [DoctorController::class, 'store'])->name('doctors.store');
            Route::put('doctors/{doctor}', [DoctorController::class, 'update'])->name('doctors.update');
            Route::delete('doctors/{doctor}', [DoctorController::class, 'destroy'])->name('doctors.destroy');
        });
    });
