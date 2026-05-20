# jannahclinic — P5b: Public Landing + Customer Portal Polish — Design

> **Status:** DRAFT — pending user review
> **Date:** 2026-05-20
> **Builds on:** P0 (auth + shells), P1 (services + doctors), P5a (notifications + portal nav).
> **Scope of P5 split:** Original P0 roadmap row `P5 — الإشعارات والبوابة` is now split:
>
> - **P5a (shipped):** in-app notification system.
> - **P5b (this spec):** public landing surface + adaptive portal shell + intent-to-action login gate + new `Profile` and `Settings` pages.
>
> **Out of P5b (explicitly):**
> - Manager-editable tips/FAQ (config-driven for MVP; DB-backed CMS deferred).
> - Standalone `/tips` page (a single tip card embedded on the home page is sufficient for now).
> - Membership purchase flow (P4b — independent sub-project).
> - SMS/email notification channels.
> - Customer-facing notification-preference controls (defer until real opt-out demand).

---

## 1. Motivation & decisions locked

Today the entire customer surface lives under `/portal/*` and is gated by `auth + role:customer`. A new visitor cannot see the clinic's services, doctors, or even the brand without first signing up — a hard friction point for acquisition. The clinic needs a public landing site that lets anyone browse, then asks for login only when the user attempts an action that genuinely needs an account (booking, viewing own appointments, payment uploads, medical records, loyalty).

Five decisions lock the shape of this work:

1. **URL split, not URL reuse.** Public routes live at the root (`/`, `/services`, `/doctors`, `/support`). Customer-private routes stay under `/portal/*`. A guest visiting `/portal/anything` redirects to the public home.
2. **Adaptive `ClientShell`, not a separate shell.** A single layout reads `auth.user`; if absent, it renders the guest header (login/register CTAs + 4-tab nav) and if present, the authed header (bell + logout + 6-tab nav including "نقاطي").
3. **Intent-to-action login gate.** Guest clicks "احجز" → routed to `/login?intent=booking[&service=N&doctor=M]` → after authentication, the login redirect handler reads `intent` and forwards to the matching authed route. Same pattern for any guest-clicked authed action.
4. **Config-driven tips & FAQ.** `config/clinic.php` gains `tips` (array of short strings) and `faqs` (array of `{q,a}` objects). Manager edits in deployment. A DB-backed CMS is deferred.
5. **No new domain entities or migrations.** `Service`, `DoctorProfile`, `User`, `CustomerProfile` already carry everything the new pages need. P5b is a UI+routes phase.

---

## 2. Architecture & file structure

### 2.1 Routes

Two new groups + extensions to the existing portal group.

**New: `routes/web.php` public group (no middleware beyond `web`)**

| Method | Path | Name | Controller |
|---|---|---|---|
| GET | `/` | `public.home` | `Public\HomeController@index` |
| GET | `/services` | `public.services` | `Public\ServiceBrowseController@index` |
| GET | `/doctors` | `public.doctors` | `Public\DoctorBrowseController@index` |
| GET | `/support` | `public.support` | `Public\SupportController@index` |

(`/login`, `/register` already exist via Laravel Breeze-style auth scaffolding.)

**Extensions to existing `routes/portal.php` (auth + role:customer):**

| Method | Path | Name | Controller |
|---|---|---|---|
| GET | `/portal/profile` | `portal.profile.edit` | `Portal\ProfileController@edit` |
| PUT | `/portal/profile` | `portal.profile.update` | `Portal\ProfileController@update` |
| GET | `/portal/settings` | `portal.settings.index` | `Portal\SettingsController@index` |
| PUT | `/portal/settings/password` | `portal.settings.password` | `Portal\SettingsController@updatePassword` |

(`Portal\ProfileController::updateAvatar` already exists at `portal.profile.avatar`; it stays.)

**Guest landing on `/portal`:** if `auth()->check()` is false, redirect to `/`. Otherwise serve the existing `Portal/Home.vue` (which is the authed dashboard — see §2.4).

All 6 new route names are locked in `tests/Feature/RouteNamesTest.php`.

### 2.2 Controllers (new)

```
app/Http/Controllers/Public/
├── HomeController.php
├── ServiceBrowseController.php       (intentionally separate from Portal\ServiceBrowseController)
├── DoctorBrowseController.php
└── SupportController.php

app/Http/Controllers/Portal/
├── ProfileController.php             (extends existing updateAvatar)
└── SettingsController.php            (new)
```

Each public controller renders an `Inertia::render('Public/...', ...)` with simple read-only props. No DB writes from public routes (defense-in-depth — anything mutating must be authed).

### 2.3 Adaptive ClientShell

`resources/js/Layouts/ClientShell.vue` becomes the single layout for both surfaces. Reads `usePage().props.auth.user`:

- **Guest header (user is null):**
  - Brand "عيادة جنّة" on the inline-start
  - `<Link href="/login">تسجيل الدخول</Link>` + `<Link href="/register">إنشاء حساب</Link>` on the inline-end
- **Authed header (user exists):**
  - Brand
  - `<NotificationBell href="/portal/notifications" />`
  - User name + `<Link href="/logout" method="post">خروج</Link>`

Bottom nav switches between two tab sets:

| Surface | Tabs |
|---|---|
| Guest | الرئيسية (`/`) · الخدمات (`/services`) · الأطبّاء (`/doctors`) · الدعم (`/support`) |
| Authed | الرئيسية (`/portal`) · مواعيدي (`/portal/appointments`) · سجلي الطبي (`/portal/medical-record`) · نقاطي (`/portal/loyalty`) · حسابي (`/portal/profile`) · خدمات (`/services`) |

Note: the authed bottom nav keeps a link to the PUBLIC services page (so logged-in users can still browse from the same shell — same surface, same browse list). The booking action is initiated from inside the services page or from the home dashboard.

### 2.4 AuthGuard pattern

A small Vue helper component `resources/js/Components/foundation/AuthGuardLink.vue`:

```vue
<template>
  <Link
    v-if="isAuthed"
    :href="authedHref"
    v-bind="$attrs"
  ><slot /></Link>
  <Link
    v-else
    :href="`/login?intent=${intent}${qs}`"
    v-bind="$attrs"
  ><slot /></Link>
</template>
```

The Vue side encodes the intent + optional context (service id, doctor id, etc.) into the query string. The server's `LoginController::store` (Laravel Breeze-style) reads `intent` after authentication and routes accordingly via a small `IntentResolver` service.

Intent map (in `App\Domain\Auth\Services\IntentResolver`):

| Intent value | Target |
|---|---|
| `booking` | `/portal/booking?service={n}&doctor={n}` (forwarded as-is) |
| `appointments` | `/portal/appointments` |
| `loyalty` | `/portal/loyalty` |
| (default / unknown) | `/portal` |

### 2.5 Pages

Inertia pages reside under `resources/js/Pages/`:

```
Public/
├── Home.vue                  (greeting · featured services 4 · featured doctor · tip · CTAs)
├── Services.vue              (catalog grid with prices + categories filter)
├── Doctors.vue               (doctors grid with specialty + rating)
└── Support.vue               (FAQ accordion + contact strip)

Portal/
├── Profile/
│   └── Edit.vue              (form: name, email, phone, DoB, gender, avatar)
└── Settings/
    └── Index.vue             (change password section; future settings stub area)
```

### 2.6 Config additions

`config/clinic.php` gains two keys:

```php
'tips' => [
    'اشرب 8 أكواب ماء يوميًّا للحفاظ على نضارة بشرتك.',
    'النوم 7-9 ساعات ليلًا يحسّن المزاج والمناعة.',
    'حركة خفيفة 30 دقيقة يوميًّا تقي من معظم الأمراض المزمنة.',
    'استخدم واقي الشمس حتى في الأيام الغائمة.',
    // … manager edits in deployment
],

'faqs' => [
    ['q' => 'كيف أحجز موعدًا؟', 'a' => 'من قائمة الخدمات اختر الخدمة المناسبة ثم اضغط «احجز» — سيُطلب منك الدخول أو إنشاء حساب.'],
    ['q' => 'ما طريقة الدفع؟', 'a' => 'يتمّ الدفع عبر تحويل بنكي ثم رفع صورة الإيصال من صفحة الموعد.'],
    ['q' => 'هل توجد خدمة منزلية؟', 'a' => 'نعم، لبعض الخدمات. تظهر علامة «منزلية» على الخدمة عند البحث.'],
    ['q' => 'هل يمكنني إلغاء الموعد؟', 'a' => 'نعم، من صفحة «مواعيدي» قبل وقت محدّد من الموعد.'],
    // … manager edits in deployment
],
```

---

## 3. Featured-content logic

### 3.1 Featured services

`Public\HomeController::index` selects 4 services for the home hero:

```php
$featuredServices = Service::query()
    ->where('is_active', true)
    ->withCount(['appointments' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
    ->orderByDesc('appointments_count')
    ->orderBy('display_order')
    ->limit(4)
    ->with('category:id,name,color_variant')
    ->get();
```

Fallback ordering by `display_order` ensures stable output when there's no booking history yet.

### 3.2 Featured doctor

```php
$featuredDoctor = DoctorProfile::query()
    ->where('is_bookable', true)
    ->orderByDesc('rating_average')
    ->orderBy('display_order')
    ->with('user:id,name')
    ->first();
```

No new `is_featured` flag — the existing `rating_average` + `display_order` produce a stable order.

### 3.3 Tip of the day

`array_random(config('clinic.tips'))` on each request. (Could be seeded by date for stability — but YAGNI; random is fine for MVP.)

---

## 4. Public pages — detail

### 4.1 `Public/Home.vue`

Layout (top to bottom):

```
┌────────────────────────────────────────┐
│ Brand · [login / register]             │  ← header (guest header from ClientShell)
├────────────────────────────────────────┤
│  أهلًا بك في عيادة جنّة                  │  ← hero greeting
│  وصف قصير للعيادة                       │
│  [احجز موعدًا  →]                       │  ← CTA (AuthGuardLink intent=booking)
├────────────────────────────────────────┤
│  خدمات مميّزة (4 cards in 2x2 grid)    │  ← featured services
│  [Card: name · price · CTA "احجز"]   ← each card has AuthGuardLink
├────────────────────────────────────────┤
│  الفئات (chip row)                     │  ← link to /services?category=N
├────────────────────────────────────────┤
│  طبيب مميَّز                            │  ← featured doctor card
│  [name · specialty · rating · CTA]    ← AuthGuardLink intent=booking&doctor=N
├────────────────────────────────────────┤
│  نصيحة اليوم                           │  ← tip card
│  "..."                                  │
├────────────────────────────────────────┤
│  أسئلة شائعة → كل الأسئلة (link)        │  ← teaser FAQ section
└────────────────────────────────────────┘
│ Bottom nav (4 tabs for guest)          │
```

For authed users visiting `/` (or `/portal`), the same structure applies but the hero greeting shows `أهلًا {name}` and includes "موعدك القادم" if any. Implementation: HomeController checks `auth()->check()` and includes `nextAppointment` in props for authed users only.

### 4.2 `Public/Services.vue`

Grid of all active services. Each card: name, price, duration, "loyalty discount" hint hidden (no badges per existing directive). Filter chip row: "الكل" + each category.

Cards have an `<AuthGuardLink intent="booking" :service="id">احجز</AuthGuardLink>` CTA.

### 4.3 `Public/Doctors.vue`

Grid of all `is_bookable` doctors. Each card: avatar (placeholder if none), name, specialty, rating stars, optional bio truncated. CTA `<AuthGuardLink intent="booking" :doctor="id">احجز معه</AuthGuardLink>`.

### 4.4 `Public/Support.vue`

FAQ accordion (read from config). Below: a "اتصل بنا" card with the clinic's phone, address, and WhatsApp link (sourced from `config/clinic.contact` — already exists per P0 conventions).

---

## 5. Authed pages — detail

### 5.1 `Portal/Profile/Edit.vue`

Single form bound to the `User` + `CustomerProfile` row:
- `name`, `email`, `phone` (User)
- `date_of_birth`, `gender`, `avatar` (CustomerProfile)
- `notes`, `chronic_conditions`, `allergies` are intentionally NOT here — they're managed by staff via the medical-profile flow per ADR-003.

Validation rules mirror existing `Admin\CustomerController::update` minus the staff-only fields.

`ProfileController::update` writes both User and CustomerProfile in a single `DB::transaction`. Avatar upload reuses the existing `updateAvatar` endpoint.

### 5.2 `Portal/Settings/Index.vue`

Sections:
- **Change password:** current password + new password + confirmation (uses Laravel's `current_password` rule and Hash::make).
- **Notification preferences** (stub for P5c): a disabled section with "قريبًا" badge.
- **Language** (stub): "العربيّة" displayed, no toggle — Arabic-only per P0.
- **Logout from all devices** (stub): no implementation yet.

`SettingsController::updatePassword`:
- Validates `current_password` against the user's hash.
- Updates `$user->password = Hash::make($new)`.
- Fires a notification: "تمّ تغيير كلمة المرور" (via P5a `NotificationService` — new `securityPasswordChanged` generator).

---

## 6. AuthGuardLink + intent resolver

### 6.1 Component contract

`resources/js/Components/foundation/AuthGuardLink.vue`:

```vue
<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  intent: { type: String, required: true },     // 'booking' | 'appointments' | ...
  authedHref: { type: String, required: true }, // where to go if logged in
  context: { type: Object, default: () => ({}) }, // extra params (service, doctor, ...)
})
const page = usePage()
const isAuthed = computed(() => !!page.props?.auth?.user)
const qs = computed(() => {
  const entries = Object.entries(props.context).filter(([, v]) => v != null)
  if (entries.length === 0) return ''
  return '&' + entries.map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&')
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
    :href="`/login?intent=${intent}${qs}`"
    v-bind="$attrs"
  ><slot /></Link>
</template>
```

### 6.2 Server-side intent resolver

New `app/Domain/Auth/Services/IntentResolver.php`:

```php
final class IntentResolver
{
    public function resolve(Request $request, ?string $intent): string
    {
        if ($intent === null) {
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

`Auth\AuthenticatedSessionController::store` (or whichever login-store handler exists) reads `$request->input('intent')` and uses the resolver to compute the redirect target. The default Laravel `intended()` mechanism is bypassed for this specific path (the `intent` query param is the authoritative signal). Cross-references: existing Laravel "intended URL" still works for direct visits to authed URLs.

### 6.3 Login form

The existing `Pages/Auth/Login.vue` passes `intent` and `context` through as hidden inputs (populated from `route().query` on mount). After login, the controller redirects to the resolved URL.

---

## 7. Adaptive ClientShell — detail

Single file `resources/js/Layouts/ClientShell.vue` (replacing the current). Logic:

```js
const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)

const guestTabs = [
  { label: 'الرئيسية', href: '/', test: u => u === '/' },
  { label: 'الخدمات', href: '/services', test: u => u.startsWith('/services') },
  { label: 'الأطبّاء', href: '/doctors', test: u => u.startsWith('/doctors') },
  { label: 'الدعم', href: '/support', test: u => u.startsWith('/support') },
]
const authedTabs = [
  { label: 'الرئيسية', href: '/portal' },
  { label: 'مواعيدي', href: '/portal/appointments' },
  { label: 'سجلي', href: '/portal/medical-record' },
  { label: 'نقاطي', href: '/portal/loyalty' },
  { label: 'حسابي', href: '/portal/profile' },
  { label: 'خدمات', href: '/services' }, // public services link, still accessible
]
const tabs = computed(() => isAuthed.value ? authedTabs : guestTabs)
```

The bottom-nav grid switches between `grid-cols-4` (guest) and `grid-cols-6` (authed). Existing `NotificationBell` and logout link become `v-if="isAuthed"`.

---

## 8. Page-level guards

### 8.1 Guest visits `/portal/*`

Existing middleware `auth` + `role:customer` triggers Laravel's default redirect to `/login`. With P5b, the redirect-after-login uses `intended()` to land on the originally requested URL. Combined with the `intent=` query handling, this preserves the deep-link experience.

### 8.2 Authed user visits `/`

The home controller renders the same `Public/Home.vue` page, but with `nextAppointment` populated and a personalized greeting. The adaptive ClientShell shows the authed header + 6-tab bottom nav.

### 8.3 `/portal` for authed user

Existing route `portal.home` renders `Portal/Home.vue`. With P5b, we KEEP this page (it's the personalized home) but redirect it from the public path: `/` shows everyone the same hero with personalization injected for authed users. So we deprecate `Portal/Home.vue` and the `portal.home` route points to `/` for guests OR renders the same Public/Home for authed users. Simpler: redirect `/portal` to `/`.

**Decision:** delete `Pages/Portal/Home.vue`, point `/portal` (route `portal.home`) to redirect to `/`. The personalization for authed users is handled in `Public/Home.vue` reading `auth.user`.

---

## 9. Testing

### Pest feature tests
- **PublicAccessTest:** guest can GET `/`, `/services`, `/doctors`, `/support` → 200; cannot GET `/portal/anything` → redirect to `/login`.
- **IntentResolverTest:** unit test for the resolver mapping (each intent → expected URL).
- **LoginIntentTest:** posting to `/login` with `intent=booking&service=5` lands on `/portal/booking?service=5`.
- **AuthedHomeTest:** authed user visiting `/` sees their name and `nextAppointment` in props.
- **ProfileEditTest:** customer updates their profile; partial updates; receptionist cannot reach (route is customer-only).
- **SettingsPasswordTest:** customer changes password with correct current_password; wrong current_password → 422; notification dispatched.

### Vitest
- `AuthGuardLink.spec.js` — renders authed href when user is set; renders login intent when user is null; encodes context into query string.
- `ClientShell-Adaptive.spec.js` — guest renders 4-tab nav + login/register CTAs; authed renders 6-tab nav + bell.

Target: **+15 Pest tests**, **+2 Vitest specs**.

---

## 10. Out of scope (deferred)

| Item | Reactivation trigger |
|---|---|
| Manager-editable tips/FAQ via admin UI | clinic asks for in-app editing without deploy |
| Standalone `/tips` page | content volume justifies a dedicated page |
| Per-category notification opt-out | real customer complaint about noise |
| Multi-language toggle | clinic expands beyond Arabic-speaking customers |
| Logout-from-all-devices | session-management requirement |
| Avatar from public profile (so doctors can see avatar) | UI request |
| SMS booking confirmations | P5a deferred-items list still applies |
| WhatsApp Click-to-Chat for booking | marketing decision |
| SEO meta tags + structured data | when search-engine indexing matters |

---

## 11. Implementation sequencing (informs the plan)

Plan should follow this order; every step ends green (Pest + Vitest + Pint + PHPStan + Vite):

1. **Public routes + controllers + page stubs.** Routes locked in `RouteNamesTest`. PublicAccessTest passes.
2. **`Public/Home.vue`** — featured services + featured doctor + tip + greeting. AuthedHomeTest passes.
3. **`Public/Services.vue` + `Public/Doctors.vue`** — full lists.
4. **`config/clinic.php`** tips + faqs; **`Public/Support.vue`** — FAQ accordion + contact.
5. **`AuthGuardLink` component + Vitest spec.** Wire CTAs on home/services/doctors.
6. **Adaptive `ClientShell` + Vitest spec.** Guest vs authed branches; bottom-nav grid swap.
7. **`IntentResolver` service + login redirect hook.** LoginIntentTest passes.
8. **`Portal/Profile/Edit.vue` + `ProfileController` extend.** ProfileEditTest passes.
9. **`Portal/Settings/Index.vue` + `SettingsController::updatePassword`.** SettingsPasswordTest passes + new `securityPasswordChanged` notification generator on `NotificationService`.
10. **DoD gate + ARCHITECTURE + CHANGELOG + tag `p5b-portal`.**

---

## 12. Definition of Done

- All 10 sequencing tasks merged.
- Pest green (~321 + ~15 new = ~336).
- Vitest green (33 + 2 new = 35).
- Pint clean, PHPStan L5 clean, `npm run build` clean.
- All new route names locked in `RouteNamesTest`.
- No existing test regressions.
- `CHANGELOG.md` entry under "[P5b] Public Landing + Portal Polish — 2026-05-20".
- `docs/ARCHITECTURE.md` updated with the public/authed split summary + deferred-items pointer.
- Tag `p5b-portal` applied and pushed.
- Manual smoke pass:
  - As guest, visit `/` → see home → click "احجز" → land on `/login?intent=booking&service=5` → authenticate → arrive on `/portal/booking?service=5` with the service pre-selected.
  - As authed customer, visit `/` → see personalized greeting + next appointment.
  - Update profile → see flash success → reload reflects changes.
  - Change password → bell shows new "تمّ تغيير كلمة المرور" notification.
