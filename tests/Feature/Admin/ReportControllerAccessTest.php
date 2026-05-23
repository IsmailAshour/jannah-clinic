<?php

use App\Enums\UserRole;
use App\Models\User;

it('manager can view the reports page', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->get('/admin/reports')->assertOk();
});

it('receptionist is forbidden from the reports page', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($r)->get('/admin/reports')->assertForbidden();
});

it('doctor is forbidden from the reports page', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($d)->get('/admin/reports')->assertForbidden();
});

it('guest is redirected to login from the reports page', function () {
    $this->get('/admin/reports')->assertRedirect('/login');
});
