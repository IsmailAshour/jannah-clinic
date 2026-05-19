<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('creates a service under a category', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'تدليك', 'slug' => 'massage', 'color_variant' => 'brand']);
    $this->actingAs($m)->post('/admin/catalog/services', [
        'category_id' => $cat->id, 'name' => 'تدليك علاجي', 'base_price' => 150, 'duration_minutes' => 45,
        'home_service_enabled' => true,
    ])->assertRedirect();
    $s = Service::first();
    expect($s->base_price)->toBe('150.00');
    expect($s->home_service_enabled)->toBeTrue();
});

it('rejects negative price and zero duration', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand']);
    $this->actingAs($m)->post('/admin/catalog/services', [
        'category_id' => $cat->id, 'name' => 'x', 'base_price' => -1, 'duration_minutes' => 0,
    ])->assertSessionHasErrors(['base_price', 'duration_minutes']);
});

it('blocks deleting a category that has services', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand']);
    Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 10, 'duration_minutes' => 30]);
    $this->actingAs($m)->delete("/admin/catalog/categories/{$cat->id}")->assertStatus(409);
});
