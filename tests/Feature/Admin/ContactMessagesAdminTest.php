<?php

use App\Enums\ContactMessageStatus;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\User;

it('staff can list messages', function () {
    ContactMessage::create([
        'name' => 'ا', 'email' => 'a@a.com', 'subject' => 's', 'body' => 'b',
        'status' => ContactMessageStatus::New,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->get('/admin/messages')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Messages/Index')
            ->has('messages.data', 1)
            ->where('stats.total', 1)
            ->where('stats.new', 1));
});

it('customer cannot access admin messages', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($customer)->get('/admin/messages')->assertForbidden();
});

it('opening show flips new → read and stamps read_at', function () {
    $msg = ContactMessage::create([
        'name' => 'ا', 'email' => 'a@a.com', 'subject' => 's', 'body' => 'b',
        'status' => ContactMessageStatus::New,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->get("/admin/messages/{$msg->id}")->assertOk();

    $msg->refresh();
    expect($msg->status)->toBe(ContactMessageStatus::Read)
        ->and($msg->read_at)->not->toBeNull();
});

it('manager can mark replied and that stamps handler + replied_at', function () {
    $msg = ContactMessage::create([
        'name' => 'ا', 'email' => 'a@a.com', 'subject' => 's', 'body' => 'b',
        'status' => ContactMessageStatus::Read,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->post("/admin/messages/{$msg->id}/status", [
        'status' => 'replied',
    ])->assertRedirect();

    $msg->refresh();
    expect($msg->status)->toBe(ContactMessageStatus::Replied)
        ->and($msg->replied_at)->not->toBeNull()
        ->and($msg->handled_by)->toBe($manager->id);
});

it('doctor cannot update message status (manager-only)', function () {
    $msg = ContactMessage::create([
        'name' => 'ا', 'email' => 'a@a.com', 'subject' => 's', 'body' => 'b',
        'status' => ContactMessageStatus::New,
    ]);
    $doctor = User::factory()->create(['role' => UserRole::Doctor]);

    $this->actingAs($doctor)->post("/admin/messages/{$msg->id}/status", [
        'status' => 'replied',
    ])->assertForbidden();
});

it('manager can delete a message', function () {
    $msg = ContactMessage::create([
        'name' => 'ا', 'email' => 'a@a.com', 'subject' => 's', 'body' => 'b',
        'status' => ContactMessageStatus::New,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->delete("/admin/messages/{$msg->id}")->assertRedirect('/admin/messages');
    expect(ContactMessage::count())->toBe(0);
});

it('filters by status and search query', function () {
    ContactMessage::create(['name' => 'علي', 'email' => 'ali@a.com', 'subject' => 'X', 'body' => 'b', 'status' => ContactMessageStatus::New]);
    ContactMessage::create(['name' => 'سامي', 'email' => 'sami@a.com', 'subject' => 'Y', 'body' => 'b', 'status' => ContactMessageStatus::Replied]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)->get('/admin/messages?status=new')
        ->assertInertia(fn ($p) => $p->has('messages.data', 1)
            ->where('messages.data.0.status', 'new'));

    $this->actingAs($manager)->get('/admin/messages?q=ali')
        ->assertInertia(fn ($p) => $p->has('messages.data', 1)
            ->where('messages.data.0.email', 'ali@a.com'));
});
