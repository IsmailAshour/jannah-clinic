# jannahclinic — Architecture

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: architecture
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Last updated: 2026-05-20 (P3 Medical Records: encrypted MedicalEntry/Prescription/MedicalAuditLog with append-only invariant, AuditLogger explicit-call inside transactions, MedicalEntryPolicy, Admin entry write + Customer portal read, ADR-003 supersedes ADR-002 — production now permits real PHI; AdminDataTable family on shadcn-vue + @tanstack/vue-table for new admin list surfaces, 8-page R-DataTable migration debt recorded)

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
| `docs/adr/002-basic-security-posture.md` | ADR-002 — MVP security posture (SUPERSEDED by ADR-003 on 2026-05-20) |
| `docs/adr/003-encrypted-medical-records.md` | ADR-003 — Encrypted medical records + audit log (ACTIVE; supersedes ADR-002) |

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
- **ADR-003** — Strict security posture for medical records: every PHI free-text
  field (medical_entries.visible_summary/staff_notes, prescriptions.*,
  customer_profiles.notes/chronic_conditions/allergies) is encrypted at rest via
  Laravel's `encrypted` cast keyed by `APP_KEY`. Every CREATE/UPDATE/VIEW of
  these fields appends to `medical_audit_logs` (append-only, enforced at the
  model layer; CI grep gate fails on any `MedicalAuditLog::*->update|delete`).
  Receptionists are explicitly excluded from PHI surfaces.
- **ADR-002 (SUPERSEDED by ADR-003 on 2026-05-20)** — The MVP "basic posture"
  (no encryption, no audit) is no longer in force. Production now permits real
  patient data subject to ADR-003 remaining ACCEPTED/ACTIVE in the registry.

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

Vue page: `Pages/Admin/Booking/Create.vue` — wraps `BookingWizard` with `customerPicker=true`; sends customer list + doctors/services/coverageAreas/homeSurchargePct. On store: resolves customer via `customer_id` (verified Customer-role) or quick-creates via `AuthService::registerCustomer` with a `Str::password(16)` generated password. `createdByRole` = `$request->user()->role`. **T10 note:** on success now redirects to `admin.appointments.index` (was `admin.dashboard`).

**P1 Task 10 appointment management routes (all staff):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/appointments` | `admin.appointments.index` | `Admin\AppointmentController@index` | all staff |
| POST | `/admin/appointments/{appointment}/transition` | `admin.appointments.transition` | `Admin\AppointmentController@transition` | all staff |

Vue page: `Pages/Admin/Appointments/Index.vue` — paginated appointments with filter bar (status / doctor / date); per-row action buttons for status transitions gated by current state; cancel uses a `Modal` with reason textarea; delegates to `AppointmentTransitionService` (R7); `Gate::authorize('manage', $appointment)` (policy: isStaff). Error bag: `withErrors(['appointment' => $e->getMessage()])` on `InvalidTransitionException` (never abort).

**Customer admin routes (Polish-D):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/admin/customers` | `admin.customers.index` | `Admin\CustomerController@index` | all staff |
| GET | `/admin/customers/{customer}` | `admin.customers.show` | `Admin\CustomerController@show` | all staff |
| PUT | `/admin/customers/{customer}` | `admin.customers.update` | `Admin\CustomerController@update` | manager only |
| POST | `/admin/customers/{customer}/toggle-active` | `admin.customers.toggle-active` | `Admin\CustomerController@toggleActive` | manager only |

Vue pages: `Pages/Admin/Customers/Index.vue` (list + filter bar — q across name/email/phone, status active/inactive), `Pages/Admin/Customers/Show.vue` (profile block + stats + paginated appointments + edit `Modal`). List always scoped to `role = Customer`; `show`/`update`/`toggleActive` `abort_unless($customer->role === Customer, 404)` (matches T10 ownership-guard pattern). Update validation: name (required), email/phone (nullable + unique ignoring own id), `is_active` (boolean), `date_of_birth` (nullable|date|before:today), `gender` (nullable|max:16), `notes` (nullable|max:2000). User + CustomerProfile upsert in `DB::transaction`. No destroy. Soft-disable via `users.is_active`; `LoginRequest` rejects inactive users with the uniform `auth.failed` failure (defence in depth, no info leak). AdminShell nav: `العملاء` leaf under the `العيادة` group (lucide `Contact2`).

**`AppointmentTransitionService`** (`app/Domain/Booking/Services/AppointmentTransitionService.php`): encapsulates all appointment lifecycle transitions. `transition(Appointment, AppointmentStatus, ?reason)` enforces the state machine via `AppointmentStatus::canTransitionTo()`, throws `InvalidTransitionException` on illegal transitions, sets `cancellation_reason` when cancelling. `reschedule(Appointment, CarbonImmutable)` runs in `DB::transaction`: creates a new `requested` appointment via `BookingService::book()`, sets `rescheduled_from_id`, marks old appointment `rescheduled`.

**`AppointmentPolicy`** (`app/Policies/AppointmentPolicy.php`): registered via `Gate::policy(Appointment::class, AppointmentPolicy::class)` in `AppServiceProvider::boot`. Abilities: `view`/`cancel`/`reschedule` → staff always; customer only if `customer_id === user->id`. `manage` → staff only.

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

Vue page: `Pages/Portal/Booking/Create.vue` — wraps `BookingWizard` with `customerPicker=false`; sends doctors/services/coverageAreas/homeSurchargePct. On store: `customerId = $request->user()->id`, `createdByRole = Customer`. Both booking controllers delegate to `BookingService::book(BookingData)` (R7); catch `SlotUnavailableException`/`InvalidBookingException` and `back()->withErrors(['booking' => $msg])` (Inertia-safe error bag; never abort(409/422)). **T10:** on success redirects to `portal.appointments.index` (was `portal.home`).

**P1 Task 10 customer my-appointments routes (customer):**

| Method | Path | Name | Controller | Auth |
|--------|------|------|------------|------|
| GET | `/portal/appointments` | `portal.appointments.index` | `Portal\AppointmentController@index` | customer |
| POST | `/portal/appointments/{appointment}/cancel` | `portal.appointments.cancel` | `Portal\AppointmentController@cancel` | customer |
| POST | `/portal/appointments/{appointment}/reschedule` | `portal.appointments.reschedule` | `Portal\AppointmentController@reschedule` | customer |

Vue page: `Pages/Portal/Appointments/Index.vue` — card list of customer's own appointments; cancel via `Modal` with required reason textarea; reschedule via `Modal` with date + slot picker (fetches from `/portal/availability`, mirrors `BookingWizard` slot fetch pattern); posts EXACT ISO8601 `start` string. `Gate::authorize('cancel'/'reschedule', $appointment)` — policy enforces `customer_id === user->id`, returns 403 on mismatch.

**Nav reachability (T10 — P1-NAV completion):** `AdminShell` nav now includes `المواعيد` (`/admin/appointments`) and `الحجز` (`/admin/booking`) leaves. `ClientShell` bottom tabs now have 4 real tabs: الرئيسية, الخدمات, الحجز (`/portal/booking`), مواعيدي (`/portal/appointments`). All disabled placeholders replaced.

**Shared `BookingWizard` component (`Components/booking/BookingWizard.vue`):**
3-step wizard (step 0 = customer picker for admin only → step 1 = delivery mode → step 2 = doctor + service → step 3 = date + slot). The slot picker calls the availability endpoint via `fetch` and stores the EXACT ISO8601+offset `start` string returned by the endpoint (never reconstructed). Client-side price preview uses `price_override ?? base_price` + home surcharge estimate; server recomputes authoritatively. Page components own `useForm` and post via Inertia; wizard emits `submit` with the payload object.

### Surface isolation

`EnsureUserHasRole` middleware (alias `role`, registered in `bootstrap/app.php`)
enforces role checks server-side on every request. A customer hitting any
`/admin/*` route receives HTTP 403; a staff user hitting `/portal/*` receives
HTTP 403. UI element visibility is decoration only (R3).

---

## P1 — Services & Booking (T1–T10)

P1 delivers the full end-to-end booking capability: service catalog, doctor
profiles + schedules, availability engine, pricing, transactional booking, and
appointment lifecycle management across both surfaces. All 10 P1 tasks are
complete and all route names are locked by `RouteNamesTest`.

### Domain services (`app/Domain/Booking/Services/`)

| Service | Responsibility |
|---------|---------------|
| `AvailabilityService` | Slot generation from weekly schedule + date exceptions; excludes past and conflicting (non-terminal) appointments; respects `closed`/`custom_hours` exceptions |
| `PricingService` | bcmath quote `{base, surcharge, total}` — never IEEE754; home surcharge reads `home_surcharge_pct` via SettingService (DB override → `config/clinic.php` fallback; R12) |
| `BookingService` | Transactional `book(BookingData)`: `lockForUpdate` doctor row, `AvailabilityService` re-check (double-booking guard), creates `Appointment` + `ServiceAddress` (home only). Throws `SlotUnavailableException` / `InvalidBookingException` |
| `AppointmentTransitionService` | `transition(Appointment, AppointmentStatus, ?reason)` enforces the 7-state machine via `AppointmentStatus::canTransitionTo()`; throws `InvalidTransitionException`. `reschedule()` runs in `DB::transaction`: new `requested` appointment via `BookingService::book()`, sets `rescheduled_from_id`, marks old `rescheduled` |

### Shared `BookingWizard` + booking channels

**`BookingWizard.vue`** (`Components/booking/BookingWizard.vue`): 3-step wizard
(step 0 = customer picker for admin only → step 1 = delivery mode → step 2 =
doctor + service → step 3 = date + slot). The slot picker calls the
availability endpoint via `fetch` and stores the EXACT ISO8601+offset `start`
string returned by the endpoint. Client-side price preview is indicative only;
server recomputes authoritatively via `PricingService`.

Two booking channels share this component:
- **Portal self-booking** (`Portal\BookingController`): `customerPicker=false`;
  `createdByRole=Customer`; success redirects to `portal.appointments.index`.
- **Admin on-behalf** (`Admin\BookingController`): `customerPicker=true`;
  resolves customer via verified `customer_id` OR quick-creates a new customer
  via `AuthService::registerCustomer` with a generated password (staff-managed
  workflow); `createdByRole = $request->user()->role`; success redirects to
  `admin.appointments.index`.

Both controllers delegate to `BookingService::book(BookingData)` (R7);
catch `SlotUnavailableException` / `InvalidBookingException` and
`back()->withErrors(['booking' => $msg])` (Inertia-safe; never abort).

### Availability endpoint

`GET /admin/availability` and `GET /portal/availability` — query params:
`doctor` (id), `service` (id), `date` (Y-m-d). Returns JSON array of
`{start, end, label}` (ISO8601+offset strings). Both canonical names locked
by `RouteNamesTest`.

### R12 surcharge config

`home_surcharge_pct` is stored in the `settings` table (managed via
`Admin/Settings/Index` — `PUT /admin/settings/surcharge`). Falls back to
`config('clinic.home_surcharge_pct')` (default `30`) when no DB row exists.
No hardcoded surcharge anywhere in application logic.

### 7-state lifecycle + `AppointmentPolicy`

States: `requested → confirmed → completed|no_show|cancelled|rescheduled`;
`requested → rejected|cancelled|rescheduled`. All terminal states: `rejected`,
`completed`, `cancelled`, `no_show`, `rescheduled`. Enforcement is
server-side in `AppointmentTransitionService`; the `rescheduled` terminal
state is further blocked at the admin transition endpoint (only the
`reschedule()` path may set it).

`AppointmentPolicy` (registered via `Gate::policy` in `AppServiceProvider`):
- `view`/`cancel`/`reschedule` → staff always; customer only if `customer_id === user->id`
- `manage` → staff only

### All P1 route names locked by RouteNamesTest

`tests/Feature/RouteNamesTest.php` asserts the exact set of all canonical
admin and portal route names and that no `admin.admin.*` / `portal.portal.*`
doubled prefix exists. Covers all T2–T10 routes plus the availability and
booking wizard routes.

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

## P0 Boundary and Known Debt

P0 delivers auth, roles, two empty shells, the design system, and the quality
gate. P1 (T1–T10) delivers the full services-and-booking capability. The
P2–P5 roadmap is in
`docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` §2.

### P1 resolved items

- **✅ Shell nav (P1-NAV):** `AdminShell` grouped nav reaches catalog (`الخدمات` group), doctors/appointments/booking (`العيادة` group), coverage, and settings. Per-doctor schedule page reachable via a dedicated `الجدول` action on the doctors list. `ClientShell` has 4 real tabs: الرئيسية, الخدمات, الحجز, مواعيدي. All `aria-current` active states wired.
- **✅ Appointment CHECK constraints (T6+T8):** all four Postgres-only CHECK constraints on `appointments` landed with T6 migration; write logic with T8 enforces them at application layer too.
- **✅ Booking domain (T8):** `PricingService` (bcmath, R9), `BookingService` (transactional, double-booking guard), `BookingData` DTO, and booking exceptions — all built and unit-tested.
- **✅ Booking wizard + both channels (T9):** `BookingWizard`, portal self-booking, admin on-behalf + quick-create customer — all built, feature-tested, and nav-reachable.
- **✅ Appointment lifecycle (T10):** `AppointmentTransitionService` + `AppointmentPolicy` + admin and portal appointment controllers — all transitions server-side enforced; manual `rescheduled` status blocked at admin endpoint; 17 new tests.
- **✅ `AppointmentTransitionService` no-lock note:** `transition()` is MVP no-lock (last-write-wins for two concurrent *valid* transitions); acceptable for low-concurrency trusted staff. Documented inline with a TODO for P2 `lockForUpdate`.
- **✅ AdminShell sidebar collapse (post-P1 polish):** `AdminShell` sidebar is now responsive and user-collapsible — implemented via the **vendored shadcn-vue Sidebar** (`resources/js/Components/ui/sidebar/**`, reka-ui-based, hand-vendored from the official `default` JS-style registry and converted to this project's object-`defineProps` JS conventions; CLI's `reka-nova` style is TS-only and was incompatible). Desktop (≥ md) `collapsible="offcanvas"` toggle (state persisted via the component's `sidebar_state` cookie — replaces the prior hand-rolled `localStorage` key `jannah.adminSidebarCollapsed`); mobile (< md) opens as a reka-ui Sheet from the inline-start edge with backdrop + `Esc` + focus all handled by the component. Sidebar CSS vars (`--sidebar`, `--sidebar-foreground`, `--sidebar-accent`, `--sidebar-border`, `--sidebar-ring`) are remapped to the brand palette in `resources/css/app.css` (brand bg / white fg / `rgb(255 255 255 / 0.15)` active), preserving the prior visual look. Single `SidebarTrigger` in the header serves both modes via `useSidebar()`. This rebuild also fixed a real Tailwind specificity bug in the prior hand-rolled drawer where `rtl:translate-x-full` out-specified `lg:translate-x-0`, leaving the sidebar off-screen on desktop RTL. Covered by `resources/js/Layouts/__tests__/AdminShell.spec.js`.

### Still-open debt (deferred to post-P1 polish or P2+)

- **✅ RESOLVED — AdminShell sidebar collapse:** responsive hamburger drawer (mobile) + desktop collapsible sidebar, preference persisted to `localStorage`. See "P1 resolved items" above.
- **Inertia persistent layouts:** `defineOptions` layout for shell state preservation across navigations still deferred (in-file `TODO(P1)`).
- **ConfirmablePassword phone-only hazard:** `ConfirmablePasswordController` fails for phone-only users (email null); add phone-aware confirmation before any route uses `password.confirm` middleware.
- **✅ RESOLVED — ADR-002 production gate (P3):** medical-record audit logging and at-rest encryption are now in place. ADR-002 SUPERSEDED by ADR-003 (`docs/adr/003-encrypted-medical-records.md`) on 2026-05-20. Production now permits real patient data subject to ADR-003 remaining ACCEPTED in the canonical registry.
- **✅ RESOLVED — R-DataTable Migration (2026-05-20):** all 7 legacy admin list pages migrated to `AdminDataTable`. The legacy `<DataTable>` component and its Vitest spec were deleted; `foundation/index.js` no longer exports the legacy name. Migrated pages: `Admin/Catalog/Services.vue`, `Admin/Catalog/Categories.vue`, `Admin/Appointments/Index.vue`, `Admin/Doctors/Index.vue`, `Admin/Customers/Index.vue` (paginated), `Admin/Customers/Show.vue` (appointments list — paginated), `Admin/Payments/Index.vue` (paginated), `Admin/Coverage/Index.vue`. `Admin/Doctors/Schedule.vue` is a weekly slot-grid (not a list) and was exempt from migration.
- **APP_KEY rotation runbook (introduced in P3 via ADR-003):** ADR-003 mandates quarterly key rotation. The operational procedure is now documented at `docs/runbooks/app-key-rotation.md` (two-key window via `APP_PREVIOUS_KEYS`, idempotent `tinker` re-encryption loop, pre-flight backup checklist, failure-mode table). A dedicated `medical:rotate-encryption` artisan command remains deferred; the manual loop suffices for current data volume.
- **Cairo WOFF2:** Cairo shipped as TTF (~599KB); convert to WOFF2 in post-P1 polish (spec §3.3 prescribed woff2).
- **`--easing-spring` token:** cubic-bezier easing token not yet defined in `@theme`; add when overlay animations land.
- **Avatar cleanup:** old avatar file is not deleted on replace (post-P1 cleanup).
- **VerifyEmailController / ConfirmablePasswordController redirect:** redirects to `portal.home` after completion — needs role-aware redirect logic before staff email-verify gates are added.
- **Currency symbol ₪ hardcoded** in catalog/portal Vue (single-currency clinic); make config/locale-driven if multi-currency is ever needed.
- **App timezone `Asia/Hebron`:** defaults to `env('APP_TIMEZONE', 'Asia/Hebron')` — confirm/adjust before any production deployment in a different timezone (`config/app.php`).
- **Schedule slot-grid model (redesign):** doctor availability is a fixed 30-min grid (`config/clinic.php` slot keys + `App\Domain\Booking\Slots\SlotGrid`, canonical `'HH:MM'` strings — no more time-cast/Carbon contract). Storage = `doctor_schedule_slots` (per-weekday enabled slots) + `schedule_exceptions` (`closed`|`custom`) + `schedule_exception_slots` (per-date custom slots). `Service.duration_minutes` constrained to {30,60}; `AvailabilityService` offers a service's N (1–2) consecutive enabled+free grid slots. Legacy `doctor_schedules`/`DoctorSchedule`/`custom_start`/`custom_end`/`slot_interval_minutes` fully retired; the prior `datetime:H:i` cast debt is resolved (moot). Spec: `docs/superpowers/specs/2026-05-19-jannahclinic-p1-schedule-redesign-design.md`.
- **Vue `isTerminal` JS array duplicates PHP `AppointmentStatus::isTerminal()` (T10 acceptable MVP):** both must be updated together if the lifecycle grows — revisit with a generated TS enum or endpoint if a status is added.
- **Phone-only quick-created customers cannot self-authenticate to portal (T9 MVP):** staff-quick-created customers have no known password; needs password-reset or phone-OTP flow before portal self-login is required.
- **RTL CI check scoping:** RTL CI grep is scoped to authored dirs; vendored `shadcn-vue Components/ui/` excluded by design.

---

## P2 — Payments (bank-transfer receipt model)

Spec: `docs/superpowers/specs/2026-05-20-jannahclinic-p2-payments-design.md`.
Implementation plan: `docs/superpowers/plans/2026-05-20-jannahclinic-p2-payments.md`.

**Hybrid lifecycle:** `Payment` is **decoupled** from `AppointmentStatus`. No states added to the appointment state machine. Staff can confirm appointments without payment (trusted customers, pay-in-clinic). UI surfaces "awaiting payment" prompts in both surfaces but never blocks lifecycle transitions.

**Domain entities:**
- `App\Models\Payment` — 1:1 with `Appointment` (cascadeOnDelete unique FK), `amount` decimal:2, `status` enum (6 cases: pending → submitted → paid/rejected; paid → refund_pending → refunded), verified/refunded audit fields. pgsql CHECK constraints on status + amount>=0.
- `App\Models\PaymentReceipt` — N:1 with `Payment`; `file_path` (private `local` disk), `mime_type`, `file_size`, `status` (uploaded|rejected), uploader + rejector audit. CHECK on status + size>0.
- `App\Enums\PaymentStatus` — 6-case backed enum + `isTerminal()` + `isPaid()` helpers.
- `App\Domain\Payment\Services\PaymentService` (R7) — `uploadReceipt`/`verify`/`reject`/`markRefundPending`/`markRefunded` with state-source guards throwing `InvalidPaymentTransitionException`.
- `App\Observers\AppointmentObserver` — auto-marks `paid` Payment as `refund_pending` when its Appointment transitions to `Cancelled` or `Rejected`. Registered in AppServiceProvider.

**File storage:** receipts stored under `storage/app/receipts/{payment_id}/{uuid}.{ext}` on the private `local` disk — NEVER on `public`. Served only through `admin.payments.receipt-file` (ownership check + Storage::disk('local')->response()). Customers also view their own uploaded receipt via the portal payment page.

**P2 routes (10 new — locked by `tests/Feature/RouteNamesTest.php`):**

| Method | Path | Name | Auth |
|---|---|---|---|
| GET  | `/portal/appointments/{appointment}/payment` | `portal.appointments.payment` | customer (owner) |
| POST | `/portal/appointments/{appointment}/payment/upload` | `portal.appointments.payment.upload` | customer (owner) |
| GET  | `/admin/payments` | `admin.payments.index` | all staff |
| GET  | `/admin/payments/{payment}` | `admin.payments.show` | all staff |
| GET  | `/admin/payments/{payment}/receipts/{receipt}/file` | `admin.payments.receipt-file` | all staff |
| POST | `/admin/payments/{payment}/verify` | `admin.payments.verify` | manager only |
| POST | `/admin/payments/{payment}/reject` | `admin.payments.reject` | manager only |
| POST | `/admin/payments/{payment}/mark-refund-pending` | `admin.payments.mark-refund-pending` | manager only |
| POST | `/admin/payments/{payment}/mark-refunded` | `admin.payments.mark-refunded` | manager only |
| PUT  | `/admin/settings/bank` | `admin.settings.bank` | manager only |

**Bank-account info (R12):** 4 keys in `config/clinic.php` (`bank_name`, `bank_account_holder`, `bank_iban`, `bank_account_number`) with `env()` empty defaults; runtime values via `SettingService` (DB-backed override). Managed from `/admin/settings` ("بيانات الحساب البنكي" section); shown to customers on the portal payment page.

**Inertia shared prop `adminCounts.submitted_payments`:** lazy closure in `HandleInertiaRequests::share` — short-circuits to `null` for non-staff so the COUNT(*) query never runs for guests/customers. The `AdminShell` sidebar leaf "المدفوعات" renders a numeric badge when the count > 0.

---

## Related Documents

- ADR-001: `docs/adr/001-adopt-methodology-kit.md`
- ADR-002 (SUPERSEDED): `docs/adr/002-basic-security-posture.md`
- ADR-003 (ACTIVE): `docs/adr/003-encrypted-medical-records.md`
- Definition of Done: `docs/DEFINITION-OF-DONE.md`
- Spec roadmap (§2 P1–P5): `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md`
- Domain Model: `docs/DOMAIN-MODEL.md`
