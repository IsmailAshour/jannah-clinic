<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makeBookingFixture(): array
{
    $doc = DoctorProfile::factory()->create();
    $cat = ServiceCategory::create(['name' => 'Test', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'Consult', 'base_price' => 100, 'duration_minutes' => 30]);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 6));

    return [$doc, $svc, $date];
}

it('customer gets slots via portal availability endpoint', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $date] = makeBookingFixture();

    $this->actingAs($customer)
        ->getJson("/portal/availability?doctor={$doc->id}&service={$svc->id}&date={$date->toDateString()}")
        ->assertOk()
        ->assertJsonIsArray()
        ->assertJsonStructure([['start', 'end', 'label']]);
});

it('staff gets slots via admin availability endpoint', function () {
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);
    [$doc, $svc, $date] = makeBookingFixture();

    $this->actingAs($staff)
        ->getJson("/admin/availability?doctor={$doc->id}&service={$svc->id}&date={$date->toDateString()}")
        ->assertOk()
        ->assertJsonIsArray()
        ->assertJsonStructure([['start', 'end', 'label']]);
});

it('customer is forbidden from the admin availability endpoint', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $date] = makeBookingFixture();

    $this->actingAs($customer)
        ->getJson("/admin/availability?doctor={$doc->id}&service={$svc->id}&date={$date->toDateString()}")
        ->assertForbidden();
});
