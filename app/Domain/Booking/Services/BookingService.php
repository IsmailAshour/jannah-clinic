<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {}

    public function book(BookingData $d): Appointment
    {
        return DB::transaction(function () use ($d) {
            // Serialises concurrent book() calls for the same doctor on PostgreSQL.
            // lockForUpdate() is a no-op on SQLite (test driver) — the double-booking
            // test proves the re-check logic, not the lock itself; production
            // correctness depends on PostgreSQL row-level locking. Do not remove.
            $doctor = DoctorProfile::query()->lockForUpdate()->findOrFail($d->doctorProfileId);
            $service = Service::query()->findOrFail($d->serviceId);

            if (! $doctor->services()->where('services.id', $service->id)->exists()) {
                throw new InvalidBookingException('الطبيب لا يقدّم هذه الخدمة.');
            }
            if ($d->deliveryMode === DeliveryMode::Home) {
                if (! $service->home_service_enabled) {
                    throw new InvalidBookingException('الخدمة غير متاحة كزيارة منزلية.');
                }
                $area = HomeServiceCoverageArea::query()->where('is_active', true)->find($d->coverageAreaId);
                if (! $area || ! $d->addressText) {
                    throw new InvalidBookingException('منطقة التغطية أو العنوان غير صالح.');
                }
            }

            $available = collect($this->availability->slotsFor($doctor, $service, $d->startAt))
                ->first(fn ($s) => $s['start']->equalTo($d->startAt));
            if (! $available) {
                throw new SlotUnavailableException('الفترة لم تعد متاحة، اختر فترة أخرى.');
            }

            $quote = $this->pricing->quote($doctor, $service, $d->deliveryMode);

            $appt = Appointment::create([
                'customer_id' => $d->customerId,
                'doctor_profile_id' => $doctor->id,
                'service_id' => $service->id,
                'start_at' => $available['start'],
                'end_at' => $available['end'],
                'status' => AppointmentStatus::Requested,
                'price_at_booking' => $quote['total'],
                'delivery_mode' => $d->deliveryMode,
                'home_surcharge_amount' => $quote['surcharge'],
                'created_by_role' => $d->createdByRole,
            ]);

            if ($d->deliveryMode === DeliveryMode::Home) {
                $appt->serviceAddress()->create([
                    'coverage_area_id' => $d->coverageAreaId,
                    'address_text' => $d->addressText,
                    'location_note' => $d->locationNote,
                ]);
            }

            // P2: every Appointment gets a pending Payment created atomically.
            Payment::create([
                'appointment_id' => $appt->id,
                'amount' => $quote['total'],
                'status' => PaymentStatus::Pending,
            ]);

            return $appt->fresh(['serviceAddress', 'payment']);
        });
    }
}
