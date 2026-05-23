<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\LoyaltyLedger;
use App\Models\Payment;

it('booking with payment_method=loyalty_points creates appointment WITHOUT a Payment row', function () {
    $f = mkRedeemFixtures(balance: 1000);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceIds: [$f['service']->id],
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::LoyaltyPoints,
    ));

    expect($appt->payment_method->value)->toBe('loyalty_points')
        ->and($appt->loyalty_points_spent)->toBe(500)
        ->and(Payment::where('appointment_id', $appt->id)->exists())->toBeFalse()
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);
    expect(LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->where('reason', LoyaltyReason::RedeemedForAppointment->value)
        ->count())->toBe(1);
});

it('booking with insufficient balance throws and creates nothing', function () {
    $f = mkRedeemFixtures(balance: 100);

    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceIds: [$f['service']->id],
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::LoyaltyPoints,
    )))->toThrow(InsufficientLoyaltyBalanceException::class);

    expect(Appointment::count())->toBe(0)
        ->and(LoyaltyLedger::count())->toBe(0);
});

it('booking with payment_method=cash still creates Payment row and earns nothing yet', function () {
    $f = mkRedeemFixtures(balance: 0);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceIds: [$f['service']->id],
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: PaymentMethod::Cash,
    ));

    expect($appt->payment_method->value)->toBe('cash')
        ->and(Payment::where('appointment_id', $appt->id)->exists())->toBeTrue()
        ->and(LoyaltyLedger::count())->toBe(0);
});
