<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

function mkPortalApp(User $customer, AppointmentStatus $status = AppointmentStatus::Completed): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);

    return Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
        'status' => $status, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

it('customer sees own entries; staff_notes never in payload', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id, 'chronic_conditions' => 'asthma']);
    $appt = mkPortalApp($customer);
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'flu, rest',
        'staff_notes' => 'SECRET ANXIETY NOTE',
    ]);
    Prescription::create([
        'medical_entry_id' => $entry->id,
        'medication_name' => 'Para', 'dosage' => '500mg',
        'frequency' => 'twice', 'duration' => '5d',
    ]);

    $resp = $this->actingAs($customer)->get('/portal/medical-record')->assertOk();
    $payload = json_encode($resp->viewData('page')['props']);

    expect($payload)->toContain('flu, rest')
        ->and($payload)->toContain('Para')
        ->and($payload)->not->toContain('SECRET ANXIETY NOTE');
});

it('another customer cannot see this record (404)', function () {
    $a = User::factory()->create(['role' => UserRole::Customer]);
    $b = User::factory()->create(['role' => UserRole::Customer]);
    $appt = mkPortalApp($a);
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'private',
    ]);

    $this->actingAs($b)->get("/portal/medical-record/entries/{$entry->id}")->assertNotFound();
});

it('customer can view own entry detail', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $appt = mkPortalApp($customer);
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'detail page text',
        'staff_notes' => 'SECRET',
    ]);

    $resp = $this->actingAs($customer)->get("/portal/medical-record/entries/{$entry->id}")->assertOk();
    $payload = json_encode($resp->viewData('page')['props']);
    expect($payload)->toContain('detail page text')->and($payload)->not->toContain('SECRET');
});
