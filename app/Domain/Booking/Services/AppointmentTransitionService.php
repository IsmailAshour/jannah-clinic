<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ServiceAddress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentTransitionService
{
    public function __construct(
        private readonly BookingService $booking,
        private readonly NotificationService $notifications,
    ) {}

    public function transition(Appointment $a, AppointmentStatus $to, ?string $reason = null): Appointment
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
        match ($to) {
            AppointmentStatus::Confirmed => $this->notifications->appointmentConfirmed($a),
            AppointmentStatus::Rejected => $this->notifications->appointmentRejected($a),
            AppointmentStatus::Cancelled => $this->notifications->appointmentCancelledByStaff($a),
            AppointmentStatus::Completed => $this->notifications->appointmentCompleted($a),
            default => null,
        };

        return $a;
    }

    public function reschedule(Appointment $old, CarbonImmutable $newStart): Appointment
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
        $this->notifications->appointmentRescheduledForCustomer($new->fresh()->load('customer'));

        return $new;
    }
}
