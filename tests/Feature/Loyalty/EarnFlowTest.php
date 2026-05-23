<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\LoyaltyReason;
use App\Models\LoyaltyLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('multi-service award credits only the loyalty-eligible service subtotal', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: true);
    // The fixture's first service is loyalty_enabled & priced 100.
    // Attach a SECOND service priced 150 with loyalty_enabled=false.
    $appt = $f['payment']->appointment;
    $cat = App\Models\ServiceCategory::create(['name' => 'k', 'slug' => 'k'.uniqid(), 'color_variant' => 'brand']);
    $excluded = App\Models\Service::create([
        'category_id' => $cat->id, 'name' => 'no-loyalty', 'base_price' => '150.00',
        'duration_minutes' => 30, 'home_service_enabled' => false, 'loyalty_enabled' => false,
    ]);
    $appt->appointmentServices()->create([
        'service_id' => $excluded->id, 'price_at_booking' => '150.00',
        'duration_minutes' => 30, 'sort_order' => 1,
    ]);
    // Bump the appointment + payment totals so they reflect both services.
    $appt->update(['price_at_booking' => '250.00']);
    $f['payment']->update(['amount' => '250.00']);

    app(PaymentService::class)->uploadReceipt($f['payment']->fresh(), UploadedFile::fake()->image('r.jpg'), $f['customer']);
    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);

    // Total paid = 250, but only the 100-priced service is loyalty-eligible.
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
    expect(LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->where('reason', LoyaltyReason::EarnedFromPayment->value)
        ->sum('points_delta'))->toBe(100);
});
