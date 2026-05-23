<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AppointmentPhotoController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ClinicSettingController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CoverageAreaController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerLoyaltyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\MedicalEntryController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\AvailableDaysController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:manager,doctor,receptionist'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
        // JSON feed for the dashboard calendar (per-month appointment list).
        Route::get('dashboard/calendar', [DashboardController::class, 'calendar'])->name('dashboard.calendar');
        // Analytics & reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        // Catalog index – readable by all staff
        Route::get('catalog/categories', [ServiceCategoryController::class, 'index'])->name('catalog.categories');
        Route::get('catalog/services', [ServiceController::class, 'index'])->name('catalog.services');

        // Doctors list – readable by all staff
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');

        // Doctor schedule view – readable by all staff
        Route::get('doctors/{doctor}/schedule', [DoctorScheduleController::class, 'editSchedule'])->name('doctors.schedule');
        // Doctor day-view (timeline) – readable by all staff
        Route::get('doctors/{doctor}/day', [DoctorScheduleController::class, 'day'])->name('doctors.day');

        // Before/after photo streaming — read by any staff; uploads/deletes gated below
        Route::get('appointments/{appointment}/photos/{photo}/file', [AppointmentPhotoController::class, 'file'])
            ->name('appointments.photos.file');

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
        // Unified per-appointment page: status controls + payment receipt + photos.
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])
            ->whereNumber('appointment')->name('appointments.show');
        Route::post('appointments/{appointment}/transition', [AppointmentController::class, 'transition'])->name('appointments.transition');

        // Customer admin (Polish-D) — read-only for all staff
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        // AJAX lookup for the admin booking wizard's customer picker.
        // Defined BEFORE {customer} so it's not shadowed by the wildcard.
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        // P4a — Loyalty: any staff role can read
        Route::get('customers/{customer}/loyalty', [CustomerLoyaltyController::class, 'show'])
            ->name('customers.loyalty.show');

        // P3 — Medical Records read (all staff with view policy; receptionist blocked at policy layer)
        Route::get('medical-entries/{entry}/edit', [MedicalEntryController::class, 'edit'])
            ->name('medical-entries.edit');

        // P5a — Notifications (any staff role can read their own feed)
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

        // Payments (P2) — read + receipt file (all staff)
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/receipts/{receipt}/file', [PaymentController::class, 'receiptFile'])->name('payments.receipt-file');

        // Contact messages — read (all staff), mutations gated below
        Route::get('messages', [ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [ContactMessageController::class, 'show'])->name('messages.show');

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
            Route::put('settings/clinic', [ClinicSettingController::class, 'updateClinicInfo'])->name('settings.clinic');
            Route::post('settings/clinic/logo', [ClinicSettingController::class, 'uploadLogo'])->name('settings.clinic.logo');
            Route::put('settings/surcharge', [ClinicSettingController::class, 'updateSurcharge'])->name('settings.surcharge');
            Route::put('settings/bank', [ClinicSettingController::class, 'saveBankInfo'])->name('settings.bank');

            // Customer admin mutations – manager only
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::post('customers/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
            Route::post('customers/{customer}/reset-password', [CustomerController::class, 'resetPassword'])->name('customers.reset-password');

            // P4a — manager only adjust
            Route::post('customers/{customer}/loyalty/adjust', [CustomerLoyaltyController::class, 'adjust'])
                ->name('customers.loyalty.adjust');

            // Payments (P2) — manager only mutations
            Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
            Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
            Route::post('payments/{payment}/mark-refund-pending', [PaymentController::class, 'markRefundPending'])->name('payments.mark-refund-pending');
            Route::post('payments/{payment}/mark-refunded', [PaymentController::class, 'markRefunded'])->name('payments.mark-refunded');

            // Contact messages — manager only mutations
            Route::post('messages/{message}/status', [ContactMessageController::class, 'updateStatus'])->name('messages.status');
            Route::delete('messages/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy');

            // Staff (manager + receptionist) — manager only
            Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
            Route::put('staff/{user}', [StaffController::class, 'update'])->name('staff.update');
            Route::post('staff/{user}/toggle-active', [StaffController::class, 'toggleActive'])->name('staff.toggle-active');
            Route::post('staff/{user}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
            Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
        });

        // P3 — Medical record writes (manager + doctor; gate refined in Policy)
        Route::middleware('role:manager,doctor')->group(function () {
            Route::post('appointments/{appointment}/medical-entry', [MedicalEntryController::class, 'store'])
                ->name('appointments.medical-entry.store');
            Route::get('appointments/{appointment}/medical-entry/create', [MedicalEntryController::class, 'create'])
                ->name('appointments.medical-entry.create');
            Route::put('medical-entries/{entry}', [MedicalEntryController::class, 'update'])
                ->name('medical-entries.update');
        });

        // P3 — Customer medical profile (manager + doctor)
        Route::middleware('role:manager,doctor')->group(function () {
            Route::put('customers/{customer}/profile/medical', [CustomerController::class, 'updateMedicalProfile'])
                ->name('customers.profile.medical.update');

            // Before/after session photos
            Route::post('appointments/{appointment}/photos', [AppointmentPhotoController::class, 'store'])
                ->name('appointments.photos.store');
            Route::delete('appointments/{appointment}/photos/{photo}', [AppointmentPhotoController::class, 'destroy'])
                ->name('appointments.photos.destroy');
        });
    });
