<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

it('manager updates customer medical profile and writes audit', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $resp = $this->actingAs($m)->put("/admin/customers/{$customer->id}/profile/medical", [
        'chronic_conditions' => 'diabetes type 2',
        'allergies' => 'penicillin',
    ]);

    $resp->assertRedirect()->assertSessionHasNoErrors();
    $profile = CustomerProfile::firstWhere('user_id', $customer->id);
    expect($profile->chronic_conditions)->toBe('diabetes type 2')
        ->and($profile->allergies)->toBe('penicillin');
});

it('doctor can update customer medical profile', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($d)->put("/admin/customers/{$customer->id}/profile/medical", [
        'chronic_conditions' => 'asthma',
    ])->assertRedirect()->assertSessionHasNoErrors();
});

it('receptionist cannot update medical profile', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($r)->put("/admin/customers/{$customer->id}/profile/medical", [
        'chronic_conditions' => 'x',
    ])->assertForbidden();
});
