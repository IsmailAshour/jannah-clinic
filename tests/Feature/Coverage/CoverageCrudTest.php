<?php

use App\Enums\UserRole;
use App\Models\HomeServiceCoverageArea;
use App\Models\User;

it('lets a manager add a coverage area', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/coverage', ['name' => 'رام الله', 'is_active' => true])->assertRedirect();
    expect(HomeServiceCoverageArea::where('name', 'رام الله')->exists())->toBeTrue();
});

it('forbids a customer', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/coverage', ['name' => 'x'])->assertForbidden();
});
