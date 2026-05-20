<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('full doctor->customer medical record flow with audit trail', function () {
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor, 'name' => 'د. سارة']);
    $doctorProfile = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد']);

    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 'استشارة',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doctorProfile->services()->attach($svc->id);

    $appt = Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctorProfile->id,
        'service_id' => $svc->id,
        'start_at' => now()->subDay(),
        'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);

    // 1. Doctor visits create page — no stub written
    $this->actingAs($doctorUser)
        ->get("/admin/appointments/{$appt->id}/medical-entry/create")
        ->assertOk();
    expect(MedicalEntry::count())->toBe(0);

    // 2. Doctor submits the form
    $this->actingAs($doctorUser)
        ->post("/admin/appointments/{$appt->id}/medical-entry", [
            'visible_summary' => 'إنفلونزا موسمية. راحة + سوائل.',
            'staff_notes' => 'يبدو قلقاً — تواصل بعد ٣ أيام',
            'prescriptions' => [
                ['medication_name' => 'باراسيتامول', 'dosage' => '500 ملغ',
                    'frequency' => 'كل ٨ ساعات', 'duration' => '٥ أيام', 'notes' => null],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

    $entry = MedicalEntry::firstWhere('appointment_id', $appt->id);
    expect($entry)->not->toBeNull()
        ->and($entry->visible_summary)->toBe('إنفلونزا موسمية. راحة + سوائل.')
        ->and($entry->staff_notes)->toBe('يبدو قلقاً — تواصل بعد ٣ أيام')
        ->and($entry->prescriptions()->count())->toBe(1);

    // 3. Customer fetches portal index — sees visible_summary + prescription, NOT staff_notes
    $resp = $this->actingAs($customer)->get('/portal/medical-record')->assertOk();
    $payload = json_encode($resp->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

    expect($payload)->toContain('إنفلونزا موسمية')
        ->and($payload)->toContain('باراسيتامول')
        ->and($payload)->not->toContain('يبدو قلقاً');

    // 4. Customer opens entry detail — same constraint
    $detail = $this->actingAs($customer)->get("/portal/medical-record/entries/{$entry->id}")->assertOk();
    $detailPayload = json_encode($detail->viewData('page')['props'], JSON_UNESCAPED_UNICODE);
    expect($detailPayload)->toContain('إنفلونزا موسمية')
        ->and($detailPayload)->not->toContain('يبدو قلقاً');

    // 5. Audit log captures the chain
    $actions = MedicalAuditLog::query()
        ->where('customer_id', $customer->id)
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($actions)->toContain(MedicalAuditAction::EntryCreated->value)
        ->and($actions)->toContain(MedicalAuditAction::PrescriptionCreated->value)
        ->and($actions)->toContain(MedicalAuditAction::EntryViewed->value);

    // 6. Audit rows for write events were authored by the doctor
    $createAudit = MedicalAuditLog::firstWhere('action', MedicalAuditAction::EntryCreated->value);
    expect($createAudit->user_id)->toBe($doctorUser->id);

    // 7. Audit row for view event was authored by the customer
    $viewAudits = MedicalAuditLog::where('action', MedicalAuditAction::EntryViewed->value)->get();
    expect($viewAudits->pluck('user_id')->all())->toContain($customer->id);
});
