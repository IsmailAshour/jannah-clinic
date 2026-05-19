# Changelog

All notable changes to jannahclinic are documented here. Per Definition of Done Q.9,
every PR adds an entry. Format: Keep a Changelog; project uses phase tags (P0–P5).

## [P1] Services & Booking — (in progress)
- Settings store + SettingService (config-driven, R12); config/clinic.php.
- Service catalog (categories + services): admin CRUD + portal browse.
- Doctors + doctor_service pivot (+price_override); admin CRUD; AuthService::createStaff.
- Doctor weekly schedules + date exceptions (admin, manager-only mutations);
  time fields use `datetime:H:i` cast (Carbon at runtime, `'HH:MM'` in JSON);
  enabled-window/custom_hours validation (`required_if` + `after`); success flash.
- Coverage areas CRUD (`home_service_coverage_areas` table) + config-driven home-surcharge admin setting (`home_surcharge_pct` via SettingService); manager-only mutations; destroy guarded with QueryException catch for T6 FK; 6 new route names (`admin.coverage.*`, `admin.settings.*`).
- Appointment + ServiceAddress entities (data layer only): `AppointmentStatus` (7-state lifecycle, terminal states, allowed transitions) + `DeliveryMode` enums; `appointments` migration with 4 Postgres CHECK constraints (status/mode/price/time) + self-referential `rescheduled_from_id`; `service_addresses` migration (1:1 with appointments, FK → coverage areas restrictOnDelete); `Appointment` and `ServiceAddress` models with enum casts. Booking write/transition logic arrives T8/T10.

## [P0] Foundation — 2026-05-19
- Adopted methodology-kit v1.0.1 (governance, Golden Rules, Definition of Done, ADR-001/002).
- Laravel 13 + Inertia + Vue 3 + Tailwind v4 + shadcn-vue + PostgreSQL scaffold.
- building.app-derived design system (@theme clinic tokens, Cairo, RTL-first) + foundation component layer.
- Email-or-phone authentication, 4 roles, server-side admin/portal surface isolation.
- Two empty surfaces (AdminShell / ClientShell), unified Inertia error pages, customer avatar upload.
- Quality gate (Pint, Larastan L5, Pest, Vitest, RTL grep) wired into CI + PR template.
