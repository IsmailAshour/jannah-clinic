<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('shows the services page to a customer', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $cat = ServiceCategory::create(['name' => 'حجامة', 'slug' => 'hijama', 'color_variant' => 'gold']);
    Service::create(['category_id' => $cat->id, 'name' => 'حجامة جافة', 'base_price' => 80, 'duration_minutes' => 30, 'is_active' => true]);
    $this->actingAs($c)->get('/portal/services')
        ->assertInertia(fn ($p) => $p->component('Portal/Services/Index'));
});

it('forbids staff from the customer services page', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->get('/portal/services')->assertForbidden();
});
