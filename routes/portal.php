<?php

use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Portal\BookingController;
use App\Http\Controllers\Portal\ServiceBrowseController;
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

        // Booking wizard — customer self-booking
        Route::get('booking', [BookingController::class, 'create'])->name('booking.create');
        Route::post('booking', [BookingController::class, 'store'])->name('booking.store');
    });
