<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalEntry;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

function mkAppt(User $customer, DoctorProfile $doctorProfile, AppointmentStatus $status): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 'استشارة',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doctorProfile->services()->attach($svc->id);

    return Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctorProfile->id,
        'service_id' => $svc->id,
        'start_at' => now()->subDay(),
        'end_at' => now()->subDay()->addMinutes(30),
        'status' => $status,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

beforeEach(function () {
    $this->doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $this->doctorProfile = DoctorProfile::factory()->create(['user_id' => $this->doctorUser->id]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
});

it('doctor sees their completed-no-entry appointments as addable', function () {
    $completed = mkAppt($this->customer, $this->doctorProfile, AppointmentStatus::Completed);

    $resp = $this->actingAs($this->doctorUser)->get("/admin/customers/{$this->customer->id}")->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['addableAppointments'])->toHaveCount(1)
        ->and($props['addableAppointments'][0]['id'])->toBe($completed->id);
});

it('completed appointment with existing entry is excluded', function () {
    $appt = mkAppt($this->customer, $this->doctorProfile, AppointmentStatus::Completed);
    MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => $this->doctorUser->id,
        'visible_summary' => 'x',
    ]);

    $resp = $this->actingAs($this->doctorUser)->get("/admin/customers/{$this->customer->id}")->assertOk();
    expect($resp->viewData('page')['props']['addableAppointments'])->toBe([]);
});

it('non-completed appointments are excluded', function () {
    mkAppt($this->customer, $this->doctorProfile, AppointmentStatus::Confirmed);

    $resp = $this->actingAs($this->doctorUser)->get("/admin/customers/{$this->customer->id}")->assertOk();
    expect($resp->viewData('page')['props']['addableAppointments'])->toBe([]);
});

it('appointments assigned to a different doctor are excluded', function () {
    $otherDoctor = User::factory()->create(['role' => UserRole::Doctor]);
    $otherProfile = DoctorProfile::factory()->create(['user_id' => $otherDoctor->id]);
    mkAppt($this->customer, $otherProfile, AppointmentStatus::Completed);

    $resp = $this->actingAs($this->doctorUser)->get("/admin/customers/{$this->customer->id}")->assertOk();
    expect($resp->viewData('page')['props']['addableAppointments'])->toBe([]);
});

it('manager sees an empty addableAppointments list', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    mkAppt($this->customer, $this->doctorProfile, AppointmentStatus::Completed);

    $resp = $this->actingAs($manager)->get("/admin/customers/{$this->customer->id}")->assertOk();
    expect($resp->viewData('page')['props']['addableAppointments'])->toBe([]);
});

it('receptionist sees an empty addableAppointments list', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    mkAppt($this->customer, $this->doctorProfile, AppointmentStatus::Completed);

    $resp = $this->actingAs($r)->get("/admin/customers/{$this->customer->id}")->assertOk();
    expect($resp->viewData('page')['props']['addableAppointments'])->toBe([]);
});
