<?php

namespace App\Domain\Payment\Exceptions;

class InvalidPaymentTransitionException extends \RuntimeException
{
    public function __construct(string $message = 'انتقال غير مسموح لحالة الدفع.')
    {
        parent::__construct($message);
    }
}
