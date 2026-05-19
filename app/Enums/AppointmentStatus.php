<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Rejected, self::Completed, self::Cancelled, self::NoShow, self::Rescheduled,
        ], true);
    }

    /** @return self[] */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Rejected, self::Cancelled, self::Rescheduled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled, self::Rescheduled],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
