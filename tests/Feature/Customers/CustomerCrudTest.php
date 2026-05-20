<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

it('lets a manager view the customers list with only customer-role users', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'عميل اختبار']);
    $staff = User::factory()->create(['role' => UserRole::Receptionist, 'name' => 'موظف']);

    $resp = $this->actingAs($m)->get('/admin/customers')->assertOk();
    $page = $resp->viewData('page');
    $ids = collect($page['props']['customers']['data'])->pluck('id')->all();

    expect($ids)->toContain($customer->id);
    expect($ids)->not->toContain($staff->id);
});

it('filters customers by search query (name, email, phone)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $a = User::factory()->create(['role' => UserRole::Customer, 'name' => 'محمد الأحمد', 'email' => 'mohammad@example.com', 'phone' => '0599100100']);
    $b = User::factory()->create(['role' => UserRole::Customer, 'name' => 'سارة', 'email' => 'sara@example.com', 'phone' => '0599200200']);

    // Search by name fragment
    $ids = collect($this->actingAs($m)->get('/admin/customers?q=محمد')->viewData('page')['props']['customers']['data'])->pluck('id')->all();
    expect($ids)->toContain($a->id)->not->toContain($b->id);

    // Search by email fragment
    $ids = collect($this->actingAs($m)->get('/admin/customers?q=sara@')->viewData('page')['props']['customers']['data'])->pluck('id')->all();
    expect($ids)->toContain($b->id)->not->toContain($a->id);

    // Search by phone fragment
    $ids = collect($this->actingAs($m)->get('/admin/customers?q=599100')->viewData('page')['props']['customers']['data'])->pluck('id')->all();
    expect($ids)->toContain($a->id)->not->toContain($b->id);
});

it('filters customers by status (active / inactive)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $on = User::factory()->create(['role' => UserRole::Customer, 'is_active' => true]);
    $off = User::factory()->create(['role' => UserRole::Customer, 'is_active' => false]);

    $idsActive = collect($this->actingAs($m)->get('/admin/customers?status=active')->viewData('page')['props']['customers']['data'])->pluck('id')->all();
    expect($idsActive)->toContain($on->id)->not->toContain($off->id);

    $idsInactive = collect($this->actingAs($m)->get('/admin/customers?status=inactive')->viewData('page')['props']['customers']['data'])->pluck('id')->all();
    expect($idsInactive)->toContain($off->id)->not->toContain($on->id);
});

it('allows a receptionist to read the list but blocks mutations (403)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $c = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($r)->get('/admin/customers')->assertOk();
    $this->actingAs($r)->put("/admin/customers/{$c->id}", ['name' => 'x'])->assertForbidden();
    $this->actingAs($r)->post("/admin/customers/{$c->id}/toggle-active")->assertForbidden();
});

it('forbids a customer from reaching the customers admin surface', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->get('/admin/customers')->assertForbidden();
});

it('shows a customer 200 and returns 404 for a non-customer id', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $staff = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($m)->get("/admin/customers/{$c->id}")->assertOk();
    $this->actingAs($m)->get("/admin/customers/{$staff->id}")->assertNotFound();
});

it('lets a manager update name + DOB + notes via PUT', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $c = User::factory()->create(['role' => UserRole::Customer, 'name' => 'قديم']);

    $resp = $this->actingAs($m)->put("/admin/customers/{$c->id}", [
        'name' => 'الاسم الجديد',
        'date_of_birth' => '1990-01-15',
        'notes' => 'حساسية من البنسلين',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $c->refresh();
    expect($c->name)->toBe('الاسم الجديد');
    $cp = CustomerProfile::where('user_id', $c->id)->first();
    expect($cp)->not->toBeNull();
    expect($cp->date_of_birth->format('Y-m-d'))->toBe('1990-01-15');
    expect($cp->notes)->toBe('حساسية من البنسلين');
});

it('rejects a duplicate email on update with a validation error (and no DB change)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $other = User::factory()->create(['role' => UserRole::Customer, 'email' => 'taken@example.com']);
    $c = User::factory()->create(['role' => UserRole::Customer, 'email' => 'me@example.com', 'name' => 'أنا']);

    $this->actingAs($m)->put("/admin/customers/{$c->id}", [
        'name' => 'محاولة',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');

    $c->refresh();
    expect($c->email)->toBe('me@example.com');
    expect($c->name)->toBe('أنا');
});

it('lets a manager toggle the active flag', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $c = User::factory()->create(['role' => UserRole::Customer, 'is_active' => true]);

    $this->actingAs($m)->post("/admin/customers/{$c->id}/toggle-active")->assertRedirect();
    expect($c->fresh()->is_active)->toBeFalse();

    $this->actingAs($m)->post("/admin/customers/{$c->id}/toggle-active")->assertRedirect();
    expect($c->fresh()->is_active)->toBeTrue();
});

it('returns 404 on toggle-active for a non-customer id', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $staff = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($m)->post("/admin/customers/{$staff->id}/toggle-active")->assertNotFound();
});

it('includes appointment history and stats on the show page', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $cat = ServiceCategory::create(['name' => 'c', 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 'فحص', 'base_price' => 100, 'duration_minutes' => 30]);
    $doc = DoctorProfile::factory()->create();

    $base = [
        'customer_id' => $c->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ];
    Appointment::create($base + ['start_at' => now()->subDays(10), 'end_at' => now()->subDays(10)->addMinutes(30), 'status' => AppointmentStatus::Completed]);
    Appointment::create($base + ['start_at' => now()->subDays(5), 'end_at' => now()->subDays(5)->addMinutes(30), 'status' => AppointmentStatus::NoShow]);

    $resp = $this->actingAs($m)->get("/admin/customers/{$c->id}")->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['stats']['total'])->toBe(2);
    expect($props['stats']['completed'])->toBe(1);
    expect($props['stats']['noShow'])->toBe(1);
    expect($props['stats']['lastVisit'])->not->toBeNull();
    expect(count($props['appointments']['data']))->toBe(2);
});
