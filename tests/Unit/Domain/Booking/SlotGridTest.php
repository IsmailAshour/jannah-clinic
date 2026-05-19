<?php

use App\Domain\Booking\Slots\SlotGrid;

it('builds the 28-slot half-hour grid', function () {
    $all = SlotGrid::all();
    expect($all)->toHaveCount(28);
    expect($all[0])->toBe('08:00');
    expect($all[count($all) - 1])->toBe('21:30');
});
it('splits morning/evening at band_split', function () {
    expect(SlotGrid::morning())->toContain('08:00')->not->toContain('15:00');
    expect(SlotGrid::evening())->toContain('15:00')->not->toContain('14:30');
});
it('validates grid membership', function () {
    expect(SlotGrid::isValid('08:30'))->toBeTrue();
    expect(SlotGrid::isValid('08:15'))->toBeFalse();
    expect(SlotGrid::isValid('22:00'))->toBeFalse();
});
it('returns consecutive blocks or null past the day end', function () {
    expect(SlotGrid::blockFrom('09:00', 1))->toBe(['09:00']);
    expect(SlotGrid::blockFrom('09:00', 2))->toBe(['09:00', '09:30']);
    expect(SlotGrid::blockFrom('21:00', 2))->toBe(['21:00', '21:30']);
    expect(SlotGrid::blockFrom('21:30', 2))->toBeNull();
    expect(SlotGrid::blockFrom('08:15', 1))->toBeNull();
});
