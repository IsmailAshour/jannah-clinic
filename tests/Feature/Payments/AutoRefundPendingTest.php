<?php

use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

function aPaidAppointmentForObserver(string $apptStatus = 'confirmed'): array
{
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
        'customer_id' => $cust->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => $apptStatus, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'verified_at' => now(),
    ]);

    return [$appt, $payment];
}

it('auto-marks payment as refund_pending when appointment is cancelled', function () {
    [$appt, $payment] = aPaidAppointmentForObserver('confirmed');

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'العميل ألغى');

    expect($payment->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('auto-marks payment as refund_pending when appointment is rejected', function () {
    [$appt, $payment] = aPaidAppointmentForObserver('requested');

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Rejected);

    expect($payment->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('does NOT auto-refund when the payment is not paid (still pending)', function () {
    [$appt, $payment] = aPaidAppointmentForObserver('confirmed');
    $payment->update(['status' => PaymentStatus::Pending, 'verified_at' => null]);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'سبب');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does NOT auto-refund when the appointment is completed (service rendered)', function () {
    [$appt, $payment] = aPaidAppointmentForObserver('confirmed');

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Completed);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('does NOT auto-refund when the appointment is no_show (no service but kept)', function () {
    [$appt, $payment] = aPaidAppointmentForObserver('confirmed');

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::NoShow);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});
