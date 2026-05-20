# P3 — Medical Records (Encrypted + Audited) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Execution mode for this branch:** lean inline (no per-task subagent ceremony). After each task: run targeted tests + Pint + PHPStan; full DoD gate runs once at the end (Task 18).

**Goal:** Add medical records (`medical_entries`, `prescriptions`), per-customer chronic-conditions + allergies, and an immutable audit log — all encrypted at rest — and ship a Doctor write UI, a Manager/Doctor view, and a Customer portal read view. Lift the ADR-002 production block on real patient data via ADR-003 (already authored).

**Architecture:** Three new tables. Laravel `encrypted` Eloquent casts on every PHI field. A single `AuditLogger` service called explicitly inside each service-class `DB::transaction`. Append-only `MedicalAuditLog` model rejecting `save`-after-`exists` and rejecting `delete`. Doctor / Manager / Receptionist / Customer authorization via two policies. Server-rendered Inertia + Vue 3 with the new reusable `DataTable` foundation components consuming `@tanstack/vue-table`.

**Tech Stack:** Laravel 13, PostgreSQL 16 (prod + CI) / SQLite in-memory (tests), Pest, Inertia.js, Vue 3 Composition API, Tailwind v4, shadcn-vue + `@tanstack/vue-table` v8 (already installed), Pint, PHPStan L5, Vitest.

---

## File Structure

**Create:**
- `database/migrations/2026_05_20_120000_create_medical_records_and_audit.php`
- `app/Enums/MedicalAuditAction.php`
- `app/Models/MedicalEntry.php`
- `app/Models/Prescription.php`
- `app/Models/MedicalAuditLog.php`
- `app/Domain/MedicalRecord/Services/AuditLogger.php`
- `app/Domain/MedicalRecord/Services/MedicalEntryService.php`
- `app/Domain/MedicalRecord/Services/PrescriptionService.php`
- `app/Console/Commands/EncryptCustomerNotes.php`
- `app/Policies/MedicalEntryPolicy.php`
- `app/Policies/PrescriptionPolicy.php`
- `app/Http/Controllers/Admin/MedicalEntryController.php`
- `app/Http/Controllers/Portal/MedicalRecordController.php`
- `resources/js/Components/foundation/DataTable.vue`
- `resources/js/Components/foundation/DataTablePagination.vue`
- `resources/js/Components/foundation/DataTableColumnHeader.vue`
- `resources/js/Components/foundation/DataTableViewOptions.vue`
- `resources/js/Components/foundation/DataTableRowActions.vue`
- `resources/js/Pages/Admin/MedicalEntries/Edit.vue`
- `resources/js/Pages/Portal/MedicalRecord/Index.vue`
- `resources/js/Pages/Portal/MedicalRecord/Show.vue`
- `tests/Unit/Domain/MedicalRecord/AuditLoggerTest.php`
- `tests/Unit/Domain/MedicalRecord/MedicalEntryServiceTest.php`
- `tests/Unit/Domain/MedicalRecord/PrescriptionServiceTest.php`
- `tests/Unit/Models/MedicalAuditLogTest.php`
- `tests/Feature/Admin/MedicalEntryControllerTest.php`
- `tests/Feature/Admin/CustomerMedicalProfileTest.php`
- `tests/Feature/Portal/MedicalRecordControllerTest.php`
- `tests/Feature/Encryption/PhiAtRestTest.php`
- `tests/Feature/Encryption/EncryptCustomerNotesCommandTest.php`
- `resources/js/Components/foundation/__tests__/DataTable.spec.js`

**Modify:**
- `app/Models/CustomerProfile.php` — add `encrypted` cast on `notes`, `chronic_conditions`, `allergies`; fillable additions
- `app/Http/Controllers/Admin/CustomerController.php` — add `updateMedicalProfile`
- `app/Providers/AppServiceProvider.php` or `AuthServiceProvider.php` — register policies
- `app/Providers/AppServiceProvider.php` — observer registration (no new observer in P3)
- `routes/web.php` — six new routes
- `tests/Feature/RouteNamesTest.php` — lock six new names
- `resources/js/Pages/Admin/Appointments/Show.vue` — add medical-entry section
- `resources/js/Pages/Admin/Customers/Show.vue` — add DataTable for entries + medical-profile form
- `resources/js/Layouts/PortalShell.vue` — add "السجل الطبي" sidebar item
- `docs/ARCHITECTURE.md` — security posture update + R-DataTable debt list
- `docs/DOMAIN-MODEL.md` — add MedicalEntry, Prescription, MedicalAuditLog entities
- `CHANGELOG.md` — P3 entry

---

## Task 1: Migrations — three new tables + customer_profiles additions

**Files:**
- Create: `database/migrations/2026_05_20_120000_create_medical_records_and_audit.php`

- [ ] **Step 1: Write the migration**

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
        Schema::create('medical_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->text('visible_summary');
            $t->text('staff_notes')->nullable();
            $t->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medical_entry_id')->constrained()->cascadeOnDelete();
            $t->string('medication_name');
            $t->string('dosage');
            $t->string('frequency');
            $t->string('duration');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['medical_entry_id', 'created_at']);
        });

        Schema::create('medical_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id');
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->json('changed_fields')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['customer_id', 'created_at']);
            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['user_id', 'created_at']);
        });

        Schema::table('customer_profiles', function (Blueprint $t) {
            $t->text('chronic_conditions')->nullable();
            $t->text('allergies')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE medical_audit_logs ADD CONSTRAINT medical_audit_logs_action_check CHECK (action IN ('entry.created','entry.updated','entry.viewed','prescription.created','prescription.updated','prescription.deleted','profile_medical.viewed','profile_medical.updated'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medical_audit_logs DROP CONSTRAINT IF EXISTS medical_audit_logs_action_check');
        }
        Schema::table('customer_profiles', function (Blueprint $t) {
            $t->dropColumn(['chronic_conditions', 'allergies']);
        });
        Schema::dropIfExists('medical_audit_logs');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medical_entries');
    }
};
```

- [ ] **Step 2: Run migration on scratch DB**

Run: `php artisan migrate:fresh --env=testing` (uses SQLite in-memory) — expect: green
Run (PowerShell): `$env:DB_DATABASE='jannahclinic_scratch'; php artisan migrate:fresh; $env:DB_DATABASE=$null` — expect: green (verifies pgsql CHECK constraint syntax)

- [ ] **Step 3: Commit**

```powershell
git add database/migrations/2026_05_20_120000_create_medical_records_and_audit.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add medical_entries, prescriptions, medical_audit_logs tables" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Enum + Models with encrypted casts + append-only audit log

**Files:**
- Create: `app/Enums/MedicalAuditAction.php`, `app/Models/MedicalEntry.php`, `app/Models/Prescription.php`, `app/Models/MedicalAuditLog.php`, `tests/Unit/Models/MedicalAuditLogTest.php`
- Modify: `app/Models/CustomerProfile.php`

- [ ] **Step 1: Write the failing append-only test**

```php
<?php

use App\Enums\MedicalAuditAction;
use App\Models\MedicalAuditLog;
use App\Models\User;

it('throws on save after create (append-only)', function () {
    $u = User::factory()->create();
    $log = MedicalAuditLog::create([
        'user_id' => $u->id,
        'action' => MedicalAuditAction::EntryCreated->value,
        'auditable_type' => 'App\Models\MedicalEntry',
        'auditable_id' => 1,
        'customer_id' => $u->id,
    ]);

    $log->action = MedicalAuditAction::EntryUpdated->value;

    expect(fn () => $log->save())->toThrow(\LogicException::class, 'append-only');
});

it('throws on delete (append-only)', function () {
    $u = User::factory()->create();
    $log = MedicalAuditLog::create([
        'user_id' => $u->id,
        'action' => MedicalAuditAction::EntryCreated->value,
        'auditable_type' => 'App\Models\MedicalEntry',
        'auditable_id' => 1,
        'customer_id' => $u->id,
    ]);

    expect(fn () => $log->delete())->toThrow(\LogicException::class, 'append-only');
});
```

- [ ] **Step 2: Run — expect FAIL (model not implemented)**

Run: `./vendor/bin/pest tests/Unit/Models/MedicalAuditLogTest.php` → FAIL

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum MedicalAuditAction: string
{
    case EntryCreated = 'entry.created';
    case EntryUpdated = 'entry.updated';
    case EntryViewed = 'entry.viewed';
    case PrescriptionCreated = 'prescription.created';
    case PrescriptionUpdated = 'prescription.updated';
    case PrescriptionDeleted = 'prescription.deleted';
    case ProfileMedicalViewed = 'profile_medical.viewed';
    case ProfileMedicalUpdated = 'profile_medical.updated';
}
```

- [ ] **Step 4: Write MedicalAuditLog model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'customer_id', 'changed_fields', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'changed_fields' => 'array',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('medical_audit_logs is append-only');
        }
        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new \LogicException('medical_audit_logs is append-only');
    }
}
```

- [ ] **Step 5: Write MedicalEntry model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalEntry extends Model
{
    protected $fillable = ['appointment_id', 'author_id', 'visible_summary', 'staff_notes'];

    protected $casts = [
        'visible_summary' => 'encrypted',
        'staff_notes' => 'encrypted',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderBy('created_at');
    }
}
```

- [ ] **Step 6: Write Prescription model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'medical_entry_id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes',
    ];

    protected $casts = [
        'medication_name' => 'encrypted',
        'dosage' => 'encrypted',
        'frequency' => 'encrypted',
        'duration' => 'encrypted',
        'notes' => 'encrypted',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(MedicalEntry::class, 'medical_entry_id');
    }
}
```

- [ ] **Step 7: Update CustomerProfile model**

Read: `app/Models/CustomerProfile.php`. Add `chronic_conditions`, `allergies` to `$fillable`; cast `notes`, `chronic_conditions`, `allergies` to `encrypted` (use the existing casts pattern in that file). Code shape:

```php
protected $fillable = [
    'user_id', 'date_of_birth', 'gender', 'notes', 'avatar_path',
    'profile_completed_at', 'chronic_conditions', 'allergies',
];

protected $casts = [
    'date_of_birth' => 'date',
    'profile_completed_at' => 'datetime',
    'notes' => 'encrypted',
    'chronic_conditions' => 'encrypted',
    'allergies' => 'encrypted',
];
```

- [ ] **Step 8: Run tests — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Models/MedicalAuditLogTest.php` → PASS (2/2)

- [ ] **Step 9: Commit**

```powershell
git add app/Enums/MedicalAuditAction.php app/Models/MedicalEntry.php app/Models/Prescription.php app/Models/MedicalAuditLog.php app/Models/CustomerProfile.php tests/Unit/Models/MedicalAuditLogTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add models with encrypted casts and append-only audit log" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: AuditLogger service

**Files:**
- Create: `app/Domain/MedicalRecord/Services/AuditLogger.php`, `tests/Unit/Domain/MedicalRecord/AuditLoggerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\MedicalAuditAction;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Http\Request;

it('records an entry-created audit row with full field set', function () {
    $doctor = User::factory()->create();
    $customer = User::factory()->create();
    $entry = MedicalEntry::factory()->create(['author_id' => $doctor->id]);

    $req = Request::create('/x', 'POST', server: ['REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'pest']);
    $req->setUserResolver(fn () => $doctor);

    $logger = new AuditLogger($req);
    $logger->record(MedicalAuditAction::EntryCreated, $entry, $customer, ['visible_summary', 'staff_notes']);

    $row = MedicalAuditLog::firstWhere('action', 'entry.created');
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($doctor->id)
        ->and($row->customer_id)->toBe($customer->id)
        ->and($row->ip_address)->toBe('1.2.3.4')
        ->and($row->user_agent)->toBe('pest')
        ->and($row->changed_fields)->toBe(['visible_summary', 'staff_notes']);
});

it('truncates long user_agent to 255 chars', function () {
    $doctor = User::factory()->create();
    $customer = User::factory()->create();
    $entry = MedicalEntry::factory()->create(['author_id' => $doctor->id]);

    $long = str_repeat('A', 400);
    $req = Request::create('/x', 'POST', server: ['HTTP_USER_AGENT' => $long]);
    $req->setUserResolver(fn () => $doctor);

    (new AuditLogger($req))->record(MedicalAuditAction::EntryViewed, $entry, $customer);

    expect(MedicalAuditLog::first()->user_agent)->toHaveLength(255);
});
```

- [ ] **Step 2: Write a MedicalEntry factory** (needed for both tests above)

Create `database/factories/MedicalEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'author_id' => User::factory(),
            'visible_summary' => 'sample diagnosis',
            'staff_notes' => null,
        ];
    }
}
```

- [ ] **Step 3: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/AuditLoggerTest.php` → FAIL (AuditLogger not implemented)

- [ ] **Step 4: Implement AuditLogger**

```php
<?php

namespace App\Domain\MedicalRecord\Services;

use App\Enums\MedicalAuditAction;
use App\Models\MedicalAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private Request $request) {}

    public function record(
        MedicalAuditAction $action,
        Model $auditable,
        User $patient,
        ?array $changedFields = null
    ): void {
        MedicalAuditLog::create([
            'user_id' => $this->request->user()?->id,
            'action' => $action->value,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'customer_id' => $patient->id,
            'changed_fields' => $changedFields,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->truncate((string) $this->request->userAgent(), 255),
        ]);
    }

    private function truncate(string $value, int $max): ?string
    {
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $max);
    }
}
```

- [ ] **Step 5: Run — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/AuditLoggerTest.php` → PASS (2/2)

- [ ] **Step 6: Commit**

```powershell
git add app/Domain/MedicalRecord/Services/AuditLogger.php database/factories/MedicalEntryFactory.php tests/Unit/Domain/MedicalRecord/AuditLoggerTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add AuditLogger service" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Backfill command — re-encrypt existing customer_profiles.notes idempotently

**Files:**
- Create: `app/Console/Commands/EncryptCustomerNotes.php`, `tests/Feature/Encryption/EncryptCustomerNotesCommandTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('encrypts plaintext notes and is idempotent', function () {
    $user = User::factory()->create();
    DB::table('customer_profiles')->insert([
        'user_id' => $user->id,
        'notes' => 'plaintext leftover',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('medical:encrypt-customer-notes')->assertExitCode(0);

    $raw = DB::table('customer_profiles')->where('user_id', $user->id)->value('notes');
    expect($raw)->not->toBe('plaintext leftover');
    expect(Crypt::decryptString($raw))->toBe('plaintext leftover');

    $this->artisan('medical:encrypt-customer-notes')->assertExitCode(0);

    $rawAfter = DB::table('customer_profiles')->where('user_id', $user->id)->value('notes');
    expect(Crypt::decryptString($rawAfter))->toBe('plaintext leftover');
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Feature/Encryption/EncryptCustomerNotesCommandTest.php` → FAIL (command not found)

- [ ] **Step 3: Implement command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptCustomerNotes extends Command
{
    protected $signature = 'medical:encrypt-customer-notes';
    protected $description = 'Re-encrypt plaintext customer_profiles.notes rows (idempotent)';

    public function handle(): int
    {
        $count = 0;
        DB::table('customer_profiles')
            ->whereNotNull('notes')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    try {
                        Crypt::decryptString($row->notes);
                        continue;
                    } catch (\Throwable) {
                        DB::table('customer_profiles')
                            ->where('id', $row->id)
                            ->update(['notes' => Crypt::encryptString($row->notes)]);
                        $count++;
                    }
                }
            });
        $this->info("Re-encrypted {$count} rows.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run — expect PASS**

Run: `./vendor/bin/pest tests/Feature/Encryption/EncryptCustomerNotesCommandTest.php` → PASS

- [ ] **Step 5: Commit**

```powershell
git add app/Console/Commands/EncryptCustomerNotes.php tests/Feature/Encryption/EncryptCustomerNotesCommandTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add idempotent medical:encrypt-customer-notes backfill command" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: MedicalEntryService — create / update with audit, transactional

**Files:**
- Create: `app/Domain/MedicalRecord/Services/MedicalEntryService.php`, `tests/Unit/Domain/MedicalRecord/MedicalEntryServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Domain\MedicalRecord\Services\MedicalEntryService;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->appointment = Appointment::factory()->completed()->create([
        'customer_id' => $this->customer->id,
    ]);

    $req = Request::create('/');
    $req->setUserResolver(fn () => $this->doctor);
    app()->instance('request', $req);
});

it('creates entry and audit row in one transaction', function () {
    $svc = app(MedicalEntryService::class);
    $entry = $svc->create($this->appointment, $this->doctor, [
        'visible_summary' => 'flu, rest',
        'staff_notes' => 'looks anxious',
    ]);

    expect($entry->appointment_id)->toBe($this->appointment->id)
        ->and($entry->author_id)->toBe($this->doctor->id)
        ->and($entry->visible_summary)->toBe('flu, rest');

    $audit = MedicalAuditLog::firstWhere('action', MedicalAuditAction::EntryCreated->value);
    expect($audit)->not->toBeNull()
        ->and($audit->changed_fields)->toEqualCanonicalizing(['visible_summary', 'staff_notes']);
});

it('update writes audit with only dirty fields', function () {
    $svc = app(MedicalEntryService::class);
    $entry = $svc->create($this->appointment, $this->doctor, [
        'visible_summary' => 'initial',
        'staff_notes' => null,
    ]);

    $svc->update($entry, ['visible_summary' => 'updated', 'staff_notes' => null]);

    $audit = MedicalAuditLog::firstWhere('action', MedicalAuditAction::EntryUpdated->value);
    expect($audit->changed_fields)->toBe(['visible_summary']);
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/MedicalEntryServiceTest.php` → FAIL

- [ ] **Step 3: Implement service**

```php
<?php

namespace App\Domain\MedicalRecord\Services;

use App\Enums\MedicalAuditAction;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicalEntryService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(Appointment $appointment, User $author, array $data): MedicalEntry
    {
        return DB::transaction(function () use ($appointment, $author, $data) {
            $entry = MedicalEntry::create([
                'appointment_id' => $appointment->id,
                'author_id' => $author->id,
                'visible_summary' => $data['visible_summary'],
                'staff_notes' => $data['staff_notes'] ?? null,
            ]);
            $this->audit->record(
                MedicalAuditAction::EntryCreated,
                $entry,
                $appointment->customer,
                ['visible_summary', 'staff_notes'],
            );
            return $entry;
        });
    }

    public function update(MedicalEntry $entry, array $data): MedicalEntry
    {
        return DB::transaction(function () use ($entry, $data) {
            $entry->fill($data);
            $dirty = array_keys($entry->getDirty());
            $entry->save();
            if ($dirty !== []) {
                $this->audit->record(
                    MedicalAuditAction::EntryUpdated,
                    $entry,
                    $entry->appointment->customer,
                    $dirty,
                );
            }
            return $entry;
        });
    }
}
```

- [ ] **Step 4: Add `Appointment::factory()->completed()` state** if not already present in `database/factories/AppointmentFactory.php` — verify by running the test; if undefined, add:

```php
public function completed(): self
{
    return $this->state(fn () => ['status' => 'completed']);
}
```

- [ ] **Step 5: Run — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/MedicalEntryServiceTest.php` → PASS

- [ ] **Step 6: Commit**

```powershell
git add app/Domain/MedicalRecord/Services/MedicalEntryService.php tests/Unit/Domain/MedicalRecord/MedicalEntryServiceTest.php database/factories/AppointmentFactory.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add MedicalEntryService with transactional audit" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: PrescriptionService — create / update / delete / diff

**Files:**
- Create: `app/Domain/MedicalRecord/Services/PrescriptionService.php`, `tests/Unit/Domain/MedicalRecord/PrescriptionServiceTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Domain\MedicalRecord\Services\PrescriptionService;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalAuditLog;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $appt = Appointment::factory()->completed()->create(['customer_id' => $this->customer->id]);
    $this->entry = MedicalEntry::factory()->create(['appointment_id' => $appt->id, 'author_id' => $this->doctor->id]);

    $req = Request::create('/');
    $req->setUserResolver(fn () => $this->doctor);
    app()->instance('request', $req);
});

it('diffs prescriptions: creates, updates, deletes', function () {
    $svc = app(PrescriptionService::class);

    $p1 = Prescription::create([
        'medical_entry_id' => $this->entry->id,
        'medication_name' => 'Paracetamol', 'dosage' => '500mg',
        'frequency' => 'twice', 'duration' => '5 days', 'notes' => null,
    ]);

    $svc->syncForEntry($this->entry, [
        ['id' => $p1->id, 'medication_name' => 'Paracetamol', 'dosage' => '500mg',
         'frequency' => 'three times', 'duration' => '5 days', 'notes' => null],
        ['medication_name' => 'Ibuprofen', 'dosage' => '400mg',
         'frequency' => 'once', 'duration' => '3 days', 'notes' => null],
    ]);

    expect($this->entry->prescriptions()->count())->toBe(2)
        ->and($p1->fresh()->frequency)->toBe('three times');

    expect(MedicalAuditLog::where('action', 'prescription.updated')->count())->toBe(1)
        ->and(MedicalAuditLog::where('action', 'prescription.created')->count())->toBe(1);
});

it('deletes prescriptions not present in the desired set', function () {
    $svc = app(PrescriptionService::class);
    $p = Prescription::create([
        'medical_entry_id' => $this->entry->id,
        'medication_name' => 'X', 'dosage' => 'd', 'frequency' => 'f', 'duration' => 'du',
    ]);

    $svc->syncForEntry($this->entry, []);

    expect(Prescription::find($p->id))->toBeNull()
        ->and(MedicalAuditLog::where('action', 'prescription.deleted')->count())->toBe(1);
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/PrescriptionServiceTest.php` → FAIL

- [ ] **Step 3: Implement service**

```php
<?php

namespace App\Domain\MedicalRecord\Services;

use App\Enums\MedicalAuditAction;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class PrescriptionService
{
    public function __construct(private AuditLogger $audit) {}

    public function syncForEntry(MedicalEntry $entry, array $desired): void
    {
        DB::transaction(function () use ($entry, $desired) {
            $existing = $entry->prescriptions()->get()->keyBy('id');
            $keepIds = [];
            foreach ($desired as $row) {
                $row = $this->normalize($row);
                if (isset($row['id']) && $existing->has($row['id'])) {
                    /** @var Prescription $p */
                    $p = $existing[$row['id']];
                    $p->fill($row);
                    $dirty = array_keys($p->getDirty());
                    if ($dirty !== []) {
                        $p->save();
                        $this->audit->record(
                            MedicalAuditAction::PrescriptionUpdated,
                            $p,
                            $entry->appointment->customer,
                            $dirty,
                        );
                    }
                    $keepIds[] = $p->id;
                } else {
                    unset($row['id']);
                    $created = $entry->prescriptions()->create($row);
                    $this->audit->record(
                        MedicalAuditAction::PrescriptionCreated,
                        $created,
                        $entry->appointment->customer,
                        ['medication_name', 'dosage', 'frequency', 'duration', 'notes'],
                    );
                    $keepIds[] = $created->id;
                }
            }
            $toDelete = $existing->keys()->diff($keepIds);
            foreach ($toDelete as $id) {
                /** @var Prescription $p */
                $p = $existing[$id];
                $this->audit->record(
                    MedicalAuditAction::PrescriptionDeleted,
                    $p,
                    $entry->appointment->customer,
                );
                $p->delete();
            }
        });
    }

    private function normalize(array $row): array
    {
        return array_intersect_key(
            $row + ['notes' => null],
            array_flip(['id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes']),
        );
    }
}
```

- [ ] **Step 4: Run — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Domain/MedicalRecord/PrescriptionServiceTest.php` → PASS

- [ ] **Step 5: Commit**

```powershell
git add app/Domain/MedicalRecord/Services/PrescriptionService.php tests/Unit/Domain/MedicalRecord/PrescriptionServiceTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add PrescriptionService with diff sync" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Reusable Data Table foundation components

**Files:**
- Create: 5 components under `resources/js/Components/foundation/` and `resources/js/Components/foundation/__tests__/DataTable.spec.js`

- [ ] **Step 1: Write `DataTable.vue`**

```vue
<script setup>
import { ref } from 'vue'
import {
  FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel,
  getSortedRowModel, useVueTable,
} from '@tanstack/vue-table'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table'
import { valueUpdater } from '@/Components/ui/table/utils'
import DataTablePagination from './DataTablePagination.vue'
import DataTableViewOptions from './DataTableViewOptions.vue'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, required: true },
  filterColumn: { type: String, default: null },
  filterPlaceholder: { type: String, default: 'بحث…' },
  emptyText: { type: String, default: 'لا توجد سجلات.' },
  serverMeta: { type: Object, default: null },
  onPageChange: { type: Function, default: null },
})

const sorting = ref([])
const columnFilters = ref([])
const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: props.serverMeta ? undefined : getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: (u) => valueUpdater(u, sorting),
  onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
  onColumnVisibilityChange: (u) => valueUpdater(u, columnVisibility),
  onRowSelectionChange: (u) => valueUpdater(u, rowSelection),
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
})

defineExpose({ table })
</script>

<template>
  <div>
    <div class="flex items-center py-4 gap-2">
      <Input
        v-if="filterColumn"
        class="max-w-sm"
        :placeholder="filterPlaceholder"
        :model-value="table.getColumn(filterColumn)?.getFilterValue() ?? ''"
        @update:model-value="(v) => table.getColumn(filterColumn)?.setFilterValue(v)"
      />
      <DataTableViewOptions :table="table" class="ms-auto" />
    </div>
    <div class="border rounded-md">
      <Table>
        <TableHeader>
          <TableRow v-for="hg in table.getHeaderGroups()" :key="hg.id">
            <TableHead v-for="h in hg.headers" :key="h.id">
              <FlexRender v-if="!h.isPlaceholder" :render="h.column.columnDef.header" :props="h.getContext()" />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="table.getRowModel().rows?.length">
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
            >
              <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <template v-else>
            <TableRow>
              <TableCell :colSpan="columns.length" class="h-24 text-center">{{ emptyText }}</TableCell>
            </TableRow>
          </template>
        </TableBody>
      </Table>
    </div>
    <DataTablePagination :table="table" :server-meta="serverMeta" :on-page-change="onPageChange" />
  </div>
</template>
```

- [ ] **Step 2: Write `DataTablePagination.vue`**

```vue
<script setup>
import { Button } from '@/Components/ui/button'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import { ChevronsRight, ChevronsLeft, ChevronRight, ChevronLeft } from 'lucide-vue-next'

const props = defineProps({
  table: { type: Object, required: true },
  serverMeta: { type: Object, default: null },
  onPageChange: { type: Function, default: null },
})

function go(page) {
  if (props.serverMeta && props.onPageChange) {
    props.onPageChange(page)
  } else {
    props.table.setPageIndex(page - 1)
  }
}

function pageInfo() {
  if (props.serverMeta) {
    return { current: props.serverMeta.current_page, last: props.serverMeta.last_page }
  }
  return {
    current: props.table.getState().pagination.pageIndex + 1,
    last: props.table.getPageCount(),
  }
}
</script>

<template>
  <div class="flex items-center justify-between px-2 py-4">
    <div class="flex-1 text-sm text-muted-foreground">
      {{ table.getFilteredSelectedRowModel().rows.length }} من
      {{ serverMeta?.total ?? table.getFilteredRowModel().rows.length }} مُحدَّد.
    </div>
    <div class="flex items-center gap-6">
      <div class="flex items-center gap-2" v-if="!serverMeta">
        <p class="text-sm font-medium">صفوف لكل صفحة</p>
        <Select
          :model-value="`${table.getState().pagination.pageSize}`"
          @update:model-value="(v) => table.setPageSize(Number(v))"
        >
          <SelectTrigger class="h-8 w-[70px]">
            <SelectValue :placeholder="`${table.getState().pagination.pageSize}`" />
          </SelectTrigger>
          <SelectContent side="top">
            <SelectItem v-for="n in [10, 20, 30, 50]" :key="n" :value="`${n}`">{{ n }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="text-sm font-medium">صفحة {{ pageInfo().current }} من {{ pageInfo().last }}</div>
      <div class="flex items-center gap-1">
        <Button variant="outline" size="icon" :disabled="pageInfo().current === 1" @click="go(1)">
          <ChevronsRight class="size-4 rtl:hidden" /><ChevronsLeft class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === 1" @click="go(pageInfo().current - 1)">
          <ChevronRight class="size-4 rtl:hidden" /><ChevronLeft class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().current + 1)">
          <ChevronLeft class="size-4 rtl:hidden" /><ChevronRight class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().last)">
          <ChevronsLeft class="size-4 rtl:hidden" /><ChevronsRight class="size-4 hidden rtl:block" />
        </Button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 3: Write `DataTableColumnHeader.vue`**

```vue
<script setup>
import { ArrowUpDown, ArrowDown, ArrowUp } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'

defineProps({
  column: { type: Object, required: true },
  title: { type: String, required: true },
})
</script>

<template>
  <Button v-if="column.getCanSort()" variant="ghost" class="-mx-3" @click="column.toggleSorting(column.getIsSorted() === 'asc')">
    {{ title }}
    <ArrowUp v-if="column.getIsSorted() === 'asc'" class="size-4 ms-2" />
    <ArrowDown v-else-if="column.getIsSorted() === 'desc'" class="size-4 ms-2" />
    <ArrowUpDown v-else class="size-4 ms-2 opacity-50" />
  </Button>
  <span v-else>{{ title }}</span>
</template>
```

- [ ] **Step 4: Write `DataTableViewOptions.vue`**

```vue
<script setup>
import {
  DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Settings2 } from 'lucide-vue-next'

defineProps({ table: { type: Object, required: true } })
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" size="sm" class="h-8">
        <Settings2 class="size-4 me-2" /> أعمدة
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[180px]">
      <DropdownMenuLabel>إظهار/إخفاء</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuCheckboxItem
        v-for="col in table.getAllColumns().filter((c) => c.getCanHide())"
        :key="col.id"
        :model-value="col.getIsVisible()"
        @update:model-value="(v) => col.toggleVisibility(!!v)"
      >
        {{ col.columnDef.meta?.label ?? col.id }}
      </DropdownMenuCheckboxItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
```

- [ ] **Step 5: Write `DataTableRowActions.vue`**

```vue
<script setup>
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { MoreHorizontal } from 'lucide-vue-next'

defineProps({ label: { type: String, default: 'إجراءات' } })
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="size-8">
        <span class="sr-only">{{ label }}</span>
        <MoreHorizontal class="size-4" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">
      <DropdownMenuLabel>{{ label }}</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <slot />
    </DropdownMenuContent>
  </DropdownMenu>
</template>
```

- [ ] **Step 6: Write a Vitest smoke test**

```js
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { h } from 'vue'
import DataTable from '../DataTable.vue'

describe('DataTable', () => {
  it('renders rows and supports column visibility toggling', async () => {
    const columns = [
      { accessorKey: 'name', header: 'Name', meta: { label: 'Name' } },
      { accessorKey: 'role', header: 'Role', meta: { label: 'Role' } },
    ]
    const data = [
      { name: 'Alice', role: 'Doctor' },
      { name: 'Bob', role: 'Manager' },
    ]
    const wrapper = mount(DataTable, { props: { columns, data } })
    expect(wrapper.text()).toContain('Alice')
    expect(wrapper.text()).toContain('Bob')
  })

  it('shows empty state when data is empty', () => {
    const wrapper = mount(DataTable, {
      props: { columns: [{ accessorKey: 'x', header: 'x', meta: { label: 'x' } }], data: [], emptyText: 'فارغ' },
    })
    expect(wrapper.text()).toContain('فارغ')
  })
})
```

- [ ] **Step 7: Run the test**

Run: `npm run test:js -- DataTable` → PASS

- [ ] **Step 8: Add a barrel export**

Modify (or create) `resources/js/Components/foundation/index.js` — add the five new components to its exports following the existing pattern in that file.

- [ ] **Step 9: Commit**

```powershell
git add resources/js/Components/foundation/DataTable.vue resources/js/Components/foundation/DataTablePagination.vue resources/js/Components/foundation/DataTableColumnHeader.vue resources/js/Components/foundation/DataTableViewOptions.vue resources/js/Components/foundation/DataTableRowActions.vue resources/js/Components/foundation/__tests__/DataTable.spec.js resources/js/Components/foundation/index.js
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add reusable shadcn-vue DataTable foundation components" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Policies — MedicalEntryPolicy + PrescriptionPolicy

**Files:**
- Create: `app/Policies/MedicalEntryPolicy.php`, `app/Policies/PrescriptionPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php` (or wherever policies register — confirm by `grep "'policies'"` in `app/Providers/`)

- [ ] **Step 1: Implement MedicalEntryPolicy**

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\User;

class MedicalEntryPolicy
{
    public function view(User $user, MedicalEntry $entry): bool
    {
        if ($user->role === UserRole::Customer) {
            return $entry->appointment->customer_id === $user->id;
        }
        if ($user->role === UserRole::Receptionist) {
            return false;
        }
        return in_array($user->role, [UserRole::Manager, UserRole::Doctor], true);
    }

    public function create(User $user, Appointment $appointment): bool
    {
        if ($user->role !== UserRole::Doctor) {
            return false;
        }
        return $appointment->doctor_profile_id === $user->doctorProfile?->id
            && $appointment->status === 'completed';
    }

    public function update(User $user, MedicalEntry $entry): bool
    {
        return $user->role === UserRole::Doctor && $entry->author_id === $user->id;
    }
}
```

- [ ] **Step 2: Implement PrescriptionPolicy**

```php
<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    public function __construct(private MedicalEntryPolicy $entryPolicy) {}

    public function create(User $user, Prescription $prescription): bool
    {
        return $this->entryPolicy->update($user, $prescription->entry);
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $this->entryPolicy->update($user, $prescription->entry);
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return $this->entryPolicy->update($user, $prescription->entry);
    }
}
```

- [ ] **Step 3: Register policies**

In the relevant provider (check `app/Providers/AppServiceProvider.php` `boot()` or `AuthServiceProvider.php` `$policies` array — follow whichever convention the project already uses):

```php
\App\Models\MedicalEntry::class => \App\Policies\MedicalEntryPolicy::class,
\App\Models\Prescription::class => \App\Policies\PrescriptionPolicy::class,
```

- [ ] **Step 4: Run static analysis**

Run: `./vendor/bin/phpstan analyse app/Policies/MedicalEntryPolicy.php app/Policies/PrescriptionPolicy.php` → PASS

- [ ] **Step 5: Commit**

```powershell
git add app/Policies/MedicalEntryPolicy.php app/Policies/PrescriptionPolicy.php app/Providers/AppServiceProvider.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add MedicalEntry + Prescription policies (doctor write, receptionist blocked)" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Admin\MedicalEntryController + routes + feature tests

**Files:**
- Create: `app/Http/Controllers/Admin/MedicalEntryController.php`, `tests/Feature/Admin/MedicalEntryControllerTest.php`
- Modify: `routes/web.php`, `tests/Feature/RouteNamesTest.php`

- [ ] **Step 1: Write failing feature tests**

```php
<?php

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Models\MedicalEntry;
use App\Models\DoctorProfile;

beforeEach(function () {
    $this->doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $this->doctorProfile = DoctorProfile::factory()->create(['user_id' => $this->doctorUser->id]);
    $this->customer = User::factory()->create(['role' => UserRole::Customer]);
    $this->appt = Appointment::factory()->completed()->create([
        'customer_id' => $this->customer->id,
        'doctor_profile_id' => $this->doctorProfile->id,
    ]);
});

it('assigned doctor can create an entry with prescriptions', function () {
    $resp = $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'flu',
        'staff_notes' => 'anxious',
        'prescriptions' => [
            ['medication_name' => 'Para', 'dosage' => '500mg', 'frequency' => 'twice', 'duration' => '5d', 'notes' => null],
        ],
    ]);

    $resp->assertRedirect()->assertSessionHasNoErrors();
    expect(MedicalEntry::where('appointment_id', $this->appt->id)->exists())->toBeTrue();
});

it('unassigned doctor gets 403', function () {
    $other = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $other->id]);

    $this->actingAs($other)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('receptionist gets 403', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $this->actingAs($r)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('customer gets 403', function () {
    $this->actingAs($this->customer)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => 'x',
    ])->assertForbidden();
});

it('empty visible_summary returns 422', function () {
    $this->actingAs($this->doctorUser)->post("/admin/appointments/{$this->appt->id}/medical-entry", [
        'visible_summary' => '',
    ])->assertSessionHasErrors('visible_summary');
});
```

- [ ] **Step 2: Run — expect FAIL (no routes / no controller)**

Run: `./vendor/bin/pest tests/Feature/Admin/MedicalEntryControllerTest.php` → FAIL

- [ ] **Step 3: Implement controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Domain\MedicalRecord\Services\MedicalEntryService;
use App\Domain\MedicalRecord\Services\PrescriptionService;
use App\Enums\MedicalAuditAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicalEntryController extends Controller
{
    public function store(
        Request $request,
        Appointment $appointment,
        MedicalEntryService $entries,
        PrescriptionService $prescriptions,
    ) {
        $this->authorize('create', [MedicalEntry::class, $appointment]);

        $data = $request->validate([
            'visible_summary' => 'required|string',
            'staff_notes' => 'nullable|string',
            'prescriptions' => 'array',
            'prescriptions.*.medication_name' => 'required|string|max:255',
            'prescriptions.*.dosage' => 'required|string|max:255',
            'prescriptions.*.frequency' => 'required|string|max:255',
            'prescriptions.*.duration' => 'required|string|max:255',
            'prescriptions.*.notes' => 'nullable|string',
        ]);

        $entry = $entries->create($appointment, $request->user(), $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return redirect()->route('admin.appointments.show', $appointment)
            ->with('success', 'تم حفظ السجل الطبي.');
    }

    public function edit(MedicalEntry $entry, AuditLogger $audit): Response
    {
        $this->authorize('view', $entry);
        $audit->record(MedicalAuditAction::EntryViewed, $entry, $entry->appointment->customer);

        return Inertia::render('Admin/MedicalEntries/Edit', [
            'entry' => $entry->only(['id', 'appointment_id', 'visible_summary', 'staff_notes']),
            'prescriptions' => $entry->prescriptions->map->only([
                'id', 'medication_name', 'dosage', 'frequency', 'duration', 'notes',
            ]),
            'appointment' => $entry->appointment->only(['id', 'start_at']),
            'customer' => [
                'id' => $entry->appointment->customer->id,
                'name' => $entry->appointment->customer->name,
            ],
        ]);
    }

    public function update(
        Request $request,
        MedicalEntry $entry,
        MedicalEntryService $entries,
        PrescriptionService $prescriptions,
    ) {
        $this->authorize('update', $entry);

        $data = $request->validate([
            'visible_summary' => 'required|string',
            'staff_notes' => 'nullable|string',
            'prescriptions' => 'array',
            'prescriptions.*.id' => 'nullable|integer|exists:prescriptions,id',
            'prescriptions.*.medication_name' => 'required|string|max:255',
            'prescriptions.*.dosage' => 'required|string|max:255',
            'prescriptions.*.frequency' => 'required|string|max:255',
            'prescriptions.*.duration' => 'required|string|max:255',
            'prescriptions.*.notes' => 'nullable|string',
        ]);

        $entries->update($entry, $data);
        $prescriptions->syncForEntry($entry, $data['prescriptions'] ?? []);

        return redirect()->route('admin.appointments.show', $entry->appointment)
            ->with('success', 'تم تحديث السجل الطبي.');
    }
}
```

- [ ] **Step 4: Wire routes**

In `routes/web.php`, inside the staff/admin route group (follow existing pattern; group already provides `admin.` prefix and `staff` middleware):

```php
Route::post('appointments/{appointment}/medical-entry', [Admin\MedicalEntryController::class, 'store'])
    ->name('appointments.medical-entry.store');
Route::get('medical-entries/{entry}/edit', [Admin\MedicalEntryController::class, 'edit'])
    ->name('medical-entries.edit');
Route::put('medical-entries/{entry}', [Admin\MedicalEntryController::class, 'update'])
    ->name('medical-entries.update');
```

The route-model binding for `{entry}` is `MedicalEntry` (default by name); confirm by looking at how other controllers use route binding in this project.

- [ ] **Step 5: Update RouteNamesTest**

Add to the canonical-names array in `tests/Feature/RouteNamesTest.php`:

```php
'admin.appointments.medical-entry.store',
'admin.medical-entries.edit',
'admin.medical-entries.update',
```

- [ ] **Step 6: Run feature + RouteNamesTest — expect PASS**

Run: `./vendor/bin/pest tests/Feature/Admin/MedicalEntryControllerTest.php tests/Feature/RouteNamesTest.php` → PASS

- [ ] **Step 7: Commit**

```powershell
git add app/Http/Controllers/Admin/MedicalEntryController.php routes/web.php tests/Feature/Admin/MedicalEntryControllerTest.php tests/Feature/RouteNamesTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add Admin MedicalEntryController + routes + auth tests" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Admin\CustomerController updateMedicalProfile + route + test

**Files:**
- Modify: `app/Http/Controllers/Admin/CustomerController.php`, `routes/web.php`, `tests/Feature/RouteNamesTest.php`
- Create: `tests/Feature/Admin/CustomerMedicalProfileTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

it('doctor updates customer medical profile and writes audit', function () {
    $doc = User::factory()->create(['role' => UserRole::Doctor]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->create(['user_id' => $customer->id]);

    $resp = $this->actingAs($doc)->put("/admin/customers/{$customer->id}/profile/medical", [
        'chronic_conditions' => 'diabetes type 2',
        'allergies' => 'penicillin',
    ]);

    $resp->assertRedirect()->assertSessionHasNoErrors();
    expect($customer->profile->fresh()->chronic_conditions)->toBe('diabetes type 2');
});

it('receptionist cannot update medical profile', function () {
    $r = User::factory()->create(['role' => UserRole::Receptionist]);
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($r)->put("/admin/customers/{$customer->id}/profile/medical", [
        'chronic_conditions' => 'x',
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Feature/Admin/CustomerMedicalProfileTest.php` → FAIL

- [ ] **Step 3: Implement controller method**

Read `app/Http/Controllers/Admin/CustomerController.php` first, then add:

```php
use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\MedicalAuditAction;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

public function updateMedicalProfile(Request $request, User $customer, AuditLogger $audit)
{
    abort_unless(in_array($request->user()->role, [UserRole::Manager, UserRole::Doctor], true), 403);

    $data = $request->validate([
        'chronic_conditions' => 'nullable|string|max:5000',
        'allergies' => 'nullable|string|max:5000',
    ]);

    DB::transaction(function () use ($customer, $data, $audit) {
        $profile = $customer->profile;
        $profile->fill($data);
        $dirty = array_keys($profile->getDirty());
        $profile->save();
        if ($dirty !== []) {
            $audit->record(MedicalAuditAction::ProfileMedicalUpdated, $profile, $customer, $dirty);
        }
    });

    return back()->with('success', 'تم تحديث الملف الطبي.');
}
```

- [ ] **Step 4: Wire route**

In `routes/web.php` admin group:

```php
Route::put('customers/{customer}/profile/medical', [Admin\CustomerController::class, 'updateMedicalProfile'])
    ->name('customers.profile.medical.update');
```

- [ ] **Step 5: Update RouteNamesTest**

Add `'admin.customers.profile.medical.update'` to the canonical list.

- [ ] **Step 6: Run — expect PASS**

Run: `./vendor/bin/pest tests/Feature/Admin/CustomerMedicalProfileTest.php tests/Feature/RouteNamesTest.php` → PASS

- [ ] **Step 7: Commit**

```powershell
git add app/Http/Controllers/Admin/CustomerController.php routes/web.php tests/Feature/Admin/CustomerMedicalProfileTest.php tests/Feature/RouteNamesTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add Admin updateMedicalProfile for chronic/allergies" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Extend Admin/Appointments/Show.vue with medical-entry section

**Files:**
- Modify: `app/Http/Controllers/Admin/AppointmentController.php` (show method), `resources/js/Pages/Admin/Appointments/Show.vue`

- [ ] **Step 1: Read both files first**

Read each file before editing — capture the existing props shape so the extension is additive.

- [ ] **Step 2: Add entry+role props to the controller's `show` payload**

In `Admin\AppointmentController::show`, append to the Inertia response:

```php
'medicalEntry' => $appointment->medicalEntry?->only(['id', 'visible_summary']),
'canWriteEntry' => $request->user()->can('create', [\App\Models\MedicalEntry::class, $appointment]),
```

(Add `public function medicalEntry(): HasOne { return $this->hasOne(MedicalEntry::class); }` to `App\Models\Appointment` if not present.)

- [ ] **Step 3: Add the section to the Vue page**

In `resources/js/Pages/Admin/Appointments/Show.vue` template, inside the main content area:

```vue
<section v-if="appointment.status === 'completed'" class="border rounded-md p-4">
  <header class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-semibold">السجل الطبي</h2>
    <Link
      v-if="medicalEntry"
      :href="route('admin.medical-entries.edit', medicalEntry.id)"
      class="text-sm underline"
    >تعديل</Link>
    <Link
      v-else-if="canWriteEntry"
      :href="route('admin.medical-entries.create', { appointment: appointment.id })"
      class="text-sm underline"
    >إضافة سجل</Link>
  </header>
  <p v-if="medicalEntry" class="text-sm whitespace-pre-line">{{ medicalEntry.visible_summary }}</p>
  <p v-else class="text-sm text-muted-foreground">لا يوجد سجل بعد.</p>
</section>
```

For the "إضافة سجل" case, since there is no GET create route in P3, route the link to the existing edit page after a POST-then-redirect — or, simpler, add a new GET route that resolves-or-creates a draft entry. Re-examine and pick whichever existing pattern the project follows for create-flows. If unsure, add:

```php
Route::get('appointments/{appointment}/medical-entry/create', function (Appointment $appointment) {
    abort_unless(auth()->user()->can('create', [MedicalEntry::class, $appointment]), 403);
    $entry = MedicalEntry::firstOrCreate(
        ['appointment_id' => $appointment->id],
        ['author_id' => auth()->id(), 'visible_summary' => ''],
    );
    return redirect()->route('admin.medical-entries.edit', $entry);
})->name('appointments.medical-entry.create');
```

Add the name `'admin.appointments.medical-entry.create'` to `RouteNamesTest`.

- [ ] **Step 4: Run feature suite touching appointments**

Run: `./vendor/bin/pest tests/Feature/Admin/Appointments` → PASS

- [ ] **Step 5: Commit**

```powershell
git add app/Models/Appointment.php app/Http/Controllers/Admin/AppointmentController.php resources/js/Pages/Admin/Appointments/Show.vue routes/web.php tests/Feature/RouteNamesTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): surface medical entry section on Admin Appointment Show" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Extend Admin/Customers/Show.vue with DataTable + medical profile form

**Files:**
- Modify: `app/Http/Controllers/Admin/CustomerController.php` (show method), `resources/js/Pages/Admin/Customers/Show.vue`

- [ ] **Step 1: Augment controller show payload**

```php
$entries = MedicalEntry::query()
    ->whereHas('appointment', fn ($q) => $q->where('customer_id', $customer->id))
    ->with(['appointment:id,start_at,doctor_profile_id', 'prescriptions'])
    ->latest('created_at')
    ->paginate(20);

return Inertia::render('Admin/Customers/Show', [
    // ... existing props ...
    'medicalProfile' => $customer->profile->only(['chronic_conditions', 'allergies']),
    'medicalEntries' => $entries->through(fn ($e) => [
        'id' => $e->id,
        'date' => $e->appointment->start_at->toIso8601String(),
        'visible_summary' => $e->visible_summary,
        'prescriptions_count' => $e->prescriptions->count(),
    ]),
    'canEditMedicalProfile' => in_array($request->user()->role, [UserRole::Manager, UserRole::Doctor], true),
]);
```

Record audit views appropriately in the controller (one `profile_medical.viewed` if profile fields are non-null).

- [ ] **Step 2: Update the Vue page**

Add (template, scaled & elided to focus changes):

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import DataTable from '@/Components/foundation/DataTable.vue'
import DataTableColumnHeader from '@/Components/foundation/DataTableColumnHeader.vue'
import DataTableRowActions from '@/Components/foundation/DataTableRowActions.vue'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Button } from '@/Components/ui/button'
import { h } from 'vue'

const props = defineProps({
  customer: Object,
  medicalProfile: Object,
  medicalEntries: Object,
  canEditMedicalProfile: Boolean,
})

const medForm = useForm({
  chronic_conditions: props.medicalProfile.chronic_conditions ?? '',
  allergies: props.medicalProfile.allergies ?? '',
})

function saveMedical() {
  medForm.put(route('admin.customers.profile.medical.update', props.customer.id))
}

const entryColumns = [
  {
    accessorKey: 'date',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'التاريخ' }),
    cell: ({ row }) => new Date(row.original.date).toLocaleDateString('ar'),
    meta: { label: 'التاريخ' },
  },
  {
    accessorKey: 'visible_summary',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'الخلاصة' }),
    cell: ({ row }) => row.original.visible_summary.slice(0, 80) + (row.original.visible_summary.length > 80 ? '…' : ''),
    meta: { label: 'الخلاصة' },
  },
  {
    accessorKey: 'prescriptions_count',
    header: ({ column }) => h(DataTableColumnHeader, { column, title: 'وصفات' }),
    meta: { label: 'وصفات' },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => h(DataTableRowActions, null, {
      default: () => h(DropdownMenuItem, {
        onClick: () => router.visit(route('admin.medical-entries.edit', row.original.id)),
      }, 'فتح'),
    }),
  },
]
</script>

<template>
  <!-- ... existing markup ... -->

  <section class="border rounded-md p-4">
    <h2 class="text-lg font-semibold mb-3">الملف الطبي</h2>
    <form v-if="canEditMedicalProfile" class="space-y-3" @submit.prevent="saveMedical">
      <div>
        <label class="block text-sm font-medium mb-1">الأمراض المزمنة</label>
        <Textarea v-model="medForm.chronic_conditions" rows="3" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">الحساسية</label>
        <Textarea v-model="medForm.allergies" rows="3" />
      </div>
      <div class="flex justify-end">
        <Button :disabled="medForm.processing">حفظ</Button>
      </div>
    </form>
    <dl v-else class="text-sm">
      <dt class="text-muted-foreground">الأمراض المزمنة</dt><dd class="mb-2">{{ medicalProfile.chronic_conditions || '—' }}</dd>
      <dt class="text-muted-foreground">الحساسية</dt><dd>{{ medicalProfile.allergies || '—' }}</dd>
    </dl>
  </section>

  <section class="border rounded-md p-4">
    <h2 class="text-lg font-semibold mb-3">السجل الطبي للعميل</h2>
    <DataTable
      :columns="entryColumns"
      :data="medicalEntries.data"
      filter-column="visible_summary"
      filter-placeholder="ابحث في الخلاصات…"
      :server-meta="medicalEntries.meta"
      :on-page-change="(p) => router.get(route('admin.customers.show', customer.id), { page: p }, { preserveState: true })"
      empty-text="لا توجد سجلات."
    />
  </section>
</template>
```

- [ ] **Step 3: Run the dev server in another shell and manually verify the page**

Run: `npm run dev` (in another shell) + `php artisan serve --port=8000`; log in as a doctor, visit `/admin/customers/{id}`. Confirm sort + filter + visibility + pagination + row actions all render.

- [ ] **Step 4: Run tests touching customers**

Run: `./vendor/bin/pest tests/Feature/Admin/Customer` → PASS

- [ ] **Step 5: Commit**

```powershell
git add app/Http/Controllers/Admin/CustomerController.php resources/js/Pages/Admin/Customers/Show.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): Admin Customer Show — medical profile form + entries DataTable" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: Admin/MedicalEntries/Edit.vue form

**Files:**
- Create: `resources/js/Pages/Admin/MedicalEntries/Edit.vue`

- [ ] **Step 1: Implement the form**

```vue
<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { Textarea } from '@/Components/ui/textarea'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Trash2, Plus } from 'lucide-vue-next'

const props = defineProps({
  entry: Object,
  prescriptions: Array,
  appointment: Object,
  customer: Object,
})

const form = useForm({
  visible_summary: props.entry.visible_summary,
  staff_notes: props.entry.staff_notes ?? '',
  prescriptions: props.prescriptions.map((p) => ({ ...p })),
})

function addPrescription() {
  form.prescriptions.push({ medication_name: '', dosage: '', frequency: '', duration: '', notes: '' })
}
function removePrescription(i) {
  form.prescriptions.splice(i, 1)
}
function save() {
  form.put(route('admin.medical-entries.update', props.entry.id))
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6 max-w-3xl">
      <header>
        <h1 class="text-xl font-semibold">السجل الطبي — {{ customer.name }}</h1>
        <p class="text-sm text-muted-foreground">موعد بتاريخ {{ new Date(appointment.start_at).toLocaleDateString('ar') }}</p>
      </header>

      <form class="space-y-6" @submit.prevent="save">
        <div>
          <label class="block text-sm font-medium mb-1">الخلاصة (يراها العميل)</label>
          <Textarea v-model="form.visible_summary" rows="4" />
          <p v-if="form.errors.visible_summary" class="text-sm text-destructive mt-1">{{ form.errors.visible_summary }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">ملاحظات داخلية (للطاقم فقط)</label>
          <Textarea v-model="form.staff_notes" rows="3" />
        </div>

        <fieldset class="border rounded-md p-4">
          <legend class="text-sm font-medium px-2">الوصفات الطبية</legend>
          <div v-for="(p, i) in form.prescriptions" :key="i" class="grid grid-cols-1 md:grid-cols-2 gap-3 py-2 border-b last:border-b-0">
            <Input v-model="p.medication_name" placeholder="الدواء" />
            <Input v-model="p.dosage" placeholder="الجرعة" />
            <Input v-model="p.frequency" placeholder="التكرار" />
            <Input v-model="p.duration" placeholder="المدّة" />
            <Input v-model="p.notes" placeholder="ملاحظات (اختياري)" class="md:col-span-2" />
            <div class="md:col-span-2 flex justify-end">
              <Button type="button" variant="ghost" @click="removePrescription(i)">
                <Trash2 class="size-4 me-1" /> حذف
              </Button>
            </div>
          </div>
          <div class="pt-3">
            <Button type="button" variant="outline" @click="addPrescription">
              <Plus class="size-4 me-1" /> إضافة وصفة
            </Button>
          </div>
        </fieldset>

        <div class="flex justify-end gap-2">
          <Link :href="route('admin.appointments.show', appointment.id)" class="text-sm underline">إلغاء</Link>
          <Button :disabled="form.processing">حفظ</Button>
        </div>
      </form>
    </div>
  </AdminShell>
</template>
```

- [ ] **Step 2: Manually verify in the browser**

(Same dev server as Task 12.) Walk through: create entry on a completed appointment, add 2 prescriptions, save, return to Appointment Show — section now reflects entry; back to Edit — prescriptions present.

- [ ] **Step 3: Commit**

```powershell
git add resources/js/Pages/Admin/MedicalEntries/Edit.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add Admin Medical Entry edit form with dynamic prescriptions" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: Portal\MedicalRecordController + routes + tests (staff_notes absence)

**Files:**
- Create: `app/Http/Controllers/Portal/MedicalRecordController.php`, `tests/Feature/Portal/MedicalRecordControllerTest.php`
- Modify: `routes/web.php`, `tests/Feature/RouteNamesTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use App\Models\User;

it('customer sees own entries; staff_notes never in payload', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->create(['user_id' => $customer->id, 'chronic_conditions' => 'asthma']);
    $appt = Appointment::factory()->completed()->create(['customer_id' => $customer->id]);
    $entry = MedicalEntry::factory()->create([
        'appointment_id' => $appt->id,
        'visible_summary' => 'flu, rest',
        'staff_notes' => 'SECRET ANXIETY NOTE',
    ]);
    Prescription::create([
        'medical_entry_id' => $entry->id,
        'medication_name' => 'Para', 'dosage' => '500mg',
        'frequency' => 'twice', 'duration' => '5d',
    ]);

    $resp = $this->actingAs($customer)->get('/portal/medical-record')->assertOk();
    $payload = json_encode($resp->viewData('page')['props']);

    expect($payload)->toContain('flu, rest')
        ->and($payload)->toContain('Para')
        ->and($payload)->not->toContain('SECRET ANXIETY NOTE');
});

it('another customer cannot see this record', function () {
    $a = User::factory()->create(['role' => UserRole::Customer]);
    $b = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->create(['user_id' => $a->id]);
    $appt = Appointment::factory()->completed()->create(['customer_id' => $a->id]);
    $entry = MedicalEntry::factory()->create(['appointment_id' => $appt->id]);

    $this->actingAs($b)->get("/portal/medical-record/entries/{$entry->id}")->assertNotFound();
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Feature/Portal/MedicalRecordControllerTest.php` → FAIL

- [ ] **Step 3: Implement controller**

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Domain\MedicalRecord\Services\AuditLogger;
use App\Enums\MedicalAuditAction;
use App\Http\Controllers\Controller;
use App\Models\MedicalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicalRecordController extends Controller
{
    public function index(Request $request, AuditLogger $audit): Response
    {
        $user = $request->user();
        $profile = $user->profile;
        $entries = MedicalEntry::query()
            ->whereHas('appointment', fn ($q) => $q->where('customer_id', $user->id))
            ->with(['appointment:id,start_at', 'prescriptions'])
            ->latest()
            ->paginate(20);

        if ($profile && ($profile->chronic_conditions || $profile->allergies)) {
            $audit->record(MedicalAuditAction::ProfileMedicalViewed, $profile, $user);
        }

        return Inertia::render('Portal/MedicalRecord/Index', [
            'medicalProfile' => [
                'chronic_conditions' => $profile?->chronic_conditions,
                'allergies' => $profile?->allergies,
            ],
            'entries' => $entries->through(fn ($e) => [
                'id' => $e->id,
                'date' => $e->appointment->start_at->toIso8601String(),
                'visible_summary' => $e->visible_summary,
                'prescriptions' => $e->prescriptions->map->only([
                    'medication_name', 'dosage', 'frequency', 'duration', 'notes',
                ]),
            ]),
        ]);
    }

    public function show(MedicalEntry $entry, Request $request, AuditLogger $audit): Response
    {
        if ($entry->appointment->customer_id !== $request->user()->id) {
            abort(404);
        }
        $audit->record(MedicalAuditAction::EntryViewed, $entry, $request->user());

        return Inertia::render('Portal/MedicalRecord/Show', [
            'entry' => [
                'id' => $entry->id,
                'date' => $entry->appointment->start_at->toIso8601String(),
                'visible_summary' => $entry->visible_summary,
                'prescriptions' => $entry->prescriptions->map->only([
                    'medication_name', 'dosage', 'frequency', 'duration', 'notes',
                ]),
            ],
        ]);
    }
}
```

- [ ] **Step 4: Wire routes**

In `routes/web.php` portal/customer group:

```php
Route::get('medical-record', [Portal\MedicalRecordController::class, 'index'])->name('medical-record.index');
Route::get('medical-record/entries/{entry}', [Portal\MedicalRecordController::class, 'show'])->name('medical-record.show');
```

Add names to RouteNamesTest: `portal.medical-record.index`, `portal.medical-record.show`.

- [ ] **Step 5: Run — expect PASS**

Run: `./vendor/bin/pest tests/Feature/Portal/MedicalRecordControllerTest.php tests/Feature/RouteNamesTest.php` → PASS

- [ ] **Step 6: Commit**

```powershell
git add app/Http/Controllers/Portal/MedicalRecordController.php routes/web.php tests/Feature/Portal/MedicalRecordControllerTest.php tests/Feature/RouteNamesTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add Portal MedicalRecordController with staff_notes exclusion test" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 15: Portal Vue pages + sidebar item

**Files:**
- Create: `resources/js/Pages/Portal/MedicalRecord/Index.vue`, `resources/js/Pages/Portal/MedicalRecord/Show.vue`
- Modify: `resources/js/Layouts/PortalShell.vue`

- [ ] **Step 1: Build `Portal/MedicalRecord/Index.vue`**

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
import PortalShell from '@/Layouts/PortalShell.vue'

defineProps({ medicalProfile: Object, entries: Object })
</script>

<template>
  <PortalShell>
    <div class="p-6 space-y-6">
      <h1 class="text-xl font-semibold">السجل الطبي</h1>

      <section v-if="medicalProfile.chronic_conditions || medicalProfile.allergies" class="border rounded-md p-4">
        <h2 class="text-sm font-medium text-muted-foreground mb-2">معلومات صحية ثابتة</h2>
        <dl class="text-sm space-y-1">
          <div v-if="medicalProfile.chronic_conditions"><dt class="inline font-medium">الأمراض المزمنة:</dt><dd class="inline ms-1">{{ medicalProfile.chronic_conditions }}</dd></div>
          <div v-if="medicalProfile.allergies"><dt class="inline font-medium">الحساسية:</dt><dd class="inline ms-1">{{ medicalProfile.allergies }}</dd></div>
        </dl>
      </section>

      <section class="space-y-3">
        <h2 class="text-sm font-medium text-muted-foreground">الزيارات السابقة</h2>
        <article v-for="e in entries.data" :key="e.id" class="border rounded-md p-4">
          <header class="flex justify-between mb-2">
            <time class="text-sm text-muted-foreground">{{ new Date(e.date).toLocaleDateString('ar') }}</time>
            <Link :href="route('portal.medical-record.show', e.id)" class="text-sm underline">عرض</Link>
          </header>
          <p class="text-sm whitespace-pre-line">{{ e.visible_summary }}</p>
          <ul v-if="e.prescriptions.length" class="mt-2 text-sm list-disc list-inside">
            <li v-for="(p, i) in e.prescriptions" :key="i">{{ p.medication_name }} — {{ p.dosage }} · {{ p.frequency }} · {{ p.duration }}</li>
          </ul>
        </article>
        <p v-if="!entries.data.length" class="text-sm text-muted-foreground">لا توجد سجلات.</p>
      </section>
    </div>
  </PortalShell>
</template>
```

- [ ] **Step 2: Build `Portal/MedicalRecord/Show.vue`**

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
import PortalShell from '@/Layouts/PortalShell.vue'

defineProps({ entry: Object })
</script>

<template>
  <PortalShell>
    <div class="p-6 space-y-4 max-w-2xl">
      <Link :href="route('portal.medical-record.index')" class="text-sm underline">← الرجوع</Link>
      <h1 class="text-xl font-semibold">زيارة بتاريخ {{ new Date(entry.date).toLocaleDateString('ar') }}</h1>
      <p class="text-sm whitespace-pre-line">{{ entry.visible_summary }}</p>
      <section v-if="entry.prescriptions.length">
        <h2 class="text-sm font-medium text-muted-foreground mb-2">الوصفات</h2>
        <ul class="text-sm list-disc list-inside">
          <li v-for="(p, i) in entry.prescriptions" :key="i">{{ p.medication_name }} — {{ p.dosage }} · {{ p.frequency }} · {{ p.duration }}<span v-if="p.notes"> · {{ p.notes }}</span></li>
        </ul>
      </section>
    </div>
  </PortalShell>
</template>
```

- [ ] **Step 3: Add sidebar item to `PortalShell.vue`**

In the nav array, add:

```js
{ label: 'السجل الطبي', href: route('portal.medical-record.index'), icon: HeartPulse },
```

(Use the existing nav-item shape — read the file first.)

- [ ] **Step 4: Manual browser check** — log in as customer, click sidebar, see record.

- [ ] **Step 5: Commit**

```powershell
git add resources/js/Pages/Portal/MedicalRecord/Index.vue resources/js/Pages/Portal/MedicalRecord/Show.vue resources/js/Layouts/PortalShell.vue
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add Portal Medical Record pages and sidebar item" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 16: PHI-at-rest test + CI grep gate

**Files:**
- Create: `tests/Feature/Encryption/PhiAtRestTest.php`
- Modify: CI workflow (find it via `git ls-files | grep yml` — likely `.github/workflows/ci.yml`)

- [ ] **Step 1: Write the PHI-at-rest test**

```php
<?php

use App\Models\Appointment;
use App\Models\MedicalEntry;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

it('medical_entries.visible_summary is encrypted at rest', function () {
    $appt = Appointment::factory()->completed()->create();
    $entry = MedicalEntry::factory()->create([
        'appointment_id' => $appt->id,
        'visible_summary' => 'PLAINTEXT-DIAGNOSIS-XYZ',
    ]);

    $raw = DB::table('medical_entries')->where('id', $entry->id)->value('visible_summary');
    expect($raw)->not->toContain('PLAINTEXT-DIAGNOSIS-XYZ');

    expect($entry->fresh()->visible_summary)->toBe('PLAINTEXT-DIAGNOSIS-XYZ');
});

it('prescriptions.medication_name is encrypted at rest', function () {
    $entry = MedicalEntry::factory()->create();
    $p = Prescription::create([
        'medical_entry_id' => $entry->id,
        'medication_name' => 'UNIQUE-MED-TOKEN-77',
        'dosage' => 'd', 'frequency' => 'f', 'duration' => 'du',
    ]);

    $raw = DB::table('prescriptions')->where('id', $p->id)->value('medication_name');
    expect($raw)->not->toContain('UNIQUE-MED-TOKEN-77');
});
```

- [ ] **Step 2: Run — expect PASS**

Run: `./vendor/bin/pest tests/Feature/Encryption/PhiAtRestTest.php` → PASS

- [ ] **Step 3: Add CI grep gate**

Read the existing CI workflow; append a step to the test job:

```yaml
- name: P3 — MedicalAuditLog append-only grep gate
  run: |
    if grep -rEn 'MedicalAuditLog::.+->(update|delete)\b' app; then
      echo "ERROR: append-only invariant violated" && exit 1
    fi
```

If no GH Actions workflow is present (project uses scripts/ci.sh or similar), add the same check there.

- [ ] **Step 4: Commit**

```powershell
git add tests/Feature/Encryption/PhiAtRestTest.php .github/workflows/ci.yml
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(p3): add PHI-at-rest tests + CI append-only grep gate" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 17: Docs sync — ARCHITECTURE, DOMAIN-MODEL, CHANGELOG

**Files:**
- Modify: `docs/ARCHITECTURE.md`, `docs/DOMAIN-MODEL.md`, `CHANGELOG.md`

- [ ] **Step 1: Update ARCHITECTURE.md**

In the security/posture section, replace the ADR-002 reference with ADR-003 details. Add a new section "Carried Debt — R-DataTable migration" listing the 8 admin pages from spec §9.2.

- [ ] **Step 2: Update DOMAIN-MODEL.md**

Add three new entity sections after `CustomerProfile`:
- `MedicalEntry` — 1:1 with Appointment; author user (Doctor); encrypted `visible_summary` + `staff_notes`.
- `Prescription` — N:1 with MedicalEntry; encrypted med fields.
- `MedicalAuditLog` — append-only; actor / action / auditable / customer / changed_fields / ip / ua.

Add to CustomerProfile section: `chronic_conditions`, `allergies`, and the new PHI classification of `notes`.

- [ ] **Step 3: Add CHANGELOG entry**

Read the file first to match its format. Add an entry citing P3 + ADR-003.

- [ ] **Step 4: Commit**

```powershell
git add docs/ARCHITECTURE.md docs/DOMAIN-MODEL.md CHANGELOG.md
git -c user.email=admin@istoria.app -c user.name=claude commit -m "docs(p3): update ARCHITECTURE, DOMAIN-MODEL, CHANGELOG for medical records" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 18: Full DoD gate + tag p3-medical-records

- [ ] **Step 1: Run the full local gate**

```powershell
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest --coverage --min=60
npm run test:js
```

All four MUST be green. If any fail, fix and re-commit per the relevant task. Don't suppress.

- [ ] **Step 2: Verify scratch-Postgres `migrate:fresh` succeeds**

```powershell
$env:DB_DATABASE='jannahclinic_scratch'; php artisan migrate:fresh; $env:DB_DATABASE=$null
```

Expect: every migration up; including the new P3 migration with pgsql CHECK constraint.

- [ ] **Step 3: Run the append-only grep gate locally**

```powershell
if (Select-String -Path app -Pattern 'MedicalAuditLog::.+->(update|delete)\b' -Recurse) { Write-Error 'append-only violated' } else { Write-Output 'gate clean' }
```

Expect: "gate clean".

- [ ] **Step 4: Run the money-float and RTL grep gates**

```powershell
# money-float
$mt = Get-ChildItem app, database -Recurse -Include *.php | Select-String -Pattern '\b(float|double)\b' | Where-Object { $_.Line -match 'price|amount|fee|total' }
if ($mt) { Write-Error 'money float found'; $mt } else { Write-Output 'money gate clean' }

# RTL
$rt = Get-ChildItem resources/js/Layouts, resources/js/Pages, resources/js/Components/foundation, resources/css -Recurse -Include *.vue, *.css | Select-String -Pattern '\b(pl-|pr-|ml-|mr-)[0-9]|\btext-left\b|\btext-right\b'
if ($rt) { Write-Error 'RTL physical props found'; $rt } else { Write-Output 'RTL gate clean' }
```

Both must be clean.

- [ ] **Step 5: Verify ADR-003 is ACCEPTED and registry rows updated**

Inspect `docs/CANONICAL-DECISION-REGISTRY.md` and `docs/adr/README.md`. Both must list ADR-002 as SUPERSEDED and ADR-003 as ACCEPTED. This was done before the plan started — re-verify, don't re-edit.

- [ ] **Step 6: Tag the merge commit**

```powershell
git tag -a p3-medical-records -m "P3 — Medical Records (encrypted + audited). Supersedes ADR-002 via ADR-003."
```

- [ ] **Step 7: Final report**

Output the DoD checklist from the spec (§14) with each item ticked, plus a short summary: tables created, new test count, ADR-003 status, tag applied.

---

## Self-Review Notes

- Every task has files, test code, run commands with expected output, and a commit step.
- Type names consistent: `MedicalEntry`, `Prescription`, `MedicalAuditLog`, `AuditLogger`, `MedicalEntryService`, `PrescriptionService` everywhere.
- All six new route names (§10 of spec) are covered in the plan + RouteNamesTest extension (Tasks 9, 10, 11, 14).
- DataTable component (Task 7) precedes its consumer (Task 12) so dependencies are linear.
- The "create entry" route is delivered as a redirect in Task 11 to keep the controller in Task 9 minimal (store + edit + update). This is a deliberate scope decision and is documented inline.
- No placeholders — every step shows the code or command.

End of plan.
