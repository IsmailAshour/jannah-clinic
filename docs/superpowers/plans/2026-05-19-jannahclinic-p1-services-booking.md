# jannahclinic P1 (Services & Booking) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver P1 of jannahclinic: a pre-priced service catalog, doctors with weekly schedules, a shared 3-step booking wizard (customer self + reception on-behalf, centre/home with config-driven surcharge), and the full 7-state appointment lifecycle — built on the P0 foundation, ending at the Appointment only (no payment; payments are P2).

**Architecture:** Approach A — dependency-ordered vertical build. All business logic in `app/Domain/{Module}/Services/` (R7); thin controllers; Inertia/Vue 3 pages on the P0 foundation layer (`AdminShell`/`ClientShell`, `PageStates`, `DataTable`, `FormGroup`, `Modal`, design tokens, RTL logical props). Money is `decimal` + bcmath (no float). Postgres CHECK constraints pgsql-guarded (SQLite test DB skips them; CI Postgres is authoritative — same pattern as P0).

**Tech Stack (as built in P0):** PHP 8.4, Laravel 13, Inertia.js, Vue 3, Tailwind v4 (`@theme`), shadcn-vue (reka-ui), PostgreSQL (prod/CI) + SQLite in-memory (local tests), Pest, Pint, Larastan L5, Vite, Vitest.

**Project root:** `C:\~projects\jannahclinic` (git repo, branch `master`, tag `p0-foundation` at the P0 HEAD). Windows/PowerShell. Postgres: user `postgres` / pass `123123`, `psql` at `C:\Program Files\PostgreSQL\18\bin\psql.exe` (`$env:PGPASSWORD='123123'`).

**Spec:** `docs/superpowers/specs/2026-05-19-jannahclinic-p1-services-booking-design.md` (read it; §2 model, §3 services, §7 YAGNI boundary are authoritative).

**P0 patterns to follow (verify by reading the referenced P0 files before coding):**
- Models use the `#[Fillable([...])]` attribute (see `app/Models/User.php`), `protected $casts`, enum casts (`app/Enums/UserRole.php`).
- Migrations: Postgres CHECK constraints inside `if (DB::getDriverName() === 'pgsql') { DB::statement(...) }`, and reversed in `down()` with `DROP CONSTRAINT IF EXISTS` before `dropColumn`/`dropIfExists` (see `database/migrations/2026_05_19_000001_add_role_phone_to_users.php`).
- Routes: add groups to `routes/admin.php` (`auth`+`role:manager,doctor,receptionist`) / `routes/portal.php` (`auth`+`role:customer`) — NEVER add `verified` to portal. Policies for per-record authorization (R3).
- Domain services under `app/Domain/{Module}/Services/`; Pest unit tests under `tests/Unit/Domain/{Module}/`, feature tests under `tests/Feature/{Module}/`.
- Vue pages wrap content in `AdminShell`/`ClientShell`, import from `@/Components/foundation`; logical CSS only (`ps-/pe-/ms-/me-`, `text-start/end`) — CI greps authored dirs.
- Every task: update `docs/DOMAIN-MODEL.md` + `docs/ARCHITECTURE.md` when models/routes change (R6); add a `CHANGELOG.md` entry (DoD Q.9); run the gate; commit. Commit trailer:
```
Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```
- Quality gate per task: `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --no-progress`, `php artisan test`, `npm run test:js` (when JS changed), `npm run build` (when JS changed), RTL grep on authored dirs.

---

## File Structure

| Path | Responsibility |
|------|----------------|
| `config/clinic.php` | Config-driven defaults (`home_surcharge_pct`, `booking_lead_minutes`) |
| `app/Models/Setting.php` + migration | key/value settings store |
| `app/Domain/Settings/Services/SettingService.php` | get/set with config fallback (R12) |
| `app/Models/ServiceCategory.php`, `Service.php` + migrations | Catalog |
| `app/Models/DoctorProfile.php` + `doctor_service` pivot migration | Doctors ↔ services (+price_override) |
| `app/Models/DoctorSchedule.php`, `ScheduleException.php` + migrations | Availability source data |
| `app/Models/HomeServiceCoverageArea.php` + migration | Home coverage zones |
| `app/Enums/AppointmentStatus.php`, `DeliveryMode.php` | Lifecycle + mode enums |
| `app/Models/Appointment.php`, `ServiceAddress.php` + migrations | Booking records |
| `app/Domain/Booking/Services/AvailabilityService.php` | Pure slot computation |
| `app/Domain/Booking/Services/PricingService.php` | bcmath quote (base/surcharge/total) |
| `app/Domain/Booking/Services/BookingService.php` | Transactional booking + lock |
| `app/Domain/Booking/Services/AppointmentTransitionService.php` | State machine |
| `app/Domain/Booking/Data/BookingData.php` | Typed booking input DTO |
| `app/Http/Controllers/Admin/*`, `Portal/*` | Thin controllers per surface |
| `app/Policies/AppointmentPolicy.php` | Per-record authz (R3) |
| `routes/admin.php`, `routes/portal.php` | Route groups (extend P0 files) |
| `resources/js/Components/booking/BookingWizard.vue` | Shared 3-step wizard |
| `resources/js/Pages/Admin/{Catalog,Doctors,Coverage,Settings,Appointments,Booking}/*` | Admin pages |
| `resources/js/Pages/Portal/{Services,Booking,Appointments}/*` | Portal pages |
| `tests/Unit/Domain/Booking/*`, `tests/Unit/Domain/Settings/*`, `tests/Feature/{Catalog,Doctors,Booking,Appointments}/*` | Pest tests |

---

## Task 1: Settings store + SettingService (config-driven, R12)

**Files:**
- Create: `config/clinic.php`
- Create: `database/migrations/2026_05_20_000001_create_settings_table.php`
- Create: `app/Models/Setting.php`
- Create: `app/Domain/Settings/Services/SettingService.php`
- Test: `tests/Unit/Domain/Settings/SettingServiceTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Domain/Settings/SettingServiceTest.php`:
```php
<?php
use App\Domain\Settings\Services\SettingService;
use App\Models\Setting;

it('falls back to config default when no row exists', function () {
    expect(app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct')))
        ->toBe(30);
});

it('returns the stored value over the config default', function () {
    Setting::create(['key' => 'home_surcharge_pct', 'value' => '25']);
    expect(app(SettingService::class)->get('home_surcharge_pct', config('clinic.home_surcharge_pct')))
        ->toBe('25');
});

it('sets (upserts) a value', function () {
    $svc = app(SettingService::class);
    $svc->set('home_surcharge_pct', '40');
    $svc->set('home_surcharge_pct', '45');
    expect(Setting::where('key', 'home_surcharge_pct')->count())->toBe(1);
    expect($svc->get('home_surcharge_pct', 0))->toBe('45');
});
```

- [ ] **Step 2: Run it — confirm fail**

`php artisan test --filter=SettingServiceTest` → FAIL (classes missing).

- [ ] **Step 3: Create `config/clinic.php`**
```php
<?php

return [
    // Home-visit surcharge as a percentage of the (override-or-base) price.
    'home_surcharge_pct' => 30,
    // Minimum minutes between "now" and a bookable slot start.
    'booking_lead_minutes' => 0,
];
```

- [ ] **Step 4: Migration `..._create_settings_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

- [ ] **Step 5: `app/Models/Setting.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
}
```

- [ ] **Step 6: `app/Domain/Settings/Services/SettingService.php`**
```php
<?php

namespace App\Domain\Settings\Services;

use App\Models\Setting;

class SettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::query()->where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
```

- [ ] **Step 7: Migrate + test**

```powershell
cd C:\~projects\jannahclinic
php artisan migrate
php artisan test --filter=SettingServiceTest
```
Expected: migrate OK; 3 tests pass. (Note: `get` returns the DB string when a row exists, and the typed config default otherwise — the tests assert exactly this behaviour.)

- [ ] **Step 8: Docs + gate + commit**

Update `docs/DOMAIN-MODEL.md` (add `Setting` entity row + remove it from any OUT-OF-SCOPE list). Update `docs/ARCHITECTURE.md` (note `config/clinic.php` + `SettingService` under Governance/R12). Add `CHANGELOG.md` entry under a new `## [P1] Services & Booking — (in progress)` heading: `- Settings store + SettingService (config-driven, R12); config/clinic.php.`
```powershell
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(settings): config-driven SettingService + settings store (R12)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```
Expected: pint pass, phpstan 0, all tests green (43 P0 + 3 = 46).

---

## Task 2: Service catalog (ServiceCategory + Service) — model, admin CRUD, portal browse

**Files:**
- Create: migrations `..._create_service_categories_table.php`, `..._create_services_table.php`
- Create: `app/Models/ServiceCategory.php`, `app/Models/Service.php`
- Create: `app/Http/Controllers/Admin/ServiceCategoryController.php`, `Admin/ServiceController.php`, `Portal/ServiceBrowseController.php`
- Create: `resources/js/Pages/Admin/Catalog/Categories.vue`, `Admin/Catalog/Services.vue`, `Portal/Services/Index.vue`
- Modify: `routes/admin.php`, `routes/portal.php`
- Test: `tests/Feature/Catalog/CategoryCrudTest.php`, `tests/Feature/Catalog/ServiceCrudTest.php`, `tests/Feature/Catalog/PortalBrowseTest.php`

- [ ] **Step 1: Migrations** (CHECK constraints pgsql-guarded; `down()` drops constraints first — follow the P0 pattern in `2026_05_19_000001_add_role_phone_to_users.php`)

`..._create_service_categories_table.php`:
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
        Schema::create('service_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('color_variant', 16)->default('brand');
            $t->integer('display_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE service_categories ADD CONSTRAINT service_categories_color_check CHECK (color_variant IN ('brand','gold'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_categories DROP CONSTRAINT IF EXISTS service_categories_color_check');
        }
        Schema::dropIfExists('service_categories');
    }
};
```

`..._create_services_table.php`:
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
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('service_categories')->restrictOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('base_price', 10, 2);
            $t->integer('duration_minutes');
            $t->boolean('home_service_enabled')->default(false);
            $t->string('icon_key')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_base_price_check CHECK (base_price >= 0)');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_base_price_check');
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
        }
        Schema::dropIfExists('services');
    }
};
```

- [ ] **Step 2: Models**

`app/Models/ServiceCategory.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'color_variant', 'display_order', 'is_active'])]
class ServiceCategory extends Model
{
    protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
```

`app/Models/Service.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['category_id', 'name', 'description', 'base_price', 'duration_minutes', 'home_service_enabled', 'icon_key', 'is_active', 'display_order'])]
class Service extends Model
{
    protected $casts = [
        'base_price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'home_service_enabled' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(DoctorProfile::class, 'doctor_service')
            ->withPivot('price_override')->withTimestamps();
    }
}
```
(`DoctorProfile` + pivot table are created in Task 3; the relation method compiles now and is exercised in Task 3 tests.)

- [ ] **Step 3: Failing feature test — category CRUD** `tests/Feature/Catalog/CategoryCrudTest.php`:
```php
<?php
use App\Models\User;
use App\Models\ServiceCategory;
use App\Enums\UserRole;

it('lets a manager create a category', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/catalog/categories', [
        'name' => 'حجامة', 'slug' => 'hijama', 'color_variant' => 'gold', 'display_order' => 1,
    ])->assertRedirect();
    expect(ServiceCategory::where('slug', 'hijama')->exists())->toBeTrue();
});

it('forbids a customer from creating a category', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/catalog/categories', ['name' => 'x', 'slug' => 'x'])
        ->assertForbidden();
});

it('rejects an invalid color_variant', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/catalog/categories', [
        'name' => 'x', 'slug' => 'x', 'color_variant' => 'purple',
    ])->assertSessionHasErrors('color_variant');
});
```
Run `php artisan test --filter=CategoryCrudTest` → FAIL.

- [ ] **Step 4: Controllers (thin — validation + delegate to model; no business logic)**

`app/Http/Controllers/Admin/ServiceCategoryController.php`: resourceful `index` (Inertia render `Admin/Catalog/Categories` with `categories` ordered by display_order), `store`, `update`, `destroy`. `store`/`update` validate:
```php
$data = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:service_categories,slug' . ($id ? ',' . $id : '')],
    'color_variant' => ['required', 'in:brand,gold'],
    'display_order' => ['nullable', 'integer', 'min:0'],
    'is_active' => ['boolean'],
]);
ServiceCategory::create($data); // or $category->update($data)
return back();
```
`destroy`: block if it has services (`abort_if($category->services()->exists(), 409, 'لا يمكن حذف فئة بها خدمات.')`).

`app/Http/Controllers/Admin/ServiceController.php`: `index` (Inertia `Admin/Catalog/Services` with services + categories for the form), `store`/`update` validate:
```php
$request->validate([
    'category_id' => ['required', 'exists:service_categories,id'],
    'name' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string'],
    'base_price' => ['required', 'numeric', 'min:0'],
    'duration_minutes' => ['required', 'integer', 'min:1'],
    'home_service_enabled' => ['boolean'],
    'icon_key' => ['nullable', 'string', 'max:64'],
    'is_active' => ['boolean'],
    'display_order' => ['nullable', 'integer', 'min:0'],
]);
```
`Portal/ServiceBrowseController@index`: Inertia `Portal/Services/Index` with active categories + their active services (eager `category`), ordered by display_order.

- [ ] **Step 5: Routes** — add to `routes/admin.php` inside the existing `auth`+`role:manager,doctor,receptionist` group:
```php
Route::get('catalog/categories', [\App\Http\Controllers\Admin\ServiceCategoryController::class, 'index'])->name('catalog.categories');
Route::post('catalog/categories', [\App\Http\Controllers\Admin\ServiceCategoryController::class, 'store'])->name('catalog.categories.store');
Route::put('catalog/categories/{category}', [\App\Http\Controllers\Admin\ServiceCategoryController::class, 'update'])->name('catalog.categories.update');
Route::delete('catalog/categories/{category}', [\App\Http\Controllers\Admin\ServiceCategoryController::class, 'destroy'])->name('catalog.categories.destroy');
Route::get('catalog/services', [\App\Http\Controllers\Admin\ServiceController::class, 'index'])->name('catalog.services');
Route::post('catalog/services', [\App\Http\Controllers\Admin\ServiceController::class, 'store'])->name('catalog.services.store');
Route::put('catalog/services/{service}', [\App\Http\Controllers\Admin\ServiceController::class, 'update'])->name('catalog.services.update');
Route::delete('catalog/services/{service}', [\App\Http\Controllers\Admin\ServiceController::class, 'destroy'])->name('catalog.services.destroy');
```
Add to `routes/portal.php` inside the `auth`+`role:customer` group:
```php
Route::get('services', [\App\Http\Controllers\Portal\ServiceBrowseController::class, 'index'])->name('services.index');
```

- [ ] **Step 6: Vue pages** (wrap in `AdminShell`/`ClientShell`; use `@/Components/foundation`: `PageHeader`, `DataTable`, `Modal`, `FormGroup`, `PageStates`, `StatusBadge`; RTL logical classes only; Arabic labels; `useForm` from `@inertiajs/vue3`).
  - `Admin/Catalog/Categories.vue`: PageHeader + "إضافة فئة" button → `Modal` with `FormGroup` fields (name, slug, color_variant select brand/gold, display_order, is_active toggle); `DataTable` columns [name, slug, color, order, active, actions(edit/delete)]; delete via `ConfirmModal`.
  - `Admin/Catalog/Services.vue`: same pattern; form fields per Step 4 validation; category as `<select>` from props; base_price numeric, duration_minutes numeric, home_service_enabled toggle.
  - `Portal/Services/Index.vue`: `PageHeader`; for each active category render its name + grid of service cards (name, price via a small currency display showing `service.base_price` + " ₪", duration); `PageStates` empty when no services. (No booking action here yet — wizard is Task 9; this page is browse-only in this task.)

- [ ] **Step 7: Service CRUD + portal browse tests**

`tests/Feature/Catalog/ServiceCrudTest.php`:
```php
<?php
use App\Models\User; use App\Models\Service; use App\Models\ServiceCategory; use App\Enums\UserRole;

it('creates a service under a category', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name'=>'تدليك','slug'=>'massage','color_variant'=>'brand']);
    $this->actingAs($m)->post('/admin/catalog/services', [
        'category_id'=>$cat->id,'name'=>'تدليك علاجي','base_price'=>150,'duration_minutes'=>45,
        'home_service_enabled'=>true,
    ])->assertRedirect();
    $s = Service::first();
    expect($s->base_price)->toBe('150.00');
    expect($s->home_service_enabled)->toBeTrue();
});

it('rejects negative price and zero duration', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name'=>'x','slug'=>'x','color_variant'=>'brand']);
    $this->actingAs($m)->post('/admin/catalog/services', [
        'category_id'=>$cat->id,'name'=>'x','base_price'=>-1,'duration_minutes'=>0,
    ])->assertSessionHasErrors(['base_price','duration_minutes']);
});

it('blocks deleting a category that has services', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name'=>'x','slug'=>'x','color_variant'=>'brand']);
    Service::create(['category_id'=>$cat->id,'name'=>'s','base_price'=>10,'duration_minutes'=>30]);
    $this->actingAs($m)->delete("/admin/catalog/categories/{$cat->id}")->assertStatus(409);
});
```

`tests/Feature/Catalog/PortalBrowseTest.php`:
```php
<?php
use App\Models\User; use App\Models\Service; use App\Models\ServiceCategory; use App\Enums\UserRole;

it('shows active services to a customer and hides inactive', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $cat = ServiceCategory::create(['name'=>'حجامة','slug'=>'hijama','color_variant'=>'gold']);
    Service::create(['category_id'=>$cat->id,'name'=>'حجامة جافة','base_price'=>80,'duration_minutes'=>30,'is_active'=>true]);
    Service::create(['category_id'=>$cat->id,'name'=>'مخفية','base_price'=>80,'duration_minutes'=>30,'is_active'=>false]);
    $this->actingAs($c)->get('/portal/services')
        ->assertInertia(fn ($p) => $p->component('Portal/Services/Index'));
});

it('forbids staff from the customer services page', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->get('/portal/services')->assertForbidden();
});
```
Run `php artisan test --filter="CategoryCrudTest|ServiceCrudTest|PortalBrowseTest"` → all pass after Steps 4–6.

- [ ] **Step 8: Docs + gate + commit**

Update `docs/DOMAIN-MODEL.md` (add ServiceCategory, Service; trim OUT-OF-SCOPE), `docs/ARCHITECTURE.md` (catalog routes/surfaces), `CHANGELOG.md` (`- Service catalog (categories + services): admin CRUD + portal browse.`).
```powershell
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run build
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(catalog): service categories + services (admin CRUD, portal browse)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Doctors + doctor_service pivot (+price_override)

**Files:**
- Create: migrations `..._create_doctor_profiles_table.php`, `..._create_doctor_service_table.php`
- Create: `app/Models/DoctorProfile.php`
- Create: `app/Http/Controllers/Admin/DoctorController.php`
- Create: `resources/js/Pages/Admin/Doctors/Index.vue`
- Modify: `routes/admin.php`, `database/factories/` (DoctorProfileFactory)
- Test: `tests/Feature/Doctors/DoctorCrudTest.php`, `tests/Unit/Domain/Booking/DoctorServiceLinkTest.php`

- [ ] **Step 1: Migrations**

`..._create_doctor_profiles_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $t->string('specialty');
            $t->text('bio')->nullable();
            $t->decimal('rating_average', 2, 1)->nullable();
            $t->boolean('is_bookable')->default(true);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
```

`..._create_doctor_service_table.php`:
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
        Schema::create('doctor_service', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->decimal('price_override', 10, 2)->nullable();
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'service_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_service ADD CONSTRAINT doctor_service_price_check CHECK (price_override IS NULL OR price_override >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_service DROP CONSTRAINT IF EXISTS doctor_service_price_check');
        }
        Schema::dropIfExists('doctor_service');
    }
};
```

- [ ] **Step 2: `app/Models/DoctorProfile.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'specialty', 'bio', 'rating_average', 'is_bookable', 'display_order'])]
class DoctorProfile extends Model
{
    protected $casts = [
        'rating_average' => 'decimal:1',
        'is_bookable' => 'boolean',
        'display_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'doctor_service')
            ->withPivot('price_override')->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }
}
```
(`DoctorSchedule`/`ScheduleException` are Task 4; relation methods compile now, exercised in Task 4.)

- [ ] **Step 3: `DoctorProfileFactory`** — `database/factories/DoctorProfileFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::Doctor])->id,
            'specialty' => 'عام',
            'is_bookable' => true,
            'display_order' => 0,
        ];
    }
}
```
Add `use HasFactory;` + `protected static function newFactory()` if the project doesn't auto-resolve; follow the P0 `UserFactory` resolution pattern (read `app/Models/User.php`).

- [ ] **Step 4: Failing tests**

`tests/Unit/Domain/Booking/DoctorServiceLinkTest.php`:
```php
<?php
use App\Models\DoctorProfile; use App\Models\Service; use App\Models\ServiceCategory;

it('links a service with an optional price override', function () {
    $cat = ServiceCategory::create(['name'=>'x','slug'=>'x','color_variant'=>'brand']);
    $svc = Service::create(['category_id'=>$cat->id,'name'=>'s','base_price'=>100,'duration_minutes'=>30]);
    $doc = DoctorProfile::factory()->create();
    $doc->services()->attach($svc->id, ['price_override' => 120]);
    expect($doc->services()->first()->pivot->price_override)->toBe('120.00');
});
```

`tests/Feature/Doctors/DoctorCrudTest.php`:
```php
<?php
use App\Models\User; use App\Models\DoctorProfile; use App\Models\Service; use App\Models\ServiceCategory; use App\Enums\UserRole;

it('lets a manager create a doctor and assign services with override', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $cat = ServiceCategory::create(['name'=>'x','slug'=>'x','color_variant'=>'brand']);
    $svc = Service::create(['category_id'=>$cat->id,'name'=>'s','base_price'=>100,'duration_minutes'=>30]);
    $this->actingAs($m)->post('/admin/doctors', [
        'name'=>'د. سارة','email'=>'sara@c.com','password'=>'secret12','password_confirmation'=>'secret12',
        'specialty'=>'جلدية','is_bookable'=>true,
        'services'=>[['service_id'=>$svc->id,'price_override'=>130]],
    ])->assertRedirect();
    $doc = DoctorProfile::first();
    expect($doc->user->role)->toBe(UserRole::Doctor);
    expect($doc->services()->first()->pivot->price_override)->toBe('130.00');
});

it('forbids a customer from creating a doctor', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/doctors', ['name'=>'x'])->assertForbidden();
});
```
Run → FAIL.

- [ ] **Step 5: `app/Http/Controllers/Admin/DoctorController.php`** — thin; delegates user creation to a small domain method. Add to `app/Domain/Auth/Services/AuthService.php` a `createStaff(array $data, UserRole $role): User` method (mirror `registerCustomer` but no CustomerProfile, role param, in a `DB::transaction`). Controller `store`:
```php
$data = $request->validate([
    'name' => ['required','string','max:255'],
    'email' => ['required','email','unique:users,email'],
    'password' => ['required','confirmed', \Illuminate\Validation\Rules\Password::defaults()],
    'specialty' => ['required','string','max:255'],
    'bio' => ['nullable','string'],
    'is_bookable' => ['boolean'],
    'display_order' => ['nullable','integer','min:0'],
    'services' => ['array'],
    'services.*.service_id' => ['required','exists:services,id'],
    'services.*.price_override' => ['nullable','numeric','min:0'],
]);
DB::transaction(function () use ($data) {
    $user = app(\App\Domain\Auth\Services\AuthService::class)->createStaff($data, \App\Enums\UserRole::Doctor);
    $doc = \App\Models\DoctorProfile::create([
        'user_id'=>$user->id,'specialty'=>$data['specialty'],'bio'=>$data['bio']??null,
        'is_bookable'=>$data['is_bookable']??true,'display_order'=>$data['display_order']??0,
    ]);
    foreach ($data['services'] ?? [] as $s) {
        $doc->services()->attach($s['service_id'], ['price_override'=>$s['price_override']??null]);
    }
});
return back();
```
`index` → Inertia `Admin/Doctors/Index` with doctors (eager `user`,`services`) + all services for the assignment form. `update`/`destroy` analogous (update syncs pivot via `$doc->services()->sync([...])`). Add the `AuthService::createStaff` unit test in `tests/Unit/Domain/Auth/AuthServiceTest.php` (append): asserts role + no CustomerProfile.

- [ ] **Step 6: Routes** — add to `routes/admin.php` (staff group):
```php
Route::get('doctors', [\App\Http\Controllers\Admin\DoctorController::class, 'index'])->name('doctors.index');
Route::post('doctors', [\App\Http\Controllers\Admin\DoctorController::class, 'store'])->name('doctors.store');
Route::put('doctors/{doctor}', [\App\Http\Controllers\Admin\DoctorController::class, 'update'])->name('doctors.update');
Route::delete('doctors/{doctor}', [\App\Http\Controllers\Admin\DoctorController::class, 'destroy'])->name('doctors.destroy');
```

- [ ] **Step 7: `resources/js/Pages/Admin/Doctors/Index.vue`** — `AdminShell` + `PageHeader` + DataTable [name, specialty, bookable, #services, actions] + Modal form (name/email/password+confirm, specialty, bio, is_bookable toggle, display_order, and a repeatable service-assignment row: service `<select>` + optional price_override number). Foundation components, RTL, Arabic.

- [ ] **Step 8: Migrate, run all the Task-3 tests, docs, gate, commit**
```powershell
php artisan migrate
php artisan test --filter="DoctorServiceLinkTest|DoctorCrudTest|AuthServiceTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run build
```
Update `docs/DOMAIN-MODEL.md` (DoctorProfile + doctor_service), `docs/ARCHITECTURE.md`, `CHANGELOG.md`. Commit:
```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(doctors): doctor profiles + service assignment with price override

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: DoctorSchedule + ScheduleException + admin schedule management

**Files:**
- Create: migrations `..._create_doctor_schedules_table.php`, `..._create_schedule_exceptions_table.php`
- Create: `app/Models/DoctorSchedule.php`, `app/Models/ScheduleException.php`
- Create: `app/Http/Controllers/Admin/DoctorScheduleController.php`
- Create: `resources/js/Pages/Admin/Doctors/Schedule.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Doctors/ScheduleCrudTest.php`

- [ ] **Step 1: Migrations**

`..._create_doctor_schedules_table.php`:
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
        Schema::create('doctor_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->smallInteger('weekday'); // 0=Sunday .. 6=Saturday
            $t->boolean('morning_enabled')->default(false);
            $t->time('morning_start')->nullable();
            $t->time('morning_end')->nullable();
            $t->boolean('evening_enabled')->default(false);
            $t->time('evening_start')->nullable();
            $t->time('evening_end')->nullable();
            $t->integer('slot_interval_minutes')->default(30);
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'weekday']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_interval_check CHECK (slot_interval_minutes > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_schedules DROP CONSTRAINT IF EXISTS doctor_schedules_weekday_check');
            DB::statement('ALTER TABLE doctor_schedules DROP CONSTRAINT IF EXISTS doctor_schedules_interval_check');
        }
        Schema::dropIfExists('doctor_schedules');
    }
};
```

`..._create_schedule_exceptions_table.php`:
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
        Schema::create('schedule_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('type', 16); // closed | custom_hours
            $t->time('custom_start')->nullable();
            $t->time('custom_end')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'date']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom_hours'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check');
        }
        Schema::dropIfExists('schedule_exceptions');
    }
};
```

- [ ] **Step 2: Models**

`app/Models/DoctorSchedule.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_profile_id', 'weekday', 'morning_enabled', 'morning_start', 'morning_end', 'evening_enabled', 'evening_start', 'evening_end', 'slot_interval_minutes'])]
class DoctorSchedule extends Model
{
    protected $casts = [
        'weekday' => 'integer',
        'morning_enabled' => 'boolean',
        'evening_enabled' => 'boolean',
        'slot_interval_minutes' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }
}
```

`app/Models/ScheduleException.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_profile_id', 'date', 'type', 'custom_start', 'custom_end', 'note'])]
class ScheduleException extends Model
{
    protected $casts = ['date' => 'date'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }
}
```

- [ ] **Step 3: Failing test** `tests/Feature/Doctors/ScheduleCrudTest.php`:
```php
<?php
use App\Models\User; use App\Models\DoctorProfile; use App\Models\DoctorSchedule; use App\Enums\UserRole;

it('saves a weekly schedule row for a doctor', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [[
            'weekday'=>1,'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'12:00',
            'evening_enabled'=>false,'slot_interval_minutes'=>30,
        ]],
    ])->assertRedirect();
    expect(DoctorSchedule::where('doctor_profile_id',$doc->id)->where('weekday',1)->exists())->toBeTrue();
});

it('rejects an out-of-range weekday', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->put("/admin/doctors/{$doc->id}/schedule", [
        'schedules' => [['weekday'=>9,'slot_interval_minutes'=>30]],
    ])->assertSessionHasErrors('schedules.0.weekday');
});

it('adds a closed schedule exception', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $doc = DoctorProfile::factory()->create();
    $this->actingAs($m)->post("/admin/doctors/{$doc->id}/exceptions", [
        'date'=>now()->addWeek()->toDateString(),'type'=>'closed',
    ])->assertRedirect();
    expect($doc->scheduleExceptions()->count())->toBe(1);
});
```
Run → FAIL.

- [ ] **Step 4: `DoctorScheduleController`** — `editSchedule($doctor)` Inertia render `Admin/Doctors/Schedule` (doctor + its 0–7 schedule rows + exceptions). `saveSchedule` validates `schedules` array (each: weekday `integer|between:0,6`, morning/evening enabled booleans, *_start/_end `nullable|date_format:H:i`, slot_interval_minutes `integer|min:5`) and `upsert`s by (doctor,weekday). `addException` validates date `required|date`, type `in:closed,custom_hours`, custom_start/end `nullable|date_format:H:i`; `deleteException`.

- [ ] **Step 5: Routes** (staff group, `routes/admin.php`):
```php
Route::get('doctors/{doctor}/schedule', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'editSchedule'])->name('doctors.schedule');
Route::put('doctors/{doctor}/schedule', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'saveSchedule'])->name('doctors.schedule.save');
Route::post('doctors/{doctor}/exceptions', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'addException'])->name('doctors.exceptions.add');
Route::delete('doctors/{doctor}/exceptions/{exception}', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'deleteException'])->name('doctors.exceptions.delete');
```

- [ ] **Step 6: `Admin/Doctors/Schedule.vue`** — `AdminShell` + `PageHeader` (doctor name) + a 7-row weekly grid (one `FormSection` per weekday: morning toggle + start/end time inputs, evening toggle + start/end, slot interval) bound to a single `useForm({ schedules: [...] })` PUT; + an exceptions panel (`DataTable` of date/type/note + add via `Modal`, delete via `ConfirmModal`). RTL, Arabic weekday names (الأحد..السبت), foundation components.

- [ ] **Step 7: Migrate, tests, docs, gate, commit**
```powershell
php artisan migrate
php artisan test --filter=ScheduleCrudTest
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run build
```
Update DOMAIN-MODEL.md/ARCHITECTURE.md/CHANGELOG.md. Commit `feat(doctors): weekly schedules + date exceptions (admin)`.

---

## Task 5: Coverage areas + home-surcharge admin setting

**Files:**
- Create: migration `..._create_home_service_coverage_areas_table.php`
- Create: `app/Models/HomeServiceCoverageArea.php`
- Create: `app/Http/Controllers/Admin/CoverageAreaController.php`, `Admin/ClinicSettingController.php`
- Create: `resources/js/Pages/Admin/Coverage/Index.vue`, `Admin/Settings/Index.vue`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Coverage/CoverageCrudTest.php`, `tests/Feature/Settings/SurchargeSettingTest.php`

- [ ] **Step 1: Migration**
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_service_coverage_areas', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_service_coverage_areas');
    }
};
```

- [ ] **Step 2: `app/Models/HomeServiceCoverageArea.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_active', 'display_order'])]
class HomeServiceCoverageArea extends Model
{
    protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];
}
```

- [ ] **Step 3: Failing tests** `tests/Feature/Coverage/CoverageCrudTest.php`:
```php
<?php
use App\Models\User; use App\Models\HomeServiceCoverageArea; use App\Enums\UserRole;

it('lets a manager add a coverage area', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->post('/admin/coverage', ['name'=>'رام الله','is_active'=>true])
        ->assertRedirect();
    expect(HomeServiceCoverageArea::where('name','رام الله')->exists())->toBeTrue();
});
it('forbids a customer', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->post('/admin/coverage', ['name'=>'x'])->assertForbidden();
});
```
`tests/Feature/Settings/SurchargeSettingTest.php`:
```php
<?php
use App\Models\User; use App\Domain\Settings\Services\SettingService; use App\Enums\UserRole;

it('updates the home surcharge percentage', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/surcharge', ['home_surcharge_pct'=>'35'])
        ->assertRedirect();
    expect(app(SettingService::class)->get('home_surcharge_pct', 30))->toBe('35');
});
it('rejects a non-numeric or out-of-range percentage', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->put('/admin/settings/surcharge', ['home_surcharge_pct'=>'-5'])
        ->assertSessionHasErrors('home_surcharge_pct');
});
```
Run → FAIL.

- [ ] **Step 4: Controllers** — `CoverageAreaController` resourceful CRUD (validate name `required|string|max:255`, is_active boolean, display_order). `ClinicSettingController@index` Inertia `Admin/Settings/Index` with current `home_surcharge_pct` (via `SettingService::get('home_surcharge_pct', config('clinic.home_surcharge_pct'))`); `@updateSurcharge` validates `home_surcharge_pct` `required|numeric|between:0,100` then `app(SettingService::class)->set('home_surcharge_pct', (string)$v)`; `return back()`.

- [ ] **Step 5: Routes** (staff group):
```php
Route::get('coverage', [\App\Http\Controllers\Admin\CoverageAreaController::class,'index'])->name('coverage.index');
Route::post('coverage', [\App\Http\Controllers\Admin\CoverageAreaController::class,'store'])->name('coverage.store');
Route::put('coverage/{area}', [\App\Http\Controllers\Admin\CoverageAreaController::class,'update'])->name('coverage.update');
Route::delete('coverage/{area}', [\App\Http\Controllers\Admin\CoverageAreaController::class,'destroy'])->name('coverage.destroy');
Route::get('settings', [\App\Http\Controllers\Admin\ClinicSettingController::class,'index'])->name('settings.index');
Route::put('settings/surcharge', [\App\Http\Controllers\Admin\ClinicSettingController::class,'updateSurcharge'])->name('settings.surcharge');
```

- [ ] **Step 6: Vue pages** — `Admin/Coverage/Index.vue` (DataTable + Modal CRUD, like categories). `Admin/Settings/Index.vue` (`FormSection` with one number `FormGroup` for the surcharge %, save button; show current value). Foundation, RTL, Arabic.

- [ ] **Step 7: Migrate, tests, docs, gate, commit**
```powershell
php artisan migrate
php artisan test --filter="CoverageCrudTest|SurchargeSettingTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run build
```
Docs + CHANGELOG. Commit `feat(coverage): coverage areas CRUD + config-driven home-surcharge setting (R12)`.

---

## Task 6: Appointment + ServiceAddress entities + enums (data layer only)

**Files:**
- Create: `app/Enums/AppointmentStatus.php`, `app/Enums/DeliveryMode.php`
- Create: migrations `..._create_appointments_table.php`, `..._create_service_addresses_table.php`
- Create: `app/Models/Appointment.php`, `app/Models/ServiceAddress.php`
- Test: `tests/Unit/Domain/Booking/AppointmentStatusTest.php`

- [ ] **Step 1: Failing enum test** `tests/Unit/Domain/Booking/AppointmentStatusTest.php`:
```php
<?php
use App\Enums\AppointmentStatus as S;

it('knows terminal states', function () {
    expect(S::Requested->isTerminal())->toBeFalse();
    expect(S::Confirmed->isTerminal())->toBeFalse();
    foreach ([S::Rejected,S::Completed,S::Cancelled,S::NoShow,S::Rescheduled] as $t) {
        expect($t->isTerminal())->toBeTrue();
    }
});

it('allows only the defined transitions', function () {
    expect(S::Requested->canTransitionTo(S::Confirmed))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Rejected))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Cancelled))->toBeTrue();
    expect(S::Requested->canTransitionTo(S::Rescheduled))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Completed))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::NoShow))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Cancelled))->toBeTrue();
    expect(S::Confirmed->canTransitionTo(S::Rescheduled))->toBeTrue();
    // forbidden
    expect(S::Completed->canTransitionTo(S::Confirmed))->toBeFalse();
    expect(S::Requested->canTransitionTo(S::Completed))->toBeFalse();
    expect(S::Cancelled->canTransitionTo(S::Requested))->toBeFalse();
});
```
Run → FAIL.

- [ ] **Step 2: `app/Enums/DeliveryMode.php`**
```php
<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case Center = 'center';
    case Home = 'home';
}
```

- [ ] **Step 3: `app/Enums/AppointmentStatus.php`**
```php
<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Rejected, self::Completed, self::Cancelled, self::NoShow, self::Rescheduled,
        ], true);
    }

    /** @return self[] */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Rejected, self::Cancelled, self::Rescheduled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled, self::Rescheduled],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
```

- [ ] **Step 4: Run enum test — PASS** (`php artisan test --filter=AppointmentStatusTest`).

- [ ] **Step 5: Migrations**

`..._create_appointments_table.php`:
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
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->dateTime('start_at');
            $t->dateTime('end_at');
            $t->string('status', 16)->default('requested');
            $t->decimal('price_at_booking', 10, 2);
            $t->string('delivery_mode', 8);
            $t->decimal('home_surcharge_amount', 10, 2)->default(0);
            $t->string('created_by_role', 20);
            $t->string('cancellation_reason')->nullable();
            $t->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $t->timestamps();
            $t->index(['doctor_profile_id', 'start_at']);
            $t->index(['customer_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('requested','confirmed','rejected','completed','cancelled','no_show','rescheduled'))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_mode_check CHECK (delivery_mode IN ('center','home'))");
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_price_check CHECK (price_at_booking >= 0)');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_time_check CHECK (end_at > start_at)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['status','mode','price','time'] as $c) {
                DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_{$c}_check");
            }
        }
        Schema::dropIfExists('appointments');
    }
};
```

`..._create_service_addresses_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete()->unique();
            $t->foreignId('coverage_area_id')->constrained('home_service_coverage_areas')->restrictOnDelete();
            $t->string('address_text');
            $t->string('location_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addresses');
    }
};
```

- [ ] **Step 6: Models**

`app/Models/Appointment.php`:
```php
<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['customer_id', 'doctor_profile_id', 'service_id', 'start_at', 'end_at', 'status', 'price_at_booking', 'delivery_mode', 'home_surcharge_amount', 'created_by_role', 'cancellation_reason', 'rescheduled_from_id'])]
class Appointment extends Model
{
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'status' => AppointmentStatus::class,
        'delivery_mode' => DeliveryMode::class,
        'created_by_role' => UserRole::class,
        'price_at_booking' => 'decimal:2',
        'home_surcharge_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceAddress(): HasOne
    {
        return $this->hasOne(ServiceAddress::class);
    }
}
```

`app/Models/ServiceAddress.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'coverage_area_id', 'address_text', 'location_note'])]
class ServiceAddress extends Model
{
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function coverageArea(): BelongsTo
    {
        return $this->belongsTo(HomeServiceCoverageArea::class, 'coverage_area_id');
    }
}
```

- [ ] **Step 7: Migrate + full suite + docs + commit**
```powershell
php artisan migrate
php artisan test --filter="AppointmentStatusTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test
```
Update DOMAIN-MODEL.md (Appointment, ServiceAddress, the two enums) + ARCHITECTURE.md + CHANGELOG. Commit `feat(booking): appointment + service-address entities + status/mode enums`.

---

## Task 7: AvailabilityService (pure domain, heavy unit TDD) + availability endpoint

**Files:**
- Create: `app/Domain/Booking/Services/AvailabilityService.php`
- Create: `app/Http/Controllers/Booking/AvailabilityController.php`
- Modify: `routes/admin.php`, `routes/portal.php` (a shared availability GET in each surface group)
- Test: `tests/Unit/Domain/Booking/AvailabilityServiceTest.php`, `tests/Feature/Booking/AvailabilityEndpointTest.php`

- [ ] **Step 1: Failing unit test** `tests/Unit/Domain/Booking/AvailabilityServiceTest.php` — cover: morning-only window, evening-only, both, `closed` exception → [], `custom_hours` exception replaces windows, a booked (confirmed/requested) appointment removes the overlapping slot, a `cancelled`/`rescheduled` appointment does NOT remove the slot, past slots excluded, slot length = service.duration_minutes, step = slot_interval_minutes, last slot must fit before window end.
```php
<?php
use App\Domain\Booking\Services\AvailabilityService;
use App\Enums\AppointmentStatus;
use App\Models\{Appointment, DoctorProfile, DoctorSchedule, Service, ServiceCategory};
use Carbon\CarbonImmutable;

function mkService(int $dur = 30): Service {
    $c = ServiceCategory::create(['name'=>'x','slug'=>uniqid(),'color_variant'=>'brand']);
    return Service::create(['category_id'=>$c->id,'name'=>'s','base_price'=>100,'duration_minutes'=>$dur]);
}

it('generates morning slots at the interval, fitting the duration', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    // pick a fixed future Monday
    $date = CarbonImmutable::parse('next monday')->setTime(0,0);
    DoctorSchedule::create([
        'doctor_profile_id'=>$doc->id,'weekday'=>(int)$date->dayOfWeek,
        'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'10:00',
        'evening_enabled'=>false,'slot_interval_minutes'=>30,
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc, $svc, $date);
    expect(count($slots))->toBe(2); // 09:00-09:30, 09:30-10:00
    expect($slots[0]['start']->format('H:i'))->toBe('09:00');
    expect($slots[1]['start']->format('H:i'))->toBe('09:30');
});

it('returns no slots on a closed exception day', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService();
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id'=>$doc->id,'weekday'=>(int)$date->dayOfWeek,'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'12:00','evening_enabled'=>false,'slot_interval_minutes'=>30]);
    $doc->scheduleExceptions()->create(['date'=>$date->toDateString(),'type'=>'closed']);
    expect(app(AvailabilityService::class)->slotsFor($doc,$svc,$date))->toBe([]);
});

it('excludes a slot already taken by a non-terminal appointment', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id'=>$doc->id,'weekday'=>(int)$date->dayOfWeek,'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'10:00','evening_enabled'=>false,'slot_interval_minutes'=>30]);
    Appointment::create([
        'customer_id'=>\App\Models\User::factory()->create()->id,'doctor_profile_id'=>$doc->id,
        'service_id'=>$svc->id,'start_at'=>$date->setTime(9,0),'end_at'=>$date->setTime(9,30),
        'status'=>AppointmentStatus::Confirmed,'price_at_booking'=>100,'delivery_mode'=>'center',
        'created_by_role'=>'customer',
    ]);
    $slots = app(AvailabilityService::class)->slotsFor($doc,$svc,$date);
    expect(count($slots))->toBe(1);
    expect($slots[0]['start']->format('H:i'))->toBe('09:30');
});

it('does not exclude a slot for a cancelled appointment', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id'=>$doc->id,'weekday'=>(int)$date->dayOfWeek,'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'09:30','evening_enabled'=>false,'slot_interval_minutes'=>30]);
    Appointment::create(['customer_id'=>\App\Models\User::factory()->create()->id,'doctor_profile_id'=>$doc->id,'service_id'=>$svc->id,'start_at'=>$date->setTime(9,0),'end_at'=>$date->setTime(9,30),'status'=>AppointmentStatus::Cancelled,'price_at_booking'=>100,'delivery_mode'=>'center','created_by_role'=>'customer']);
    expect(count(app(AvailabilityService::class)->slotsFor($doc,$svc,$date)))->toBe(1);
});

it('excludes slots that start in the past', function () {
    $doc = DoctorProfile::factory()->create();
    $svc = mkService(30);
    $today = CarbonImmutable::now();
    DoctorSchedule::create(['doctor_profile_id'=>$doc->id,'weekday'=>(int)$today->dayOfWeek,'morning_enabled'=>true,'morning_start'=>'00:00','morning_end'=>'23:59','evening_enabled'=>false,'slot_interval_minutes'=>30]);
    $slots = app(AvailabilityService::class)->slotsFor($doc,$svc,$today);
    foreach ($slots as $s) { expect($s['start']->greaterThanOrEqualTo($today))->toBeTrue(); }
});
```
Run → FAIL.

- [ ] **Step 2: Implement `app/Domain/Booking/Services/AvailabilityService.php`**
```php
<?php

namespace App\Domain\Booking\Services;

use App\Enums\AppointmentStatus;
use App\Models\DoctorProfile;
use App\Models\Service;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    /** @return array<int,array{start:CarbonImmutable,end:CarbonImmutable}> */
    public function slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $windows = $this->windowsFor($doctor, $date);
        if ($windows === []) {
            return [];
        }

        $duration = $service->duration_minutes;
        $interval = $this->intervalFor($doctor, (int) $date->dayOfWeek);
        $now = CarbonImmutable::now()->addMinutes((int) config('clinic.booking_lead_minutes', 0));

        $taken = $doctor->appointments()
            ->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed])
            ->whereDate('start_at', $date->toDateString())
            ->get(['start_at', 'end_at']);

        $slots = [];
        foreach ($windows as [$winStart, $winEnd]) {
            $cursor = $date->setTimeFromTimeString($winStart);
            $limit = $date->setTimeFromTimeString($winEnd);
            while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($limit)) {
                $slotStart = $cursor;
                $slotEnd = $cursor->addMinutes($duration);
                $overlaps = $taken->contains(
                    fn ($a) => $slotStart->lessThan($a->end_at) && $slotEnd->greaterThan($a->start_at)
                );
                if (! $overlaps && $slotStart->greaterThanOrEqualTo($now)) {
                    $slots[] = ['start' => $slotStart, 'end' => $slotEnd];
                }
                $cursor = $cursor->addMinutes($interval);
            }
        }

        return $slots;
    }

    /** @return array<int,array{0:string,1:string}> */
    private function windowsFor(DoctorProfile $doctor, CarbonImmutable $date): array
    {
        $exception = $doctor->scheduleExceptions()
            ->whereDate('date', $date->toDateString())->first();
        if ($exception) {
            if ($exception->type === 'closed') {
                return [];
            }
            if ($exception->type === 'custom_hours' && $exception->custom_start && $exception->custom_end) {
                // NOTE: custom_start/custom_end are Carbon (datetime:H:i cast, see T4) — use ->format('H:i'), NOT substr((string)...).
                return [[$exception->custom_start->format('H:i'), $exception->custom_end->format('H:i')]];
            }
        }

        $schedule = $doctor->schedules()->where('weekday', (int) $date->dayOfWeek)->first();
        if (! $schedule) {
            return [];
        }
        // NOTE: morning_*/evening_* are Carbon (datetime:H:i cast, see T4) — use ->format('H:i'), NOT substr((string)...).
        $windows = [];
        if ($schedule->morning_enabled && $schedule->morning_start && $schedule->morning_end) {
            $windows[] = [$schedule->morning_start->format('H:i'), $schedule->morning_end->format('H:i')];
        }
        if ($schedule->evening_enabled && $schedule->evening_start && $schedule->evening_end) {
            $windows[] = [$schedule->evening_start->format('H:i'), $schedule->evening_end->format('H:i')];
        }

        return $windows;
    }

    private function intervalFor(DoctorProfile $doctor, int $weekday): int
    {
        return (int) ($doctor->schedules()->where('weekday', $weekday)->value('slot_interval_minutes') ?? 30);
    }
}
```
Add an `appointments(): HasMany` relation to `DoctorProfile` (in `app/Models/DoctorProfile.php`):
```php
public function appointments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\Appointment::class, 'doctor_profile_id');
}
```

- [ ] **Step 3: Run unit tests — PASS** (`php artisan test --filter=AvailabilityServiceTest`). Fix until all green; the `custom_hours` case is also covered — add a test asserting the custom window overrides schedule windows if not already present.

- [ ] **Step 4: Availability endpoint** `app/Http/Controllers/Booking/AvailabilityController.php`:
```php
public function __invoke(\Illuminate\Http\Request $request, \App\Domain\Booking\Services\AvailabilityService $svc)
{
    $data = $request->validate([
        'doctor' => ['required','exists:doctor_profiles,id'],
        'service' => ['required','exists:services,id'],
        'date' => ['required','date'],
    ]);
    $doctor = \App\Models\DoctorProfile::findOrFail($data['doctor']);
    $service = \App\Models\Service::findOrFail($data['service']);
    $slots = $svc->slotsFor($doctor, $service, \Carbon\CarbonImmutable::parse($data['date']));
    return response()->json(array_map(fn ($s) => [
        'start' => $s['start']->toIso8601String(),
        'end' => $s['end']->toIso8601String(),
        'label' => $s['start']->format('H:i'),
    ], $slots));
}
```
Register in BOTH groups (so portal customers and admin staff can fetch): in `routes/portal.php` (customer group) and `routes/admin.php` (staff group):
```php
Route::get('availability', \App\Http\Controllers\Booking\AvailabilityController::class)->name('availability');
```

- [ ] **Step 5: Feature test** `tests/Feature/Booking/AvailabilityEndpointTest.php`: a customer GETs `/portal/availability?doctor=&service=&date=` and receives a JSON array; a staff GETs `/admin/availability` similarly; a customer hitting `/admin/availability` → 403.

- [ ] **Step 6: gate + docs + commit**
```powershell
php artisan test --filter="AvailabilityServiceTest|AvailabilityEndpointTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test
```
Update ARCHITECTURE.md (AvailabilityService + endpoint). CHANGELOG. Commit `feat(booking): AvailabilityService slot engine + availability endpoint`.

---

## Task 8: PricingService + BookingService (transactional, locked) + BookingData

**Files:**
- Create: `app/Domain/Booking/Data/BookingData.php`
- Create: `app/Domain/Booking/Services/PricingService.php`
- Create: `app/Domain/Booking/Services/BookingService.php`
- Create: `app/Domain/Booking/Exceptions/SlotUnavailableException.php`, `InvalidBookingException.php`
- Test: `tests/Unit/Domain/Booking/PricingServiceTest.php`, `tests/Feature/Booking/BookingServiceTest.php`

- [ ] **Step 1: Failing PricingService test** `tests/Unit/Domain/Booking/PricingServiceTest.php`:
```php
<?php
use App\Domain\Booking\Services\PricingService;
use App\Enums\DeliveryMode;
use App\Models\{DoctorProfile, Service, ServiceCategory, Setting};

function pricedDoctorService(?string $override = null): array {
    $c = ServiceCategory::create(['name'=>'x','slug'=>uniqid(),'color_variant'=>'brand']);
    $s = Service::create(['category_id'=>$c->id,'name'=>'s','base_price'=>200,'duration_minutes'=>30,'home_service_enabled'=>true]);
    $d = DoctorProfile::factory()->create();
    $d->services()->attach($s->id, ['price_override'=>$override]);
    return [$d, $s];
}

it('quotes base price for a centre visit', function () {
    [$d,$s] = pricedDoctorService();
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Center);
    expect($q)->toBe(['base'=>'200.00','surcharge'=>'0.00','total'=>'200.00']);
});

it('uses the doctor price override when set', function () {
    [$d,$s] = pricedDoctorService('250');
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Center);
    expect($q['base'])->toBe('250.00');
});

it('adds the configured home surcharge percentage', function () {
    [$d,$s] = pricedDoctorService();           // base 200
    Setting::create(['key'=>'home_surcharge_pct','value'=>'30']);
    $q = app(PricingService::class)->quote($d, $s, DeliveryMode::Home);
    expect($q)->toBe(['base'=>'200.00','surcharge'=>'60.00','total'=>'260.00']);
});
```
Run → FAIL.

- [ ] **Step 2: `app/Domain/Booking/Services/PricingService.php`**
```php
<?php

namespace App\Domain\Booking\Services;

use App\Domain\Settings\Services\SettingService;
use App\Enums\DeliveryMode;
use App\Models\DoctorProfile;
use App\Models\Service;

class PricingService
{
    public function __construct(private readonly SettingService $settings) {}

    /** @return array{base:string,surcharge:string,total:string} */
    public function quote(DoctorProfile $doctor, Service $service, DeliveryMode $mode): array
    {
        $override = $doctor->services()
            ->where('services.id', $service->id)
            ->first()?->pivot?->price_override;
        // bcmath-pure: NO (float) cast — `app/database` lines containing price/amount/fee/total
        // must not match \b(float|double)\b (quality-gate.yml money check). base_price/price_override
        // are `decimal:2` casts (numeric strings); bcadd(...,'0',2) normalises to a 2-dp string.
        $base = bcadd((string) ($override ?? $service->base_price), '0', 2);

        $surcharge = '0.00';
        if ($mode === DeliveryMode::Home) {
            $pct = (string) $this->settings->get('home_surcharge_pct', config('clinic.home_surcharge_pct'));
            $surcharge = bcdiv(bcmul($base, $pct, 4), '100', 2);
        }

        return [
            'base' => $base,
            'surcharge' => $surcharge,
            'total' => bcadd($base, $surcharge, 2),
        ];
    }
}
```

- [ ] **Step 3: Run PricingService test — PASS.**

- [ ] **Step 4: `BookingData` + exceptions**

`app/Domain/Booking/Data/BookingData.php`:
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
    ) {}
}
```
`SlotUnavailableException` + `InvalidBookingException` — extend `\RuntimeException`, each with an Arabic default message.

- [ ] **Step 5: Failing BookingService feature test** `tests/Feature/Booking/BookingServiceTest.php`:
```php
<?php
use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Enums\{AppointmentStatus, DeliveryMode, UserRole};
use App\Models\{DoctorProfile, DoctorSchedule, HomeServiceCoverageArea, Service, ServiceCategory, User};
use Carbon\CarbonImmutable;

function bookingFixture(bool $home = false): array {
    $c = ServiceCategory::create(['name'=>'x','slug'=>uniqid(),'color_variant'=>'brand']);
    $s = Service::create(['category_id'=>$c->id,'name'=>'s','base_price'=>100,'duration_minutes'=>30,'home_service_enabled'=>$home]);
    $d = DoctorProfile::factory()->create();
    $d->services()->attach($s->id);
    $date = CarbonImmutable::parse('next monday');
    DoctorSchedule::create(['doctor_profile_id'=>$d->id,'weekday'=>(int)$date->dayOfWeek,'morning_enabled'=>true,'morning_start'=>'09:00','morning_end'=>'10:00','evening_enabled'=>false,'slot_interval_minutes'=>30]);
    $cust = User::factory()->create(['role'=>UserRole::Customer]);
    return compact('s','d','date','cust');
}

it('books a centre appointment at requested status with computed price', function () {
    ['s'=>$s,'d'=>$d,'date'=>$date,'cust'=>$cust] = bookingFixture();
    $appt = app(BookingService::class)->book(new BookingData(
        customerId:$cust->id, doctorProfileId:$d->id, serviceId:$s->id,
        startAt:$date->setTime(9,0), deliveryMode:DeliveryMode::Center, createdByRole:UserRole::Customer,
    ));
    expect($appt->status)->toBe(AppointmentStatus::Requested);
    expect($appt->price_at_booking)->toBe('100.00');
    expect($appt->serviceAddress)->toBeNull();
});

it('books a home appointment with a ServiceAddress and surcharge', function () {
    ['s'=>$s,'d'=>$d,'date'=>$date,'cust'=>$cust] = bookingFixture(home:true);
    $area = HomeServiceCoverageArea::create(['name'=>'رام الله','is_active'=>true]);
    $appt = app(BookingService::class)->book(new BookingData(
        customerId:$cust->id, doctorProfileId:$d->id, serviceId:$s->id,
        startAt:$date->setTime(9,0), deliveryMode:DeliveryMode::Home, createdByRole:UserRole::Customer,
        coverageAreaId:$area->id, addressText:'شارع 1',
    ));
    expect($appt->delivery_mode)->toBe(DeliveryMode::Home);
    expect($appt->serviceAddress->address_text)->toBe('شارع 1');
    expect((float)$appt->home_surcharge_amount)->toBeGreaterThan(0);
});

it('rejects booking a slot that is not available', function () {
    ['s'=>$s,'d'=>$d,'date'=>$date,'cust'=>$cust] = bookingFixture();
    expect(fn () => app(BookingService::class)->book(new BookingData(
        customerId:$cust->id, doctorProfileId:$d->id, serviceId:$s->id,
        startAt:$date->setTime(15,0), deliveryMode:DeliveryMode::Center, createdByRole:UserRole::Customer,
    )))->toThrow(SlotUnavailableException::class);
});

it('prevents double-booking the same slot', function () {
    ['s'=>$s,'d'=>$d,'date'=>$date,'cust'=>$cust] = bookingFixture();
    $data = fn () => new BookingData(customerId:$cust->id,doctorProfileId:$d->id,serviceId:$s->id,startAt:$date->setTime(9,0),deliveryMode:DeliveryMode::Center,createdByRole:UserRole::Customer);
    app(BookingService::class)->book($data());
    expect(fn () => app(BookingService::class)->book($data()))->toThrow(SlotUnavailableException::class);
});
```
Run → FAIL.

- [ ] **Step 6: `app/Domain/Booking/Services/BookingService.php`**
```php
<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidBookingException;
use App\Domain\Booking\Exceptions\SlotUnavailableException;
use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\HomeServiceCoverageArea;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {}

    public function book(BookingData $d): Appointment
    {
        return DB::transaction(function () use ($d) {
            $doctor = DoctorProfile::query()->lockForUpdate()->findOrFail($d->doctorProfileId);
            $service = Service::query()->findOrFail($d->serviceId);

            if (! $doctor->services()->where('services.id', $service->id)->exists()) {
                throw new InvalidBookingException('الطبيب لا يقدّم هذه الخدمة.');
            }
            if ($d->deliveryMode === DeliveryMode::Home) {
                if (! $service->home_service_enabled) {
                    throw new InvalidBookingException('الخدمة غير متاحة كزيارة منزلية.');
                }
                $area = HomeServiceCoverageArea::query()->where('is_active', true)->find($d->coverageAreaId);
                if (! $area || ! $d->addressText) {
                    throw new InvalidBookingException('منطقة التغطية أو العنوان غير صالح.');
                }
            }

            $available = collect($this->availability->slotsFor($doctor, $service, $d->startAt))
                ->first(fn ($s) => $s['start']->equalTo($d->startAt));
            if (! $available) {
                throw new SlotUnavailableException('الفترة لم تعد متاحة، اختر فترة أخرى.');
            }

            $quote = $this->pricing->quote($doctor, $service, $d->deliveryMode);

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

            if ($d->deliveryMode === DeliveryMode::Home) {
                $appt->serviceAddress()->create([
                    'coverage_area_id' => $d->coverageAreaId,
                    'address_text' => $d->addressText,
                    'location_note' => $d->locationNote,
                ]);
            }

            return $appt->fresh('serviceAddress');
        });
    }
}
```

- [ ] **Step 7: Run BookingService tests — PASS** (fix until green; the double-booking test relies on the in-transaction availability re-check + lock).

> **Contract (T7 review I3):** the slot re-validation MUST go through `AvailabilityService::slotsFor()` — it is the single source of truth for the overlap/past/window/status rule. Do NOT reimplement the overlap predicate inline in `BookingService`. The `DoctorProfile::query()->lockForUpdate()->findOrFail(...)` row lock is the load-bearing double-booking guard: it serializes concurrent `book()` calls for the same doctor so the second caller's `slotsFor()` re-check sees the first's `Requested` appointment and throws `SlotUnavailableException`. Do not remove or weaken the lock or move the re-check outside the transaction.

- [ ] **Step 8: gate + docs + commit**
```powershell
php artisan test --filter="PricingServiceTest|BookingServiceTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test
```
ARCHITECTURE.md (PricingService/BookingService/exceptions) + CHANGELOG. Commit `feat(booking): PricingService (bcmath) + transactional BookingService with slot lock`.

---

## Task 9: Booking wizard (shared) + portal self-booking + admin on-behalf

**Files:**
- Create: `resources/js/Components/booking/BookingWizard.vue`
- Create: `app/Http/Controllers/Portal/BookingController.php`, `app/Http/Controllers/Admin/BookingController.php`
- Create: `resources/js/Pages/Portal/Booking/Create.vue`, `resources/js/Pages/Admin/Booking/Create.vue`
- Modify: `routes/portal.php`, `routes/admin.php`, `app/Domain/Auth/Services/AuthService.php` (reuse `registerCustomer` for quick-create)
- Test: `tests/Feature/Booking/PortalBookingTest.php`, `tests/Feature/Booking/AdminOnBehalfBookingTest.php`, `resources/js/Components/booking/__tests__/BookingWizard.spec.js`

- [ ] **Step 1: Controllers (thin → BookingService)**

`Portal/BookingController`: `create` → Inertia `Portal/Booking/Create` with bookable doctors (+their services via pivot), active coverage areas. `store` validates (doctor exists+bookable, service exists, start ISO date, delivery_mode in center,home; if home: coverage_area_id active + address_text required) then builds `BookingData(customerId: $request->user()->id, createdByRole: UserRole::Customer, ...)` → `app(BookingService::class)->book($data)` inside try/catch translating `SlotUnavailableException`→409 back with error, `InvalidBookingException`→422 back with error; success → redirect `portal.appointments` with flash.

`Admin/BookingController`: `create` → Inertia `Admin/Booking/Create` (same doctor/service/coverage props + a customer picker list). `store`: same validation + `customer_id` (`required` if not quick-create) OR `new_customer` block (name + email|phone) → if quick-create, `app(AuthService::class)->registerCustomer([...])` (reused from P0) then use its id; `createdByRole` = `$request->user()->role`. Same try/catch.

> **Contract (T8 review — binding for both controllers):**
> - **`startAt` timezone (Important 2):** the wizard submits back the EXACT `start` ISO 8601 string the availability endpoint returned (it embeds the correct `Asia/Hebron` offset for that date — see T7 §M3). The controller MUST build `BookingData.startAt` via `CarbonImmutable::parse($validated['start'])` on that round-tripped string (the embedded offset yields the correct instant, DST-safe, so `->equalTo()` against `AvailabilityService` slots matches). Do NOT reconstruct `startAt` from separate date+time fields and do NOT strip/replace the offset, and do NOT parse a bare local string with a fixed `+03:00` — that breaks across Palestine DST. Validate `start` as `date`.
> - **`customer_id` integrity (Minor 6):** `BookingService` trusts `customerId` and does not check role/existence. Portal uses `$request->user()->id` (safe). Admin on-behalf MUST validate the picked `customer_id` exists AND is a Customer-role user (`exists:users,id` + an explicit role check, e.g. `User::where('id',$id)->where('role',UserRole::Customer)->exists()`, else 422 `InvalidBookingException`-style error) BEFORE calling `BookingService::book()`; a quick-created customer via `registerCustomer` is Customer by construction. A non-existent id would otherwise surface as an uncaught `QueryException`/500.

- [ ] **Step 2: Failing feature tests**

`tests/Feature/Booking/PortalBookingTest.php`: a customer posts a valid centre booking → redirect + an `Appointment` row (status requested, created_by_role customer); posting an unavailable slot → 409/redirect-with-error and no row; a staff user hitting `/portal/booking` → 403.

`tests/Feature/Booking/AdminOnBehalfBookingTest.php`: a receptionist posts a booking for an existing customer → appointment created with `created_by_role = receptionist`, customer_id = chosen; a receptionist quick-creates a customer (name+phone) and books → a customer User + CustomerProfile + appointment exist; a customer hitting `/admin/booking` → 403.

(Write these with concrete payloads mirroring the Task 8 fixtures: create category/service/doctor/schedule, pick the first availability slot via `AvailabilityService` to get a valid `start`.)
Run → FAIL.

- [ ] **Step 3: Routes**

`routes/portal.php` (customer group):
```php
Route::get('booking', [\App\Http\Controllers\Portal\BookingController::class,'create'])->name('booking.create');
Route::post('booking', [\App\Http\Controllers\Portal\BookingController::class,'store'])->name('booking.store');
```
`routes/admin.php` (staff group):
```php
Route::get('booking', [\App\Http\Controllers\Admin\BookingController::class,'create'])->name('booking.create');
Route::post('booking', [\App\Http\Controllers\Admin\BookingController::class,'store'])->name('booking.store');
```

- [ ] **Step 4: `BookingWizard.vue` (shared)** — props: `doctors` (each with `services[]` incl. `price_override`), `coverageAreas`, `availabilityUrl` (route differs per surface), optional `customerPicker` (boolean — admin shows a customer select / quick-create block before step 1). Three steps using foundation `PageStates`/`FormGroup`/`Modal` + tokens + RTL:
  1. Delivery mode (`center`/`home`); if `home`: coverage-area `<select>` (active) + `address_text` + `location_note`.
  2. Doctor `<select>` (bookable) → Service `<select>` filtered to that doctor's services (and `home_service_enabled` when mode=home).
  3. Date input → on change `fetch(availabilityUrl + ?doctor&service&date)` → render slot buttons. **Endpoint contract (T7 review M3):** the JSON is `{start,end,label}[]` where `start`/`end` are ISO 8601 **with timezone offset** (e.g. `2026-06-02T09:00:00+03:00`, app tz `Asia/Hebron`) and `label` is the `HH:MM` display string. Use `label` for the button text; submit the slot's `start` value back as-is (the server re-parses and authoritatively re-validates it). Footer shows a client-side price preview = `price_override ?? base_price` (+ surcharge% when home; surcharge% passed as a prop from the server `home_surcharge_pct`) — clearly a preview; the server recomputes authoritatively.
  Emits `submit` with the assembled payload; the page component posts it via Inertia `useForm`.
  `Portal/Booking/Create.vue` wraps `<ClientShell>` + `<BookingWizard :customerPicker="false" availabilityUrl="/portal/availability" .../>`. `Admin/Booking/Create.vue` wraps `<AdminShell>` + `<BookingWizard :customerPicker="true" availabilityUrl="/admin/availability" .../>` plus the customer select/quick-create.

- [ ] **Step 5: Vitest** `resources/js/Components/booking/__tests__/BookingWizard.spec.js`: mount with stub props; assert step 1 renders mode options; selecting `home` reveals coverage-area + address fields; step 2 service options filter by chosen doctor; an empty slots response shows the "لا فترات متاحة" empty state. (Mock `fetch`.)

- [ ] **Step 6: Run all Task-9 tests**
```powershell
php artisan test --filter="PortalBookingTest|AdminOnBehalfBookingTest"
npm run test:js
```
Both green.

- [ ] **Step 7: gate + docs + commit**
```powershell
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run build
```
ARCHITECTURE.md (booking surfaces + wizard) + DOMAIN-MODEL note (no new model) + CHANGELOG. Commit `feat(booking): shared 3-step wizard — portal self + admin on-behalf`.

---

## Task 10: Appointment lifecycle — transitions, admin management, my-appointments

**Files:**
- Create: `app/Domain/Booking/Services/AppointmentTransitionService.php`
- Create: `app/Policies/AppointmentPolicy.php`
- Create: `app/Http/Controllers/Admin/AppointmentController.php`, `Portal/AppointmentController.php`
- Create: `resources/js/Pages/Admin/Appointments/Index.vue`, `resources/js/Pages/Portal/Appointments/Index.vue`
- Modify: `routes/admin.php`, `routes/portal.php`, `app/Providers/AppServiceProvider.php` (register policy)
- Test: `tests/Unit/Domain/Booking/TransitionServiceTest.php`, `tests/Feature/Appointments/AdminLifecycleTest.php`, `tests/Feature/Appointments/PortalAppointmentTest.php`

- [ ] **Step 1: Failing unit test** `tests/Unit/Domain/Booking/TransitionServiceTest.php`: confirm `requested→confirmed`, `requested→rejected`, `confirmed→completed`, `confirmed→no_show`, `requested|confirmed→cancelled` succeed and mutate status (+ cancellation_reason when cancelling); illegal transitions (`completed→confirmed`, `cancelled→requested`) throw `InvalidTransitionException`; reschedule creates a new `requested` appointment with `rescheduled_from_id` set and old → `rescheduled`, both inside one transaction, new price re-quoted.

- [ ] **Step 2: `AppointmentTransitionService`**
```php
<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Data\BookingData;
use App\Domain\Booking\Exceptions\InvalidTransitionException;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class AppointmentTransitionService
{
    public function __construct(private readonly BookingService $booking) {}

    public function transition(Appointment $a, AppointmentStatus $to, ?string $reason = null): Appointment
    {
        if (! $a->status->canTransitionTo($to)) {
            throw new InvalidTransitionException("انتقال غير مسموح: {$a->status->value} → {$to->value}");
        }
        $a->status = $to;
        if ($to === AppointmentStatus::Cancelled) {
            $a->cancellation_reason = $reason;
        }
        $a->save();

        return $a;
    }

    public function reschedule(Appointment $old, \Carbon\CarbonImmutable $newStart): Appointment
    {
        return DB::transaction(function () use ($old, $newStart) {
            if (! $old->status->canTransitionTo(AppointmentStatus::Rescheduled)) {
                throw new InvalidTransitionException('لا يمكن إعادة جدولة هذا الموعد.');
            }
            $new = $this->booking->book(new BookingData(
                customerId: $old->customer_id,
                doctorProfileId: $old->doctor_profile_id,
                serviceId: $old->service_id,
                startAt: $newStart,
                deliveryMode: $old->delivery_mode,
                createdByRole: $old->created_by_role,
                coverageAreaId: $old->serviceAddress?->coverage_area_id,
                addressText: $old->serviceAddress?->address_text,
                locationNote: $old->serviceAddress?->location_note,
            ));
            $new->rescheduled_from_id = $old->id;
            $new->save();
            $old->status = AppointmentStatus::Rescheduled;
            $old->save();

            return $new;
        });
    }
}
```
Add `InvalidTransitionException` (extends `\RuntimeException`).

- [ ] **Step 3: Run unit test — PASS.**

- [ ] **Step 4: `AppointmentPolicy`** — `view`/`cancel`/`reschedule`: customer only on own (`$appt->customer_id === $user->id` && `$user->role === Customer`); staff (`$user->isStaff()`) may `manage` (confirm/reject/complete/no_show/cancel any). Register in `AppServiceProvider::boot` (`Gate::policy(Appointment::class, AppointmentPolicy::class)`), or `#[UsePolicy]` attribute if the P0 codebase uses that — read `app/Providers/AppServiceProvider.php` and follow its pattern.

- [ ] **Step 5: Controllers**
- `Admin/AppointmentController@index`: Inertia `Admin/Appointments/Index` with paginated appointments (filters: status, doctor, date — query params), eager `customer,doctor.user,service`. `@transition` (POST `{status, reason?}`) authorizes `manage`, calls `AppointmentTransitionService::transition`, try/catch → friendly error.
- `Portal/AppointmentController@index`: customer's own appointments (eager service,doctor.user), `PageStates`. `@cancel` (authorize `cancel`, requires reason) → transition to Cancelled. `@reschedule` (authorize `reschedule`, `{start}`) → `AppointmentTransitionService::reschedule`; translate exceptions.

- [ ] **Step 6: Routes**
`routes/admin.php` (staff group):
```php
Route::get('appointments', [\App\Http\Controllers\Admin\AppointmentController::class,'index'])->name('appointments.index');
Route::post('appointments/{appointment}/transition', [\App\Http\Controllers\Admin\AppointmentController::class,'transition'])->name('appointments.transition');
```
`routes/portal.php` (customer group):
```php
Route::get('appointments', [\App\Http\Controllers\Portal\AppointmentController::class,'index'])->name('appointments.index');
Route::post('appointments/{appointment}/cancel', [\App\Http\Controllers\Portal\AppointmentController::class,'cancel'])->name('appointments.cancel');
Route::post('appointments/{appointment}/reschedule', [\App\Http\Controllers\Portal\AppointmentController::class,'reschedule'])->name('appointments.reschedule');
```

- [ ] **Step 7: Vue pages**
- `Admin/Appointments/Index.vue`: `AdminShell` + `PageHeader` + filter bar (status/doctor/date) + `DataTable` [customer, doctor, service, start_at, status `StatusBadge`, mode, actions]. Actions per row by current status: confirm/reject (requested), complete/no_show (confirmed), cancel (non-terminal) — each via `ConfirmModal` POSTing the transition. `PageStates` empty/loading.
- `Portal/Appointments/Index.vue`: `ClientShell` + list cards (service, doctor, date, `StatusBadge`, price); actions when non-terminal & owned: cancel (`ConfirmModal` + reason) and reschedule (opens `BookingWizard` in reschedule mode OR a date+slot `Modal` reusing the availability endpoint). RTL, Arabic.

- [ ] **Step 8: Feature tests**
`tests/Feature/Appointments/AdminLifecycleTest.php`: staff confirms a requested appt (status→confirmed); staff completes a confirmed appt; illegal transition returns friendly error & unchanged status; customer hitting `/admin/appointments` → 403.
`tests/Feature/Appointments/PortalAppointmentTest.php`: customer cancels own appt with reason (status→cancelled, reason stored); customer cannot cancel another customer's appt (403 via policy); customer reschedules → new requested appt linked via rescheduled_from_id, old→rescheduled; staff hitting `/portal/appointments` → 403.
Run all Task-10 tests → green.

- [ ] **Step 9: gate + docs + commit**
```powershell
php artisan test --filter="TransitionServiceTest|AdminLifecycleTest|PortalAppointmentTest"
./vendor/bin/pint ; ./vendor/bin/pint --test ; ./vendor/bin/phpstan analyse --no-progress ; php artisan test ; npm run test:js ; npm run build
```
DOMAIN-MODEL.md (rescheduled_from_id relation note) + ARCHITECTURE.md (lifecycle service + policy + routes) + CHANGELOG. Commit `feat(appointments): lifecycle transitions, admin management, customer my-appointments`.

---

## Task 11: P1 acceptance — docs, full gate, tag

**Files:** updates to `docs/DOMAIN-MODEL.md`, `docs/ARCHITECTURE.md`, `CHANGELOG.md`; no app code.

- [ ] **Step 1: Finalize `docs/DOMAIN-MODEL.md`** — ensure ALL P1 entities documented (Setting, ServiceCategory, Service, DoctorProfile, doctor_service, DoctorSchedule, ScheduleException, HomeServiceCoverageArea, Appointment, ServiceAddress) with columns/constraints/relations; the "OUT OF SCOPE" section now lists only P2–P5 entities (Payment/Receipt/MedicalRecord/MedicalEntry/Prescription/MembershipPlan/UserMembership/LoyaltyTransaction/Notification).

- [ ] **Step 2: Finalize `docs/ARCHITECTURE.md`** — add a "P1 — Services & Booking" section: the 4 Domain services (Availability/Pricing/Booking/Transition), the shared wizard + two channels, the availability endpoint, surcharge config (R12), lifecycle/policy; update the P0-debt list (mark any P1-resolved items: e.g. shells now have real nav for catalog/doctors/appointments if added — only claim what was actually built; otherwise leave the debt items as-is and note still-open).

- [ ] **Step 3: `CHANGELOG.md`** — close the `## [P1] Services & Booking` heading with a dated summary line; ensure each task added its bullet.

- [ ] **Step 4: Full DoD gate**
```powershell
cd C:\~projects\jannahclinic
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
php artisan test
npm run test:js
npm run build
Select-String -Path resources\js\Layouts\*.vue,resources\js\Pages\**\*.vue,resources\js\Components\foundation\*.vue,resources\js\Components\booking\*.vue,resources\css\app.css -Pattern "\b(pl-|pr-|ml-|mr-)[0-9]|\btext-left\b|\btext-right\b"
Select-String -Path docs\GOLDEN-RULES.md,docs\DEFINITION-OF-DONE.md -Pattern "\{\{[A-Z_]+\}\}"
$env:PGPASSWORD='123123'; php artisan migrate:fresh ; & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -d jannahclinic -c "\dt"
```
Expected: pint pass; phpstan 0; all Pest pass; vitest pass; build ok; RTL grep on authored dirs (incl. `Components/booking`) NOTHING; no `{{}}` in kit docs; `migrate:fresh` on Postgres creates all P0+P1 tables.

- [ ] **Step 5: P1 acceptance checklist (verify each, PASS/FAIL + evidence)**
- [ ] Catalog: manager CRUD categories+services; customer browses active services; inactive hidden; can't delete a category with services.
- [ ] Doctors: manager creates doctor (User role=doctor) + assigns services with optional price_override; weekly schedule + exceptions saved.
- [ ] Coverage + surcharge: areas CRUD; `home_surcharge_pct` editable from admin and read via SettingService (R12, no hardcode).
- [ ] AvailabilityService unit-tested (morning/evening/closed/custom/booked-exclusion/past) — all green.
- [ ] PricingService bcmath (override + home %); BookingService transactional + double-book prevented (tests green).
- [ ] Booking wizard: customer self-books centre & home (ServiceAddress + surcharge); reception books on-behalf (created_by_role, existing or quick-created customer).
- [ ] Lifecycle: full 7-state transitions enforced server-side; customer cancel/reschedule own only (policy); staff confirm/reject/complete/no_show; illegal transitions rejected friendly.
- [ ] No P2+ logic present (no Payment/Receipt/MedicalRecord/Membership/Loyalty/Notification models) — `app/Models` contains only P0+P1 entities. YAGNI held.
- [ ] Quality gate green; DOMAIN-MODEL/ARCHITECTURE/CHANGELOG updated (R6/Q.9).

- [ ] **Step 6: Commit + tag**
```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "docs: P1 acceptance — DOMAIN-MODEL/ARCHITECTURE/CHANGELOG; full gate green

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag p1-services-booking
git -C C:\~projects\jannahclinic log --oneline | Select-Object -First 5
git -C C:\~projects\jannahclinic tag
```

---

## Plan Self-Review

**Spec coverage:** §1 decisions → all tasks (decision 1 no-payment: Task 6/8 create Appointment only, Task 11 YAGNI check; decision 2 doctor_service+override: Task 3 + PricingService Task 8; decision 3 7-state lifecycle: Task 6 enum + Task 10 transitions; decision 4 two channels: Task 9; decision 5 availability engine: Task 7; decision 6 surcharge config + coverage: Task 1+5+8). §2 model → Tasks 1,2,3,4,5,6 (every entity, with the corrected slot-blocking set {requested,confirmed} reflected in Task 7 Step 2 `whereIn([Requested,Confirmed])` and its tests; rescheduled excluded — matches spec §3.1 fix). §3 services → Tasks 7 (Availability), 8 (Pricing/Booking), 10 (Transition incl. reschedule creating a new `Requested` appt linked via rescheduled_from_id — matches spec §3.4 fix). §4 surfaces/pages → Tasks 2,3,4,5,9,10 (admin+portal). §5 error handling → controllers translate domain exceptions (Tasks 9,10) + foundation PageStates. §6 testing → unit (Availability/Pricing/Status/Transition/Setting) + feature + Vitest in each task. §7 YAGNI → Task 11 Step 5 boundary check (only P0+P1 models). §8 governance → every task: R7 services, R12 setting, R16 PageStates, R20 RTL grep, R6 doc updates, CHANGELOG, gate, subagent cycle. No gaps.

**Placeholder scan:** No "TBD/TODO". CRUD/Vue steps give exact validation arrays, exact route definitions, exact foundation-component composition, and concrete test payloads — a defined contract, not "add validation". Logic-bearing services + enums have complete code + test-first Pest. Controllers specified with exact validation + delegation; the engineer follows the explicit P0 patterns referenced at the top.

**Type/name consistency:** `DoctorProfile::services()` pivot `doctor_service` with `price_override` consistent across Tasks 2,3,8. `AppointmentStatus` cases/`canTransitionTo`/`isTerminal` consistent Tasks 6,7,10. `BookingData` constructor params consistent Tasks 8 (def) and 10 (reschedule reuse). `AvailabilityService::slotsFor` signature + return shape (`['start'=>CarbonImmutable,'end'=>...]`) consistent Tasks 7,8. `PricingService::quote` return `{base,surcharge,total}` consistent Tasks 8 (def) used in BookingService. Slot-blocking set = {Requested,Confirmed} consistent between spec §3.1 (fixed), Task 7 impl + tests. Route names (`portal.booking.store`, `admin.appointments.transition`, `*.availability`) consistent across route + controller + page tasks. `AuthService::registerCustomer` (P0) reused in Task 9 quick-create; new `createStaff` defined Task 3 and unit-tested there.

No issues found.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-19-jannahclinic-p1-services-booking.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, spec + quality review between tasks (same as P0).

**2. Inline Execution** — execute via executing-plans with checkpoints.

Which approach?

---

# P1 AMENDMENT — Schedule Redesign (Tasks 12–15)

> Spec: `docs/superpowers/specs/2026-05-19-jannahclinic-p1-schedule-redesign-design.md`.
> Decomposed **additive → engine+fixtures → controller+UI → cleanup+acceptance** so the
> full suite stays green at the end of every task (we never drop `DoctorSchedule`
> until nothing references it). Route names are UNCHANGED throughout.

## Task 12: Slot-grid config + `SlotGrid` + additive slot tables + Service {30,60} (purely additive — nothing removed)

**Files:**
- Modify: `config/clinic.php`
- Create: `app/Domain/Booking/Slots/SlotGrid.php`
- Create: migration `..._add_slot_grid_tables_and_service_duration_check.php`
- Create: `app/Models/DoctorScheduleSlot.php`, `app/Models/ScheduleExceptionSlot.php`
- Modify: `app/Models/DoctorProfile.php` (ADD `scheduleSlots()`, keep `schedules()`), `app/Models/ScheduleException.php` (ADD `slots()`, keep existing columns/casts), `app/Models/Service.php` (ADD `slotCount()`), `app/Http/Controllers/Admin/ServiceController.php` (duration `in:30,60`)
- Test: `tests/Unit/Domain/Booking/SlotGridTest.php`; add a duration-validation case to the existing `tests/Feature/Catalog/ServiceCrudTest.php`

- [ ] **Step 1: `config/clinic.php`** — add (keep `home_surcharge_pct`, `booking_lead_minutes`):
```php
'slot_minutes' => 30,
'day_start' => '08:00',
'day_end' => '22:00',
'band_split' => '15:00',
```

- [ ] **Step 2: failing `tests/Unit/Domain/Booking/SlotGridTest.php`**
```php
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
```
Run → FAIL.

- [ ] **Step 3: `app/Domain/Booking/Slots/SlotGrid.php`**
```php
<?php

namespace App\Domain\Booking\Slots;

class SlotGrid
{
    /** @return list<string> */
    public static function all(): array
    {
        $step = (int) config('clinic.slot_minutes', 30);
        $start = self::toMin((string) config('clinic.day_start', '08:00'));
        $end = self::toMin((string) config('clinic.day_end', '22:00'));
        $out = [];
        for ($m = $start; $m + $step <= $end; $m += $step) {
            $out[] = self::toHHMM($m);
        }

        return $out;
    }

    /** @return list<string> */
    public static function morning(): array
    {
        $split = self::toMin((string) config('clinic.band_split', '15:00'));

        return array_values(array_filter(self::all(), fn ($s) => self::toMin($s) < $split));
    }

    /** @return list<string> */
    public static function evening(): array
    {
        $split = self::toMin((string) config('clinic.band_split', '15:00'));

        return array_values(array_filter(self::all(), fn ($s) => self::toMin($s) >= $split));
    }

    public static function isValid(string $hhmm): bool
    {
        return in_array($hhmm, self::all(), true);
    }

    /** @return list<string>|null */
    public static function blockFrom(string $start, int $count): ?array
    {
        $all = self::all();
        $i = array_search($start, $all, true);
        if ($i === false || $count < 1) {
            return null;
        }
        $block = array_slice($all, $i, $count);

        return count($block) === $count ? array_values($block) : null;
    }

    private static function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private static function toHHMM(int $min): string
    {
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }
}
```
Run SlotGridTest → PASS.

- [ ] **Step 4: additive migration** `..._add_slot_grid_tables_and_service_duration_check.php` — creates the two new tables and tightens the Service duration constraint. DOES NOT touch `doctor_schedules` or the `schedule_exceptions` columns (those are dropped only in Task 15).
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
        Schema::create('doctor_schedule_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->string('slot_start', 5);
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'weekday', 'slot_start']);
            $t->index(['doctor_profile_id', 'weekday']);
        });
        Schema::create('schedule_exception_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_exception_id')->constrained()->cascadeOnDelete();
            $t->string('slot_start', 5);
            $t->timestamps();
            $t->unique(['schedule_exception_id', 'slot_start']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_schedule_slots ADD CONSTRAINT dss_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
            // Normalise any out-of-set QA durations BEFORE tightening the constraint.
            DB::statement('UPDATE services SET duration_minutes = CASE WHEN duration_minutes <= 30 THEN 30 ELSE 60 END WHERE duration_minutes NOT IN (30,60)');
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes IN (30,60))');
        } else {
            DB::table('services')->whereNotIn('duration_minutes', [30, 60])
                ->update(['duration_minutes' => DB::raw('CASE WHEN duration_minutes <= 30 THEN 30 ELSE 60 END')]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes > 0)');
        }
        Schema::dropIfExists('schedule_exception_slots');
        Schema::dropIfExists('doctor_schedule_slots');
    }
};
```

- [ ] **Step 5: models.**
`app/Models/DoctorScheduleSlot.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['doctor_profile_id', 'weekday', 'slot_start'])]
class DoctorScheduleSlot extends Model
{
    protected $casts = ['weekday' => 'integer'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }
}
```
`app/Models/ScheduleExceptionSlot.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_exception_id', 'slot_start'])]
class ScheduleExceptionSlot extends Model
{
    public function exception(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'schedule_exception_id');
    }
}
```
`app/Models/DoctorProfile.php` — ADD (keep the existing `schedules()` method untouched for now):
```php
public function scheduleSlots(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(DoctorScheduleSlot::class, 'doctor_profile_id');
}
```
(Use the file's existing import style for `HasMany` — it already imports it; match it, don't inline FQCN if the file uses short names.)
`app/Models/ScheduleException.php` — ADD (keep existing `custom_start`/`custom_end` in `$casts`/`#[Fillable]` for now; Task 14 stops using them, Task 15 drops them):
```php
public function slots(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ScheduleExceptionSlot::class, 'schedule_exception_id');
}
```
`app/Models/Service.php` — ADD:
```php
public function slotCount(): int
{
    return intdiv((int) $this->duration_minutes, (int) config('clinic.slot_minutes', 30));
}
```

- [ ] **Step 6: `ServiceController` duration rule.** In store AND update validation, change the `duration_minutes` rule to require 30 or 60 (read the file; match its existing rule-array/pipe style exactly), e.g. `'duration_minutes' => ['required','integer','in:30,60']`. Add to `tests/Feature/Catalog/ServiceCrudTest.php` one case: creating a service with `duration_minutes=45` (manager) → `assertSessionHasErrors('duration_minutes')`. Keep existing tests; only add.

- [ ] **Step 7: gate + commit.** `./vendor/bin/pint && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress && php artisan test` (FULL suite green — purely additive, expect prior count + SlotGridTest(4) + 1 service case; 0 regressions). Money grep still empty. `$env:PGPASSWORD='123123'; php artisan migrate` runs the additive migration on the live Postgres DB (creates the 2 tables; normalises any odd QA service durations to 30/60). Commit `feat(p1-redesign/t12): slot-grid config + SlotGrid + additive slot tables + Service {30,60}`.

## Task 13: AvailabilityService grid-engine rewrite + suite fixture migration

**Files:**
- Rewrite: `app/Domain/Booking/Services/AvailabilityService.php`
- Modify: `tests/Pest.php` (add shared helpers) — or a dedicated `tests/Support/SlotFixtures.php` autoloaded by Pest
- Rewrite: `tests/Unit/Domain/Booking/AvailabilityServiceTest.php`
- Modify (fixtures only): every test that currently does `DoctorSchedule::create([...])` — at minimum `tests/Feature/Booking/AvailabilityEndpointTest.php`, `tests/Feature/Booking/BookingServiceTest.php`, `tests/Feature/Booking/PortalBookingTest.php`, `tests/Feature/Booking/AdminOnBehalfBookingTest.php`, `tests/Unit/Domain/Booking/TransitionServiceTest.php`, `tests/Feature/Appointments/AdminLifecycleTest.php`, `tests/Feature/Appointments/PortalAppointmentTest.php` (grep the whole `tests/` tree for `DoctorSchedule` and convert ALL).

- [ ] **Step 1: shared Pest helper** (in `tests/Pest.php`, following its existing helper style):
```php
function enableDoctorSlots(\App\Models\DoctorProfile $doctor, int $weekday, array $starts): void
{
    foreach ($starts as $s) {
        \App\Models\DoctorScheduleSlot::create([
            'doctor_profile_id' => $doctor->id,
            'weekday' => $weekday,
            'slot_start' => $s,
        ]);
    }
}
/** Contiguous half-hour starts from $from for $count slots, e.g. slotRange('09:00',4) => 09:00,09:30,10:00,10:30 */
function slotRange(string $from, int $count): array
{
    return \App\Domain\Booking\Slots\SlotGrid::blockFrom($from, $count) ?? [];
}
```

- [ ] **Step 2: rewrite `AvailabilityService::slotsFor`** (signature & return shape UNCHANGED — keeps T7 §M3 ISO contract, T8/T9/T10 untouched):
```php
<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Slots\SlotGrid;
use App\Enums\AppointmentStatus;
use App\Models\DoctorProfile;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AvailabilityService
{
    /** @return array<int,array{start:CarbonImmutable,end:CarbonImmutable}> */
    public function slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();
        $enabled = $this->enabledFor($doctor, $date);
        if ($enabled === []) {
            return [];
        }

        $need = max(1, $service->slotCount());
        $duration = (int) $service->duration_minutes;
        $now = CarbonImmutable::now()->addMinutes((int) config('clinic.booking_lead_minutes', 0));

        /** @var Collection<int,\App\Models\Appointment> $taken */
        $taken = $doctor->appointments()
            ->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed])
            ->whereDate('start_at', $date->toDateString())
            ->get(['start_at', 'end_at']);

        $enabledSet = array_flip($enabled);
        $slots = [];
        foreach (SlotGrid::all() as $s) {
            $block = SlotGrid::blockFrom($s, $need);
            if ($block === null) {
                continue;
            }
            $allEnabled = true;
            foreach ($block as $b) {
                if (! isset($enabledSet[$b])) {
                    $allEnabled = false;
                    break;
                }
            }
            if (! $allEnabled) {
                continue;
            }
            $start = $date->setTimeFromTimeString($s);
            $end = $start->addMinutes($duration);
            if ($start->lessThan($now)) {
                continue;
            }
            $overlaps = $taken->contains(
                fn ($a) => $start->lessThan($a->end_at) && $end->greaterThan($a->start_at)
            );
            if (! $overlaps) {
                $slots[] = ['start' => $start, 'end' => $end];
            }
        }

        return $slots;
    }

    /** @return list<string> enabled 'HH:MM' starts for the date (exception-aware) */
    private function enabledFor(DoctorProfile $doctor, CarbonImmutable $date): array
    {
        /** @var \App\Models\ScheduleException|null $ex */
        $ex = $doctor->scheduleExceptions()->whereDate('date', $date->toDateString())->first();
        if ($ex) {
            if ($ex->type === 'closed') {
                return [];
            }
            if ($ex->type === 'custom') {
                return $ex->slots()->pluck('slot_start')->all();
            }
        }

        return $doctor->scheduleSlots()
            ->where('weekday', (int) $date->dayOfWeek)
            ->pluck('slot_start')->all();
    }
}
```

- [ ] **Step 3: rewrite `tests/Unit/Domain/Booking/AvailabilityServiceTest.php`** using `enableDoctorSlots`/`slotRange`. Cover, with a fixed future weekday (`CarbonImmutable::parse('next monday')`, `weekday=(int)$date->dayOfWeek`) and `mkService($dur)` helper (30 or 60):
  - 30-min service, slots `09:00,09:30,10:00` enabled → 3 slots at those starts.
  - closed exception that date → `[]`.
  - custom exception with slots `['14:00','14:30']` (no weekly slots) → only 14:00 (+ 14:30 if 30-min) emitted; nothing in the morning.
  - 60-min service needs 2 consecutive enabled+free: enable `09:00,09:30,10:30` (gap at 10:00) → 60-min emits only `09:00` (09:30→10:00 fails: 10:00 not enabled; 10:30 has no 11:00).
  - a 60-min `Confirmed` appointment 09:00–10:00 blocks both halves: with `09:00..11:00` enabled and a 60-min service → `09:00` excluded, next valid start `10:00` (10:00–11:00) emitted.
  - `Cancelled` appointment does NOT block.
  - past exclusion (today, enable an early past slot + a future one → only future).
  - last 60-min start boundary: enable `21:00,21:30`, 60-min → exactly one slot `21:00` (21:00–22:00); a 60-min at `21:30` never offered.

- [ ] **Step 4: migrate ALL dependent fixtures.** `grep -rl DoctorSchedule tests/` and convert every `DoctorSchedule::create([... 'morning_start'=>'09:00','morning_end'=>'10:00' ... 'slot_interval_minutes'=>30 ...])` to the equivalent `enableDoctorSlots($doctor, (int)$date->dayOfWeek, slotRange('09:00', N))` where N covers the same span the test needs (e.g. a 30-min service test that booked 09:00 needs at least `['09:00']`; a test that needs two bookable 30-min slots 09:00 & 09:30 needs `slotRange('09:00',2)`; preserve each test's intent — read each test and enable exactly the slots that make its existing assertions pass). Remove the now-unused `DoctorSchedule` imports from those test files. Do NOT change assertions; only the schedule-seeding lines.

- [ ] **Step 5: gate + commit.** Full suite green (`php artisan test`), pint, phpstan 0 (use precise `@var` like the file already does — NO new `@phpstan-ignore`). The old `DoctorScheduleController`/`Schedule.vue`/`ScheduleCrudTest` still operate on the old `doctor_schedules` table and stay green (untouched this task). `npm run test:js`/`npm run build` unaffected but run them. Commit `feat(p1-redesign/t13): AvailabilityService grid engine + suite fixtures on doctor_schedule_slots`.

## Task 14: DoctorScheduleController + Schedule.vue button-grid + closed/custom exceptions

**Files:**
- Rewrite: `app/Http/Controllers/Admin/DoctorScheduleController.php`
- Rewrite: `resources/js/Pages/Admin/Doctors/Schedule.vue`
- Rewrite: `tests/Feature/Doctors/ScheduleCrudTest.php`

- [ ] **Step 1: controller.** Route names/paths UNCHANGED. `editSchedule(DoctorProfile $doctor)`: Inertia render `Admin/Doctors/Schedule` with `doctor`, `grid` => `['morning'=>SlotGrid::morning(),'evening'=>SlotGrid::evening()]`, `slots` => doctor's `scheduleSlots` grouped weekday→`string[]`, `exceptions` => each `scheduleExceptions` with `{id,date,type,note,slots:[...]}`. `saveSchedule(Request,$doctor)`: validate `slots` is an array keyed 0..6, each an array whose every value passes `SlotGrid::isValid` (use a closure/`Rule`); inside `DB::transaction`, for each weekday delete the doctor's slots for that weekday and re-insert the submitted set (idempotent replace). `back()->with('success','تم حفظ الجدول')`. Manager-only (reuse the existing route-group authz exactly as the current controller did). `addException(Request,$doctor)`: validate `date` (date), `type` (`in:closed,custom`), `slots` (`array`, `required_if:type,custom`, each `SlotGrid::isValid`), `note` (nullable string max:255). `updateOrCreate` the `ScheduleException` by `(doctor,date)` with `type,note`; then sync `schedule_exception_slots`: delete its slots, and if `type==='custom'` insert the submitted ones. `back()->with('success','تمت إضافة الاستثناء')`. `deleteException(DoctorProfile $doctor, ScheduleException $exception)`: keep the existing ownership `abort_unless($exception->doctor_profile_id===$doctor->id,404)`, delete (cascade removes its slots), `back()->with('success','تم حذف الاستثناء')`. Larastan-clean (precise types; no new ignore).

- [ ] **Step 2: `Schedule.vue` button grid.** `AdminShell` + `PageHeader`. For each weekday (الأحد..السبت, index 0..6) a row with two labelled groups (صباحية / مسائية) of toggle buttons built from the `grid.morning`/`grid.evening` props; a button is "selected" (filled, `aria-pressed`) when its `HH:MM` is in that weekday's enabled set; clicking toggles it in a local reactive structure; a "حفظ الجدول" button PUTs the whole `{ slots: {0:[...],1:[...],...} }` to `admin.doctors.schedule.save` via Inertia `useForm`. Exceptions panel: a `<input type="date" dir="ltr">` + a `مغلق`/`مخصّص` choice; when `مخصّص`, render the SAME button grid for that one date; submit to `admin.doctors.exceptions.add`. List existing exceptions (date, type, slot count) each with a delete (`ConfirmModal`, reading `errors.delete`). Foundation components, RTL **logical properties only** (CI greps `resources/js/**/*.vue`), Arabic. Reuse the Arabic weekday array order matching integer 0=الأحد..6=السبت (same mapping the old page used / the controller/AvailabilityService expect).

- [ ] **Step 3: rewrite `tests/Feature/Doctors/ScheduleCrudTest.php`**: manager saves a weekday slot set (PUT) → rows exist in `doctor_schedule_slots` exactly matching; re-saving with a different set replaces (no stale rows); an invalid `slot_start` (e.g. `'08:15'`) → `assertSessionHasErrors`; non-manager staff PUT → 403; manager GET schedule page → 200; add a `closed` exception → row with type closed, no slots; add a `custom` exception with `['09:00','09:30']` → exception + 2 `schedule_exception_slots`; custom exception with an invalid slot → errors; delete exception (ownership 404 for another doctor's exception) cascades its slots. Keep Pest style.

- [ ] **Step 4: gate + commit.** Full `php artisan test` green, `pint`, `phpstan 0`, `npm run test:js`, `npm run build`, RTL grep on `resources/js/Pages/Admin/Doctors` empty. Now NOTHING references `DoctorSchedule` or `custom_start/custom_end`. Commit `feat(p1-redesign/t14): doctor schedule button-grid UI + closed/custom exceptions`.

## Task 15: cleanup migration + P1 re-acceptance + final review + tag

**Files:** Create cleanup migration `..._drop_legacy_doctor_schedules_and_exception_columns.php`; delete `app/Models/DoctorSchedule.php`; docs (`DOMAIN-MODEL.md`, `ARCHITECTURE.md`, `CHANGELOG.md`).

- [ ] **Step 1: verify zero references.** `grep -rn "DoctorSchedule\b\|doctor_schedules\|custom_start\|custom_end\|slot_interval_minutes" app/ tests/ resources/` → only `DoctorScheduleSlot`/`DoctorScheduleController`/`doctor_schedule_slots` may match; NO `DoctorSchedule` model use, NO `doctor_schedules` table use, NO `custom_start/custom_end`, NO `slot_interval_minutes`. If anything remains, fix it before migrating.

- [ ] **Step 2: cleanup migration.**
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
        Schema::dropIfExists('doctor_schedules');
        Schema::table('schedule_exceptions', function (Blueprint $t) {
            $t->dropColumn(['custom_start', 'custom_end']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check");
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom'))");
        }
    }

    public function down(): void
    {
        // One-way cleanup (legacy weekly-window model retired). Re-add minimally if rolled back.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check");
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom_hours'))");
        }
        Schema::table('schedule_exceptions', function (Blueprint $t) {
            $t->time('custom_start')->nullable();
            $t->time('custom_end')->nullable();
        });
    }
};
```
Delete `app/Models/DoctorSchedule.php`. Run `$env:PGPASSWORD='123123'; php artisan migrate` on the live DB (drops legacy table/columns — QA schedule data under the old model is intentionally not preserved; the dev seeder seeds none; re-enter via the new grid UI).

- [ ] **Step 3: docs (R6/Q.9).** `DOMAIN-MODEL.md`: remove `DoctorSchedule`; document `doctor_schedule_slots`, restructured `ScheduleException` (`closed|custom`), `schedule_exception_slots`, and `SlotGrid`/Service `{30,60}`. `ARCHITECTURE.md`: replace the schedule description with the slot-grid model + `SlotGrid` + the AvailabilityService grid engine + config keys; **delete** the now-moot "Schedule time-field contract (T4 datetime:H:i)" debt bullet; note Service duration constraint. `CHANGELOG.md`: under a re-opened `## [P1] Services & Booking` add the redesign bullets, then re-close with the project date.

- [ ] **Step 4: full P1 re-acceptance gate** (same as Task 11 Step 4, NON-DESTRUCTIVE to the live DB — use a throwaway `jannahclinic_gate` scratch DB for `migrate:fresh`, then drop it; never `migrate:fresh` the live `jannahclinic`): `pint --test`; `phpstan 0`; `php artisan test` ALL green; `npm run test:js`; `npm run build`; RTL grep authored dirs empty; no `{{}}` in kit docs; money grep empty; scratch-DB fresh migrate creates all P0+P1 tables (now including `doctor_schedule_slots`/`schedule_exception_slots`, NOT `doctor_schedules`). Re-run the Task 11 Step 5 acceptance checklist (PASS/FAIL + evidence), with item 2 now reading: weekly slot-grid + closed/custom exceptions saved & reachable.

- [ ] **Step 5: commit (NO tag here — the controller tags after the final whole-P1 review).**
`git status`; stage only the migration, the deleted model, and the three doc files (no `git add -A`). Commit `chore(p1-redesign/t15): drop legacy schedule model + P1 re-acceptance gate green`.

---
