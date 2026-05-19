<?php

use App\Enums\UserRole;

it('classifies staff vs customer', function () {
    expect(UserRole::Manager->isStaff())->toBeTrue();
    expect(UserRole::Doctor->isStaff())->toBeTrue();
    expect(UserRole::Receptionist->isStaff())->toBeTrue();
    expect(UserRole::Customer->isStaff())->toBeFalse();
});
