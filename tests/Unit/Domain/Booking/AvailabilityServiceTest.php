<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\ScheduleException;
use App\Models\ScheduleExceptionSlot;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function mkService(int $dur = 30): Service
{
    if (! in_array($dur, [30, 60], true)) {
        throw new InvalidArgumentException('mkService duration must be 30 or 60');
    }
    $c = ServiceCategory::create(['name' => 'x', 'slug' => uniqid(), 'color_variant' => 'brand']);

    return Service::create(['category_id' => $c->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => $dur]);
}

it('generates 30-min slots for each enabled grid start', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['09:00', '09:30', '10:00']);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    expect(count($slots))->toBe(3);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[1]['start']->format('H:i'))->toBe('09:30');
    expect($slots[2]['start']->format('H:i'))->toBe('10:00');
});

it('returns no slots on a closed exception day even though weekly slots exist', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['09:00', '09:30']);
    ScheduleException::create([
        'doctor_profile_id' => $doc->id,
        'date' => $date->toDateString(),
        'type' => 'closed',
    ]);

    expect(app(AvailabilityService::class)->slotsFor($doc, $svc, $date))->toBe([]);
});

it('custom exception overrides weekly slots', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['09:00']);
    $ex = ScheduleException::create([
        'doctor_profile_id' => $doc->id,
        'date' => $date->toDateString(),
        'type' => 'custom',
    ]);
    foreach (['14:00', '14:30'] as $s) {
        ScheduleExceptionSlot::create(['schedule_exception_id' => $ex->id, 'slot_start' => $s]);
    }

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    expect(count($slots))->toBe(2);
    expect($slots[0]['start']->format('H:i'))->toBe('14:00');
    expect($slots[1]['start']->format('H:i'))->toBe('14:30');
    $starts = array_map(fn ($x) => $x['start']->format('H:i'), $slots);
    expect($starts)->not->toContain('09:00');
});

it('a 60-min service requires two contiguous enabled grid slots', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(60);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    // gap at 10:00 -> only 09:00 yields a valid 60-min block (09:00 + 09:30)
    enableDoctorSlots($doc, $wd, ['09:00', '09:30', '10:30']);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[0]['end']->format('H:i'))->toBe('10:00');
});

it('a 60-min confirmed appointment blocks the overlapping slot but a later slot is offered', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(60);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    // 09:00..11:00 (5 grid starts) so a 10:00–11:00 block (10:00 + 10:30) is valid
    enableDoctorSlots($doc, $wd, slotRange('09:00', 5));
    Appointment::create([
        'customer_id' => User::factory()->create()->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => $date->setTime(9, 0),
        'end_at' => $date->setTime(10, 0),
        'status' => AppointmentStatus::Confirmed,
        'price_at_booking' => 100,
        'delivery_mode' => 'center',
        'created_by_role' => 'customer',
    ]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    $starts = array_map(fn ($x) => $x['start']->format('H:i'), $slots);

    expect($starts)->not->toContain('09:00');
    expect($starts)->toContain('10:00');
});

it('a cancelled appointment does not block its slot', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['09:00']);
    Appointment::create([
        'customer_id' => User::factory()->create()->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => $date->setTime(9, 0),
        'end_at' => $date->setTime(9, 30),
        'status' => AppointmentStatus::Cancelled,
        'price_at_booking' => 100,
        'delivery_mode' => 'center',
        'created_by_role' => 'customer',
    ]);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
});

it('excludes slots that start in the past', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $today = CarbonImmutable::now();
    $wd = (int) $today->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['08:00', '21:30']);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $today);

    // Every returned slot must start at or after "now" — and an early 08:00 slot
    // is guaranteed in the past on a same-day query, so it must never appear.
    $starts = array_map(fn ($x) => $x['start'], $slots);
    foreach ($starts as $start) {
        expect($start->greaterThanOrEqualTo($today))->toBeTrue();
    }
    expect(collect($starts)->contains(fn ($s) => $s->equalTo($today->setTimeFromTimeString('08:00'))))->toBeFalse();
});

it('honours the last 60-min start boundary at 21:00', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(60);
    $date = CarbonImmutable::parse('next monday');
    $wd = (int) $date->dayOfWeek;
    enableDoctorSlots($doc, $wd, ['21:00', '21:30']);

    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);

    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('21:00');
    expect($slots[0]['end']->format('H:i'))->toBe('22:00');
    $starts = array_map(fn ($x) => $x['start']->format('H:i'), $slots);
    expect($starts)->not->toContain('21:30');
});
