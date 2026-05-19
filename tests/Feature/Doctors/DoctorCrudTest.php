<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

it('resyncs services on update (add, change override, remove)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x', 'color_variant' => 'brand']);
    $a = Service::create(['category_id' => $cat->id, 'name' => 'A', 'base_price' => 100, 'duration_minutes' => 30]);
    $b = Service::create(['category_id' => $cat->id, 'name' => 'B', 'base_price' => 200, 'duration_minutes' => 30]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($a->id, ['price_override' => 110]);
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}", [
        'name' => $doc->user->name, 'email' => $doc->user->email, 'specialty' => 'عام', 'is_bookable' => true,
        'services' => [['service_id' => $b->id, 'price_override' => null]],
    ])->assertRedirect();
    $doc->refresh();
    expect($doc->services()->pluck('services.id')->all())->toBe([$b->id]); // A removed, B added
    expect($doc->services()->first()->pivot->price_override)->toBeNull();
});

it('deletes the underlying user when a doctor is destroyed', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $uid = $doc->user_id;
    $this->actingAs($m)->delete("/admin/doctors/{$doc->id}")->assertRedirect();
    expect(User::whereKey($uid)->exists())->toBeFalse();
    expect(DoctorProfile::whereKey($doc->id)->exists())->toBeFalse();
});

it('can change a doctor password on update', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}", [
        'name' => $doc->user->name, 'email' => $doc->user->email, 'specialty' => 'عام', 'is_bookable' => true,
        'password' => 'newsecret12', 'password_confirmation' => 'newsecret12', 'services' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(Hash::check('newsecret12', $doc->user->fresh()->password))->toBeTrue();
});
