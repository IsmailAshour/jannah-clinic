<?php

use App\Domain\Booking\Services\PricingService;
use App\Enums\DeliveryMode;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;

function pricedDoctorService(?string $override = null): array
{
    $c = ServiceCategory::create(['name' => 'x', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $s = Service::create(['category_id' => $c->id, 'name' => 's', 'base_price' => 200, 'duration_minutes' => 30, 'home_service_enabled' => true]);
    $d = DoctorProfile::factory()->create();
    $d->services()->attach($s->id, ['price_override' => $override]);

    return [$d, $s];
}

it('quotes base price for a centre visit', function () {
    [$d,$s] = pricedDoctorService();
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Center);
    expect($q)->toBe(['base' => '200.00', 'surcharge' => '0.00', 'total' => '200.00']);
});

it('uses the doctor price override when set', function () {
    [$d,$s] = pricedDoctorService('250');
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Center);
    expect($q['base'])->toBe('250.00');
});

it('adds the configured home surcharge percentage', function () {
    [$d,$s] = pricedDoctorService();
    Setting::create(['key' => 'home_surcharge_pct', 'value' => '30']);
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Home);
    expect($q)->toBe(['base' => '200.00', 'surcharge' => '60.00', 'total' => '260.00']);
});
