<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->customer = User::factory()->create([
        'role' => UserRole::Customer,
        'password' => Hash::make('oldpassword123'),
    ]);
});

it('customer can view settings page', function () {
    $this->actingAs($this->customer)->get('/portal/settings')->assertOk();
});

it('customer changes password with correct current password', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertRedirect();

    expect(Hash::check('newpassword456', $this->customer->fresh()->password))->toBeTrue();
});

it('rejects password change with wrong current password', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('oldpassword123', $this->customer->fresh()->password))->toBeTrue();
});

it('rejects password change with mismatched confirmation', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});

it('password change dispatches a security notification', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertRedirect();

    $n = $this->customer->notifications()->latest()->first();
    expect($n)->not->toBeNull()
        ->and($n->data['title'])->toContain('كلمة المرور');
});
