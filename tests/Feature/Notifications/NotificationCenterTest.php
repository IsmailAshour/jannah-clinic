<?php

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AppointmentChanged;
use Illuminate\Notifications\DatabaseNotification;

function notif(User $to, NotificationCategory $cat = NotificationCategory::Appointment): DatabaseNotification
{
    $to->notify(new AppointmentChanged([
        'category' => $cat->value,
        'title' => 't', 'body' => 'b', 'action_url' => '/x',
        'subject_type' => User::class, 'subject_id' => $to->id,
    ]));

    return $to->notifications()->latest()->first();
}

it('customer marks their own notification as read', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $n = notif($u);

    $this->actingAs($u)->post("/portal/notifications/{$n->id}/read")
        ->assertRedirect('/x');

    expect($n->fresh()->read_at)->not->toBeNull();
});

it('customer cannot read another customer notification (403)', function () {
    $a = User::factory()->create(['role' => UserRole::Customer]);
    $b = User::factory()->create(['role' => UserRole::Customer]);
    $n = notif($b);

    $this->actingAs($a)->post("/portal/notifications/{$n->id}/read")->assertForbidden();
    expect($n->fresh()->read_at)->toBeNull();
});

it('manager marks their own notification as read', function () {
    $u = User::factory()->create(['role' => UserRole::Manager]);
    $n = notif($u);

    $this->actingAs($u)->post("/admin/notifications/{$n->id}/read")->assertRedirect('/x');
    expect($n->fresh()->read_at)->not->toBeNull();
});

it('manager cannot read a different staff notification (403)', function () {
    $a = User::factory()->create(['role' => UserRole::Manager]);
    $b = User::factory()->create(['role' => UserRole::Manager]);
    $n = notif($b);

    $this->actingAs($a)->post("/admin/notifications/{$n->id}/read")->assertForbidden();
});

it('mark-all flips only the acting users unread rows', function () {
    $u1 = User::factory()->create(['role' => UserRole::Customer]);
    $u2 = User::factory()->create(['role' => UserRole::Customer]);
    notif($u1);
    notif($u1);
    notif($u2);

    $this->actingAs($u1)->post('/portal/notifications/mark-all-read')->assertRedirect();

    expect($u1->fresh()->unreadNotifications()->count())->toBe(0)
        ->and($u2->fresh()->unreadNotifications()->count())->toBe(1);
});

it('customer cannot reach the admin notifications surface', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($u)->get('/admin/notifications')->assertForbidden();
});

it('admin notifications index renders for staff', function () {
    $u = User::factory()->create(['role' => UserRole::Manager]);
    notif($u);
    $this->actingAs($u)->get('/admin/notifications')->assertOk();
});

it('portal notifications index renders for customer', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    notif($u);
    $this->actingAs($u)->get('/portal/notifications')->assertOk();
});

it('filters by category', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    notif($u, NotificationCategory::Appointment);
    notif($u, NotificationCategory::Payment);
    notif($u, NotificationCategory::Medical);

    $resp = $this->actingAs($u)->get('/portal/notifications?category=payment')->assertOk();
    $rows = $resp->viewData('page')['props']['feed']['data'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['data']['category'])->toBe('payment');
});

it('filters by unread only', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $r = notif($u);
    $r->markAsRead();
    notif($u);

    $resp = $this->actingAs($u)->get('/portal/notifications?unread=1')->assertOk();
    expect($resp->viewData('page')['props']['feed']['data'])->toHaveCount(1);
});

it('paginates at 20 per page', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    for ($i = 0; $i < 25; $i++) {
        notif($u);
    }

    $resp = $this->actingAs($u)->get('/portal/notifications')->assertOk();
    $meta = $resp->viewData('page')['props']['feed'];
    expect(count($meta['data']))->toBe(20)
        ->and($meta['current_page'])->toBe(1)
        ->and($meta['last_page'])->toBe(2);
});
