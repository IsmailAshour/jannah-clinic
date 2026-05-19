<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makePortalFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'عيادة', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'استشارة', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id,
        'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true,
        'morning_start' => '09:00',
        'morning_end' => '12:00',
        'evening_enabled' => false,
        'slot_interval_minutes' => 30,
    ]);

    return [$doc, $svc, $date];
}

it('customer can book a valid centre appointment via portal', function () {
    [$doc, $svc, $date] = makePortalFixture();
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'center',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('appointments', [
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'status' => AppointmentStatus::Requested->value,
        'created_by_role' => UserRole::Customer->value,
    ]);

    expect(session('success'))->not->toBeNull();
});

it('customer gets booking error when slot is unavailable', function () {
    [$doc, $svc, $date] = makePortalFixture();
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $outsideSlot = $date->setTime(15, 0)->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $outsideSlot,
            'delivery_mode' => 'center',
        ])
        ->assertSessionHasErrors('booking');

    $this->assertDatabaseCount('appointments', 0);
});

it('staff user is forbidden from portal booking create page', function () {
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($staff)
        ->get('/portal/booking')
        ->assertForbidden();
});
