<?php

use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Domain\Loyalty\Services\LoyaltyService;
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
use Illuminate\Support\Facades\DB;

function mkLoyaltyFixtures(): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => true, 'loyalty_redemption_points' => 500,
    ]);
    $doctor->services()->attach($service->id);
    $appt = mkAppointment([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Paid,
    ]);

    return compact('customer', 'doctorUser', 'doctor', 'service', 'appt', 'payment');
}

beforeEach(function () {
    $this->service = app(LoyaltyService::class);
});

it('awardForPayment creates ledger entry and updates cached balance', function () {
    $f = mkLoyaltyFixtures();

    $this->service->awardForPayment($f['payment']);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(100)
        ->and($entry->balance_after)->toBe(100)
        ->and($entry->reason)->toBe(LoyaltyReason::EarnedFromPayment->value)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
});

it('awardForPayment is idempotent on the same payment', function () {
    $f = mkLoyaltyFixtures();

    $this->service->awardForPayment($f['payment']);
    $this->service->awardForPayment($f['payment']);

    expect(LoyaltyLedger::where('reason', LoyaltyReason::EarnedFromPayment->value)
        ->where('reference_id', $f['payment']->id)->count())->toBe(1);
});

it('clawbackForRefund creates negative entry mirroring the earned amount', function () {
    $f = mkLoyaltyFixtures();
    $this->service->awardForPayment($f['payment']);

    $this->service->clawbackForRefund($f['payment']);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(-100)
        ->and($entry->balance_after)->toBe(0)
        ->and($entry->reason)->toBe(LoyaltyReason::ClawbackFromRefund->value);
});

it('redeemForAppointment throws when balance is insufficient', function () {
    $f = mkLoyaltyFixtures();

    expect(fn () => $this->service->redeemForAppointment($f['appt'], $f['customer']))
        ->toThrow(InsufficientLoyaltyBalanceException::class);
});

it('redeemForAppointment deducts cost and writes ledger', function () {
    $f = mkLoyaltyFixtures();
    $f['customer']->customerProfile->update(['loyalty_balance' => 600]);

    $deducted = $this->service->redeemForAppointment($f['appt'], $f['customer']);

    expect($deducted)->toBe(500)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(-500)
        ->and($entry->reason)->toBe(LoyaltyReason::RedeemedForAppointment->value);
});

it('reverseRedemption returns the exact points_spent', function () {
    $f = mkLoyaltyFixtures();
    $f['appt']->update(['payment_method' => 'loyalty_points', 'loyalty_points_spent' => 500]);
    $f['customer']->customerProfile->update(['loyalty_balance' => 0]);

    $this->service->reverseRedemption($f['appt']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);
    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->reason)->toBe(LoyaltyReason::RefundReversal->value);
});

it('adjust by manager writes ledger with actor and notes', function () {
    $f = mkLoyaltyFixtures();
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->service->adjust($f['customer'], 50, 'هدية ترحيب', $manager);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(50)
        ->and($entry->reason)->toBe(LoyaltyReason::AdjustmentByManager->value)
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->notes)->toBe('هدية ترحيب');
});

it('rolls back ledger and balance when the surrounding transaction rolls back', function () {
    $f = mkLoyaltyFixtures();

    try {
        DB::transaction(function () use ($f) {
            $this->service->awardForPayment($f['payment']);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(LoyaltyLedger::count())->toBe(0)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
});

it('chain invariant — balance_after_N = balance_after_N-1 + points_delta_N across consecutive writes', function () {
    $f = mkLoyaltyFixtures();
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->service->adjust($f['customer'], 50, 'one', $manager);
    $this->service->adjust($f['customer'], 30, 'two', $manager);
    $this->service->adjust($f['customer'], -10, 'three', $manager);

    $entries = LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->orderBy('id')->get();

    expect($entries[0]->balance_after)->toBe(50)
        ->and($entries[1]->balance_after)->toBe(80)
        ->and($entries[2]->balance_after)->toBe(70)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(70);
});
