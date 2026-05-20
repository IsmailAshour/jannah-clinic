<?php

use App\Enums\UserRole;
use App\Models\User;

it('guest can visit public home', function () {
    $this->get('/')->assertOk();
});

it('guest can visit /services', function () {
    $this->get('/services')->assertOk();
});

it('guest can visit /doctors', function () {
    $this->get('/doctors')->assertOk();
});

it('guest can visit /support', function () {
    $this->get('/support')->assertOk();
});

it('guest cannot visit /portal/anything — redirects to login', function () {
    $this->get('/portal/appointments')->assertRedirect('/login');
});

it('authed customer can also visit public pages', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($u)->get('/')->assertOk();
    $this->actingAs($u)->get('/services')->assertOk();
});
