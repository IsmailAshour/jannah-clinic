<?php

use App\Domain\MedicalRecord\Services\MedicalEntryService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalAuditLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;

function buildAppointmentForEntryService(User $customer): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);

    return Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => now()->subDay(),
        'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

beforeEach(function () {
    $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->appointment = buildAppointmentForEntryService($this->customer);

    $req = Request::create('/');
    $req->setUserResolver(fn () => $this->doctor);
    app()->instance('request', $req);
});

it('creates entry and audit row in one transaction', function () {
    $svc = app(MedicalEntryService::class);
    $entry = $svc->create($this->appointment, $this->doctor, [
        'visible_summary' => 'flu, rest',
        'staff_notes' => 'looks anxious',
    ]);

    expect($entry->appointment_id)->toBe($this->appointment->id)
        ->and($entry->author_id)->toBe($this->doctor->id)
        ->and($entry->visible_summary)->toBe('flu, rest');

    $audit = MedicalAuditLog::firstWhere('action', MedicalAuditAction::EntryCreated->value);
    expect($audit)->not->toBeNull()
        ->and($audit->changed_fields)->toEqualCanonicalizing(['visible_summary', 'staff_notes']);
});

it('update writes audit with only dirty fields', function () {
    $svc = app(MedicalEntryService::class);
    $entry = $svc->create($this->appointment, $this->doctor, [
        'visible_summary' => 'initial',
        'staff_notes' => null,
    ]);

    $svc->update($entry, ['visible_summary' => 'updated', 'staff_notes' => null]);

    $audit = MedicalAuditLog::firstWhere('action', MedicalAuditAction::EntryUpdated->value);
    expect($audit->changed_fields)->toBe(['visible_summary']);
});
