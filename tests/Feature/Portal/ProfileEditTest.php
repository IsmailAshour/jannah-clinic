<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد', 'phone' => '0500000000']);
    CustomerProfile::create(['user_id' => $this->customer->id]);
});

it('customer can view profile edit page', function () {
    $this->actingAs($this->customer)->get('/portal/profile')->assertOk();
});

it('customer updates name + phone + DoB + gender', function () {
    $this->actingAs($this->customer)->put('/portal/profile', [
        'name' => 'أحمد محمود',
        'phone' => '0599999999',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
    ])->assertRedirect();

    expect($this->customer->fresh()->name)->toBe('أحمد محمود')
        ->and($this->customer->fresh()->phone)->toBe('0599999999')
        ->and($this->customer->customerProfile->fresh()->gender)->toBe('male');
});

it('staff cannot reach portal profile (role middleware)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->get('/portal/profile')->assertForbidden();
});

it('validation rejects empty name', function () {
    $this->actingAs($this->customer)->put('/portal/profile', [
        'name' => '',
        'phone' => '0599999999',
    ])->assertSessionHasErrors('name');
});
