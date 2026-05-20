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
use Illuminate\Support\Facades\DB;

function mkApptForPhi(): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    return Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

it('medical_entries.visible_summary is encrypted at rest', function () {
    $appt = mkApptForPhi();
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'PLAINTEXT-DIAGNOSIS-XYZ',
    ]);

    $raw = DB::table('medical_entries')->where('id', $entry->id)->value('visible_summary');
    expect($raw)->not->toContain('PLAINTEXT-DIAGNOSIS-XYZ');
    expect($entry->fresh()->visible_summary)->toBe('PLAINTEXT-DIAGNOSIS-XYZ');
});

it('medical_entries.staff_notes is encrypted at rest', function () {
    $appt = mkApptForPhi();
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'x',
        'staff_notes' => 'INTERNAL-STAFF-NOTE-ABC',
    ]);

    $raw = DB::table('medical_entries')->where('id', $entry->id)->value('staff_notes');
    expect($raw)->not->toContain('INTERNAL-STAFF-NOTE-ABC');
});

it('prescriptions.medication_name and dosage are encrypted at rest', function () {
    $appt = mkApptForPhi();
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
        'visible_summary' => 'x',
    ]);
    $p = Prescription::create([
        'medical_entry_id' => $entry->id,
        'medication_name' => 'UNIQUE-MED-77',
        'dosage' => 'DOSAGE-XX-99',
        'frequency' => 'f', 'duration' => 'du',
    ]);

    $row = DB::table('prescriptions')->where('id', $p->id)->first();
    expect($row->medication_name)->not->toContain('UNIQUE-MED-77');
    expect($row->dosage)->not->toContain('DOSAGE-XX-99');
});

it('customer_profiles chronic_conditions and allergies are encrypted at rest', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    $profile = CustomerProfile::create([
        'user_id' => $user->id,
        'chronic_conditions' => 'CHRONIC-MARKER-1',
        'allergies' => 'ALLERGY-MARKER-2',
        'notes' => 'NOTE-MARKER-3',
    ]);

    $row = DB::table('customer_profiles')->where('id', $profile->id)->first();
    expect($row->chronic_conditions)->not->toContain('CHRONIC-MARKER-1');
    expect($row->allergies)->not->toContain('ALLERGY-MARKER-2');
    expect($row->notes)->not->toContain('NOTE-MARKER-3');
});
