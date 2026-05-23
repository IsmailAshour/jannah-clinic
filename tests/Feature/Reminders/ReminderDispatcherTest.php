<?php

use App\Domain\Reminders\Jobs\SendAppointmentReminderJob;
use App\Domain\Reminders\Services\ReminderDispatcher;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\ReminderKind;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

function mkReminderFixtureAppointment(
    AppointmentStatus $status,
    CarbonImmutable $startAt,
    bool $customerHasEmail = true,
): Appointment {
    $cat = ServiceCategory::create([
        'name' => 'c'.uniqid(),
        'slug' => 'c'.uniqid(),
        'color_variant' => 'brand',
    ]);
    $svc = Service::create([
        'category_id' => $cat->id,
        'name' => 'استشارة',
        'base_price' => '100.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
    ]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $doctor->services()->attach($svc->id);

    $customer = User::factory()->create([
        'role' => UserRole::Customer,
        'email' => $customerHasEmail ? "patient_".uniqid().'@example.com' : null,
    ]);

    return mkAppointment([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctor->id,
        'service_id' => $svc->id,
        'start_at' => $startAt,
        'end_at' => $startAt->addMinutes(30),
        'status' => $status,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

it('only dispatches the 24h reminder when start is between 2h and 24h from now', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(10);
    mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertPushed(SendAppointmentReminderJob::class, 1);
    Queue::assertPushed(SendAppointmentReminderJob::class, fn ($job) => $job->kind === ReminderKind::Before24h->value);
});

it('dispatches both 24h and 2h reminders when start is within the next 2 hours', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addMinutes(90);
    mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertPushed(SendAppointmentReminderJob::class, 2);
});

it('skips Requested appointments (only Confirmed are reminded)', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(10);
    mkReminderFixtureAppointment(AppointmentStatus::Requested, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});

it('skips Cancelled appointments', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(10);
    mkReminderFixtureAppointment(AppointmentStatus::Cancelled, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});

it('skips appointments whose customer has no email', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(10);
    mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start, customerHasEmail: false);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});

it('does not re-dispatch a reminder that has already been sent', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(10);
    $appt = mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start);
    AppointmentReminder::create([
        'appointment_id' => $appt->id,
        'kind' => ReminderKind::Before24h->value,
        'sent_at' => now(),
        'recipient_email' => $appt->customer->email,
    ]);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});

it('skips appointments whose start_at is already past', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->subMinutes(5);
    mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});

it('skips appointments more than 24 hours out', function () {
    Queue::fake();
    $start = CarbonImmutable::now()->addHours(30);
    mkReminderFixtureAppointment(AppointmentStatus::Confirmed, $start);

    app(ReminderDispatcher::class)->dispatch();

    Queue::assertNotPushed(SendAppointmentReminderJob::class);
});
