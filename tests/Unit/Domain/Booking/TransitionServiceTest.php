<?php

use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Domain\Booking\Services\AvailabilityService;
use App\Domain\Booking\Services\PricingService;
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

function makeTransitionFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'عيادة ترانزيشن', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'استشارة', 'base_price' => 150, 'duration_minutes' => 30, 'home_service_enabled' => false]);
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
        'price_at_booking' => '150.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);

    return [$appt, $doc, $svc, $customer, $date];
}

it('transitions requested → confirmed', function () {
    [$appt] = makeTransitionFixture();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::Confirmed);
    expect($result->status)->toBe(AppointmentStatus::Confirmed);
    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Confirmed);
});

it('transitions requested → rejected', function () {
    [$appt] = makeTransitionFixture();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::Rejected);
    expect($result->status)->toBe(AppointmentStatus::Rejected);
});

it('transitions confirmed → completed', function () {
    [$appt] = makeTransitionFixture();
    $appt->status = AppointmentStatus::Confirmed;
    $appt->save();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::Completed);
    expect($result->status)->toBe(AppointmentStatus::Completed);
});

it('transitions confirmed → no_show', function () {
    [$appt] = makeTransitionFixture();
    $appt->status = AppointmentStatus::Confirmed;
    $appt->save();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::NoShow);
    expect($result->status)->toBe(AppointmentStatus::NoShow);
});

it('transitions requested → cancelled with reason', function () {
    [$appt] = makeTransitionFixture();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::Cancelled, 'تعارض في المواعيد');
    expect($result->status)->toBe(AppointmentStatus::Cancelled);
    expect($result->cancellation_reason)->toBe('تعارض في المواعيد');
    expect(Appointment::find($appt->id)->cancellation_reason)->toBe('تعارض في المواعيد');
});

it('transitions confirmed → cancelled', function () {
    [$appt] = makeTransitionFixture();
    $appt->status = AppointmentStatus::Confirmed;
    $appt->save();
    $svc = app(AppointmentTransitionService::class);
    $result = $svc->transition($appt, AppointmentStatus::Cancelled);
    expect($result->status)->toBe(AppointmentStatus::Cancelled);
});

it('throws InvalidTransitionException for completed → confirmed', function () {
    [$appt] = makeTransitionFixture();
    $appt->status = AppointmentStatus::Completed;
    $appt->save();
    $svc = app(AppointmentTransitionService::class);
    expect(fn () => $svc->transition($appt, AppointmentStatus::Confirmed))
        ->toThrow(InvalidTransitionException::class);
});

it('throws InvalidTransitionException for cancelled → requested', function () {
    [$appt] = makeTransitionFixture();
    $appt->status = AppointmentStatus::Cancelled;
    $appt->save();
    $svc = app(AppointmentTransitionService::class);
    expect(fn () => $svc->transition($appt, AppointmentStatus::Requested))
        ->toThrow(InvalidTransitionException::class);
});

it('reschedule creates a new requested appointment and sets old status to rescheduled', function () {
    [$appt, $doc, $svc, $customer, $date] = makeTransitionFixture();

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    // Use the second available slot for reschedule (first is already booked)
    $newStart = isset($slots[1]) ? $slots[1]['start'] : $slots[0]['start']->addMinutes(30);

    $transitionSvc = app(AppointmentTransitionService::class);
    $newAppt = $transitionSvc->reschedule($appt, $newStart);

    // New appointment is requested
    expect($newAppt->status)->toBe(AppointmentStatus::Requested);
    // rescheduled_from_id links back to old appointment
    expect($newAppt->rescheduled_from_id)->toBe($appt->id);
    // Old appointment is rescheduled
    expect(Appointment::find($appt->id)->status)->toBe(AppointmentStatus::Rescheduled);

    // price_at_booking on new appointment is a fresh bcmath quote
    $pricing = app(PricingService::class);
    $expectedQuote = $pricing->quote($doc, $svc, $appt->delivery_mode);
    expect((string) $newAppt->price_at_booking)->toBe($expectedQuote['total']);
});
