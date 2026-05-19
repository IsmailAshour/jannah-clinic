<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores a customer avatar', function () {
    Storage::fake('public');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $u->customerProfile()->create([]);
    $this->actingAs($u)
        ->post('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
        ->assertRedirect();
    $path = $u->customerProfile->fresh()->avatar_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('rejects a non-customer avatar upload', function () {
    Storage::fake('public');
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)
        ->post('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
        ->assertForbidden();
});

it('creates a profile if missing and stores avatar', function () {
    Storage::fake('public');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    // deliberately NO customerProfile()->create()
    $this->actingAs($u)
        ->post('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
        ->assertRedirect();
    $profile = $u->fresh()->customerProfile;
    expect($profile)->not->toBeNull();
    expect($profile->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($profile->avatar_path);
});

it('rejects a non-image avatar upload', function () {
    Storage::fake('public');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $u->customerProfile()->create([]);
    $this->actingAs($u)
        ->post('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
});
