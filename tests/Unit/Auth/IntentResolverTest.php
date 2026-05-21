<?php

use App\Domain\Auth\Services\IntentResolver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->resolver = new IntentResolver;
});

it('returns public home when intent is null', function () {
    expect($this->resolver->resolve(new Request, null))->toBe(route('public.home'));
});

it('returns public home when intent is empty string', function () {
    expect($this->resolver->resolve(new Request, ''))->toBe(route('public.home'));
});

it('returns booking with passed service id', function () {
    $req = Request::create('/', 'POST', ['service' => '5']);
    $url = $this->resolver->resolve($req, 'booking');
    expect($url)->toContain('/portal/booking')->and($url)->toContain('service=5');
});

it('returns appointments route', function () {
    expect($this->resolver->resolve(new Request, 'appointments'))->toBe(route('portal.appointments.index'));
});

it('returns loyalty route', function () {
    expect($this->resolver->resolve(new Request, 'loyalty'))->toBe(route('portal.loyalty.index'));
});

it('unknown intent falls back to public home', function () {
    expect($this->resolver->resolve(new Request, 'unknown-intent'))->toBe(route('public.home'));
});
