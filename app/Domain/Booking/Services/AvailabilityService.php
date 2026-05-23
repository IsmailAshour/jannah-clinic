<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Slots\SlotGrid;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleException;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AvailabilityService
{
    /**
     * Single-service back-compat — delegates to slotsForServices with a
     * one-element array.
     *
     * @return array<int,array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    public function slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date): array
    {
        return $this->slotsForServices($doctor, [$service], $date);
    }

    /**
     * Multi-service slot search. Duration = sum of services'
     * duration_minutes; slot count = ceil(totalDuration / slotMinutes).
     *
     * @param  iterable<Service>  $services
     * @return array<int,array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    public function slotsForServices(DoctorProfile $doctor, iterable $services, CarbonImmutable $date): array
    {
        $totalDuration = 0;
        foreach ($services as $s) {
            /** @var Service $s */
            $totalDuration += (int) $s->duration_minutes;
        }
        if ($totalDuration <= 0) {
            return [];
        }
        $slotMinutes = (int) config('clinic.slot_minutes', 30);
        $need = max(1, (int) ceil($totalDuration / $slotMinutes));

        $date = $date->startOfDay();
        $enabled = $this->enabledFor($doctor, $date);
        if ($enabled === []) {
            return [];
        }

        $now = CarbonImmutable::now()->addMinutes((int) config('clinic.booking_lead_minutes', 0));

        /** @var Collection<int,Appointment> $taken */
        $taken = $doctor->appointments()
            ->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed])
            ->whereDate('start_at', $date->toDateString())
            ->get(['start_at', 'end_at']);

        $enabledSet = array_flip($enabled);
        $slots = [];
        foreach (SlotGrid::all() as $s) {
            $block = SlotGrid::blockFrom($s, $need);
            if ($block === null) {
                continue;
            }
            $allEnabled = true;
            foreach ($block as $b) {
                if (! isset($enabledSet[$b])) {
                    $allEnabled = false;
                    break;
                }
            }
            if (! $allEnabled) {
                continue;
            }
            $start = $date->setTimeFromTimeString($s);
            $end = $start->addMinutes($totalDuration);
            if ($start->lessThan($now)) {
                continue;
            }
            $overlaps = $taken->contains(
                fn ($a) => $start->lessThan($a->end_at) && $end->greaterThan($a->start_at)
            );
            if (! $overlaps) {
                $slots[] = ['start' => $start, 'end' => $end];
            }
        }

        return $slots;
    }

    /** @return list<string> 'Y-m-d' dates in [$from,$to] (inclusive) that have >=1 bookable slot */
    public function availableDatesFor(DoctorProfile $doctor, Service $service, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->availableDatesForServices($doctor, [$service], $from, $to);
    }

    /**
     * @param  iterable<Service>  $services
     * @return list<string>
     */
    public function availableDatesForServices(DoctorProfile $doctor, iterable $services, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // iterable may be a generator — collect to a list once.
        $svcList = [];
        foreach ($services as $s) {
            $svcList[] = $s;
        }
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $out = [];
        for ($d = $from; $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
            if ($this->slotsForServices($doctor, $svcList, $d) !== []) {
                $out[] = $d->toDateString();
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function enabledFor(DoctorProfile $doctor, CarbonImmutable $date): array
    {
        /** @var ScheduleException|null $ex */
        $ex = $doctor->scheduleExceptions()->whereDate('date', $date->toDateString())->first();
        if ($ex) {
            if ($ex->type === 'closed') {
                return [];
            }
            if ($ex->type === 'custom') {
                /** @var list<string> $custom */
                $custom = $ex->slots()->pluck('slot_start')->all();

                return $custom;
            }
        }

        /** @var list<string> $weekly */
        $weekly = $doctor->scheduleSlots()
            ->where('weekday', (int) $date->dayOfWeek)
            ->pluck('slot_start')->all();

        return $weekly;
    }
}
