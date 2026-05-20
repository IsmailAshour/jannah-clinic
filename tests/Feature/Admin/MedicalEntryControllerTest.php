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

function mkP3Appointment(User $customer, DoctorProfile $doctorProfile, AppointmentStatus $status = AppointmentStatus::Completed): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
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
    $this->appt = mkP3Appointment($this->customer, $this->doctorProfile);
});

it('assigned doctor can create an entry with prescriptions', function () {
    $resp = $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'flu',
        'staff_notes' => 'anxious',
        'prescriptions' => [
            ['medication_name' => 'Para', 'dosage' => '500mg', 'frequency' => 'twice', 'duration' => '5d', 'notes' => null],
        ],
    ]);

    $resp->assertRedirect()->assertSessionHasNoErrors();
    expect(MedicalEntry::where('appointment_id', $this->appt->id)->exists())->toBeTrue();
});

it('unassigned doctor gets 403', function () {
    $other = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('receptionist gets 403 via role middleware', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('customer gets 403', function () {
    $this->actingAs($this->customer)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('empty visible_summary returns 422', function () {
    $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => '',
    ])->assertSessionHasErrors('visible_summary');
});

it('non-completed appointment cannot get a medical entry', function () {
    $appt2 = mkP3Appointment($this->customer, $this->doctorProfile, AppointmentStatus::Requested);
    $this->actingAs($this->doctorUser)->post("/admin/appointments/{$appt2->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('doctor author can update own entry', function () {
    $entry = MedicalEntry::create([
        'appointment_id' => $this->appt->id,
        'author_id' => $this->doctorUser->id,
        'visible_summary' => 'initial',
    ]);

    $this->actingAs($this->doctorUser)->put("/admin/medical-entries/{$entry->id}", [
        'visible_summary' => 'updated diagnosis',
        'prescriptions' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($entry->fresh()->visible_summary)->toBe('updated diagnosis');
});

it('different doctor cannot update entry', function () {
    $entry = MedicalEntry::create([
        'appointment_id' => $this->appt->id,
        'author_id' => $this->doctorUser->id,
        'visible_summary' => 'initial',
    ]);
    $other = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)->put("/admin/medical-entries/{$entry->id}", [
        'visible_summary' => 'hijacked',
        'prescriptions' => [],
    ])->assertForbidden();
});
