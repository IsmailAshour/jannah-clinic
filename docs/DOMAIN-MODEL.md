# jannahclinic — Domain Model

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: domain
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Last updated: 2026-05-19 (P0 foundation — Task 10)
> P0 scope only. P1–P5 entities are explicitly OUT OF SCOPE — see §P1+ below.

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

## Entity Relationship (P0)

```
users (1) ─────── (0..1) customer_profiles
```

Every `CustomerProfile` belongs to exactly one `User`. The `UNIQUE(user_id)`
constraint and `HasOne` / `BelongsTo` pair enforce this at both DB and ORM layers.

---

## P1+ Entities (OUT OF SCOPE — YAGNI)

The following entities are explicitly deferred to P1–P5. They MUST NOT be
modelled, migrated, or referenced in P0 code. They are listed here only as a
forward reference for ARCHITECTURE.md §P0 Boundary:

> ServiceCategory, Service, DoctorProfile, DoctorSchedule, DoctorScheduleException,
> ServiceAddress, Appointment, Payment, Receipt, MedicalRecord, MedicalEntry,
> Prescription, MembershipPlan, UserMembership, LoyaltyTransaction, Notification

Roadmap: `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` §2
and the `clinic` reference feature inventory.
