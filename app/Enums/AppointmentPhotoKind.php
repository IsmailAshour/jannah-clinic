<?php

namespace App\Enums;

enum AppointmentPhotoKind: string
{
    case Before = 'before';
    case After = 'after';

    public function labelAr(): string
    {
        return match ($this) {
            self::Before => 'قبل',
            self::After => 'بعد',
        };
    }
}
