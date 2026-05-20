<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

beforeEach(function () {
    $this->customerA = User::factory()->create(['role' => UserRole::Customer]);
    $this->customerB = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $this->customerA->id]);
    CustomerProfile::create(['user_id' => $this->customerB->id]);
});

it('customer sees own loyalty page', function () {
    $this->actingAs($this->customerA)->get('/portal/loyalty')->assertOk();
});

it('customer cannot access admin loyalty pages', function () {
    $this->actingAs($this->customerA)
        ->get("/admin/customers/{$this->customerB->id}/loyalty")
        ->assertForbidden();
});

it('manager sees any customer loyalty admin page', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)
        ->get("/admin/customers/{$this->customerA->id}/loyalty")
        ->assertOk();
});

it('doctor and receptionist can read but not adjust', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $r = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($d)->get("/admin/customers/{$this->customerA->id}/loyalty")->assertOk();
    $this->actingAs($r)->get("/admin/customers/{$this->customerA->id}/loyalty")->assertOk();

    $this->actingAs($d)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 10, 'note' => 'x'])
        ->assertForbidden();
    $this->actingAs($r)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 10, 'note' => 'x'])
        ->assertForbidden();
});

it('manager can adjust', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($m)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 50, 'note' => 'هدية'])
        ->assertRedirect();

    expect($this->customerA->customerProfile->fresh()->loyalty_balance)->toBe(50);
});
