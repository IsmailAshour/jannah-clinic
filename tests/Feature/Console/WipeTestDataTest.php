<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use App\Models\DoctorProfile;
use App\Models\MedicalAttachment;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('wipes all customer + appointment data while preserving staff + services + doctors', function () {
    // ---- Pre-fixture: staff (must survive) ----
    $mgr = User::factory()->create(['role' => UserRole::Manager]);
    $rec = User::factory()->create(['role' => UserRole::Receptionist]);
    $docUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doc = DoctorProfile::factory()->create(['user_id' => $docUser->id]);

    $cat = ServiceCategory::create([
        'name' => 'X', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand',
    ]);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's', 'base_price' => '100.00',
        'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc->services()->attach($svc->id);

    // ---- Test data (must die) ----
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
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

    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => $docUser->id,
        'visible_summary' => 'ok',
    ]);

    $attachment = MedicalAttachment::create([
        'appointment_id' => $appt->id,
        'file_path' => 'medical-attachments/'.$appt->id.'/lab.pdf',
        'original_filename' => 'lab.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $mgr->id,
    ]);
    Storage::disk('local')->put($attachment->file_path, 'PDF-CONTENT');

    $photo = AppointmentPhoto::create([
        'appointment_id' => $appt->id,
        'kind' => 'before',
        'file_path' => 'appointment-photos/'.$appt->id.'/before.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
        'uploaded_by' => $docUser->id,
    ]);
    Storage::disk('local')->put($photo->file_path, 'JPEG-CONTENT');

    $payment = Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => 'paid',
    ]);
    $receipt = PaymentReceipt::create([
        'payment_id' => $payment->id,
        'file_path' => 'payment-receipts/'.$payment->id.'/r.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
        'status' => 'verified',
        'uploaded_by' => $customer->id,
    ]);
    Storage::disk('local')->put($receipt->file_path, 'RECEIPT');

    DB::table('loyalty_ledger')->insert([
        'customer_id' => $customer->id,
        'points_delta' => 50,
        'balance_after' => 50,
        'reason' => 'earned_from_payment',
        'created_at' => now(),
    ]);

    // ---- Sanity: data IS there ----
    expect(Appointment::count())->toBe(1);
    expect(User::where('role', UserRole::Customer)->count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->file_path);

    // ---- Act ----
    $this->artisan('clinic:wipe-test-data', ['--force' => true])
        ->assertSuccessful();

    // ---- Assert: test data is gone ----
    expect(Appointment::count())->toBe(0);
    expect(User::where('role', UserRole::Customer)->count())->toBe(0);
    expect(MedicalEntry::count())->toBe(0);
    expect(MedicalAttachment::count())->toBe(0);
    expect(AppointmentPhoto::count())->toBe(0);
    expect(Payment::count())->toBe(0);
    expect(PaymentReceipt::count())->toBe(0);
    expect(DB::table('loyalty_ledger')->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->file_path);
    Storage::disk('local')->assertMissing($photo->file_path);
    Storage::disk('local')->assertMissing($receipt->file_path);

    // ---- Assert: configuration data survives ----
    // Identity checks rather than counts: the DoctorProfileFactory transitively
    // creates an extra Doctor user, so role counts aren't reliable here.
    expect(User::find($mgr->id))->not->toBeNull();
    expect(User::find($rec->id))->not->toBeNull();
    expect(User::find($docUser->id))->not->toBeNull();
    expect(DoctorProfile::find($doc->id))->not->toBeNull();
    expect(Service::find($svc->id))->not->toBeNull();
    expect(ServiceCategory::find($cat->id))->not->toBeNull();
});

it('refuses to run without --force or explicit confirmation', function () {
    User::factory()->create(['role' => UserRole::Customer]);

    // Without --force and without an interactive 'WIPE' confirmation, the
    // command must abort. We invoke without --force and answer 'no' to
    // the initial Yes/No, which the command treats as a clean abort.
    $this->artisan('clinic:wipe-test-data')
        ->expectsConfirmation('هل تريد المتابعة؟ (سيُطلَب منك تأكيد إضافيّ)', 'no')
        ->expectsOutput('تمّ الإلغاء.')
        ->assertSuccessful();

    expect(User::where('role', UserRole::Customer)->count())->toBe(1);
});
