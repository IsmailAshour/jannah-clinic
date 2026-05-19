<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in by phone and lands on portal', function () {
    User::factory()->create(['phone' => '0599123456', 'email' => null, 'role' => UserRole::Customer, 'password' => Hash::make('secret12')]);
    $this->post('/login', ['identifier' => '0599123456', 'password' => 'secret12'])
        ->assertRedirect(route('portal.home'));
});
it('logs in staff by email and lands on admin', function () {
    User::factory()->create(['email' => 'mgr@c.com', 'role' => UserRole::Manager, 'password' => Hash::make('secret12')]);
    $this->post('/login', ['identifier' => 'mgr@c.com', 'password' => 'secret12'])
        ->assertRedirect(route('admin.dashboard'));
});
