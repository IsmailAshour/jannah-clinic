<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
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
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));

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
        ->assertRedirect(route('portal.appointments.index'));

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

it('customer can book a valid home appointment via portal', function () {
    $cat = ServiceCategory::create(['name' => 'عيادة منزلية', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'زيارة منزلية', 'base_price' => 200, 'duration_minutes' => 30, 'home_service_enabled' => true]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $area = HomeServiceCoverageArea::create(['name' => 'رام الله', 'is_active' => true, 'display_order' => 1]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'home',
            'coverage_area_id' => $area->id,
            'address_text' => 'شارع النهضة، مبنى 5',
        ])
        ->assertRedirect(route('portal.appointments.index'));

    $appointment = Appointment::where([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'delivery_mode' => 'home',
        'status' => AppointmentStatus::Requested->value,
        'created_by_role' => UserRole::Customer->value,
    ])->first();

    expect($appointment)->not->toBeNull();

    $this->assertDatabaseHas('service_addresses', [
        'appointment_id' => $appointment->id,
        'coverage_area_id' => $area->id,
        'address_text' => 'شارع النهضة، مبنى 5',
    ]);

    expect(session('success'))->not->toBeNull();
});

it('customer can book a valid online appointment via portal', function () {
    $cat = ServiceCategory::create(['name' => 'استشارة أونلاين', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'متابعة أونلاين', 'base_price' => 80, 'duration_minutes' => 30, 'home_service_enabled' => false, 'online_service_enabled' => true]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'online',
            'whatsapp_phone' => '0599123456',
        ])
        ->assertRedirect(route('portal.appointments.index'));

    $this->assertDatabaseHas('appointments', [
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'delivery_mode' => 'online',
        'whatsapp_phone' => '+970599123456',
        'status' => AppointmentStatus::Requested->value,
    ]);

    $appt = Appointment::where('customer_id', $customer->id)->first();
    $this->assertDatabaseMissing('service_addresses', ['appointment_id' => $appt->id]);
});

it('online booking without whatsapp_phone fails validation', function () {
    $cat = ServiceCategory::create(['name' => 'أونلاين', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'استشارة', 'base_price' => 80, 'duration_minutes' => 30, 'online_service_enabled' => true]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'online',
        ])
        ->assertSessionHasErrors('whatsapp_phone');

    $this->assertDatabaseCount('appointments', 0);
});

it('online booking against a non-online service is rejected', function () {
    $cat = ServiceCategory::create(['name' => 'مركز', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'فحص', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false, 'online_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $start = $slots[0]['start']->toIso8601String();

    $this->actingAs($customer)
        ->post('/portal/booking', [
            'doctor' => $doc->id,
            'service' => $svc->id,
            'start' => $start,
            'delivery_mode' => 'online',
            'whatsapp_phone' => '0599123456',
        ])
        ->assertSessionHasErrors('booking');

    $this->assertDatabaseCount('appointments', 0);
});
