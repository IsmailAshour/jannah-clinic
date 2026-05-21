<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\User;

class MedicalEntryPolicy
{
    public function view(User $user, MedicalEntry $entry): bool
    {
        if ($user->role === UserRole::Customer) {
            return $entry->appointment->customer_id === $user->id;
        }
        if ($user->role === UserRole::Receptionist) {
            return false;
        }

        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function create(User $user, Appointment $appointment): bool
    {
        if ($user->role !== UserRole::Doctor) {
            return false;
        }

        // Status gate lifted 2026-05-21 — doctors may file the record at any
        // point in the appointment lifecycle (during the visit, before the
        // status flips to Completed, etc.).
        return $appointment->doctor_profile_id === $user->doctorProfile?->id;
    }

    public function update(User $user, MedicalEntry $entry): bool
    {
        return $user->role === UserRole::Doctor && $entry->author_id === $user->id;
    }
}
