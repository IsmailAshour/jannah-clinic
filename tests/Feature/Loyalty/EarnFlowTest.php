<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function mkPaidPath(bool $loyaltyEnabled = true): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => $loyaltyEnabled,
    ]);
    $doctor->services()->attach($service->id);
    $appt = Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Pending,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    return compact('customer', 'manager', 'payment', 'service');
}

it('verify awards points when service loyalty_enabled', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: true);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);

    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
    expect(LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->where('reason', LoyaltyReason::EarnedFromPayment->value)
        ->count())->toBe(1);
});

it('verify does NOT award when service loyalty_enabled=false', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: false);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);

    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
    expect(LoyaltyLedger::count())->toBe(0);
});
