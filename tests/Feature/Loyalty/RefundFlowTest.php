<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\LoyaltyReason;
use App\Models\LoyaltyLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('markRefunded claws back the points', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: true);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);
    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);
    app(PaymentService::class)->markRefundPending($f['payment']->fresh());

    app(PaymentService::class)->markRefunded($f['payment']->fresh(), $f['manager'], 'TX-1');

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
    expect(LoyaltyLedger::where('reason', LoyaltyReason::ClawbackFromRefund->value)->count())->toBe(1);
});
