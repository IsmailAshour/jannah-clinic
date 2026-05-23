<?php

use App\Domain\Reminders\Jobs\SendAppointmentReminderJob;
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
use App\Notifications\AppointmentReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

function mkJobFixtureAppointment(
    AppointmentStatus $status = AppointmentStatus::Confirmed,
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

    return Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctor->id,
        'service_id' => $svc->id,
        'start_at' => CarbonImmutable::now()->addHours(10),
        'end_at' => CarbonImmutable::now()->addHours(10)->addMinutes(30),
        'status' => $status,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

it('creates an appointment_reminders row and notifies the customer', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment();

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();

    $this->assertDatabaseHas('appointment_reminders', [
        'appointment_id' => $appt->id,
        'kind' => 'before_24h',
        'recipient_email' => $appt->customer->email,
    ]);
    Notification::assertSentTo($appt->customer, AppointmentReminderNotification::class);
});

it('does not double-send when run twice for the same (appointment, kind)', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment();

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();
    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();

    // Only one row + only one notification despite two runs.
    expect(AppointmentReminder::where('appointment_id', $appt->id)->count())->toBe(1);
    Notification::assertSentToTimes($appt->customer, AppointmentReminderNotification::class, 1);
});

it('aborts cleanly when the appointment status is no longer Confirmed', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment(status: AppointmentStatus::Cancelled);

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();

    $this->assertDatabaseCount('appointment_reminders', 0);
    Notification::assertNothingSent();
});

it('aborts cleanly when the appointment customer has no email', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment(customerHasEmail: false);

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();

    $this->assertDatabaseCount('appointment_reminders', 0);
    Notification::assertNothingSent();
});

it('aborts cleanly when the appointment was already reminded (race-safe)', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment();
    AppointmentReminder::create([
        'appointment_id' => $appt->id,
        'kind' => ReminderKind::Before24h->value,
        'sent_at' => now(),
        'recipient_email' => $appt->customer->email,
    ]);

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();

    expect(AppointmentReminder::where('appointment_id', $appt->id)->count())->toBe(1);
    Notification::assertNothingSent();
});

it('handles a non-existent appointment gracefully', function () {
    Notification::fake();

    (new SendAppointmentReminderJob(999999, ReminderKind::Before24h->value))->handle();

    Notification::assertNothingSent();
    $this->assertDatabaseCount('appointment_reminders', 0);
});

it('sends 2h reminder kind independently from 24h kind', function () {
    Notification::fake();
    $appt = mkJobFixtureAppointment();

    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before24h->value))->handle();
    (new SendAppointmentReminderJob($appt->id, ReminderKind::Before2h->value))->handle();

    expect(AppointmentReminder::where('appointment_id', $appt->id)->count())->toBe(2);
    Notification::assertSentToTimes($appt->customer, AppointmentReminderNotification::class, 2);
});
