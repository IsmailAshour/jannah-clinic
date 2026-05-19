<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('lets a manager create a doctor and assign services with override', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30]);
    $this->actingAs($m)->post('/admin/doctors', [
        'name' => 'د. سارة', 'email' => 'sara@c.com', 'password' => 'secret12', 'password_confirmation' => 'secret12',
        'specialty' => 'جلدية', 'is_bookable' => true,
        'services' => [['service_id' => $svc->id, 'price_override' => 130]],
    ])->assertRedirect();
    $doc = DoctorProfile::first();
    expect($doc->user->role)->toBe(UserRole::Doctor);
    expect($doc->services()->first()->pivot->price_override)->toBe('130.00');
});

it('forbids a non-manager staff (doctor) from creating a doctor', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->post('/admin/doctors', ['name' => 'x'])->assertForbidden();
});

it('forbids a customer entirely', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/doctors', ['name' => 'x'])->assertForbidden();
});

it('lets any staff view the doctors list', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->get('/admin/doctors')->assertOk();
});
