<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->cat = ServiceCategory::create(['name' => 'c', 'slug' => 's', 'color_variant' => 'brand']);
});

it('manager creates service with loyalty fields', function () {
    $this->actingAs($this->manager)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'New',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => true,
        'loyalty_redemption_points' => 250,
    ])->assertRedirect();

    $s = Service::firstWhere('name', 'New');
    expect($s->loyalty_enabled)->toBeTrue()
        ->and($s->loyalty_redemption_points)->toBe(250);
});

it('rejects negative redemption points', function () {
    $this->actingAs($this->manager)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'Bad',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => true,
        'loyalty_redemption_points' => -10,
    ])->assertSessionHasErrors('loyalty_redemption_points');
});

it('receptionist cannot configure service loyalty', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'X',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
    ])->assertForbidden();
});

it('manager updates service loyalty fields', function () {
    $svc = Service::create([
        'category_id' => $this->cat->id, 'name' => 'Existing',
        'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => false, 'loyalty_redemption_points' => null,
    ]);

    $this->actingAs($this->manager)->put("/admin/catalog/services/{$svc->id}", [
        'category_id' => $this->cat->id,
        'name' => 'Existing',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => true,
        'loyalty_redemption_points' => 100,
    ])->assertRedirect();

    expect($svc->fresh()->loyalty_enabled)->toBeTrue()
        ->and($svc->fresh()->loyalty_redemption_points)->toBe(100);
});

it('manager can create a service with loyalty DISABLED', function () {
    $this->actingAs($this->manager)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'NoLoyalty',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => false,
        'loyalty_redemption_points' => null,
    ])->assertRedirect();

    $s = Service::firstWhere('name', 'NoLoyalty');
    expect($s->loyalty_enabled)->toBeFalse()
        ->and($s->loyalty_redemption_points)->toBeNull();
});

it('manager toggling loyalty OFF on update clears redemption points', function () {
    $svc = Service::create([
        'category_id' => $this->cat->id, 'name' => 'WasLoyal',
        'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => true, 'loyalty_redemption_points' => 500,
    ]);

    $this->actingAs($this->manager)->put("/admin/catalog/services/{$svc->id}", [
        'category_id' => $this->cat->id,
        'name' => 'WasLoyal',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => false,
        'loyalty_redemption_points' => null,
    ])->assertRedirect();

    expect($svc->fresh()->loyalty_enabled)->toBeFalse()
        ->and($svc->fresh()->loyalty_redemption_points)->toBeNull();
});
