<?php

namespace App\Domain\Booking\Data;

use App\Enums\DeliveryMode;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;

final class BookingData
{
    public function __construct(
        public int $customerId,
        public int $doctorProfileId,
        public int $serviceId,
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
}
