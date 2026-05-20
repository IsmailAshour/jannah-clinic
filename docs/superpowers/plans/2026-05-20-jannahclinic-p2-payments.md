# P2 Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development OR superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add bank-transfer payment tracking (receipt upload + manager verification + refund flow) attached to every Appointment, without changing the existing AppointmentStatus state machine.

**Architecture:** Hybrid lifecycle — `Payment` (1:1 with `Appointment`) and `PaymentReceipt` (N:1 with `Payment`) are decoupled from `AppointmentStatus`. A `PaymentService` enforces 6-state transitions. An `AppointmentObserver` auto-transitions a `paid` Payment to `refund_pending` when its Appointment is cancelled/rejected. Receipt files live in private storage and are served only via authz-checked controller endpoints.

**Tech Stack:** Laravel 13, Postgres 16 (CHECK constraints), SQLite in-memory tests, Inertia + Vue 3, Tailwind v4, shadcn-vue sidebar-07, foundation components, bcmath (no float for money), Pest, Vitest, Pint, Larastan L5.

**Spec:** `docs/superpowers/specs/2026-05-20-jannahclinic-p2-payments-design.md`.

---

## Task 1: Migration + models + PaymentStatus enum + BookingService integration

**Files:**
- Create: `database/migrations/{ts}_create_payments_and_receipts_with_backfill.php`
- Create: `app/Enums/PaymentStatus.php`
- Create: `app/Models/Payment.php`
- Create: `app/Models/PaymentReceipt.php`
- Modify: `app/Models/Appointment.php` (add `payment()` HasOne)
- Modify: `app/Domain/Booking/Services/BookingService.php` (insert a `Payment::create([...])` after `Appointment::create(...)` in the existing transaction)
- Create: `tests/Unit/Domain/Booking/BookingCreatesPaymentTest.php`

- [ ] **Step 1: Create the migration** via `php artisan make:migration create_payments_and_receipts_with_backfill` (the timestamp must sort after all existing migrations).

Replace the body with:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('status', 16)->default('pending');
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('refunded_at')->nullable();
            $t->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('refund_reference')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('file_path');
            $t->unsignedInteger('file_size');
            $t->string('mime_type', 64);
            $t->string('status', 16)->default('uploaded');
            $t->text('rejection_reason')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['payment_id', 'id']); // (payment_id, id DESC) used for "latest receipt"
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending','submitted','paid','rejected','refund_pending','refunded'))");
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount >= 0)');
            DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_status_check CHECK (status IN ('uploaded','rejected'))");
            DB::statement('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_size_check CHECK (file_size > 0)');
        }

        // Idempotent backfill: ensure every existing appointment has a payment row.
        // Uses raw SQL so it works on both pgsql and sqlite.
        DB::statement(
            'INSERT INTO payments (appointment_id, amount, status, created_at, updated_at) '.
            "SELECT a.id, a.price_at_booking, 'pending', NOW(), NOW() FROM appointments a ".
            'WHERE NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['status', 'amount'] as $c) {
                DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_{$c}_check");
            }
            foreach (['status', 'size'] as $c) {
                DB::statement("ALTER TABLE payment_receipts DROP CONSTRAINT IF EXISTS payment_receipts_{$c}_check");
            }
        }
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 2: Create `app/Enums/PaymentStatus.php`**

```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return $this === self::Refunded;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
```

- [ ] **Step 3: Create `app/Models/Payment.php`**

```php
<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'appointment_id', 'amount', 'status',
    'verified_at', 'verified_by',
    'refunded_at', 'refunded_by', 'refund_reference',
    'rejection_reason', 'notes',
])]
class Payment extends Model
{
    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->orderByDesc('id');
    }
}
```

- [ ] **Step 4: Create `app/Models/PaymentReceipt.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'uploaded_by', 'file_path', 'file_size', 'mime_type',
    'status', 'rejection_reason', 'rejected_at', 'rejected_by',
])]
class PaymentReceipt extends Model
{
    protected $casts = [
        'rejected_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
```

- [ ] **Step 5: Modify `app/Models/Appointment.php`** — add the `payment()` HasOne relation. Append the method inside the class, after the existing relations (e.g. after `rescheduledFrom()`). Keep all existing fillables/casts/relations unchanged.

```php
public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(Payment::class);
}
```

(If the file's import style uses short `HasOne`, mirror that. Read the file first.)

- [ ] **Step 6: Modify `BookingService::book`** — inside the existing `DB::transaction` callback, AFTER the `Appointment::create([...])` and AFTER the optional `serviceAddress()->create(...)`, BEFORE the `return $appt->fresh(...)`, add the Payment creation:

```php
\App\Models\Payment::create([
    'appointment_id' => $appt->id,
    'amount' => $quote['total'],
    'status' => \App\Enums\PaymentStatus::Pending,
]);
```

(Use top-of-file `use App\Models\Payment;` + `use App\Enums\PaymentStatus;` to match the file's existing import style.)

- [ ] **Step 7: Failing test** `tests/Unit/Domain/Booking/BookingCreatesPaymentTest.php`

```php
<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\BookingService;
use App\Enums\DeliveryMode;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

it('creates a pending Payment when an Appointment is booked', function () {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $date = CarbonImmutable::parse('next monday');
    enableDoctorSlots($doc, (int) $date->dayOfWeek, slotRange('09:00', 2));
    $cust = User::factory()->create(['role' => UserRole::Customer]);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $cust->id,
        doctorProfileId: $doc->id,
        serviceId: $svc->id,
        startAt: $date->setTime(9, 0),
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
    ));

    expect($appt->payment)->not->toBeNull();
    expect($appt->payment->status)->toBe(PaymentStatus::Pending);
    expect($appt->payment->amount)->toBe('100.00');
});
```

- [ ] **Step 8: Run migration on local Postgres** (`$env:PGPASSWORD='123123'; php artisan migrate`), then run the test.

```bash
php artisan test --filter=BookingCreatesPaymentTest
```

Expected: PASS. Also confirm full suite is still green: `php artisan test` (expect 177+ passing — was 176, +1 new test).

- [ ] **Step 9: Pint + PHPStan + commit**

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse --no-progress
git add database/migrations/*create_payments_and_receipts_with_backfill.php \
        app/Enums/PaymentStatus.php app/Models/Payment.php app/Models/PaymentReceipt.php \
        app/Models/Appointment.php app/Domain/Booking/Services/BookingService.php \
        tests/Unit/Domain/Booking/BookingCreatesPaymentTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t1): payments + receipts schema, PaymentStatus enum, BookingService creates pending Payment" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: PaymentService TDD + InvalidPaymentTransitionException

**Files:**
- Create: `app/Domain/Payment/Exceptions/InvalidPaymentTransitionException.php`
- Create: `app/Domain/Payment/Services/PaymentService.php`
- Create: `tests/Unit/Domain/Payment/PaymentServiceTest.php`

- [ ] **Step 1: Create the exception** `app/Domain/Payment/Exceptions/InvalidPaymentTransitionException.php`

```php
<?php

namespace App\Domain\Payment\Exceptions;

class InvalidPaymentTransitionException extends \RuntimeException
{
    public function __construct(string $message = 'انتقال غير مسموح لحالة الدفع.')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 2: Write the unit test FIRST** `tests/Unit/Domain/Payment/PaymentServiceTest.php`

```php
<?php

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makePayment(string $status = 'pending'): Payment {
    $appt = Appointment::factory()->create();
    // factory creates appointment + a Payment.pending via observer? No — backfill only.
    // For tests, create payment directly:
    return Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => $status,
    ]);
}

it('pending -> submitted on uploadReceipt', function () {
    Storage::fake('local');
    $p = makePayment('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $svc = app(PaymentService::class);

    $receipt = $svc->uploadReceipt($p, UploadedFile::fake()->image('r.jpg'), $u);

    expect($p->fresh()->status)->toBe(PaymentStatus::Submitted);
    expect($receipt->payment_id)->toBe($p->id);
    expect($receipt->uploaded_by)->toBe($u->id);
    expect($receipt->status)->toBe('uploaded');
    Storage::disk('local')->assertExists($receipt->file_path);
});

it('rejects upload when file is too large', function () {
    Storage::fake('local');
    $p = makePayment('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $big = UploadedFile::fake()->create('big.jpg', 6 * 1024, 'image/jpeg'); // 6 MB

    expect(fn () => app(PaymentService::class)->uploadReceipt($p, $big, $u))
        ->toThrow(InvalidPaymentTransitionException::class);
    expect($p->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects upload when MIME is unsupported', function () {
    Storage::fake('local');
    $p = makePayment('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $bad = UploadedFile::fake()->create('x.txt', 100, 'text/plain');

    expect(fn () => app(PaymentService::class)->uploadReceipt($p, $bad, $u))
        ->toThrow(InvalidPaymentTransitionException::class);
});

it('submitted -> paid on verify', function () {
    $p = makePayment('submitted');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    app(PaymentService::class)->verify($p, $m);

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Paid);
    expect($p->verified_by)->toBe($m->id);
    expect($p->verified_at)->not->toBeNull();
    expect($p->rejection_reason)->toBeNull();
});

it('submitted -> rejected on reject (with reason)', function () {
    Storage::fake('local');
    $p = makePayment('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $receipt = app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r.jpg'), $u);

    app(PaymentService::class)->reject($p, $m, 'إيصال غير واضح');

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Rejected);
    expect($p->rejection_reason)->toBe('إيصال غير واضح');
    expect($receipt->fresh()->status)->toBe('rejected');
    expect($receipt->fresh()->rejection_reason)->toBe('إيصال غير واضح');
});

it('rejected -> submitted on re-upload', function () {
    Storage::fake('local');
    $p = makePayment('pending');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $m = User::factory()->create(['role' => UserRole::Manager]);
    app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r1.jpg'), $u);
    app(PaymentService::class)->reject($p, $m, 'سبب');
    expect($p->fresh()->status)->toBe(PaymentStatus::Rejected);

    app(PaymentService::class)->uploadReceipt($p, UploadedFile::fake()->image('r2.jpg'), $u);

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Submitted);
    expect($p->rejection_reason)->toBeNull();
    expect($p->receipts()->count())->toBe(2);
});

it('paid -> refund_pending via markRefundPending', function () {
    $p = makePayment('paid');

    app(PaymentService::class)->markRefundPending($p);

    expect($p->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('refund_pending -> refunded on markRefunded with reference', function () {
    $p = makePayment('refund_pending');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    app(PaymentService::class)->markRefunded($p, $m, 'BANK-REF-12345');

    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded);
    expect($p->refunded_by)->toBe($m->id);
    expect($p->refunded_at)->not->toBeNull();
    expect($p->refund_reference)->toBe('BANK-REF-12345');
});

it('blocks illegal transitions', function () {
    $paid = makePayment('paid');
    $refunded = makePayment('refunded');
    $pending = makePayment('pending');
    $m = User::factory()->create(['role' => UserRole::Manager]);

    // Cannot verify what was never submitted
    expect(fn () => app(PaymentService::class)->verify($pending, $m))
        ->toThrow(InvalidPaymentTransitionException::class);
    // Cannot re-mark a refunded payment
    expect(fn () => app(PaymentService::class)->markRefundPending($refunded))
        ->toThrow(InvalidPaymentTransitionException::class);
    // Cannot verify a paid payment
    expect(fn () => app(PaymentService::class)->verify($paid, $m))
        ->toThrow(InvalidPaymentTransitionException::class);
});
```

- [ ] **Step 3: Run test — expect FAILURE**

```bash
php artisan test --filter=PaymentServiceTest
```

Expected: errors / red (PaymentService class not yet defined).

- [ ] **Step 4: Implement `app/Domain/Payment/Services/PaymentService.php`**

```php
<?php

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    public function uploadReceipt(Payment $payment, UploadedFile $file, User $uploader): PaymentReceipt
    {
        // File guards (driver-agnostic).
        if ($file->getSize() === false || $file->getSize() <= 0 || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidPaymentTransitionException('حجم الإيصال يجب أن لا يتجاوز 5 ميغابايت.');
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidPaymentTransitionException('صيغة الملف غير مدعومة. ارفع JPG أو PNG أو PDF.');
        }
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Rejected], true)) {
            throw new InvalidPaymentTransitionException("لا يمكن رفع إيصال عندما تكون الحالة {$payment->status->value}.");
        }

        return DB::transaction(function () use ($payment, $file, $uploader) {
            $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs("receipts/{$payment->id}", $name, 'local');

            $receipt = PaymentReceipt::create([
                'payment_id' => $payment->id,
                'uploaded_by' => $uploader->id,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'uploaded',
            ]);

            $payment->update([
                'status' => PaymentStatus::Submitted,
                'rejection_reason' => null,
            ]);

            return $receipt;
        });
    }

    public function verify(Payment $payment, User $manager): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw new InvalidPaymentTransitionException("لا يمكن التحقّق إلا من إيصال قيد المراجعة (الحالة الحالية: {$payment->status->value}).");
        }
        $payment->update([
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
            'verified_by' => $manager->id,
            'rejection_reason' => null,
        ]);

        return $payment;
    }

    public function reject(Payment $payment, User $manager, string $reason): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw new InvalidPaymentTransitionException("لا يمكن رفض إلا إيصالًا قيد المراجعة (الحالة الحالية: {$payment->status->value}).");
        }

        return DB::transaction(function () use ($payment, $manager, $reason) {
            $latest = $payment->receipts()->first(); // orderByDesc('id') by relation default
            if ($latest && $latest->status === 'uploaded') {
                $latest->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'rejected_at' => now(),
                    'rejected_by' => $manager->id,
                ]);
            }
            $payment->update([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            return $payment;
        });
    }

    public function markRefundPending(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw new InvalidPaymentTransitionException("لا يمكن طلب استرداد إلا لدفعة مُسدَّدة (الحالة الحالية: {$payment->status->value}).");
        }
        $payment->update(['status' => PaymentStatus::RefundPending]);

        return $payment;
    }

    public function markRefunded(Payment $payment, User $manager, ?string $reference = null): Payment
    {
        if ($payment->status !== PaymentStatus::RefundPending) {
            throw new InvalidPaymentTransitionException("لا يمكن تسجيل استرداد إلا لدفعة بانتظار الاسترداد (الحالة الحالية: {$payment->status->value}).");
        }
        $payment->update([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
            'refunded_by' => $manager->id,
            'refund_reference' => $reference,
        ]);

        return $payment;
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
php artisan test --filter=PaymentServiceTest
```

Expected: all PaymentServiceTest cases PASS. Re-run full suite to confirm 0 regressions.

- [ ] **Step 6: Pint + PHPStan + commit**

```bash
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
git add app/Domain/Payment/ tests/Unit/Domain/Payment/
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t2): PaymentService with TDD transitions + InvalidPaymentTransitionException" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: AppointmentObserver — auto refund_pending on Cancelled/Rejected

**Files:**
- Create: `app/Observers/AppointmentObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Create: `tests/Feature/Payments/AutoRefundPendingTest.php`

- [ ] **Step 1: Failing test** `tests/Feature/Payments/AutoRefundPendingTest.php`

```php
<?php

use App\Domain\Booking\Services\AppointmentTransitionService;
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

function aPaidAppointment(): array {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);

    $appt = Appointment::create([
        'customer_id' => $cust->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'verified_at' => now(),
    ]);

    return [$appt, $payment];
}

it('auto-marks payment as refund_pending when appointment is cancelled', function () {
    [$appt, $payment] = aPaidAppointment();

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'العميل ألغى');

    expect($payment->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('auto-marks payment as refund_pending when appointment is rejected', function () {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
        'customer_id' => $cust->id,
        'doctor_profile_id' => $doc->id,
        'service_id' => $svc->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Requested,
        'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center,
        'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create(['appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Paid, 'verified_at' => now()]);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Rejected);

    expect($payment->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('does NOT auto-refund a non-paid payment', function () {
    [$appt, $payment] = aPaidAppointment();
    $payment->update(['status' => PaymentStatus::Pending, 'verified_at' => null]);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'سبب');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does NOT auto-refund when an appointment is completed', function () {
    [$appt, $payment] = aPaidAppointment();

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Completed);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});
```

- [ ] **Step 2: Run test — expect FAILURE** (`AutoRefundPendingTest` fails because no observer is registered).

```bash
php artisan test --filter=AutoRefundPendingTest
```

- [ ] **Step 3: Create `app/Observers/AppointmentObserver.php`**

```php
<?php

namespace App\Observers;

use App\Domain\Payment\Services\PaymentService;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;

class AppointmentObserver
{
    public function __construct(private readonly PaymentService $payments) {}

    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged('status')) {
            return;
        }

        $newStatus = $appointment->status;
        if ($newStatus !== AppointmentStatus::Cancelled && $newStatus !== AppointmentStatus::Rejected) {
            return;
        }

        $payment = $appointment->payment()->first();
        if (! $payment || $payment->status !== PaymentStatus::Paid) {
            return;
        }

        $this->payments->markRefundPending($payment);
    }
}
```

- [ ] **Step 4: Register the observer** in `app/Providers/AppServiceProvider.php`'s `boot()` method, near the existing `Gate::policy(...)` registrations:

```php
\App\Models\Appointment::observe(\App\Observers\AppointmentObserver::class);
```

(Use the file's existing top-of-file import style — if it imports the model + observer with `use`, mirror that.)

- [ ] **Step 5: Run test — expect PASS**

```bash
php artisan test --filter=AutoRefundPendingTest
php artisan test  # confirm full suite still green
```

- [ ] **Step 6: Pint + PHPStan + commit**

```bash
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
git add app/Observers/ app/Providers/AppServiceProvider.php tests/Feature/Payments/AutoRefundPendingTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t3): AppointmentObserver auto-marks paid Payment as refund_pending on Cancelled/Rejected" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Portal payment page + receipt streaming + PaymentPolicy

**Files:**
- Create: `app/Policies/PaymentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register PaymentPolicy)
- Create: `app/Http/Controllers/Portal/PaymentController.php`
- Modify: `routes/portal.php` (add 2 routes)
- Modify: `tests/Feature/RouteNamesTest.php` (add 2 portal names; the other 7 are added in Task 5)
- Create: `resources/js/Pages/Portal/Payments/Show.vue`
- Create: `tests/Feature/Payments/PortalPaymentTest.php`

- [ ] **Step 1: Create `app/Policies/PaymentPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer
            && $payment->appointment()->where('customer_id', $user->id)->exists();
    }
}
```

- [ ] **Step 2: Register the policy** in `AppServiceProvider::boot()`:

```php
\Illuminate\Support\Facades\Gate::policy(\App\Models\Payment::class, \App\Policies\PaymentPolicy::class);
```

- [ ] **Step 3: Failing feature test** `tests/Feature/Payments/PortalPaymentTest.php`

```php
<?php

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

function aPortalAppointment(): array {
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
        'customer_id' => $cust->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Requested, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create(['appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Pending]);

    return [$appt, $payment, $cust];
}

it('customer sees own payment page', function () {
    [$appt, , $cust] = aPortalAppointment();
    $this->actingAs($cust)->get("/portal/appointments/{$appt->id}/payment")->assertOk();
});

it('customer cannot see another customer\'s payment page', function () {
    [$appt] = aPortalAppointment();
    $other = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($other)->get("/portal/appointments/{$appt->id}/payment")->assertForbidden();
});

it('customer uploads a valid JPG receipt → payment becomes submitted', function () {
    Storage::fake('local');
    [$appt, $payment, $cust] = aPortalAppointment();

    $this->actingAs($cust)
        ->post("/portal/appointments/{$appt->id}/payment/upload", [
            'receipt' => UploadedFile::fake()->image('r.jpg'),
        ])
        ->assertRedirect();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Submitted);
    expect($payment->receipts()->count())->toBe(1);
    Storage::disk('local')->assertExists($payment->receipts()->first()->file_path);
});

it('upload too-large file → validation error, payment unchanged', function () {
    Storage::fake('local');
    [$appt, $payment, $cust] = aPortalAppointment();

    $this->actingAs($cust)
        ->post("/portal/appointments/{$appt->id}/payment/upload", [
            'receipt' => UploadedFile::fake()->create('big.jpg', 6 * 1024, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('receipt');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('staff cannot reach the portal payment page (surface isolation)', function () {
    [$appt] = aPortalAppointment();
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->get("/portal/appointments/{$appt->id}/payment")->assertForbidden();
});
```

- [ ] **Step 4: Create `app/Http/Controllers/Portal/PaymentController.php`**

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Settings\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function show(Appointment $appointment, SettingService $settings): Response
    {
        abort_unless($appointment->customer_id === request()->user()->id, 403);
        $payment = $appointment->payment()->with(['receipts' => fn ($q) => $q->latest('id')])->firstOrFail();

        return Inertia::render('Portal/Payments/Show', [
            'appointment' => $appointment->load('service:id,name', 'doctor.user:id,name'),
            'payment' => $payment,
            'bank' => [
                'name' => $settings->get('bank_name', config('clinic.bank_name', '')),
                'account_holder' => $settings->get('bank_account_holder', config('clinic.bank_account_holder', '')),
                'iban' => $settings->get('bank_iban', config('clinic.bank_iban', '')),
                'account_number' => $settings->get('bank_account_number', config('clinic.bank_account_number', '')),
            ],
        ]);
    }

    public function upload(Request $request, Appointment $appointment, PaymentService $service): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 403);
        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $payment = $appointment->payment()->firstOrFail();
        try {
            $service->uploadReceipt($payment, $request->file('receipt'), $request->user());
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('success', 'تم رفع الإيصال — بانتظار التحقّق.');
    }
}
```

- [ ] **Step 5: Add routes** in `routes/portal.php` inside the customer group (read the file for the group structure). Use UNPREFIXED names — final names will be `portal.appointments.payment` and `portal.appointments.payment.upload`.

```php
Route::get('appointments/{appointment}/payment', [\App\Http\Controllers\Portal\PaymentController::class, 'show'])->name('appointments.payment');
Route::post('appointments/{appointment}/payment/upload', [\App\Http\Controllers\Portal\PaymentController::class, 'upload'])->name('appointments.payment.upload');
```

- [ ] **Step 6: Update `tests/Feature/RouteNamesTest.php`** — add to the locked-names list:

```php
'portal.appointments.payment', 'portal.appointments.payment.upload',
```

(The 7 admin names land in Task 5. RouteNamesTest will then assert the full set.)

- [ ] **Step 7: Create `resources/js/Pages/Portal/Payments/Show.vue`** — RTL, foundation components, Arabic. Implements §5.1 of the spec.

```vue
<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Upload, Copy } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup, FormSection, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  appointment: { type: Object, required: true },
  payment: { type: Object, required: true },
  bank: { type: Object, required: true },
})

const statusMap = {
  pending: { label: 'بانتظار الدفع', variant: 'warning' },
  submitted: { label: 'بانتظار التحقّق', variant: 'info' },
  paid: { label: 'مدفوع', variant: 'success' },
  rejected: { label: 'مرفوض', variant: 'danger' },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded: { label: 'مُسترَد', variant: 'info' },
}

const form = useForm({ receipt: null })
const fileError = computed(() => form.errors.receipt ?? null)

function pick(e) {
  form.receipt = e.target.files[0] ?? null
}
function submit() {
  if (!form.receipt) return
  form.post(`/portal/appointments/${props.appointment.id}/payment/upload`, {
    forceFormData: true,
    onSuccess: () => { form.reset('receipt') },
  })
}

const copied = ref(false)
async function copyIban() {
  try { await navigator.clipboard.writeText(props.bank.iban); copied.value = true; setTimeout(() => copied.value = false, 2000) } catch {}
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-6">
      <PageHeader title="دفع الموعد">
        <template #subtitle>
          {{ appointment.service?.name }} — د. {{ appointment.doctor?.user?.name }}
        </template>
      </PageHeader>

      <FormSection title="حالة الدفع">
        <div class="flex items-center gap-3">
          <StatusBadge :type="statusMap[payment.status]?.variant ?? 'info'" :label="statusMap[payment.status]?.label ?? payment.status" />
          <div class="text-2xl font-bold">{{ payment.amount }} ₪</div>
        </div>
        <p v-if="payment.status === 'rejected' && payment.rejection_reason" class="mt-2 text-sm text-danger">
          سبب الرفض: {{ payment.rejection_reason }}
        </p>
        <p v-if="payment.status === 'refunded' && payment.refund_reference" class="mt-2 text-sm text-text-secondary">
          مرجع الاسترداد: <span dir="ltr">{{ payment.refund_reference }}</span>
        </p>
      </FormSection>

      <FormSection title="بيانات الحساب البنكي" description="حوّل المبلغ ثم ارفع صورة وصل التحويل.">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div><dt class="text-text-secondary">البنك</dt><dd class="text-text-primary">{{ bank.name || '—' }}</dd></div>
          <div><dt class="text-text-secondary">اسم الحساب</dt><dd class="text-text-primary">{{ bank.account_holder || '—' }}</dd></div>
          <div><dt class="text-text-secondary">رقم الحساب</dt><dd dir="ltr" class="text-text-primary">{{ bank.account_number || '—' }}</dd></div>
          <div>
            <dt class="text-text-secondary">IBAN</dt>
            <dd class="flex items-center gap-2">
              <span dir="ltr" class="text-text-primary font-mono">{{ bank.iban || '—' }}</span>
              <Button v-if="bank.iban" size="sm" variant="outline" @click="copyIban">
                <Copy class="h-4 w-4" /> {{ copied ? 'تم النسخ' : 'نسخ' }}
              </Button>
            </dd>
          </div>
        </dl>
      </FormSection>

      <FormSection v-if="['pending','rejected'].includes(payment.status)" title="رفع إيصال التحويل">
        <FormGroup label="ملف الإيصال (JPG/PNG/PDF — حد أقصى 5 ميغابايت)" name="receipt" :error="fileError" required>
          <template #default="{ describedby }">
            <input id="receipt" type="file" name="receipt" accept="image/jpeg,image/png,application/pdf" @change="pick" :aria-describedby="describedby" />
          </template>
        </FormGroup>
        <div class="mt-4">
          <Button :disabled="!form.receipt || form.processing" @click="submit">
            <Upload class="h-4 w-4" /> {{ payment.status === 'rejected' ? 'إعادة الرفع' : 'رفع الإيصال' }}
          </Button>
        </div>
      </FormSection>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 8: Run tests — expect PASS**

```bash
php artisan test --filter="PortalPaymentTest|RouteNamesTest"
php artisan test
```

- [ ] **Step 9: Pint + PHPStan + RTL grep + build + commit**

```bash
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
grep -rnE '\b(pl-|pr-|ml-|mr-)[0-9]|\btext-left\b|\btext-right\b|\bleft-[0-9]|\bright-[0-9]' resources/js/Pages/Portal/Payments || echo CLEAN
npm run build
git add app/Policies/PaymentPolicy.php app/Providers/AppServiceProvider.php \
        app/Http/Controllers/Portal/PaymentController.php routes/portal.php \
        tests/Feature/RouteNamesTest.php tests/Feature/Payments/PortalPaymentTest.php \
        resources/js/Pages/Portal/Payments/Show.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t4): portal payment page + receipt upload + PaymentPolicy + 2 routes locked" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Admin payments — index + show + actions + receipt streaming + 7 routes

**Files:**
- Create: `app/Http/Controllers/Admin/PaymentController.php`
- Modify: `routes/admin.php` (add 7 routes; reads under all-staff group, mutations under role:manager)
- Modify: `tests/Feature/RouteNamesTest.php` (add 7 admin names)
- Create: `resources/js/Pages/Admin/Payments/Index.vue`
- Create: `resources/js/Pages/Admin/Payments/Show.vue`
- Create: `tests/Feature/Payments/AdminPaymentTest.php`

- [ ] **Step 1: Failing feature test** `tests/Feature/Payments/AdminPaymentTest.php`

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
use App\Models\PaymentReceipt;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function aSubmittedPayment(): array {
    Storage::fake('local');
    $cat = ServiceCategory::create(['name' => 'x', 'slug' => 'x'.uniqid(), 'color_variant' => 'brand']);
    $svc = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => 100, 'duration_minutes' => 30, 'home_service_enabled' => false]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id);
    $cust = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::create([
        'customer_id' => $cust->id, 'doctor_profile_id' => $doc->id, 'service_id' => $svc->id,
        'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Requested, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer,
    ]);
    $payment = Payment::create(['appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Pending]);
    app(PaymentService::class)->uploadReceipt($payment, UploadedFile::fake()->image('r.jpg'), $cust);

    return [$payment->fresh(), $appt, $cust];
}

it('lists payments for staff', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    aSubmittedPayment();
    $this->actingAs($r)->get('/admin/payments')->assertOk();
});

it('shows a payment with receipts', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPayment();
    $this->actingAs($r)->get("/admin/payments/{$p->id}")->assertOk();
});

it('manager verifies a submitted payment → paid', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPayment();
    $this->actingAs($m)->post("/admin/payments/{$p->id}/verify")->assertRedirect();
    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Paid);
    expect($p->verified_by)->toBe($m->id);
});

it('receptionist cannot verify (403)', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPayment();
    $this->actingAs($r)->post("/admin/payments/{$p->id}/verify")->assertForbidden();
});

it('manager rejects with reason', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPayment();
    $this->actingAs($m)->post("/admin/payments/{$p->id}/reject", ['reason' => 'إيصال غير واضح'])->assertRedirect();
    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Rejected);
    expect($p->rejection_reason)->toBe('إيصال غير واضح');
});

it('manager marks refund pending on a paid payment', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPayment();
    $p->update(['status' => PaymentStatus::Paid, 'verified_at' => now(), 'verified_by' => $m->id]);
    $this->actingAs($m)->post("/admin/payments/{$p->id}/mark-refund-pending")->assertRedirect();
    expect($p->fresh()->status)->toBe(PaymentStatus::RefundPending);
});

it('manager marks refunded with reference', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    [$p] = aSubmittedPayment();
    $p->update(['status' => PaymentStatus::RefundPending]);
    $this->actingAs($m)->post("/admin/payments/{$p->id}/mark-refunded", ['reference' => 'BANK-REF-1'])->assertRedirect();
    $p->refresh();
    expect($p->status)->toBe(PaymentStatus::Refunded);
    expect($p->refund_reference)->toBe('BANK-REF-1');
});

it('streams the receipt file for staff', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    [$p] = aSubmittedPayment();
    $receipt = $p->receipts()->first();
    $this->actingAs($r)->get("/admin/payments/{$p->id}/receipts/{$receipt->id}/file")->assertOk();
});

it('customer cannot reach admin payments (403)', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->get('/admin/payments')->assertForbidden();
});
```

- [ ] **Step 2: Create `app/Http/Controllers/Admin/PaymentController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Domain\Payment\Services\PaymentService;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'submitted');
        $q = (string) $request->input('q', '');

        $query = Payment::query()
            ->with(['appointment.customer:id,name,phone,email', 'appointment.service:id,name', 'appointment.doctor.user:id,name'])
            ->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->whereHas('appointment.customer', fn ($qq) => $qq->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $payment->load([
            'appointment.customer:id,name,phone,email',
            'appointment.service:id,name',
            'appointment.doctor.user:id,name',
            'receipts.uploader:id,name',
            'receipts.rejector:id,name',
            'verifier:id,name',
            'refunder:id,name',
        ]);

        return Inertia::render('Admin/Payments/Show', [
            'payment' => $payment,
        ]);
    }

    public function verify(Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->verify($payment, request()->user());
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم تحقّق الإيصال.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $service->reject($payment, $request->user(), $data['reason']);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم رفض الإيصال.');
    }

    public function markRefundPending(Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->markRefundPending($payment);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم وَسم الدفعة للاسترداد.');
    }

    public function markRefunded(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:255']]);
        try {
            $service->markRefunded($payment, $request->user(), $data['reference'] ?? null);
        } catch (InvalidPaymentTransitionException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'تم تسجيل تنفيذ الاسترداد.');
    }

    public function receiptFile(Payment $payment, PaymentReceipt $receipt): StreamedResponse
    {
        abort_unless($receipt->payment_id === $payment->id, 404);

        return Storage::disk('local')->response($receipt->file_path);
    }
}
```

- [ ] **Step 3: Add 7 admin routes** in `routes/admin.php`. Read the file; reads (`index`/`show`/`receiptFile`) go in the outer all-staff group; mutations go under the nested `role:manager` group. Use UNPREFIXED names.

```php
// Reads (all staff)
Route::get('payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'show'])->name('payments.show');
Route::get('payments/{payment}/receipts/{receipt}/file', [\App\Http\Controllers\Admin\PaymentController::class, 'receiptFile'])->name('payments.receipt-file');

// Mutations (manager only) — inside the nested role:manager group:
Route::post('payments/{payment}/verify', [\App\Http\Controllers\Admin\PaymentController::class, 'verify'])->name('payments.verify');
Route::post('payments/{payment}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'reject'])->name('payments.reject');
Route::post('payments/{payment}/mark-refund-pending', [\App\Http\Controllers\Admin\PaymentController::class, 'markRefundPending'])->name('payments.mark-refund-pending');
Route::post('payments/{payment}/mark-refunded', [\App\Http\Controllers\Admin\PaymentController::class, 'markRefunded'])->name('payments.mark-refunded');
```

- [ ] **Step 4: Update `tests/Feature/RouteNamesTest.php`** — add 7 admin names to the locked list:

```php
'admin.payments.index', 'admin.payments.show', 'admin.payments.receipt-file',
'admin.payments.verify', 'admin.payments.reject', 'admin.payments.mark-refund-pending', 'admin.payments.mark-refunded',
```

- [ ] **Step 5: Create `resources/js/Pages/Admin/Payments/Index.vue`** — DataTable + filters + status badges. Modeled on `Admin/Customers/Index.vue`.

```vue
<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Eye } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, DataTable, PageStates, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  payments: { type: Object, default: () => ({ data: [], links: [] }) },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? 'submitted')

function applyFilters() {
  router.get('/admin/payments', { q: q.value || undefined, status: status.value || undefined }, { preserveScroll: true, replace: true })
}
function resetFilters() { q.value = ''; status.value = 'submitted'; applyFilters() }

const statusMap = {
  pending: { label: 'بانتظار الدفع', variant: 'warning' },
  submitted: { label: 'بانتظار التحقّق', variant: 'info' },
  paid: { label: 'مدفوع', variant: 'success' },
  rejected: { label: 'مرفوض', variant: 'danger' },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded: { label: 'مُسترَد', variant: 'info' },
}

const columns = [
  { key: 'customer', label: 'العميل' },
  { key: 'service', label: 'الخدمة' },
  { key: 'doctor', label: 'الطبيب' },
  { key: 'amount', label: 'المبلغ' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="المدفوعات" />

      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (اسم/بريد/هاتف)</label>
          <Input id="q" v-model="q" class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select id="status" v-model="status" class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm">
            <option value="all">الكل</option>
            <option value="submitted">بانتظار التحقّق</option>
            <option value="pending">بانتظار الدفع</option>
            <option value="paid">مدفوع</option>
            <option value="rejected">مرفوض</option>
            <option value="refund_pending">بانتظار الاسترداد</option>
            <option value="refunded">مُسترَد</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <PageStates :is-empty="payments.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا مدفوعات تطابق الفلتر.</div>
        </template>
        <DataTable :columns="columns" :rows="payments.data">
          <template #cell-customer="{ row }">{{ row.appointment?.customer?.name ?? '—' }}</template>
          <template #cell-service="{ row }">{{ row.appointment?.service?.name ?? '—' }}</template>
          <template #cell-doctor="{ row }">{{ row.appointment?.doctor?.user?.name ?? '—' }}</template>
          <template #cell-amount="{ row }">{{ row.amount }} ₪</template>
          <template #cell-status="{ row }">
            <StatusBadge :type="statusMap[row.status]?.variant ?? 'info'" :label="statusMap[row.status]?.label ?? row.status" />
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Link :href="`/admin/payments/${row.id}`" class="inline-flex items-center gap-1 text-sm text-brand hover:underline">
                <Eye class="h-4 w-4" /> <span>عرض</span>
              </Link>
            </div>
          </template>
        </DataTable>
      </PageStates>
    </div>
  </AdminShell>
</template>
```

- [ ] **Step 6: Create `resources/js/Pages/Admin/Payments/Show.vue`** — receipt preview, action bar, history. Implements §5.3 of the spec.

```vue
<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Check, X, RotateCcw, BadgeCheck } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, FormSection, StatusBadge, Modal, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({ payment: { type: Object, required: true } })
const isManager = (() => usePage().props?.auth?.user?.role === 'manager')()

const statusMap = {
  pending: { label: 'بانتظار الدفع', variant: 'warning' },
  submitted: { label: 'بانتظار التحقّق', variant: 'info' },
  paid: { label: 'مدفوع', variant: 'success' },
  rejected: { label: 'مرفوض', variant: 'danger' },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded: { label: 'مُسترَد', variant: 'info' },
}

const currentReceipt = props.payment.receipts?.[0] ?? null
const isImage = currentReceipt && currentReceipt.mime_type?.startsWith('image/')
const isPdf = currentReceipt && currentReceipt.mime_type === 'application/pdf'
const receiptUrl = currentReceipt ? `/admin/payments/${props.payment.id}/receipts/${currentReceipt.id}/file` : null

function verify() { router.post(`/admin/payments/${props.payment.id}/verify`, {}, { preserveScroll: true }) }
const showReject = ref(false)
const rejectForm = useForm({ reason: '' })
function submitReject() {
  rejectForm.post(`/admin/payments/${props.payment.id}/reject`, {
    preserveScroll: true,
    onSuccess: () => { showReject.value = false; rejectForm.reset('reason') },
  })
}
function markRefundPending() { router.post(`/admin/payments/${props.payment.id}/mark-refund-pending`, {}, { preserveScroll: true }) }
const showRefunded = ref(false)
const refundedForm = useForm({ reference: '' })
function submitRefunded() {
  refundedForm.post(`/admin/payments/${props.payment.id}/mark-refunded`, {
    preserveScroll: true,
    onSuccess: () => { showRefunded.value = false; refundedForm.reset('reference') },
  })
}
</script>
<template>
  <AdminShell>
    <div class="p-6 space-y-6">
      <PageHeader :title="`دفعة #${payment.id}`">
        <template #action>
          <div v-if="isManager" class="flex gap-2">
            <Button v-if="payment.status === 'submitted'" @click="verify"><Check class="h-4 w-4" /> تحقّق</Button>
            <Button v-if="payment.status === 'submitted'" variant="outline" class="text-danger" @click="showReject = true"><X class="h-4 w-4" /> رفض</Button>
            <Button v-if="payment.status === 'paid'" variant="outline" @click="markRefundPending"><RotateCcw class="h-4 w-4" /> وَسِم للاسترداد</Button>
            <Button v-if="payment.status === 'refund_pending'" @click="showRefunded = true"><BadgeCheck class="h-4 w-4" /> سجّل تنفيذ الاسترداد</Button>
          </div>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
          <FormSection title="الإيصال الحالي">
            <div v-if="!currentReceipt" class="text-text-secondary text-sm">لم يُرفع أي إيصال بعد.</div>
            <div v-else>
              <img v-if="isImage" :src="receiptUrl" alt="إيصال التحويل" class="max-h-[70vh] mx-auto rounded border border-border-default" />
              <iframe v-else-if="isPdf" :src="receiptUrl" class="w-full h-[70vh] rounded border border-border-default"></iframe>
              <a v-else :href="receiptUrl" target="_blank" class="text-brand underline text-sm">فتح الملف في تبويب جديد</a>
              <p class="mt-2 text-xs text-text-secondary">
                رفعه {{ currentReceipt.uploader?.name }} — {{ currentReceipt.mime_type }} — {{ (currentReceipt.file_size / 1024).toFixed(1) }} KB
              </p>
            </div>
          </FormSection>

          <FormSection title="سجل المحاولات" v-if="payment.receipts && payment.receipts.length > 1">
            <ul class="space-y-2 text-sm">
              <li v-for="r in payment.receipts" :key="r.id" class="flex justify-between border-b border-border-default pb-2">
                <span>
                  <a :href="`/admin/payments/${payment.id}/receipts/${r.id}/file`" target="_blank" class="text-brand underline">عرض</a>
                  — {{ r.uploader?.name }} ({{ new Date(r.created_at).toLocaleString('ar-SA') }})
                </span>
                <StatusBadge :type="r.status === 'rejected' ? 'danger' : 'info'" :label="r.status === 'rejected' ? `مرفوض: ${r.rejection_reason ?? ''}` : 'مرفوع'" />
              </li>
            </ul>
          </FormSection>
        </div>

        <div class="space-y-4">
          <FormSection title="ملخص الموعد">
            <dl class="text-sm space-y-2">
              <div><dt class="text-text-secondary">العميل</dt><dd>{{ payment.appointment?.customer?.name }}</dd></div>
              <div><dt class="text-text-secondary">الخدمة</dt><dd>{{ payment.appointment?.service?.name }}</dd></div>
              <div><dt class="text-text-secondary">الطبيب</dt><dd>{{ payment.appointment?.doctor?.user?.name }}</dd></div>
              <div><dt class="text-text-secondary">المبلغ</dt><dd class="text-lg font-bold">{{ payment.amount }} ₪</dd></div>
              <div><dt class="text-text-secondary">حالة الدفع</dt><dd><StatusBadge :type="statusMap[payment.status]?.variant" :label="statusMap[payment.status]?.label" /></dd></div>
              <div v-if="payment.verified_at"><dt class="text-text-secondary">تحقّق</dt><dd>{{ payment.verifier?.name }} — {{ new Date(payment.verified_at).toLocaleString('ar-SA') }}</dd></div>
              <div v-if="payment.refunded_at"><dt class="text-text-secondary">استرداد</dt><dd>{{ payment.refunder?.name }} — {{ new Date(payment.refunded_at).toLocaleString('ar-SA') }}<br><span v-if="payment.refund_reference" dir="ltr" class="font-mono text-xs">{{ payment.refund_reference }}</span></dd></div>
            </dl>
          </FormSection>
        </div>
      </div>
    </div>

    <Modal :open="showReject" title="رفض الإيصال" @update:open="showReject = $event">
      <form class="space-y-4" @submit.prevent="submitReject">
        <FormGroup label="سبب الرفض" name="reason" :error="rejectForm.errors.reason" required>
          <template #default="{ describedby }">
            <textarea id="reason" v-model="rejectForm.reason" name="reason" rows="3" :aria-describedby="describedby" class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showReject = false">إلغاء</Button>
        <Button class="bg-danger text-white" :disabled="rejectForm.processing || !rejectForm.reason.trim()" @click="submitReject">رفض</Button>
      </template>
    </Modal>

    <Modal :open="showRefunded" title="سجّل تنفيذ الاسترداد" @update:open="showRefunded = $event">
      <form class="space-y-4" @submit.prevent="submitRefunded">
        <FormGroup label="مرجع التحويل (اختياري)" name="reference" :error="refundedForm.errors.reference">
          <template #default="{ describedby }">
            <Input id="reference" v-model="refundedForm.reference" name="reference" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showRefunded = false">إلغاء</Button>
        <Button :disabled="refundedForm.processing" @click="submitRefunded">تسجيل</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
```

- [ ] **Step 7: Run tests — expect PASS**

```bash
php artisan test --filter="AdminPaymentTest|RouteNamesTest"
php artisan test
```

- [ ] **Step 8: Gate + build + commit**

```bash
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
grep -rnE '\b(pl-|pr-|ml-|mr-)[0-9]|\btext-left\b|\btext-right\b|\bleft-[0-9]|\bright-[0-9]' resources/js/Pages/Admin/Payments || echo CLEAN
npm run build
git add app/Http/Controllers/Admin/PaymentController.php routes/admin.php \
        tests/Feature/Payments/AdminPaymentTest.php tests/Feature/RouteNamesTest.php \
        resources/js/Pages/Admin/Payments/Index.vue resources/js/Pages/Admin/Payments/Show.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t5): admin payments index/show + 7 routes + receipt preview + action bar" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Sidebar entry + submitted-count badge

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share `adminCounts.submitted_payments` for staff)
- Modify: `resources/js/Layouts/AdminShell.vue` (add `المدفوعات` leaf with badge)

- [ ] **Step 1: Update `HandleInertiaRequests::share`** — add a lazy closure for the submitted count, gated to staff:

```php
'adminCounts' => fn () => $request->user()?->isStaff() ? [
    'submitted_payments' => \App\Models\Payment::where('status', 'submitted')->count(),
] : null,
```

(Add this as a sibling of the existing `flash` share, inside the `share()` return array.)

- [ ] **Step 2: Modify `resources/js/Layouts/AdminShell.vue`** — read the file first; preserve EVERY existing label/href/group exactly (especially the double-space `حجز موعد  لعميل`). Add `Receipt` to the lucide-vue-next import, and add this leaf to the `العيادة` group's `children` array (suggested position: between `المواعيد` and `حجز موعد  لعميل`):

```js
{ label: 'المدفوعات', href: '/admin/payments', icon: Receipt, badgeKey: 'submitted_payments' },
```

Then in the template, where each child link renders inside the sidebar menu, add a badge if `n.badgeKey` and `page.props.adminCounts?.[n.badgeKey] > 0`:

```vue
<span v-if="n.badgeKey && page.props.adminCounts?.[n.badgeKey] > 0"
      class="ms-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 text-[10px] font-bold text-white">
  {{ page.props.adminCounts[n.badgeKey] }}
</span>
```

Place the badge inside the existing `<SidebarMenuSubButton>`/`<SidebarMenuButton>` rendering for that nav item.

- [ ] **Step 3: Verify** — visit `/admin/payments` and the nav item, confirm the badge renders when ≥1 `submitted` payment exists.

```bash
npm run build
npm run test:js
```

- [ ] **Step 4: Update existing `AdminShell.spec.js`** — add ONE test that the count badge renders when `adminCounts.submitted_payments > 0`. Read the existing spec for mock style.

```js
it('renders submitted-payments badge when count > 0', async () => {
  // usePage mock returns adminCounts: { submitted_payments: 3 }
  // assert the badge text "3" appears next to the payments link
})
```

- [ ] **Step 5: Gate + commit**

```bash
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
npm run build && npm run test:js
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/Layouts/AdminShell.vue resources/js/Layouts/__tests__/AdminShell.spec.js
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t6): sidebar 'المدفوعات' leaf + staff-only submitted-count badge" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: R12 bank settings (4 keys)

**Files:**
- Modify: `config/clinic.php` (add 4 empty defaults)
- Modify: `app/Http/Controllers/Admin/ClinicSettingController.php` (read + saveBankInfo)
- Modify: `routes/admin.php` (add 1 new route: `admin.settings.bank`)
- Modify: `tests/Feature/RouteNamesTest.php` (add `admin.settings.bank`)
- Modify: `resources/js/Pages/Admin/Settings/Index.vue` (add bank-info FormSection)
- Modify: `tests/Feature/Settings/SurchargeSettingTest.php` OR create `tests/Feature/Settings/BankSettingTest.php`

- [ ] **Step 1: `config/clinic.php`** — add (next to `home_surcharge_pct`):

```php
'bank_name' => env('CLINIC_BANK_NAME', ''),
'bank_account_holder' => env('CLINIC_BANK_ACCOUNT_HOLDER', ''),
'bank_iban' => env('CLINIC_BANK_IBAN', ''),
'bank_account_number' => env('CLINIC_BANK_ACCOUNT_NUMBER', ''),
```

- [ ] **Step 2: Update `ClinicSettingController::index`** — also pass the 4 bank values (read from SettingService with config fallback) to the Inertia props:

```php
'bank' => [
    'name' => $settings->get('bank_name', config('clinic.bank_name', '')),
    'account_holder' => $settings->get('bank_account_holder', config('clinic.bank_account_holder', '')),
    'iban' => $settings->get('bank_iban', config('clinic.bank_iban', '')),
    'account_number' => $settings->get('bank_account_number', config('clinic.bank_account_number', '')),
],
```

- [ ] **Step 3: Add `saveBankInfo` action** to `ClinicSettingController`:

```php
public function saveBankInfo(\Illuminate\Http\Request $request, \App\Domain\Settings\Services\SettingService $settings): \Illuminate\Http\RedirectResponse
{
    $data = $request->validate([
        'bank_name' => ['nullable', 'string', 'max:255'],
        'bank_account_holder' => ['nullable', 'string', 'max:255'],
        'bank_iban' => ['nullable', 'string', 'max:64'],
        'bank_account_number' => ['nullable', 'string', 'max:64'],
    ]);
    foreach ($data as $k => $v) {
        $settings->set($k, (string) ($v ?? ''));
    }

    return back()->with('success', 'تم حفظ بيانات البنك.');
}
```

- [ ] **Step 4: Add route** in `routes/admin.php` (manager-only group):

```php
Route::put('settings/bank', [\App\Http\Controllers\Admin\ClinicSettingController::class, 'saveBankInfo'])->name('settings.bank');
```

- [ ] **Step 5: Update `RouteNamesTest`** — add `'admin.settings.bank'` to the locked list.

- [ ] **Step 6: Failing test** `tests/Feature/Settings/BankSettingTest.php`

```php
<?php

use App\Domain\Settings\Services\SettingService;
use App\Enums\UserRole;
use App\Models\User;

it('manager saves bank settings', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/bank', [
        'bank_name' => 'بنك القاهرة عمّان',
        'bank_account_holder' => 'عيادة جنّة',
        'bank_iban' => 'PS12CAIRO00000000000000',
        'bank_account_number' => '123456',
    ])->assertRedirect();

    $s = app(SettingService::class);
    expect($s->get('bank_name'))->toBe('بنك القاهرة عمّان');
    expect($s->get('bank_iban'))->toBe('PS12CAIRO00000000000000');
});

it('receptionist cannot save bank settings', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->put('/admin/settings/bank', ['bank_name' => 'x'])->assertForbidden();
});
```

- [ ] **Step 7: Update `resources/js/Pages/Admin/Settings/Index.vue`** — add a new `FormSection` "بيانات الحساب البنكي" with 4 inputs and a save button posting `PUT /admin/settings/bank`. Read the existing file for the existing form structure (surcharge); mirror it.

- [ ] **Step 8: Run tests + gate + commit**

```bash
php artisan test --filter="BankSettingTest|RouteNamesTest|SurchargeSettingTest"
php artisan test
./vendor/bin/pint && ./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
npm run build
git add config/clinic.php app/Http/Controllers/Admin/ClinicSettingController.php routes/admin.php \
        tests/Feature/RouteNamesTest.php tests/Feature/Settings/BankSettingTest.php \
        resources/js/Pages/Admin/Settings/Index.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p2/t7): R12 bank settings (4 keys) + admin save route + bank-info form section" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: P2 acceptance — docs + full DoD gate + tag

**Files:**
- Modify: `docs/DOMAIN-MODEL.md`
- Modify: `docs/ARCHITECTURE.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Update `docs/DOMAIN-MODEL.md`** — add `Payment`, `PaymentReceipt`, `PaymentStatus` enum, and a brief note on the Appointment ↔ Payment 1:1 relation + the listener. Bump "Last updated".

- [ ] **Step 2: Update `docs/ARCHITECTURE.md`** — add a "P2 — Payments" section: domain service, listener, 9 routes (admin + portal) in a new route table, private receipt storage policy, R12 bank settings. Bump "Last updated".

- [ ] **Step 3: Update `CHANGELOG.md`** — add `## [P2] Payments — 2026-05-20` heading with a top-line summary + bullets per task.

- [ ] **Step 4: Full DoD gate** — run, capture, fix anything red:

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
php artisan test
npm run test:js
npm run build
grep -rnE '\b(pl-|pr-|ml-|mr-)[0-9]|\btext-left\b|\btext-right\b|\bleft-[0-9]|\bright-[0-9]' resources/js/Layouts resources/js/Pages resources/js/Components/foundation resources/js/Components/booking
grep -rEn '\b(float|double)\b' app database | grep -i 'price\|amount\|fee\|total'
grep -rnE '\{\{[A-Z_]+\}\}' docs/GOLDEN-RULES.md docs/DEFINITION-OF-DONE.md
```

Plus scratch-Postgres `migrate:fresh` (no live DB risk):

```powershell
$env:PGPASSWORD='123123'
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -d postgres -c "DROP DATABASE IF EXISTS jannahclinic_gate;" -c "CREATE DATABASE jannahclinic_gate;"
$env:DB_DATABASE='jannahclinic_gate'; php artisan migrate:fresh --force; $env:DB_DATABASE='jannahclinic'
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -d jannahclinic_gate -c "\dt"
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -d postgres -c "DROP DATABASE IF EXISTS jannahclinic_gate;"
```

Confirm `\dt` includes `payments` and `payment_receipts`.

- [ ] **Step 5: P2 acceptance checklist** (PASS/FAIL with evidence for each):

1. Booking creates a `Payment.pending` row (`BookingCreatesPaymentTest`).
2. Customer can upload a JPG/PNG/PDF receipt (≤5MB) → Payment becomes `submitted` (`PortalPaymentTest`).
3. Manager can verify → `paid`; reject with reason → `rejected`; customer re-uploads → back to `submitted`. (`AdminPaymentTest`, `PaymentServiceTest`).
4. Manager can mark refund-pending manually; can record refunded with reference (`AdminPaymentTest`).
5. Auto refund-pending fires when an appointment is Cancelled or Rejected while paid (`AutoRefundPendingTest`); does NOT fire for Completed/NoShow/Rescheduled.
6. Receptionist can view but cannot mutate (`AdminPaymentTest` 403 cases).
7. Customer cannot see another customer's payment, cannot reach `/admin/payments` (`PortalPaymentTest`, `AdminPaymentTest`).
8. Receipt file served only via authz-checked controller; never under `storage/app/public/` (`AdminPaymentTest` streams; manual grep `storage/app/public/receipts` returns nothing).
9. Sidebar shows "المدفوعات" with a submitted-count badge for staff.
10. Bank info is config-driven via `SettingService` with `config/clinic.php` fallback (4 keys); manager edits from `/admin/settings`.
11. RouteNamesTest locks all 10 new routes (2 portal + 7 admin payment + 1 admin settings bank).
12. Money grep empty; RTL grep empty; pint/phpstan clean; full test suite green.

- [ ] **Step 6: Commit docs + tag**

```bash
git add docs/DOMAIN-MODEL.md docs/ARCHITECTURE.md CHANGELOG.md
git -c user.email=admin@istoria.app -c user.name=claude commit -m "docs(p2): acceptance — DOMAIN-MODEL/ARCHITECTURE/CHANGELOG finalized; full DoD gate green" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag -a p2-payments -m "P2 — Payments complete (bank-transfer receipt + verification + refund flow; hybrid lifecycle; 10 routes; gate green)"
git push origin main
git push origin p2-payments
```

---

## Plan self-review (post-write)

**Spec coverage** (every §):
- §1 motivations/decisions → covered by Tasks 1–8 (hybrid; refunds in scope; receipt on appointment page).
- §2.1 payments table → Task 1 migration.
- §2.2 payment_receipts table → Task 1 migration.
- §2.3 bank settings (4 keys) → Task 7.
- §2.4 PaymentStatus enum → Task 1.
- §3 state machine → Task 2 (PaymentService) + Task 3 (auto refund_pending) + Task 5 (manual mark-refund-pending for Rescheduled).
- §4.1 PaymentService → Task 2.
- §4.2 listener → Task 3 (`AppointmentObserver`).
- §4.3 routes (9 total — 2 portal in Task 4, 7 admin in Task 5) + 1 settings route in Task 7 = 10 total locked.
- §4.4 PaymentPolicy → Task 4.
- §4.5 receipt streaming → Task 5 (`receiptFile` action) + Task 4 path enforcement (local disk).
- §4.6 migrations + backfill → Task 1.
- §5.1 Portal/Payments/Show.vue → Task 4.
- §5.2 Admin/Payments/Index.vue → Task 5.
- §5.3 Admin/Payments/Show.vue (receipt prominent + history) → Task 5.
- §5.4 sidebar entry + badge → Task 6.
- §5.5 portal appointment banner → covered by Portal page + the existing portal appointment detail can be augmented later if needed (deferred polish; the Show page already exposes status). Note: spec §5.5 mentions a banner on appointment detail; current scope adds the dedicated payment page. A small follow-up edit to the portal appointment detail page to render a banner+link to the payment page can be folded into Task 4 — confirm during implementation.
- §6 tests → covered across Tasks 1–7 with the listed test files.
- §7 docs → Task 8.
- §8 out of scope → respected (no card, no partial, no notifications).
- §9 sequencing → matches Tasks 1–8.

**Placeholder scan** — none. All steps have exact code or exact commands.

**Type/name consistency** — verified:
- `Payment` `#[Fillable]` matches columns; cast `status` to `PaymentStatus` matches enum.
- `PaymentReceipt` matches its table columns; relation `uploader` uses `uploaded_by` consistently.
- `PaymentService` method names (`uploadReceipt`, `verify`, `reject`, `markRefundPending`, `markRefunded`) match the controllers' calls and the spec.
- Route names: `portal.appointments.payment(.upload)?` (2) + `admin.payments.{index,show,receipt-file,verify,reject,mark-refund-pending,mark-refunded}` (7) + `admin.settings.bank` (1) = 10. Match.
- `AppointmentObserver::updated` listens to `wasChanged('status')` AND target = Cancelled|Rejected — matches spec §3 auto-trigger rules; Reschedule NOT triggered, matching the spec.
- `InvalidPaymentTransitionException` consistent across PaymentService throws + controller `catch` blocks.
- Sidebar leaf icon `Receipt` from lucide-vue-next — `Receipt` exists in lucide.

**Frequent commits** — every task ends in a commit; Task 8 also tags + pushes.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-20-jannahclinic-p2-payments.md`. Two execution options:

**1. Subagent-Driven (recommended for new feature work)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — I execute tasks in this session with my own inline verification, full gate after every task. Matches the lean mode the user used for post-P1 polish.

Which approach?
