<?php

use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use App\Models\User;

it('manager adjusts balance and customer profile reflects it', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$customer->id}/loyalty/adjust", [
            'delta' => 75,
            'note' => 'مكافأة شكر',
        ])->assertRedirect();

    expect($customer->customerProfile->fresh()->loyalty_balance)->toBe(75);
    $entry = LoyaltyLedger::firstWhere('customer_id', $customer->id);
    expect($entry->reason)->toBe(LoyaltyReason::AdjustmentByManager->value)
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->notes)->toBe('مكافأة شكر');
});

it('rejects zero delta', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$customer->id}/loyalty/adjust", [
            'delta' => 0,
            'note' => 'x',
        ])->assertSessionHasErrors('delta');
});

it('customer show page includes loyalty preview props for non-receptionist', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $c = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $c->id, 'loyalty_balance' => 200]);

    $resp = $this->actingAs($m)->get("/admin/customers/{$c->id}")->assertOk();
    $props = $resp->viewData('page')['props'];
    expect($props['loyaltyBalance'])->toBe(200)
        ->and($props['loyaltyPreview'])->toBeArray()
        ->and($props['loyaltyTotals'])->toBeArray()
        ->and($props['canAdjustLoyalty'])->toBeTrue();
});

it('doctor sees loyalty section but cannot adjust', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $c = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $c->id]);

    $resp = $this->actingAs($d)->get("/admin/customers/{$c->id}")->assertOk();
    $props = $resp->viewData('page')['props'];
    expect($props['canAdjustLoyalty'])->toBeFalse();
});

it('manager adjustment notifies the customer with a loyalty notification', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$customer->id}/loyalty/adjust", [
            'delta' => 50,
            'note' => 'شكر',
        ])->assertRedirect();

    $notif = $customer->notifications()->latest()->first();
    expect($notif)->not->toBeNull()
        ->and($notif->data['category'])->toBe('loyalty')
        ->and($notif->data['action_url'])->toBe('/portal/loyalty');
});
