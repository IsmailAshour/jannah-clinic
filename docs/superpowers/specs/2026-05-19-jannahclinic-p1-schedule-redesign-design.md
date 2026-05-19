# jannahclinic — P1 Schedule Redesign (fixed 30-min slot grid)

> **Status:** APPROVED — P1 amendment (folded into P1, before the `p1-services-booking` tag)
> **Date:** 2026-05-19
> **Supersedes:** the T4 weekly-window model (`doctor_schedules` morning/evening start-end + `slot_interval_minutes`) and the T4 `datetime:H:i` Carbon-cast interface contract.

## 1. Motivation

The doctor schedule should be a clear button grid, and the clinic operates on a
uniform half-hour cadence. Per-day "duration minutes" per doctor is unnecessary:
slots are a fixed 30-minute grid; a service is either 30 or 60 minutes and
consumes 1 or 2 consecutive slots.

## 2. Decisions (locked with the user)

1. **Fold into P1** before tagging `p1-services-booking` (amendment, not a new phase).
2. **Normalized slot rows** for storage (`doctor_schedule_slots`).
3. **Exceptions = closed + per-date slot override** (`closed` whole day off, or `custom` with an explicit per-date slot set using the same grid).
4. **Enforce service duration ∈ {30, 60}**.

## 3. Config (R12) — `config/clinic.php`

| Key | Value | Meaning |
|-----|-------|---------|
| `slot_minutes` | `30` | grid granularity |
| `day_start` | `'08:00'` | first slot start |
| `day_end` | `'22:00'` | hard end (a slot/block must finish by this) |
| `band_split` | `'15:00'` | UI-only: morning = `day_start`–`band_split`, evening = `band_split`–`day_end`. Time is **contiguous**; the split is presentational grouping only. |

`booking_lead_minutes` is retained (past-slot guard). `slot_interval_minutes` is
**removed** entirely (it was a `doctor_schedules` column, not a config key).

Derived grid (pure, from config): ordered `'HH:MM'` starts
`08:00, 08:30, … , 21:30` (28 slots). Last 30-min start `21:30` (ends 22:00);
last 60-min start `21:00` (ends 22:00).

## 4. Data model

**Dropped:** `doctor_schedules` table + `App\Models\DoctorSchedule`.

**New `doctor_schedule_slots`:** `id`, `doctor_profile_id` (FK→`doctor_profiles`,
`cascadeOnDelete`), `weekday` (int 0–6, Sunday=0 … Saturday=6 — unchanged
convention), `slot_start` (`string`, 5 chars `'HH:MM'`), `timestamps`.
`unique(doctor_profile_id, weekday, slot_start)`. pgsql CHECK
`weekday BETWEEN 0 AND 6`. One row = one enabled half-hour for that weekday.

**Restructured `schedule_exceptions`:** retains `id, doctor_profile_id, date,
note, timestamps`. `type` allowed values change from `closed|custom_hours` to
`closed|custom`. Columns `custom_start`, `custom_end` are **dropped**. pgsql
CHECK updated to `type IN ('closed','custom')`. `unique(doctor_profile_id, date)`
retained.

**New `schedule_exception_slots`:** `id`, `schedule_exception_id`
(FK→`schedule_exceptions`, `cascadeOnDelete`), `slot_start` (`'HH:MM'`),
`timestamps`. `unique(schedule_exception_id, slot_start)`. Only populated when
the parent exception `type = custom`.

Migration: a single new migration drops `doctor_schedules`, drops the two
exception columns + old type CHECK, adds the new type CHECK, and creates
`doctor_schedule_slots` + `schedule_exception_slots`. **No data migration** —
QA-only data; the dev seeder seeds no schedule data; any QA-entered schedules
are re-entered via the new grid UI.

**Models:** delete `DoctorSchedule`. Create `DoctorScheduleSlot`
(`#[Fillable(['doctor_profile_id','weekday','slot_start'])]`, `weekday`→int cast,
`doctor()` BelongsTo). Rewrite `ScheduleException` (drop custom_start/end casts;
add `slots(): HasMany ScheduleExceptionSlot`). Create `ScheduleExceptionSlot`
(`#[Fillable(['schedule_exception_id','slot_start'])]`, `exception()` BelongsTo).
`DoctorProfile`: replace `schedules()` with `scheduleSlots(): HasMany
DoctorScheduleSlot`; keep `scheduleExceptions()`; keep `appointments()`.

## 5. `SlotGrid` (pure domain helper)

`App\Domain\Booking\Slots\SlotGrid` — pure, config-driven, unit-tested:
- `all(): string[]` — ordered grid (`'08:00'…'21:30'`).
- `morning(): string[]` / `evening(): string[]` — split at `band_split` (UI grouping).
- `isValid(string $hhmm): bool`.
- `blockFrom(string $start, int $count): ?string[]` — the `$count` consecutive
  grid starts beginning at `$start`, or `null` if the block runs past `day_end`
  or `$start` is not on the grid.

## 6. Service

`Service.duration_minutes` validated `in:30,60` in `ServiceController`
store/update; pgsql CHECK `duration_minutes IN (30,60)` (new migration alters
`services`, replacing the prior `duration_minutes > 0` CHECK). `Service`
exposes `slotCount(): int` = `intdiv(duration_minutes, 30)` (1 or 2).

## 7. AvailabilityService (engine rewrite — signature & return shape UNCHANGED)

`slotsFor(DoctorProfile $doctor, Service $service, CarbonImmutable $date):
array{0:array{start:CarbonImmutable,end:CarbonImmutable}}` — same shape, so
`BookingService` (T8), the availability endpoint (T7), the wizard (T9), and
`AppointmentTransitionService::reschedule` (T10) need **no changes**.

Algorithm:
1. `weekday = (int) $date->dayOfWeek`.
2. **Enabled set** for the date:
   - exception for (doctor, date): `closed` → return `[]`; `custom` → enabled =
     that exception's `schedule_exception_slots` starts.
   - else → enabled = `doctor_schedule_slots` starts for `weekday`.
3. `need = $service->slotCount()` (1 or 2).
4. `now = CarbonImmutable::now()->addMinutes(config('clinic.booking_lead_minutes',0))`.
5. `taken` = doctor's `[Requested,Confirmed]` appointments on that date →
   expand each into the set of 30-min grid starts it occupies
   (`slotCount(appt.service or by duration)` consecutive starts from its
   `start_at`). (Compute occupied starts from `start_at` + the appointment's
   own duration: `(end_at - start_at)/30` consecutive grid starts.)
6. For each grid start `s`: let `block = SlotGrid::blockFrom(s, need)`. Emit a
   slot iff `block !== null` AND every slot in `block` is in **enabled** AND no
   slot in `block` is in **taken** AND the block start
   `>= now` (date+`s`, app tz `Asia/Hebron`). Slot `start` = date+`s`,
   `end` = start + `service->duration_minutes`.
7. Return ordered slots.

(Booking flow unchanged: `BookingService` still re-checks via `slotsFor` under
the doctor row lock; a 60-min booking persists `start_at=s`,
`end_at=s+60`, and step 5 will correctly mark both half-hours occupied for
subsequent availability.)

## 8. Schedule management (controller + UI — "جدول أفضل")

`DoctorScheduleController` (route names UNCHANGED: `admin.doctors.schedule`,
`admin.doctors.schedule.save`, `admin.doctors.exceptions.add`,
`admin.doctors.exceptions.delete`; payloads change):
- `editSchedule`: Inertia render with the doctor, the config grid
  (morning/evening arrays), the doctor's enabled slot-starts grouped by weekday,
  and the exceptions (each with its custom slots).
- `saveSchedule`: validate `slots` as a map weekday→`string[]` where every value
  is a valid grid start (`SlotGrid::isValid`) and weekday ∈ 0–6; replace the
  doctor's `doctor_schedule_slots` to exactly that set (delete-missing /
  insert-new per weekday) in a transaction. Manager-only (unchanged authz).
- `addException`: validate `date` (date), `type` (`in:closed,custom`),
  `slots` (`required_if:type,custom`, array of valid grid starts), `note`
  (nullable). `updateOrCreate` the exception by (doctor,date); when `custom`
  replace its `schedule_exception_slots`; when `closed` clear them.
- `deleteException`: unchanged (ownership `abort_unless` 404).

`resources/js/Pages/Admin/Doctors/Schedule.vue`: a **toggle-button grid** — 7
weekday rows (الأحد…السبت); within each row two labelled groups (صباحية /
مسائية) of half-hour buttons; clicking toggles enable/disable; "تحديد الكل/مسح"
per row optional. Exceptions panel: a date input + `مغلق`/`مخصّص`; when
`مخصّص`, the same button grid for that single date; list existing exceptions
with delete. Foundation components, RTL logical properties only, Arabic.

## 9. Test impact

- New: `SlotGridTest` (unit), `AvailabilityServiceTest` (rewritten for the grid
  engine: enabled-set, closed exception, custom-slot exception, 30-min=1 slot,
  60-min needs 2 consecutive enabled+free, taken-block exclusion incl. a 60-min
  appointment blocking both halves, past exclusion, last-60-min-start boundary
  21:00, gap rejects a 60-min spanning a disabled middle slot),
  `ScheduleCrudTest` (rewritten for slot upsert + closed/custom exceptions +
  invalid grid value rejected + non-manager forbidden), Service `in:30,60`
  validation test.
- A shared Pest test helper `enableDoctorSlots($doctor, int $weekday,
  array $starts)` (and an exception-slots helper) replaces every
  `DoctorSchedule::create([... morning_start ...])` fixture across the suite
  (`AvailabilityServiceTest`, `AvailabilityEndpointTest`, `BookingServiceTest`,
  `PortalBookingTest`, `AdminOnBehalfBookingTest`, `TransitionServiceTest`,
  `AdminLifecycleTest`, `PortalAppointmentTest`, and any others). Mechanical but
  broad — every dependent fixture must seed `doctor_schedule_slots` for the
  needed weekday/times so the existing booking/lifecycle assertions still hold.
- `RouteNamesTest` unchanged (no route-name changes).

## 10. Docs (R6/Q.9)

`DOMAIN-MODEL.md`: replace `DoctorSchedule` with `doctor_schedule_slots`;
restructured `ScheduleException` + `schedule_exception_slots`. `ARCHITECTURE.md`:
slot-grid model + `SlotGrid` + config + AvailabilityService engine rewrite;
**remove** the now-moot "Schedule time-field contract (datetime:H:i)" debt item
(slots are plain `'HH:MM'` strings). `CHANGELOG.md`: re-opened `[P1]` heading,
redesign bullets, re-closed at true P1 completion (supersedes T11's earlier
close).

## 11. Sequencing

Amendment tasks **T12 → T15** executed via subagent-driven-development
(implement + spec review + quality review per task). After T15: final
whole-P1 code review → tag `p1-services-booking` → finishing-a-development-branch
→ then the post-P1 UI/UX polish pass (task #52).

## 12. Out of scope (unchanged)

No P2+ (payments/records/membership/loyalty/notifications). No changes to
booking channels, pricing, lifecycle state machine, or policies beyond the
slot-source swap inside `AvailabilityService`.
