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
use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
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

            if ($d->serviceIds === []) {
                throw new InvalidBookingException('يجب اختيار خدمة واحدة على الأقلّ.');
            }
            if (count($d->serviceIds) !== count(array_unique($d->serviceIds))) {
                throw new InvalidBookingException('لا يمكن اختيار الخدمة نفسها مرّتين في الموعد.');
            }

            $servicesById = Service::query()->whereIn('id', $d->serviceIds)->get()->keyBy('id');
            if ($servicesById->count() !== count($d->serviceIds)) {
                throw new InvalidBookingException('خدمة واحدة أو أكثر غير موجودة.');
            }
            $orderedServices = [];
            foreach ($d->serviceIds as $sid) {
                $orderedServices[] = $servicesById[$sid];
            }

            // All services must be linked to this doctor.
            $linkedCount = (int) $doctor->services()->whereIn('services.id', $d->serviceIds)->count();
            if ($linkedCount !== count($d->serviceIds)) {
                throw new InvalidBookingException('الطبيب لا يقدّم واحدة أو أكثر من الخدمات المختارة.');
            }

            // Delivery-mode eligibility — every service in the booking must
            // support the chosen mode.
            if ($d->deliveryMode === DeliveryMode::Home) {
                foreach ($orderedServices as $svc) {
                    if (! $svc->home_service_enabled) {
                        throw new InvalidBookingException("الخدمة «{$svc->name}» غير متاحة كزيارة منزلية.");
                    }
                }
                $area = HomeServiceCoverageArea::query()->where('is_active', true)->find($d->coverageAreaId);
                if (! $area || ! $d->addressText) {
                    throw new InvalidBookingException('منطقة التغطية أو العنوان غير صالح.');
                }
            }
            if ($d->deliveryMode === DeliveryMode::Online) {
                foreach ($orderedServices as $svc) {
                    if (! $svc->online_service_enabled) {
                        throw new InvalidBookingException("الخدمة «{$svc->name}» غير متاحة كموعد أونلاين.");
                    }
                }
                if ($d->whatsappPhone === null || trim($d->whatsappPhone) === '') {
                    throw new InvalidBookingException('رقم واتساب مطلوب لمواعيد الأونلاين.');
                }
            }

            $available = collect($this->availability->slotsForServices($doctor, $orderedServices, $d->startAt))
                ->first(fn ($s) => $s['start']->equalTo($d->startAt));
            if (! $available) {
                throw new SlotUnavailableException('الفترة لم تعد متاحة، اختر فترة أخرى.');
            }

            $quote = $this->pricing->quoteMulti($doctor, $orderedServices, $d->deliveryMode);

            // Resolve staff discount (manager/receptionist only — Customer role
            // rejected outright). discount_amount is computed from gross total
            // and clamped at [0, total] so the patient can never owe negative.
            // Loyalty-points payments cannot stack with a cash discount.
            $discountAmount = null;
            if ($d->discountType !== null) {
                if ($d->createdByRole === UserRole::Customer) {
                    throw new InvalidBookingException('لا يمكن للعميل تطبيق خصم على حجزه.');
                }
                if ($d->paymentMethod === PaymentMethod::LoyaltyPoints) {
                    throw new InvalidBookingException('لا يمكن تطبيق خصم على دفع بالنقاط.');
                }
                if ($d->discountValue === null || ! is_numeric($d->discountValue) || (float) $d->discountValue <= 0) {
                    throw new InvalidBookingException('قيمة الخصم غير صالحة.');
                }
                if ($d->discountType === DiscountType::Percent && (float) $d->discountValue > 100) {
                    throw new InvalidBookingException('نسبة الخصم لا يمكن أن تتجاوز 100%.');
                }
                $discountAmount = $d->discountType === DiscountType::Percent
                    ? bcdiv(bcmul($quote['total'], $d->discountValue, 4), '100', 2)
                    : bcadd($d->discountValue, '0', 2);
                // Clamp at total — a 200₪ fixed discount on a 150₪ visit caps at 150.
                if (bccomp($discountAmount, $quote['total'], 2) > 0) {
                    $discountAmount = $quote['total'];
                }
            }

            $apptAttrs = [
                'customer_id' => $d->customerId,
                'doctor_profile_id' => $doctor->id,
                'start_at' => $available['start'],
                'end_at' => $available['end'],
                'status' => AppointmentStatus::Requested,
                'price_at_booking' => $quote['total'],
                'delivery_mode' => $d->deliveryMode,
                'whatsapp_phone' => $d->deliveryMode === DeliveryMode::Online ? $d->whatsappPhone : null,
                'home_surcharge_amount' => $quote['surcharge'],
                'created_by_role' => $d->createdByRole,
                'payment_method' => $d->paymentMethod,
                'discount_type' => $discountAmount !== null ? $d->discountType : null,
                'discount_value' => $discountAmount !== null ? $d->discountValue : null,
                'discount_amount' => $discountAmount,
                'discount_reason' => $discountAmount !== null ? $d->discountReason : null,
            ];

            if ($d->paymentMethod === PaymentMethod::LoyaltyPoints) {
                // Multi-service loyalty redemption: every service must be
                // loyalty-enabled with a redemption value; cost = sum.
                $totalPoints = 0;
                foreach ($orderedServices as $svc) {
                    if (! $svc->loyalty_enabled || ! $svc->loyalty_redemption_points) {
                        throw new InsufficientLoyaltyBalanceException("الخدمة «{$svc->name}» غير متاحة للاستبدال بالنقاط.");
                    }
                    $totalPoints += (int) $svc->loyalty_redemption_points;
                }
                $apptAttrs['loyalty_points_spent'] = $totalPoints;
            }

            $appt = Appointment::create($apptAttrs);

            // Multi-service pivot — one row per service, in user-chosen order.
            $now = now();
            $rows = [];
            foreach ($orderedServices as $i => $svc) {
                $rows[] = [
                    'appointment_id' => $appt->id,
                    'service_id' => $svc->id,
                    'price_at_booking' => $quote['lines'][$i]['base'],
                    'duration_minutes' => $quote['lines'][$i]['duration_minutes'],
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('appointment_services')->insert($rows);

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
                // P2: every cash Appointment gets a pending Payment created
                // atomically. amount = total of all services + surcharge -
                // staff discount (if any). Loyalty-points payments cannot
                // stack with a discount (rejected above).
                $payAmount = $discountAmount !== null
                    ? bcsub($quote['total'], $discountAmount, 2)
                    : $quote['total'];
                Payment::create([
                    'appointment_id' => $appt->id,
                    'amount' => $payAmount,
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
