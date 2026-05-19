# jannahclinic — Architecture

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: architecture
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Last updated: 2026-05-19 (P1 Task 9 shared BookingWizard + Portal/Admin booking controllers + 4 booking routes)

**R6 obligation:** this file MUST be updated in the same change set as any change
to models, routes, middleware, design tokens, or CI configuration.

---

## Stack (as built)

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.4 |
| Framework | Laravel 13 |
| SPA bridge | Inertia.js |
| Frontend | Vue 3 (Composition API) |
| CSS | Tailwind CSS v4 (CSS-first `@theme` — no `tailwind.config.js`) |
| UI primitives | shadcn-vue (reka-ui / Radix Vue port) |
| Database (prod + CI) | PostgreSQL 16 |
| Database (local tests) | SQLite in-memory (speed only — see phpunit.xml note and ADR-002 §Consequences) |
| Test runner | Pest (PHP), Vitest (JS) |
| Linter | Laravel Pint |
| Static analysis | Larastan (PHPStan) level 5 |
| Build tool | Vite |
| Font | Cairo variable font — self-hosted (`resources/fonts/Cairo.ttf`) |

**SQLite / Postgres split:** Local Pest tests run on SQLite in-memory for speed.
Postgres-only CHECK constraints (`users_role_check`, `users_email_or_phone`) are
deliberately skipped in SQLite; application-layer validation (AuthService,
LoginRequest) enforces these invariants before the DB. CI (`quality-gate.yml`)
runs a real Postgres 16 service and is the authoritative constraint gate.
See the `phpunit.xml` comment and ADR-002.

---

## Governance

The project adopted **methodology-kit v1.0.1** at inception (ADR-001). Bootstrap
output lives in `docs/`:

| Document | Role |
|----------|------|
| `docs/GOLDEN-RULES.md` | R0–R8 (kit core) + R9–R23 (generated domain/stack rules) |
| `docs/DEFINITION-OF-DONE.md` | Gate Q + architecture + UI/UX + i18n gates |
| `docs/DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md` | Document authority ladder |
| `docs/CANONICAL-DECISION-REGISTRY.md` | Registry of all canonical decisions |
| `docs/adr/001-adopt-methodology-kit.md` | ADR-001 — kit adoption + derivation interview YAML |
| `docs/adr/002-basic-security-posture.md` | ADR-002 — MVP security posture + production gate |

**Key rules affecting this codebase:**

- **R7** — Business logic lives in service classes under `app/Domain/{Module}/Services/`.
  Auth logic is in `app/Domain/Auth/Services/AuthService.php`.
  Availability slot engine is in `app/Domain/Booking/Services/AvailabilityService.php`.
  Pricing (bcmath quote) is in `app/Domain/Booking/Services/PricingService.php`.
  Transactional booking writes are in `app/Domain/Booking/Services/BookingService.php`.
- **R12** — Config-driven values via `config/clinic.php` + `App\Domain\Settings\Services\SettingService` (DB override → config fallback).
- **R20** — Logical CSS properties only (no `margin-left`, `padding-right`,
  `text-align: left/right`). CI greps `resources/js/**/*.vue` for violations.
- **R6** — `docs/ARCHITECTURE.md` and `docs/DOMAIN-MODEL.md` are the kit
  `autodoc_targets` and must be updated with every relevant change.
- **ADR-002** — Real patient data MUST NOT reach production under the current
  posture (basic auth + roles only; no medical-record audit or at-rest encryption).

---

## Application Surfaces

The application exposes two isolated surfaces. Route files are loaded via
`bootstrap/app.php` `withRouting(then:)`.

### Control Panel — `routes/admin.php`

```
middleware: [auth, role:manager,doctor,receptionist]
prefix:     /admin
name:       admin.*
layout:     AdminShell (resources/js/Layouts/AdminShell.vue)
```

Entry point: `GET /admin` → `admin.dashboard` → `Pages/Admin/Dashboard.vue`.

**P1 Task 2 catalog routes (staff group):**

| Method | Path | Name | Controller |
|--------|------|------|------------|
| GET | `/admin/catalog/categories` | `admin.catalog.categories` | `Admin\ServiceCategoryController@index` |
| POST | `/admin/catalog/categories` | `admin.catalog.categories.store` | `Admin\ServiceCategoryController@store` |
| PUT | `/admin/catalog/categories/{category}` | `admin.catalog.categories.update` | `Admin\ServiceCategoryController@update` |
| DELETE | `/admin/catalog/categories/{category}` | `admin.catalog.categories.destroy` | `Admin\ServiceCategoryController@destroy` |
| GET | `/admin/catalog/services` | `admin.catalog.services` | `Admin\ServiceController@index` |
| POST | `/admin/catalog/services` | `admin.catalog.services.store` | `Admin\ServiceController@store` |
| PUT | `/admin/catalog/services/{service}` | `admin.catalog.services.update` | `Admin\ServiceController@update` |
| DELETE | `/admin/catalog/services/{service}` | `admin.catalog.services.destroy` | `Admin\ServiceController@destroy` |

Vue pages: `Pages/Admin/Catalog/Categories.vue`, `Pages/Admin/Catalog/Services.vue`.

**P1 Task 3 doctor routes:**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/doctors` | `admin.doctors.index` | `Admin\DoctorController@index` | all staff |
| POST | `/admin/doctors` | `admin.doctors.store` | `Admin\DoctorController@store` | manager only |
| PUT | `/admin/doctors/{doctor}` | `admin.doctors.update` | `Admin\DoctorController@update` | manager only |
| DELETE | `/admin/doctors/{doctor}` | `admin.doctors.destroy` | `Admin\DoctorController@destroy` | manager only |

Vue page: `Pages/Admin/Doctors/Index.vue`.

**P1 Task 4 schedule routes:**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/doctors/{doctor}/schedule` | `admin.doctors.schedule` | `Admin\DoctorScheduleController@editSchedule` | all staff |
| PUT | `/admin/doctors/{doctor}/schedule` | `admin.doctors.schedule.save` | `Admin\DoctorScheduleController@saveSchedule` | manager only |
| POST | `/admin/doctors/{doctor}/exceptions` | `admin.doctors.exceptions.add` | `Admin\DoctorScheduleController@addException` | manager only |
| DELETE | `/admin/doctors/{doctor}/exceptions/{exception}` | `admin.doctors.exceptions.delete` | `Admin\DoctorScheduleController@deleteException` | manager only |

These canonical names are locked by `tests/Feature/RouteNamesTest.php` (asserts the
exact set and that no `admin.admin.*` / `portal.portal.*` doubled prefix exists).

Vue page: `Pages/Admin/Doctors/Schedule.vue`.

**P1 Task 5 coverage + clinic-settings routes:**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/coverage` | `admin.coverage.index` | `Admin\CoverageAreaController@index` | all staff |
| POST | `/admin/coverage` | `admin.coverage.store` | `Admin\CoverageAreaController@store` | manager only |
| PUT | `/admin/coverage/{area}` | `admin.coverage.update` | `Admin\CoverageAreaController@update` | manager only |
| DELETE | `/admin/coverage/{area}` | `admin.coverage.destroy` | `Admin\CoverageAreaController@destroy` | manager only |
| GET | `/admin/settings` | `admin.settings.index` | `Admin\ClinicSettingController@index` | all staff |
| PUT | `/admin/settings/surcharge` | `admin.settings.surcharge` | `Admin\ClinicSettingController@updateSurcharge` | manager only |

Vue pages: `Pages/Admin/Coverage/Index.vue`, `Pages/Admin/Settings/Index.vue`.
`updateSurcharge` writes `home_surcharge_pct` via `SettingService::set` (R12).

**P1 Task 7 availability route (all staff):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/availability` | `admin.availability` | `Booking\AvailabilityController` | all staff |

Query params: `doctor` (id), `service` (id), `date` (Y-m-d). Returns JSON array of `{start, end, label}`.

**P1 Task 9 booking wizard routes (all staff):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/booking` | `admin.booking.create` | `Admin\BookingController@create` | all staff |
| POST | `/admin/booking` | `admin.booking.store` | `Admin\BookingController@store` | all staff |

Vue page: `Pages/Admin/Booking/Create.vue` — wraps `BookingWizard` with `customerPicker=true`; sends customer list + doctors/services/coverageAreas/homeSurchargePct. On store: resolves customer via `customer_id` (verified Customer-role) or quick-creates via `AuthService::registerCustomer` with a `Str::password(16)` generated password. `createdByRole` = `$request->user()->role`.

### Customer Portal — `routes/portal.php`

```
middleware: [auth, role:customer]
prefix:     /portal
name:       portal.*
layout:     ClientShell (resources/js/Layouts/ClientShell.vue)
```

Key P0 routes: `GET /portal` → `portal.home`; `POST /portal/profile/avatar`
→ `portal.profile.avatar` (handled by `ProfileController::updateAvatar`).

**P1 Task 2 catalog routes (customer group):**

| Method | Path | Name | Controller |
|--------|------|------|------------|
| GET | `/portal/services` | `portal.services.index` | `Portal\ServiceBrowseController@index` |

Vue page: `Pages/Portal/Services/Index.vue` — browse-only (no booking; wizard is Task 4+).

**P1 Task 7 availability route (customer):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/portal/availability` | `portal.availability` | `Booking\AvailabilityController` | customer |

Query params: `doctor` (id), `service` (id), `date` (Y-m-d). Returns JSON array of `{start, end, label}`.

**P1 Task 9 booking wizard routes (customer):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/portal/booking` | `portal.booking.create` | `Portal\BookingController@create` | customer |
| POST | `/portal/booking` | `portal.booking.store` | `Portal\BookingController@store` | customer |

Vue page: `Pages/Portal/Booking/Create.vue` — wraps `BookingWizard` with `customerPicker=false`; sends doctors/services/coverageAreas/homeSurchargePct. On store: `customerId = $request->user()->id`, `createdByRole = Customer`. Both booking controllers delegate to `BookingService::book(BookingData)` (R7); catch `SlotUnavailableException`/`InvalidBookingException` and `back()->withErrors(['booking' => $msg])` (Inertia-safe error bag; never abort(409/422)). On success, redirect to an existing route with a flash (T10 will repoint to appointments).

**Shared `BookingWizard` component (`Components/booking/BookingWizard.vue`):**
3-step wizard (step 0 = customer picker for admin only → step 1 = delivery mode → step 2 = doctor + service → step 3 = date + slot). The slot picker calls the availability endpoint via `fetch` and stores the EXACT ISO8601+offset `start` string returned by the endpoint (never reconstructed). Client-side price preview uses `price_override ?? base_price` + home surcharge estimate; server recomputes authoritatively. Page components own `useForm` and post via Inertia; wizard emits `submit` with the payload object.

### Surface isolation

`EnsureUserHasRole` middleware (alias `role`, registered in `bootstrap/app.php`)
enforces role checks server-side on every request. A customer hitting any
`/admin/*` route receives HTTP 403; a staff user hitting `/portal/*` receives
HTTP 403. UI element visibility is decoration only (R3).

---

## Authentication

- **Identifier:** email OR phone (`AuthService::resolveByIdentifier` — `LoginRequest`
  uses it for credential resolution; both fields are nullable-unique in the DB).
- **Registration:** `AuthService::registerCustomer` wraps `User` creation
  (role=customer) and `CustomerProfile` creation in a single `DB::transaction`.
  At least one of email/phone is required (validated in `RegisteredUserController`).
- **Staff creation:** `AuthService::createStaff(array $data, UserRole $role): User`
  creates a staff user (no CustomerProfile) in a `DB::transaction`. Used by
  `DoctorController::store` (role=Doctor); mirrors `registerCustomer` structure.
- **Post-login redirect:** `isStaff()` → `admin.dashboard`; customer →
  `portal.home` (in `AuthenticatedSessionController::store`).
- **Email verification:** `User implements MustVerifyEmail`. Portal routes
  deliberately omit the `verified` middleware alias — phone-only customers have
  no email and would be permanently trapped on `/email/verify`. See the hazard
  docblock in `app/Models/User.php` and ADR-002.

---

## Error Handling

Wired in `bootstrap/app.php` `withExceptions`:

| Status | Behaviour |
|--------|-----------|
| 403, 404, 429, 500, 503 | Inertia render `Pages/Errors/Error.vue` (non-local only) |
| 419 (CSRF expired) | `back()->with(['message' => '...'])` (flash, non-Inertia redirect) |
| Local environment | Default Laravel error page — stack traces visible |

---

## Design System

Tailwind CSS v4 `@theme` block in `resources/css/app.css` defines
clinic-semantic tokens (derived from building.app Visual DNA):

- **Brand:** `--color-brand: #0B4F2F`, `--color-gold: #C9A227`
- **Surfaces:** page / card / sunken
- **Text:** primary / secondary / tertiary
- **Borders:** default / strong
- **Semantic status:** success / warning / danger / info / amount
- **Radius:** xs (6px) → xl (16px)
- **Shadow:** xs → lg
- **Motion:** fast (100ms) / normal (200ms) / slow (300ms)

shadcn-vue semantic tokens are mapped via `@theme inline` to Tailwind utility
names (`bg-background`, `text-foreground`, etc.). shadcn-vue components live
under `resources/js/Components/ui/`.

### Foundation Layer (`resources/js/Components/foundation/`)

Building-block components built on top of the design tokens and shadcn-vue
primitives:

| Component | Purpose |
|-----------|---------|
| `PageStates.vue` | 4-state slot wrapper: loading / empty / error / success (R16) |
| `DataTable.vue` | Sortable, slotted table with empty + loading states |
| `FormGroup.vue`, `FormSection.vue`, `FormActions.vue` | Form layout primitives |
| `Modal.vue`, `Drawer.vue`, `ConfirmModal.vue` | Overlay components via reka-ui portal (R23) |
| `StatusBadge.vue` | Semantic status chip |
| `StatCard.vue` | Dashboard stat tile |
| `EmptyState.vue`, `ErrorState.vue` | Reusable empty/error illustrations |
| `PageHeader.vue` | Page title + action slot |

All overlays render via reka-ui teleport to document root (R23). RTL-first:
all layout uses CSS logical properties (`inline-start/end`, `block-start/end`)
— physical directional properties are a CI hard-fail.

---

## Quality Gate

`.github/workflows/quality-gate.yml` runs on every PR / push to main:

| Check | Command | Hard Fail? |
|-------|---------|-----------|
| Linter | `./vendor/bin/pint --test` | Yes |
| Static analysis | `./vendor/bin/phpstan analyse --no-progress` (L5) | Yes |
| Tests | `php artisan test` (Pest, Postgres service, ≥60% coverage) | Yes |
| Money float check | grep `\b(float\|double)\b` on money fields | Yes |
| RTL logical props | grep physical Tailwind utilities in authored dirs (`Layouts/Pages/Components/foundation/resources/css`); `Components/ui/` excluded | Yes |

`composer quality` mirrors the gate locally.

---

## P0 Boundary and Known P1+ Debt

P0 delivers auth, roles, two empty shells, the design system, and the quality
gate. No booking, services, payments, records, loyalty, or notifications are
present — this is intentional (YAGNI). The P1–P5 roadmap is in
`docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` §2.

Documented P1 debt items:

- **Avatar cleanup:** old avatar file is not deleted on replace (P1 cleanup).
- **Shell nav:** ✅ RESOLVED (P1-NAV). `AdminShell` links the 6 real admin
  routes and `ClientShell` the 2 real portal routes, both with `aria-current`
  active state (unbuilt portal tabs render disabled, not mislinked). Still
  deferred: Inertia persistent layouts (`defineOptions` layout) to preserve
  shell state across navigations — tracked by the honest in-file `TODO(P1)`.
- **Staff verify/confirm redirect:** `VerifyEmailController` and
  `ConfirmablePasswordController` redirect to `portal.home` after completion —
  harmless now (staff cannot reach portal), but needs role-aware redirect logic
  before staff email-verify gates are added in P1.
- **Production gate (ADR-002):** medical-record audit logging and at-rest
  encryption are mandatory before real patient data reaches production. This ADR
  MUST be superseded and the kit re-bootstrapped before any production deployment
  with real patient data.
- **Cairo font format:** Cairo shipped as TTF (~599KB); convert to WOFF2 in P1 (spec §3.3 prescribed woff2).
- **Missing easing token:** `--easing-spring` cubic-bezier token not yet defined in @theme (spec §3.3); add when overlay animations land in P1.
- **AdminShell sidebar collapse:** sidebar collapse (256px↔64px, spec §3.3) not implemented — P1 with real nav.
- **ConfirmablePasswordController phone-only hazard:** fails for phone-only users (email null); add phone-aware confirmation before any P1 route uses `password.confirm` middleware.
- **Doc/rule numbering note:** the P0 spec text refers to the 4-UI-states rule as "R10"; in the kit-generated `docs/GOLDEN-RULES.md` it is R16 (R10 there = no-double-counting). Code is correct; this is a spec-vs-generated numbering note only.
- **RTL CI check scoping:** RTL CI check is scoped to authored code (`Layouts/Pages/Components/foundation/resources/css`); vendored `shadcn-vue Components/ui/` is excluded by design (upstream uses physical Tailwind classes; reka-ui/RTL handled at runtime).
- **Currency symbol ₪ is hardcoded in catalog/portal Vue (single-currency clinic); make config/locale-driven if multi-currency is ever needed.**
- **App timezone defaults to `Asia/Hebron` (env `APP_TIMEZONE`), explicitly set as the assumption for a Palestinian clinic.** Confirm/adjust this default before any production deployment in a different timezone. Controlled via `config/app.php` → `env('APP_TIMEZONE', 'Asia/Hebron')`.
- **Redundant weekday-schedule query removed (T7 polish):** `AvailabilityService` previously fetched the `DoctorSchedule` row twice per call (once in `windowsFor`, once in the removed `intervalFor`). The row is now fetched once in `slotsFor` and passed to `windowsFor`; `intervalFor` has been eliminated.
- **Schedule time-field contract (T4 → T7+ interface):** `DoctorSchedule.morning_start/morning_end/evening_start/evening_end` and `ScheduleException.custom_start/custom_end` use the `datetime:H:i` Eloquent cast. Consequence: at runtime they are **Carbon** instances (so `(string)$model->morning_start` yields a full `Y-m-d H:i:s`, NOT `'09:00'` — never `substr((string)...)` them); Inertia/JSON serialization yields `'09:00'` (correct for `<input type="time">` prefill). T7 `AvailabilityService` and any later consumer MUST read these via `->format('H:i')` or Carbon comparison. The P1 plan's T7 `windowsFor()` snippet has been corrected accordingly.
- **Appointment Postgres CHECK constraints (T6 data layer; write logic T8+):** The `appointments` table carries four Postgres-only CHECK constraints — `appointments_status_check` (7-value enum), `appointments_mode_check` (center/home), `appointments_price_check` (price >= 0), `appointments_time_check` (end_at > start_at). These are skipped on SQLite test DB (ADR-002); CI Postgres is the authoritative gate.
- **T8 — Booking domain (pure backend, no routes/controllers):** `PricingService::quote()` returns `{base, surcharge, total}` as bcmath strings (never IEEE754); home surcharge uses the `home_surcharge_pct` setting (DB override → `config/clinic.php` fallback). `BookingService::book(BookingData)` runs inside `DB::transaction`; locks the doctor row (`lockForUpdate`) before the in-transaction slot re-validation through `AvailabilityService::slotsFor()` — this is the double-booking guard. Creates `Appointment` + `ServiceAddress` (home only). Throws `SlotUnavailableException` for unavailable/conflicting slots, `InvalidBookingException` for rule violations. `BookingData` is a typed value object (DTO) carrying all booking intent. Exceptions live in `App\Domain\Booking\Exceptions`.
- **T9 — Phone-only quick-created customers cannot self-authenticate to portal:** customers quick-created by staff via `Admin\BookingController` receive a `Str::password(16)` they are never shown; they have no way to log into the portal until a password-reset or phone-OTP flow exists. Acceptable MVP (staff-managed workflow); revisit if portal self-login for these customers is required.

---

## Related Documents

- ADR-001: `docs/adr/001-adopt-methodology-kit.md`
- ADR-002: `docs/adr/002-basic-security-posture.md`
- Definition of Done: `docs/DEFINITION-OF-DONE.md`
- Spec roadmap (§2 P1–P5): `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md`
- Domain Model: `docs/DOMAIN-MODEL.md`
