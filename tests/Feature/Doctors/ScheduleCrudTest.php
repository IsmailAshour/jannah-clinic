<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\DoctorScheduleSlot;
use App\Models\ScheduleException;
use App\Models\ScheduleExceptionSlot;
use App\Models\User;

it('lets a manager view the schedule page', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->get("/admin/doctors/{$doc->id}/schedule")->assertOk();
});

it('lets any staff view the schedule page (non-manager read allowed)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($r)->get("/admin/doctors/{$doc->id}/schedule")->assertOk();
});

it('saves weekly slots for the given weekdays and nothing else', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [1 => ['09:00', '09:30'], 3 => ['17:00']],
    ])->assertRedirect();

    $rows = DoctorScheduleSlot::where('doctor_profile_id', $doc->id)
        ->orderBy('weekday')->orderBy('slot_start')
        ->get(['weekday', 'slot_start'])
        ->map(fn ($r) => [$r->weekday, $r->slot_start])->all();

    expect($rows)->toBe([
        [1, '09:00'],
        [1, '09:30'],
        [3, '17:00'],
    ]);
});

it('replaces a weekday set on re-save (no stale rows)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [1 => ['09:00', '09:30']],
    ])->assertRedirect();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [1 => ['10:00']],
    ])->assertRedirect();

    $rows = DoctorScheduleSlot::where('doctor_profile_id', $doc->id)
        ->where('weekday', 1)->pluck('slot_start')->sort()->values()->all();

    expect($rows)->toBe(['10:00']);
});

it('dedupes repeated slot values for a weekday', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [2 => ['09:00', '09:00', '09:30']],
    ])->assertRedirect();

    expect(DoctorScheduleSlot::where('doctor_profile_id', $doc->id)->where('weekday', 2)->count())->toBe(2);
});

it('rejects an off-grid slot value and persists nothing', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [1 => ['08:15']],
    ])->assertSessionHasErrors('schedule');

    expect(DoctorScheduleSlot::where('doctor_profile_id', $doc->id)->count())->toBe(0);
});

it('rejects a non-canonical slot string (9:00) and persists nothing', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [1 => ['9:00']],
    ])->assertSessionHasErrors('schedule');

    expect(DoctorScheduleSlot::where('doctor_profile_id', $doc->id)->count())->toBe(0);
});

it('rejects an out-of-range weekday key', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();

    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'slots' => [7 => ['09:00']],
    ])->assertSessionHasErrors('schedule');

    expect(DoctorScheduleSlot::where('doctor_profile_id', $doc->id)->count())->toBe(0);
});

it('forbids a non-manager staff from saving a schedule', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($r)->put("/admin/doctors/{$doc->id}/schedule", ['slots' => []])->assertForbidden();
});

it('adds a closed exception with zero slot rows', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeek()->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'closed', 'note' => 'إجازة',
    ])->assertRedirect();

    $ex = $doc->scheduleExceptions()->firstOrFail();
    expect($ex->type)->toBe('closed');
    expect($ex->note)->toBe('إجازة');
    expect($ex->slots()->count())->toBe(0);
});

it('adds a custom exception with its slots', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(2)->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom', 'slots' => ['09:00', '09:30'],
    ])->assertRedirect();

    $ex = $doc->scheduleExceptions()->firstOrFail();
    expect($ex->type)->toBe('custom');
    expect($ex->slots()->orderBy('slot_start')->pluck('slot_start')->all())->toBe(['09:00', '09:30']);
});

it('updateOrCreate replaces an exception and its slots on the same date', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(3)->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom', 'slots' => ['09:00', '09:30'],
    ])->assertRedirect();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'closed',
    ])->assertRedirect();

    expect($doc->scheduleExceptions()->count())->toBe(1);
    $ex = $doc->scheduleExceptions()->firstOrFail();
    expect($ex->type)->toBe('closed');
    expect($ex->slots()->count())->toBe(0);
});

it('rejects a custom exception with an off-grid slot and persists nothing', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(4)->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom', 'slots' => ['08:15'],
    ])->assertSessionHasErrors('slots');

    expect($doc->scheduleExceptions()->count())->toBe(0);
});

it('rejects a custom exception with no slots (required_if)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(5)->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom',
    ])->assertSessionHasErrors('slots');

    expect($doc->scheduleExceptions()->count())->toBe(0);
});

it('rejects an invalid exception type', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeeks(6)->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom_hours',
    ])->assertSessionHasErrors('type');

    expect($doc->scheduleExceptions()->count())->toBe(0);
});

it('forbids a non-manager staff from adding an exception', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($r)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => now()->addWeek()->toDateString(), 'type' => 'closed',
    ])->assertForbidden();
});

it('deletes an exception and cascades its slots', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $date = now()->addWeek()->toDateString();

    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date' => $date, 'type' => 'custom', 'slots' => ['09:00', '09:30'],
    ])->assertRedirect();

    $ex = $doc->scheduleExceptions()->firstOrFail();
    $exId = $ex->id;

    $this->actingAs($m)->delete("/admin/doctors/{$doc->id}/exceptions/{$ex->id}")->assertRedirect();

    expect($doc->scheduleExceptions()->count())->toBe(0);
    expect(ScheduleExceptionSlot::where('schedule_exception_id', $exId)->count())->toBe(0);
});

it('returns 404 when deleting another doctor exception (ownership guard)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $docA = DoctorProfile::factory()->create();
    $docB = DoctorProfile::factory()->create();

    $ex = ScheduleException::create([
        'doctor_profile_id' => $docB->id,
        'date' => now()->addWeek()->toDateString(),
        'type' => 'closed',
    ]);

    $this->actingAs($m)->delete("/admin/doctors/{$docA->id}/exceptions/{$ex->id}")->assertNotFound();
    expect(ScheduleException::whereKey($ex->id)->exists())->toBeTrue();
});
