<?php

use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\AvailableDaysController;
use App\Http\Controllers\Portal\AppointmentController;
use App\Http\Controllers\Portal\BookingController;
use App\Http\Controllers\Portal\LoyaltyController;
use App\Http\Controllers\Portal\MedicalRecordController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\Portal\ServiceBrowseController;
use App\Http\Controllers\Portal\SettingsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:customer'])
    ->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', fn () => Inertia::render('Portal/Home'))->name('home');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

        Route::get('services', [ServiceBrowseController::class, 'index'])->name('services.index');

        // Availability
        Route::get('availability', AvailabilityController::class)->name('availability');
        Route::get('availability/days', AvailableDaysController::class)->name('availability.days');

        // Booking wizard — customer self-booking
        Route::get('booking', [BookingController::class, 'create'])->name('booking.create');
        Route::post('booking', [BookingController::class, 'store'])->name('booking.store');

        // My appointments — customer
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        // Legacy notification links generated before 2026-05-21 used /portal/appointments/{id}.
        // No per-appointment show page exists; redirect to index so old in-DB notifications resolve.
        Route::get('appointments/{appointment}', fn () => redirect()->route('portal.appointments.index'))
            ->whereNumber('appointment')->name('appointments.show');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');

        // Payment (per-appointment) — view + upload receipt
        Route::get('appointments/{appointment}/payment', [PaymentController::class, 'show'])->name('appointments.payment');
        Route::post('appointments/{appointment}/payment/upload', [PaymentController::class, 'upload'])->name('appointments.payment.upload');

        // P3 — Medical Record (customer view of own record)
        Route::get('medical-record', [MedicalRecordController::class, 'index'])->name('medical-record.index');
        Route::get('medical-record/entries/{entry}', [MedicalRecordController::class, 'show'])->name('medical-record.show');

        // P5a — Notifications (customer feed)
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

        // P4a — Loyalty (customer feed)
        Route::get('loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');

        // P5b — customer profile
        Route::get('profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [PortalProfileController::class, 'update'])->name('profile.update');

        // P5b — customer settings
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    });
