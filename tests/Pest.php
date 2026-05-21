<?php

use App\Domain\Booking\Slots\SlotGrid;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\DoctorScheduleSlot;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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

function mkPaidPath(bool $loyaltyEnabled = true): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => $loyaltyEnabled,
    ]);
    $doctor->services()->attach($service->id);
    $appt = Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Pending,
    ]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    return compact('customer', 'manager', 'payment', 'service');
}

function mkRedeemFixtures(int $balance): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => $balance]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => true, 'loyalty_redemption_points' => 500,
    ]);
    $doctor->services()->attach($service->id);

    $start = CarbonImmutable::now()->next(Carbon::MONDAY)->setTime(10, 0);
    enableDoctorSlots($doctor, 1, slotRange('10:00', 4));

    return compact('customer', 'doctor', 'service', 'start');
}
