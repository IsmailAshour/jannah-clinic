<?php

use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;

it('links a service with an optional price override', function () {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id, ['price_override' => 120]);
    expect($doc->services()->first()->pivot->price_override)->toBe('120.00');
});
