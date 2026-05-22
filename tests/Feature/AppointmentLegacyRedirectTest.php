<?php

use App\Enums\UserRole;
use App\Models\User;

it('redirects legacy /portal/appointments/{id} to the index', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($customer)
        ->get('/portal/appointments/123')
        ->assertRedirect('/portal/appointments');
});

it('shows the admin appointment detail page now that show() exists (legacy redirect superseded 2026-05-21)', function () {
    // The legacy /admin/appointments/{id} route used to redirect to the index;
    // now there's a real Admin\AppointmentController::show. A non-existent ID
    // falls through to 404 via implicit model binding.
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($manager)
        ->get('/admin/appointments/123')
        ->assertNotFound();
});
