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

it('forbids a doctor and receptionist from creating a category', function () {
    foreach ([UserRole::Doctor, UserRole::Receptionist] as $r) {
        $u = User::factory()->create(['role' => $r]);
        $this->actingAs($u)->post('/admin/catalog/categories', ['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand'])
            ->assertForbidden();
    }
});

it('still lets any staff view the catalog list', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->get('/admin/catalog/categories')->assertOk();
});

it('allows updating a category keeping its own slug (unique ignores self)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'حجامة', 'slug' => 'hijama', 'color_variant' => 'brand']);
    $this->actingAs($m)->put("/admin/catalog/categories/{$cat->id}", [
        'name' => 'حجامة محدّثة', 'slug' => 'hijama', 'color_variant' => 'gold',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($cat->fresh()->name)->toBe('حجامة محدّثة');
});
