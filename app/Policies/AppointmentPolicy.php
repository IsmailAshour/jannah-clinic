<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->role->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer && $appointment->customer_id === $user->id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($user->role->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer && $appointment->customer_id === $user->id;
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        if ($user->role->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer && $appointment->customer_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return $user->role->isStaff();
    }
}
