# jannahclinic — Domain Model

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: domain
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Last updated: 2026-05-20 (P2 — Payment + PaymentReceipt entities, PaymentStatus enum, AppointmentObserver auto-refund; hybrid lifecycle, AppointmentStatus unchanged)
> P0 entities fully documented; P1 Task 2 entities (ServiceCategory, Service), P1 Task 3 entities (DoctorProfile, doctor_service pivot), P1 schedule slot-grid (DoctorScheduleSlot, ScheduleException, ScheduleExceptionSlot — redesign), P1 Task 5 entities (HomeServiceCoverageArea), and P1 Task 6 entities (Appointment, ServiceAddress) added below.

**R6 obligation:** this file MUST be updated in the same change set as any model,
migration, enum, or relationship change.

---

## P0 Entities

### `User`

Table: `users`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `name` | varchar(255) | NOT NULL |
| `email` | varchar(255) | nullable, unique |
| `phone` | varchar(32) | nullable, unique |
| `password` | varchar(255) | NOT NULL (hashed — bcrypt) |
| `role` | varchar(20) | NOT NULL, default `customer`, indexed |
| `is_active` | boolean | NOT NULL, default `true`, indexed (Polish-D) |
| `remember_token` | varchar(100) | nullable |
| `email_verified_at` | timestamp | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres-only CHECK constraints** (skipped on SQLite test DB; CI Postgres is
the authoritative gate — see `phpunit.xml` note and ADR-002):

```sql
CONSTRAINT users_role_check
    CHECK (role IN ('manager','doctor','receptionist','customer'))

CONSTRAINT users_email_or_phone
    CHECK (email IS NOT NULL OR phone IS NOT NULL)
```

**Fillable (attribute #[Fillable]):** `name`, `email`, `password`, `phone`, `role`, `is_active`
**Hidden:** `password`, `remember_token`
**Casts:** `email_verified_at → datetime`, `password → hashed`, `role → UserRole`, `is_active → boolean`

**`is_active` (Polish-D):** soft-disable flag used by the Customer-admin surface
(`Admin\CustomerController@toggleActive`). `LoginRequest::authenticate()` rejects
inactive users with the uniform `auth.failed` error (defence in depth — no info
leak about disabled accounts). No hard delete in the customer admin UI:
`appointments.customer_id` is `cascadeOnDelete`, so deletion would silently
destroy appointment history; the toggle is the right UX.

**Key methods:**

- `isStaff(): bool` — delegates to `UserRole::isStaff()`; true for manager,
  doctor, receptionist.
- `customerProfile(): HasOne` — one-to-one to `CustomerProfile` (customer role only).

**Implements `MustVerifyEmail` (hazard note):** Portal routes MUST NOT include
the `verified` middleware alias. Phone-only customers have no email and would be
permanently trapped on `/email/verify` if verification were required. See
`app/Models/User.php` docblock and ADR-002 for the P1 resolution path.

**Model path:** `app/Models/User.php`
**Factory:** `Database\Factories\UserFactory`

---

### `CustomerProfile`

Table: `customer_profiles`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `user_id` | bigint unsigned | NOT NULL, FK → `users.id`, CASCADE DELETE, UNIQUE |
| `date_of_birth` | date | nullable |
| `gender` | varchar(16) | nullable |
| `notes` | text | nullable |
| `avatar_path` | varchar(255) | nullable |
| `profile_completed_at` | timestamp | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Fillable:** `user_id`, `date_of_birth`, `gender`, `notes`, `avatar_path`,
`profile_completed_at`
**Casts:** `date_of_birth → date`, `profile_completed_at → datetime`

**Relationships:**
- `user(): BelongsTo` — belongs to `User`

**Notes:**
- Created automatically by `AuthService::registerCustomer` inside a
  `DB::transaction` when a customer registers. Always exists for any user with
  `role = customer`.
- `avatar_path` stores the filesystem path relative to `storage/app/public`.
  P1 debt: the old avatar file is not deleted on replacement.
- `UNIQUE(user_id)` enforces the one-to-one relationship at the DB level (R12).
- `notes` (Polish-D): clinic-staff-managed free-text note about the customer,
  edited from the Customer-admin Show page (`Admin\CustomerController@update`).
  Deliberately distinct from P3 medical records (out of P1 scope, ADR-002).

**Model path:** `app/Models/CustomerProfile.php`

---

### `UserRole` (PHP Enum)

`App\Enums\UserRole` — backed enum (`string`)

| Case | Value | `isStaff()` | `isCustomer()` |
|------|-------|-------------|----------------|
| `Manager` | `'manager'` | `true` | `false` |
| `Doctor` | `'doctor'` | `true` | `false` |
| `Receptionist` | `'receptionist'` | `true` | `false` |
| `Customer` | `'customer'` | `false` | `true` |

Methods: `isStaff(): bool`, `isCustomer(): bool`

Used in: `User::$casts`, `EnsureUserHasRole` middleware, `AuthService`,
`AuthenticatedSessionController` (post-login redirect).

**Enum path:** `app/Enums/UserRole.php`

---

### `Setting`

Table: `settings`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `key` | varchar(255) | NOT NULL, unique |
| `value` | varchar(255) | NOT NULL |
| `created_at` / `updated_at` | timestamp | nullable |

**Fillable:** `key`, `value`

**Purpose:** Key/value store for config-driven settings that can be overridden at
runtime. Serves P1+ features (e.g., booking pricing in P2) with fallback to
`config/clinic.php` defaults when no row exists. Accessed via
`App\Domain\Settings\Services\SettingService`.

**Model path:** `app/Models/Setting.php`

---

## Entity Relationship (P0)

```
users (1) ─────── (0..1) customer_profiles
```

Every `CustomerProfile` belongs to exactly one `User`. The `UNIQUE(user_id)`
constraint and `HasOne` / `BelongsTo` pair enforce this at both DB and ORM layers.

---

## P1 Entities (Task 2 — Service Catalog)

### `ServiceCategory`

Table: `service_categories`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `name` | varchar(255) | NOT NULL |
| `slug` | varchar(255) | NOT NULL, unique |
| `color_variant` | varchar(16) | NOT NULL, default `brand` |
| `display_order` | integer | NOT NULL, default `0` |
| `is_active` | boolean | NOT NULL, default `true` |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres-only CHECK constraint:**

```sql
CONSTRAINT service_categories_color_check
    CHECK (color_variant IN ('brand','gold'))
```

**Fillable:** `name`, `slug`, `color_variant`, `display_order`, `is_active`
**Casts:** `is_active → boolean`, `display_order → integer`

**Relationships:**
- `services(): HasMany` → `Service` (FK `category_id`)

**Model path:** `app/Models/ServiceCategory.php`

---

### `Service`

Table: `services`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `category_id` | bigint unsigned | NOT NULL, FK → `service_categories.id` RESTRICT DELETE |
| `name` | varchar(255) | NOT NULL |
| `description` | text | nullable |
| `base_price` | decimal(10,2) | NOT NULL |
| `duration_minutes` | integer | NOT NULL |
| `home_service_enabled` | boolean | NOT NULL, default `false` |
| `icon_key` | varchar(255) | nullable |
| `is_active` | boolean | NOT NULL, default `true` |
| `display_order` | integer | NOT NULL, default `0` |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres-only CHECK constraints:**

```sql
CONSTRAINT services_base_price_check  CHECK (base_price >= 0)
CONSTRAINT services_duration_check    CHECK (duration_minutes > 0)
```

**Fillable:** `category_id`, `name`, `description`, `base_price`, `duration_minutes`,
`home_service_enabled`, `icon_key`, `is_active`, `display_order`
**Casts:** `base_price → decimal:2`, `duration_minutes → integer`,
`home_service_enabled → boolean`, `is_active → boolean`, `display_order → integer`

**Relationships:**
- `category(): BelongsTo` → `ServiceCategory`
- `doctors(): BelongsToMany` → `DoctorProfile` via `doctor_service` pivot (using `DoctorServicePivot`)

**Model path:** `app/Models/Service.php`

---

## P1 Entities (Task 3 — Doctor Profiles + Service Assignment)

### `DoctorProfile`

Table: `doctor_profiles`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `user_id` | bigint unsigned | NOT NULL, FK → `users.id`, CASCADE DELETE, UNIQUE |
| `specialty` | varchar(255) | NOT NULL |
| `bio` | text | nullable |
| `rating_average` | decimal(2,1) | nullable |
| `is_bookable` | boolean | NOT NULL, default `true` |
| `display_order` | integer | NOT NULL, default `0` |
| `created_at` / `updated_at` | timestamp | nullable |

**Fillable (attribute #[Fillable]):** `user_id`, `specialty`, `bio`, `rating_average`, `is_bookable`, `display_order`
**Casts:** `rating_average → decimal:1`, `is_bookable → boolean`, `display_order → integer`

**Relationships:**
- `user(): BelongsTo` → `User`
- `services(): BelongsToMany` → `Service` via `doctor_service` pivot (using `DoctorServicePivot`)
- `scheduleSlots(): HasMany` → `DoctorScheduleSlot` (enabled 30-min slots per weekday)
- `scheduleExceptions(): HasMany` → `ScheduleException` (each `slots(): HasMany ScheduleExceptionSlot`)
- `appointments(): HasMany` → `Appointment` (via `doctor_profile_id`) — added T7 for `AvailabilityService`

**Notes:**
- Created by `DoctorController::store` via `AuthService::createStaff` (role=doctor) + `DoctorProfile::create` inside a `DB::transaction`.
- Admin CRUD is manager-only; list GET is readable by all staff.

**Model path:** `app/Models/DoctorProfile.php`
**Factory:** `Database\Factories\DoctorProfileFactory`

---

### `doctor_service` (pivot)

Table: `doctor_service`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `service_id` | bigint unsigned | NOT NULL, FK → `services.id`, CASCADE DELETE |
| `price_override` | decimal(10,2) | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(doctor_profile_id, service_id)`

**Postgres-only CHECK constraint:**

```sql
CONSTRAINT doctor_service_price_check
    CHECK (price_override IS NULL OR price_override >= 0)
```

**Pivot model:** `App\Models\DoctorServicePivot` (extends `Pivot`)
**Cast:** `price_override → decimal:2`

---

## P1 Entities (Task 4 + Schedule Redesign — fixed 30-min slot grid)

> **Redesigned (P1 amendment, see `docs/superpowers/specs/2026-05-19-jannahclinic-p1-schedule-redesign-design.md`).**
> The legacy weekly-window model (`doctor_schedules` morning/evening start-end +
> `slot_interval_minutes`, and `schedule_exceptions.custom_start/custom_end`) was
> **retired**. A doctor's availability is now a set of enabled fixed **30-minute**
> slots; exceptions are `closed` or a per-date `custom` slot set.

### Slot grid (config / `SlotGrid`)

Config (`config/clinic.php`, R12): `slot_minutes=30`, `day_start='08:00'`,
`day_end='22:00'`, `band_split='15:00'` (morning/evening grouping is presentational
— time is contiguous). `App\Domain\Booking\Slots\SlotGrid` derives the canonical
ordered grid `08:00 … 21:30` (28 starts), with `all/morning/evening/isValid/blockFrom`.
A `Service` is 30 or 60 minutes (`duration_minutes` validated `in:30,60`, pgsql
`services_duration_check CHECK (duration_minutes IN (30,60))`); `Service::slotCount()`
= 1 or 2 consecutive slots.

### `DoctorScheduleSlot`

Table: `doctor_schedule_slots` — one row = one enabled half-hour for a weekday.

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `weekday` | tinyint unsigned | NOT NULL (0=Sunday … 6=Saturday, Carbon `dayOfWeek`) |
| `slot_start` | varchar(5) | NOT NULL, canonical `'HH:MM'` ∈ `SlotGrid::all()` |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(doctor_profile_id, weekday, slot_start)`. **Index:** `(doctor_profile_id, weekday)`.
**Postgres CHECK:** `dss_weekday_check CHECK (weekday BETWEEN 0 AND 6)`.
**Fillable:** `doctor_profile_id`, `weekday`, `slot_start`. **Casts:** `weekday → integer`.
**Relationships:** `doctor(): BelongsTo` → `DoctorProfile`.
**Model path:** `app/Models/DoctorScheduleSlot.php`.

---

### `ScheduleException`

Table: `schedule_exceptions` (restructured — `custom_start`/`custom_end` dropped).

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `date` | date | NOT NULL |
| `type` | varchar(16) | NOT NULL (`closed` or `custom`) |
| `note` | varchar(255) | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(doctor_profile_id, date)`.
**Postgres CHECK:** `schedule_exceptions_type_check CHECK (type IN ('closed','custom'))`.
**Fillable:** `doctor_profile_id`, `date`, `type`, `note`. **Casts:** `date → date`.
**Relationships:** `doctor(): BelongsTo` → `DoctorProfile`; `slots(): HasMany` → `ScheduleExceptionSlot`.
**Semantics:** `closed` → doctor unavailable that whole date (overrides weekly).
`custom` → availability that date is exactly the linked `ScheduleExceptionSlot`
set (a `custom` exception with zero slots = effectively closed). `updateOrCreate`
keyed on `(doctor_profile_id, date)`. Admin mutations manager-only; view all-staff.
**Model path:** `app/Models/ScheduleException.php`.

### `ScheduleExceptionSlot`

Table: `schedule_exception_slots` — per-date custom slot (only for `type=custom`).

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `schedule_exception_id` | bigint unsigned | NOT NULL, FK → `schedule_exceptions.id`, CASCADE DELETE |
| `slot_start` | varchar(5) | NOT NULL, canonical `'HH:MM'` ∈ `SlotGrid::all()` |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(schedule_exception_id, slot_start)`.
**Fillable:** `schedule_exception_id`, `slot_start`.
**Relationships:** `exception(): BelongsTo` → `ScheduleException`.
**Model path:** `app/Models/ScheduleExceptionSlot.php`.

`AvailabilityService` derives bookable slots from this grid: enabled set =
`closed`→∅ / `custom`→its slots / else the weekday `DoctorScheduleSlot` set;
a service needing N (1–2) consecutive grid slots is offered where all N are
enabled, free of non-terminal appointments, and not past.

---

---

## P1 Entities (Task 5 — Coverage Areas + Home-Surcharge Setting)

### `HomeServiceCoverageArea`

Table: `home_service_coverage_areas`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `name` | varchar(255) | NOT NULL |
| `is_active` | boolean | NOT NULL, default `true` |
| `display_order` | integer | NOT NULL, default `0` |
| `created_at` / `updated_at` | timestamp | nullable |

**Fillable (attribute #[Fillable]):** `name`, `is_active`, `display_order`
**Casts:** `is_active → boolean`, `display_order → integer`

**Notes:**
- Admin CRUD is manager-only; list GET is readable by all staff.
- T6 will add `ServiceAddress.coverage_area_id` as a FK referencing this table with `restrictOnDelete`. The `destroy` method is already structured with a `QueryException` catch to handle that constraint when it arrives.

**Model path:** `app/Models/HomeServiceCoverageArea.php`

---

### `home_surcharge_pct` (runtime setting)

Stored in the `settings` table under key `home_surcharge_pct` via `SettingService`.
Falls back to `config('clinic.home_surcharge_pct')` (default `30`) when no row exists.
Managed through `Admin/Settings/Index` (PUT `/admin/settings/surcharge`).

---

## P1 Entities (Task 6 — Appointment + ServiceAddress)

### `AppointmentStatus` (PHP Enum)

`App\Enums\AppointmentStatus` — backed enum (`string`)

| Case | Value | `isTerminal()` |
|------|-------|----------------|
| `Requested` | `'requested'` | `false` |
| `Confirmed` | `'confirmed'` | `false` |
| `Rejected` | `'rejected'` | `true` |
| `Completed` | `'completed'` | `true` |
| `Cancelled` | `'cancelled'` | `true` |
| `NoShow` | `'no_show'` | `true` |
| `Rescheduled` | `'rescheduled'` | `true` |

**7-state lifecycle:** `Requested` and `Confirmed` are the only non-terminal (active) states. All others are terminal — no further transitions are permitted once reached.

**Allowed transitions:**

| From | Allowed next states |
|------|---------------------|
| `Requested` | `Confirmed`, `Rejected`, `Cancelled`, `Rescheduled` |
| `Confirmed` | `Completed`, `NoShow`, `Cancelled`, `Rescheduled` |
| Any terminal | _(none — empty array)_ |

**Methods:** `isTerminal(): bool`, `allowedNext(): self[]`, `canTransitionTo(self $to): bool`

**Note:** Booking write logic and transition enforcement (service classes) arrive in T8/T10. This enum provides the pure state-machine definition only.

**Enum path:** `app/Enums/AppointmentStatus.php`

---

### `DeliveryMode` (PHP Enum)

`App\Enums\DeliveryMode` — backed enum (`string`)

| Case | Value |
|------|-------|
| `Center` | `'center'` |
| `Home` | `'home'` |

Used in: `Appointment::$casts` (delivery_mode column), DB CHECK constraint `appointments_mode_check`.

**Enum path:** `app/Enums/DeliveryMode.php`

---

### `Appointment`

Table: `appointments`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `customer_id` | bigint unsigned | NOT NULL, FK → `users.id`, CASCADE DELETE |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `service_id` | bigint unsigned | NOT NULL, FK → `services.id`, RESTRICT DELETE |
| `start_at` | datetime | NOT NULL |
| `end_at` | datetime | NOT NULL |
| `status` | varchar(16) | NOT NULL, default `requested` |
| `price_at_booking` | decimal(10,2) | NOT NULL |
| `delivery_mode` | varchar(8) | NOT NULL |
| `home_surcharge_amount` | decimal(10,2) | NOT NULL, default `0` |
| `created_by_role` | varchar(20) | NOT NULL |
| `cancellation_reason` | varchar(255) | nullable |
| `rescheduled_from_id` | bigint unsigned | nullable, FK → `appointments.id`, NULL ON DELETE |
| `created_at` / `updated_at` | timestamp | nullable |

**Indexes:** `(doctor_profile_id, start_at)`, `(customer_id, status)`

**Postgres-only CHECK constraints:**

```sql
CONSTRAINT appointments_status_check  CHECK (status IN ('requested','confirmed','rejected','completed','cancelled','no_show','rescheduled'))
CONSTRAINT appointments_mode_check    CHECK (delivery_mode IN ('center','home'))
CONSTRAINT appointments_price_check   CHECK (price_at_booking >= 0)
CONSTRAINT appointments_time_check    CHECK (end_at > start_at)
```

**Fillable (attribute #[Fillable]):** `customer_id`, `doctor_profile_id`, `service_id`, `start_at`, `end_at`, `status`, `price_at_booking`, `delivery_mode`, `home_surcharge_amount`, `created_by_role`, `cancellation_reason`, `rescheduled_from_id`

**Casts:** `start_at → datetime`, `end_at → datetime`, `status → AppointmentStatus`, `delivery_mode → DeliveryMode`, `created_by_role → UserRole`, `price_at_booking → decimal:2`, `home_surcharge_amount → decimal:2`

**Relationships:**
- `customer(): BelongsTo` → `User` (via `customer_id`)
- `doctor(): BelongsTo` → `DoctorProfile` (via `doctor_profile_id`)
- `service(): BelongsTo` → `Service`
- `serviceAddress(): HasOne` → `ServiceAddress`
- `rescheduledFrom(): BelongsTo` → `Appointment` (self-referential via `rescheduled_from_id`) — tracks the original appointment that was rescheduled.

**Lifecycle note (T10):** Status transitions are enforced by `AppointmentTransitionService`. Allowed transitions are defined in `AppointmentStatus::allowedNext()`. Rescheduling creates a NEW appointment with `status=requested` and `rescheduled_from_id = old.id`, sets the old appointment's `status=rescheduled` — both in one `DB::transaction`. Customers may cancel or reschedule their own non-terminal appointments; staff may transition any appointment via the admin panel. Illegal transitions throw `InvalidTransitionException`.

**Notes:**
- `rescheduled_from_id` self-references `appointments.id` to track rescheduling lineage (nullable, null-on-delete).
- Postgres CHECK constraints are skipped on SQLite (tests); CI Postgres is the authoritative gate (ADR-002).

**Model path:** `app/Models/Appointment.php`

---

### `ServiceAddress`

Table: `service_addresses`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `appointment_id` | bigint unsigned | NOT NULL, FK → `appointments.id`, CASCADE DELETE, UNIQUE |
| `coverage_area_id` | bigint unsigned | NOT NULL, FK → `home_service_coverage_areas.id`, RESTRICT DELETE |
| `address_text` | varchar(255) | NOT NULL |
| `location_note` | varchar(255) | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**One-to-one with Appointment:** `UNIQUE(appointment_id)` enforces at DB level. Every home-delivery appointment has exactly one `ServiceAddress`.

**FK `coverage_area_id` → `home_service_coverage_areas` with `restrictOnDelete`:** a coverage area cannot be deleted while any service address references it. The `HomeServiceCoverageArea` controller destroy method already has a `QueryException` catch for this constraint (added in T5).

**Fillable (attribute #[Fillable]):** `appointment_id`, `coverage_area_id`, `address_text`, `location_note`

**Relationships:**
- `appointment(): BelongsTo` → `Appointment`
- `coverageArea(): BelongsTo` → `HomeServiceCoverageArea` (via `coverage_area_id`)

**Model path:** `app/Models/ServiceAddress.php`

---

## Entity Relationship (P0 + P1 Tasks 2–6)

```
users (1) ─────── (0..1) customer_profiles
users (1) ─────── (0..1) doctor_profiles
service_categories (1) ── (*) services
services (*) ──────────── (*) doctor_profiles  [pivot: doctor_service (+price_override)]
doctor_profiles (1) ────── (*) doctor_schedule_slots [slot-grid redesign]
doctor_profiles (1) ────── (*) schedule_exceptions   [redesign: closed|custom]
schedule_exceptions (1) ── (*) schedule_exception_slots [custom-date slots]
home_service_coverage_areas (1) ── (*) service_addresses  [Task 6 — restrictOnDelete]
users (1) ─────────────── (*) appointments            [as customer_id]
doctor_profiles (1) ────── (*) appointments
services (1) ──────────── (*) appointments
appointments (0..1) ─────── (0..1) service_addresses  [1:1, home-delivery only]
appointments (0..1) ─────── (*) appointments          [rescheduled_from_id self-ref; T10 — Appointment::rescheduledFrom() BelongsTo]
```

---

## P2 Entities — Payments (bank-transfer receipt model)

Spec: `docs/superpowers/specs/2026-05-20-jannahclinic-p2-payments-design.md`.
The Appointment ↔ Payment relation is 1:1 (UNIQUE FK on `appointment_id` cascadeOnDelete);
Payment ↔ PaymentReceipt is 1:N (each upload attempt = one row; current = latest non-rejected).

### `Payment`

Table: `payments`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `appointment_id` | bigint unsigned | NOT NULL, FK → `appointments.id` CASCADE DELETE, **UNIQUE** |
| `amount` | decimal(10,2) | NOT NULL |
| `status` | varchar(16) | NOT NULL, default `pending` |
| `verified_at` | timestamp | nullable |
| `verified_by` | bigint unsigned | nullable, FK → `users.id`, nullOnDelete |
| `refunded_at` | timestamp | nullable |
| `refunded_by` | bigint unsigned | nullable, FK → `users.id`, nullOnDelete |
| `refund_reference` | varchar(255) | nullable |
| `rejection_reason` | text | nullable |
| `notes` | text | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres CHECK:** `status IN ('pending','submitted','paid','rejected','refund_pending','refunded')`, `amount >= 0`.
**Index:** `(status, created_at)` — supports the admin payments index "submitted first" filter.
**Fillable:** all of the above except `id`/timestamps.
**Casts:** `amount → decimal:2`, `status → PaymentStatus`, `verified_at`/`refunded_at` → `datetime`.
**Relations:** `appointment(): BelongsTo` → `Appointment`; `verifier(): BelongsTo` → `User`; `refunder(): BelongsTo` → `User`; `receipts(): HasMany` → `PaymentReceipt` (orderByDesc id).
**Model path:** `app/Models/Payment.php`.

### `PaymentReceipt`

Table: `payment_receipts`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `payment_id` | bigint unsigned | NOT NULL, FK → `payments.id` CASCADE DELETE |
| `uploaded_by` | bigint unsigned | NOT NULL, FK → `users.id`, restrictOnDelete |
| `file_path` | varchar(255) | NOT NULL (relative to `storage/app/receipts/`, **never** under `storage/app/public/`) |
| `file_size` | unsigned int | NOT NULL (bytes) |
| `mime_type` | varchar(64) | NOT NULL |
| `status` | varchar(16) | NOT NULL, default `uploaded` |
| `rejection_reason` | text | nullable |
| `rejected_at` | timestamp | nullable |
| `rejected_by` | bigint unsigned | nullable, FK → `users.id`, nullOnDelete |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres CHECK:** `status IN ('uploaded','rejected')`, `file_size > 0`.
**Index:** `(payment_id, id)` — supports `latest receipt` lookups.
**Fillable:** `payment_id`, `uploaded_by`, `file_path`, `file_size`, `mime_type`, `status`, `rejection_reason`, `rejected_at`, `rejected_by`.
**Casts:** `rejected_at → datetime`.
**Relations:** `payment()` / `uploader()` / `rejector()`.
**Model path:** `app/Models/PaymentReceipt.php`.

### `PaymentStatus` (PHP Enum)

`App\Enums\PaymentStatus` — string-backed enum with 6 cases:

| Case | Value | Notes |
|------|-------|-------|
| `Pending` | `pending` | Payment row exists, no receipt yet |
| `Submitted` | `submitted` | Receipt uploaded, manager hasn't acted |
| `Paid` | `paid` | Manager verified the receipt |
| `Rejected` | `rejected` | Manager rejected (reason on payment + latest receipt); customer can re-upload → back to submitted |
| `RefundPending` | `refund_pending` | Auto (Appointment Cancelled/Rejected while paid) OR manual (Rescheduled / staff decision) |
| `Refunded` | `refunded` | Terminal. Manager recorded the reverse-transfer execution |

Helpers: `isTerminal(): bool` (true only for `Refunded`), `isPaid(): bool`.
**Enum path:** `app/Enums/PaymentStatus.php`.

### Hybrid lifecycle + auto-refund listener

`AppointmentStatus` (P1) is **unchanged**. The hybrid integration lives in `App\Observers\AppointmentObserver::updated()`: when an Appointment's status transitions to `Cancelled` or `Rejected` AND its Payment is in state `paid`, the observer calls `PaymentService::markRefundPending()` so the refund queue picks it up. Other terminal transitions (`Completed`, `NoShow`, `Rescheduled`) deliberately do NOT auto-trigger — see spec §3 rationale.

Registered in `AppServiceProvider::boot` via `Appointment::observe(AppointmentObserver::class)`.

---

## P3+ Entities (OUT OF SCOPE — YAGNI)

The following entities are explicitly deferred to P3–P5. They MUST NOT be
modelled, migrated, or referenced until their phase begins:

> MedicalRecord, MedicalEntry, Prescription, MembershipPlan, UserMembership, LoyaltyTransaction, Notification

Roadmap: `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` §2
and the `clinic` reference feature inventory.
