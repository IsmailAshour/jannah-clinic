<?php

namespace App\Domain\Booking\Exceptions;

class InvalidTransitionException extends \RuntimeException
{
    public function __construct(string $message = 'انتقال غير مسموح للموعد.')
    {
        parent::__construct($message);
    }
}
