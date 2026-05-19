<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Doctor = 'doctor';
    case Receptionist = 'receptionist';
    case Customer = 'customer';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
