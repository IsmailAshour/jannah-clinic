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
        $settings = app(SettingService::class);

        return Inertia::render('Admin/Settings/Index', [
            'home_surcharge_pct' => $settings->get('home_surcharge_pct', config('clinic.home_surcharge_pct')),
            'bank' => [
                'name' => $settings->get('bank_name', config('clinic.bank_name', '')),
                'account_holder' => $settings->get('bank_account_holder', config('clinic.bank_account_holder', '')),
                'iban' => $settings->get('bank_iban', config('clinic.bank_iban', '')),
                'account_number' => $settings->get('bank_account_number', config('clinic.bank_account_number', '')),
            ],
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

    public function saveBankInfo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_iban' => ['nullable', 'string', 'max:64'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
        ]);
        $settings = app(SettingService::class);
        foreach ($data as $key => $value) {
            $settings->set($key, (string) ($value ?? ''));
        }

        return back()->with('success', 'تم حفظ بيانات البنك.');
    }
}
