# Changelog

All notable changes to jannahclinic are documented here. Per Definition of Done Q.9,
every PR adds an entry. Format: Keep a Changelog; project uses phase tags (P0–P5).

## [P1] Services & Booking — (in progress)
- **T8 — PricingService (bcmath) + transactional BookingService + BookingData + booking exceptions:** `PricingService::quote()` returns `{base,surcharge,total}` as bcmath 2-dp strings (money-gate: no float on any monetary column); `BookingService::book(BookingData)` runs in `DB::transaction` with `lockForUpdate` doctor row + `AvailabilityService` re-check (double-booking safe); `BookingData` typed DTO; `SlotUnavailableException` + `InvalidBookingException` (Arabic defaults); 7 new tests (3 unit + 4 feature).
- **T7 polish — tz default + redundant-query removal + contract tests:** app timezone now defaults to `Asia/Hebron` (env `APP_TIMEZONE`); redundant weekday-schedule DB query eliminated (`intervalFor` removed, `DoctorSchedule` row fetched once in `slotsFor`); 3 new slot-engine contract tests (TG1: exact-fill boundary, TG2: interval<duration overlapping starts, TG3: morning+evening union).
- **T7 — AvailabilityService slot engine + availability endpoint:** `AvailabilityService::slotsFor` (pure domain, unit-tested); respects closed/custom_hours exceptions, excludes past slots and non-terminal conflicts; `GET /admin/availability` + `GET /portal/availability` (JSON `{start,end,label}[]`); `DoctorProfile::appointments()` HasMany added; 2 new canonical route names (`admin.availability`, `portal.availability`); 9 new tests (6 unit + 3 feature).
- Settings store + SettingService (config-driven, R12); config/clinic.php.
- Service catalog (categories + services): admin CRUD + portal browse.
- Doctors + doctor_service pivot (+price_override); admin CRUD; AuthService::createStaff.
- Doctor weekly schedules + date exceptions (admin, manager-only mutations);
  time fields use `datetime:H:i` cast (Carbon at runtime, `'HH:MM'` in JSON);
  enabled-window/custom_hours validation (`required_if` + `after`); success flash.
- Coverage areas CRUD (`home_service_coverage_areas` table) + config-driven home-surcharge admin setting (`home_surcharge_pct` via SettingService); manager-only mutations; destroy guarded with QueryException catch for T6 FK; 6 new route names (`admin.coverage.*`, `admin.settings.*`).
- Appointment + ServiceAddress entities (data layer only): `AppointmentStatus` (7-state lifecycle, terminal states, allowed transitions) + `DeliveryMode` enums; `appointments` migration with 4 Postgres CHECK constraints (status/mode/price/time) + self-referential `rescheduled_from_id`; `service_addresses` migration (1:1 with appointments, FK → coverage areas restrictOnDelete); `Appointment` and `ServiceAddress` models with enum casts. Booking write/transition logic arrives T8/T10.
- Real shell navigation (P1-NAV): `AdminShell` links the 6 admin routes, `ClientShell` the 2 portal routes, both with `aria-current` active state; resolves the P0 placeholder-nav debt (persistent layout still deferred).
- Fix: foundation `Modal` now uses `DialogScrollContent` so tall forms scroll and the footer/submit stays reachable (affected all admin CRUD modals).

## [P0] Foundation — 2026-05-19
- Adopted methodology-kit v1.0.1 (governance, Golden Rules, Definition of Done, ADR-001/002).
- Laravel 13 + Inertia + Vue 3 + Tailwind v4 + shadcn-vue + PostgreSQL scaffold.
- building.app-derived design system (@theme clinic tokens, Cairo, RTL-first) + foundation component layer.
- Email-or-phone authentication, 4 roles, server-side admin/portal surface isolation.
- Two empty surfaces (AdminShell / ClientShell), unified Inertia error pages, customer avatar upload.
- Quality gate (Pint, Larastan L5, Pest, Vitest, RTL grep) wired into CI + PR template.
