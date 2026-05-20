<?php

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
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

/**
 * Build a Payment in a chosen status, with the Appointment + supporting rows
 * minted directly (bypasses BookingService so we can control status exactly).
 */
function pmt(string $status = 'pending'): Payment
{
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
        'customer_id' => $cust->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Requested, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);

    return Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => $status,
    ]);
}

it('pending → submitted on uploadReceipt', function () {
    Storage::fake('local');
    $p = pmt('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);

    $receipt = app(PaymentService::class)->uploadReceipt(
        $p, UploadedFile::fake()->image('r.jpg'), $u
    );

    expect($p->fresh()->status)->toBe(PaymentStatus::Submitted);
    expect($receipt->payment_id)->toBe($p->id);
    expect($receipt->uploaded_by)->toBe($u->id);
    expect($receipt->status)->toBe('uploaded');
    Storage::disk('local')->assertExists($receipt->file_path);
});

it('rejects upload when file is too large (>5MB)', function () {
    Storage::fake('local');
    $p = pmt('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $big = UploadedFile::fake()->create('big.jpg', 6 * 1024, 'image/jpeg');

    expect(fn () => app(PaymentService::class)->uploadReceipt($p, $big, $u))
        ->toThrow(InvalidPaymentTransitionException::class);
    expect($p->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects upload when MIME type is unsupported', function () {
    Storage::fake('local');
    $p = pmt('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $bad = UploadedFile::fake()->create('x.txt', 100, 'text/plain');

    expect(fn () => app(PaymentService::class)->uploadReceipt($p, $bad, $u))
        ->toThrow(InvalidPaymentTransitionException::class);
});

it('rejects upload when payment is already paid', function () {
    Storage::fake('local');
    $p = pmt('paid');
    $u = User::factory()->create(['role' => UserRole::Customer]);

    expect(fn () => app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r.jpg'), $u))
        ->toThrow(InvalidPaymentTransitionException::class);
});

it('submitted → paid on verify', function () {
    $p = pmt('submitted');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    app(PaymentService::class)->verify($p, $m);

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Paid);
    expect($p->verified_by)->toBe($m->id);
    expect($p->verified_at)->not->toBeNull();
    expect($p->rejection_reason)->toBeNull();
});

it('submitted → rejected on reject (with reason mirrored to latest receipt)', function () {
    Storage::fake('local');
    $p = pmt('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $receipt = app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r.jpg'), $u);

    app(PaymentService::class)->reject($p, $m, 'إيصال غير واضح');

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Rejected);
    expect($p->rejection_reason)->toBe('إيصال غير واضح');
    $r = $receipt->fresh();
    expect($r->status)->toBe('rejected');
    expect($r->rejection_reason)->toBe('إيصال غير واضح');
    expect($r->rejected_by)->toBe($m->id);
});

it('rejected → submitted on re-upload (history preserved)', function () {
    Storage::fake('local');
    $p = pmt('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $m = User::factory()->create(['role' => UserRole::Manager]);
    app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r1.jpg'), $u);
    app(PaymentService::class)->reject($p, $m, 'سبب');
    expect($p->fresh()->status)->toBe(PaymentStatus::Rejected);

    app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r2.jpg'), $u);

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Submitted);
    expect($p->rejection_reason)->toBeNull();
    expect($p->receipts()->count())->toBe(2);
});

it('paid → refund_pending via markRefundPending', function () {
    $p = pmt('paid');

    app(PaymentService::class)->markRefundPending($p);

    expect($p->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('refund_pending → refunded on markRefunded with reference', function () {
    $p = pmt('refund_pending');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    app(PaymentService::class)->markRefunded($p, $m, 'BANK-REF-12345');

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded);
    expect($p->refunded_by)->toBe($m->id);
    expect($p->refunded_at)->not->toBeNull();
    expect($p->refund_reference)->toBe('BANK-REF-12345');
});

it('blocks illegal transitions', function () {
    $paid = pmt('paid');
    $refunded = pmt('refunded');
    $pending = pmt('pending');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    expect(fn () => app(PaymentService::class)->verify($pending, $m))
        ->toThrow(InvalidPaymentTransitionException::class);
    expect(fn () => app(PaymentService::class)->markRefundPending($refunded))
        ->toThrow(InvalidPaymentTransitionException::class);
    expect(fn () => app(PaymentService::class)->verify($paid, $m))
        ->toThrow(InvalidPaymentTransitionException::class);
});
