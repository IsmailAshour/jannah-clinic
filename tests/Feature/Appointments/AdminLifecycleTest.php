<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makeAdminLifecycleFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'عيادة إدارية', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'فحص', 'base_price' => 120, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
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
    $slot = app(AvailabilityService::class)->slotsFor($doc, $svc, $date)[0];

    $appt = Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => $slot['start'],
        'end_at' => $slot['end'],
        'status' => AppointmentStatus::Requested,
        'price_at_booking' => '120.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);

    return [$appt, $svc, $doc, $customer];
}

it('staff can confirm a requested appointment', function () {
    [$appt] = makeAdminLifecycleFixture();
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($staff)
        ->post("/admin/appointments/{$appt->id}/transition", ['status' => 'confirmed'])
        ->assertRedirect();

    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Confirmed);
});

it('staff can complete a confirmed appointment', function () {
    [$appt] = makeAdminLifecycleFixture();
    $appt->status = AppointmentStatus::Confirmed;
    $appt->save();
    $staff = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($staff)
        ->post("/admin/appointments/{$appt->id}/transition", ['status' => 'completed'])
        ->assertRedirect();

    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Completed);
});

it('illegal transition (completed → confirmed) returns appointment error and leaves status unchanged', function () {
    [$appt] = makeAdminLifecycleFixture();
    $appt->status = AppointmentStatus::Completed;
    $appt->save();
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($staff)
        ->post("/admin/appointments/{$appt->id}/transition", ['status' => 'confirmed'])
        ->assertSessionHasErrors('appointment');

    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Completed);
});

it('customer cannot access admin appointments index', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($customer)
        ->get('/admin/appointments')
        ->assertForbidden();
});
