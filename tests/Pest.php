<?php

use App\Domain\Booking\Slots\SlotGrid;
use App\Models\DoctorProfile;
use App\Models\DoctorScheduleSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

function enableDoctorSlots(DoctorProfile $doctor, int $weekday, array $starts): void
{
    foreach ($starts as $s) {
        DoctorScheduleSlot::create([
            'doctor_profile_id' => $doctor->id,
            'weekday' => $weekday,
            'slot_start' => $s,
        ]);
    }
}

/** Contiguous half-hour grid starts from $from for $count slots (e.g. slotRange('09:00',4) => ['09:00','09:30','10:00','10:30']) */
function slotRange(string $from, int $count): array
{
    return SlotGrid::blockFrom($from, $count)
        ?? throw new InvalidArgumentException("slotRange: invalid grid start $from / count $count");
}
