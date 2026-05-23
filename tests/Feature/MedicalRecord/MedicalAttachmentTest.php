<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalAttachment;
use App\Models\MedicalEntry;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function mkAttachmentFixtures(): array
{
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $cat = ServiceCategory::create([
        'name' => 'c'.uniqid(),
        'slug' => 'c'.uniqid(),
        'color_variant' => 'brand',
    ]);
    $svc = Service::create([
        'category_id' => $cat->id,
        'name' => 'استشارة',
        'base_price' => '100.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
    ]);
    $doctor->services()->attach($svc->id);

    $appt = Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctor->id,
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
        'author_id' => $doctorUser->id,
        'visible_summary' => 'صحّة جيّدة.',
    ]);

    return compact('manager', 'doctorUser', 'customer', 'entry');
}

beforeEach(function () {
    Storage::fake('local');
});

it('manager can upload a PDF attachment', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab-results.pdf', 200, 'application/pdf');

    $this->actingAs($mgr)
        ->post("/admin/medical-entries/{$entry->id}/attachments", [
            'file' => $file,
            'title' => 'تحليل دم',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('medical_attachments', [
        'medical_entry_id' => $entry->id,
        'title' => 'تحليل دم',
        'original_filename' => 'lab-results.pdf',
        'uploaded_by' => $mgr->id,
    ]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();
    Storage::disk('local')->assertExists($att->file_path);
});

it('doctor can upload an image attachment', function () {
    ['doctorUser' => $doc, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->image('scan.jpg', 800, 600);

    $this->actingAs($doc)
        ->post("/admin/medical-entries/{$entry->id}/attachments", [
            'file' => $file,
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('medical_attachments', 1);
});

it('receptionist cannot upload', function () {
    $fix = mkAttachmentFixtures();
    $rec = User::factory()->create(['role' => UserRole::Receptionist]);
    $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');

    $this->actingAs($rec)
        ->post("/admin/medical-entries/{$fix['entry']->id}/attachments", [
            'file' => $file,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('medical_attachments', 0);
});

it('customer cannot upload', function () {
    ['customer' => $customer, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');

    $this->actingAs($customer)
        ->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file])
        ->assertForbidden();
});

it('rejects executable file uploads', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $this->actingAs($mgr)
        ->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('manager can stream a file from the admin route', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    $this->actingAs($mgr)
        ->get("/admin/medical-entries/{$entry->id}/attachments/{$att->id}/file")
        ->assertOk();
});

it('receptionist cannot stream a file from admin', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    $rec = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($rec)
        ->get("/admin/medical-entries/{$entry->id}/attachments/{$att->id}/file")
        ->assertForbidden();
});

it('owning customer can stream their attachment from portal', function () {
    ['manager' => $mgr, 'customer' => $customer, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    $this->actingAs($customer)
        ->get("/portal/medical-record/entries/{$entry->id}/attachments/{$att->id}/file")
        ->assertOk();
});

it('non-owning customer cannot stream another customers attachment', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    $intruder = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($intruder)
        ->get("/portal/medical-record/entries/{$entry->id}/attachments/{$att->id}/file")
        ->assertForbidden();
});

it('cross-entry attachment ID returns 404', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    // Create a second entry — accessing $att via that entry's URL is wrong.
    $otherFix = mkAttachmentFixtures();
    $otherEntry = $otherFix['entry'];

    $this->actingAs($mgr)
        ->get("/admin/medical-entries/{$otherEntry->id}/attachments/{$att->id}/file")
        ->assertNotFound();
});

it('manager can delete an attachment and the file is removed from disk', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();
    $path = $att->file_path;

    $this->actingAs($mgr)
        ->delete("/admin/medical-entries/{$entry->id}/attachments/{$att->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('medical_attachments', ['id' => $att->id]);
    Storage::disk('local')->assertMissing($path);
});

it('receptionist cannot delete an attachment', function () {
    ['manager' => $mgr, 'entry' => $entry] = mkAttachmentFixtures();
    $file = UploadedFile::fake()->create('lab.pdf', 100, 'application/pdf');
    $this->actingAs($mgr)->post("/admin/medical-entries/{$entry->id}/attachments", ['file' => $file]);
    /** @var \App\Models\MedicalAttachment $att */
    $att = MedicalAttachment::where('medical_entry_id', $entry->id)->first();

    $rec = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($rec)
        ->delete("/admin/medical-entries/{$entry->id}/attachments/{$att->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('medical_attachments', ['id' => $att->id]);
});
