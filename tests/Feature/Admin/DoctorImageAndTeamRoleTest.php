<?php

use App\Enums\TeamRole;
use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('manager can create a team member with image + role', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->post('/admin/doctors', [
        'name' => 'سارة',
        'email' => 'nurse@example.com',
        'password' => 'Aa1!aaaaaa',
        'password_confirmation' => 'Aa1!aaaaaa',
        'specialty' => 'عام',
        'team_role' => 'nurse',
        'is_bookable' => true,
        'image' => UploadedFile::fake()->image('s.jpg', 400, 400),
    ])->assertRedirect();

    $profile = DoctorProfile::query()->latest('id')->first();
    expect($profile)->not->toBeNull()
        ->and($profile->team_role)->toBe(TeamRole::Nurse)
        ->and($profile->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($profile->image_path);
});

it('update replaces image and clears old file', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('team', 'public');
    $doctor = DoctorProfile::factory()->create(['image_path' => $oldPath, 'team_role' => 'doctor']);

    $this->actingAs($manager)->put("/admin/doctors/{$doctor->id}", [
        'name' => $doctor->user->name,
        'email' => $doctor->user->email,
        'specialty' => 'تجميل',
        'team_role' => 'physiotherapist',
        'is_bookable' => true,
        'image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertRedirect();

    $doctor->refresh();
    expect($doctor->team_role)->toBe(TeamRole::Physiotherapist)
        ->and($doctor->image_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($doctor->image_path);
});

it('remove_image flag clears the existing image', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $path = UploadedFile::fake()->image('keep.jpg')->store('team', 'public');
    $doctor = DoctorProfile::factory()->create(['image_path' => $path, 'team_role' => 'doctor']);

    $this->actingAs($manager)->put("/admin/doctors/{$doctor->id}", [
        'name' => $doctor->user->name,
        'email' => $doctor->user->email,
        'specialty' => 'عام',
        'team_role' => 'doctor',
        'is_bookable' => true,
        'remove_image' => true,
    ])->assertRedirect();

    $doctor->refresh();
    expect($doctor->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects an invalid team_role value', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->post('/admin/doctors', [
        'name' => 'X',
        'email' => 'x@x.com',
        'password' => 'Aa1!aaaaaa',
        'password_confirmation' => 'Aa1!aaaaaa',
        'specialty' => 'عام',
        'team_role' => 'astronaut',
        'is_bookable' => true,
    ])->assertSessionHasErrors('team_role');
});

it('defaults team_role to doctor when omitted (backward compatibility)', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->post('/admin/doctors', [
        'name' => 'Y',
        'email' => 'y@y.com',
        'password' => 'Aa1!aaaaaa',
        'password_confirmation' => 'Aa1!aaaaaa',
        'specialty' => 'عام',
        'is_bookable' => true,
    ])->assertRedirect();

    $profile = DoctorProfile::query()->latest('id')->first();
    expect($profile->team_role)->toBe(TeamRole::Doctor);
});
