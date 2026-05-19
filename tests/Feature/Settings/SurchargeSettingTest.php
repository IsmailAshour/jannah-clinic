<?php

use App\Domain\Settings\Services\SettingService;
use App\Enums\UserRole;
use App\Models\User;

it('updates the home surcharge percentage', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/surcharge', ['home_surcharge_pct' => '35'])->assertRedirect();
    expect(app(SettingService::class)->get('home_surcharge_pct', 30))->toBe('35');
});

it('rejects a non-numeric or out-of-range percentage', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/surcharge', ['home_surcharge_pct' => '-5'])->assertSessionHasErrors('home_surcharge_pct');
});

it('rejects a non-numeric surcharge percentage', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/surcharge', ['home_surcharge_pct' => 'abc'])->assertSessionHasErrors('home_surcharge_pct');
});
