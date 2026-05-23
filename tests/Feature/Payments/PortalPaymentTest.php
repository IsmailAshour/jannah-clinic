<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function aPortalApptWithPayment(): array
{
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = mkAppointment([
        'customer_id' => $cust->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Requested, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Pending,
    ]);

    return [$appt, $payment, $cust];
}

it('customer can see their own payment page', function () {
    [$appt, , $cust] = aPortalApptWithPayment();
    $this->actingAs($cust)->get("/portal/appointments/{$appt->id}/payment")->assertOk();
});

it('customer cannot see another customer\'s payment page (403)', function () {
    [$appt] = aPortalApptWithPayment();
    $other = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($other)->get("/portal/appointments/{$appt->id}/payment")->assertForbidden();
});

it('customer uploads a valid JPG receipt and payment becomes submitted', function () {
    Storage::fake('local');
    [$appt, $payment, $cust] = aPortalApptWithPayment();

    $this->actingAs($cust)
        ->post("/portal/appointments/{$appt->id}/payment/upload", [
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Submitted);
    expect($payment->receipts()->count())->toBe(1);
    Storage::disk('local')->assertExists($payment->receipts()->first()->file_path);
});

it('upload too-large file is rejected with a validation error and payment unchanged', function () {
    Storage::fake('local');
    [$appt, $payment, $cust] = aPortalApptWithPayment();

    $this->actingAs($cust)
        ->post("/portal/appointments/{$appt->id}/payment/upload", [
            'receipt' => UploadedFile::fake()->create('big.jpg', 6 * 1024, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('receipt');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('staff cannot reach the portal payment page (surface isolation)', function () {
    [$appt] = aPortalApptWithPayment();
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->get("/portal/appointments/{$appt->id}/payment")->assertForbidden();
});
