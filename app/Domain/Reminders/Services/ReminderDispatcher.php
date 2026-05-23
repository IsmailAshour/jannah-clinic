<?php

namespace App\Domain\Reminders\Services;

use App\Domain\Reminders\Jobs\SendAppointmentReminderJob;
use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Cron-driven sweeper that locates Confirmed appointments approaching one
 * of the reminder thresholds and dispatches a job per (appointment, kind)
 * pair. The job (not the dispatcher) is the final idempotency gate —
 * a unique DB constraint on (appointment_id, kind) catches races.
 */
class ReminderDispatcher
{
    /**
     * @return int total jobs queued
     */
    public function dispatch(): int
    {
        $queued = 0;
        foreach (ReminderKind::cases() as $kind) {
            $queued += $this->dispatchKind($kind);
        }

        return $queued;
    }

    private function dispatchKind(ReminderKind $kind): int
    {
        $now = CarbonImmutable::now();
        $threshold = $now->addHours($kind->thresholdHours());

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->where('start_at', '>', $now)
            ->where('start_at', '<=', $threshold)
            ->whereDoesntHave('reminders', fn ($q) => $q->where('kind', $kind->value))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('email'))
            ->get(['id']);

        foreach ($appointments as $appointment) {
            SendAppointmentReminderJob::dispatch($appointment->id, $kind->value);
        }

        return $appointments->count();
    }
}
