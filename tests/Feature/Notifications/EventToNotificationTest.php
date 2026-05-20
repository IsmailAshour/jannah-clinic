<?php

use App\Domain\Payment\Services\PaymentService;
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

function mkApptWithPayment(User $customer, DoctorProfile $doctor): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doctor->services()->attach($svc->id);

    $a = Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctor->id,
        'service_id' => $svc->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    Payment::create([
        'appointment_id' => $a->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Pending,
    ]);

    return $a;
}

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $this->doctor = DoctorProfile::factory()->create(['user_id' => $this->doctorUser->id]);
    $this->manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
});

it('uploadReceipt notifies managers', function () {
    Storage::fake('local');
    $appt = mkApptWithPayment($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);

    app(PaymentService::class)->uploadReceipt(
        $payment,
        UploadedFile::fake()->image('receipt.jpg'),
        $this->customer
    );

    expect($this->manager->notifications()->count())->toBe(1);
});

it('verify notifies the customer with payment category', function () {
    Storage::fake('local');
    $appt = mkApptWithPayment($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    $this->manager->notifications()->delete();
    $this->customer->notifications()->delete();

    app(PaymentService::class)->verify($payment->fresh(), $this->manager);

    $n = $this->customer->notifications()->latest()->first();
    expect($n)->not->toBeNull()->and($n->data['category'])->toBe('payment');
});

it('reject notifies the customer', function () {
    Storage::fake('local');
    $appt = mkApptWithPayment($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    $this->customer->notifications()->delete();

    app(PaymentService::class)->reject($payment->fresh(), $this->manager, 'صورة غير واضحة');

    $n = $this->customer->notifications()->latest()->first();
    expect($n->data['title'])->toContain('مرفوض');
});

it('markRefunded notifies the customer', function () {
    Storage::fake('local');
    $appt = mkApptWithPayment($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    app(PaymentService::class)->verify($payment->fresh(), $this->manager);
    app(PaymentService::class)->markRefundPending($payment->fresh());
    $this->customer->notifications()->delete();

    app(PaymentService::class)->markRefunded($payment->fresh(), $this->manager, 'TX-001');

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('استرداد');
});

it('markRefundPending does NOT notify (internal staging)', function () {
    Storage::fake('local');
    $appt = mkApptWithPayment($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    app(PaymentService::class)->verify($payment->fresh(), $this->manager);
    $this->customer->notifications()->delete();

    app(PaymentService::class)->markRefundPending($payment->fresh());

    expect($this->customer->notifications()->count())->toBe(0);
});
