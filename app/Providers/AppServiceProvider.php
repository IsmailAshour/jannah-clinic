<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\LoyaltyLedger;
use App\Models\MedicalEntry;
use App\Observers\AppointmentObserver;
use App\Policies\AppointmentPolicy;
use App\Policies\LoyaltyLedgerPolicy;
use App\Policies\MedicalEntryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(MedicalEntry::class, MedicalEntryPolicy::class);
        Gate::policy(LoyaltyLedger::class, LoyaltyLedgerPolicy::class);

        // P2: auto-mark Payment as refund_pending when Appointment transitions to
        // Cancelled or Rejected while paid (spec §3 hybrid lifecycle).
        Appointment::observe(AppointmentObserver::class);
    }
}
