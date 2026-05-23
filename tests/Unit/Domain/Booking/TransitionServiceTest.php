<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Domain\Booking\Services\AvailabilityService;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\PricingService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
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
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));

    $slot = app(AvailabilityService::class)->slotsFor($doc, $svc, $date)[0];

    $appt = mkAppointment([
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

    // makeTransitionFixture books $slots[0]; recomputing here excludes that
    // slot, so the 6-slot grid (slotRange('09:00', 6)) yields 5 remaining.
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect($slots)->toHaveCount(5);
    $newStart = $slots[1]['start'];

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

it('reschedule of a home-delivery appointment carries the ServiceAddress to the new appointment', function () {
    // Build a home-enabled fixture from scratch (makeTransitionFixture uses center mode)
    $cat = ServiceCategory::create(['name' => 'منزلي ترانزيشن', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'زيارة منزلية', 'base_price' => 200, 'duration_minutes' => 30, 'home_service_enabled' => true]);
    $doc = DoctorProfile::factory()->create(['is_bookable' => true]);
    $doc->services()->attach($svc->id);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $area = HomeServiceCoverageArea::create(['name' => 'رام الله', 'is_active' => true]);
    $date = CarbonImmutable::parse('next tuesday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    // Book the first slot as a home appointment (creates ServiceAddress)
    $oldAppt = app(BookingService::class)->book(new BookingData(
        customerId: $customer->id,
        doctorProfileId: $doc->id,
        serviceIds: [$svc->id],
        startAt: $slots[0]['start'],
        deliveryMode: DeliveryMode::Home,
        createdByRole: UserRole::Customer,
        coverageAreaId: $area->id,
        addressText: 'شارع الإستقلال 5',
        locationNote: 'الطابق الثاني',
    ));

    // $slots is computed before the booking above, so all 6 grid slots are present.
    expect($slots)->toHaveCount(6);
    $newStart = $slots[1]['start'];

    $newAppt = app(AppointmentTransitionService::class)->reschedule($oldAppt, $newStart);

    // Old is rescheduled, new is requested, link is correct
    expect(Appointment::find($oldAppt->id)->status)->toBe(AppointmentStatus::Rescheduled);
    expect($newAppt->status)->toBe(AppointmentStatus::Requested);
    expect($newAppt->rescheduled_from_id)->toBe($oldAppt->id);

    // ServiceAddress is carried to the new appointment
    $newAppt->load('serviceAddress');
    expect($newAppt->serviceAddress)->not->toBeNull();
    expect($newAppt->serviceAddress->coverage_area_id)->toBe($area->id);
    expect($newAppt->serviceAddress->address_text)->toBe('شارع الإستقلال 5');
});
