<?php

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\User;

function mkManager(bool $active = true): User
{
    return User::factory()->create([
        'role' => UserRole::Manager,
        'is_active' => $active,
        'email' => 'mgr_'.uniqid().'@example.com',
    ]);
}

function mkReceptionist(): User
{
    return User::factory()->create([
        'role' => UserRole::Receptionist,
        'is_active' => true,
        'email' => 'rec_'.uniqid().'@example.com',
    ]);
}

it('manager can list staff', function () {
    $mgr = mkManager();
    mkReceptionist();

    $this->actingAs($mgr)
        ->get('/admin/staff')
        ->assertOk();
});

it('receptionist cannot access staff page', function () {
    $rec = mkReceptionist();

    $this->actingAs($rec)
        ->get('/admin/staff')
        ->assertForbidden();
});

it('customer cannot access staff page', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($customer)
        ->get('/admin/staff')
        ->assertForbidden();
});

it('manager can create a receptionist and gets a temp password back', function () {
    $mgr = mkManager();

    $resp = $this->actingAs($mgr)->post('/admin/staff', [
        'name' => 'سارة',
        'email' => 'sara@example.com',
        'phone' => null,
        'role' => 'receptionist',
    ])->assertRedirect();

    $resp->assertSessionHas('success');
    $resp->assertSessionHas('temp_password');

    $this->assertDatabaseHas('users', [
        'email' => 'sara@example.com',
        'role' => 'receptionist',
        'is_active' => true,
    ]);
});

it('manager can create another manager', function () {
    $mgr = mkManager();

    $this->actingAs($mgr)->post('/admin/staff', [
        'name' => 'مدير ثانٍ',
        'email' => 'mgr2@example.com',
        'role' => 'manager',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'mgr2@example.com',
        'role' => 'manager',
    ]);
});

it('rejects creation with neither email nor phone', function () {
    $mgr = mkManager();

    $this->actingAs($mgr)->post('/admin/staff', [
        'name' => 'مَجهول',
        'role' => 'receptionist',
    ])->assertSessionHasErrors('email');
});

it('manager can update staff name + role', function () {
    $mgr = mkManager();
    $rec = mkReceptionist();

    $this->actingAs($mgr)->put("/admin/staff/{$rec->id}", [
        'name' => 'الاسم الجديد',
        'email' => $rec->email,
        'role' => 'manager',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $rec->refresh();
    expect($rec->name)->toBe('الاسم الجديد');
    expect($rec->role)->toBe(UserRole::Manager);
});

it('refuses to demote the LAST active manager', function () {
    $onlyMgr = mkManager();
    // No other manager exists.

    $this->actingAs($onlyMgr)->put("/admin/staff/{$onlyMgr->id}", [
        'name' => $onlyMgr->name,
        'email' => $onlyMgr->email,
        'role' => 'receptionist',
    ])->assertSessionHasErrors('staff');

    $onlyMgr->refresh();
    expect($onlyMgr->role)->toBe(UserRole::Manager);
});

it('allows demoting a manager when another active manager exists', function () {
    $mgrA = mkManager();
    $mgrB = mkManager();

    $this->actingAs($mgrA)->put("/admin/staff/{$mgrB->id}", [
        'name' => $mgrB->name,
        'email' => $mgrB->email,
        'role' => 'receptionist',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $mgrB->refresh();
    expect($mgrB->role)->toBe(UserRole::Receptionist);
});

it('refuses to delete yourself', function () {
    $mgr = mkManager();
    $other = mkManager(); // ensure we don't trip the last-manager guard

    $this->actingAs($mgr)->delete("/admin/staff/{$mgr->id}")
        ->assertSessionHasErrors('staff');

    expect(User::find($mgr->id))->not->toBeNull();
});

it('refuses to delete the last active manager', function () {
    $onlyMgr = mkManager();
    // Another manager exists but is INACTIVE — still leaves zero active mgrs if we delete onlyMgr.
    mkManager(active: false);

    // Need a second person to be the actor (can't act-as the target we're deleting).
    $actor = mkManager(); // now there ARE two active managers
    // Inactivate the actor AFTER acting, so we simulate the situation where
    // only onlyMgr is the last active. Cleaner: use a separate actor that's
    // not the only-mgr, and delete onlyMgr after inactivating the others.
    $extra = mkManager();
    $actor->is_active = false;
    $actor->save();
    $extra->is_active = false;
    $extra->save();
    // Now only $onlyMgr is active. Delete attempt by a (now inactive) actor —
    // but auth still treats them as authenticated. The guard fires regardless.

    $this->actingAs($actor)->delete("/admin/staff/{$onlyMgr->id}")
        ->assertSessionHasErrors('staff');

    expect(User::find($onlyMgr->id))->not->toBeNull();
});

it('refuses to deactivate yourself', function () {
    $mgr = mkManager();
    mkManager(); // not last manager

    $this->actingAs($mgr)->post("/admin/staff/{$mgr->id}/toggle-active")
        ->assertSessionHasErrors('staff');

    $mgr->refresh();
    expect($mgr->is_active)->toBeTrue();
});

it('reset-password rotates the password and returns it in flash', function () {
    $mgr = mkManager();
    $rec = mkReceptionist();
    $oldHash = $rec->password;

    $this->actingAs($mgr)->post("/admin/staff/{$rec->id}/reset-password")
        ->assertRedirect()
        ->assertSessionHas('temp_password');

    $rec->refresh();
    expect($rec->password)->not->toBe($oldHash);
});

it('returns 404 when targeting a Doctor user (doctors have their own controller)', function () {
    $mgr = mkManager();
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);

    $this->actingAs($mgr)->put("/admin/staff/{$doctorUser->id}", [
        'name' => 'محاولة سيّئة',
        'email' => $doctorUser->email,
        'role' => 'manager',
    ])->assertNotFound();
});

it('returns 404 when targeting a Customer user', function () {
    $mgr = mkManager();
    $customer = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($mgr)->delete("/admin/staff/{$customer->id}")
        ->assertNotFound();
});
