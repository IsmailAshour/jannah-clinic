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
