# jannahclinic — Customer Admin (Polish-D) — Design

> **Status:** APPROVED — small admin scope, polish-style (post-P1). Builds on P0+P1.
> **Date:** 2026-05-20

## 1. Motivation

P1 shipped without an admin customer management page. Customers exist via public
registration and admin quick-create during booking, but staff cannot list,
search, view, or edit them. This page closes that gap.

## 2. Decisions

1. **Soft-disable via `users.is_active` (boolean default true).** No hard delete in the UI — `appointments.customer_id` is `cascadeOnDelete`, so hard delete would silently destroy appointment history. Toggle-active is the right UX. `LoginRequest` rejects inactive users at login (defence in depth).
2. **Editable fields:** User: `name`, `email`, `phone`, `is_active`. CustomerProfile: `date_of_birth`, `gender`, `notes`. NEVER editable from this surface: `password` (separate flow, later), `role` (Customer fixed — promotion is a different workflow), `email_verified_at`, `avatar_path` (customer owns their avatar — admin shouldn't change it from here).
3. **List always filters `role = Customer`.** Staff users are not exposed here. Search query ORs across `name`/`email`/`phone` (LIKE `%q%`). Optional status filter: all / active / inactive.
4. **Show page:** profile block (User + CustomerProfile fields) + appointments table (eager: `service:id,name`, `doctor.user:id,name`; columns: status (StatusBadge), doctor, service, start_at, delivery_mode, price_at_booking) + basic stats (total bookings, completed, no-show, last visit datetime).
5. **Routes (canonical names — UNPREFIXED under the `admin.` group):** `admin.customers.index`, `admin.customers.show`, `admin.customers.update`, `admin.customers.toggle-active`. Reads = all staff; mutations = manager only (consistent with T2/T5 precedent). No destroy.
6. **Nav:** add `العملاء` under the `العيادة` group in `AdminShell` (with lucide `Contact2`).

## 3. Schema

**New migration** (additive, safe): `..._add_is_active_to_users.php` adds `users.is_active boolean default true` (with pgsql index on the column for active-list queries). No back-fill needed (default applies). `User` model: add `is_active` to `#[Fillable]` and `$casts` (boolean).

## 4. AuthService / LoginRequest

`LoginRequest::authenticate()` (or wherever `Auth::attempt` is called) must reject when `$user->is_active === false`. Concrete: after `resolveByIdentifier` finds the user, check `!$user->is_active` → throw the same `auth.failed` `ValidationException` (do not leak "account disabled" information — uniform failure message, like industry norm).

## 5. Backend

- `app/Http/Controllers/Admin/CustomerController.php`:
  - `index(Request)` — paginate `User::where('role', UserRole::Customer)` with optional `q`/`status` query params; eager `customerProfile`. Returns Inertia page with paginated customers + the current filter state.
  - `show(User $customer)` — `abort_unless($customer->role === UserRole::Customer, 404)`; load `customerProfile`, paginated `appointments()->with(['service:id,name','doctor.user:id,name'])->latest('start_at')`; compute stats.
  - `update(Request, User $customer)` — `abort_unless($customer->role === UserRole::Customer, 404)`; validate name (required), email (nullable|email|unique except own), phone (nullable|unique except own), is_active (boolean), date_of_birth (nullable|date|before:today), gender (nullable|string|max:16), notes (nullable|string|max:2000). Update User + upsert CustomerProfile in a transaction. `back()->with('success', 'تم حفظ بيانات العميل.')`.
  - `toggleActive(User $customer)` — `abort_unless` customer role; flip `is_active`; save. `back()->with('success', ...)`.

## 6. Frontend

- `resources/js/Pages/Admin/Customers/Index.vue`:
  - `AdminShell` + `PageHeader title="العملاء"`.
  - Filter bar: text input (q), status select (الكل / نشط / غير نشط). Inertia GET preserves filters.
  - `DataTable` columns: الاسم, الهاتف, البريد, الحالة (StatusBadge active/inactive), إجراءات (عرض).
  - `PageStates` empty/loading.
- `resources/js/Pages/Admin/Customers/Show.vue`:
  - `AdminShell` + `PageHeader title="customer.name"` with toggle-active button (manager only) + edit button (opens Modal).
  - Profile block (User + CustomerProfile fields).
  - Stats block: total, completed, no-show, last visit.
  - Appointments `DataTable` (paginated server-side).
  - Edit Modal: form with all editable fields + save (Inertia `useForm`).
- RTL, foundation, Arabic. lucide icons: `Contact2` (nav), `Pencil`/`Eye` (actions).

## 7. Tests

- `tests/Feature/Customers/CustomerCrudTest.php`: manager list customers (search by name/phone/email returns expected); customer-role only (staff not in list); show 404 for staff id; update changes User + CustomerProfile in one transaction; email/phone uniqueness across other customers; toggle-active flips; receptionist can read but not mutate; customer cannot reach `/admin/customers` (403).
- `tests/Feature/Auth/InactiveLoginTest.php`: a deactivated customer cannot log in (uniform `auth.failed`).
- `tests/Feature/RouteNamesTest.php`: add the 4 new canonical names.
- Vitest: not required for this CRUD (no novel JS component beyond DataTable/Modal patterns already covered).

## 8. Docs (R6/Q.9)

- `docs/DOMAIN-MODEL.md`: add `users.is_active` column note + Customer-admin surface; clarify that admin manages the `notes` text field (clinic-staff patient notes — distinct from medical records).
- `docs/ARCHITECTURE.md`: add the 4 customer routes to the admin route table; bump last-updated.
- `CHANGELOG.md`: one polish entry.

## 9. Out of scope

- Password reset by admin (separate flow, later).
- Soft-delete with `deleted_at` (the `is_active` flag is sufficient for this need).
- Audit log of who changed what (P3 territory under ADR-002).
- Bulk operations / CSV export.
- Customer's own self-profile page in the portal.
