<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LoyaltyLedger;
use App\Models\User;

class LoyaltyLedgerPolicy
{
    public function view(User $user, LoyaltyLedger $entry): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer && $entry->customer_id === $user->id;
    }
}
