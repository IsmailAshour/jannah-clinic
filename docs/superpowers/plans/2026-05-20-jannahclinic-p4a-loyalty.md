# jannahclinic P4a — Loyalty Points — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the in-app loyalty-points system: customers earn `floor(amount)` points per shekel paid (on appointments whose service has `loyalty_enabled=true`), redeem points for booking redeemable services at a per-service points cost, with manager-adjustable balances and full audit through an append-only `loyalty_ledger`.

**Architecture:** A single `LoyaltyService` mirrors the existing `AuditLogger` / `NotificationService` pattern — explicit, transactional writes to `loyalty_ledger` plus a same-transaction update of the denormalized `customer_profiles.loyalty_balance` cache. The service is invoked AFTER `DB::transaction` returns in caller services (`PaymentService::verify` / `markRefunded`, `AppointmentTransitionService::transition`) per the P5a lesson that a side-channel failure must not roll back a domain change. The exception is `BookingService::book` for `payment_method=loyalty_points`, where deduction MUST happen inside the booking transaction (the ledger entry replaces the missing `Payment` row as the proof of payment). `LoyaltyChanged` notifications dispatch via `NotificationService::dispatch` (try/catch + log).

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL prod / SQLite tests · Pest · Larastan L5 · Pint · Inertia.js · Vue 3 · shadcn-vue · Tailwind v4 · Vitest (jsdom).

**Spec:** `docs/superpowers/specs/2026-05-20-jannahclinic-p4a-loyalty-design.md`

**Execution mode:** Lean — no per-task review-subagent ceremony. After each task: inline verification + full gate (Pest + Vitest + Pint + PHPStan + Vite) + commit + push.

**Commit convention (verbatim per project standard):**
```
git -c user.email=admin@istoria.app -c user.name=claude commit -m "<subject>" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## File Structure

**New backend files**
- `app/Enums/LoyaltyReason.php` — `enum: string` with 5 cases
- `app/Models/LoyaltyLedger.php` — append-only model (throw on update/delete)
- `app/Domain/Loyalty/Services/LoyaltyService.php` — sole entry point: award/clawback/redeem/reverse/adjust/balance
- `app/Domain/Loyalty/Exceptions/InsufficientLoyaltyBalanceException.php`
- `app/Notifications/LoyaltyChanged.php` — `database` channel only, mirrors `PaymentChanged`
- `app/Policies/LoyaltyLedgerPolicy.php` — `view()` rule
- `app/Http/Controllers/Admin/CustomerLoyaltyController.php` — `show()`, `adjust()`
- `app/Http/Controllers/Portal/LoyaltyController.php` — `index()`
- `app/Console/Commands/RebuildLoyaltyBalances.php` — `loyalty:rebuild-balances`
- `database/migrations/2026_05_20_160000_create_loyalty_ledger_and_columns.php`

**New frontend files**
- `resources/js/Pages/Admin/Customers/Loyalty.vue` — full paginated ledger page (links from customer detail "المزيد")
- `resources/js/Pages/Portal/Loyalty/Index.vue`
- `resources/js/Components/foundation/LoyaltyAdjustModal.vue` — reusable manager modal

**New tests**
- `tests/Unit/Loyalty/LoyaltyServiceTest.php`
- `tests/Unit/Loyalty/LoyaltyLedgerAppendOnlyTest.php`
- `tests/Feature/Loyalty/EarnFlowTest.php`
- `tests/Feature/Loyalty/RedeemFlowTest.php`
- `tests/Feature/Loyalty/RefundFlowTest.php`
- `tests/Feature/Loyalty/CancellationFlowTest.php`
- `tests/Feature/Loyalty/AdjustFlowTest.php`
- `tests/Feature/Loyalty/AuthorizationTest.php`
- `tests/Feature/Loyalty/ServiceConfigTest.php`
- `resources/js/Pages/Portal/Loyalty/__tests__/Index.spec.js`
- `resources/js/Pages/Portal/Booking/__tests__/PaymentMethodPicker.spec.js`

**Modified files**
- `app/Enums/NotificationCategory.php` — add `Loyalty` case
- `app/Models/Service.php` — `#[Fillable]` + casts
- `app/Models/Appointment.php` — `#[Fillable]` + casts + @property
- `app/Models/CustomerProfile.php` — `#[Fillable]` + @property
- `app/Domain/Booking/Data/BookingData.php` — `paymentMethod` field
- `app/Domain/Booking/Services/BookingService.php` — branch on `paymentMethod`
- `app/Domain/Booking/Services/AppointmentTransitionService.php` — reverse redemption hook
- `app/Domain/Payment/Services/PaymentService.php` — award + clawback hooks
- `app/Domain/Notification/Services/NotificationService.php` — 4 new generators
- `app/Http/Controllers/Admin/ServiceController.php` — extend validation
- `app/Http/Controllers/Admin/CustomerController.php` — embed loyalty preview in `show()`
- `app/Http/Controllers/Portal/BookingController.php` — accept `payment_method`
- `app/Http/Controllers/Admin/BookingController.php` — accept `payment_method`
- `app/Providers/AppServiceProvider.php` — register `LoyaltyLedgerPolicy`
- `routes/admin.php` — 2 new routes
- `routes/portal.php` — 1 new route
- `tests/Feature/RouteNamesTest.php` — lock 3 new route names
- `resources/js/Pages/Admin/Catalog/Services.vue` — extend edit modal
- `resources/js/Pages/Admin/Customers/Show.vue` — loyalty section
- `resources/js/Pages/Portal/Services/Index.vue` — redemption badges (file may need creation; check)
- `resources/js/Pages/Portal/Booking/BookingWizard.vue` — payment method picker
- `resources/js/Layouts/ClientShell.vue` — 6th tab
- `docs/ARCHITECTURE.md` — Loyalty section
- `CHANGELOG.md` — Unreleased entry

---

## Task 1: Migration + enum + LoyaltyLedger model + LoyaltyService skeleton + unit tests

**Files:**
- Create: `database/migrations/2026_05_20_160000_create_loyalty_ledger_and_columns.php`
- Create: `app/Enums/LoyaltyReason.php`
- Create: `app/Models/LoyaltyLedger.php`
- Create: `app/Domain/Loyalty/Exceptions/InsufficientLoyaltyBalanceException.php`
- Create: `app/Domain/Loyalty/Services/LoyaltyService.php`
- Modify: `app/Models/Service.php` (`#[Fillable]` + casts)
- Modify: `app/Models/Appointment.php` (`#[Fillable]` + casts + @property)
- Modify: `app/Models/CustomerProfile.php` (`#[Fillable]`)
- Create: `tests/Unit/Loyalty/LoyaltyServiceTest.php`
- Create: `tests/Unit/Loyalty/LoyaltyLedgerAppendOnlyTest.php`

- [ ] **Step 1.1: Write the unit tests (will fail)**

Create `tests/Unit/Loyalty/LoyaltyLedgerAppendOnlyTest.php`:

```php
<?php

use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\LoyaltyLedger;
use App\Models\User;

it('throws when trying to update an existing ledger row', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $entry = LoyaltyLedger::create([
        'customer_id' => $u->id,
        'points_delta' => 100,
        'balance_after' => 100,
        'reason' => LoyaltyReason::EarnedFromPayment->value,
    ]);

    expect(fn () => $entry->update(['points_delta' => 200]))
        ->toThrow(LogicException::class);
});

it('throws when trying to delete a ledger row', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $entry = LoyaltyLedger::create([
        'customer_id' => $u->id,
        'points_delta' => 100,
        'balance_after' => 100,
        'reason' => LoyaltyReason::EarnedFromPayment->value,
    ]);

    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});
```

Create `tests/Unit/Loyalty/LoyaltyServiceTest.php`:

```php
<?php

use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function mkLoyaltyFixtures(): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create([
        'category_id' => $cat->id, 'name' => 's',
        'base_price' => '100.00', 'duration_minutes' => 30, 'home_service_enabled' => false,
        'loyalty_enabled' => true, 'loyalty_redemption_points' => 500,
    ]);
    $doctor->services()->attach($service->id);
    $appt = Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
        'status' => AppointmentStatus::Completed, 'price_at_booking' => '100.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);
    $payment = Payment::create([
        'appointment_id' => $appt->id, 'amount' => '100.00', 'status' => PaymentStatus::Paid,
    ]);

    return compact('customer', 'doctorUser', 'doctor', 'service', 'appt', 'payment');
}

beforeEach(function () {
    $this->service = app(LoyaltyService::class);
});

it('awardForPayment creates ledger entry and updates cached balance', function () {
    $f = mkLoyaltyFixtures();

    $this->service->awardForPayment($f['payment']);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(100)
        ->and($entry->balance_after)->toBe(100)
        ->and($entry->reason)->toBe(LoyaltyReason::EarnedFromPayment->value)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
});

it('awardForPayment is idempotent on the same payment', function () {
    $f = mkLoyaltyFixtures();

    $this->service->awardForPayment($f['payment']);
    $this->service->awardForPayment($f['payment']);

    expect(LoyaltyLedger::where('reason', LoyaltyReason::EarnedFromPayment->value)
        ->where('reference_id', $f['payment']->id)->count())->toBe(1);
});

it('clawbackForRefund creates negative entry mirroring the earned amount', function () {
    $f = mkLoyaltyFixtures();
    $this->service->awardForPayment($f['payment']);

    $this->service->clawbackForRefund($f['payment']);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(-100)
        ->and($entry->balance_after)->toBe(0)
        ->and($entry->reason)->toBe(LoyaltyReason::ClawbackFromRefund->value);
});

it('redeemForAppointment throws when balance is insufficient', function () {
    $f = mkLoyaltyFixtures();

    expect(fn () => $this->service->redeemForAppointment($f['appt'], $f['customer']))
        ->toThrow(InsufficientLoyaltyBalanceException::class);
});

it('redeemForAppointment deducts cost and writes ledger', function () {
    $f = mkLoyaltyFixtures();
    $f['customer']->customerProfile->update(['loyalty_balance' => 600]);

    $deducted = $this->service->redeemForAppointment($f['appt'], $f['customer']);

    expect($deducted)->toBe(500)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(-500)
        ->and($entry->reason)->toBe(LoyaltyReason::RedeemedForAppointment->value);
});

it('reverseRedemption returns the exact points_spent', function () {
    $f = mkLoyaltyFixtures();
    $f['appt']->update(['payment_method' => 'loyalty_points', 'loyalty_points_spent' => 500]);
    $f['customer']->customerProfile->update(['loyalty_balance' => 0]);

    $this->service->reverseRedemption($f['appt']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);
    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->reason)->toBe(LoyaltyReason::RefundReversal->value);
});

it('adjust by manager writes ledger with actor and notes', function () {
    $f = mkLoyaltyFixtures();
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->service->adjust($f['customer'], 50, 'هدية ترحيب', $manager);

    $entry = LoyaltyLedger::where('customer_id', $f['customer']->id)->latest('id')->first();
    expect($entry->points_delta)->toBe(50)
        ->and($entry->reason)->toBe(LoyaltyReason::AdjustmentByManager->value)
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->notes)->toBe('هدية ترحيب');
});

it('rolls back ledger and balance when the surrounding transaction rolls back', function () {
    $f = mkLoyaltyFixtures();

    try {
        DB::transaction(function () use ($f) {
            $this->service->awardForPayment($f['payment']);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(LoyaltyLedger::count())->toBe(0)
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
});
```

- [ ] **Step 1.2: Run the tests — confirm they fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Unit/Loyalty/`
Expected: FAIL — `Class "App\Domain\Loyalty\Services\LoyaltyService" not found` (or similar).

- [ ] **Step 1.3: Create the migration**

Create `database/migrations/2026_05_20_160000_create_loyalty_ledger_and_columns.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->integer('points_delta');
            $table->integer('balance_after');
            $table->string('reason', 32);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
            $table->index(['reason', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE loyalty_ledger ADD CONSTRAINT loyalty_ledger_reason_check
                CHECK (reason IN ('earned_from_payment','redeemed_for_appointment','clawback_from_refund','refund_reversal','adjustment_by_manager'))");
        }

        Schema::table('services', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(true);
            $table->unsignedInteger('loyalty_redemption_points')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_loyalty_points_positive
                CHECK (loyalty_redemption_points IS NULL OR loyalty_redemption_points > 0)');
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->integer('loyalty_balance')->default(0);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_method', 16)->default('cash');
            $table->unsignedInteger('loyalty_points_spent')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_payment_method_check
                CHECK (payment_method IN ('cash','loyalty_points'))");
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_loyalty_points_consistency
                CHECK ((payment_method = \'loyalty_points\') = (loyalty_points_spent IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'loyalty_points_spent']);
        });
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('loyalty_balance');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['loyalty_enabled', 'loyalty_redemption_points']);
        });
        Schema::dropIfExists('loyalty_ledger');
    }
};
```

- [ ] **Step 1.4: Create the enum**

Create `app/Enums/LoyaltyReason.php`:

```php
<?php

namespace App\Enums;

enum LoyaltyReason: string
{
    case EarnedFromPayment = 'earned_from_payment';
    case RedeemedForAppointment = 'redeemed_for_appointment';
    case ClawbackFromRefund = 'clawback_from_refund';
    case RefundReversal = 'refund_reversal';
    case AdjustmentByManager = 'adjustment_by_manager';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
```

- [ ] **Step 1.5: Create the LoyaltyLedger model (append-only)**

Create `app/Models/LoyaltyLedger.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $points_delta
 * @property int $balance_after
 * @property string $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $notes
 * @property int|null $actor_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property User $customer
 * @property User|null $actor
 */
#[Fillable(['customer_id', 'points_delta', 'balance_after', 'reason', 'reference_type', 'reference_id', 'notes', 'actor_id'])]
class LoyaltyLedger extends Model
{
    protected $table = 'loyalty_ledger';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'immutable_datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('LoyaltyLedger is append-only — entries cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new \LogicException('LoyaltyLedger is append-only — entries cannot be deleted.');
    }
}
```

- [ ] **Step 1.6: Create the exception**

Create `app/Domain/Loyalty/Exceptions/InsufficientLoyaltyBalanceException.php`:

```php
<?php

namespace App\Domain\Loyalty\Exceptions;

class InsufficientLoyaltyBalanceException extends \DomainException {}
```

- [ ] **Step 1.7: Create LoyaltyService**

Create `app/Domain/Loyalty/Services/LoyaltyService.php`:

```php
<?php

namespace App\Domain\Loyalty\Services;

use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function awardForPayment(Payment $payment): void
    {
        $exists = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::EarnedFromPayment->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();
        if ($exists) {
            return;
        }
        $points = (int) floor((float) $payment->amount);
        if ($points <= 0) {
            return;
        }
        $customer = $payment->appointment->customer;
        $this->writeEntry($customer, $points, LoyaltyReason::EarnedFromPayment, $payment);
    }

    public function clawbackForRefund(Payment $payment): void
    {
        $earned = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::EarnedFromPayment->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->value('points_delta');
        if ($earned === null) {
            return;
        }
        $alreadyClawed = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::ClawbackFromRefund->value)
            ->where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();
        if ($alreadyClawed) {
            return;
        }
        $customer = $payment->appointment->customer;
        $this->writeEntry($customer, -$earned, LoyaltyReason::ClawbackFromRefund, $payment);
    }

    public function redeemForAppointment(Appointment $appointment, User $customer): int
    {
        $service = $appointment->service;
        if (! $service->loyalty_enabled || $service->loyalty_redemption_points === null || $service->loyalty_redemption_points <= 0) {
            throw new InsufficientLoyaltyBalanceException('الخدمة غير متاحة للاستبدال بالنقاط.');
        }
        $cost = (int) $service->loyalty_redemption_points;
        $balance = $this->balance($customer);
        if ($balance < $cost) {
            throw new InsufficientLoyaltyBalanceException("رصيد النقاط غير كافٍ (المطلوب {$cost}، المتاح {$balance}).");
        }
        $this->writeEntry($customer, -$cost, LoyaltyReason::RedeemedForAppointment, $appointment);

        return $cost;
    }

    public function reverseRedemption(Appointment $cancelled): void
    {
        if ($cancelled->payment_method !== 'loyalty_points' || $cancelled->loyalty_points_spent === null) {
            return;
        }
        $alreadyReversed = LoyaltyLedger::query()
            ->where('reason', LoyaltyReason::RefundReversal->value)
            ->where('reference_type', Appointment::class)
            ->where('reference_id', $cancelled->id)
            ->exists();
        if ($alreadyReversed) {
            return;
        }
        $customer = $cancelled->customer;
        $this->writeEntry($customer, (int) $cancelled->loyalty_points_spent, LoyaltyReason::RefundReversal, $cancelled);
    }

    public function adjust(User $customer, int $delta, string $note, User $manager): void
    {
        if ($manager->role !== UserRole::Manager) {
            throw new AuthorizationException('فقط المدير يستطيع تعديل النقاط.');
        }
        if ($delta === 0) {
            return;
        }
        $this->writeEntry($customer, $delta, LoyaltyReason::AdjustmentByManager, null, $note, $manager);
    }

    public function balance(User $customer): int
    {
        $profile = $customer->customerProfile;
        if ($profile === null) {
            $profile = CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
        }

        return (int) $profile->loyalty_balance;
    }

    private function writeEntry(
        User $customer,
        int $delta,
        LoyaltyReason $reason,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        ?string $notes = null,
        ?User $actor = null,
    ): void {
        DB::transaction(function () use ($customer, $delta, $reason, $reference, $notes, $actor) {
            $profile = $customer->customerProfile
                ?? CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
            $newBalance = (int) $profile->loyalty_balance + $delta;
            LoyaltyLedger::create([
                'customer_id' => $customer->id,
                'points_delta' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason->value,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'actor_id' => $actor?->id,
            ]);
            $profile->update(['loyalty_balance' => $newBalance]);
        });
    }
}
```

- [ ] **Step 1.8: Extend Service model**

Edit `app/Models/Service.php`. Replace the `#[Fillable]` line and add casts:

```php
#[Fillable(['category_id', 'name', 'description', 'base_price', 'duration_minutes', 'home_service_enabled', 'icon_key', 'is_active', 'display_order', 'loyalty_enabled', 'loyalty_redemption_points'])]
class Service extends Model
{
    protected $casts = [
        'base_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'home_service_enabled' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'loyalty_enabled' => 'boolean',
        'loyalty_redemption_points' => 'integer',
    ];
```

- [ ] **Step 1.9: Extend Appointment model**

Edit `app/Models/Appointment.php`. Append the two columns to `#[Fillable]` and update the @property docblock to include them:

Find the `#[Fillable(...)]` attribute and append `'payment_method', 'loyalty_points_spent'` to the array. Add to `@property` block:
```
 * @property string $payment_method
 * @property int|null $loyalty_points_spent
```

- [ ] **Step 1.10: Extend CustomerProfile model**

Edit `app/Models/CustomerProfile.php`. Append `'loyalty_balance'` to the `#[Fillable]` array. Add to @property:
```
 * @property int $loyalty_balance
```

- [ ] **Step 1.11: Run migrations + tests**

Run:
```
cd /c/~projects/jannahclinic && php artisan migrate && php artisan test tests/Unit/Loyalty/
```
Expected: 9/9 PASS.

- [ ] **Step 1.12: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +9 (~290), Pint clean, PHPStan 0, Vite OK, Vitest 26.

- [ ] **Step 1.13: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add database/migrations/2026_05_20_160000_create_loyalty_ledger_and_columns.php \
        app/Enums/LoyaltyReason.php \
        app/Models/LoyaltyLedger.php \
        app/Models/Service.php \
        app/Models/Appointment.php \
        app/Models/CustomerProfile.php \
        app/Domain/Loyalty/ \
        tests/Unit/Loyalty/
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): ledger + LoyaltyService scaffolding (P4a/1)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 2: Routes + controllers + policy + auth tests

**Files:**
- Create: `app/Policies/LoyaltyLedgerPolicy.php`
- Create: `app/Http/Controllers/Admin/CustomerLoyaltyController.php`
- Create: `app/Http/Controllers/Portal/LoyaltyController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/admin.php`
- Modify: `routes/portal.php`
- Modify: `tests/Feature/RouteNamesTest.php`
- Create: `resources/js/Pages/Admin/Customers/Loyalty.vue` (stub)
- Create: `resources/js/Pages/Portal/Loyalty/Index.vue` (stub)
- Create: `tests/Feature/Loyalty/AuthorizationTest.php`

- [ ] **Step 2.1: Write the authorization test**

Create `tests/Feature/Loyalty/AuthorizationTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

beforeEach(function () {
    $this->customerA = User::factory()->create(['role' => UserRole::Customer]);
    $this->customerB = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $this->customerA->id]);
    CustomerProfile::create(['user_id' => $this->customerB->id]);
});

it('customer sees own loyalty page', function () {
    $this->actingAs($this->customerA)->get('/portal/loyalty')->assertOk();
});

it('customer cannot access admin loyalty pages', function () {
    $this->actingAs($this->customerA)
        ->get("/admin/customers/{$this->customerB->id}/loyalty")
        ->assertForbidden();
});

it('manager sees any customer loyalty admin page', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)
        ->get("/admin/customers/{$this->customerA->id}/loyalty")
        ->assertOk();
});

it('doctor and receptionist can read but not adjust', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $r = User::factory()->create(['role' => UserRole::Receptionist]);

    $this->actingAs($d)->get("/admin/customers/{$this->customerA->id}/loyalty")->assertOk();
    $this->actingAs($r)->get("/admin/customers/{$this->customerA->id}/loyalty")->assertOk();

    $this->actingAs($d)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 10, 'note' => 'x'])
        ->assertForbidden();
    $this->actingAs($r)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 10, 'note' => 'x'])
        ->assertForbidden();
});

it('manager can adjust', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($m)
        ->post("/admin/customers/{$this->customerA->id}/loyalty/adjust", ['delta' => 50, 'note' => 'هدية'])
        ->assertRedirect();

    expect($this->customerA->customerProfile->fresh()->loyalty_balance)->toBe(50);
});
```

- [ ] **Step 2.2: Run — confirm fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/AuthorizationTest.php`
Expected: FAIL — routes don't exist.

- [ ] **Step 2.3: Create the policy**

Create `app/Policies/LoyaltyLedgerPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LoyaltyLedger;
use App\Models\User;

class LoyaltyLedgerPolicy
{
    public function view(User $user, LoyaltyLedger $entry): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->role === UserRole::Customer && $entry->customer_id === $user->id;
    }
}
```

- [ ] **Step 2.4: Register the policy**

Edit `app/Providers/AppServiceProvider.php`. Inside the `boot()` method, locate the existing `Gate::policy(...)` calls (e.g. MedicalEntryPolicy) and add:

```php
Gate::policy(LoyaltyLedger::class, LoyaltyLedgerPolicy::class);
```

Add corresponding `use` statements:
```php
use App\Models\LoyaltyLedger;
use App\Policies\LoyaltyLedgerPolicy;
```

- [ ] **Step 2.5: Create admin controller**

Create `app/Http/Controllers/Admin/CustomerLoyaltyController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Loyalty\Services\LoyaltyService;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyLedger;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerLoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function show(Request $request, User $customer): Response
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $ledger = LoyaltyLedger::query()
            ->where('customer_id', $customer->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/Customers/Loyalty', [
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
            'balance' => $this->loyalty->balance($customer),
            'ledger' => $ledger,
        ]);
    }

    public function adjust(Request $request, User $customer): RedirectResponse
    {
        abort_unless($customer->role === UserRole::Customer, 404);
        abort_unless($request->user()->role === UserRole::Manager, 403);

        $v = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $this->loyalty->adjust($customer, (int) $v['delta'], $v['note'], $request->user());

        return back()->with('success', 'تم تعديل الرصيد.');
    }
}
```

- [ ] **Step 2.6: Create portal controller**

Create `app/Http/Controllers/Portal/LoyaltyController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Loyalty\Services\LoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = $request->input('tab', 'all');

        $query = LoyaltyLedger::query()->where('customer_id', $user->id);
        if ($tab === 'earn') {
            $query->where('points_delta', '>', 0);
        } elseif ($tab === 'redeem') {
            $query->where('points_delta', '<', 0);
        }
        $ledger = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $summary = [
            'earned' => LoyaltyLedger::query()->where('customer_id', $user->id)
                ->where('points_delta', '>', 0)->sum('points_delta'),
            'redeemed' => abs((int) LoyaltyLedger::query()->where('customer_id', $user->id)
                ->where('points_delta', '<', 0)->sum('points_delta')),
        ];

        return Inertia::render('Portal/Loyalty/Index', [
            'balance' => $this->loyalty->balance($user),
            'summary' => $summary,
            'ledger' => $ledger,
            'tab' => $tab,
        ]);
    }
}
```

- [ ] **Step 2.7: Register routes**

Edit `routes/admin.php`. Inside the outer `Route::middleware(['auth', 'role:manager,doctor,receptionist'])` group, alongside the existing readable-by-all-staff customer routes:

```php
        // P4a — Loyalty (any staff can read, manager only adjusts)
        Route::get('customers/{customer}/loyalty', [\App\Http\Controllers\Admin\CustomerLoyaltyController::class, 'show'])
            ->name('customers.loyalty.show');
```

Inside the `role:manager` inner group, add:

```php
            Route::post('customers/{customer}/loyalty/adjust', [\App\Http\Controllers\Admin\CustomerLoyaltyController::class, 'adjust'])
                ->name('customers.loyalty.adjust');
```

Edit `routes/portal.php`. Inside the existing portal group, before the closing `});`:

```php
        // P4a — Loyalty
        Route::get('loyalty', [\App\Http\Controllers\Portal\LoyaltyController::class, 'index'])
            ->name('loyalty.index');
```

- [ ] **Step 2.8: Lock route names**

Edit `tests/Feature/RouteNamesTest.php`. Append to the `$names` array (before the closing `]`):

```php
'admin.customers.loyalty.show',
'admin.customers.loyalty.adjust',
'portal.loyalty.index',
```

- [ ] **Step 2.9: Create stub Inertia pages**

Create `resources/js/Pages/Admin/Customers/Loyalty.vue`:

```vue
<script setup>
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  customer: { type: Object, required: true },
  balance: { type: Number, required: true },
  ledger: { type: Object, required: true },
})
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader :title="`نقاط ولاء — ${customer.name}`" :description="`الرصيد الحالي: ${balance}`" />
    </div>
  </AdminShell>
</template>
```

Create `resources/js/Pages/Portal/Loyalty/Index.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  balance: { type: Number, required: true },
  summary: { type: Object, required: true },
  ledger: { type: Object, required: true },
  tab: { type: String, required: true },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="نقاطي" :description="`الرصيد: ${balance}`" />
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 2.10: Run tests**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/AuthorizationTest.php tests/Feature/RouteNamesTest.php`
Expected: 6/6 PASS (5 auth + 1 route-names).

- [ ] **Step 2.11: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +5, Pint clean, PHPStan 0, Vite OK, Vitest 26.

- [ ] **Step 2.12: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Policies/LoyaltyLedgerPolicy.php \
        app/Http/Controllers/Admin/CustomerLoyaltyController.php \
        app/Http/Controllers/Portal/LoyaltyController.php \
        app/Providers/AppServiceProvider.php \
        routes/admin.php routes/portal.php \
        tests/Feature/RouteNamesTest.php tests/Feature/Loyalty/AuthorizationTest.php \
        resources/js/Pages/Admin/Customers/Loyalty.vue \
        resources/js/Pages/Portal/Loyalty/Index.vue
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): routes + controllers + policy + cross-role auth tests (P4a/2)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 3: PaymentService integration (award + clawback)

**Files:**
- Modify: `app/Domain/Payment/Services/PaymentService.php`
- Create: `tests/Feature/Loyalty/EarnFlowTest.php`
- Create: `tests/Feature/Loyalty/RefundFlowTest.php`

- [ ] **Step 3.1: Write earn + refund tests**

Create `tests/Feature/Loyalty/EarnFlowTest.php`:

```php
<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('verify awards points when service loyalty_enabled', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: true);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);

    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(100);
    expect(LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->where('reason', LoyaltyReason::EarnedFromPayment->value)
        ->count())->toBe(1);
});

it('verify does NOT award when service loyalty_enabled=false', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: false);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);

    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
    expect(LoyaltyLedger::count())->toBe(0);
});
```

Create `tests/Feature/Loyalty/RefundFlowTest.php`:

```php
<?php

use App\Domain\Payment\Services\PaymentService;
use App\Enums\LoyaltyReason;
use App\Models\LoyaltyLedger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('markRefunded claws back the points', function () {
    Storage::fake('local');
    $f = mkPaidPath(loyaltyEnabled: true);
    app(PaymentService::class)->uploadReceipt($f['payment'], UploadedFile::fake()->image('r.jpg'), $f['customer']);
    app(PaymentService::class)->verify($f['payment']->fresh(), $f['manager']);
    app(PaymentService::class)->markRefundPending($f['payment']->fresh());

    app(PaymentService::class)->markRefunded($f['payment']->fresh(), $f['manager'], 'TX-1');

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(0);
    expect(LoyaltyLedger::where('reason', LoyaltyReason::ClawbackFromRefund->value)->count())->toBe(1);
});
```

- [ ] **Step 3.2: Run — fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/EarnFlowTest.php tests/Feature/Loyalty/RefundFlowTest.php`
Expected: FAIL — PaymentService does not yet call LoyaltyService.

- [ ] **Step 3.3: Wire LoyaltyService into PaymentService**

Edit `app/Domain/Payment/Services/PaymentService.php`:

1. Add import:
```php
use App\Domain\Loyalty\Services\LoyaltyService;
```

2. Extend the constructor:
```php
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}
```

3. In `verify()`, AFTER the existing `$this->notifications->paymentApproved($payment);` line and BEFORE `return $payment;`, add:
```php
        if ($payment->appointment->service->loyalty_enabled) {
            $this->loyalty->awardForPayment($payment);
        }
```

4. In `markRefunded()`, AFTER the existing `$this->notifications->paymentRefunded($payment);` line and BEFORE `return $payment;`, add:
```php
        $this->loyalty->clawbackForRefund($payment);
```

(The `clawbackForRefund` is idempotent + no-op when no earn entry exists, so an unconditional call is safe.)

- [ ] **Step 3.4: Run tests — pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/EarnFlowTest.php tests/Feature/Loyalty/RefundFlowTest.php`
Expected: 3/3 PASS.

- [ ] **Step 3.5: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +3, Pint clean, PHPStan 0, Vite OK.

- [ ] **Step 3.6: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/Payment/Services/PaymentService.php \
        tests/Feature/Loyalty/EarnFlowTest.php tests/Feature/Loyalty/RefundFlowTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): award + clawback wired through PaymentService (P4a/3)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 4: BookingService + AppointmentTransitionService integration (redeem + reverse)

**Files:**
- Modify: `app/Domain/Booking/Data/BookingData.php`
- Modify: `app/Domain/Booking/Services/BookingService.php`
- Modify: `app/Domain/Booking/Services/AppointmentTransitionService.php`
- Create: `tests/Feature/Loyalty/RedeemFlowTest.php`
- Create: `tests/Feature/Loyalty/CancellationFlowTest.php`

- [ ] **Step 4.1: Write redeem + cancel tests**

Create `tests/Feature/Loyalty/RedeemFlowTest.php`:

```php
<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\DoctorProfile;
use App\Models\DoctorScheduleSlot;
use App\Models\LoyaltyLedger;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

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

    $start = CarbonImmutable::now()->next(\Carbon\Carbon::MONDAY)->setTime(10, 0);
    DoctorScheduleSlot::create([
        'doctor_profile_id' => $doctor->id,
        'weekday' => 1,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    return compact('customer', 'doctor', 'service', 'start');
}

it('booking with payment_method=loyalty_points creates appointment WITHOUT a Payment row', function () {
    $f = mkRedeemFixtures(balance: 1000);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: 'loyalty_points',
    ));

    expect($appt->payment_method)->toBe('loyalty_points')
        ->and($appt->loyalty_points_spent)->toBe(500)
        ->and(Payment::where('appointment_id', $appt->id)->exists())->toBeFalse()
        ->and($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);
    expect(LoyaltyLedger::where('customer_id', $f['customer']->id)
        ->where('reason', LoyaltyReason::RedeemedForAppointment->value)
        ->count())->toBe(1);
});

it('booking with insufficient balance throws and creates nothing', function () {
    $f = mkRedeemFixtures(balance: 100);

    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: 'loyalty_points',
    )))->toThrow(InsufficientLoyaltyBalanceException::class);

    expect(Appointment::count())->toBe(0)
        ->and(LoyaltyLedger::count())->toBe(0);
});

it('booking with payment_method=cash still creates Payment row and earns nothing yet', function () {
    $f = mkRedeemFixtures(balance: 0);

    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: 'cash',
    ));

    expect($appt->payment_method)->toBe('cash')
        ->and(Payment::where('appointment_id', $appt->id)->exists())->toBeTrue()
        ->and(LoyaltyLedger::count())->toBe(0);
});
```

Create `tests/Feature/Loyalty/CancellationFlowTest.php`:

```php
<?php

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\AppointmentTransitionService;
use App\Domain\Booking\Services\BookingService;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\LoyaltyLedger;

it('cancelling a loyalty-redeemed appointment returns the points', function () {
    $f = mkRedeemFixtures(balance: 1000);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId: $f['customer']->id,
        doctorProfileId: $f['doctor']->id,
        serviceId: $f['service']->id,
        startAt: $f['start'],
        deliveryMode: DeliveryMode::Center,
        createdByRole: UserRole::Customer,
        paymentMethod: 'loyalty_points',
    ));
    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(500);

    app(AppointmentTransitionService::class)->transition($appt, AppointmentStatus::Cancelled, 'changed mind');

    expect($f['customer']->customerProfile->fresh()->loyalty_balance)->toBe(1000);
    expect(LoyaltyLedger::where('reason', LoyaltyReason::RefundReversal->value)->count())->toBe(1);
});
```

- [ ] **Step 4.2: Run — fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/RedeemFlowTest.php tests/Feature/Loyalty/CancellationFlowTest.php`
Expected: FAIL — BookingData has no `paymentMethod`, services not wired.

- [ ] **Step 4.3: Extend BookingData**

Edit `app/Domain/Booking/Data/BookingData.php`:

```php
<?php

namespace App\Domain\Booking\Data;

use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;

final class BookingData
{
    public function __construct(
        public int $customerId,
        public int $doctorProfileId,
        public int $serviceId,
        public CarbonImmutable $startAt,
        public DeliveryMode $deliveryMode,
        public UserRole $createdByRole,
        public ?int $coverageAreaId = null,
        public ?string $addressText = null,
        public ?string $locationNote = null,
        public string $paymentMethod = 'cash',
    ) {}
}
```

- [ ] **Step 4.4: Wire BookingService**

Edit `app/Domain/Booking/Services/BookingService.php`:

1. Add imports:
```php
use App\Domain\Loyalty\Exceptions\InsufficientLoyaltyBalanceException;
use App\Domain\Loyalty\Services\LoyaltyService;
use App\Models\User;
```

2. Extend the constructor:
```php
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}
```

3. Inside the existing `DB::transaction(function () use ($d) { ... })` body of `book()`, replace the segment:

```php
            $appt = Appointment::create([
                'customer_id' => $d->customerId,
                'doctor_profile_id' => $doctor->id,
                'service_id' => $service->id,
                'start_at' => $available['start'],
                'end_at' => $available['end'],
                'status' => AppointmentStatus::Requested,
                'price_at_booking' => $quote['total'],
                'delivery_mode' => $d->deliveryMode,
                'home_surcharge_amount' => $quote['surcharge'],
                'created_by_role' => $d->createdByRole,
            ]);
```

…with the loyalty-aware version:

```php
            $apptAttrs = [
                'customer_id' => $d->customerId,
                'doctor_profile_id' => $doctor->id,
                'service_id' => $service->id,
                'start_at' => $available['start'],
                'end_at' => $available['end'],
                'status' => AppointmentStatus::Requested,
                'price_at_booking' => $quote['total'],
                'delivery_mode' => $d->deliveryMode,
                'home_surcharge_amount' => $quote['surcharge'],
                'created_by_role' => $d->createdByRole,
                'payment_method' => $d->paymentMethod,
            ];
            if ($d->paymentMethod === 'loyalty_points') {
                if (! $service->loyalty_enabled || ! $service->loyalty_redemption_points) {
                    throw new InsufficientLoyaltyBalanceException('الخدمة غير متاحة للاستبدال بالنقاط.');
                }
                $apptAttrs['loyalty_points_spent'] = (int) $service->loyalty_redemption_points;
            }
            $appt = Appointment::create($apptAttrs);
```

4. Replace the existing Payment creation block:

```php
            // P2: every Appointment gets a pending Payment created atomically.
            Payment::create([
                'appointment_id' => $appt->id,
                'amount' => $quote['total'],
                'status' => PaymentStatus::Pending,
            ]);
```

…with:

```php
            if ($d->paymentMethod === 'cash') {
                Payment::create([
                    'appointment_id' => $appt->id,
                    'amount' => $quote['total'],
                    'status' => PaymentStatus::Pending,
                ]);
            } else {
                $customer = User::query()->findOrFail($d->customerId);
                $this->loyalty->redeemForAppointment($appt, $customer);
            }
```

- [ ] **Step 4.5: Wire AppointmentTransitionService for reverse**

Edit `app/Domain/Booking/Services/AppointmentTransitionService.php`:

1. Add import:
```php
use App\Domain\Loyalty\Services\LoyaltyService;
```

2. Extend the constructor:
```php
    public function __construct(
        private readonly BookingService $booking,
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
    ) {}
```

3. In `transition()`, AFTER the existing `match` block (the one that dispatches `appointmentConfirmed/...`) and BEFORE `return $a;`, add:

```php
        if ($to === AppointmentStatus::Cancelled && $a->payment_method === 'loyalty_points') {
            $this->loyalty->reverseRedemption($a);
        }
```

- [ ] **Step 4.6: Run tests**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/RedeemFlowTest.php tests/Feature/Loyalty/CancellationFlowTest.php`
Expected: 4/4 PASS.

- [ ] **Step 4.7: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +4, all clean.

- [ ] **Step 4.8: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/Booking/Data/BookingData.php \
        app/Domain/Booking/Services/BookingService.php \
        app/Domain/Booking/Services/AppointmentTransitionService.php \
        tests/Feature/Loyalty/RedeemFlowTest.php tests/Feature/Loyalty/CancellationFlowTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): redeem at booking + reverse on cancel (P4a/4)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 5: Admin service config UI

**Files:**
- Modify: `app/Http/Controllers/Admin/ServiceController.php` (validation rules)
- Modify: `resources/js/Pages/Admin/Catalog/Services.vue` (edit modal extension)
- Create: `tests/Feature/Loyalty/ServiceConfigTest.php`

- [ ] **Step 5.1: Write the config test**

Create `tests/Feature/Loyalty/ServiceConfigTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->cat = ServiceCategory::create(['name' => 'c', 'slug' => 's', 'color_variant' => 'brand']);
});

it('manager creates service with loyalty fields', function () {
    $this->actingAs($this->manager)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'New',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => true,
        'loyalty_redemption_points' => 250,
    ])->assertRedirect();

    $s = Service::firstWhere('name', 'New');
    expect($s->loyalty_enabled)->toBeTrue()
        ->and($s->loyalty_redemption_points)->toBe(250);
});

it('rejects negative redemption points', function () {
    $this->actingAs($this->manager)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'Bad',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
        'loyalty_enabled' => true,
        'loyalty_redemption_points' => -10,
    ])->assertSessionHasErrors('loyalty_redemption_points');
});

it('receptionist cannot configure service loyalty', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->post('/admin/catalog/services', [
        'category_id' => $this->cat->id,
        'name' => 'X',
        'base_price' => '50.00',
        'duration_minutes' => 30,
        'home_service_enabled' => false,
    ])->assertForbidden();
});
```

- [ ] **Step 5.2: Run — fail**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/ServiceConfigTest.php`
Expected: FAIL — validation doesn't yet accept loyalty fields (the fields are persisted but validation may not pass).

- [ ] **Step 5.3: Extend ServiceController validation**

Edit `app/Http/Controllers/Admin/ServiceController.php`. Locate the `store` AND `update` methods. In both, find the `$request->validate([...])` block and append:

```php
            'loyalty_enabled' => ['sometimes', 'boolean'],
            'loyalty_redemption_points' => ['nullable', 'integer', 'min:1'],
```

Make sure the data passed to `Service::create()` / `$service->update()` includes the two fields. If the controller uses `$request->validated()` directly, no further change is needed — the model `#[Fillable]` already permits them.

- [ ] **Step 5.4: Run tests — pass**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/ServiceConfigTest.php`
Expected: 3/3 PASS.

- [ ] **Step 5.5: Extend the services edit modal**

Edit `resources/js/Pages/Admin/Catalog/Services.vue`. Inside the existing edit/create form (locate the `<form>` with `submit*` handler), AFTER the pricing/duration fields and BEFORE the form actions, add a Loyalty section:

```vue
        <fieldset class="space-y-3 border-t pt-4">
          <legend class="text-sm font-semibold text-text-primary">الولاء</legend>
          <FormGroup label="تفعيل الولاء" name="loyalty_enabled" :error="form.errors.loyalty_enabled">
            <template #default>
              <input
                id="loyalty_enabled"
                v-model="form.loyalty_enabled"
                type="checkbox"
                name="loyalty_enabled"
                class="h-4 w-4"
              />
            </template>
          </FormGroup>
          <FormGroup
            v-if="form.loyalty_enabled"
            label="نقاط الاستبدال"
            name="loyalty_redemption_points"
            :error="form.errors.loyalty_redemption_points"
            hint="اتركه فارغًا إن أردت كسب النقاط فقط دون السماح بالاستبدال."
          >
            <template #default="{ describedby }">
              <Input
                id="loyalty_redemption_points"
                v-model="form.loyalty_redemption_points"
                type="number"
                min="1"
                name="loyalty_redemption_points"
                dir="ltr"
                :aria-describedby="describedby"
              />
            </template>
          </FormGroup>
        </fieldset>
```

Also extend the `useForm({...})` initialization to include `loyalty_enabled` and `loyalty_redemption_points` so they reset cleanly.

- [ ] **Step 5.6: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +3, clean.

- [ ] **Step 5.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Admin/ServiceController.php \
        resources/js/Pages/Admin/Catalog/Services.vue \
        tests/Feature/Loyalty/ServiceConfigTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): service-config UI + validation (P4a/5)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 6: Admin customer-detail loyalty section + adjust modal

**Files:**
- Modify: `app/Http/Controllers/Admin/CustomerController.php` (embed loyalty preview)
- Modify: `resources/js/Pages/Admin/Customers/Show.vue` (loyalty section + adjust modal)
- Modify: `resources/js/Pages/Admin/Customers/Loyalty.vue` (full ledger page from Task 2 stub)
- Create: `tests/Feature/Loyalty/AdjustFlowTest.php`

- [ ] **Step 6.1: Write the adjust flow test**

Create `tests/Feature/Loyalty/AdjustFlowTest.php`:

```php
<?php

use App\Enums\LoyaltyReason;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use App\Models\User;

it('manager adjusts balance and customer profile reflects it', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$customer->id}/loyalty/adjust", [
            'delta' => 75,
            'note' => 'مكافأة شكر',
        ])->assertRedirect();

    expect($customer->customerProfile->fresh()->loyalty_balance)->toBe(75);
    $entry = LoyaltyLedger::firstWhere('customer_id', $customer->id);
    expect($entry->reason)->toBe(LoyaltyReason::AdjustmentByManager->value)
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->notes)->toBe('مكافأة شكر');
});

it('rejects zero delta', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::create(['user_id' => $customer->id]);

    $this->actingAs($manager)
        ->post("/admin/customers/{$customer->id}/loyalty/adjust", [
            'delta' => 0,
            'note' => 'x',
        ])->assertSessionHasErrors('delta');
});
```

- [ ] **Step 6.2: Run — should pass for first test (route exists from Task 2), confirm validation behavior on second**

Run: `cd /c/~projects/jannahclinic && php artisan test tests/Feature/Loyalty/AdjustFlowTest.php`
Expected: PASS for both, since `delta` validation already says `not_in:0`.

- [ ] **Step 6.3: Extend CustomerController::show with loyalty preview**

Edit `app/Http/Controllers/Admin/CustomerController.php`. In `show()`, AFTER the existing variables and BEFORE the `Inertia::render(...)` call, add:

```php
        $loyaltyBalance = app(\App\Domain\Loyalty\Services\LoyaltyService::class)->balance($customer);
        $loyaltyPreview = \App\Models\LoyaltyLedger::query()
            ->where('customer_id', $customer->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'points_delta' => $e->points_delta,
                'balance_after' => $e->balance_after,
                'reason' => $e->reason,
                'notes' => $e->notes,
                'actor_name' => $e->actor?->name,
                'created_at' => $e->created_at->toIso8601String(),
            ])
            ->all();
        $loyaltyTotals = [
            'earned' => (int) \App\Models\LoyaltyLedger::query()->where('customer_id', $customer->id)
                ->where('points_delta', '>', 0)->sum('points_delta'),
            'redeemed' => abs((int) \App\Models\LoyaltyLedger::query()->where('customer_id', $customer->id)
                ->where('points_delta', '<', 0)->sum('points_delta')),
        ];
```

Add to the `Inertia::render(...)` props array:

```php
            'loyaltyBalance' => $loyaltyBalance,
            'loyaltyPreview' => $loyaltyPreview,
            'loyaltyTotals' => $loyaltyTotals,
            'canAdjustLoyalty' => $request->user()->role === \App\Enums\UserRole::Manager,
```

- [ ] **Step 6.4: Add loyalty section to Customers/Show.vue**

Edit `resources/js/Pages/Admin/Customers/Show.vue`. Add to `defineProps({...})`:

```js
  loyaltyBalance: { type: Number, default: 0 },
  loyaltyPreview: { type: Array, default: () => [] },
  loyaltyTotals: { type: Object, default: () => ({ earned: 0, redeemed: 0 }) },
  canAdjustLoyalty: { type: Boolean, default: false },
```

Add a new `showAdjustModal` ref and `adjustForm` near the existing form setup:

```js
import { useForm } from '@inertiajs/vue3'  // confirm already imported

const showAdjustModal = ref(false)
const adjustForm = useForm({ delta: '', note: '' })

function openAdjust() {
  adjustForm.reset()
  adjustForm.clearErrors()
  showAdjustModal.value = true
}
function submitAdjust() {
  adjustForm.transform((data) => ({ delta: Number(data.delta), note: data.note }))
    .post(`/admin/customers/${props.customer.id}/loyalty/adjust`, {
      preserveScroll: true,
      onSuccess: () => { showAdjustModal.value = false },
    })
}

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}
```

Add this section to the template BEFORE the medical entries section (around line ~363 in the current file):

```vue
      <!-- Loyalty section -->
      <FormSection v-if="canViewMedical" title="نقاط الولاء" description="رصيد العميل وآخر 10 حركات.">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الرصيد الحالي</p>
            <p class="text-3xl font-bold text-text-primary">{{ loyaltyBalance }}</p>
          </div>
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الكسب الإجمالي</p>
            <p class="text-2xl font-semibold text-success">+{{ loyaltyTotals.earned }}</p>
          </div>
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الاستبدال الإجمالي</p>
            <p class="text-2xl font-semibold text-danger">−{{ loyaltyTotals.redeemed }}</p>
          </div>
        </div>

        <div class="flex justify-end">
          <Button v-if="canAdjustLoyalty" size="sm" variant="outline" @click="openAdjust">+ تعديل يدوي</Button>
          <Button size="sm" variant="ghost" @click="router.visit(`/admin/customers/${customer.id}/loyalty`)">
            عرض الكل
          </Button>
        </div>

        <ul class="divide-y divide-border-default">
          <li v-if="loyaltyPreview.length === 0" class="py-4 text-center text-text-tertiary text-sm">
            لا توجد حركات نقاط بعد.
          </li>
          <li v-for="row in loyaltyPreview" :key="row.id" class="py-3 flex items-center gap-3">
            <span :class="['text-sm font-semibold w-16', row.points_delta > 0 ? 'text-success' : 'text-danger']">
              {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
              <div class="text-xs text-text-tertiary truncate">
                {{ row.notes || '—' }} {{ row.actor_name ? `· بواسطة ${row.actor_name}` : '' }}
              </div>
            </div>
            <div class="text-xs text-text-tertiary shrink-0">
              {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
            </div>
          </li>
        </ul>
      </FormSection>
```

Add the adjust modal at the end of the template (next to other modals):

```vue
    <Modal :open="showAdjustModal" title="تعديل رصيد النقاط" @update:open="showAdjustModal = $event">
      <form class="space-y-4" @submit.prevent="submitAdjust">
        <FormGroup label="التغيير (موجب للإضافة، سالب للحسم)" name="delta" :error="adjustForm.errors.delta" required>
          <template #default="{ describedby }">
            <Input id="adj-delta" v-model="adjustForm.delta" type="number" name="delta" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>
        <FormGroup label="السبب" name="note" :error="adjustForm.errors.note" required>
          <template #default="{ describedby }">
            <textarea
              id="adj-note"
              v-model="adjustForm.note"
              name="note"
              rows="3"
              maxlength="500"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showAdjustModal = false">إلغاء</Button>
        <Button :disabled="adjustForm.processing" @click="submitAdjust">حفظ</Button>
      </template>
    </Modal>
```

- [ ] **Step 6.5: Build the full ledger page**

Replace `resources/js/Pages/Admin/Customers/Loyalty.vue` with:

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

defineProps({
  customer: { type: Object, required: true },
  balance: { type: Number, required: true },
  ledger: { type: Object, required: true },
})

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6">
      <PageHeader :title="`نقاط ولاء — ${customer.name}`" :description="`الرصيد الحالي: ${balance}`">
        <template #action>
          <Button variant="outline" @click="router.visit(`/admin/customers/${customer.id}`)">
            عودة لصفحة العميل
          </Button>
        </template>
      </PageHeader>

      <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
        <li v-if="ledger.data.length === 0" class="p-6 text-center text-text-secondary">
          لا توجد حركات.
        </li>
        <li v-for="row in ledger.data" :key="row.id" class="p-4 flex items-center gap-3">
          <span :class="['text-sm font-bold w-20', row.points_delta > 0 ? 'text-success' : 'text-danger']">
            {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
            <div class="text-xs text-text-tertiary truncate">{{ row.notes || '—' }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            رصيد: {{ row.balance_after }}
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
          </div>
        </li>
      </ul>

      <div v-if="ledger.last_page > 1" class="flex justify-center gap-2">
        <Button
          v-for="link in ledger.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        ><span v-html="link.label" /></Button>
      </div>
    </div>
  </AdminShell>
</template>
```

- [ ] **Step 6.6: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +2, clean.

- [ ] **Step 6.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Admin/CustomerController.php \
        resources/js/Pages/Admin/Customers/Show.vue \
        resources/js/Pages/Admin/Customers/Loyalty.vue \
        tests/Feature/Loyalty/AdjustFlowTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): admin customer-detail section + adjust modal + full ledger page (P4a/6)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 7: Portal /loyalty page + 6-tab bottom nav

**Files:**
- Modify: `resources/js/Pages/Portal/Loyalty/Index.vue` (full page)
- Modify: `resources/js/Layouts/ClientShell.vue` (6th tab)
- Create: `resources/js/Pages/Portal/Loyalty/__tests__/Index.spec.js`

- [ ] **Step 7.1: Add 6th tab to ClientShell**

Edit `resources/js/Layouts/ClientShell.vue`. Update `tabs` array (append) and the `grid-cols-5` class:

```js
const tabs = [
  { label: 'الرئيسية', href: '/portal', real: true },
  { label: 'الخدمات', href: '/portal/services', real: true },
  { label: 'الحجز', href: '/portal/booking', real: true },
  { label: 'مواعيدي', href: '/portal/appointments', real: true },
  { label: 'سجلي الطبي', href: '/portal/medical-record', real: true },
  { label: 'نقاطي', href: '/portal/loyalty', real: true },
]
```

Find the `grid-cols-5` class on the `<nav>` element and change to `grid-cols-6`.

- [ ] **Step 7.2: Build the full /portal/loyalty page**

Replace `resources/js/Pages/Portal/Loyalty/Index.vue`:

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  balance: { type: Number, required: true },
  summary: { type: Object, required: true },
  ledger: { type: Object, required: true },
  tab: { type: String, required: true },
})

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}

function setTab(t) {
  router.get('/portal/loyalty', { tab: t === 'all' ? undefined : t }, { preserveScroll: true })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="نقاطي" description="رصيدك وحركات النقاط." />

      <div class="grid grid-cols-2 gap-3">
        <div class="bg-surface-card rounded-lg shadow-sm p-4">
          <p class="text-xs text-text-secondary">الرصيد</p>
          <p class="text-3xl font-bold text-brand mt-1">{{ balance }}</p>
          <p class="text-xs text-text-tertiary mt-1">نقطة</p>
        </div>
        <div class="bg-surface-card rounded-lg shadow-sm p-4">
          <p class="text-xs text-text-secondary">منذ البداية</p>
          <p class="text-sm text-success mt-1">كسبت: +{{ summary.earned }}</p>
          <p class="text-sm text-danger">استبدلت: −{{ summary.redeemed }}</p>
        </div>
      </div>

      <div class="flex gap-2">
        <Button :variant="tab === 'all' ? 'default' : 'outline'" size="sm" @click="setTab('all')">الكل</Button>
        <Button :variant="tab === 'earn' ? 'default' : 'outline'" size="sm" @click="setTab('earn')">كسب</Button>
        <Button :variant="tab === 'redeem' ? 'default' : 'outline'" size="sm" @click="setTab('redeem')">استبدال</Button>
      </div>

      <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
        <li v-if="ledger.data.length === 0" class="p-6 text-center text-text-secondary">لا توجد حركات.</li>
        <li v-for="row in ledger.data" :key="row.id" class="p-3 flex items-center gap-3">
          <span :class="['text-sm font-bold w-14', row.points_delta > 0 ? 'text-success' : 'text-danger']">
            {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
            <div class="text-xs text-text-tertiary truncate">{{ row.notes || '—' }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
          </div>
        </li>
      </ul>

      <div v-if="ledger.last_page > 1" class="flex justify-center gap-1">
        <Button
          v-for="link in ledger.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        ><span v-html="link.label" /></Button>
      </div>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 7.3: Create Vitest spec**

Create `resources/js/Pages/Portal/Loyalty/__tests__/Index.spec.js`:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import Index from '../Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
  router: { get: vi.fn() },
  usePage: () => ({ props: { auth: { user: { role: 'customer' } }, notifications: null } }),
}))
vi.mock('@/Layouts/ClientShell.vue', () => ({ default: { template: '<div><slot /></div>' } }))

describe('Portal/Loyalty Index', () => {
  it('renders the balance', () => {
    const w = mount(Index, {
      props: {
        balance: 1247,
        summary: { earned: 3200, redeemed: 1953 },
        ledger: { data: [], links: [], last_page: 1 },
        tab: 'all',
      },
    })
    expect(w.text()).toContain('1247')
  })

  it('renders ledger rows', () => {
    const w = mount(Index, {
      props: {
        balance: 100,
        summary: { earned: 100, redeemed: 0 },
        ledger: {
          data: [{ id: 1, points_delta: 100, balance_after: 100, reason: 'earned_from_payment', notes: null, created_at: '2026-05-20T10:00:00Z' }],
          links: [],
          last_page: 1,
        },
        tab: 'all',
      },
    })
    expect(w.text()).toContain('كسب من زيارة')
  })
})
```

- [ ] **Step 7.4: Run Vitest**

Run: `cd /c/~projects/jannahclinic && npx vitest run resources/js/Pages/Portal/Loyalty/__tests__/Index.spec.js`
Expected: 2/2 PASS.

- [ ] **Step 7.5: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Vitest 28, clean.

- [ ] **Step 7.6: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add resources/js/Pages/Portal/Loyalty/Index.vue \
        resources/js/Pages/Portal/Loyalty/__tests__/Index.spec.js \
        resources/js/Layouts/ClientShell.vue
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): /portal/loyalty page + 6-tab bottom nav (P4a/7)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 8: Services browse badges + BookingWizard payment-method picker

**Files:**
- Modify: `resources/js/Pages/Portal/Services/Index.vue` (badges) — if file doesn't exist, locate the portal services list and apply equivalent treatment
- Modify: `resources/js/Pages/Portal/Booking/BookingWizard.vue` (picker)
- Modify: `app/Http/Controllers/Portal/ServiceBrowseController.php` (include loyalty fields + balance)
- Modify: `app/Http/Controllers/Portal/BookingController.php` (accept payment_method)
- Modify: `app/Http/Controllers/Admin/BookingController.php` (accept payment_method)
- Create: `resources/js/Pages/Portal/Booking/__tests__/PaymentMethodPicker.spec.js`

- [ ] **Step 8.1: Extend ServiceBrowseController to include loyalty fields**

Edit `app/Http/Controllers/Portal/ServiceBrowseController.php`. In the `index` method, ensure each returned service row carries `loyalty_enabled` and `loyalty_redemption_points`. Also pass the customer's current balance:

```php
        $balance = $request->user()
            ? app(\App\Domain\Loyalty\Services\LoyaltyService::class)->balance($request->user())
            : 0;
```

Pass `$balance` as an Inertia prop named `loyaltyBalance`.

- [ ] **Step 8.2: Add loyalty badge to services list**

Edit `resources/js/Pages/Portal/Services/Index.vue`. Add `loyaltyBalance` to `defineProps`:

```js
const props = defineProps({
  // ... existing ...
  loyaltyBalance: { type: Number, default: 0 },
})
```

In the service-card template (locate `<div>` rendering each service), AFTER the price and BEFORE the action area, add:

```vue
        <div
          v-if="service.loyalty_enabled && service.loyalty_redemption_points"
          :class="[
            'mt-2 inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium',
            loyaltyBalance >= service.loyalty_redemption_points
              ? 'bg-brand/10 text-brand'
              : 'bg-surface-page text-text-tertiary'
          ]"
          :title="loyaltyBalance >= service.loyalty_redemption_points
            ? 'يمكن الاستبدال بنقاط'
            : `رصيدك ${loyaltyBalance} — تحتاج ${service.loyalty_redemption_points - loyaltyBalance} نقطة إضافية`"
        >
          ⚡ استبدل بـ {{ service.loyalty_redemption_points }} نقطة
        </div>
```

- [ ] **Step 8.3: Extend BookingController to accept payment_method**

Edit `app/Http/Controllers/Portal/BookingController.php`. In the `store` method's `$request->validate(...)` block, append:

```php
            'payment_method' => ['sometimes', 'string', 'in:cash,loyalty_points'],
```

In the `BookingData` instantiation, pass `paymentMethod: $v['payment_method'] ?? 'cash'`.

Apply the same change to `app/Http/Controllers/Admin/BookingController.php`.

- [ ] **Step 8.4: Add the payment-method picker to BookingWizard**

Edit `resources/js/Pages/Portal/Booking/BookingWizard.vue`. In step 3 (the review/confirm step), BEFORE the submit button, add:

```vue
        <fieldset v-if="selectedService.loyalty_enabled && selectedService.loyalty_redemption_points" class="space-y-2">
          <legend class="text-sm font-semibold text-text-primary">طريقة الدفع</legend>
          <label class="flex items-center gap-2 p-3 rounded-md border border-border-default cursor-pointer">
            <input type="radio" v-model="paymentMethod" value="cash" />
            <span class="text-sm">نقدًا (تحويل بنكي)</span>
          </label>
          <label
            v-if="loyaltyBalance >= selectedService.loyalty_redemption_points"
            class="flex items-center gap-2 p-3 rounded-md border border-border-default cursor-pointer"
          >
            <input type="radio" v-model="paymentMethod" value="loyalty_points" />
            <span class="text-sm">
              بنقاط الولاء — يكلّف {{ selectedService.loyalty_redemption_points }} نقطة، رصيدك {{ loyaltyBalance }}
            </span>
          </label>
          <p
            v-else
            class="text-xs text-text-tertiary"
          >
            رصيدك ({{ loyaltyBalance }}) لا يكفي للاستبدال بنقاط (المطلوب: {{ selectedService.loyalty_redemption_points }}).
          </p>
        </fieldset>
```

Add `paymentMethod` to the local state and `loyaltyBalance` to props:

```js
const paymentMethod = ref('cash')
// in defineProps add:
//   loyaltyBalance: { type: Number, default: 0 },
```

In the submit handler, include `payment_method: paymentMethod.value` in the payload.

- [ ] **Step 8.5: Vitest spec for the picker**

Create `resources/js/Pages/Portal/Booking/__tests__/PaymentMethodPicker.spec.js`. (Treat this as a focused unit test of the radio logic; if the wizard is too large to mount, consider extracting the picker into `PaymentMethodPicker.vue` first — see Step 8.5a below.)

**Step 8.5a (extraction, if wizard mount is impractical):** Create `resources/js/Components/foundation/PaymentMethodPicker.vue` with the fieldset content as a standalone component taking props `loyaltyEnabled, loyaltyRedemptionPoints, loyaltyBalance, modelValue` and emitting `update:modelValue`. Replace the inline fieldset in BookingWizard with `<PaymentMethodPicker v-model="paymentMethod" ... />`.

Vitest spec:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import PaymentMethodPicker from '@/Components/foundation/PaymentMethodPicker.vue'

describe('PaymentMethodPicker', () => {
  it('hides loyalty option when service does not have loyalty_enabled', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: false, loyaltyRedemptionPoints: 500, loyaltyBalance: 1000, modelValue: 'cash' },
    })
    expect(w.find('[data-testid="picker"]').exists()).toBe(false)
  })
  it('shows loyalty option disabled when balance insufficient', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: true, loyaltyRedemptionPoints: 500, loyaltyBalance: 100, modelValue: 'cash' },
    })
    expect(w.text()).toContain('لا يكفي')
  })
  it('shows loyalty option enabled when balance sufficient', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: true, loyaltyRedemptionPoints: 500, loyaltyBalance: 600, modelValue: 'cash' },
    })
    expect(w.findAll('input[type="radio"]').length).toBe(2)
  })
})
```

- [ ] **Step 8.6: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Vitest +3, all clean.

- [ ] **Step 8.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Portal/ServiceBrowseController.php \
        app/Http/Controllers/Portal/BookingController.php \
        app/Http/Controllers/Admin/BookingController.php \
        resources/js/Pages/Portal/Services/Index.vue \
        resources/js/Pages/Portal/Booking/BookingWizard.vue \
        resources/js/Components/foundation/PaymentMethodPicker.vue \
        resources/js/Pages/Portal/Booking/__tests__/PaymentMethodPicker.spec.js
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): browse badges + booking payment-method picker (P4a/8)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 9: Notifications integration

**Files:**
- Modify: `app/Enums/NotificationCategory.php`
- Create: `app/Notifications/LoyaltyChanged.php`
- Modify: `app/Domain/Notification/Services/NotificationService.php`
- Modify: `app/Domain/Loyalty/Services/LoyaltyService.php` (call notifications)

- [ ] **Step 9.1: Add Loyalty case to NotificationCategory**

Edit `app/Enums/NotificationCategory.php`. Add the case:

```php
    case Loyalty = 'loyalty';
```

- [ ] **Step 9.2: Create LoyaltyChanged notification**

Create `app/Notifications/LoyaltyChanged.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class LoyaltyChanged extends Notification
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

- [ ] **Step 9.3: Add 4 generators to NotificationService**

Edit `app/Domain/Notification/Services/NotificationService.php`. Add imports:

```php
use App\Models\LoyaltyLedger;
use App\Notifications\LoyaltyChanged;
```

Add at the end of the class (before `markAsRead`):

```php
    public function loyaltyPointsEarned(LoyaltyLedger $entry): void
    {
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "+{$entry->points_delta} نقطة من زيارتك",
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsEarned');
    }

    public function loyaltyPointsRedeemed(LoyaltyLedger $entry): void
    {
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => abs($entry->points_delta).' نقطة استُبدلت بحجز',
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsRedeemed');
    }

    public function loyaltyPointsAdjusted(LoyaltyLedger $entry): void
    {
        $sign = $entry->points_delta > 0 ? '+' : '';
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "عدّل الطاقم رصيدك ({$sign}{$entry->points_delta})",
            'body' => $entry->notes ?? 'بدون سبب مذكور.',
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsAdjusted');
    }

    public function loyaltyPointsReversed(LoyaltyLedger $entry): void
    {
        $sign = $entry->points_delta > 0 ? '+' : '';
        $this->dispatch($entry->customer, new LoyaltyChanged([
            'category' => NotificationCategory::Loyalty->value,
            'title' => "{$sign}{$entry->points_delta} نقطة بعد إلغاء/استرداد",
            'body' => "رصيدك الآن {$entry->balance_after} نقطة.",
            'action_url' => '/portal/loyalty',
            'subject_type' => LoyaltyLedger::class,
            'subject_id' => $entry->id,
        ]), 'loyaltyPointsReversed');
    }
```

- [ ] **Step 9.4: Hook notifications inside LoyaltyService**

Edit `app/Domain/Loyalty/Services/LoyaltyService.php`. Add an import + constructor param:

```php
use App\Domain\Notification\Services\NotificationService;
use App\Enums\LoyaltyReason;
```

Replace the empty class body opener and add a constructor:

```php
class LoyaltyService
{
    public function __construct(private readonly NotificationService $notifications) {}
```

After each `$this->writeEntry(...)` call site, capture the inserted ledger entry and dispatch the matching notification AFTER the call (the writeEntry already commits its inner transaction). Modify `writeEntry` to return the created entry:

```php
    private function writeEntry(
        User $customer,
        int $delta,
        LoyaltyReason $reason,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        ?string $notes = null,
        ?User $actor = null,
    ): LoyaltyLedger {
        return DB::transaction(function () use ($customer, $delta, $reason, $reference, $notes, $actor) {
            $profile = $customer->customerProfile
                ?? CustomerProfile::create(['user_id' => $customer->id, 'loyalty_balance' => 0]);
            $newBalance = (int) $profile->loyalty_balance + $delta;
            $entry = LoyaltyLedger::create([
                'customer_id' => $customer->id,
                'points_delta' => $delta,
                'balance_after' => $newBalance,
                'reason' => $reason->value,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'actor_id' => $actor?->id,
            ]);
            $profile->update(['loyalty_balance' => $newBalance]);

            return $entry;
        });
    }
```

Then update each public method to capture the entry and dispatch the matching notification AFTER the writeEntry returns. Example for `awardForPayment`:

```php
    public function awardForPayment(Payment $payment): void
    {
        // ... idempotency check ...
        $points = (int) floor((float) $payment->amount);
        if ($points <= 0) {
            return;
        }
        $customer = $payment->appointment->customer;
        $entry = $this->writeEntry($customer, $points, LoyaltyReason::EarnedFromPayment, $payment);
        $this->notifications->loyaltyPointsEarned($entry);
    }
```

Mirror the pattern for `clawbackForRefund` → `loyaltyPointsReversed`, `redeemForAppointment` → `loyaltyPointsRedeemed`, `reverseRedemption` → `loyaltyPointsReversed`, `adjust` → `loyaltyPointsAdjusted`. The Pest tests for those methods may need updating if they assert on notification counts — re-run.

- [ ] **Step 9.5: Full gate**

Run:
```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: all green.

- [ ] **Step 9.6: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Enums/NotificationCategory.php \
        app/Notifications/LoyaltyChanged.php \
        app/Domain/Notification/Services/NotificationService.php \
        app/Domain/Loyalty/Services/LoyaltyService.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(loyalty): 4 LoyaltyChanged notifications wired through P5a NotificationService (P4a/9)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 10: DoD gate + ARCHITECTURE + CHANGELOG + tag + artisan command

**Files:**
- Create: `app/Console/Commands/RebuildLoyaltyBalances.php`
- Modify: `docs/ARCHITECTURE.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 10.1: Add artisan rebuild command**

Create `app/Console/Commands/RebuildLoyaltyBalances.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\CustomerProfile;
use App\Models\LoyaltyLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildLoyaltyBalances extends Command
{
    protected $signature = 'loyalty:rebuild-balances';

    protected $description = 'Recompute customer_profiles.loyalty_balance from the loyalty_ledger sum (idempotent ops command).';

    public function handle(): int
    {
        CustomerProfile::query()->chunkById(500, function ($profiles) {
            foreach ($profiles as $profile) {
                $sum = (int) LoyaltyLedger::query()->where('customer_id', $profile->user_id)->sum('points_delta');
                DB::table('customer_profiles')->where('id', $profile->id)->update(['loyalty_balance' => $sum]);
            }
        });
        $this->info('Loyalty balances rebuilt.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 10.2: Update ARCHITECTURE.md**

Edit `docs/ARCHITECTURE.md`. After the P5a Notification System section, append:

```markdown
## Loyalty Points (P4a)

In-app retention mechanic: every cash payment earns `floor(amount)` points; points redeem at booking against per-service redemption costs.

- **Storage:** append-only `loyalty_ledger` (mirrors `medical_audit_logs` invariants — `save()` throws on `$exists`, `delete()` throws unconditionally). `customer_profiles.loyalty_balance` is the denormalized cache; ledger is the source of truth.
- **Reasons enum:** `App\Enums\LoyaltyReason` — `earned_from_payment` / `redeemed_for_appointment` / `clawback_from_refund` / `refund_reversal` / `adjustment_by_manager`.
- **Per-service flags:** `services.loyalty_enabled` (bool) controls participation in both directions; `services.loyalty_redemption_points` (int, nullable) is the redemption cost. Postgres CHECK constraint ensures non-negative redemption.
- **Booking payment methods:** `appointments.payment_method ∈ {cash, loyalty_points}`. Loyalty-paid appointments have NO `Payment` row; the ledger entry IS the proof of payment.
- **Generator:** `App\Domain\Loyalty\Services\LoyaltyService` — explicit, transactional, idempotent on award/clawback/reverse (lookup by reference). Notifications dispatched AFTER ledger commit via `NotificationService::dispatch` (P5a pattern).
- **Wiring:** `PaymentService::verify` (award, gated on service.loyalty_enabled), `PaymentService::markRefunded` (clawback, unconditional/idempotent), `BookingService::book` (redeem inside transaction, skips Payment creation), `AppointmentTransitionService::transition` (reverse on Cancelled with `payment_method=loyalty_points`).
- **Authorization:** customer reads own ledger; staff read any; manager-only adjust; receptionist/doctor cannot adjust.
- **Ops command:** `php artisan loyalty:rebuild-balances` recomputes cache from ledger.

**Deferred to future ADRs:** points expiry, VIP tiers, promotional 2x campaigns, point transfers between customers, mixed redemption (partial points + cash), SMS/email loyalty notifications, memberships (P4b — independent sub-project). See spec §10 for the full deferred-items table.
```

- [ ] **Step 10.3: Update CHANGELOG.md**

Edit `CHANGELOG.md`. Insert a new section above the most recent `## [P5a]` heading (or wherever the Unreleased section sits):

```markdown
## [P4a] Loyalty Points — 2026-05-20

**P4a complete:** customers earn `floor(amount)` points per shekel paid (1 ₪ = 1 point) on appointments whose service has `loyalty_enabled=true`. Points redeem at booking time against a per-service `loyalty_redemption_points` cost, replacing the Payment row entirely (the ledger entry is the proof). Refunds claw back points symmetrically; cancellation of a loyalty-redeemed booking returns the points. Manager-adjustment widget on the customer page (with reason + audit), reflected in real time on `/portal/loyalty`. **No SMS/email, no expiry, no tiers** — all deferred behind future ADRs.

- **Domain model:** append-only `loyalty_ledger` (`save()` throws on update, `delete()` throws unconditionally) + `customer_profiles.loyalty_balance` denormalized cache + `services.loyalty_enabled` / `services.loyalty_redemption_points` + `appointments.payment_method` / `appointments.loyalty_points_spent`. Postgres CHECK constraints on reason set, points sign, and method/spent consistency.
- **`LoyaltyService`** mirrors `AuditLogger` / `NotificationService` patterns: explicit, transactional, idempotent on `awardForPayment` / `clawbackForRefund` / `reverseRedemption` by lookup on reference. `redeemForAppointment` is the only call site inside a domain transaction (BookingService) — every other writer dispatches AFTER `DB::transaction` returns, per the P5a lesson.
- **Routes (3 new — locked):** `admin.customers.loyalty.show` / `admin.customers.loyalty.adjust` (manager only) / `portal.loyalty.index`.
- **Notifications (P5a integration):** `NotificationCategory::Loyalty` added; 4 generators (`loyaltyPointsEarned` / `loyaltyPointsRedeemed` / `loyaltyPointsAdjusted` / `loyaltyPointsReversed`) wired through the same `dispatch()` helper that contains failures via try/catch + log.
- **UI:** service-edit modal gains loyalty section (toggle + redemption cost); customer-detail page gains balance + 10-row preview + manager adjust modal + full-ledger page at `/admin/customers/{id}/loyalty`; portal gains `/portal/loyalty` with balance card + lifetime summary + filter tabs (all/earn/redeem); services browse list shows a "⚡ استبدل بـ X نقطة" badge per redeemable service (dimmed when balance insufficient); BookingWizard step 3 gets a payment-method picker (extracted as `<PaymentMethodPicker>` for testability). ClientShell bottom nav extends from 5 to 6 tabs (adds "نقاطي").
- **Auth:** customer reads own ledger only; staff read any; manager-only `adjust` (route + service-layer double check); receptionist/doctor cannot adjust.
- **Tests:** ~30 Pest (`LoyaltyService` unit, `EarnFlow`, `RedeemFlow`, `RefundFlow`, `CancellationFlow`, `AdjustFlow`, `Authorization`, `ServiceConfig`, append-only invariant) + 3 Vitest (Portal/Loyalty Index + PaymentMethodPicker). Rollback invariant tested; idempotency tested; cross-user 403 matrix covered.
- **Ops:** `loyalty:rebuild-balances` artisan command rebuilds the cache from the ledger sum (idempotent, chunked).
- **Tag:** `p4a-loyalty`.
```

- [ ] **Step 10.4: Final full gate**

Run:
```
cd /c/~projects/jannahclinic
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse --no-progress
npm run build
npx vitest run
```

Expected at this point:
- Pest: ~310 (~281 before + ~30 new from P4a)
- Vitest: 29 (26 + 3 new)
- Pint clean, PHPStan 0, Vite OK

- [ ] **Step 10.5: Manual smoke pass**

1. Log in as a customer. Customer profile has `loyalty_balance=0`.
2. Make a booking → upload receipt → log in as manager → verify → log back in as customer → bell badge shows 1 → open `/portal/loyalty` → balance = 100 (for a 100₪ service).
3. Browse services → see "⚡ استبدل بـ 500 نقطة" badge → confirm dimmed (balance is 100, not 500).
4. As manager: adjust customer +500 with note "هدية ترحيب" → customer sees notification → balance now 600.
5. Customer rebooks → BookingWizard step 3 shows loyalty option enabled → submit with `loyalty_points` → appointment created with `payment_method=loyalty_points`, no payment row, balance 100 → ledger shows -500 entry.
6. Cancel the loyalty-redeemed appointment → balance returns to 600 → ledger shows +500 reversal entry.

If any step fails: fix root cause, re-run gate, do NOT proceed to tag.

- [ ] **Step 10.6: Commit + tag + push**

```bash
cd /c/~projects/jannahclinic
git add app/Console/Commands/RebuildLoyaltyBalances.php docs/ARCHITECTURE.md CHANGELOG.md
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "docs(p4a): ARCHITECTURE + CHANGELOG + rebuild-balances command (P4a/10)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag p4a-loyalty
git push origin main
git push origin p4a-loyalty
```

- [ ] **Step 10.7: Done**

P4a is complete. Report to user:
- Final test counts (Pest / Vitest / Pint / PHPStan / Vite build)
- Tag pushed: `p4a-loyalty`
- Highlight the manual smoke pass that was performed
- Surface any deferred items that became apparent during execution (add to spec §10 if so)

---

## Self-Review Summary

**Spec coverage:** Every section of spec §1–§12 maps to a task above. The cancellation-on-loyalty edge case (spec §4 last row of integration table) is covered by Task 4's `CancellationFlowTest`. The `loyalty:rebuild-balances` ops command from spec §12 is added in Task 10. Notification PHI-omission is not relevant here (no PHI in loyalty data, explicit in spec §8).

**Placeholder scan:** No "TBD" / "TODO" / "fill in" markers. Every code block in every step is complete. The only conditional in Task 8 (`if file doesn't exist`) is a real exploration that needs to happen at execution time — the engineer either edits the existing file or creates it; the snippet content is provided.

**Type consistency:** `LoyaltyService` method names (`awardForPayment` / `clawbackForRefund` / `redeemForAppointment` / `reverseRedemption` / `adjust` / `balance`) match across Tasks 1, 3, 4, 6, 9. `LoyaltyReason` enum values (`earned_from_payment` / `redeemed_for_appointment` / `clawback_from_refund` / `refund_reversal` / `adjustment_by_manager`) match across migration CHECK, enum cases, and test assertions. `BookingData.paymentMethod` field name matches everywhere it's referenced. `NotificationCategory::Loyalty` value (`'loyalty'`) is set in Task 9.1 and used in Task 9.3's payloads.
