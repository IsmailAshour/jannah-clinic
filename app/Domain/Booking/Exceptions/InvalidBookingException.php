<?php

namespace App\Domain\Booking\Exceptions;

class InvalidBookingException extends \RuntimeException
{
    public function __construct(string $message = 'بيانات الحجز غير صالحة.')
    {
        parent::__construct($message);
    }
}
