<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('manager can create a service with image + content', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create([
        'name' => 'C', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true,
    ]);

    $this->actingAs($manager)->post('/admin/catalog/services', [
        'category_id' => $cat->id,
        'name' => 'خدمة جديدة',
        'description' => 'وصف قصير',
        'content' => 'محتوى تفصيلي عن الخدمة.',
        'base_price' => '100.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'is_active' => true,
        'loyalty_enabled' => true,
        'image' => UploadedFile::fake()->image('s.jpg', 600, 400),
    ])->assertRedirect();

    $service = Service::firstWhere('name', 'خدمة جديدة');
    expect($service)->not->toBeNull()
        ->and($service->content)->toBe('محتوى تفصيلي عن الخدمة.')
        ->and($service->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($service->image_path);
});

it('updating with a new image deletes the old one', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'C', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('services', 'public');
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 'X', 'base_price' => '50',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true,
        'image_path' => $oldPath,
    ]);

    $this->actingAs($manager)->put("/admin/catalog/services/{$service->id}", [
        'category_id' => $cat->id, 'name' => 'X',
        'base_price' => '50', 'duration_minutes' => 30,
        'home_service_enabled' => false, 'is_active' => true, 'loyalty_enabled' => true,
        'image' => UploadedFile::fake()->image('new.jpg'),
    ])->assertRedirect();

    $service->refresh();
    expect($service->image_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($service->image_path);
});

it('remove_image flag clears the existing image without uploading a new one', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'C', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);
    $path = UploadedFile::fake()->image('keep-me.jpg')->store('services', 'public');
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 'X', 'base_price' => '50',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true,
        'image_path' => $path,
    ]);

    $this->actingAs($manager)->put("/admin/catalog/services/{$service->id}", [
        'category_id' => $cat->id, 'name' => 'X',
        'base_price' => '50', 'duration_minutes' => 30,
        'home_service_enabled' => false, 'is_active' => true, 'loyalty_enabled' => true,
        'remove_image' => true,
    ])->assertRedirect();

    $service->refresh();
    expect($service->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects non-image uploads', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'C', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);

    $this->actingAs($manager)->post('/admin/catalog/services', [
        'category_id' => $cat->id, 'name' => 'bad', 'base_price' => '0',
        'duration_minutes' => 30, 'is_active' => true, 'loyalty_enabled' => true,
        'image' => UploadedFile::fake()->create('virus.exe', 100),
    ])->assertSessionHasErrors('image');
});
