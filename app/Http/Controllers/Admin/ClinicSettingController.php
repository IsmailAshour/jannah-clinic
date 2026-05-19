<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'home_surcharge_pct' => app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct')),
        ]);
    }

    public function updateSurcharge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'home_surcharge_pct' => 'required|numeric|between:0,100',
        ]);

        app(SettingService::class)->set('home_surcharge_pct', (string) $validated['home_surcharge_pct']);

        return back()->with('success', 'تم حفظ الإعداد');
    }
}
