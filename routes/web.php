<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DoctorBrowseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ServiceBrowseController;
use App\Http\Controllers\Public\SupportController;
use Illuminate\Support\Facades\Route;

// Public landing — no auth.
Route::get('/', [HomeController::class, 'index'])->name('public.home');
Route::get('/services', [ServiceBrowseController::class, 'index'])->name('public.services');
Route::get('/services/{service}', [ServiceBrowseController::class, 'show'])->name('public.services.show');
Route::get('/doctors', [DoctorBrowseController::class, 'index'])->name('public.doctors');
Route::get('/support', [SupportController::class, 'index'])->name('public.support');
Route::post('/support/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('public.contact.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
