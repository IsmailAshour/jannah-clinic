<?php

namespace App\Domain\Booking\Data;

use App\Enums\DeliveryMode;
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
