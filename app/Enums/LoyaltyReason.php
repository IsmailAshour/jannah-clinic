<?php

namespace App\Enums;

enum LoyaltyReason: string
{
    case EarnedFromPayment = 'earned_from_payment';
    case RedeemedForAppointment = 'redeemed_for_appointment';
    case ClawbackFromRefund = 'clawback_from_refund';
    case RefundReversal = 'refund_reversal';
    case AdjustmentByManager = 'adjustment_by_manager';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
