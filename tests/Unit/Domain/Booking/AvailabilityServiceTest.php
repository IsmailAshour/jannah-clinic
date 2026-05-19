<?php

use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function mkService(int $dur = 30): Service
{
    $c = ServiceCategory::create(['name' => 'x', 'slug' => uniqid(), 'color_variant' => 'brand']);

    return Service::create(['category_id' => $c->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => $dur]);
}

it('generates morning slots at the interval, fitting the duration', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday')->setTime(0, 0);
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '10:00',
        'evening_enabled' => false, 'slot_interval_minutes' => 30,
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(2);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[1]['start']->format('H:i'))->toBe('09:30');
});

it('returns no slots on a closed exception day', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService();
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek, 'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '12:00', 'evening_enabled' => false, 'slot_interval_minutes' => 30]);
    $doc->scheduleExceptions()->create(['date' => $date->toDateString(), 'type' => 'closed']);
    expect(app(AvailabilityService::class)->slotsFor($doc, $svc, $date))->toBe([]);
});

it('excludes a slot already taken by a non-terminal appointment', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek, 'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '10:00', 'evening_enabled' => false, 'slot_interval_minutes' => 30]);
    Appointment::create([
        'customer_id' => User::factory()->create()->id, 'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id, 'start_at' => $date->setTime(9, 0), 'end_at' => $date->setTime(9, 30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => 100, 'delivery_mode' => 'center',
        'created_by_role' => 'customer',
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('09:30');
});

it('does not exclude a slot for a cancelled appointment', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek, 'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '09:30', 'evening_enabled' => false, 'slot_interval_minutes' => 30]);
    Appointment::create(['customer_id' => User::factory()->create()->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id, 'start_at' => $date->setTime(9, 0), 'end_at' => $date->setTime(9, 30), 'status' => AppointmentStatus::Cancelled, 'price_at_booking' => 100, 'delivery_mode' => 'center', 'created_by_role' => 'customer']);
    expect(count(app(AvailabilityService::class)->slotsFor($doc, $svc, $date)))->toBe(1);
});

it('excludes slots that start in the past', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $today = CarbonImmutable::now();
    DoctorSchedule::create(['doctor_profile_id' => $doc->id, 'weekday' => (int) $today->dayOfWeek, 'morning_enabled' => true, 'morning_start' => '00:00', 'morning_end' => '23:59', 'evening_enabled' => false, 'slot_interval_minutes' => 30]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $today);
    foreach ($slots as $s) {
        expect($s['start']->greaterThanOrEqualTo($today))->toBeTrue();
    }
});

it('custom_hours exception overrides the weekly schedule windows', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday')->setTime(0, 0);
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '12:00',
        'evening_enabled' => false, 'slot_interval_minutes' => 30,
    ]);
    $doc->scheduleExceptions()->create([
        'date' => $date->toDateString(), 'type' => 'custom_hours',
        'custom_start' => '14:00', 'custom_end' => '15:00',
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(2);
    expect($slots[0]['start']->format('H:i'))->toBe('14:00');
    expect($slots[1]['start']->format('H:i'))->toBe('14:30');
});

// TG1 — slot exactly fills window: verifies the <= in the while-condition
it('TG1: emits exactly one slot when it exactly fills the window', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday')->setTime(0, 0);
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '09:30',
        'evening_enabled' => false, 'slot_interval_minutes' => 30,
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[0]['end']->format('H:i'))->toBe('09:30');
});

// TG2 — interval < duration: documents intended overlapping-start behavior (to be reviewed in T8)
it('TG2: interval shorter than duration produces overlapping slot starts', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday')->setTime(0, 0);
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '10:00',
        'evening_enabled' => false, 'slot_interval_minutes' => 15,
    ]);
    // 09:00+30=09:30 ≤ 10:00 ✓; 09:15+30=09:45 ≤ 10:00 ✓; 09:30+30=10:00 ≤ 10:00 ✓; 09:45+30=10:15 > 10:00 ✗
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(3);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[1]['start']->format('H:i'))->toBe('09:15');
    expect($slots[2]['start']->format('H:i'))->toBe('09:30');
});

// TG3 — morning + evening union: verifies windows-union loop and contiguous indexing
it('TG3: morning and evening windows both contribute slots in order', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday')->setTime(0, 0);
    DoctorSchedule::create([
        'doctor_profile_id' => $doc->id, 'weekday' => (int) $date->dayOfWeek,
        'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '10:00',
        'evening_enabled' => true, 'evening_start' => '17:00', 'evening_end' => '18:00',
        'slot_interval_minutes' => 30,
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(4);
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[1]['start']->format('H:i'))->toBe('09:30');
    expect($slots[2]['start']->format('H:i'))->toBe('17:00');
    expect($slots[3]['start']->format('H:i'))->toBe('17:30');
});
