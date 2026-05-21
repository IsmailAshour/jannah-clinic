<?php

use App\Enums\ContactMessageStatus;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\User;

it('guest can submit a contact message', function () {
    $resp = $this->post('/support/contact', [
        'name' => 'زائر',
        'email' => 'visitor@example.com',
        'phone' => '0599000000',
        'subject' => 'استفسار',
        'body' => 'لدي سؤال حول الخدمات.',
    ]);

    $resp->assertRedirect();
    $resp->assertSessionHas('success');

    $msg = ContactMessage::first();
    expect($msg)->not->toBeNull()
        ->and($msg->name)->toBe('زائر')
        ->and($msg->email)->toBe('visitor@example.com')
        ->and($msg->status)->toBe(ContactMessageStatus::New)
        ->and($msg->user_id)->toBeNull();
});

it('authed user gets user_id stamped on the message', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);

    $this->actingAs($user)->post('/support/contact', [
        'name' => 'العميل',
        'email' => 'c@example.com',
        'subject' => 's',
        'body' => 'b',
    ])->assertRedirect();

    expect(ContactMessage::first()->user_id)->toBe($user->id);
});

it('validates required fields', function () {
    $this->post('/support/contact', [
        'name' => '',
        'email' => 'not-an-email',
        'subject' => '',
        'body' => '',
    ])->assertSessionHasErrors(['name', 'email', 'subject', 'body']);

    expect(ContactMessage::count())->toBe(0);
});

it('trims and lowercases email', function () {
    $this->post('/support/contact', [
        'name' => '  زائر  ',
        'email' => '  Visitor@Example.COM  ',
        'subject' => '  س  ',
        'body' => '  ب  ',
    ])->assertRedirect();

    $msg = ContactMessage::first();
    expect($msg->email)->toBe('visitor@example.com')
        ->and($msg->name)->toBe('زائر')
        ->and($msg->subject)->toBe('س')
        ->and($msg->body)->toBe('ب');
});
