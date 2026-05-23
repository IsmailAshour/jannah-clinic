<?php

use App\Domain\MedicalRecord\Services\PrescriptionService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);

    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $appt = mkAppointment([
        'customer_id' => $this->customer->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $this->entry = MedicalEntry::create([
        'appointment_id' => $appt->id, 'author_id' => $this->doctor->id,
        'visible_summary' => 'x', 'staff_notes' => null,
    ]);

    $req = Request::create('/');
    $req->setUserResolver(fn () => $this->doctor);
    app()->instance('request', $req);
});

it('diffs prescriptions: creates, updates, deletes', function () {
    $svc = app(PrescriptionService::class);

    $p1 = Prescription::create([
        'medical_entry_id' => $this->entry->id,
        'medication_name' => 'Paracetamol', 'dosage' => '500mg',
        'frequency' => 'twice', 'duration' => '5 days', 'notes' => null,
    ]);

    $svc->syncForEntry($this->entry, [
        ['id' => $p1->id, 'medication_name' => 'Paracetamol', 'dosage' => '500mg',
            'frequency' => 'three times', 'duration' => '5 days', 'notes' => null],
        ['medication_name' => 'Ibuprofen', 'dosage' => '400mg',
            'frequency' => 'once', 'duration' => '3 days', 'notes' => null],
    ]);

    expect($this->entry->prescriptions()->count())->toBe(2)
        ->and($p1->fresh()->frequency)->toBe('three times');

    expect(MedicalAuditLog::where('action', 'prescription.updated')->count())->toBe(1)
        ->and(MedicalAuditLog::where('action', 'prescription.created')->count())->toBe(1);
});

it('deletes prescriptions not present in the desired set', function () {
    $svc = app(PrescriptionService::class);
    $p = Prescription::create([
        'medical_entry_id' => $this->entry->id,
        'medication_name' => 'X', 'dosage' => 'd', 'frequency' => 'f', 'duration' => 'du',
    ]);

    $svc->syncForEntry($this->entry, []);

    expect(Prescription::find($p->id))->toBeNull()
        ->and(MedicalAuditLog::where('action', 'prescription.deleted')->count())->toBe(1);
});
