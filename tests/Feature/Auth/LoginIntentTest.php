<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->password = 'password123!';
});

it('login with intent=booking redirects to /portal/booking with service param', function () {
    $u = User::factory()->create([
        'role' => UserRole::Customer,
        'email' => 'c@example.com',
        'password' => Hash::make($this->password),
    ]);

    $resp = $this->post('/login', [
        'identifier' => 'c@example.com',
        'password' => $this->password,
        'intent' => 'booking',
        'service' => 5,
    ]);

    $resp->assertRedirect();
    expect($resp->headers->get('Location'))
        ->toContain('/portal/booking')
        ->toContain('service=5');
});

it('login without intent redirects to public home', function () {
    $u = User::factory()->create([
        'role' => UserRole::Customer,
        'email' => 'c2@example.com',
        'password' => Hash::make($this->password),
    ]);

    $resp = $this->post('/login', [
        'identifier' => 'c2@example.com',
        'password' => $this->password,
    ]);
    $resp->assertRedirect(route('public.home'));
});

it('staff login still goes to admin dashboard regardless of intent', function () {
    $m = User::factory()->create([
        'role' => UserRole::Manager,
        'email' => 'm@example.com',
        'password' => Hash::make($this->password),
    ]);

    $resp = $this->post('/login', [
        'identifier' => 'm@example.com',
        'password' => $this->password,
        'intent' => 'booking',
        'service' => 5,
    ]);
    $resp->assertRedirect(route('admin.dashboard'));
});
