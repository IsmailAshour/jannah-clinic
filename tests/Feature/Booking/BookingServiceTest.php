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

// ---- Multi-service coverage --------------------------------------------

function twoServicesFixture(): array
{
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => uniqid(), 'color_variant' => 'brand']);
    // A: 30-min @ 100₪. B: 60-min @ 150₪. Total expected: 90 min, 250₪ base.
    $a = Service::create(['category_id' => $cat->id, 'name' => 'A', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => true]);
    $b = Service::create(['category_id' => $cat->id, 'name' => 'B', 'base_price' => 150, 'duration_minutes' => 60, 'home_service_enabled' => true]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach([$a->id, $b->id]);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));
    $cust = User::factory()->create(['role' => UserRole::Customer]);

    return compact('a', 'b', 'doc', 'date', 'cust');
}

it('booking 2 services creates 2 pivot rows in user-chosen order', function () {
    ['a' => $a, 'b' => $b, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id,
        doctorProfileId: $doc->id,
        serviceIds: [$b->id, $a->id],  // B first, A second
        startAt: $date->setTime(9, 0),
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
    ));

    $rows = $appt->appointmentServices()->orderBy('sort_order')->get();
    expect($rows->count())->toBe(2);
    expect($rows[0]->service_id)->toBe($b->id)
        ->and($rows[0]->sort_order)->toBe(0)
        ->and($rows[0]->duration_minutes)->toBe(60);
    expect($rows[1]->service_id)->toBe($a->id)
        ->and($rows[1]->sort_order)->toBe(1)
        ->and($rows[1]->duration_minutes)->toBe(30);
});

it('booking 2 services sums prices on the appointment + per-line on the pivot', function () {
    ['a' => $a, 'b' => $b, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$a->id, $b->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    ));

    // 100 + 150 = 250 (no surcharge — Center delivery)
    expect((string) $appt->price_at_booking)->toBe('250.00')
        ->and((string) $appt->home_surcharge_amount)->toBe('0.00');

    $rows = $appt->appointmentServices()->orderBy('sort_order')->get();
    expect((string) $rows[0]->price_at_booking)->toBe('100.00')
        ->and((string) $rows[1]->price_at_booking)->toBe('150.00');
});

it('booking 2 services blocks end_at out by the SUM of durations', function () {
    ['a' => $a, 'b' => $b, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$a->id, $b->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    ));

    // 30 + 60 = 90 min → end_at = 10:30
    expect($appt->end_at->format('H:i'))->toBe('10:30');
});

it('rejects booking when one of multiple services is not linked to the doctor', function () {
    ['a' => $a, 'b' => $b, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();
    $cat2 = ServiceCategory::create(['name' => 'y', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $rogue = Service::create(['category_id' => $cat2->id, 'name' => 'rogue', 'base_price' => 50, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    // rogue not attached to doctor

    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$a->id, $rogue->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    )))->toThrow(InvalidBookingException::class, 'الطبيب لا يقدّم');
});

it('rejects multi-service home booking when one service is clinic-only', function () {
    ['doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();
    $cat = ServiceCategory::create(['name' => 'mixed', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $homeOk = Service::create(['category_id' => $cat->id, 'name' => 'home-ok', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => true]);
    $clinicOnly = Service::create(['category_id' => $cat->id, 'name' => 'clinic-only', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc->services()->attach([$homeOk->id, $clinicOnly->id]);
    $area = HomeServiceCoverageArea::create(['name' => 'منطقة', 'is_active' => true]);

    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$homeOk->id, $clinicOnly->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Home, createdByRole: UserRole::Customer,
        coverageAreaId: $area->id, addressText: 'شارع 7',
    )))->toThrow(InvalidBookingException::class, 'غير متاحة كزيارة منزلية');
});

it('rejects the same service appearing twice in one booking', function () {
    ['a' => $a, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();

    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$a->id, $a->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Center, createdByRole: UserRole::Customer,
    )))->toThrow(InvalidBookingException::class, 'مرّتين');
});

it('home surcharge is applied to the SUM of services, not per-line', function () {
    ['a' => $a, 'b' => $b, 'doc' => $doc, 'date' => $date, 'cust' => $cust] = twoServicesFixture();
    $area = HomeServiceCoverageArea::create(['name' => 'منطقة', 'is_active' => true]);
    // Default surcharge = 30% (per BookingServiceTest)

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id, doctorProfileId: $doc->id, serviceIds: [$a->id, $b->id],
        startAt: $date->setTime(9, 0), deliveryMode: DeliveryMode::Home, createdByRole: UserRole::Customer,
        coverageAreaId: $area->id, addressText: 'شارع 8',
    ));

    // subtotal 250, 30% surcharge = 75, total = 325
    expect((string) $appt->home_surcharge_amount)->toBe('75.00')
        ->and((string) $appt->price_at_booking)->toBe('325.00');
});

