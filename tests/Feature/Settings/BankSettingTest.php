<?php

use App\Domain\Settings\Services\SettingService;
use App\Enums\UserRole;
use App\Models\User;

it('manager saves bank settings', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($m)->put('/admin/settings/bank', [
        'bank_name' => 'بنك القاهرة عمّان',
        'bank_account_holder' => 'عيادة جنّة',
        'bank_iban' => 'PS12CAIRO00000000000000',
        'bank_account_number' => '123456',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $s = app(SettingService::class);
    expect($s->get('bank_name'))->toBe('بنك القاهرة عمّان');
    expect($s->get('bank_iban'))->toBe('PS12CAIRO00000000000000');
    expect($s->get('bank_account_holder'))->toBe('عيادة جنّة');
    expect($s->get('bank_account_number'))->toBe('123456');
});

it('receptionist cannot save bank settings (403)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($r)->put('/admin/settings/bank', ['bank_name' => 'x'])->assertForbidden();
});

it('settings index exposes the bank prop for the admin form', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    app(SettingService::class)->set('bank_iban', 'PS99TEST00000000000000');

    $resp = $this->actingAs($m)->get('/admin/settings')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['bank']['iban'])->toBe('PS99TEST00000000000000');
});
