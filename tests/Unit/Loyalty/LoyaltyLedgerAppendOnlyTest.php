<?php

use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\LoyaltyLedger;
use App\Models\User;

it('throws when trying to update an existing ledger row', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $entry = LoyaltyLedger::create([
        'customer_id' => $u->id,
        'points_delta' => 100,
        'balance_after' => 100,
        'reason' => LoyaltyReason::EarnedFromPayment->value,
    ]);

    expect(fn () => $entry->update(['points_delta' => 200]))
        ->toThrow(LogicException::class);
});

it('throws when trying to delete a ledger row', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $entry = LoyaltyLedger::create([
        'customer_id' => $u->id,
        'points_delta' => 100,
        'balance_after' => 100,
        'reason' => LoyaltyReason::EarnedFromPayment->value,
    ]);

    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});
