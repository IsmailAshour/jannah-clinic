<?php

use App\Enums\UserRole;
use App\Models\User;

it('redirects legacy /portal/appointments/{id} to the index', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($customer)
        ->get('/portal/appointments/123')
        ->assertRedirect('/portal/appointments');
});

it('redirects legacy /admin/appointments/{id} to the index', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($manager)
        ->get('/admin/appointments/123')
        ->assertRedirect('/admin/appointments');
});
