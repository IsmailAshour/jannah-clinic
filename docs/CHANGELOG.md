# Changelog

All notable changes to jannahclinic are documented here.

---

## [P1] Services & Booking — (in progress)

- **T9 (2026-05-19):** Shared 3-step `BookingWizard.vue` component; `Portal\BookingController` (customer self-booking); `Admin\BookingController` (on-behalf + quick-create customer via `AuthService::registerCustomer`); 4 new booking routes (`portal.booking.create/store`, `admin.booking.create/store`); Vitest wizard spec; feature tests for portal + admin booking flows.
- **T8 (2026-05-19):** `PricingService` (bcmath quote with home surcharge), transactional `BookingService::book(BookingData)` with double-booking guard (`lockForUpdate`), `BookingData` DTO, `SlotUnavailableException`, `InvalidBookingException`.
- **T7 (2026-05-19):** `AvailabilityService` (slot engine), shared `AvailabilityController`, portal + admin availability endpoints returning `{start, end, label}[]` ISO8601+offset.
- **T5/T6 (2026-05-19):** Coverage area CRUD + clinic settings (home surcharge); `appointments`, `service_addresses`, `home_service_coverage_areas` tables; `Appointment`, `ServiceAddress` models; `AppointmentStatus`, `DeliveryMode` enums.
- **T3/T4 (2026-05-19):** Doctor CRUD + doctor schedule management; `DoctorProfile`, `DoctorSchedule`, `ScheduleException` models; `doctor_service` pivot with `price_override`.
- **T2 (2026-05-19):** Service catalog CRUD (`ServiceCategory`, `Service`); portal service browse page.
- **T1 (2026-05-19):** Foundation layout components (`AdminShell`, `ClientShell`); design tokens; RTL logical properties CI gate.

## [P0] Foundation — COMPLETE

- Auth (email or phone), roles (`manager`, `doctor`, `receptionist`, `customer`), `AuthService`, two isolated surfaces (admin/portal), design system (Tailwind v4 `@theme`, Cairo font, shadcn-vue).
