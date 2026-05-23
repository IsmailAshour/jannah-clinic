<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function mkOwnedPayment(): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $doctor = DoctorProfile::factory()->create([
        'user_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
    ]);
    $cat = ServiceCategory::create([
        'name' => 'c', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand', 'is_active' => true,
    ]);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's', 'base_price' => '100',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true,
    ]);
    $appt = mkAppointment([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Submitted,
    ]);
    $path = UploadedFile::fake()->image('r.jpg')->store('receipts/'.$payment->id, 'local');
    $receipt = PaymentReceipt::create([
        'payment_id' => $payment->id, 'file_path' => $path, 'mime_type' => 'image/jpeg', 'file_size' => 12345,
        'uploaded_by' => $customer->id,
    ]);

    return compact('customer', 'appt', 'payment', 'receipt');
}

it('the owning customer can download their own receipt', function () {
    $f = mkOwnedPayment();

    $this->actingAs($f['customer'])
        ->get("/portal/appointments/{$f['appt']->id}/payment/receipt/{$f['receipt']->id}/file")
        ->assertOk();
});

it('another customer cannot access someone else receipt', function () {
    $f = mkOwnedPayment();
    $other = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($other)
        ->get("/portal/appointments/{$f['appt']->id}/payment/receipt/{$f['receipt']->id}/file")
        ->assertForbidden();
});

it('returns 404 when receipt id does not belong to that payment', function () {
    $f1 = mkOwnedPayment();
    $f2 = mkOwnedPayment();

    $this->actingAs($f1['customer'])
        ->get("/portal/appointments/{$f1['appt']->id}/payment/receipt/{$f2['receipt']->id}/file")
        ->assertNotFound();
});
