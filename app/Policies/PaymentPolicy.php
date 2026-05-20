<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * View a Payment: any staff member, or the customer who owns the appointment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer
            && $payment->appointment()->where('customer_id', $user->id)->exists();
    }
}
