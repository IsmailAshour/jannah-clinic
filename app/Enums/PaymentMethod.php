<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case LoyaltyPoints = 'loyalty_points';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
