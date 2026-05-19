# jannahclinic — Domain Model

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: domain
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Last updated: 2026-05-20 (P1 Task 6 — Appointment + ServiceAddress entities + AppointmentStatus/DeliveryMode enums)
> P0 entities fully documented; P1 Task 2 entities (ServiceCategory, Service), P1 Task 3 entities (DoctorProfile, doctor_service pivot), P1 Task 4 entities (DoctorSchedule, ScheduleException), P1 Task 5 entities (HomeServiceCoverageArea), and P1 Task 6 entities (Appointment, ServiceAddress) added below.

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

**Fillable (attribute #[Fillable]):** `name`, `email`, `password`, `phone`, `role`
**Hidden:** `password`, `remember_token`
**Casts:** `email_verified_at → datetime`, `password → hashed`, `role → UserRole`

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
- `schedules(): HasMany` → `DoctorSchedule`
- `scheduleExceptions(): HasMany` → `ScheduleException`

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

## P1 Entities (Task 4 — Doctor Schedules + Exceptions)

### `DoctorSchedule`

Table: `doctor_schedules`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `weekday` | smallint | NOT NULL (0=Sunday … 6=Saturday) |
| `morning_enabled` | boolean | NOT NULL, default `false` |
| `morning_start` | time | nullable |
| `morning_end` | time | nullable |
| `evening_enabled` | boolean | NOT NULL, default `false` |
| `evening_start` | time | nullable |
| `evening_end` | time | nullable |
| `slot_interval_minutes` | integer | NOT NULL, default `30` |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(doctor_profile_id, weekday)`

**Postgres-only CHECK constraints:**

```sql
CONSTRAINT doctor_schedules_weekday_check  CHECK (weekday BETWEEN 0 AND 6)
CONSTRAINT doctor_schedules_interval_check CHECK (slot_interval_minutes > 0)
```

**Fillable:** `doctor_profile_id`, `weekday`, `morning_enabled`, `morning_start`, `morning_end`, `evening_enabled`, `evening_start`, `evening_end`, `slot_interval_minutes`
**Casts:** `weekday → integer`, `morning_enabled → boolean`, `evening_enabled → boolean`, `slot_interval_minutes → integer`

**Relationships:**
- `doctor(): BelongsTo` → `DoctorProfile` (via `doctor_profile_id`)

**Model path:** `app/Models/DoctorSchedule.php`

---

### `ScheduleException`

Table: `schedule_exceptions`

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK, auto-increment |
| `doctor_profile_id` | bigint unsigned | NOT NULL, FK → `doctor_profiles.id`, CASCADE DELETE |
| `date` | date | NOT NULL |
| `type` | varchar(16) | NOT NULL (`closed` or `custom_hours`) |
| `custom_start` | time | nullable |
| `custom_end` | time | nullable |
| `note` | varchar(255) | nullable |
| `created_at` / `updated_at` | timestamp | nullable |

**Unique:** `(doctor_profile_id, date)`

**Postgres-only CHECK constraint:**

```sql
CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom_hours'))
```

**Fillable:** `doctor_profile_id`, `date`, `type`, `custom_start`, `custom_end`, `note`
**Casts:** `date → date`

**Relationships:**
- `doctor(): BelongsTo` → `DoctorProfile` (via `doctor_profile_id`)

**Notes:**
- `updateOrCreate` keyed on `(doctor_profile_id, date)` — one exception per doctor per date.
- Admin mutations (add/delete) are manager-only; the schedule view page is all-staff.

**Model path:** `app/Models/ScheduleException.php`

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

**Notes:**
- `rescheduled_from_id` self-references `appointments.id` to track rescheduling lineage (nullable, null-on-delete).
- Booking write logic and status transition enforcement arrive in T8/T10. The `AppointmentStatus` enum defines the pure state machine; no service-layer guard exists yet.
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
doctor_profiles (1) ────── (*) doctor_schedules      [Task 4]
doctor_profiles (1) ────── (*) schedule_exceptions   [Task 4]
home_service_coverage_areas (1) ── (*) service_addresses  [Task 6 — restrictOnDelete]
users (1) ─────────────── (*) appointments            [as customer_id]
doctor_profiles (1) ────── (*) appointments
services (1) ──────────── (*) appointments
appointments (0..1) ─────── (0..1) service_addresses  [1:1, home-delivery only]
appointments (0..1) ─────── (*) appointments          [rescheduled_from_id self-ref]
```

---

## P2+ Entities (OUT OF SCOPE — YAGNI)

The following entities are explicitly deferred to P2–P5. They MUST NOT be
modelled, migrated, or referenced until their phase begins:

> Payment, Receipt, MedicalRecord, MedicalEntry,
> Prescription, MembershipPlan, UserMembership, LoyaltyTransaction, Notification

Roadmap: `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` §2
and the `clinic` reference feature inventory.
