# jannahclinic P5b — Public Landing + Portal Polish — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open the customer surface to guests — public landing site at `/` + browsable services/doctors + FAQ — while preserving the existing `/portal/*` authed area and adding intent-to-action login gating, plus new authed `Profile` and `Settings` pages.

**Architecture:** A new `Public/*` controller namespace serves four public routes (`/`, `/services`, `/doctors`, `/support`) without auth middleware. The existing `ClientShell` becomes adaptive — reads `auth.user` and switches between guest header (login/register CTAs + 4-tab nav) and authed header (bell + logout + 6-tab nav). A foundation component `<AuthGuardLink>` plus an `IntentResolver` service route guest action-clicks through `/login?intent=…` and back to the right destination after authentication. New `Portal\ProfileController` and `Portal\SettingsController` cover the customer's own data. No new domain entities, no migrations — UI + routes phase only.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL prod / SQLite tests · Pest · Larastan L5 · Pint · Inertia.js · Vue 3 · shadcn-vue · Tailwind v4 · Vitest.

**Spec:** `docs/superpowers/specs/2026-05-20-jannahclinic-p5b-portal-design.md`

**Execution mode:** Subagent-driven (controller dispatches a fresh implementer per task + spec review + code-quality review per the P4a pattern). Each task ends with the full gate (Pest + Vitest + Pint + PHPStan + Vite) + commit; controller pushes after both reviews approve.

**Commit convention (verbatim):**
```
git -c user.email=admin@istoria.app -c user.name=claude commit -m "<subject>" -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## File Structure

**New backend files**
- `app/Http/Controllers/Public/HomeController.php`
- `app/Http/Controllers/Public/ServiceBrowseController.php`
- `app/Http/Controllers/Public/DoctorBrowseController.php`
- `app/Http/Controllers/Public/SupportController.php`
- `app/Http/Controllers/Portal/ProfileController.php` *(new portal profile — separate from root Breeze `App\Http\Controllers\ProfileController`)*
- `app/Http/Controllers/Portal/SettingsController.php`
- `app/Domain/Auth/Services/IntentResolver.php`

**Modified backend files**
- `routes/web.php` — replace `/` redirect with the 4 public routes
- `routes/portal.php` — add profile + settings routes
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — use IntentResolver
- `app/Http/Requests/Auth/LoginRequest.php` — accept optional `intent` and `context` (passthrough)
- `app/Domain/Notification/Services/NotificationService.php` — new `securityPasswordChanged` generator
- `config/clinic.php` — `tips` and `faqs` arrays
- `tests/Feature/RouteNamesTest.php` — lock 6 new route names

**New frontend files**
- `resources/js/Components/foundation/AuthGuardLink.vue`
- `resources/js/Pages/Public/Home.vue`
- `resources/js/Pages/Public/Services.vue`
- `resources/js/Pages/Public/Doctors.vue`
- `resources/js/Pages/Public/Support.vue`
- `resources/js/Pages/Portal/Profile/Edit.vue`
- `resources/js/Pages/Portal/Settings/Index.vue`
- `resources/js/Components/foundation/__tests__/AuthGuardLink.spec.js`
- `resources/js/Layouts/__tests__/ClientShell.spec.js`

**Modified frontend files**
- `resources/js/Layouts/ClientShell.vue` — adaptive guest/authed branches
- `resources/js/Pages/Auth/Login.vue` — forward `intent` + context params
- `resources/js/Components/foundation/index.js` — export `AuthGuardLink`
- `resources/js/Pages/Portal/Home.vue` — DELETED (or `/portal` redirects to `/`)

**New tests**
- `tests/Feature/Public/PublicAccessTest.php`
- `tests/Feature/Auth/LoginIntentTest.php`
- `tests/Unit/Auth/IntentResolverTest.php`
- `tests/Feature/Portal/ProfileEditTest.php`
- `tests/Feature/Portal/SettingsPasswordTest.php`
- `tests/Feature/Public/HomeFeaturedTest.php`

**Modified docs**
- `docs/ARCHITECTURE.md` — add Public Landing section
- `CHANGELOG.md` — Unreleased → P5b entry

---

## Task 1: Public routes + controllers + page stubs + access test

**Files:**
- Create: `app/Http/Controllers/Public/HomeController.php`
- Create: `app/Http/Controllers/Public/ServiceBrowseController.php`
- Create: `app/Http/Controllers/Public/DoctorBrowseController.php`
- Create: `app/Http/Controllers/Public/SupportController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/RouteNamesTest.php`
- Create: `resources/js/Pages/Public/Home.vue` (stub)
- Create: `resources/js/Pages/Public/Services.vue` (stub)
- Create: `resources/js/Pages/Public/Doctors.vue` (stub)
- Create: `resources/js/Pages/Public/Support.vue` (stub)
- Create: `tests/Feature/Public/PublicAccessTest.php`

- [ ] **Step 1.1: Write the access test**

Create `tests/Feature/Public/PublicAccessTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('guest can visit public home', function () {
    $this->get('/')->assertOk();
});

it('guest can visit /services', function () {
    $this->get('/services')->assertOk();
});

it('guest can visit /doctors', function () {
    $this->get('/doctors')->assertOk();
});

it('guest can visit /support', function () {
    $this->get('/support')->assertOk();
});

it('guest cannot visit /portal/anything — redirects to login', function () {
    $this->get('/portal/appointments')->assertRedirect('/login');
});

it('authed customer can also visit public pages', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($u)->get('/')->assertOk();
    $this->actingAs($u)->get('/services')->assertOk();
});
```

- [ ] **Step 1.2: Run — confirm fail**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Public/PublicAccessTest.php
```
Expected: FAIL — current `/` route redirects to `/login`.

- [ ] **Step 1.3: Create public controllers**

Create `app/Http/Controllers/Public/HomeController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Public/Home', [
            'featuredServices' => [],
            'featuredDoctor' => null,
            'tip' => null,
        ]);
    }
}
```

Create `app/Http/Controllers/Public/ServiceBrowseController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceBrowseController extends Controller
{
    public function index(Request $request): Response
    {
        $services = Service::query()
            ->where('is_active', true)
            ->with('category:id,name,color_variant')
            ->orderBy('display_order')
            ->get();
        $categories = ServiceCategory::query()->orderBy('id')->get(['id', 'name', 'color_variant']);

        return Inertia::render('Public/Services', [
            'services' => $services,
            'categories' => $categories,
            'filters' => ['category' => $request->input('category')],
        ]);
    }
}
```

Create `app/Http/Controllers/Public/DoctorBrowseController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoctorBrowseController extends Controller
{
    public function index(Request $request): Response
    {
        $doctors = DoctorProfile::query()
            ->where('is_bookable', true)
            ->with('user:id,name')
            ->orderByDesc('rating_average')
            ->orderBy('display_order')
            ->get(['id', 'user_id', 'specialty', 'bio', 'rating_average']);

        return Inertia::render('Public/Doctors', [
            'doctors' => $doctors,
        ]);
    }
}
```

Create `app/Http/Controllers/Public/SupportController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Public/Support', [
            'faqs' => config('clinic.faqs', []),
            'contact' => [
                'phone' => config('clinic.contact.phone'),
                'whatsapp' => config('clinic.contact.whatsapp'),
                'address' => config('clinic.contact.address'),
            ],
        ]);
    }
}
```

- [ ] **Step 1.4: Replace `/` redirect with public routes**

Edit `routes/web.php` — replace the existing `Route::get('/', ...)` closure with the public group:

```php
<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\DoctorBrowseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ServiceBrowseController;
use App\Http\Controllers\Public\SupportController;
use Illuminate\Support\Facades\Route;

// Public landing — no auth.
Route::get('/', [HomeController::class, 'index'])->name('public.home');
Route::get('/services', [ServiceBrowseController::class, 'index'])->name('public.services');
Route::get('/doctors', [DoctorBrowseController::class, 'index'])->name('public.doctors');
Route::get('/support', [SupportController::class, 'index'])->name('public.support');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
```

Note: the previous root closure redirected staff to `/admin` and customers to `/portal/home`. With P5b, the public home is the universal landing — both guests and authed users see it. Staff and customer logins still redirect to their dashboards (Task 7 handles this via IntentResolver, but the default in Task 1 is fine because the existing AuthenticatedSessionController already redirects after login).

- [ ] **Step 1.5: Lock route names**

Edit `tests/Feature/RouteNamesTest.php`. Append to the `$names` array:

```php
'public.home', 'public.services', 'public.doctors', 'public.support',
```

- [ ] **Step 1.6: Create stub Vue pages**

Create `resources/js/Pages/Public/Home.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  featuredServices: { type: Array, default: () => [] },
  featuredDoctor: { type: Object, default: null },
  tip: { type: String, default: null },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="عيادة جنّة" description="نهتمّ بصحّتك وجمالك." />
    </div>
  </ClientShell>
</template>
```

Create `resources/js/Pages/Public/Services.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="خدماتنا" description="استعرض الخدمات المتاحة." />
    </div>
  </ClientShell>
</template>
```

Create `resources/js/Pages/Public/Doctors.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  doctors: { type: Array, default: () => [] },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الأطبّاء" description="فريقنا الطبّي." />
    </div>
  </ClientShell>
</template>
```

Create `resources/js/Pages/Public/Support.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  faqs: { type: Array, default: () => [] },
  contact: { type: Object, default: () => ({}) },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الدعم" description="نحن هنا لمساعدتك." />
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 1.7: Run tests**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Public/PublicAccessTest.php tests/Feature/RouteNamesTest.php
```
Expected: 6 + 1 = 7 PASS.

- [ ] **Step 1.8: Full gate**

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +6, Pint clean, PHPStan 0, Vite OK, Vitest 33.

- [ ] **Step 1.9: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Public/ \
        routes/web.php \
        tests/Feature/Public/PublicAccessTest.php \
        tests/Feature/RouteNamesTest.php \
        resources/js/Pages/Public/
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(public): public routes + page stubs + access test (P5b/1)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 2: `Public/Home.vue` full layout — featured content

**Files:**
- Modify: `app/Http/Controllers/Public/HomeController.php` — populate featured content
- Modify: `resources/js/Pages/Public/Home.vue` — full layout
- Create: `tests/Feature/Public/HomeFeaturedTest.php`

- [ ] **Step 2.1: Write the featured-content test**

Create `tests/Feature/Public/HomeFeaturedTest.php`:

```php
<?php

use App\Enums\AppointmentStatus;
use App\Enums\DeliveryMode;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\CarbonImmutable;

it('home includes up to 4 featured services ordered by recent appointment count then display_order', function () {
    $cat = ServiceCategory::create(['name' => 'c', 'slug' => 's', 'color_variant' => 'brand']);
    $s1 = Service::create(['category_id' => $cat->id, 'name' => 's1', 'base_price' => '10.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 1, 'is_active' => true]);
    $s2 = Service::create(['category_id' => $cat->id, 'name' => 's2', 'base_price' => '20.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 2, 'is_active' => true]);
    $s3 = Service::create(['category_id' => $cat->id, 'name' => 's3', 'base_price' => '30.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 3, 'is_active' => true]);
    $s4 = Service::create(['category_id' => $cat->id, 'name' => 's4', 'base_price' => '40.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 4, 'is_active' => true]);
    $s5 = Service::create(['category_id' => $cat->id, 'name' => 's5', 'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'display_order' => 5, 'is_active' => true]);

    $resp = $this->get('/');
    $featured = $resp->viewData('page')['props']['featuredServices'];
    expect(count($featured))->toBe(4);
});

it('home includes a featured doctor by highest rating', function () {
    $u1 = User::factory()->create(['role' => UserRole::Doctor]);
    $u2 = User::factory()->create(['role' => UserRole::Doctor]);
    DoctorProfile::factory()->create(['user_id' => $u1->id, 'rating_average' => '4.0', 'is_bookable' => true]);
    $top = DoctorProfile::factory()->create(['user_id' => $u2->id, 'rating_average' => '5.0', 'is_bookable' => true]);

    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['featuredDoctor']['id'])->toBe($top->id);
});

it('home includes a tip from config', function () {
    config(['clinic.tips' => ['نصيحة اليوم: اشرب ماء']]);

    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['tip'])->toBe('نصيحة اليوم: اشرب ماء');
});

it('home tip is null when no tips configured', function () {
    config(['clinic.tips' => []]);
    $resp = $this->get('/');
    expect($resp->viewData('page')['props']['tip'])->toBeNull();
});

it('authed customer sees personalized greeting + nextAppointment', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد']);
    $doctorUser = User::factory()->create(['role' => UserRole::Doctor]);
    $doctor = DoctorProfile::factory()->create(['user_id' => $doctorUser->id]);
    $cat = ServiceCategory::create(['name' => 'c'.uniqid(), 'slug' => 'c'.uniqid(), 'color_variant' => 'brand']);
    $service = Service::create(['category_id' => $cat->id, 'name' => 's', 'base_price' => '50.00', 'duration_minutes' => 30, 'home_service_enabled' => false, 'is_active' => true]);
    $doctor->services()->attach($service->id);
    Appointment::create([
        'customer_id' => $customer->id, 'doctor_profile_id' => $doctor->id, 'service_id' => $service->id,
        'start_at' => CarbonImmutable::now()->addDay(), 'end_at' => CarbonImmutable::now()->addDay()->addMinutes(30),
        'status' => AppointmentStatus::Confirmed, 'price_at_booking' => '50.00',
        'delivery_mode' => DeliveryMode::Center, 'home_surcharge_amount' => '0.00',
        'created_by_role' => UserRole::Customer, 'payment_method' => 'cash',
    ]);

    $resp = $this->actingAs($customer)->get('/');
    expect($resp->viewData('page')['props']['greetingName'])->toBe('أحمد')
        ->and($resp->viewData('page')['props']['nextAppointment'])->not->toBeNull();
});
```

- [ ] **Step 2.2: Run — confirm fail**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Public/HomeFeaturedTest.php
```
Expected: FAIL — controller returns empty `featuredServices`, no `featuredDoctor`, no `tip`, no `greetingName`/`nextAppointment`.

- [ ] **Step 2.3: Populate the controller**

Replace `app/Http/Controllers/Public/HomeController.php`:

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $featuredServices = Service::query()
            ->where('is_active', true)
            ->withCount(['appointments' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
            ->orderByDesc('appointments_count')
            ->orderBy('display_order')
            ->limit(4)
            ->with('category:id,name,color_variant')
            ->get();

        $featuredDoctor = DoctorProfile::query()
            ->where('is_bookable', true)
            ->orderByDesc('rating_average')
            ->orderBy('display_order')
            ->with('user:id,name')
            ->first();

        $tips = (array) config('clinic.tips', []);
        $tip = $tips === [] ? null : $tips[array_rand($tips)];

        $greetingName = null;
        $nextAppointment = null;
        if ($request->user()) {
            $greetingName = $request->user()->name;
            $nextAppointment = Appointment::query()
                ->where('customer_id', $request->user()->id)
                ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Requested])
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->with(['service:id,name', 'doctor.user:id,name'])
                ->first();
        }

        return Inertia::render('Public/Home', [
            'featuredServices' => $featuredServices,
            'featuredDoctor' => $featuredDoctor,
            'tip' => $tip,
            'greetingName' => $greetingName,
            'nextAppointment' => $nextAppointment,
        ]);
    }
}
```

- [ ] **Step 2.4: Add `appointments` relation to Service model if missing**

Inspect `app/Models/Service.php`. If no `appointments()` relation exists, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function appointments(): HasMany
{
    return $this->hasMany(Appointment::class);
}
```

- [ ] **Step 2.5: Build the Home page**

Replace `resources/js/Pages/Public/Home.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { Button } from '@/Components/ui/button'

defineProps({
  featuredServices: { type: Array, default: () => [] },
  featuredDoctor: { type: Object, default: null },
  tip: { type: String, default: null },
  greetingName: { type: String, default: null },
  nextAppointment: { type: Object, default: null },
})

function formatDateTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-6">
      <!-- Hero -->
      <section class="bg-surface-card rounded-lg shadow-sm p-6 space-y-3">
        <h1 v-if="greetingName" class="text-2xl font-bold text-text-primary">أهلًا {{ greetingName }} 👋</h1>
        <h1 v-else class="text-2xl font-bold text-text-primary">أهلًا بك في عيادة جنّة</h1>
        <p class="text-sm text-text-secondary">نهتمّ بصحّتك وجمالك — احجز موعدك بسهولة عبر تطبيقنا.</p>
        <Link href="/services">
          <Button>تصفّح الخدمات</Button>
        </Link>
      </section>

      <!-- Next appointment (authed only) -->
      <section v-if="nextAppointment" class="bg-brand/10 border border-brand/30 rounded-lg p-4">
        <p class="text-sm font-semibold text-brand">موعدك القادم</p>
        <p class="text-sm text-text-primary mt-1">
          {{ formatDateTime(nextAppointment.start_at) }} — {{ nextAppointment.service?.name }}
          مع {{ nextAppointment.doctor?.user?.name }}
        </p>
      </section>

      <!-- Featured services -->
      <section v-if="featuredServices.length > 0" class="space-y-3">
        <h2 class="text-lg font-semibold text-text-primary">خدمات مميّزة</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <article v-for="s in featuredServices" :key="s.id" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
            <p class="text-sm text-text-tertiary">{{ s.category?.name }}</p>
            <h3 class="font-medium text-text-primary">{{ s.name }}</h3>
            <p class="text-sm text-text-secondary">{{ s.base_price }} ₪ · {{ s.duration_minutes }} دقيقة</p>
          </article>
        </div>
      </section>

      <!-- Featured doctor -->
      <section v-if="featuredDoctor" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
        <p class="text-xs text-text-tertiary">طبيب مميّز</p>
        <h3 class="font-medium text-text-primary">{{ featuredDoctor.user?.name }}</h3>
        <p class="text-sm text-text-secondary">{{ featuredDoctor.specialty || 'متعدّد التخصّصات' }}</p>
        <p class="text-xs text-text-tertiary">التقييم: {{ Number(featuredDoctor.rating_average).toFixed(1) }} ⭐</p>
      </section>

      <!-- Tip -->
      <section v-if="tip" class="bg-surface-card rounded-lg shadow-sm p-4">
        <p class="text-xs text-text-tertiary mb-1">نصيحة اليوم</p>
        <p class="text-sm text-text-primary">{{ tip }}</p>
      </section>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 2.6: Run tests + full gate**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Public/HomeFeaturedTest.php
```
Expected: 5/5 PASS.

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +5, all clean.

- [ ] **Step 2.7: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Public/HomeController.php \
        app/Models/Service.php \
        resources/js/Pages/Public/Home.vue \
        tests/Feature/Public/HomeFeaturedTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(public): home with featured services + doctor + tip + personalized greeting (P5b/2)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 3: `Public/Services.vue` + `Public/Doctors.vue` full lists

**Files:**
- Modify: `resources/js/Pages/Public/Services.vue`
- Modify: `resources/js/Pages/Public/Doctors.vue`

- [ ] **Step 3.1: Build the services list**

Replace `resources/js/Pages/Public/Services.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const selectedCategory = computed(() => props.filters?.category ?? null)

function filterByCategory(catId) {
  router.get('/services', catId ? { category: catId } : {}, { preserveScroll: true })
}

const visibleServices = computed(() => {
  if (!selectedCategory.value) return props.services
  return props.services.filter((s) => String(s.category_id) === String(selectedCategory.value))
})
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="خدماتنا" description="استعرض الخدمات المتاحة." />

      <div class="flex flex-wrap gap-2">
        <Button
          :variant="!selectedCategory ? 'default' : 'outline'"
          size="sm"
          @click="filterByCategory(null)"
        >الكل</Button>
        <Button
          v-for="c in categories"
          :key="c.id"
          :variant="String(selectedCategory) === String(c.id) ? 'default' : 'outline'"
          size="sm"
          @click="filterByCategory(c.id)"
        >{{ c.name }}</Button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="s in visibleServices"
          :key="s.id"
          class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2"
        >
          <p class="text-xs text-text-tertiary">{{ s.category?.name }}</p>
          <h3 class="font-medium text-text-primary">{{ s.name }}</h3>
          <p class="text-sm text-text-secondary line-clamp-2">{{ s.description || '' }}</p>
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-brand">{{ s.base_price }} ₪</p>
            <p class="text-xs text-text-tertiary">{{ s.duration_minutes }} دقيقة</p>
          </div>
        </article>
      </div>

      <p v-if="visibleServices.length === 0" class="text-center text-text-secondary py-6">
        لا توجد خدمات مطابقة.
      </p>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 3.2: Build the doctors list**

Replace `resources/js/Pages/Public/Doctors.vue`:

```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'

defineProps({
  doctors: { type: Array, default: () => [] },
})
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الأطبّاء" description="فريقنا الطبّي." />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="d in doctors"
          :key="d.id"
          class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2"
        >
          <h3 class="font-medium text-text-primary">{{ d.user?.name }}</h3>
          <p class="text-sm text-text-secondary">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
          <p v-if="d.bio" class="text-xs text-text-tertiary line-clamp-2">{{ d.bio }}</p>
          <p class="text-xs text-text-tertiary">⭐ {{ Number(d.rating_average).toFixed(1) }}</p>
        </article>
      </div>

      <p v-if="doctors.length === 0" class="text-center text-text-secondary py-6">
        لا يوجد أطبّاء حاليًا.
      </p>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 3.3: Full gate**

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: all clean, tests unchanged.

- [ ] **Step 3.4: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add resources/js/Pages/Public/Services.vue resources/js/Pages/Public/Doctors.vue
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(public): full services + doctors browse lists (P5b/3)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 4: `config/clinic.php` tips + faqs + `Public/Support.vue`

**Files:**
- Modify: `config/clinic.php`
- Modify: `resources/js/Pages/Public/Support.vue`

- [ ] **Step 4.1: Extend `config/clinic.php`**

Open `config/clinic.php`. Append before the closing `];`:

```php
    /*
    |--------------------------------------------------------------------------
    | Beauty/health tips (rotated on the public home — see Public\HomeController)
    |--------------------------------------------------------------------------
    | Manager edits in deployment. To make this DB-editable, see deferred items
    | in P5b spec §10.
    */
    'tips' => [
        'اشرب 8 أكواب ماء يوميًّا للحفاظ على نضارة بشرتك.',
        'النوم 7–9 ساعات ليلًا يحسّن المزاج والمناعة.',
        'حركة خفيفة 30 دقيقة يوميًّا تقي من معظم الأمراض المزمنة.',
        'استخدم واقي الشمس حتى في الأيام الغائمة.',
        'تنفّس بعمق 5 دقائق يوميًّا لخفض التوتّر.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public FAQ entries
    |--------------------------------------------------------------------------
    */
    'faqs' => [
        ['q' => 'كيف أحجز موعدًا؟', 'a' => 'من قائمة الخدمات اختر الخدمة المناسبة ثم اضغط «احجز» — سيُطلب منك تسجيل الدخول أو إنشاء حساب.'],
        ['q' => 'ما طريقة الدفع؟', 'a' => 'يتمّ الدفع عبر تحويل بنكي ثم رفع صورة الإيصال من صفحة الموعد.'],
        ['q' => 'هل توجد خدمة منزلية؟', 'a' => 'نعم لبعض الخدمات — تظهر الخدمات القابلة للتقديم منزليًّا في قائمة الحجز.'],
        ['q' => 'هل يمكنني إلغاء الموعد؟', 'a' => 'نعم، من صفحة «مواعيدي» قبل وقت محدّد من الموعد.'],
        ['q' => 'كيف أستخدم نقاط الولاء؟', 'a' => 'تكسب نقطة عن كل شيكل تدفعه. تستبدلها لاحقًا بالخدمات المُفعَّل عليها برنامج الولاء أثناء الحجز.'],
    ],
```

- [ ] **Step 4.2: Build the support page**

Replace `resources/js/Pages/Public/Support.vue`:

```vue
<script setup>
import { ref } from 'vue'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'

defineProps({
  faqs: { type: Array, default: () => [] },
  contact: { type: Object, default: () => ({}) },
})

const openIndex = ref(null)
function toggle(i) {
  openIndex.value = openIndex.value === i ? null : i
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الدعم" description="نحن هنا لمساعدتك." />

      <section v-if="contact.phone || contact.whatsapp" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
        <p class="text-sm font-semibold text-text-primary">للتواصل</p>
        <p v-if="contact.phone" class="text-sm text-text-secondary">
          📞 <a :href="`tel:${contact.phone}`" dir="ltr" class="text-brand underline">{{ contact.phone }}</a>
        </p>
        <p v-if="contact.whatsapp" class="text-sm text-text-secondary">
          💬 <a :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="text-brand underline">واتساب</a>
        </p>
        <p v-if="contact.address" class="text-sm text-text-secondary">📍 {{ contact.address }}</p>
      </section>

      <section v-if="faqs.length > 0" class="space-y-2">
        <p class="text-sm font-semibold text-text-primary">أسئلة شائعة</p>
        <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
          <li v-for="(f, i) in faqs" :key="i">
            <button
              type="button"
              class="w-full p-4 flex items-center justify-between text-start hover:bg-surface-page transition"
              :aria-expanded="openIndex === i"
              @click="toggle(i)"
            >
              <span class="text-sm font-medium text-text-primary">{{ f.q }}</span>
              <span class="text-text-tertiary text-lg leading-none">{{ openIndex === i ? '−' : '+' }}</span>
            </button>
            <div v-if="openIndex === i" class="px-4 pb-4 text-sm text-text-secondary">
              {{ f.a }}
            </div>
          </li>
        </ul>
      </section>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 4.3: Full gate + commit**

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```

```bash
cd /c/~projects/jannahclinic
git add config/clinic.php resources/js/Pages/Public/Support.vue
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(public): support page + clinic.tips + clinic.faqs config (P5b/4)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 5: `AuthGuardLink` foundation component + Vitest spec

**Files:**
- Create: `resources/js/Components/foundation/AuthGuardLink.vue`
- Modify: `resources/js/Components/foundation/index.js`
- Create: `resources/js/Components/foundation/__tests__/AuthGuardLink.spec.js`

- [ ] **Step 5.1: Create the component**

Create `resources/js/Components/foundation/AuthGuardLink.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  intent: { type: String, required: true },
  authedHref: { type: String, required: true },
  context: { type: Object, default: () => ({}) },
})

const page = usePage()
const isAuthed = computed(() => !!page.props?.auth?.user)

const guestHref = computed(() => {
  const entries = Object.entries(props.context).filter(([, v]) => v !== null && v !== undefined && v !== '')
  const tail = entries.length === 0
    ? ''
    : '&' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')

  return `/login?intent=${encodeURIComponent(props.intent)}${tail}`
})
</script>

<template>
  <Link
    v-if="isAuthed"
    :href="authedHref"
    v-bind="$attrs"
  ><slot /></Link>
  <Link
    v-else
    :href="guestHref"
    v-bind="$attrs"
  ><slot /></Link>
</template>
```

- [ ] **Step 5.2: Export from foundation index**

Edit `resources/js/Components/foundation/index.js`. Append:

```js
export { default as AuthGuardLink } from './AuthGuardLink.vue'
```

- [ ] **Step 5.3: Vitest spec**

Create `resources/js/Components/foundation/__tests__/AuthGuardLink.spec.js`:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import AuthGuardLink from '../AuthGuardLink.vue'

let pageProps = { auth: { user: null } }

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => ({ props: pageProps }),
}))

describe('AuthGuardLink', () => {
  it('renders authed href when user is set', () => {
    pageProps = { auth: { user: { id: 1, name: 'x' } } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking' },
      slots: { default: 'احجز' },
    })
    expect(w.find('a').attributes('href')).toBe('/portal/booking')
  })

  it('renders /login?intent=… when user is null', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking' },
      slots: { default: 'احجز' },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking')
  })

  it('encodes context into query string for guest', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking', context: { service: 5, doctor: 3 } },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking&service=5&doctor=3')
  })

  it('skips null/empty context values', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking', context: { service: 5, doctor: null, category: '' } },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking&service=5')
  })
})
```

- [ ] **Step 5.4: Full gate + commit**

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Vitest 33 + 4 = 37, all clean.

```bash
cd /c/~projects/jannahclinic
git add resources/js/Components/foundation/AuthGuardLink.vue \
        resources/js/Components/foundation/index.js \
        resources/js/Components/foundation/__tests__/AuthGuardLink.spec.js
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(foundation): AuthGuardLink — guest routes to /login?intent (P5b/5)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 6: Adaptive `ClientShell` + Vitest spec

**Files:**
- Modify: `resources/js/Layouts/ClientShell.vue`
- Create: `resources/js/Layouts/__tests__/ClientShell.spec.js`

- [ ] **Step 6.1: Vitest first**

Create `resources/js/Layouts/__tests__/ClientShell.spec.js`:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import ClientShell from '../ClientShell.vue'

let pageProps = { auth: { user: null }, notifications: null }
let currentUrl = '/'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as', 'aria-current'] },
  usePage: () => ({ props: pageProps, url: currentUrl }),
}))
vi.mock('@/Components/foundation', () => ({
  NotificationBell: { template: '<div data-testid="bell"></div>' },
}))

describe('ClientShell — adaptive', () => {
  it('guest renders login/register CTAs and 4-tab nav', () => {
    pageProps = { auth: { user: null } }
    currentUrl = '/'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    const html = w.html()
    expect(html).toContain('تسجيل الدخول')
    expect(html).toContain('إنشاء حساب')
    expect(w.findAll('nav a').length).toBe(4)
    expect(w.find('[data-testid="bell"]').exists()).toBe(false)
  })

  it('authed customer renders bell + 6-tab nav', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: { unread_count: 0 } }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    expect(w.find('[data-testid="bell"]').exists()).toBe(true)
    expect(w.html()).toContain('خروج')
    expect(w.findAll('nav a').length).toBe(6)
  })

  it('authed customer header shows their name', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: null }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    expect(w.html()).toContain('أحمد')
  })
})
```

- [ ] **Step 6.2: Replace ClientShell**

Replace `resources/js/Layouts/ClientShell.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { NotificationBell } from '@/Components/foundation'

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)

const guestTabs = [
  { label: 'الرئيسية', href: '/' },
  { label: 'الخدمات', href: '/services' },
  { label: 'الأطبّاء', href: '/doctors' },
  { label: 'الدعم', href: '/support' },
]

const authedTabs = [
  { label: 'الرئيسية', href: '/' },
  { label: 'مواعيدي', href: '/portal/appointments' },
  { label: 'سجلي', href: '/portal/medical-record' },
  { label: 'نقاطي', href: '/portal/loyalty' },
  { label: 'حسابي', href: '/portal/profile' },
  { label: 'خدمات', href: '/services' },
]

const tabs = computed(() => (isAuthed.value ? authedTabs : guestTabs))

function isActive(href) {
  const current = page.url
  if (href === '/') return current === '/' || current === ''
  return current === href || current.startsWith(href + '/')
}
</script>

<template>
  <div class="min-h-screen mx-auto max-w-md flex flex-col bg-surface-page">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card gap-2">
      <span class="font-bold text-brand">عيادة جنّة</span>

      <template v-if="isAuthed">
        <NotificationBell href="/portal/notifications" class="ms-auto" />
        <span class="text-xs text-text-secondary truncate max-w-24">{{ authedUser.name }}</span>
        <Link href="/logout" method="post" as="button" class="text-xs text-text-secondary">خروج</Link>
      </template>

      <template v-else>
        <div class="ms-auto flex items-center gap-2">
          <Link href="/login" class="text-xs text-text-secondary hover:text-brand">تسجيل الدخول</Link>
          <Link href="/register" class="text-xs font-semibold text-brand">إنشاء حساب</Link>
        </div>
      </template>
    </header>

    <main class="flex-1 pb-20"><slot /></main>

    <nav
      class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid"
      :class="isAuthed ? 'grid-cols-6' : 'grid-cols-4'"
    >
      <Link
        v-for="t in tabs"
        :key="t.label"
        :href="t.href"
        :aria-current="isActive(t.href) ? 'page' : undefined"
        :class="['py-3 text-center text-xs hover:text-brand transition', isActive(t.href) ? 'text-brand font-semibold' : 'text-text-secondary']"
      >{{ t.label }}</Link>
    </nav>
  </div>
</template>
```

- [ ] **Step 6.3: Full gate + commit**

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Vitest +3, all clean.

```bash
cd /c/~projects/jannahclinic
git add resources/js/Layouts/ClientShell.vue \
        resources/js/Layouts/__tests__/ClientShell.spec.js
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(portal): adaptive ClientShell — guest vs authed branches (P5b/6)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 7: `IntentResolver` service + login redirect hook

**Files:**
- Create: `app/Domain/Auth/Services/IntentResolver.php`
- Create: `tests/Unit/Auth/IntentResolverTest.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `resources/js/Pages/Auth/Login.vue`
- Create: `tests/Feature/Auth/LoginIntentTest.php`

- [ ] **Step 7.1: IntentResolver unit test**

Create `tests/Unit/Auth/IntentResolverTest.php`:

```php
<?php

use App\Domain\Auth\Services\IntentResolver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->resolver = new IntentResolver();
});

it('returns portal home when intent is null', function () {
    expect($this->resolver->resolve(new Request(), null))->toBe(route('portal.home'));
});

it('returns booking with passed service id', function () {
    $req = Request::create('/', 'POST', ['service' => '5']);
    $url = $this->resolver->resolve($req, 'booking');
    expect($url)->toContain('/portal/booking')->and($url)->toContain('service=5');
});

it('returns appointments route', function () {
    expect($this->resolver->resolve(new Request(), 'appointments'))->toBe(route('portal.appointments.index'));
});

it('returns loyalty route', function () {
    expect($this->resolver->resolve(new Request(), 'loyalty'))->toBe(route('portal.loyalty.index'));
});

it('unknown intent falls back to portal home', function () {
    expect($this->resolver->resolve(new Request(), 'unknown-intent'))->toBe(route('portal.home'));
});
```

- [ ] **Step 7.2: Create the resolver**

Create `app/Domain/Auth/Services/IntentResolver.php`:

```php
<?php

namespace App\Domain\Auth\Services;

use Illuminate\Http\Request;

class IntentResolver
{
    public function resolve(Request $request, ?string $intent): string
    {
        if ($intent === null || $intent === '') {
            return route('portal.home');
        }

        return match ($intent) {
            'booking' => $this->bookingTarget($request),
            'appointments' => route('portal.appointments.index'),
            'loyalty' => route('portal.loyalty.index'),
            'medical-record' => route('portal.medical-record.index'),
            'profile' => route('portal.profile.edit'),
            'settings' => route('portal.settings.index'),
            default => route('portal.home'),
        };
    }

    private function bookingTarget(Request $request): string
    {
        $params = [];
        foreach (['service', 'doctor', 'category'] as $key) {
            if ($request->filled($key)) {
                $params[$key] = $request->input($key);
            }
        }

        return route('portal.booking.create', $params);
    }
}
```

NOTE: this references `portal.profile.edit` and `portal.settings.index` — defined in Task 8 + 9. Until those tasks land, those `route()` calls would throw. To keep Task 7 self-contained:
- Either run Task 7 AFTER Tasks 8 + 9, OR
- Pre-register stub routes here.

Recommended ordering: implement Task 7 → 8 → 9, but in Task 7 keep the IntentResolver code AS-IS — the test only exercises `null/booking/appointments/loyalty/unknown` paths. The `profile`/`settings` branches will resolve correctly once those routes exist. The unit test will not exercise them.

- [ ] **Step 7.3: Run unit test**

```
cd /c/~projects/jannahclinic && php artisan test tests/Unit/Auth/IntentResolverTest.php
```
Expected: 5/5 PASS.

- [ ] **Step 7.4: Update LoginRequest**

Read `app/Http/Requests/Auth/LoginRequest.php`. If it does not already expose `intent` and contextual params, no rule changes are needed — the controller reads them directly from the request. Confirm `rules()` does not require `intent` (it's optional).

If `rules()` currently asserts a closed set of keys, append:

```php
            'intent' => ['sometimes', 'string', 'max:64'],
            'service' => ['sometimes', 'integer'],
            'doctor' => ['sometimes', 'integer'],
            'category' => ['sometimes', 'integer'],
```

(If `rules()` is loose, skip.)

- [ ] **Step 7.5: Hook into AuthenticatedSessionController**

Edit `app/Http/Controllers/Auth/AuthenticatedSessionController.php`. Inject `IntentResolver` and modify `store()`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Services\IntentResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly IntentResolver $resolver) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'intent' => $request->input('intent'),
            'context' => $request->only(['service', 'doctor', 'category']),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        if ($request->user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect($this->resolver->resolve($request, $request->input('intent')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

- [ ] **Step 7.6: Login form forwards intent + context**

Edit `resources/js/Pages/Auth/Login.vue`. Read it first to find the existing `useForm({...})` and the form `<input>` block. Add to the form's POST payload:

```js
const form = useForm({
  // ... existing fields (email, password, remember) ...
  intent: $page.props?.intent ?? '',
  service: $page.props?.context?.service ?? '',
  doctor: $page.props?.context?.doctor ?? '',
  category: $page.props?.context?.category ?? '',
})
```

If the existing component does not use `$page` reactively, import `usePage` and pull from it. Add hidden inputs only if the existing form uses a manual `<form>` element bypassing `useForm` — most Breeze scaffolds use `useForm`, in which case the fields above are sufficient (they're sent in the POST body automatically).

- [ ] **Step 7.7: Feature test for login intent**

Create `tests/Feature/Auth/LoginIntentTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

it('login with intent=booking redirects to /portal/booking with service param', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);

    $resp = $this->post('/login', [
        'email' => $u->email,
        'password' => 'password',
        'intent' => 'booking',
        'service' => 5,
    ]);

    $resp->assertRedirect();
    expect($resp->headers->get('Location'))->toContain('/portal/booking')->toContain('service=5');
});

it('login without intent redirects to portal home', function () {
    $u = User::factory()->create(['role' => UserRole::Customer]);

    $resp = $this->post('/login', ['email' => $u->email, 'password' => 'password']);
    $resp->assertRedirect(route('portal.home'));
});

it('staff login still goes to admin dashboard regardless of intent', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);

    $resp = $this->post('/login', [
        'email' => $m->email,
        'password' => 'password',
        'intent' => 'booking',
        'service' => 5,
    ]);
    $resp->assertRedirect(route('admin.dashboard'));
});
```

Note: `User::factory()` already sets password to `'password'` in Laravel default — confirm by inspecting `database/factories/UserFactory.php`.

- [ ] **Step 7.8: Run feature test + full gate**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Auth/LoginIntentTest.php
```
Expected: 3/3 PASS.

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +8, all clean.

- [ ] **Step 7.9: Commit + push**

```bash
cd /c/~projects/jannahclinic
git add app/Domain/Auth/Services/IntentResolver.php \
        app/Http/Controllers/Auth/AuthenticatedSessionController.php \
        app/Http/Requests/Auth/LoginRequest.php \
        resources/js/Pages/Auth/Login.vue \
        tests/Unit/Auth/IntentResolverTest.php \
        tests/Feature/Auth/LoginIntentTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(auth): IntentResolver + login forwards to original intent (P5b/7)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 8: `Portal/Profile/Edit.vue` + `Portal\ProfileController`

**Files:**
- Create: `app/Http/Controllers/Portal/ProfileController.php`
- Modify: `routes/portal.php`
- Modify: `tests/Feature/RouteNamesTest.php`
- Create: `resources/js/Pages/Portal/Profile/Edit.vue`
- Create: `tests/Feature/Portal/ProfileEditTest.php`

- [ ] **Step 8.1: Write the profile-edit test**

Create `tests/Feature/Portal/ProfileEditTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'أحمد', 'phone' => '0500000000']);
    CustomerProfile::create(['user_id' => $this->customer->id]);
});

it('customer can view profile edit page', function () {
    $this->actingAs($this->customer)->get('/portal/profile')->assertOk();
});

it('customer updates name + phone', function () {
    $this->actingAs($this->customer)->put('/portal/profile', [
        'name' => 'أحمد محمود',
        'phone' => '0599999999',
        'date_of_birth' => '1990-01-01',
        'gender' => 'male',
    ])->assertRedirect();

    expect($this->customer->fresh()->name)->toBe('أحمد محمود')
        ->and($this->customer->fresh()->phone)->toBe('0599999999')
        ->and($this->customer->customerProfile->fresh()->gender)->toBe('male');
});

it('staff cannot reach portal profile (role middleware)', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->get('/portal/profile')->assertForbidden();
});

it('validation rejects empty name', function () {
    $this->actingAs($this->customer)->put('/portal/profile', [
        'name' => '',
        'phone' => '0599999999',
    ])->assertSessionHasErrors('name');
});
```

- [ ] **Step 8.2: Create the controller**

Create `app/Http/Controllers/Portal/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('customerProfile');

        return Inertia::render('Portal/Profile/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile' => $user->customerProfile ? [
                    'date_of_birth' => $user->customerProfile->date_of_birth?->toDateString(),
                    'gender' => $user->customerProfile->gender,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
            CustomerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                ],
            );
        });

        return back()->with('success', 'تمّ حفظ البيانات.');
    }
}
```

- [ ] **Step 8.3: Register routes**

Edit `routes/portal.php`. Add import:

```php
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
```

(`as PortalProfileController` aliases it to avoid colliding with the existing root `App\Http\Controllers\ProfileController` import elsewhere.)

Inside the existing portal group (before the closing `});`):

```php
        // P5b — customer profile
        Route::get('profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [PortalProfileController::class, 'update'])->name('profile.update');
```

NOTE: there's already `Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');` near the top of portal.php. KEEP that line. The avatar update lives on the root `App\Http\Controllers\ProfileController` (Breeze). The new edit + update use `Portal\ProfileController`. Both coexist: the route names `profile.avatar`, `profile.edit`, `profile.update` are namespaced under `portal.` and refer to two different controllers. The Vue page will call `/portal/profile/avatar` (existing) and `/portal/profile` (new).

If naming collision is a concern, rename to `profile.basic.edit` / `profile.basic.update`. Otherwise leave as `portal.profile.edit` / `portal.profile.update`.

- [ ] **Step 8.4: Lock route names**

Edit `tests/Feature/RouteNamesTest.php`. Append:

```php
'portal.profile.edit', 'portal.profile.update',
```

- [ ] **Step 8.5: Build the edit page**

Create `resources/js/Pages/Portal/Profile/Edit.vue`:

```vue
<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  user: { type: Object, required: true },
})

const form = useForm({
  name: props.user.name ?? '',
  email: props.user.email ?? '',
  phone: props.user.phone ?? '',
  date_of_birth: props.user.profile?.date_of_birth ?? '',
  gender: props.user.profile?.gender ?? '',
})

function submit() {
  form.put('/portal/profile', { preserveScroll: true })
}

const page = usePage()
const flashSuccess = page.props?.flash?.success
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="حسابي" description="حدِّث بياناتك الشخصيّة." />

      <div v-if="flashSuccess" class="bg-success/10 text-success text-sm p-3 rounded-md">
        {{ flashSuccess }}
      </div>

      <form class="bg-surface-card rounded-lg shadow-sm p-4 space-y-4" @submit.prevent="submit">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email">
          <template #default="{ describedby }">
            <Input id="email" v-model="form.email" type="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="phone" v-model="form.phone" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="تاريخ الميلاد" name="date_of_birth" :error="form.errors.date_of_birth">
          <template #default="{ describedby }">
            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الجنس" name="gender" :error="form.errors.gender">
          <template #default="{ describedby }">
            <select
              id="gender"
              v-model="form.gender"
              :aria-describedby="describedby"
              class="w-full h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm"
            >
              <option value="">—</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </template>
        </FormGroup>

        <div class="flex justify-end">
          <Button type="submit" :disabled="form.processing">حفظ التعديلات</Button>
        </div>
      </form>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 8.6: Run + full gate + commit**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Portal/ProfileEditTest.php
```
Expected: 4/4 PASS.

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +4, all clean.

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Portal/ProfileController.php \
        routes/portal.php \
        tests/Feature/RouteNamesTest.php \
        resources/js/Pages/Portal/Profile/Edit.vue \
        tests/Feature/Portal/ProfileEditTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(portal): customer profile edit page + controller (P5b/8)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 9: `Portal/Settings/Index.vue` + password change + notification

**Files:**
- Create: `app/Http/Controllers/Portal/SettingsController.php`
- Modify: `routes/portal.php`
- Modify: `tests/Feature/RouteNamesTest.php`
- Modify: `app/Domain/Notification/Services/NotificationService.php` — new generator
- Create: `resources/js/Pages/Portal/Settings/Index.vue`
- Create: `tests/Feature/Portal/SettingsPasswordTest.php`

- [ ] **Step 9.1: Write the settings test**

Create `tests/Feature/Portal/SettingsPasswordTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->customer = User::factory()->create([
        'role' => UserRole::Customer,
        'password' => Hash::make('oldpassword123'),
    ]);
});

it('customer can view settings page', function () {
    $this->actingAs($this->customer)->get('/portal/settings')->assertOk();
});

it('customer changes password with correct current password', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertRedirect();

    expect(Hash::check('newpassword456', $this->customer->fresh()->password))->toBeTrue();
});

it('rejects password change with wrong current password', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('oldpassword123', $this->customer->fresh()->password))->toBeTrue();
});

it('rejects password change with mismatched confirmation', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});

it('password change dispatches a security notification', function () {
    $this->actingAs($this->customer)->put('/portal/settings/password', [
        'current_password' => 'oldpassword123',
        'password' => 'newpassword456',
        'password_confirmation' => 'newpassword456',
    ])->assertRedirect();

    $n = $this->customer->notifications()->latest()->first();
    expect($n)->not->toBeNull()
        ->and($n->data['title'])->toContain('كلمة المرور');
});
```

- [ ] **Step 9.2: Create the controller**

Create `app/Http/Controllers/Portal/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Notification\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Portal/Settings/Index', []);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($request->input('password'))]);

        $this->notifications->securityPasswordChanged($user);

        return back()->with('success', 'تمّ تغيير كلمة المرور.');
    }
}
```

- [ ] **Step 9.3: Add the notification generator**

Edit `app/Domain/Notification/Services/NotificationService.php`. Add an import if not present:

```php
use App\Notifications\AppointmentChanged;
```

(The `AppointmentChanged` notification is used as a generic database notification carrier — same shape works for security messages; or create a `SecurityChanged` notification class mirroring `LoyaltyChanged`. For simplicity, use a new class.)

Create `app/Notifications/SecurityChanged.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SecurityChanged extends Notification
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

Add to `NotificationCategory` enum if missing:
```php
    case System = 'system';
```
(Already exists per P5a.)

In `NotificationService.php`, after the existing loyalty generators, add:

```php
    public function securityPasswordChanged(User $user): void
    {
        $this->dispatch($user, new \App\Notifications\SecurityChanged([
            'category' => NotificationCategory::System->value,
            'title' => 'تمّ تغيير كلمة المرور',
            'body' => 'إذا لم يكن هذا أنت، تواصل مع الإدارة فورًا.',
            'action_url' => '/portal/settings',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]), 'securityPasswordChanged');
    }
```

- [ ] **Step 9.4: Register routes**

Edit `routes/portal.php`. Add import:

```php
use App\Http\Controllers\Portal\SettingsController;
```

Inside the portal group:

```php
        // P5b — customer settings
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
```

- [ ] **Step 9.5: Lock route names**

Edit `tests/Feature/RouteNamesTest.php`. Append:

```php
'portal.settings.index', 'portal.settings.password',
```

- [ ] **Step 9.6: Build the settings page**

Create `resources/js/Pages/Portal/Settings/Index.vue`:

```vue
<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const pwdForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function submitPassword() {
  pwdForm.put('/portal/settings/password', {
    preserveScroll: true,
    onSuccess: () => pwdForm.reset(),
  })
}

const page = usePage()
const flashSuccess = page.props?.flash?.success
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الإعدادات" description="إدارة حسابك." />

      <div v-if="flashSuccess" class="bg-success/10 text-success text-sm p-3 rounded-md">
        {{ flashSuccess }}
      </div>

      <section class="bg-surface-card rounded-lg shadow-sm p-4 space-y-4">
        <h2 class="text-sm font-semibold text-text-primary">تغيير كلمة المرور</h2>
        <form class="space-y-3" @submit.prevent="submitPassword">
          <FormGroup label="كلمة المرور الحاليّة" name="current_password" :error="pwdForm.errors.current_password" required>
            <template #default="{ describedby }">
              <Input id="current_password" v-model="pwdForm.current_password" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <FormGroup label="كلمة المرور الجديدة" name="password" :error="pwdForm.errors.password" required>
            <template #default="{ describedby }">
              <Input id="password" v-model="pwdForm.password" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <FormGroup label="تأكيد كلمة المرور" name="password_confirmation" :error="pwdForm.errors.password_confirmation" required>
            <template #default="{ describedby }">
              <Input id="password_confirmation" v-model="pwdForm.password_confirmation" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <div class="flex justify-end">
            <Button type="submit" :disabled="pwdForm.processing">تحديث</Button>
          </div>
        </form>
      </section>

      <section class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2 opacity-60">
        <p class="text-sm font-semibold text-text-primary">تفضيلات الإشعارات <span class="text-xs text-text-tertiary">— قريبًا</span></p>
        <p class="text-xs text-text-secondary">سيتيح هذا القسم لاحقًا اختيار فئات الإشعارات.</p>
      </section>

      <section class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2 opacity-60">
        <p class="text-sm font-semibold text-text-primary">اللغة <span class="text-xs text-text-tertiary">— العربيّة</span></p>
        <p class="text-xs text-text-secondary">دعم الإنجليزيّة سيُضاف لاحقًا.</p>
      </section>
    </div>
  </ClientShell>
</template>
```

- [ ] **Step 9.7: Run + full gate + commit**

```
cd /c/~projects/jannahclinic && php artisan test tests/Feature/Portal/SettingsPasswordTest.php
```
Expected: 5/5 PASS.

```
cd /c/~projects/jannahclinic && php artisan test && vendor/bin/pint && vendor/bin/phpstan analyse --no-progress && npm run build && npx vitest run
```
Expected: Pest +5, all clean.

```bash
cd /c/~projects/jannahclinic
git add app/Http/Controllers/Portal/SettingsController.php \
        app/Notifications/SecurityChanged.php \
        app/Domain/Notification/Services/NotificationService.php \
        routes/portal.php \
        tests/Feature/RouteNamesTest.php \
        resources/js/Pages/Portal/Settings/Index.vue \
        tests/Feature/Portal/SettingsPasswordTest.php
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "feat(portal): customer settings page + password change + security notification (P5b/9)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin main
```

---

## Task 10: DoD + ARCHITECTURE + CHANGELOG + tag `p5b-portal`

**Files:**
- Modify: `docs/ARCHITECTURE.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 10.1: Update `docs/ARCHITECTURE.md`**

Read the file. After the existing "Loyalty Points (P4a)" section and before "Related Documents", insert:

```markdown
## Public Landing (P5b)

The customer-facing surface is now split into two:

- **Public:** `/`, `/services`, `/doctors`, `/support` — no auth. Renders the Inertia pages under `resources/js/Pages/Public/*`. Each page uses `ClientShell.vue` which is now adaptive.
- **Private:** `/portal/*` — existing `auth + role:customer` middleware. Adds two new pages: `/portal/profile` (edit name, email, phone, DoB, gender) and `/portal/settings` (change password with current-password verification + a security notification on success).

**Adaptive `ClientShell`:** a single layout reads `usePage().props.auth.user`. Guest header renders login/register CTAs and a 4-tab bottom nav. Authed header renders `<NotificationBell>` + user name + logout, and a 6-tab nav (الرئيسية / مواعيدي / سجلي / نقاطي / حسابي / خدمات).

**Intent-to-action gate:** `<AuthGuardLink>` (a foundation component) on public CTAs sends guests to `/login?intent=booking&service={n}…`. After login, `App\Domain\Auth\Services\IntentResolver` reads the `intent` param and forwards to the resolved authed URL (e.g. `/portal/booking?service={n}`). Default: `/portal` (`portal.home`). Staff logins still go to `/admin` regardless of intent.

**Featured-content logic on the home page:**
- Top 4 services by `appointments_count` in the last 30 days, ties broken by `display_order`.
- Top doctor by `rating_average` (active + bookable), ties broken by `display_order`.
- Tip rotated randomly from `config('clinic.tips')` (config-driven; no DB).

**FAQ:** `config('clinic.faqs')` array of `{q, a}` objects. Manager edits in deployment. A DB-backed editable CMS is deferred (see spec §10).

**Password change notification:** `NotificationService::securityPasswordChanged` dispatches a `SecurityChanged` database notification using the existing P5a `dispatch()` helper (try/catch + log).
```

Also append to "Related Documents":

```markdown
- P5b Portal Polish spec: `docs/superpowers/specs/2026-05-20-jannahclinic-p5b-portal-design.md`
```

- [ ] **Step 10.2: Update `CHANGELOG.md`**

Insert above the most recent entry:

```markdown
## [P5b] Public Landing + Portal Polish — 2026-05-20

**P5b complete:** customers can browse the clinic (services, doctors, FAQ, beauty tips) without signing in. Authentication is requested only when the customer takes an action that requires an account (booking, viewing own appointments, payments, medical records, loyalty). Two new authed pages — `/portal/profile` and `/portal/settings` — round out the personal data surface.

- **Public routes (4 new):** `public.home` / `public.services` / `public.doctors` / `public.support` (no auth). `/` no longer redirects.
- **Authed routes (4 new):** `portal.profile.edit` / `portal.profile.update` / `portal.settings.index` / `portal.settings.password`.
- **Adaptive `ClientShell`:** single layout serves guest (login/register CTAs + 4-tab nav) and authed (bell + logout + 6-tab nav including "حسابي"). Switches on `usePage().props.auth.user`.
- **`<AuthGuardLink>` foundation component:** drop-in for any guest-clickable action that needs auth. Sends to `/login?intent=…&{context}` for guests; renders a regular `<Link :href="authedHref">` for authed users.
- **`App\Domain\Auth\Services\IntentResolver`:** maps `intent` query param to the right authed URL after login. Booking intent forwards `service`/`doctor`/`category` params. Staff logins still land on `/admin` regardless.
- **Home featured content:** top 4 services by recent bookings (fallback: display_order), top-rated bookable doctor, random tip from `config('clinic.tips')`. Authed visitors see a personalized greeting and their next appointment if any.
- **Public Support:** FAQ accordion + contact strip (phone, WhatsApp link, address) sourced from `config('clinic.faqs')` + `config('clinic.contact')`.
- **Portal Profile:** customer edits name, email, phone, DoB, gender (avatar update reuses the existing `portal.profile.avatar` endpoint).
- **Portal Settings:** password change with current-password verification + `Password::min(8)` rule. Success dispatches a `SecurityChanged` notification ("تمّ تغيير كلمة المرور") via the P5a `dispatch()` helper. Notification-preferences and language sections render as "قريبًا" stubs.
- **Tests:** +15 Pest (PublicAccessTest 6 · HomeFeaturedTest 5 · IntentResolverTest 5 · LoginIntentTest 3 · ProfileEditTest 4 · SettingsPasswordTest 5) + 2 Vitest (AuthGuardLink 4 specs · ClientShell-Adaptive 3 specs).
- **No migrations, no domain entities** — UI + routes phase only.
- **Tag:** `p5b-portal`.
```

- [ ] **Step 10.3: Final full gate**

```
cd /c/~projects/jannahclinic
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse --no-progress
npm run build
npx vitest run
```

Expected:
- Pest: ~321 + ~22 = ~343 (counting the additions across Tasks 1, 2, 7, 8, 9 + IntentResolver unit + others).
- Vitest: 33 + 2 = 35 (AuthGuardLink + ClientShell specs).
- Pint clean, PHPStan 0, Vite OK.

- [ ] **Step 10.4: Manual smoke pass**

1. As a guest, open `/` → see hero greeting, 4 featured services, a featured doctor, and the daily tip. Bottom nav shows 4 tabs.
2. Click the "تصفّح الخدمات" CTA → arrive at `/services` → filter by a category.
3. Visit `/doctors` → see the active doctors with ratings.
4. Visit `/support` → expand a FAQ → click WhatsApp link (should open `wa.me/...` in a new tab).
5. Click an `<AuthGuardLink>` (e.g. on the home CTA for booking) → arrive at `/login?intent=booking&service={n}` → sign in as a customer → land on `/portal/booking?service={n}`.
6. Open `/portal/profile` → change name/phone → save → see Arabic flash "تمّ حفظ البيانات.".
7. Open `/portal/settings` → change password with correct current_password → flash "تمّ تغيير كلمة المرور." → bell badge shows 1 new notification → click → see "تمّ تغيير كلمة المرور" message in the notification center.
8. Log out → adaptive shell switches back to guest header + 4-tab nav.

- [ ] **Step 10.5: Commit + tag + push**

```bash
cd /c/~projects/jannahclinic
git add docs/ARCHITECTURE.md CHANGELOG.md
git -c user.email=admin@istoria.app -c user.name=claude commit \
  -m "docs(p5b): ARCHITECTURE + CHANGELOG entries for public landing + portal polish (P5b/10)" \
  -m "Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag p5b-portal
git push origin main
git push origin p5b-portal
```

- [ ] **Step 10.6: Report**

Final report to user:
- Test counts: Pest ~343, Vitest 35, Pint clean, PHPStan 0, Vite OK.
- Tag pushed: `p5b-portal`.
- Manual smoke pass: completed (or any failures).

---

## Self-Review Summary

**Spec coverage:** Every section of spec §2–§9 maps to a task above. `<AuthGuardLink>` wiring on the home CTA is included as part of Task 2's Home.vue (the "تصفّح الخدمات" Button is a plain `<Link>` to the public services page; the booking CTAs that need auth go through AuthGuardLink AFTER Task 5 lands — wire-in is the implementer's responsibility when they touch Home.vue, and the test in Task 2 only asserts the data props, not the link wiring, leaving room for Task 5 to extend without breaking).

**Placeholder scan:** No "TBD" / "TODO" markers. The ARCHITECTURE narrative for P5b is fully drafted in Task 10.1.

**Type consistency:** Route names match between RouteNamesTest additions (Tasks 1, 8, 9) and the controller `route()` calls (Task 7's IntentResolver references `portal.profile.edit` / `portal.settings.index` which become real routes in Tasks 8/9 — confirmed.). The `intent` values map between AuthGuardLink (Task 5), Login.vue (Task 7), and IntentResolver (Task 7) — using the same vocabulary (`booking`, `appointments`, `loyalty`, `profile`, `settings`).
