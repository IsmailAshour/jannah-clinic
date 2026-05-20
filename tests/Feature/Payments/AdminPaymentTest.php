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

function aSubmittedPaymentForAdmin(): array
{
    Storage::fake('local');
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
    $payment = Payment::create(['appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Pending]);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $cust);

    return [$payment->fresh(), $appt, $cust];
}

it('lists payments for staff', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    aSubmittedPaymentForAdmin();
    $this->actingAs($r)->get('/admin/payments')->assertOk();
});

it('shows a payment detail page (staff)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPaymentForAdmin();
    $this->actingAs($r)->get("/admin/payments/{$p->id}")->assertOk();
});

it('manager verifies a submitted payment → paid', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPaymentForAdmin();

    $this->actingAs($m)->post("/admin/payments/{$p->id}/verify")->assertRedirect()->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Paid);
    expect($p->verified_by)->toBe($m->id);
});

it('receptionist cannot verify (403)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPaymentForAdmin();
    $this->actingAs($r)->post("/admin/payments/{$p->id}/verify")->assertForbidden();
});

it('manager rejects with reason — payment + latest receipt updated', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPaymentForAdmin();

    $this->actingAs($m)
        ->post("/admin/payments/{$p->id}/reject", ['reason' => 'إيصال غير واضح'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Rejected);
    expect($p->rejection_reason)->toBe('إيصال غير واضح');
    expect($p->receipts()->first()->status)->toBe('rejected');
});

it('manager marks refund pending on a paid payment', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPaymentForAdmin();
    $p->update(['status' => PaymentStatus::Paid, 'verified_at' => now(), 'verified_by' => $m->id]);

    $this->actingAs($m)->post("/admin/payments/{$p->id}/mark-refund-pending")->assertRedirect();

    expect($p->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('manager marks refunded with reference', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPaymentForAdmin();
    $p->update(['status' => PaymentStatus::RefundPending]);

    $this->actingAs($m)
        ->post("/admin/payments/{$p->id}/mark-refunded", ['reference' => 'BANK-REF-1'])
        ->assertRedirect();

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded);
    expect($p->refund_reference)->toBe('BANK-REF-1');
});

it('streams the receipt file for staff', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPaymentForAdmin();
    $receipt = $p->receipts()->first();
    $this->actingAs($r)->get("/admin/payments/{$p->id}/receipts/{$receipt->id}/file")->assertOk();
});

it('returns 404 if receipt id does not belong to the payment', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p1] = aSubmittedPaymentForAdmin();
    [$p2] = aSubmittedPaymentForAdmin();
    $foreignReceipt = $p2->receipts()->first();
    $this->actingAs($r)->get("/admin/payments/{$p1->id}/receipts/{$foreignReceipt->id}/file")->assertNotFound();
});

it('customer cannot reach admin payments (403)', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    aSubmittedPaymentForAdmin();
    $this->actingAs($c)->get('/admin/payments')->assertForbidden();
});

it('filters by status (submitted is default, all returns everything)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p1] = aSubmittedPaymentForAdmin();
    [$p2] = aSubmittedPaymentForAdmin();
    $p2->update(['status' => PaymentStatus::Paid, 'verified_at' => now()]);

    $idsDefault = collect($this->actingAs($m)->get('/admin/payments')->viewData('page')['props']['payments']['data'])->pluck('id')->all();
    expect($idsDefault)->toContain($p1->id)->not->toContain($p2->id);

    $idsAll = collect($this->actingAs($m)->get('/admin/payments?status=all')->viewData('page')['props']['payments']['data'])->pluck('id')->all();
    expect($idsAll)->toContain($p1->id, $p2->id);
});
