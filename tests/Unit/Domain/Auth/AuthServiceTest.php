<?php

use App\Domain\Auth\Services\AuthService;
use App\Enums\UserRole;
use App\Models\User;

it('resolves a user by email or phone', function () {
    $u = User::factory()->create(['email' => 'a@b.com', 'phone' => '0599000111', 'role' => UserRole::Customer]);
    $svc = app(AuthService::class);
    expect($svc->resolveByIdentifier('a@b.com')->id)->toBe($u->id);
    expect($svc->resolveByIdentifier('0599000111')->id)->toBe($u->id);
    expect($svc->resolveByIdentifier('missing'))->toBeNull();
});

it('registers a customer with a profile', function () {
    $svc = app(AuthService::class);
    $u = $svc->registerCustomer(['name' => 'X', 'email' => 'x@y.com', 'phone' => null, 'password' => 'secret12']);
    expect($u->role)->toBe(UserRole::Customer);
    expect($u->customerProfile)->not->toBeNull();
});
