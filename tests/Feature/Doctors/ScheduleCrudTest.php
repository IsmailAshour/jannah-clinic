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
