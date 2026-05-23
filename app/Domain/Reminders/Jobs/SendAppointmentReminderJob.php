<?php

namespace App\Domain\Reminders\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminderJob implements ShouldQueue
{
    use FoundationQueueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 600];
    }

    public function __construct(
        public readonly int $appointmentId,
        public readonly string $kind,
    ) {}

    public function handle(): void
    {
        $kindEnum = ReminderKind::from($this->kind);

        // The whole pipeline inside a transaction: lock the appointment, do a
        // final correctness check (status + email + not-already-sent), insert
        // the idempotency row, then send. If the insert collides on the unique
        // (appointment_id, kind) constraint we treat it as "another worker
        // already sent" and exit cleanly.
        DB::transaction(function () use ($kindEnum): void {
            /** @var Appointment|null $appointment */
            $appointment = Appointment::query()
                ->lockForUpdate()
                ->with('customer')
                ->find($this->appointmentId);

            if ($appointment === null) {
                return;
            }

            // Re-check: status may have changed between dispatcher and job.
            if ($appointment->status !== AppointmentStatus::Confirmed) {
                return;
            }

            // Re-check: customer must still have an email.
            $email = $appointment->customer->email;
            if ($email === null || $email === '') {
                return;
            }

            // Re-check: not already sent (cheap path before relying on UNIQUE).
            $alreadySent = AppointmentReminder::query()
                ->where('appointment_id', $appointment->id)
                ->where('kind', $kindEnum->value)
                ->exists();
            if ($alreadySent) {
                return;
            }

            try {
                AppointmentReminder::create([
                    'appointment_id' => $appointment->id,
                    'kind' => $kindEnum->value,
                    'sent_at' => now(),
                    'recipient_email' => $email,
                ]);
            } catch (QueryException $e) {
                // UNIQUE violation — another worker won the race. Not our row
                // to send for; abort cleanly without re-throwing (which would
                // retry the job).
                Log::info('Reminder already sent (UNIQUE race)', [
                    'appointment_id' => $appointment->id,
                    'kind' => $kindEnum->value,
                ]);

                return;
            }

            $appointment->customer->notify(new AppointmentReminderNotification($appointment, $kindEnum));
        });
    }
}
