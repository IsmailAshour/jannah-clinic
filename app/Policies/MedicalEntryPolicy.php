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
        // Status + ownership gates lifted 2026-05-21 — any doctor or the manager
        // may file the record at any point in the appointment lifecycle. Reflects
        // small-clinic reality where the manager often documents visits on the
        // doctor's behalf.
        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function update(User $user, MedicalEntry $entry): bool
    {
        // Manager + entry author (doctor) can edit.
        return $user->role === UserRole::Manager
            || ($user->role === UserRole::Doctor && $entry->author_id === $user->id);
    }

    /**
     * Attachment writes (upload + delete) follow the same audience as
     * view/update: managers + doctors. Doctors don't need to be the
     * entry's author — covering colleagues should be able to add a
     * lab result to any record, matching the small-clinic policy
     * already in `create`.
     */
    public function uploadAttachment(User $user, MedicalEntry $entry): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function deleteAttachment(User $user, MedicalEntry $entry): bool
    {
        return $user->role === UserRole::Manager
            || $user->role === UserRole::Doctor;
    }
}
