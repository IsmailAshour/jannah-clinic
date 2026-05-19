<?php

use App\Enums\UserRole;
use App\Models\ServiceCategory;
use App\Models\User;

it('lets a manager create a category', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/catalog/categories', [
        'name' => 'حجامة', 'slug' => 'hijama', 'color_variant' => 'gold', 'display_order' => 1,
    ])->assertRedirect();
    expect(ServiceCategory::where('slug', 'hijama')->exists())->toBeTrue();
});

it('forbids a customer from creating a category', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/catalog/categories', ['name' => 'x', 'slug' => 'x'])
        ->assertForbidden();
});

it('rejects an invalid color_variant', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/catalog/categories', [
        'name' => 'x', 'slug' => 'x', 'color_variant' => 'purple',
    ])->assertSessionHasErrors('color_variant');
});
