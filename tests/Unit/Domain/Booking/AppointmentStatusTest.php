<?php

use App\Enums\AppointmentStatus as S;

it('knows terminal states', function () {
    expect(S::Requested->isTerminal())->toBeFalse();
    expect(S::Confirmed->isTerminal())->toBeFalse();
    foreach ([S::Rejected, S::Completed, S::Cancelled, S::NoShow, S::Rescheduled] as $t) {
        expect($t->isTerminal())->toBeTrue();
    }
});

it('allows only the defined transitions', function () {
    expect(S::Requested->canTransitionTo(S::Confirmed))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Rejected))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Cancelled))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Rescheduled))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Completed))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::NoShow))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Cancelled))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Rescheduled))->toBeTrue();
    expect(S::Completed->canTransitionTo(S::Confirmed))->toBeFalse();
    expect(S::Requested->canTransitionTo(S::Completed))->toBeFalse();
    expect(S::Cancelled->canTransitionTo(S::Requested))->toBeFalse();
});

it('terminal states have no allowed transitions', function () {
    foreach ([S::Rejected, S::Completed, S::Cancelled, S::NoShow, S::Rescheduled] as $t) {
        expect($t->allowedNext())->toBe([]);
    }
});

it('no state can transition to itself', function () {
    foreach (S::cases() as $s) {
        expect($s->canTransitionTo($s))->toBeFalse();
    }
});
