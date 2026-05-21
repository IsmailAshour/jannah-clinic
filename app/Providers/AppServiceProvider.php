<?php

namespace App\Providers;

use App\Domain\Settings\Services\SettingService;
use App\Models\Appointment;
use App\Models\LoyaltyLedger;
use App\Models\MedicalEntry;
use App\Observers\AppointmentObserver;
use App\Policies\AppointmentPolicy;
use App\Policies\LoyaltyLedgerPolicy;
use App\Policies\MedicalEntryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        // SEO / Open Graph: inject clinic-derived defaults into every Inertia
        // root render. Crawlers (Facebook, Twitter, WhatsApp, Telegram, Google)
        // fetch the bare HTML and read these <meta> tags before any JS runs.
        View::composer('app', function ($view) {
            $settings = app(SettingService::class);
            $clinicName = $settings->get('clinic_name', config('clinic.name', 'عيادة جنّة'));
            $description = $settings->get(
                'clinic_description',
                'احجز موعدك في '.$clinicName.' بسهولة — خدمات طبيّة، عناية بالبشرة، تجميل، وحجامة. فريق طبي مؤهّل وأسعار مناسبة.'
            );
            $logoPath = $settings->get('clinic_logo_path');
            $logoUrl = $logoPath
                ? url('/storage/'.$logoPath)
                : url('/images/clinic-logo.jpg');

            $view->with([
                'seoClinicName' => $clinicName,
                'seoDescription' => $description,
                'seoImageUrl' => $logoUrl,
                'seoUrl' => url()->current(),
                'seoLocale' => 'ar_PS',
            ]);
        });
    }
}
