<?php

use App\Enums\UserRole;
use App\Models\User;

it('blocks a customer from the admin surface', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->get('/admin')->assertForbidden();
});
it('blocks staff from the customer portal', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->get('/portal')->assertForbidden();
});
it('allows manager into admin', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->get('/admin')->assertOk();
});
it('allows customer into portal', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->get('/portal')->assertOk();
});
