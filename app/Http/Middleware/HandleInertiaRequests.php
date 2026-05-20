<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Services\SettingService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // One-shot session flash exposed to Inertia pages. Polish-D needs
            // `temp_password` (shown once on Admin/Customers/Show after create);
            // `success`/`error` provided for future UI surfacing (toasts).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'temp_password' => fn () => $request->session()->get('temp_password'),
            ],
            // Staff-only counters for sidebar badges (P2). Closure is evaluated
            // lazily only when the prop is read; query is gated to staff to
            // avoid leaking COUNTs to guests/customers.
            'adminCounts' => fn () => $request->user()?->isStaff()
                ? ['submitted_payments' => Payment::query()->where('status', 'submitted')->count()]
                : null,
            // P5a — per-user unread notification count. Closure evaluated per request;
            // single COUNT query when share is materialized. Guests get null.
            'notifications' => fn () => $request->user()
                ? ['unread_count' => $request->user()->unreadNotifications()->count()]
                : null,
            // P5b — clinic brand name + logo, editable by manager from /admin/settings.
            'clinic' => fn () => [
                'name' => app(SettingService::class)
                    ->get('clinic_name', config('clinic.name', 'عيادة جنّة')),
                'logo_path' => app(SettingService::class)->get('clinic_logo_path'),
            ],
        ];
    }
}
