<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Domain\Booking\Services\BookingService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\LoyaltyLedger;

it('cancelling a loyalty-redeemed appointment returns the points', function () {
    $f = mkRedeemFixtures(balance: 1000);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::LoyaltyPoints,
    ));
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'changed mind');

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(1000);
    expect(LoyaltyLedger::where('reason', LoyaltyReason::RefundReversal->value)->count())->toBe(1);
});

it('rejecting a loyalty-redeemed Requested appointment returns the points', function () {
    $f = mkRedeemFixtures(balance: 1000);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::LoyaltyPoints,
    ));
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Rejected);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(1000);
});

it('rescheduling a loyalty-redeemed appointment preserves balance', function () {
    $f = mkRedeemFixtures(balance: 1000);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::LoyaltyPoints,
    ));
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);

    // Reschedule to next-next Monday at the same time (still in the doctor's slot window)
    $newStart = $f['start']->addWeek();
    app(AppointmentTransitionService::class)->reschedule($appt, $newStart);

    // Net balance unchanged: old appointment refunded (+500), new appointment redeemed (-500).
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);
});
