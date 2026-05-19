<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:customer'])
    ->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', fn () => Inertia::render('Portal/Home'))->name('home');
    });
