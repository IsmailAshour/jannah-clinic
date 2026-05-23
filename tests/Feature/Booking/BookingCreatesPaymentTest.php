<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\BookingService;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

it('creates a pending Payment when an Appointment is booked', function () {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);

    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 2));
    $cust = User::factory()->create(['role' => UserRole::Customer]);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id,
        doctorProfileId: $doc->id,
        serviceIds: [$svc->id],
        startAt: $date->setTime(9, 0),
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
    ));

    expect($appt->payment)->not->toBeNull();
    expect($appt->payment->status)->toBe(PaymentStatus::Pending);
    expect((string) $appt->payment->amount)->toBe('100.00');
    expect((int) $appt->payment->appointment_id)->toBe($appt->id);
});
