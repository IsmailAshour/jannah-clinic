<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

function makeDaysFixture(): array
{
    $doc = DoctorProfile::factory()->create();
    $cat = ServiceCategory::create(['name' => 'Test', 'slug' => uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'Consult', 'base_price' => 100, 'duration_minutes' => 30]);
    $monday = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $monday->dayOfWeek, slotRange('09:00', 6));

    return [$doc, $svc, $monday];
}

it('customer gets available days via portal endpoint', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $monday] = makeDaysFixture();
    $from = $monday->toDateString();
    $to = $monday->addDays(13)->toDateString();

    $res = $this->actingAs($customer)
        ->getJson("/portal/availability/days?doctor={$doc->id}&service={$svc->id}&from={$from}&to={$to}")
        ->assertOk()
        ->assertJsonIsArray();

    $days = $res->json();
    expect($days)->toBeArray();
    expect($days)->toContain($monday->toDateString());
    expect($days)->toContain($monday->addDays(7)->toDateString());
});

it('staff gets available days via admin endpoint', function () {
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);
    [$doc, $svc, $monday] = makeDaysFixture();
    $from = $monday->toDateString();
    $to = $monday->addDays(13)->toDateString();

    $this->actingAs($staff)
        ->getJson("/admin/availability/days?doctor={$doc->id}&service={$svc->id}&from={$from}&to={$to}")
        ->assertOk()
        ->assertJsonIsArray();
});

it('customer is forbidden from the admin available-days endpoint', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $monday] = makeDaysFixture();
    $from = $monday->toDateString();
    $to = $monday->addDays(13)->toDateString();

    $this->actingAs($customer)
        ->getJson("/admin/availability/days?doctor={$doc->id}&service={$svc->id}&from={$from}&to={$to}")
        ->assertForbidden();
});

it('rejects a range larger than 62 days with 422', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $monday] = makeDaysFixture();
    $from = $monday->toDateString();
    $to = $monday->addDays(70)->toDateString();

    $this->actingAs($customer)
        ->getJson("/portal/availability/days?doctor={$doc->id}&service={$svc->id}&from={$from}&to={$to}")
        ->assertStatus(422);
});

it('rejects missing or invalid params with 422', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    [$doc, $svc, $monday] = makeDaysFixture();

    // Missing from/to
    $this->actingAs($customer)
        ->getJson("/portal/availability/days?doctor={$doc->id}&service={$svc->id}")
        ->assertStatus(422);

    // to before from
    $from = $monday->toDateString();
    $to = $monday->subDays(3)->toDateString();
    $this->actingAs($customer)
        ->getJson("/portal/availability/days?doctor={$doc->id}&service={$svc->id}&from={$from}&to={$to}")
        ->assertStatus(422);

    // Invalid doctor
    $this->actingAs($customer)
        ->getJson("/portal/availability/days?doctor=999999&service={$svc->id}&from={$from}&to={$monday->addDays(2)->toDateString()}")
        ->assertStatus(422);
});
