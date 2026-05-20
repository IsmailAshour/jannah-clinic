<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return $this === self::Refunded;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
