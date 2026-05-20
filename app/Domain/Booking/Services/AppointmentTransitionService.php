<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
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
            $new = $this->booking->book(new BookingData(
                customerId: $old->customer_id,
                doctorProfileId: $old->doctor_profile_id,
                serviceId: $old->service_id,
                startAt: $newStart,
                deliveryMode: $old->delivery_mode,
                createdByRole: $old->created_by_role,
                coverageAreaId: $addr?->coverage_area_id,
                addressText: $addr?->address_text,
                locationNote: $addr?->location_note,
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
