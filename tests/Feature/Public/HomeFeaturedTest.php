<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

it('home features only services flagged is_featured, image-first ordering', function () {
    $cat = ServiceCategory::create(['name' => 'cat', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);
    $withImage = Service::create([
        'category_id' => $cat->id, 'name' => 'with-img', 'base_price' => '100',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true, 'is_featured' => true,
        'image_path' => 'services/foo.jpg', 'display_order' => 9,
    ]);
    $noImage = Service::create([
        'category_id' => $cat->id, 'name' => 'no-img', 'base_price' => '50',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true, 'is_featured' => true,
        'display_order' => 1,
    ]);
    // Active but NOT featured — must be excluded.
    Service::create([
        'category_id' => $cat->id, 'name' => 'not-featured', 'base_price' => '20',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true, 'is_featured' => false,
        'image_path' => 'services/baz.jpg', 'display_order' => 0,
    ]);
    // Featured but inactive — must be excluded.
    Service::create([
        'category_id' => $cat->id, 'name' => 'inactive-featured', 'base_price' => '10',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => false, 'is_featured' => true,
        'image_path' => 'services/bar.jpg', 'display_order' => 0,
    ]);

    $resp = $this->get('/');
    $featured = $resp->viewData('page')['props']['featuredServices'];
    expect(count($featured))->toBe(2)
        ->and($featured[0]['id'])->toBe($withImage->id)
        ->and($featured[1]['id'])->toBe($noImage->id);
});

it('home excludes services where is_featured=false even when they are active and have an image', function () {
    $cat = ServiceCategory::create(['name' => 'cat', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true]);
    Service::create([
        'category_id' => $cat->id, 'name' => 'not-featured-1', 'base_price' => '100',
        'duration_minutes' => 30, 'is_active' => true, 'is_featured' => false,
        'image_path' => 'services/x.jpg',
    ]);
    Service::create([
        'category_id' => $cat->id, 'name' => 'not-featured-2', 'base_price' => '100',
        'duration_minutes' => 30, 'is_active' => true, 'is_featured' => false,
    ]);

    $resp = $this->get('/');
    $featured = $resp->viewData('page')['props']['featuredServices'];
    expect($featured)->toBe([]);
});

it('home includes up to 6 categories ordered by display_order', function () {
    for ($i = 1; $i <= 7; $i++) {
        ServiceCategory::create([
            'name' => "cat-{$i}",
            'slug' => "slug-{$i}",
            'color_variant' => 'brand',
            'display_order' => $i,
            'is_active' => true,
        ]);
    }

    $resp = $this->get('/');
    $categories = $resp->viewData('page')['props']['categories'];
    expect(count($categories))->toBe(6);
});

it('home includes up to 4 bookable team members ordered by display_order', function () {
    $u1 = User::factory()->create(['role' => UserRole::Doctor]);
    $u2 = User::factory()->create(['role' => UserRole::Doctor]);
    $u3 = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $u1->id, 'display_order' => 2, 'is_bookable' => true]);
    DoctorProfile::factory()->create(['user_id' => $u2->id, 'display_order' => 1, 'is_bookable' => true]);
    DoctorProfile::factory()->create(['user_id' => $u3->id, 'display_order' => 3, 'is_bookable' => true]);

    $resp = $this->get('/');
    $doctors = $resp->viewData('page')['props']['doctors'];
    expect(count($doctors))->toBe(3)
        ->and((int) $doctors[0]['user_id'])->toBe($u2->id);
});

it('home includes a tip from config', function () {
    config(['clinic.tips' => ['نصيحة اليوم: اشرب ماء']]);

    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['tip'])->toBe('نصيحة اليوم: اشرب ماء');
});

it('home tip is null when no tips configured', function () {
    config(['clinic.tips' => []]);
    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['tip'])->toBeNull();
});

it('authed customer sees personalized greeting + upcoming appointments', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد']);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true]);
    $doctor->services()->attach($service->id);
    Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => CarbonImmutable::now()->addDay(), 'end_at' => CarbonImmutable::now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => '50.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);

    $resp = $this->actingAs($customer)->get('/');
    $props = $resp->viewData('page')['props'];
    expect($props['greetingName'])->toBe('أحمد')
        ->and(count($props['upcomingAppointments']))->toBe(1);
});

it('guest gets empty upcoming appointments and zero loyalty balance', function () {
    $resp = $this->get('/');
    $props = $resp->viewData('page')['props'];
    expect($props['greetingName'])->toBeNull()
        ->and($props['upcomingAppointments'])->toBe([])
        ->and($props['loyaltyBalance'])->toBe(0);
});
