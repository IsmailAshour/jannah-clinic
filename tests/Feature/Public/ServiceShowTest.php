<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('shows an active service detail page', function () {
    $cat = ServiceCategory::create([
        'name' => 'تجميل', 'slug' => 'cat-'.uniqid(), 'color_variant' => 'brand', 'is_active' => true,
    ]);
    $service = Service::create([
        'category_id' => $cat->id,
        'name' => 'تنظيف بشرة',
        'description' => 'وصف قصير',
        'content' => "أوّل فقرة عن الخدمة.\n\nفقرة ثانية تشرح الخطوات.",
        'base_price' => '120.00',
        'duration_minutes' => 30,
        'home_service_enabled' => true,
        'is_active' => true,
        'loyalty_enabled' => true,
    ]);

    $this->get("/services/{$service->id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Public/ServiceShow')
            ->where('service.id', $service->id)
            ->where('service.name', 'تنظيف بشرة')
            ->where('service.content', "أوّل فقرة عن الخدمة.\n\nفقرة ثانية تشرح الخطوات."));
});

it('returns 404 for an inactive service', function () {
    $cat = ServiceCategory::create([
        'name' => 'X', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true,
    ]);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 'مخفية', 'base_price' => '0',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => false,
    ]);

    $this->get("/services/{$service->id}")->assertNotFound();
});

it('eager-loads bookable doctors and related services', function () {
    $cat = ServiceCategory::create(['name' => 'C', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 'A', 'base_price' => '100',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true,
    ]);
    $relatedService = Service::create([
        'category_id' => $cat->id, 'name' => 'B', 'base_price' => '50',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true,
    ]);

    $bookable = DoctorProfile::factory()->create([
        'user_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'is_bookable' => true,
        'rating_average' => '5.0',
    ]);
    $notBookable = DoctorProfile::factory()->create([
        'user_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'is_bookable' => false,
    ]);
    $service->doctors()->attach([$bookable->id, $notBookable->id]);

    $this->get("/services/{$service->id}")
        ->assertInertia(fn ($p) => $p
            ->has('service.doctors', 1)
            ->where('service.doctors.0.id', $bookable->id)
            ->has('related', 1)
            ->where('related.0.id', $relatedService->id));
});
