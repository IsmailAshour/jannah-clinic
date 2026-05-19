<?php

use App\Enums\UserRole;

it('classifies staff vs customer', function () {
    expect(UserRole::Manager->isStaff())->toBeTrue();
    expect(UserRole::Doctor->isStaff())->toBeTrue();
    expect(UserRole::Receptionist->isStaff())->toBeTrue();
    expect(UserRole::Customer->isStaff())->toBeFalse();
});

it('identifies the customer role', function () {
    expect(UserRole::Customer->isCustomer())->toBeTrue();
    expect(UserRole::Manager->isCustomer())->toBeFalse();
    expect(UserRole::Doctor->isCustomer())->toBeFalse();
    expect(UserRole::Receptionist->isCustomer())->toBeFalse();
});
