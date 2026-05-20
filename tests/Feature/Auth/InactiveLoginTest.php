<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('rejects a deactivated user with the same uniform auth.failed error and stays logged out', function () {
    User::factory()->create([
        'email' => 'disabled@example.com',
        'role' => UserRole::Customer,
        'is_active' => false,
        'password' => Hash::make('secret12'),
    ]);

    $this->post('/login', ['identifier' => 'disabled@example.com', 'password' => 'secret12'])
        ->assertSessionHasErrors(['identifier' => trans('auth.failed')]);

    $this->assertGuest();
});

it('still lets an active user log in normally (sanity check)', function () {
    User::factory()->create([
        'email' => 'active@example.com',
        'role' => UserRole::Customer,
        'is_active' => true,
        'password' => Hash::make('secret12'),
    ]);

    $this->post('/login', ['identifier' => 'active@example.com', 'password' => 'secret12'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});
