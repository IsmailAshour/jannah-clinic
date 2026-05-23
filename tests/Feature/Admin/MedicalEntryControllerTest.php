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

    return mkAppointment([
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

it('any doctor (assigned or not) can file a medical entry (ownership gate lifted 2026-05-21)', function () {
    $other = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'covering colleague note',
    ])->assertRedirect()->assertSessionHasNoErrors();
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

it('non-completed appointment can still get a medical entry (gate lifted 2026-05-21)', function () {
    $appt2 = mkP3Appointment($this->customer, $this->doctorProfile, AppointmentStatus::Requested);
    $this->actingAs($this->doctorUser)->post("/admin/appointments/{$appt2->id}/medical-entry", [
        'visible_summary' => 'pre-visit note',
    ])->assertRedirect()->assertSessionHasNoErrors();
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

it('GET create does NOT persist a stub entry', function () {
    $this->actingAs($this->doctorUser)
        ->get("/admin/appointments/{$this->appt->id}/medical-entry/create")
        ->assertOk();

    expect(MedicalEntry::where('appointment_id', $this->appt->id)->exists())->toBeFalse();
});

it('GET create renders Edit page in "new" mode', function () {
    $resp = $this->actingAs($this->doctorUser)
        ->get("/admin/appointments/{$this->appt->id}/medical-entry/create")
        ->assertOk();

    $props = $resp->viewData('page')['props'];
    expect($props['entry'])->toBeNull()
        ->and($props['appointment']['id'])->toBe($this->appt->id);
});

it('GET create redirects to edit when entry already exists', function () {
    $entry = MedicalEntry::create([
        'appointment_id' => $this->appt->id,
        'author_id' => $this->doctorUser->id,
        'visible_summary' => 'existing',
    ]);

    $this->actingAs($this->doctorUser)
        ->get("/admin/appointments/{$this->appt->id}/medical-entry/create")
        ->assertRedirect("/admin/medical-entries/{$entry->id}/edit");
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

it('save honours a same-origin return_to', function () {
    $resp = $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'note',
        'return_to' => "/admin/appointments/{$this->appt->id}",
    ]);

    $resp->assertRedirect("/admin/appointments/{$this->appt->id}");
});

it('save ignores a return_to pointing at another origin', function () {
    $resp = $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'note',
        'return_to' => 'https://evil.example.com/phish',
    ]);

    $resp->assertRedirect(); // falls back to the canonical edit page
    expect($resp->headers->get('Location'))->not->toContain('evil.example.com');
});

it('save ignores a protocol-relative return_to (//evil.com)', function () {
    $resp = $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'note',
        'return_to' => '//evil.example.com/phish',
    ]);

    expect($resp->headers->get('Location'))->not->toContain('evil.example.com');
});
