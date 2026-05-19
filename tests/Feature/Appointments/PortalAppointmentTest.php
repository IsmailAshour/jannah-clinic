<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makePortalApptFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'عيادة بوابة', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'كشف', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $slot = $slots[0];

    $appt = Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => $slot['start'],
        'end_at' => $slot['end'],
        'status' => AppointmentStatus::Requested,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);

    return [$appt, $doc, $svc, $customer, $date, $slots];
}

it('customer can cancel their own appointment with a reason', function () {
    [$appt, , , $customer] = makePortalApptFixture();

    $this->actingAs($customer)
        ->post("/portal/appointments/{$appt->id}/cancel", ['reason' => 'لا أستطيع الحضور'])
        ->assertRedirect();

    $fresh = Appointment::find($appt->id);
    expect($fresh->status)->toBe(AppointmentStatus::Cancelled);
    expect($fresh->cancellation_reason)->toBe('لا أستطيع الحضور');
});

it('customer cannot cancel another customers appointment', function () {
    [$appt] = makePortalApptFixture();
    $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($otherCustomer)
        ->post("/portal/appointments/{$appt->id}/cancel", ['reason' => 'محاولة غير مصرحة'])
        ->assertForbidden();
});

it('customer can reschedule their own appointment to a valid new slot', function () {
    [$appt, $doc, $svc, $customer, $date, $slots] = makePortalApptFixture();

    // Fixture seeds slotRange('09:00', 6) and books $slots[0]; $slots is
    // computed before the booking so all 6 grid slots are present.
    expect($slots)->toHaveCount(6);
    $newSlot = $slots[1];

    $newStart = $newSlot['start']->toIso8601String();

    $this->actingAs($customer)
        ->post("/portal/appointments/{$appt->id}/reschedule", ['start' => $newStart])
        ->assertRedirect();

    // Old appointment is now rescheduled
    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Rescheduled);

    // A new requested appointment was created linked to the old one
    $newAppt = Appointment::where('rescheduled_from_id', $appt->id)->first();
    expect($newAppt)->not->toBeNull();
    expect($newAppt->status)->toBe(AppointmentStatus::Requested);
});

it('customer cancelling a terminal appointment gets a friendly error and status is unchanged', function () {
    [$appt, , , $customer] = makePortalApptFixture();
    $appt->status = AppointmentStatus::Completed;
    $appt->save();

    $this->actingAs($customer)
        ->post("/portal/appointments/{$appt->id}/cancel", ['reason' => 'محاولة إلغاء'])
        ->assertSessionHasErrors('appointment');

    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Completed);
});

it('staff user cannot access portal appointments index', function () {
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($staff)
        ->get('/portal/appointments')
        ->assertForbidden();
});
