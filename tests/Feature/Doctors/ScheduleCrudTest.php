<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;

it('saves a weekly schedule row for a doctor', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [[
            'weekday' => 1, 'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '12:00',
            'evening_enabled' => false, 'slot_interval_minutes' => 30,
        ]],
    ])->assertRedirect();
    expect(DoctorSchedule::where('doctor_profile_id', $doc->id)->where('weekday', 1)->exists())->toBeTrue();
});

it('rejects an out-of-range weekday', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [['weekday' => 9, 'slot_interval_minutes' => 30]],
    ])->assertSessionHasErrors('schedules.0.weekday');
});

it('upserts (one row per doctor+weekday)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $payload = ['schedules' => [['weekday' => 2, 'morning_enabled' => true, 'morning_start' => '08:00', 'morning_end' => '10:00', 'evening_enabled' => false, 'slot_interval_minutes' => 20]]];
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", $payload)->assertRedirect();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", $payload)->assertRedirect();
    expect(DoctorSchedule::where('doctor_profile_id', $doc->id)->where('weekday', 2)->count())->toBe(1);
});

it('adds and deletes a schedule exception', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeek()->toDateString();
    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", ['date' => $date, 'type' => 'closed'])->assertRedirect();
    expect($doc->scheduleExceptions()->count())->toBe(1);
    $ex = $doc->scheduleExceptions()->first();
    $this->actingAs($m)->delete("/admin/doctors/{$doc->id}/exceptions/{$ex->id}")->assertRedirect();
    expect($doc->scheduleExceptions()->count())->toBe(0);
});

it('forbids a non-manager staff from saving a schedule', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($r)->put("/admin/doctors/{$doc->id}/schedule", ['schedules' => []])->assertForbidden();
});

it('lets any staff view the schedule page', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($r)->get("/admin/doctors/{$doc->id}/schedule")->assertOk();
});

it('round-trips time format as H:i', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [[
            'weekday' => 3, 'morning_enabled' => true, 'morning_start' => '09:00', 'morning_end' => '12:00',
            'evening_enabled' => false, 'slot_interval_minutes' => 30,
        ]],
    ])->assertRedirect();
    $row = DoctorSchedule::where('doctor_profile_id', $doc->id)->where('weekday', 3)->firstOrFail();
    expect($row->morning_start->format('H:i'))->toBe('09:00');
    expect($row->morning_end->format('H:i'))->toBe('12:00');
});

it('rejects enabled morning window without times', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [[
            'weekday' => 1, 'morning_enabled' => true, 'morning_start' => null, 'morning_end' => null,
            'evening_enabled' => false, 'slot_interval_minutes' => 30,
        ]],
    ])->assertSessionHasErrors('schedules.0.morning_start');
});

it('rejects morning_end not after morning_start', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [[
            'weekday' => 1, 'morning_enabled' => true, 'morning_start' => '12:00', 'morning_end' => '09:00',
            'evening_enabled' => false, 'slot_interval_minutes' => 30,
        ]],
    ])->assertSessionHasErrors('schedules.0.morning_end');
});

it('rejects custom_hours exception without custom_start', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(2)->toDateString();
    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom_hours', 'custom_start' => null, 'custom_end' => null,
    ])->assertSessionHasErrors('custom_start');
});

it('rejects custom_hours exception with custom_end before custom_start', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(3)->toDateString();
    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom_hours', 'custom_start' => '14:00', 'custom_end' => '10:00',
    ])->assertSessionHasErrors('custom_end');
});
