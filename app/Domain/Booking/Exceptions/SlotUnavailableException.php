<?php

namespace App\Domain\Booking\Exceptions;

class SlotUnavailableException extends \RuntimeException
{
    public function __construct(string $message = 'الفترة لم تعد متاحة، اختر فترة أخرى.')
    {
        parent::__construct($message);
    }
}
