<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ClinicSettingController;
use App\Http\Controllers\Admin\CoverageAreaController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\AvailableDaysController;
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

        // Doctor schedule view – readable by all staff
        Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'editSchedule'])->name('doctors.schedule');

        // Coverage areas – readable by all staff
        Route::get('coverage', [CoverageAreaController::class, 'index'])->name('coverage.index');

        // Settings – readable by all staff
        Route::get('settings', [ClinicSettingController::class, 'index'])->name('settings.index');

        // Availability – readable by all staff
        Route::get('availability', AvailabilityController::class)->name('availability');
        Route::get('availability/days', AvailableDaysController::class)->name('availability.days');

        // Booking wizard — admin on-behalf booking (all staff)
        Route::get('booking', [BookingController::class, 'create'])->name('booking.create');
        Route::post('booking', [BookingController::class, 'store'])->name('booking.store');

        // Appointment management (all staff)
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('appointments/{appointment}/transition', [AppointmentController::class, 'transition'])->name('appointments.transition');

        // Customer admin (Polish-D) — read-only for all staff
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

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

            // Schedule mutations – manager only
            Route::put('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'saveSchedule'])->name('doctors.schedule.save');
            Route::post('doctors/{doctor}/exceptions', [DoctorScheduleController::class, 'addException'])->name('doctors.exceptions.add');
            Route::delete('doctors/{doctor}/exceptions/{exception}', [DoctorScheduleController::class, 'deleteException'])->name('doctors.exceptions.delete');

            // Coverage area mutations – manager only
            Route::post('coverage', [CoverageAreaController::class, 'store'])->name('coverage.store');
            Route::put('coverage/{area}', [CoverageAreaController::class, 'update'])->name('coverage.update');
            Route::delete('coverage/{area}', [CoverageAreaController::class, 'destroy'])->name('coverage.destroy');

            // Settings mutations – manager only
            Route::put('settings/surcharge', [ClinicSettingController::class, 'updateSurcharge'])->name('settings.surcharge');

            // Customer admin mutations – manager only
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::post('customers/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
        });
    });
