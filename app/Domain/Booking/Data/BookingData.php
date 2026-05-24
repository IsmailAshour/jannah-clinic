<?php

namespace App\Domain\Booking\Data;

use App\Enums\DeliveryMode;
use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;

final class BookingData
{
    /**
     * @param  list<int>  $serviceIds  Always an array even for a single-service
     *                                 booking — phase 3 of the multi-service
     *                                 refactor. The first element doubles as
     *                                 the legacy serviceId until phase 5
     *                                 drops appointments.service_id.
     */
    public function __construct(
        public int $customerId,
        public int $doctorProfileId,
        public array $serviceIds,
        public CarbonImmutable $startAt,
        public DeliveryMode $deliveryMode,
        public UserRole $createdByRole,
        public ?int $coverageAreaId = null,
        public ?string $addressText = null,
        public ?string $locationNote = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $whatsappPhone = null,
        public PaymentMethod $paymentMethod = PaymentMethod::Cash,
        // Staff-applied discount. discountValue is the raw user input as a
        // numeric string (e.g. '10' for "10%" or '50' for "50₪"). Resolved
        // to a ₪ figure inside BookingService::book() and clamped at the
        // appointment's gross total. Customer-created bookings reject any
        // discount at the controller layer.
        public ?DiscountType $discountType = null,
        public ?string $discountValue = null,
        public ?string $discountReason = null,
    ) {}

    /**
     * Legacy accessor used by phase-1/2 callers that still pass a single
     * service. Always returns the first ID.
     */
    public function primaryServiceId(): int
    {
        return $this->serviceIds[0];
    }
}
