<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('manager can reset a customer password and gets the new value via flash', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer, 'password' => Hash::make('old-pass')]);
    $oldHash = $customer->password;

    $resp = $this->actingAs($manager)
        ->from("/admin/customers/{$customer->id}")
        ->post("/admin/customers/{$customer->id}/reset-password");

    $resp->assertRedirect("/admin/customers/{$customer->id}");
    $resp->assertSessionHas('temp_password', fn ($v) => is_string($v) && strlen($v) >= 16);
    $resp->assertSessionHas('success');

    $customer->refresh();
    expect(Hash::check('old-pass', $customer->password))->toBeFalse()
        ->and($customer->password)->not->toBe($oldHash);
});

it('the flashed temp_password actually authenticates the customer', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $resp = $this->actingAs($manager)->post("/admin/customers/{$customer->id}/reset-password");
    $tempPassword = session('temp_password');
    expect($tempPassword)->not->toBeEmpty();

    $customer->refresh();
    expect(Hash::check($tempPassword, $customer->password))->toBeTrue();
});

it('doctor cannot reset a customer password (manager-only)', function () {
    $doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($doctor)
        ->post("/admin/customers/{$customer->id}/reset-password")
        ->assertForbidden();
});

it('returns 404 if target user is not a customer', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $otherStaff = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$otherStaff->id}/reset-password")
        ->assertNotFound();
});
