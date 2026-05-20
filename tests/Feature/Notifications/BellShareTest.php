<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AppointmentChanged;

function pushSimple(User $u, ?bool $read = null): void
{
    $u->notify(new AppointmentChanged([
        'category' => 'appointment', 'title' => 't', 'body' => 'b',
        'action_url' => '/x', 'subject_type' => 'User', 'subject_id' => $u->id,
    ]));
    if ($read) {
        $u->notifications()->latest()->first()->markAsRead();
    }
}

it('shares unread_count per user', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    pushSimple($u);
    pushSimple($u);
    pushSimple($u, read: true);

    $resp = $this->actingAs($u)->get('/portal/notifications')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications']['unread_count'] ?? null)->toBe(2);
});

it('share returns null for guest', function () {
    $resp = $this->get('/login')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications'] ?? null)->toBeNull();
});

it('share is present on portal home for authenticated customer', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    pushSimple($u);

    $resp = $this->actingAs($u)->get('/portal')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications']['unread_count'] ?? null)->toBe(1);
});
