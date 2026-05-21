<?php

namespace App\Enums;

enum TeamRole: string
{
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case Physiotherapist = 'physiotherapist';

    public function labelAr(): string
    {
        return match ($this) {
            self::Doctor => 'طبيب',
            self::Nurse => 'ممرّض',
            self::Physiotherapist => 'أخصّائي علاج طبيعي',
        };
    }
}
