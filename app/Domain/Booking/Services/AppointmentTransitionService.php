<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\ServiceAddress;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentTransitionService
{
    public function __construct(
        private readonly BookingService $booking,
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function transition(Appointment $a, AppointmentStatus $to, ?string $reason = null, ?User $initiator = null): Appointment
    {
        if (! $a->status->canTransitionTo($to)) {
            throw new InvalidTransitionException("انتقال غير مسموح: {$a->status->value} → {$to->value}");
        }

        $a = DB::transaction(function () use ($a, $to, $reason) {
            $a->status = $to;
            if ($to === AppointmentStatus::Cancelled) {
                $a->cancellation_reason = $reason;
            }
            $a->save();

            return $a;
        });
        // Notify AFTER the transaction commits — a notification failure
        // must not roll back an appointment status change.
        $a->load('customer', 'doctor.user');
        $byCustomer = $initiator?->role === UserRole::Customer;
        match (true) {
            $to === AppointmentStatus::Confirmed => $this->notifications->appointmentConfirmed($a),
            $to === AppointmentStatus::Rejected => $this->notifications->appointmentRejected($a),
            $to === AppointmentStatus::Cancelled && $byCustomer => $this->notifications->appointmentCancelledByCustomer($a),
            $to === AppointmentStatus::Cancelled => $this->notifications->appointmentCancelledByStaff($a),
            $to === AppointmentStatus::Completed => $this->notifications->appointmentCompleted($a),
            default => null,
        };
        if (in_array($to, [AppointmentStatus::Cancelled, AppointmentStatus::Rejected], true)
            && $a->payment_method === PaymentMethod::LoyaltyPoints) {
            $this->loyalty->reverseRedemption($a);
        }

        return $a;
    }

    public function reschedule(Appointment $old, CarbonImmutable $newStart, ?User $initiator = null): Appointment
    {
        $new = DB::transaction(function () use ($old, $newStart) {
            if (! $old->status->canTransitionTo(AppointmentStatus::Rescheduled)) {
                throw new InvalidTransitionException('لا يمكن إعادة جدولة هذا الموعد.');
            }
            /** @var ServiceAddress|null $addr */
            $addr = $old->serviceAddress;
            // Carry the payment method across the reschedule. Null can only occur
            // for legacy rows created before this column existed (DB default is 'cash');
            // fall back to Cash to keep the typed BookingData contract intact.
            $paymentMethod = $old->payment_method ?? PaymentMethod::Cash;
            // If the old appointment was paid with loyalty points, reverse that
            // redemption FIRST so the customer's balance can fund the new redemption.
            // Without this, the new book() call would either (a) try to deduct
            // points again from an already-depleted balance and throw, or (b)
            // succeed but double-charge the customer.
            if ($paymentMethod === PaymentMethod::LoyaltyPoints) {
                $this->loyalty->reverseRedemption($old);
            }
            // Carry every service from the old appointment to the new one
            // (pivot is the canonical source).
            $serviceIds = $old->appointmentServices()->orderBy('sort_order')->pluck('service_id')->all();
            $new = $this->booking->book(new BookingData(
                customerId: $old->customer_id,
                doctorProfileId: $old->doctor_profile_id,
                serviceIds: array_map('intval', $serviceIds),
                startAt: $newStart,
                deliveryMode: $old->delivery_mode,
                createdByRole: $old->created_by_role,
                coverageAreaId: $addr?->coverage_area_id,
                addressText: $addr?->address_text,
                locationNote: $addr?->location_note,
                lat: $addr?->lat !== null ? (float) $addr->lat : null,
                lng: $addr?->lng !== null ? (float) $addr->lng : null,
                whatsappPhone: $old->whatsapp_phone,
                paymentMethod: $paymentMethod,
            ));
            $new->rescheduled_from_id = $old->id;
            $new->save();
            $old->status = AppointmentStatus::Rescheduled;
            $old->save();

            return $new;
        });
        $fresh = $new->fresh()->load('customer', 'doctor.user');
        if ($initiator?->role === UserRole::Customer) {
            $this->notifications->appointmentRescheduledForStaff($fresh);
        } else {
            $this->notifications->appointmentRescheduledForCustomer($fresh);
        }

        return $new;
    }
}
