<?php

namespace App\Enums;

enum ReminderKind: string
{
    case Before24h = 'before_24h';
    case Before2h = 'before_2h';

    /**
     * Hours-before-start the reminder should fire at.
     */
    public function thresholdHours(): int
    {
        return match ($this) {
            self::Before24h => 24,
            self::Before2h => 2,
        };
    }

    /**
     * Short Arabic label for admin UI.
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::Before24h => 'قبل 24 ساعة',
            self::Before2h => 'قبل ساعتين',
        };
    }
}
