<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

/**
 * Gates for medical attachments — tied to an Appointment, not to the
 * MedicalEntry, so that documents can be uploaded BEFORE a structured
 * entry exists (lab results often arrive first).
 */
class MedicalAttachmentPolicy
{
    /** Read access for streaming a single file. */
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->role === UserRole::Customer) {
            return $appointment->customer_id === $user->id;
        }
        if ($user->role === UserRole::Receptionist) {
            // Receptionists are excluded from clinical attachments — same
            // posture as the medical entry policy.
            return false;
        }

        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function upload(User $user, Appointment $appointment): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }
}
