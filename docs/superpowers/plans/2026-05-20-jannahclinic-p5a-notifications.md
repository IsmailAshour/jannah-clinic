# jannahclinic P5a — Notification System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an event-driven in-app notification system: domain services emit notifications inside their transactions; users see a bell badge + dedicated notification center page on both admin and customer surfaces; only the recipient can read or mark their own notifications.

**Architecture:** Laravel's standard `notifications` table (no custom columns) — payload (title, body, category, action_url, subject_type/id) carried in `data` JSON. A single `NotificationService` mirrors the existing `AuditLogger` pattern: explicit, called inline by originating services inside the same `DB::transaction`. Three thin `Illuminate\Notifications\Notification` subclasses (one per category) target only the `database` channel. UI is two near-identical Inertia pages (admin + portal) sharing styling but separated for shell isolation. No SMS / email / time-based reminders / realtime push in P5a — all deferred behind future ADRs.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · Pest · Larastan L5 · Pint · Inertia.js · Vue 3 · shadcn-vue (reka-ui) · Tailwind v4 · Vitest (jsdom).

**Spec:** `docs/superpowers/specs/2026-05-20-jannahclinic-p5a-notifications-design.md`

**Execution mode:** Lean — no per-task review-subagent ceremony. After each task: inline verification + full gate (Pest + Vitest + Pint + PHPStan + Vite) + commit + push.

**Commit convention (verbatim per project standard):**
```
git -c user.email=admin@istoria.app -c user.name=claude commit -m "<subject>" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## File Structure

**New backend files**
- `app/Enums/NotificationCategory.php` — `enum: string` with 4 cases (Appointment, Payment, Medical, System)
- `app/Notifications/AppointmentChanged.php` — Notification subclass, database channel only
- `app/Notifications/PaymentChanged.php` — same shape
- `app/Notifications/MedicalRecordChanged.php` — same shape
- `app/Domain/Notification/Services/NotificationService.php` — single entry point; one method per spec §3 row
- `app/Http/Controllers/Admin/NotificationController.php` — index, markRead, markAllRead
- `app/Http/Controllers/Portal/NotificationController.php` — same surface, scoped to customer routes
- `database/migrations/2026_05_20_140000_create_notifications_table.php` — Laravel's standard schema + two composite indexes

**New frontend files**
- `resources/js/Components/foundation/NotificationBell.vue`
- `resources/js/Pages/Admin/Notifications/Index.vue`
- `resources/js/Pages/Portal/Notifications/Index.vue`

**New test files**
- `tests/Unit/Notifications/NotificationServiceTest.php` — generator unit tests
- `tests/Feature/Notifications/NotificationCenterTest.php` — list/filter/mark-read + cross-user 403
- `tests/Feature/Notifications/BellShareTest.php` — Inertia `notifications.unread_count` share
- `tests/Feature/Notifications/EventToNotificationTest.php` — service-method → notification row
- `resources/js/Components/foundation/__tests__/NotificationBell.spec.js`

**Modified files**
- `app/Http/Middleware/HandleInertiaRequests.php` — add `notifications` lazy prop
- `app/Domain/Booking/Services/BookingService.php` — inject + call NotificationService
- `app/Domain/Booking/Services/AppointmentTransitionService.php` — same
- `app/Domain/Payment/Services/PaymentService.php` — same
- `app/Domain/MedicalRecord/Services/MedicalEntryService.php` — same
- `app/Domain/MedicalRecord/Services/PrescriptionService.php` — same
- `routes/admin.php` — 3 new notification routes (read by any staff role)
- `routes/portal.php` — 3 new notification routes
- `tests/Feature/RouteNamesTest.php` — lock 6 new route names
- `resources/js/Layouts/AdminShell.vue` — bell in header
- `resources/js/Layouts/ClientShell.vue` — bell in header
- `resources/js/Components/foundation/index.js` — export `NotificationBell`
- `docs/ARCHITECTURE.md` — append P5a notes
- `CHANGELOG.md` — Unreleased entry

---

## Task 1: Migration, enum, Notification classes, NotificationService skeleton + unit tests

**Files:**
- Create: `database/migrations/2026_05_20_140000_create_notifications_table.php`
- Create: `app/Enums/NotificationCategory.php`
- Create: `app/Notifications/AppointmentChanged.php`
- Create: `app/Notifications/PaymentChanged.php`
- Create: `app/Notifications/MedicalRecordChanged.php`
- Create: `app/Domain/Notification/Services/NotificationService.php`
- Create: `tests/Unit/Notifications/NotificationServiceTest.php`

- [ ] **Step 1.1: Write the failing unit test**

Create `tests/Unit/Notifications/NotificationServiceTest.php`:

```php
<?php

use App\Domain\Notification\Services\NotificationService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\Prescription;
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
        ->and($n->data['action_url'])->toBe("/portal/appointments/{$appt->id}")
        ->and($n->data['subject_type'])->toBe(Appointment::class)
        ->and($n->data['subject_id'])->toBe($appt->id)
        ->and($n->data['title'])->toContain('تأكيد')
        ->and($n->read_at)->toBeNull();
});

it('bookingRequested notifies every active manager', function () {
    $m1 = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    $m2 = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    User::factory()->create(['role' => UserRole::Manager, 'is_active' => false]);
    $appt = mkApptForNotifTest($this->customer, $this->doctor, AppointmentStatus::Requested);

    $this->service->bookingRequested($appt);

    expect($m1->notifications()->count())->toBe(1)
        ->and($m2->notifications()->count())->toBe(1);
});

it('paymentApproved notifies the customer', function () {
    $appt = mkApptForNotifTest($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);

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
```

- [ ] **Step 1.2: Run test to verify it fails**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Unit/Notifications/NotificationServiceTest.php`
Expected: FAIL — `Class "App\Domain\Notification\Services\NotificationService" not found` and migration missing.

- [ ] **Step 1.3: Create the migration**

Create `database/migrations/2026_05_20_140000_create_notifications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Composite covering indexes for the two hot queries:
        //  - unread count: WHERE notifiable_type=? AND notifiable_id=? AND read_at IS NULL
        //  - feed page:    WHERE notifiable_type=? AND notifiable_id=? ORDER BY created_at DESC
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_idx');
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 1.4: Create the category enum**

Create `app/Enums/NotificationCategory.php`:

```php
<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Appointment = 'appointment';
    case Payment = 'payment';
    case Medical = 'medical';
    case System = 'system';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
```

- [ ] **Step 1.5: Create the three Notification subclasses**

Create `app/Notifications/AppointmentChanged.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppointmentChanged extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
```

Create `app/Notifications/PaymentChanged.php` (identical class body, different class name):

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PaymentChanged extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
```

Create `app/Notifications/MedicalRecordChanged.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MedicalRecordChanged extends Notification
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
```

- [ ] **Step 1.6: Create the NotificationService**

Create `app/Domain/Notification/Services/NotificationService.php`:

```php
<?php

namespace App\Domain\Notification\Services;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\AppointmentChanged;
use App\Notifications\MedicalRecordChanged;
use App\Notifications\PaymentChanged;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    public function bookingRequested(Appointment $a): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::Manager, UserRole::Receptionist])
            ->where('is_active', true)
            ->get();
        $payload = [
            'category' => NotificationCategory::Appointment->value,
            'title' => 'طلب حجز جديد',
            'body' => "طلب حجز جديد من {$a->customer->name} — بانتظار التأكيد.",
            'action_url' => "/admin/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ];
        foreach ($recipients as $r) {
            $r->notify(new AppointmentChanged($payload));
        }
    }

    public function appointmentConfirmed(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ تأكيد موعدك',
            'body' => "تمّ تأكيد موعدك بتاريخ {$a->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentRejected(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'نأسف، تعذّر تأكيد موعدك',
            'body' => 'تواصل مع العيادة لإعادة الجدولة.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentCancelledByCustomer(Appointment $a): void
    {
        $recipients = User::query()->where('role', UserRole::Manager)->where('is_active', true)->get();
        $payload = [
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إلغاء موعد من العميل',
            'body' => "ألغى {$a->customer->name} الموعد بتاريخ {$a->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/admin/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ];
        foreach ($recipients as $r) {
            $r->notify(new AppointmentChanged($payload));
        }
        if ($a->doctor && $a->doctor->user) {
            $a->doctor->user->notify(new AppointmentChanged($payload));
        }
    }

    public function appointmentCancelledByStaff(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'تمّ إلغاء موعدك',
            'body' => "تمّ إلغاء موعدك بتاريخ {$a->start_at->isoFormat('D MMM HH:mm')}.",
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function appointmentRescheduledForCustomer(Appointment $newAppt): void
    {
        $newAppt->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'إعادة جدولة موعد',
            'body' => "تمّ نقل موعدك إلى {$newAppt->start_at->isoFormat('D MMM YYYY HH:mm')}.",
            'action_url' => "/portal/appointments/{$newAppt->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $newAppt->id,
        ]));
    }

    public function appointmentCompleted(Appointment $a): void
    {
        $a->customer->notify(new AppointmentChanged([
            'category' => NotificationCategory::Appointment->value,
            'title' => 'اكتمل موعدك',
            'body' => 'يمكنك مراجعة سجلك الطبي لاحقًا.',
            'action_url' => "/portal/appointments/{$a->id}",
            'subject_type' => Appointment::class,
            'subject_id' => $a->id,
        ]));
    }

    public function paymentReceiptUploaded(Payment $p): void
    {
        $recipients = User::query()->where('role', UserRole::Manager)->where('is_active', true)->get();
        $payload = [
            'category' => NotificationCategory::Payment->value,
            'title' => 'إيصال جديد بانتظار المراجعة',
            'body' => "رُفع إيصال على الموعد رقم {$p->appointment_id}.",
            'action_url' => "/admin/payments/{$p->id}",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ];
        foreach ($recipients as $r) {
            $r->notify(new PaymentChanged($payload));
        }
    }

    public function paymentApproved(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تأكيد دفعتك',
            'body' => 'تمّت مراجعة إيصالك وقبوله.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function paymentRejected(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'الإيصال مرفوض',
            'body' => 'أعد رفع الإيصال — تفاصيل الرفض داخل الصفحة.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function paymentRefunded(Payment $p): void
    {
        $p->appointment->customer->notify(new PaymentChanged([
            'category' => NotificationCategory::Payment->value,
            'title' => 'تمّ تنفيذ الاسترداد',
            'body' => 'تمّ إعادة المبلغ إلى حسابك.',
            'action_url' => "/portal/appointments/{$p->appointment_id}/payment",
            'subject_type' => Payment::class,
            'subject_id' => $p->id,
        ]));
    }

    public function medicalEntryCreated(MedicalEntry $e): void
    {
        $e->appointment->customer->notify(new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'أضاف الطبيب ملاحظة على زيارتك',
            'body' => 'افتح سجلك الطبي لمراجعة الخلاصة الجديدة.',
            'action_url' => "/portal/medical-record/entries/{$e->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $e->id,
        ]));
    }

    public function prescriptionAdded(Prescription $p): void
    {
        $entry = $p->medicalEntry;
        $entry->appointment->customer->notify(new MedicalRecordChanged([
            'category' => NotificationCategory::Medical->value,
            'title' => 'تمّ إضافة وصفة جديدة',
            'body' => 'افتح سجلك الطبي لرؤية الوصفة.',
            'action_url' => "/portal/medical-record/entries/{$entry->id}",
            'subject_type' => MedicalEntry::class,
            'subject_id' => $entry->id,
        ]));
    }

    public function markAsRead(DatabaseNotification $n, User $user): void
    {
        if ((int) $n->notifiable_id !== $user->id || $n->notifiable_type !== User::class) {
            abort(403);
        }
        if ($n->read_at === null) {
            $n->markAsRead();
        }
    }

    public function markAllAsRead(User $user): int
    {
        return (int) $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
```

- [ ] **Step 1.7: Run migrations + tests**

Run:
```
cd /c/~projects/jannahclinic && php artisan migrate && php artisan test tests/Unit/Notifications/NotificationServiceTest.php
```
Expected: 6/6 PASS.

- [ ] **Step 1.8: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +6 (≈254 total), Pint clean, PHPStan 0, Vite build OK, Vitest 22.

- [ ] **Step 1.9: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add database/migrations/2026_05_20_140000_create_notifications_table.php \
        app/Enums/NotificationCategory.php \
        app/Notifications/AppointmentChanged.php \
        app/Notifications/PaymentChanged.php \
        app/Notifications/MedicalRecordChanged.php \
        app/Domain/Notification/Services/NotificationService.php \
        tests/Unit/Notifications/NotificationServiceTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): NotificationService + database channel scaffolding (P5a/1)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 2: Routes, controllers, mark-read endpoints, auth feature tests

**Files:**
- Create: `app/Http/Controllers/Admin/NotificationController.php`
- Create: `app/Http/Controllers/Portal/NotificationController.php`
- Modify: `routes/admin.php`
- Modify: `routes/portal.php`
- Modify: `tests/Feature/RouteNamesTest.php`
- Create: `tests/Feature/Notifications/NotificationCenterTest.php`

- [ ] **Step 2.1: Write the failing feature test**

Create `tests/Feature/Notifications/NotificationCenterTest.php`:

```php
<?php

use App\Domain\Notification\Services\NotificationService;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AppointmentChanged;

function notif(User $to, NotificationCategory $cat = NotificationCategory::Appointment): \Illuminate\Notifications\DatabaseNotification
{
    $to->notify(new AppointmentChanged([
        'category' => $cat->value,
        'title' => 't', 'body' => 'b', 'action_url' => '/x',
        'subject_type' => User::class, 'subject_id' => $to->id,
    ]));

    return $to->notifications()->latest()->first();
}

it('customer marks their own notification as read', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $n = notif($u);

    $this->actingAs($u)->post("/portal/notifications/{$n->id}/read")
        ->assertRedirect('/x');

    expect($n->fresh()->read_at)->not->toBeNull();
});

it('customer cannot read another customer notification (403)', function () {
    $a = User::factory()->create(['role' => UserRole::Customer]);
    $b = User::factory()->create(['role' => UserRole::Customer]);
    $n = notif($b);

    $this->actingAs($a)->post("/portal/notifications/{$n->id}/read")->assertForbidden();
    expect($n->fresh()->read_at)->toBeNull();
});

it('manager marks their own notification as read', function () {
    $u = User::factory()->create(['role' => UserRole::Manager]);
    $n = notif($u);

    $this->actingAs($u)->post("/admin/notifications/{$n->id}/read")->assertRedirect('/x');
    expect($n->fresh()->read_at)->not->toBeNull();
});

it('manager cannot read a different staff notification (403)', function () {
    $a = User::factory()->create(['role' => UserRole::Manager]);
    $b = User::factory()->create(['role' => UserRole::Manager]);
    $n = notif($b);

    $this->actingAs($a)->post("/admin/notifications/{$n->id}/read")->assertForbidden();
});

it('mark-all flips only the acting users unread rows', function () {
    $u1 = User::factory()->create(['role' => UserRole::Customer]);
    $u2 = User::factory()->create(['role' => UserRole::Customer]);
    notif($u1); notif($u1); notif($u2);

    $this->actingAs($u1)->post('/portal/notifications/mark-all-read')->assertRedirect();

    expect($u1->fresh()->unreadNotifications()->count())->toBe(0)
        ->and($u2->fresh()->unreadNotifications()->count())->toBe(1);
});

it('customer cannot reach the admin notifications surface', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($u)->get('/admin/notifications')->assertForbidden();
});

it('admin notifications index renders for staff', function () {
    $u = User::factory()->create(['role' => UserRole::Manager]);
    notif($u);
    $this->actingAs($u)->get('/admin/notifications')->assertOk();
});

it('portal notifications index renders for customer', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    notif($u);
    $this->actingAs($u)->get('/portal/notifications')->assertOk();
});
```

- [ ] **Step 2.2: Run test to verify it fails**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/NotificationCenterTest.php`
Expected: FAIL — routes don't exist (404 instead of 200/302/403).

- [ ] **Step 2.3: Create the admin controller**

Create `app/Http/Controllers/Admin/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notification\Services\NotificationService;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->input('category');
        $onlyUnread = $request->boolean('unread');

        $query = $user->notifications();
        if ($category && in_array($category, NotificationCategory::values(), true)) {
            $query->whereRaw("data::jsonb->>'category' = ?", [$category]);
        }
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['category' => $category, 'unread' => $onlyUnread],
            'categories' => NotificationCategory::values(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        /** @var DatabaseNotification|null $n */
        $n = DatabaseNotification::query()->find($id);
        abort_unless($n !== null, 404);
        $this->notifications->markAsRead($n, $request->user());

        return redirect($n->data['action_url'] ?? route('admin.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllAsRead($request->user());

        return redirect()->route('admin.notifications.index');
    }
}
```

- [ ] **Step 2.4: Create the portal controller**

Create `app/Http/Controllers/Portal/NotificationController.php` — identical body except the namespace and the Inertia view path `Portal/Notifications/Index` and the redirect route `portal.notifications.index`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Notification\Services\NotificationService;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->input('category');
        $onlyUnread = $request->boolean('unread');

        $query = $user->notifications();
        if ($category && in_array($category, NotificationCategory::values(), true)) {
            $query->whereRaw("data::jsonb->>'category' = ?", [$category]);
        }
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return Inertia::render('Portal/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['category' => $category, 'unread' => $onlyUnread],
            'categories' => NotificationCategory::values(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $n = DatabaseNotification::query()->find($id);
        abort_unless($n !== null, 404);
        $this->notifications->markAsRead($n, $request->user());

        return redirect($n->data['action_url'] ?? route('portal.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllAsRead($request->user());

        return redirect()->route('portal.notifications.index');
    }
}
```

- [ ] **Step 2.5: Register the routes**

Edit `routes/admin.php` — inside the outer `Route::middleware(['auth', 'role:manager,doctor,receptionist'])` group, alongside the existing readable-by-all-staff routes, add:

```php
        // P5a — Notifications (any staff role can read their own feed)
        Route::get('notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('notifications/{id}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('notifications/mark-all-read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllRead'])
            ->name('notifications.mark-all-read');
```

Edit `routes/portal.php` — inside the existing `Route::middleware(['auth', 'role:customer'])->prefix('portal')->name('portal.')->group(...)`, before the closing `});`:

```php
        // P5a — Notifications (customer feed)
        Route::get('notifications', [\App\Http\Controllers\Portal\NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('notifications/{id}/read', [\App\Http\Controllers\Portal\NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('notifications/mark-all-read', [\App\Http\Controllers\Portal\NotificationController::class, 'markAllRead'])
            ->name('notifications.mark-all-read');
```

- [ ] **Step 2.6: Lock the new route names**

Edit `tests/Feature/RouteNamesTest.php`. Find the `$expectedNames` array (or equivalent locked list — open the file and locate the existing entries; append the six new ones in alphabetical order with the rest of the list):

```php
'admin.notifications.index',
'admin.notifications.read',
'admin.notifications.mark-all-read',
'portal.notifications.index',
'portal.notifications.read',
'portal.notifications.mark-all-read',
```

- [ ] **Step 2.7: Create the stub Inertia pages so `assertOk()` doesn't fail on missing view**

Create `resources/js/Pages/Admin/Notifications/Index.vue`:

```vue
<script setup>
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  notifications: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="الإشعارات" description="مركز الإشعارات." />
    </div>
  </AdminShell>
</template>
```

Create `resources/js/Pages/Portal/Notifications/Index.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  notifications: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الإشعارات" description="مركز الإشعارات." />
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 2.8: Run the feature test**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/NotificationCenterTest.php`
Expected: 8/8 PASS.

- [ ] **Step 2.9: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +8, Pint clean, PHPStan 0, Vite build OK, Vitest 22.

- [ ] **Step 2.10: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Admin/NotificationController.php \
        app/Http/Controllers/Portal/NotificationController.php \
        routes/admin.php routes/portal.php \
        tests/Feature/RouteNamesTest.php \
        tests/Feature/Notifications/NotificationCenterTest.php \
        resources/js/Pages/Admin/Notifications/Index.vue \
        resources/js/Pages/Portal/Notifications/Index.vue
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): admin/portal mark-read endpoints + index stubs + auth tests (P5a/2)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 3: Inertia share + Bell component + Shell integration + Vitest

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Create: `resources/js/Components/foundation/NotificationBell.vue`
- Modify: `resources/js/Components/foundation/index.js`
- Modify: `resources/js/Layouts/AdminShell.vue`
- Modify: `resources/js/Layouts/ClientShell.vue`
- Create: `resources/js/Components/foundation/__tests__/NotificationBell.spec.js`
- Create: `tests/Feature/Notifications/BellShareTest.php`

- [ ] **Step 3.1: Write the failing share test**

Create `tests/Feature/Notifications/BellShareTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AppointmentChanged;

function pushSimple(User $u, ?bool $read = null): void
{
    $u->notify(new AppointmentChanged([
        'category' => 'appointment', 'title' => 't', 'body' => 'b',
        'action_url' => '/x', 'subject_type' => 'User', 'subject_id' => $u->id,
    ]));
    if ($read) {
        $u->notifications()->latest()->first()->markAsRead();
    }
}

it('shares unread_count per user', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    pushSimple($u);
    pushSimple($u);
    pushSimple($u, read: true);

    $resp = $this->actingAs($u)->get('/portal/notifications')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications']['unread_count'] ?? null)->toBe(2);
});

it('share returns null for guest', function () {
    $resp = $this->get('/login')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications'] ?? null)->toBeNull();
});

it('share is lazy — only evaluates when requested', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    pushSimple($u);

    $resp = $this->actingAs($u)->get('/portal')->assertOk();
    $props = $resp->viewData('page')['props'];

    expect($props['notifications']['unread_count'] ?? null)->toBe(1);
});
```

- [ ] **Step 3.2: Run it to confirm failure**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/BellShareTest.php`
Expected: FAIL — share key missing.

- [ ] **Step 3.3: Add the Inertia share**

Edit `app/Http/Middleware/HandleInertiaRequests.php` — inside `share()`, alongside the existing `adminCounts` lazy key:

```php
            // P5a — per-user unread notification count. Lazy: closure runs only
            // when the page reads `notifications.unread_count`. Guests get null.
            'notifications' => fn () => $request->user()
                ? ['unread_count' => $request->user()->unreadNotifications()->count()]
                : null,
```

- [ ] **Step 3.4: Run the feature test — should pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/BellShareTest.php`
Expected: 3/3 PASS.

- [ ] **Step 3.5: Create the Bell component + Vitest spec**

Create `resources/js/Components/foundation/NotificationBell.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'

const props = defineProps({
  href: { type: String, required: true },
})
const page = usePage()
const count = computed(() => page.props?.notifications?.unread_count ?? 0)
</script>

<template>
  <Link
    :href="props.href"
    class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-text-secondary hover:text-text-primary hover:bg-surface-page"
    aria-label="الإشعارات"
  >
    <Bell class="h-5 w-5" aria-hidden="true" />
    <span
      v-if="count > 0"
      data-testid="bell-badge"
      class="absolute -top-1 -inline-end-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 text-[10px] font-bold text-white"
    >{{ count > 99 ? '99+' : count }}</span>
  </Link>
</template>
```

Create `resources/js/Components/foundation/__tests__/NotificationBell.spec.js`:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import NotificationBell from '../NotificationBell.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
  usePage: () => ({ props: pageProps }),
}))

let pageProps = { notifications: null }

describe('NotificationBell', () => {
  it('hides badge when unread_count is 0', () => {
    pageProps = { notifications: { unread_count: 0 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').exists()).toBe(false)
  })

  it('shows badge with count when unread_count > 0', () => {
    pageProps = { notifications: { unread_count: 3 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').text()).toBe('3')
  })

  it('caps display at 99+ for large counts', () => {
    pageProps = { notifications: { unread_count: 150 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').text()).toBe('99+')
  })

  it('hides badge when share is null (guest)', () => {
    pageProps = { notifications: null }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').exists()).toBe(false)
  })
})
```

- [ ] **Step 3.6: Export the component**

Edit `resources/js/Components/foundation/index.js` — add to the exports:

```js
export { default as NotificationBell } from './NotificationBell.vue'
```

- [ ] **Step 3.7: Mount the bell in AdminShell**

Edit `resources/js/Layouts/AdminShell.vue`. In the `<header>` element near the `Link href="/logout"`, insert the bell BEFORE the logout link:

```vue
      <header class="h-16 shrink-0 bg-surface-card border-b border-border-default flex items-center px-6">
        <SidebarTrigger class="-ms-2 me-auto" aria-label="القائمة" />
        <NotificationBell href="/admin/notifications" class="me-2" />
        <Link href="/logout" method="post" as="button" class="text-sm text-text-secondary hover:text-text-primary">تسجيل الخروج</Link>
      </header>
```

Also import it at the top of the `<script setup>` block:

```js
import { NotificationBell } from '@/Components/foundation'
```

- [ ] **Step 3.8: Mount the bell in ClientShell**

Edit `resources/js/Layouts/ClientShell.vue` — modify the header line:

```vue
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card">
      <span class="font-bold text-brand">عيادة جنّة</span>
      <NotificationBell href="/portal/notifications" class="ms-auto me-2" />
      <Link href="/logout" method="post" as="button" class="text-xs text-text-secondary">خروج</Link>
    </header>
```

Add to the script imports:

```js
import { NotificationBell } from '@/Components/foundation'
```

(The existing `ms-auto` on the logout link must move to the bell so the bell-then-logout cluster is right-pinned.)

- [ ] **Step 3.9: Run the Vitest spec**

Run: `cd /c/~projects/jannahclinic && npx vitest run resources/js/Components/foundation/__tests__/NotificationBell.spec.js`
Expected: 4/4 PASS.

- [ ] **Step 3.10: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +3, Pint clean, PHPStan 0, Vite OK, Vitest +4 (=26).

- [ ] **Step 3.11: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Middleware/HandleInertiaRequests.php \
        resources/js/Components/foundation/NotificationBell.vue \
        resources/js/Components/foundation/index.js \
        resources/js/Components/foundation/__tests__/NotificationBell.spec.js \
        resources/js/Layouts/AdminShell.vue \
        resources/js/Layouts/ClientShell.vue \
        tests/Feature/Notifications/BellShareTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): unread_count Inertia share + NotificationBell in both shells (P5a/3)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 4: Notification center page — filters + pagination + mark-all + list rows (admin + portal)

**Files:**
- Modify: `resources/js/Pages/Admin/Notifications/Index.vue`
- Modify: `resources/js/Pages/Portal/Notifications/Index.vue`
- Modify: `tests/Feature/Notifications/NotificationCenterTest.php`

- [ ] **Step 4.1: Extend the feature test for filtering and pagination**

Append to `tests/Feature/Notifications/NotificationCenterTest.php`:

```php
it('filters by category', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    notif($u, NotificationCategory::Appointment);
    notif($u, NotificationCategory::Payment);
    notif($u, NotificationCategory::Medical);

    $resp = $this->actingAs($u)->get('/portal/notifications?category=payment')->assertOk();
    $rows = $resp->viewData('page')['props']['notifications']['data'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['data']['category'])->toBe('payment');
});

it('filters by unread only', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $r = notif($u); $r->markAsRead();
    notif($u);

    $resp = $this->actingAs($u)->get('/portal/notifications?unread=1')->assertOk();
    expect($resp->viewData('page')['props']['notifications']['data'])->toHaveCount(1);
});

it('paginates at 20 per page', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    for ($i = 0; $i < 25; $i++) { notif($u); }

    $resp = $this->actingAs($u)->get('/portal/notifications')->assertOk();
    $meta = $resp->viewData('page')['props']['notifications'];
    expect(count($meta['data']))->toBe(20)
        ->and($meta['current_page'])->toBe(1)
        ->and($meta['last_page'])->toBe(2);
});
```

- [ ] **Step 4.2: Run the test to confirm filter/pagination already work**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/NotificationCenterTest.php`
Expected: 11/11 PASS — the controller already supports filter+pagination from Task 2.

(If pagination keys differ from the project's existing pattern, adjust the assertions to match — the project uses Laravel's default paginator shape `{data, current_page, last_page, links, ...}`.)

- [ ] **Step 4.3: Build the full admin index page**

Replace `resources/js/Pages/Admin/Notifications/Index.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  notifications: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})

const categoryLabel = {
  appointment: 'المواعيد',
  payment: 'المدفوعات',
  medical: 'السجل الطبي',
  system: 'النظام',
}

const unreadCount = computed(() => usePage().props?.notifications?.unread_count ?? 0)

function applyFilter(category) {
  router.get('/admin/notifications', {
    ...(category ? { category } : {}),
    ...(props.filters.unread ? { unread: 1 } : {}),
  }, { preserveScroll: true })
}

function toggleUnread() {
  router.get('/admin/notifications', {
    ...(props.filters.category ? { category: props.filters.category } : {}),
    ...(props.filters.unread ? {} : { unread: 1 }),
  }, { preserveScroll: true })
}

function markAllRead() {
  router.post('/admin/notifications/mark-all-read', {}, { preserveScroll: true })
}

function openRow(row) {
  router.post(`/admin/notifications/${row.id}/read`, {}, { preserveScroll: false })
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60) return 'الآن'
  if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`
  if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`
  return new Date(iso).toLocaleDateString('ar-SA')
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-4">
      <div class="flex items-center justify-between">
        <PageHeader title="الإشعارات" :description="`غير مقروءة: ${unreadCount}`" />
        <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllRead">
          تعليم الكل كمقروء
        </Button>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button :variant="!filters.category ? 'default' : 'outline'" size="sm" @click="applyFilter(null)">الكل</Button>
        <Button
          v-for="c in categories" :key="c"
          :variant="filters.category === c ? 'default' : 'outline'"
          size="sm" @click="applyFilter(c)"
        >{{ categoryLabel[c] }}</Button>
        <Button :variant="filters.unread ? 'default' : 'outline'" size="sm" class="ms-auto" @click="toggleUnread">
          {{ filters.unread ? 'إظهار الكل' : 'غير المقروءة فقط' }}
        </Button>
      </div>

      <ul class="divide-y divide-border-default bg-surface-card rounded-lg shadow-sm">
        <li v-if="notifications.data.length === 0" class="p-6 text-center text-text-secondary">
          لا إشعارات حتى الآن.
        </li>
        <li
          v-for="row in notifications.data"
          :key="row.id"
          class="p-4 flex items-start gap-3 cursor-pointer hover:bg-surface-page"
          @click="openRow(row)"
        >
          <span v-if="!row.read_at" class="mt-2 h-2 w-2 rounded-full bg-brand shrink-0" />
          <span v-else class="mt-2 h-2 w-2 rounded-full bg-transparent shrink-0" />
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-text-primary">{{ row.data.title }}</div>
            <div class="text-sm text-text-secondary truncate">{{ row.data.body }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">{{ timeAgo(row.created_at) }}</div>
        </li>
      </ul>

      <div v-if="notifications.last_page > 1" class="flex justify-center gap-2">
        <Button
          v-for="link in notifications.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        >
          <span v-html="link.label" />
        </Button>
      </div>
    </div>
  </AdminShell>
</template>
```

- [ ] **Step 4.4: Build the portal index page**

Replace `resources/js/Pages/Portal/Notifications/Index.vue` with the identical body, except:
1. Replace `import AdminShell from '@/Layouts/AdminShell.vue'` with `import ClientShell from '@/Layouts/ClientShell.vue'`
2. Replace `<AdminShell>` ... `</AdminShell>` with `<ClientShell>` ... `</ClientShell>`
3. Replace every `/admin/notifications` URL with `/portal/notifications`
4. Replace the outer wrapper class `p-6 space-y-4` with `p-4 space-y-4` (portal density convention)

```vue
<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  notifications: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})

const categoryLabel = {
  appointment: 'المواعيد',
  payment: 'المدفوعات',
  medical: 'السجل الطبي',
  system: 'النظام',
}

const unreadCount = computed(() => usePage().props?.notifications?.unread_count ?? 0)

function applyFilter(category) {
  router.get('/portal/notifications', {
    ...(category ? { category } : {}),
    ...(props.filters.unread ? { unread: 1 } : {}),
  }, { preserveScroll: true })
}

function toggleUnread() {
  router.get('/portal/notifications', {
    ...(props.filters.category ? { category: props.filters.category } : {}),
    ...(props.filters.unread ? {} : { unread: 1 }),
  }, { preserveScroll: true })
}

function markAllRead() {
  router.post('/portal/notifications/mark-all-read', {}, { preserveScroll: true })
}

function openRow(row) {
  router.post(`/portal/notifications/${row.id}/read`, {}, { preserveScroll: false })
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60) return 'الآن'
  if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`
  if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`
  return new Date(iso).toLocaleDateString('ar-SA')
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <div class="flex items-center justify-between">
        <PageHeader title="الإشعارات" :description="`غير مقروءة: ${unreadCount}`" />
        <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllRead">
          تعليم الكل كمقروء
        </Button>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button :variant="!filters.category ? 'default' : 'outline'" size="sm" @click="applyFilter(null)">الكل</Button>
        <Button
          v-for="c in categories" :key="c"
          :variant="filters.category === c ? 'default' : 'outline'"
          size="sm" @click="applyFilter(c)"
        >{{ categoryLabel[c] }}</Button>
        <Button :variant="filters.unread ? 'default' : 'outline'" size="sm" class="ms-auto" @click="toggleUnread">
          {{ filters.unread ? 'إظهار الكل' : 'غير المقروءة فقط' }}
        </Button>
      </div>

      <ul class="divide-y divide-border-default bg-surface-card rounded-lg shadow-sm">
        <li v-if="notifications.data.length === 0" class="p-6 text-center text-text-secondary">
          لا إشعارات حتى الآن.
        </li>
        <li
          v-for="row in notifications.data"
          :key="row.id"
          class="p-4 flex items-start gap-3 cursor-pointer hover:bg-surface-page"
          @click="openRow(row)"
        >
          <span v-if="!row.read_at" class="mt-2 h-2 w-2 rounded-full bg-brand shrink-0" />
          <span v-else class="mt-2 h-2 w-2 rounded-full bg-transparent shrink-0" />
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-text-primary">{{ row.data.title }}</div>
            <div class="text-sm text-text-secondary truncate">{{ row.data.body }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">{{ timeAgo(row.created_at) }}</div>
        </li>
      </ul>

      <div v-if="notifications.last_page > 1" class="flex justify-center gap-2">
        <Button
          v-for="link in notifications.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        >
          <span v-html="link.label" />
        </Button>
      </div>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 4.5: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +3 (filter/unread/pagination), Pint clean, PHPStan 0, Vite OK, Vitest unchanged.

- [ ] **Step 4.6: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add resources/js/Pages/Admin/Notifications/Index.vue \
        resources/js/Pages/Portal/Notifications/Index.vue \
        tests/Feature/Notifications/NotificationCenterTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): notification center with filters + pagination + mark-all (P5a/4)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 5: PaymentService integration

**Files:**
- Modify: `app/Domain/Payment/Services/PaymentService.php`
- Create: `tests/Feature/Notifications/EventToNotificationTest.php`

- [ ] **Step 5.1: Write the failing event-to-notification test (Payment slice)**

Create `tests/Feature/Notifications/EventToNotificationTest.php`:

```php
<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function mkPaidPath(User $customer, DoctorProfile $doctor): Appointment
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
        'status' => AppointmentStatus::Confirmed,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
}

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $this->doctor = DoctorProfile::factory()->create(['user_id' => $this->doctorUser->id]);
    $this->manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
});

it('uploadReceipt notifies managers', function () {
    Storage::fake('local');
    $appt = mkPaidPath($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);

    app(PaymentService::class)->uploadReceipt(
        $payment,
        UploadedFile::fake()->image('receipt.jpg'),
        $this->customer
    );

    expect($this->manager->notifications()->count())->toBe(1);
});

it('verify notifies the customer with payment category', function () {
    Storage::fake('local');
    $appt = mkPaidPath($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    $this->manager->notifications()->delete();
    $this->customer->notifications()->delete();

    app(PaymentService::class)->verify($payment->fresh(), $this->manager);

    $n = $this->customer->notifications()->latest()->first();
    expect($n)->not->toBeNull()->and($n->data['category'])->toBe('payment');
});

it('reject notifies the customer', function () {
    Storage::fake('local');
    $appt = mkPaidPath($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    $this->customer->notifications()->delete();

    app(PaymentService::class)->reject($payment->fresh(), $this->manager, 'صورة غير واضحة');

    $n = $this->customer->notifications()->latest()->first();
    expect($n->data['title'])->toContain('مرفوض');
});

it('markRefunded notifies the customer', function () {
    Storage::fake('local');
    $appt = mkPaidPath($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    app(PaymentService::class)->verify($payment->fresh(), $this->manager);
    app(PaymentService::class)->markRefundPending($payment->fresh());
    $this->customer->notifications()->delete();

    app(PaymentService::class)->markRefunded($payment->fresh(), $this->manager, 'TX-001');

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('استرداد');
});

it('markRefundPending does NOT notify (internal staging)', function () {
    Storage::fake('local');
    $appt = mkPaidPath($this->customer, $this->doctor);
    $payment = Payment::firstWhere('appointment_id', $appt->id);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $this->customer);
    app(PaymentService::class)->verify($payment->fresh(), $this->manager);
    $this->customer->notifications()->delete();

    app(PaymentService::class)->markRefundPending($payment->fresh());

    expect($this->customer->notifications()->count())->toBe(0);
});
```

- [ ] **Step 5.2: Run it — confirm fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: FAIL — service doesn't yet emit notifications.

- [ ] **Step 5.3: Wire NotificationService into PaymentService**

Edit `app/Domain/Payment/Services/PaymentService.php`:

1. Add `use App\Domain\Notification\Services\NotificationService;` import.
2. Replace the existing class declaration with a constructor:

```php
class PaymentService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(private readonly NotificationService $notifications) {}
```

3. Inside `uploadReceipt`, at the end of the `DB::transaction(function () use ($payment, $file, $uploader) { ... })` closure (just before `return $receipt;`), add:

```php
            $this->notifications->paymentReceiptUploaded($payment->refresh());
```

4. Inside `verify`, after the `$payment->update([...])` block and before `return $payment;`, wrap in a transaction (it currently is NOT in one — wrap the whole body in `DB::transaction(...)`):

```php
    public function verify(Payment $payment, User $manager): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw new InvalidPaymentTransitionException("لا يمكن التحقّق إلا من إيصال قيد المراجعة (الحالة الحالية: {$payment->status->value}).");
        }

        return DB::transaction(function () use ($payment, $manager) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'verified_at' => now(),
                'verified_by' => $manager->id,
                'rejection_reason' => null,
            ]);
            $this->notifications->paymentApproved($payment->refresh());

            return $payment;
        });
    }
```

5. Inside `reject`, before `return $payment;` inside the existing transaction:

```php
            $this->notifications->paymentRejected($payment->refresh());
```

6. Inside `markRefunded`, wrap the body in `DB::transaction` similarly and call `paymentRefunded` before returning. `markRefundPending` does NOT call any notification method — confirm this stays as-is.

The final shape for `markRefunded`:

```php
    public function markRefunded(Payment $payment, User $manager, ?string $reference = null): Payment
    {
        if ($payment->status !== PaymentStatus::RefundPending) {
            throw new InvalidPaymentTransitionException("لا يمكن تسجيل استرداد إلا لدفعة بانتظار الاسترداد (الحالة الحالية: {$payment->status->value}).");
        }

        return DB::transaction(function () use ($payment, $manager, $reference) {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'refunded_by' => $manager->id,
                'refund_reference' => $reference,
            ]);
            $this->notifications->paymentRefunded($payment->refresh());

            return $payment;
        });
    }
```

- [ ] **Step 5.4: Run the test — confirm pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: 5/5 PASS.

- [ ] **Step 5.5: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +5, Pint clean, PHPStan 0, Vite OK, Vitest unchanged.

- [ ] **Step 5.6: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/Payment/Services/PaymentService.php \
        tests/Feature/Notifications/EventToNotificationTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): emit payment notifications from PaymentService transactions (P5a/5)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 6: BookingService + AppointmentTransitionService integration

**Files:**
- Modify: `app/Domain/Booking/Services/BookingService.php`
- Modify: `app/Domain/Booking/Services/AppointmentTransitionService.php`
- Modify: `tests/Feature/Notifications/EventToNotificationTest.php`

- [ ] **Step 6.1: Extend the event-to-notification test**

Append to `tests/Feature/Notifications/EventToNotificationTest.php`:

```php
it('BookingService::book notifies managers + receptionists', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist, 'is_active' => true]);

    $appt = app(\App\Domain\Booking\Services\BookingService::class)->book(
        new \App\Domain\Booking\Data\BookingData(
            customerId: $this->customer->id,
            doctorProfileId: $this->doctor->id,
            serviceId: (function () {
                $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
                $s = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false]);
                $this->doctor->services()->attach($s->id);

                return $s->id;
            })(),
            startAt: \Carbon\CarbonImmutable::parse('next monday 10:00'),
            deliveryMode: DeliveryMode::Center,
            createdByRole: UserRole::Customer,
            coverageAreaId: null,
            addressText: null,
            locationNote: null,
        )
    );

    expect($this->manager->notifications()->count())->toBeGreaterThanOrEqual(1)
        ->and($r->notifications()->count())->toBeGreaterThanOrEqual(1);
});

it('appointment Confirmed transition notifies the customer', function () {
    $appt = mkPaidPath($this->customer, $this->doctor);
    $appt->status = AppointmentStatus::Requested;
    $appt->save();
    $this->customer->notifications()->delete();

    app(\App\Domain\Booking\Services\AppointmentTransitionService::class)
        ->transition($appt, AppointmentStatus::Confirmed);

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('تأكيد');
});

it('appointment Cancelled-by-staff notifies the customer', function () {
    $appt = mkPaidPath($this->customer, $this->doctor);
    $this->customer->notifications()->delete();

    app(\App\Domain\Booking\Services\AppointmentTransitionService::class)
        ->transition($appt, AppointmentStatus::Cancelled, 'overbook');

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('إلغاء');
});

it('appointment Completed notifies the customer', function () {
    $appt = mkPaidPath($this->customer, $this->doctor);
    $this->customer->notifications()->delete();

    app(\App\Domain\Booking\Services\AppointmentTransitionService::class)
        ->transition($appt, AppointmentStatus::Completed);

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('اكتمل');
});
```

- [ ] **Step 6.2: Run it — confirm fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: 4 failures on the new tests.

- [ ] **Step 6.3: Wire BookingService**

Edit `app/Domain/Booking/Services/BookingService.php`:

1. Add import: `use App\Domain\Notification\Services\NotificationService;`
2. Add `NotificationService` to the constructor:

```php
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly NotificationService $notifications,
    ) {}
```

3. At the end of the `DB::transaction(function () use ($d) { ... })` body inside `book()`, just before `return $appointment;` (or whatever the return identifier is — locate it in the existing code), add:

```php
            $this->notifications->bookingRequested($appointment->refresh());
```

(If the local variable is named differently, adapt — but the pattern is: after the `Appointment::create(...)` line that yields the new appointment, take that variable and pass it to `bookingRequested`.)

- [ ] **Step 6.4: Wire AppointmentTransitionService**

Edit `app/Domain/Booking/Services/AppointmentTransitionService.php`:

1. Add import: `use App\Domain\Notification\Services\NotificationService;`
2. Replace the constructor:

```php
    public function __construct(
        private readonly BookingService $booking,
        private readonly NotificationService $notifications,
    ) {}
```

3. Wrap the body of `transition()` in a `DB::transaction(...)` and dispatch the right notification based on `$to`:

```php
    public function transition(Appointment $a, AppointmentStatus $to, ?string $reason = null): Appointment
    {
        if (! $a->status->canTransitionTo($to)) {
            throw new InvalidTransitionException("انتقال غير مسموح: {$a->status->value} → {$to->value}");
        }

        return DB::transaction(function () use ($a, $to, $reason) {
            $a->status = $to;
            if ($to === AppointmentStatus::Cancelled) {
                $a->cancellation_reason = $reason;
            }
            $a->save();
            $a->refresh()->load('customer', 'doctor.user');

            match ($to) {
                AppointmentStatus::Confirmed => $this->notifications->appointmentConfirmed($a),
                AppointmentStatus::Rejected => $this->notifications->appointmentRejected($a),
                AppointmentStatus::Cancelled => $this->notifications->appointmentCancelledByStaff($a),
                AppointmentStatus::Completed => $this->notifications->appointmentCompleted($a),
                default => null,
            };

            return $a;
        });
    }
```

4. Inside `reschedule()`'s existing `DB::transaction(...)`, after `$old->save();` and before the closing of the closure, add:

```php
            $this->notifications->appointmentRescheduledForCustomer($new->refresh()->load('customer'));
```

- [ ] **Step 6.5: Run the test — confirm pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: 9/9 PASS.

- [ ] **Step 6.6: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +4, Pint clean, PHPStan 0, Vite OK.

- [ ] **Step 6.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/Booking/Services/BookingService.php \
        app/Domain/Booking/Services/AppointmentTransitionService.php \
        tests/Feature/Notifications/EventToNotificationTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): emit appointment + booking notifications from services (P5a/6)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 7: MedicalEntryService + PrescriptionService integration

**Files:**
- Modify: `app/Domain/MedicalRecord/Services/MedicalEntryService.php`
- Modify: `app/Domain/MedicalRecord/Services/PrescriptionService.php`
- Modify: `tests/Feature/Notifications/EventToNotificationTest.php`

- [ ] **Step 7.1: Extend the event-to-notification test**

Append to `tests/Feature/Notifications/EventToNotificationTest.php`:

```php
it('MedicalEntryService::create notifies the customer with medical category', function () {
    $appt = mkPaidPath($this->customer, $this->doctor);
    $appt->status = AppointmentStatus::Completed; $appt->save();

    $entry = app(\App\Domain\MedicalRecord\Services\MedicalEntryService::class)
        ->create($appt, $this->doctorUser, ['visible_summary' => 'flu', 'staff_notes' => 'shh', 'prescriptions' => []]);

    $n = $this->customer->notifications()->latest()->first();
    expect($n?->data['category'])->toBe('medical')
        ->and(json_encode($n->data, JSON_UNESCAPED_UNICODE))->not->toContain('shh');
});

it('PrescriptionService::add notifies the customer', function () {
    $appt = mkPaidPath($this->customer, $this->doctor);
    $appt->status = AppointmentStatus::Completed; $appt->save();
    $entry = app(\App\Domain\MedicalRecord\Services\MedicalEntryService::class)
        ->create($appt, $this->doctorUser, ['visible_summary' => 's', 'staff_notes' => null, 'prescriptions' => []]);
    $this->customer->notifications()->delete();

    app(\App\Domain\MedicalRecord\Services\PrescriptionService::class)
        ->add($entry, $this->doctorUser, ['medication_name' => 'X', 'dosage' => '1', 'frequency' => 'q8h', 'duration' => '5d', 'notes' => null]);

    expect($this->customer->notifications()->latest()->first()?->data['title'])->toContain('وصفة');
});
```

- [ ] **Step 7.2: Run it — confirm fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: 2 new failures.

- [ ] **Step 7.3: Wire MedicalEntryService**

Edit `app/Domain/MedicalRecord/Services/MedicalEntryService.php`:

1. Add import: `use App\Domain\Notification\Services\NotificationService;`
2. Constructor — accept `NotificationService` alongside the existing `AuditLogger`:

```php
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}
```

3. Inside the `create(...)` method's transaction closure, after the existing `$this->audit->record(...)` call (the entry-created audit), and before `return $entry;`, add:

```php
            $this->notifications->medicalEntryCreated($entry->refresh()->load('appointment.customer'));
```

Place the call BEFORE any prescription-loop audit calls but AFTER the entry's own audit. The intent: each medical entry triggers exactly one customer notification at create time, regardless of how many prescriptions were attached in the same call. Prescriptions added in a separate call go through `PrescriptionService::add`.

- [ ] **Step 7.4: Wire PrescriptionService**

Edit `app/Domain/MedicalRecord/Services/PrescriptionService.php`:

1. Add import + service to constructor (mirror MedicalEntryService).
2. Inside `add(...)`'s transaction closure, after the existing audit call and before returning, add:

```php
            $this->notifications->prescriptionAdded($prescription->refresh()->load('medicalEntry.appointment.customer'));
```

- [ ] **Step 7.5: Run the test — confirm pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Notifications/EventToNotificationTest.php`
Expected: 11/11 PASS.

- [ ] **Step 7.6: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +2, Pint clean, PHPStan 0, Vite OK.

- [ ] **Step 7.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/MedicalRecord/Services/MedicalEntryService.php \
        app/Domain/MedicalRecord/Services/PrescriptionService.php \
        tests/Feature/Notifications/EventToNotificationTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(notifications): emit medical-record notifications from services (P5a/7)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 8: DoD gate + ARCHITECTURE + CHANGELOG + tag

**Files:**
- Modify: `docs/ARCHITECTURE.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 8.1: Update ARCHITECTURE**

Edit `docs/ARCHITECTURE.md`. Append (or update an existing P5 stub) a new section:

```markdown
### Notification System (P5a)

In-app only, no SMS/email. Implementation:

- Single table `notifications` (Laravel standard) — payload in `data` JSON
- Category enum: `App\Enums\NotificationCategory` (Appointment / Payment / Medical / System)
- Generator: `App\Domain\Notification\Services\NotificationService` — explicit, called inline by originating services inside the same DB transaction (mirrors the AuditLogger pattern)
- UI: bell badge in both shells (lazy Inertia share `notifications.unread_count`) + dedicated notification center at `/admin/notifications` and `/portal/notifications` with category + unread filters and 20/page pagination
- Authorization: route-level closure `notifiable_id === auth()->id()` check; cross-user reads return 403
- No PHI in payload — `visible_summary` and other ADR-003-encrypted fields stay out of the `data` column

**Deferred to future ADRs:** SMS channel, Email channel, time-based reminders, admin broadcasts, per-category opt-out, realtime push.
See `docs/superpowers/specs/2026-05-20-jannahclinic-p5a-notifications-design.md` §9 for the full deferred-items table.
```

- [ ] **Step 8.2: Update CHANGELOG**

Edit `CHANGELOG.md`. Under the `## [Unreleased]` heading (create one if missing), add:

```markdown
### Added
- **P5a — Notification System.** Event-driven in-app notifications fan out from existing domain services (Payment, Appointment, Booking, MedicalEntry, Prescription) inside their transactions. Bell badge in both shells + dedicated notification center with category/unread filters. No SMS/email in scope.
```

- [ ] **Step 8.3: Full DoD gate**

Run the full gate ONE more time end-to-end:
```
cd /c/~projects/jannahclinic
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse --no-progress
npm run build
npx vitest run
```

Expected at this point:
- Pest: ~280 (≈248 before P5a + ~28 P5a additions). The exact count depends on suite at start of plan.
- Vitest: 26 (22 + 4 NotificationBell)
- Pint, PHPStan, Vite all clean

- [ ] **Step 8.4: Manual smoke**

1. Open the app with a doctor account → confirm a Requested appointment → log out.
2. Log in as that appointment's customer → bell badge shows 1 → click → see «تمّ تأكيد موعدك» → click row → arrive on the appointment page → return → badge gone.
3. From customer: upload a receipt → log out.
4. Log in as manager → bell shows 1 → click → see «إيصال جديد بانتظار المراجعة» → mark all read → badge gone.

If any step fails: fix root cause, re-run gate, do NOT proceed to tag.

- [ ] **Step 8.5: Commit docs + tag + push**

```bash
cd /c/~projects/jannahclinic
git add docs/ARCHITECTURE.md CHANGELOG.md
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "docs(p5a): ARCHITECTURE + CHANGELOG entries for notification system (P5a/8)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag p5a-notifications
git push origin main
git push origin p5a-notifications
```

- [ ] **Step 8.6: Done**

P5a is complete. Report to user:
- Test counts (Pest / Vitest / Pint / PHPStan / Vite build)
- Tag pushed: `p5a-notifications`
- Highlight the manual smoke that was performed

---

## Self-Review Summary

**Spec coverage:** Every row of spec §3 event catalog → §5 service integration → §10 sequencing maps to a task above. Spec §6.5 click-to-read race is implemented via the POST mark-read endpoints returning a `redirect()` to `data.action_url`. Spec §7 cross-user 403 enforcement is covered by Task 2 tests.

**Deferred items (spec §9):** Explicitly not touched — no SMS, no email, no schedulers, no realtime, no opt-out preferences.

**No placeholders:** every step has concrete code or exact commands.

**Type consistency:** `NotificationService` method names are identical across tasks (`appointmentConfirmed`, `paymentApproved`, `medicalEntryCreated`, etc.). `NotificationCategory` enum values (`appointment` / `payment` / `medical` / `system`) referenced consistently across PHP and Vue code.
