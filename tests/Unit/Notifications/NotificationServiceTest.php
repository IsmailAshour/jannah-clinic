<?php

use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\NotificationCategory;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function mkApptForNotifTest(User $customer, DoctorProfile $doctor, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
{
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
    ]);
    $doctor->services()->attach($svc->id);

    return Appointment::create([
        'customer_id' => $customer->id,
        'doctor_profile_id' => $doctor->id,
        'service_id' => $svc->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addMinutes(30),
        'status' => $status,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

function mkPaymentForAppt(Appointment $a): Payment
{
    return Payment::create([
        'appointment_id' => $a->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Pending,
    ]);
}

beforeEach(function () {
    $this->service = app(NotificationService::class);
    $this->doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $this->doctor = DoctorProfile::factory()->create(['user_id' => $this->doctorUser->id]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد']);
});

it('appointmentConfirmed notifies the customer with the correct payload', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor);

    $this->service->appointmentConfirmed($appt);

    $n = $this->customer->notifications()->latest()->first();
    expect($n)->not->toBeNull()
        ->and($n->data['category'])->toBe(NotificationCategory::Appointment->value)
        ->and($n->data['action_url'])->toBe('/portal/appointments')
        ->and($n->data['subject_type'])->toBe(Appointment::class)
        ->and($n->data['subject_id'])->toBe($appt->id)
        ->and($n->data['title'])->toContain('تأكيد')
        ->and($n->read_at)->toBeNull();
});

it('bookingRequested notifies every active manager and receptionist', function () {
    $m1 = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    $m2 = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    User::factory()->create(['role' => UserRole::Manager, 'is_active' => false]);
    $r = User::factory()->create(['role' => UserRole::Receptionist, 'is_active' => true]);
    $appt = mkApptForNotifTest($this->customer, $this->doctor, AppointmentStatus::Requested);

    $this->service->bookingRequested($appt);

    expect($m1->notifications()->count())->toBe(1)
        ->and($m2->notifications()->count())->toBe(1)
        ->and($r->notifications()->count())->toBe(1);
});

it('paymentApproved notifies the customer', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor);
    $payment = mkPaymentForAppt($appt);

    $this->service->paymentApproved($payment);

    $n = $this->customer->notifications()->latest()->first();
    expect($n->data['category'])->toBe(NotificationCategory::Payment->value)
        ->and($n->data['action_url'])->toBe("/portal/appointments/{$appt->id}/payment");
});

it('medicalEntryCreated notifies the customer without PHI in the body', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor, AppointmentStatus::Completed);
    $entry = MedicalEntry::create([
        'appointment_id' => $appt->id,
        'author_id' => $this->doctorUser->id,
        'visible_summary' => 'إنفلونزا',
        'staff_notes' => 'PHI-must-not-leak',
    ]);

    $this->service->medicalEntryCreated($entry);

    $n = $this->customer->notifications()->latest()->first();
    expect($n->data['category'])->toBe(NotificationCategory::Medical->value)
        ->and(json_encode($n->data, JSON_UNESCAPED_UNICODE))->not->toContain('إنفلونزا')
        ->and(json_encode($n->data, JSON_UNESCAPED_UNICODE))->not->toContain('PHI-must-not-leak');
});

it('markAllAsRead flips every unread row for the given user', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor);
    $this->service->appointmentConfirmed($appt);
    $this->service->appointmentConfirmed($appt);
    expect($this->customer->unreadNotifications()->count())->toBe(2);

    $count = $this->service->markAllAsRead($this->customer);

    expect($count)->toBe(2)
        ->and($this->customer->unreadNotifications()->count())->toBe(0);
});

it('rolls back the notification when the surrounding transaction rolls back', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor);
    expect($this->customer->notifications()->count())->toBe(0);

    try {
        DB::transaction(function () use ($appt) {
            $this->service->appointmentConfirmed($appt);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($this->customer->notifications()->count())->toBe(0);
});
