<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function book(BookingData $d): Appointment
    {
        $appt = DB::transaction(function () use ($d) {
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
            if ($d->deliveryMode === DeliveryMode::Online) {
                if (! $service->online_service_enabled) {
                    throw new InvalidBookingException('الخدمة غير متاحة كموعد أونلاين.');
                }
                if ($d->whatsappPhone === null || trim($d->whatsappPhone) === '') {
                    throw new InvalidBookingException('رقم واتساب مطلوب لمواعيد الأونلاين.');
                }
            }

            $available = collect($this->availability->slotsFor($doctor, $service, $d->startAt))
                ->first(fn ($s) => $s['start']->equalTo($d->startAt));
            if (! $available) {
                throw new SlotUnavailableException('الفترة لم تعد متاحة، اختر فترة أخرى.');
            }

            $quote = $this->pricing->quote($doctor, $service, $d->deliveryMode);

            $apptAttrs = [
                'customer_id' => $d->customerId,
                'doctor_profile_id' => $doctor->id,
                'service_id' => $service->id,
                'start_at' => $available['start'],
                'end_at' => $available['end'],
                'status' => AppointmentStatus::Requested,
                'price_at_booking' => $quote['total'],
                'delivery_mode' => $d->deliveryMode,
                'whatsapp_phone' => $d->deliveryMode === DeliveryMode::Online ? $d->whatsappPhone : null,
                'home_surcharge_amount' => $quote['surcharge'],
                'created_by_role' => $d->createdByRole,
                'payment_method' => $d->paymentMethod,
            ];
            if ($d->paymentMethod === PaymentMethod::LoyaltyPoints) {
                // Pre-flight check duplicates LoyaltyService::redeemForAppointment but
                // is required HERE to safely populate loyalty_points_spent on the
                // appointment row before insert (DB CHECK enforces consistency).
                if (! $service->loyalty_enabled || ! $service->loyalty_redemption_points) {
                    throw new InsufficientLoyaltyBalanceException('الخدمة غير متاحة للاستبدال بالنقاط.');
                }
                $apptAttrs['loyalty_points_spent'] = (int) $service->loyalty_redemption_points;
            }
            $appt = Appointment::create($apptAttrs);

            // Phase 2 of the multi-service refactor: dual-write the pivot.
            // Every booking continues to carry a single service today (the
            // wizard still picks one), but the pivot is kept in sync so
            // downstream surfaces can start reading from it. Price stored
            // here is the BASE price — the visit-level surcharge stays on
            // the appointment.
            DB::table('appointment_services')->insert([
                'appointment_id' => $appt->id,
                'service_id' => $service->id,
                'price_at_booking' => $quote['base'],
                'duration_minutes' => $service->duration_minutes,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($d->deliveryMode === DeliveryMode::Home) {
                $appt->serviceAddress()->create([
                    'coverage_area_id' => $d->coverageAreaId,
                    'address_text' => $d->addressText,
                    'location_note' => $d->locationNote,
                    'lat' => $d->lat,
                    'lng' => $d->lng,
                ]);
            }

            if ($d->paymentMethod === PaymentMethod::Cash) {
                // P2: every cash Appointment gets a pending Payment created atomically.
                Payment::create([
                    'appointment_id' => $appt->id,
                    'amount' => $quote['total'],
                    'status' => PaymentStatus::Pending,
                ]);
            } else {
                $customer = User::query()->findOrFail($d->customerId);
                $this->loyalty->redeemForAppointment($appt, $customer);
            }

            return $appt->fresh(['serviceAddress', 'payment']);
        });
        // Notify AFTER the transaction commits — a notification failure
        // must not roll back a successful booking.
        $this->notifications->bookingRequested($appt->load('customer'));

        return $appt;
    }
}
