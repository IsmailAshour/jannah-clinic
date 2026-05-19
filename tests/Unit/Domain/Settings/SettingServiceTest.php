<?php

use App\Domain\Settings\Services\SettingService;
use App\Models\Setting;

it('falls back to config default when no row exists', function () {
    expect(app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct')))
        ->toBe('30');
});

it('returns the stored value over the config default', function () {
    Setting::create(['key' => 'home_surcharge_pct', 'value' => '25']);
    expect(app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct')))
        ->toBe('25');
});

it('sets (upserts) a value', function () {
    $svc = app(SettingService::class);
    $svc->set('home_surcharge_pct', '40');
    $svc->set('home_surcharge_pct', '45');
    expect(Setting::where('key', 'home_surcharge_pct')->count())->toBe(1);
    expect($svc->get('home_surcharge_pct', 0))->toBe('45');
});

it('always returns a string from get, even round-tripped', function () {
    $svc = app(SettingService::class);
    $svc->set('rt_pct', '50');
    expect($svc->get('rt_pct', 0))->toBeString()->toBe('50');
    // genuine null passthrough preserved
    expect($svc->get('missing_key'))->toBeNull();
});
