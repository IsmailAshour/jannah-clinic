<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Appointment = 'appointment';
    case Payment = 'payment';
    case Medical = 'medical';
    case System = 'system';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
