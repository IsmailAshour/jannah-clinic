<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makeAdminFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'عيادة إدارية', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'فحص', 'base_price' => 150, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));

    return [$doc, $svc, $date];
}

it('receptionist can book on behalf of an existing customer', function () {
    [$doc, $svc, $date] = makeAdminFixture();
    $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($receptionist)
        ->post('/admin/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'center',
            'customer_id' => $customer->id,
        ])
        ->assertRedirect(route('admin.appointments.index'));

    $this->assertDatabaseHas('appointments', [
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'status' => AppointmentStatus::Requested->value,
        'created_by_role' => UserRole::Receptionist->value,
    ]);
});

it('receptionist can quick-create a customer and book', function () {
    [$doc, $svc, $date] = makeAdminFixture();
    $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($receptionist)
        ->post('/admin/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'center',
            'new_customer' => [
                'name' => 'عميل جديد',
                'phone' => '0591234567',
            ],
        ])
        ->assertRedirect(route('admin.appointments.index'));

    $newUser = User::where('phone', '0591234567')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->role)->toBe(UserRole::Customer);

    $this->assertDatabaseHas('customer_profiles', ['user_id' => $newUser->id]);

    $this->assertDatabaseHas('appointments', [
        'customer_id' => $newUser->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'status' => AppointmentStatus::Requested->value,
        'created_by_role' => UserRole::Receptionist->value,
    ]);
});

it('customer is forbidden from admin booking create page', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($customer)
        ->get('/admin/booking')
        ->assertForbidden();
});

it('receptionist cannot book with a customer_id belonging to a non-customer user', function () {
    [$doc, $svc, $date] = makeAdminFixture();
    $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
    $anotherStaff = User::factory()->create(['role' => UserRole::Doctor]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($receptionist)
        ->post('/admin/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'center',
            'customer_id' => $anotherStaff->id,
        ])
        ->assertSessionHasErrors();

    $this->assertDatabaseCount('appointments', 0);
});
