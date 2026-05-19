<?php

namespace App\Domain\Booking\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\ScheduleException;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AvailabilityService
{
    /** @return array<int,array{start:CarbonImmutable,end:CarbonImmutable}> */
    public function slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $windows = $this->windowsFor($doctor, $date);
        if ($windows === []) {
            return [];
        }

        $duration = $service->duration_minutes;
        $interval = $this->intervalFor($doctor, (int) $date->dayOfWeek);
        $now = CarbonImmutable::now()->addMinutes((int) config('clinic.booking_lead_minutes', 0));

        /** @var Collection<int,Appointment> $taken */
        $taken = $doctor->appointments()
            ->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed])
            ->whereDate('start_at', $date->toDateString())
            ->get(['start_at', 'end_at']);

        $slots = [];
        foreach ($windows as [$winStart, $winEnd]) {
            $cursor = $date->setTimeFromTimeString($winStart);
            $limit = $date->setTimeFromTimeString($winEnd);
            while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($limit)) {
                $slotStart = $cursor;
                $slotEnd = $cursor->addMinutes($duration);
                $overlaps = $taken->contains(
                    fn (Appointment $a) => $slotStart->lessThan($a->end_at) && $slotEnd->greaterThan($a->start_at)
                );
                if (! $overlaps && $slotStart->greaterThanOrEqualTo($now)) {
                    $slots[] = ['start' => $slotStart, 'end' => $slotEnd];
                }
                $cursor = $cursor->addMinutes($interval);
            }
        }

        return $slots;
    }

    /** @return array<int,array{0:string,1:string}> */
    private function windowsFor(DoctorProfile $doctor, CarbonImmutable $date): array
    {
        /** @var ScheduleException|null $exception */
        $exception = $doctor->scheduleExceptions()
            ->whereDate('date', $date->toDateString())->first();
        if ($exception) {
            if ($exception->type === 'closed') {
                return [];
            }
            if ($exception->type === 'custom_hours' && $exception->custom_start && $exception->custom_end) {
                return [[$exception->custom_start->format('H:i'), $exception->custom_end->format('H:i')]];
            }
        }

        /** @var DoctorSchedule|null $schedule */
        $schedule = $doctor->schedules()->where('weekday', (int) $date->dayOfWeek)->first();
        if (! $schedule) {
            return [];
        }
        $windows = [];
        if ($schedule->morning_enabled && $schedule->morning_start && $schedule->morning_end) {
            $windows[] = [$schedule->morning_start->format('H:i'), $schedule->morning_end->format('H:i')];
        }
        if ($schedule->evening_enabled && $schedule->evening_start && $schedule->evening_end) {
            $windows[] = [$schedule->evening_start->format('H:i'), $schedule->evening_end->format('H:i')];
        }

        return $windows;
    }

    private function intervalFor(DoctorProfile $doctor, int $weekday): int
    {
        return (int) ($doctor->schedules()->where('weekday', $weekday)->value('slot_interval_minutes') ?? 30);
    }
}
