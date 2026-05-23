<?php

use App\Domain\Reminders\Services\ReminderDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Appointment reminders — runs every 5 minutes. The dispatcher's query is
 * cheap (status + indexed start_at window) so the cadence is safe even at
 * scale. Reminders inherit the 5-min granularity; a "T-24h" reminder may
 * arrive anywhere from T-24h to T-23h55m which is well within acceptable
 * notice for a clinic appointment.
 */
Schedule::call(fn () => app(ReminderDispatcher::class)->dispatch())
    ->everyFiveMinutes()
    ->name('appointment-reminders')
    ->withoutOverlapping();
