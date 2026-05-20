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

it('home includes up to 4 featured services', function () {
    $cat = ServiceCategory::create(['name' => 'c', 'slug' => 's', 'color_variant' => 'brand']);
    Service::create(['category_id' => $cat->id, 'name' => 's1', 'base_price' => '10.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 1, 'is_active' => true]);
    Service::create(['category_id' => $cat->id, 'name' => 's2', 'base_price' => '20.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 2, 'is_active' => true]);
    Service::create(['category_id' => $cat->id, 'name' => 's3', 'base_price' => '30.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 3, 'is_active' => true]);
    Service::create(['category_id' => $cat->id, 'name' => 's4', 'base_price' => '40.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 4, 'is_active' => true]);
    Service::create(['category_id' => $cat->id, 'name' => 's5', 'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 5, 'is_active' => true]);

    $resp = $this->get('/');
    $featured = $resp->viewData('page')['props']['featuredServices'];
    expect(count($featured))->toBe(4);
});

it('home includes a featured doctor by highest rating', function () {
    $u1 = User::factory()->create(['role' => UserRole::Doctor]);
    $u2 = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $u1->id, 'rating_average' => '4.0', 'is_bookable' => true]);
    $top = DoctorProfile::factory()->create(['user_id' => $u2->id, 'rating_average' => '5.0', 'is_bookable' => true]);

    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['featuredDoctor']['id'])->toBe($top->id);
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

it('authed customer sees personalized greeting + nextAppointment', function () {
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
    expect($resp->viewData('page')['props']['greetingName'])->toBe('أحمد')
        ->and($resp->viewData('page')['props']['nextAppointment'])->not->toBeNull();
});
