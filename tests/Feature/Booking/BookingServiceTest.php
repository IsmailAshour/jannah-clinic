<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Booking\Services\BookingService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function bookingFixture(bool $home = false): array
{
    $c = ServiceCategory::create(['name' => 'x', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $s = Service::create(['category_id' => $c->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => $home]);
    $d = DoctorProfile::factory()->create();
    $d->services()->attach($s->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($d, (int) $date->dayOfWeek, slotRange('09:00', 2));
    $cust = User::factory()->create(['role' => UserRole::Customer]);

    return compact('s', 'd', 'date', 'cust');
}

it('books a centre appointment at requested status with computed price', function () {
    ['s' => $s,'d' => $d,'date' => $date,'cust' => $cust] = bookingFixture();
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    ));
    expect($appt->status)->toBe(AppointmentStatus::Requested);
    expect($appt->price_at_booking)->toBe('100.00');
    expect($appt->serviceAddress)->toBeNull();
});

it('books a home appointment with a ServiceAddress and surcharge', function () {
    ['s' => $s,'d' => $d,'date' => $date,'cust' => $cust] = bookingFixture(home: true);
    $area = HomeServiceCoverageArea::create(['name' => 'رام الله', 'is_active' => true]);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Home, createdByRole: UserRole::Customer,
        coverageAreaId: $area->id, addressText: 'شارع 1',
    ));
    expect($appt->delivery_mode)->toBe(DeliveryMode::Home);
    expect($appt->serviceAddress->address_text)->toBe('شارع 1');
    expect($appt->home_surcharge_amount)->toBe('30.00');   // base 100 @ 30% => '30.00'
    expect($appt->price_at_booking)->toBe('130.00');         // '130.00'
});

it('rejects booking a slot that is not available', function () {
    ['s' => $s,'d' => $d,'date' => $date,'cust' => $cust] = bookingFixture();
    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s->id],
        startAt: $date->setTime(15, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    )))->toThrow(SlotUnavailableException::class);
});

it('prevents double-booking the same slot', function () {
    ['s' => $s,'d' => $d,'date' => $date,'cust' => $cust] = bookingFixture();
    $data = fn () => new BookingData(customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s->id], startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer);
    app(BookingService::class)->book($data());
    expect(fn () => app(BookingService::class)->book($data()))->toThrow(SlotUnavailableException::class);
});

it('rejects a service the doctor does not offer', function () {
    ['d' => $d,'date' => $date,'cust' => $cust] = bookingFixture();
    $c2 = ServiceCategory::create(['name' => 'y', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $s2 = Service::create(['category_id' => $c2->id, 'name' => 's2', 'base_price' => 50, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s2->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    )))->toThrow(InvalidBookingException::class);
});

it('rejects a home booking for a service not enabled for home', function () {
    ['s' => $s,'d' => $d,'date' => $date,'cust' => $cust] = bookingFixture(home: false);
    $area = HomeServiceCoverageArea::create(['name' => 'نابلس', 'is_active' => true]);
    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $d->id, serviceIds: [$s->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Home, createdByRole: UserRole::Customer,
        coverageAreaId: $area->id, addressText: 'شارع 2',
    )))->toThrow(InvalidBookingException::class);
});
