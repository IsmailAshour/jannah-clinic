# Changelog

All notable changes to jannahclinic are documented here. Per Definition of Done Q.9,
every PR adds an entry. Format: Keep a Changelog; project uses phase tags (P0–P5).

## [P5a] Notification System — 2026-05-20

**P5a complete:** event-driven in-app notifications fan out from the existing
domain services (`PaymentService`, `BookingService`, `AppointmentTransitionService`,
`MedicalEntryService`, `PrescriptionService`) inside their `DB::transaction` blocks
— so a rolled-back domain change discards its notifications too. Bell badge in both
shells (Inertia-shared `notifications.unread_count`) + dedicated notification center
at `/admin/notifications` and `/portal/notifications` with category chips, unread-only
filter, 20/page pagination, and click-to-read+redirect. **No SMS/email/push in scope**
— in-app only; off-platform delivery is deferred behind a future ADR (PHI in transit).

- **Domain model:** Laravel's standard `notifications` table (UUID, morph, JSON `data`)
  + two composite indexes (`unread_idx`, `feed_idx`). `App\Enums\NotificationCategory`
  drives both server-side filtering (`where('data->category', $cat)` — portable) and
  the UI chip set. Payload schema documented in spec §2.3; PHI explicitly excluded.
- **`NotificationService`** with one method per spec §3 event (bookingRequested,
  appointmentConfirmed/Rejected/CancelledByStaff/Completed/RescheduledForCustomer,
  paymentReceiptUploaded/Approved/Rejected/Refunded, medicalEntryCreated, prescriptionAdded,
  markAsRead, markAllAsRead). Mirrors the `AuditLogger` pattern — explicit, transactional,
  called inline. `markRefundPending` deliberately does NOT notify (internal staging state).
- **Routes (6 new — locked by `RouteNamesTest`):** `admin.notifications.{index,read,mark-all-read}`
  + `portal.notifications.{index,read,mark-all-read}`. Mark-read POST verifies
  `notifiable_id === auth()->id()` then redirects to `data.action_url`.
- **Inertia share + Bell:** `HandleInertiaRequests::share` adds `notifications.unread_count`
  (closure; guests null). New foundation component `<NotificationBell>` reads the share,
  shows a numeric badge capped at `99+`, navigates to the notification center.
  Mounted in `AdminShell.vue` (header) and `ClientShell.vue` (header). Vitest spec
  covers 4 visibility scenarios.
- **Notification center pages:** admin + portal each render a card-list with unread dot,
  title, body (1-line truncate), relative time chip, category chips, unread toggle, and
  the standard Inertia paginator. Controllers expose the feed as `props.feed` so the
  `notifications` Inertia share key isn't clobbered.
- **Wiring:** `PaymentService` now wraps `verify` and `markRefunded` in
  `DB::transaction` (were not transactional before) and emits at every status flip
  (except `markRefundPending`). `AppointmentTransitionService::transition` now wraps
  the body in a transaction (was direct save) and emits per terminal state.
  `BookingService::book` notifies all active managers+receptionists on Requested.
  `MedicalEntryService::create` and `PrescriptionService::syncForEntry` (create
  branch only) notify the customer with the medical category.
- **Tests:** +28 Pest (NotificationServiceTest 6, NotificationCenterTest 11,
  BellShareTest 3, EventToNotificationTest 10) + 4 Vitest. Cross-user 403 matrix
  covered. Inside-transaction rollback covered. PHI-omission assertion covered.
- **Docs:** ADR-003 unaffected (no PHI in payload). New section in
  `docs/ARCHITECTURE.md` summarizes the system + deferred items.
- **Tag:** `p5a-notifications`.

## [R-DataTable Migration] — 2026-05-20

**Followed P3.** Migrated all 7 legacy admin list pages from the slim hand-rolled `<DataTable>` to the new `AdminDataTable` family — fulfilling the binding rule that every admin list surface uses shadcn-vue Data Table with row actions, pagination, sorting, filtering, column visibility, and row selection. Legacy `<DataTable>` and its Vitest spec deleted; `foundation/index.js` no longer exports it.

- **Migrated pages:** `Admin/Catalog/Services.vue`, `Admin/Catalog/Categories.vue`, `Admin/Coverage/Index.vue`, `Admin/Doctors/Index.vue` (array data, client-side sort/filter), `Admin/Customers/Index.vue`, `Admin/Customers/Show.vue` (appointments sub-list), `Admin/Payments/Index.vue`, `Admin/Appointments/Index.vue` (paginated server-side; server-side filter UI preserved above the table; pagination wired via `server-meta` + `on-page-change` to Inertia `router.get`).
- **Row-action UX shift:** inline button strips replaced by `<AdminDataTableRowActions>` dropdown menus (`<DropdownMenuItem>` children). For `Appointments/Index.vue`, status-dependent actions (تأكيد / رفض / إكمال / لم يحضر / إلغاء بسبب…) collapse into the per-row dropdown.
- **Exempt:** `Admin/Doctors/Schedule.vue` — it is a weekly slot-grid (toggle buttons per weekday × time), not a list page.
- **DataTable.vue + DataTable.spec.js deleted** — no remaining consumers; `foundation/index.js` export removed.
- 22 Vitest / 238 Pest still green.

## [P3] Medical Records (encrypted + audited) — 2026-05-20

**P3 complete:** doctors can write per-appointment medical records (diagnosis + internal notes + structured prescriptions) and chronic-conditions/allergies on the customer profile; every PHI field is encrypted at rest via Laravel's `encrypted` cast; every CREATE/UPDATE/VIEW of those fields appends to an immutable `medical_audit_logs` table. Customers see only `visible_summary` + prescriptions on the portal — `staff_notes` is never serialized to the customer response. **ADR-003 supersedes ADR-002**, lifting the production block on real patient data. New shadcn-vue `AdminDataTable` family delivers row actions / pagination / sorting / filtering / column visibility / row selection for new admin list surfaces; 8 legacy admin pages are recorded as `R-DataTable` migration debt in ARCHITECTURE.md.

- **T1 — schema:** `medical_entries` (1:1 with appointments, unique FK, cascadeOnDelete), `prescriptions` (N:1 with medical_entries), `medical_audit_logs` (append-only, 8-value `action` CHECK on pgsql, denormalized `customer_id` FK + 3 indexes for fast per-patient queries) + `customer_profiles` columns `chronic_conditions`/`allergies`.
- **T2 — models + encryption:** `MedicalEntry`, `Prescription`, `MedicalAuditLog` with `#[Fillable]` attribute and Laravel `encrypted` casts on every PHI field; `CustomerProfile.notes` newly classified PHI and cast as `encrypted`. `MedicalAuditLog::save()` throws `LogicException` on `exists` and `delete()` throws unconditionally — verified by unit tests.
- **T3 — `AuditLogger` service:** explicit, transactional, captures actor user / IP / UA (truncated 255) / changed-field names (never values) / patient FK.
- **T4 — `medical:encrypt-customer-notes` artisan command:** idempotent backfill that detects already-encrypted ciphertext and skips it; safe to re-run.
- **T5 — `MedicalEntryService`:** create + update inside `DB::transaction`; audit row written in the same transaction (failure rolls back the entity change); update audits only dirty fields.
- **T6 — `PrescriptionService::syncForEntry`:** diffs the desired list, creates/updates/deletes rows individually, audits each transition.
- **T7 — `AdminDataTable` foundation:** new `AdminDataTable`, `AdminDataTablePagination`, `AdminDataTableColumnHeader`, `AdminDataTableViewOptions`, `AdminDataTableRowActions` under `resources/js/Components/foundation/` built on `@tanstack/vue-table` v8 with full feature set + Vitest smoke tests. RTL-aware navigation icons. The legacy `<DataTable>` is preserved for backward compatibility; existing admin pages migrate to `<AdminDataTable>` in the follow-on `R-DataTable Migration` phase.
- **T8 — `MedicalEntryPolicy`:** customer sees own only; receptionist explicitly blocked at the policy and route layers; doctor can create only on appointments where `doctor_profile_id` matches their `DoctorProfile` AND status is `completed`; only the entry's `author` can update it. Manager can view but not write. Policy registered via `Gate::policy()` in `AppServiceProvider`.
- **T9 — Admin\MedicalEntryController + 4 routes:** `appointments.medical-entry.store` (POST, doctor only), `appointments.medical-entry.create` (GET, doctor only — resolves-or-creates draft entry then redirects to edit), `medical-entries.edit` (GET, view policy gates), `medical-entries.update` (PUT, doctor only); 8 feature tests cover assigned-doctor happy path, unassigned-doctor 403, receptionist 403, customer 403, validation 422, completed-only constraint, author-only update.
- **T10 — Admin\CustomerController::updateMedicalProfile + route:** `customers.profile.medical.update` (PUT, manager+doctor); writes chronic_conditions/allergies + audit; receptionist blocked by route middleware. Also fixed legacy `CustomerController::store` bypass of encryption (was using `query()->update()` instead of `updateOrCreate()`).
- **T12 — Admin Customers Show.vue:** medical-profile form (manager+doctor edit, receptionist hidden), `AdminDataTable` entries list (sort/filter/visibility/row-action "فتح السجل"), and a per-appointment-row "إضافة/فتح" CTA on the existing appointments list for completed appointments. Skipped: separate `Admin/Appointments/Show.vue` (file did not exist in the project; spec adapted to surface entry CTAs on the customer page).
- **T13 — Admin/MedicalEntries/Edit.vue:** Doctor write form with dynamic prescriptions list (add/remove rows) + visible_summary + staff_notes sections.
- **T14 — Portal\MedicalRecordController + 2 routes + tests:** `portal.medical-record.index`, `portal.medical-record.show`; 404 (not 403) on other-customer access to avoid existence-leak; **explicit absence test** asserts `staff_notes` never appears in the Inertia response payload.
- **T15 — Customer portal pages + nav:** `Portal/MedicalRecord/Index.vue` (chronic+allergies card + entries list with prescriptions) and `Show.vue` (single-entry detail); `ClientShell` bottom-nav extended to 5 tabs to include "سجلي الطبي".
- **T16 — PHI-at-rest verification tests:** raw `DB::table()->value()` reads of every encrypted column assert the persisted bytes do NOT contain the plaintext marker — `visible_summary`, `staff_notes`, `prescriptions.medication_name`, `prescriptions.dosage`, `customer_profiles.chronic_conditions`, `customer_profiles.allergies`, `customer_profiles.notes`. 4 tests, 8 assertions.
- **ADR-003 (ACCEPTED):** supersedes ADR-002 (now `SUPERSEDED`); canonical registry rows + `docs/adr/README.md` index updated.
- 7 new canonical route names locked by `RouteNamesTest` (5 admin + 2 portal); no `admin.admin.*` / `portal.portal.*` doubled prefixes.

## [P2] Payments — 2026-05-20

**P2 complete:** bank-transfer receipt payment flow attached to every appointment — without changing the existing 7-state AppointmentStatus machine. Hybrid lifecycle (decoupled), receipts uploaded from the appointment page, manager verify/reject with reason, auto-mark-refund on Cancelled/Rejected, manual refund workflow; **211 tests green**, scratch-Postgres `migrate:fresh` builds the full schema clean.

- **T1 — schema + models + booking integration:** `payments` (1:1 with appointments, cascadeOnDelete unique FK, 6-state status CHECK, amount>=0 CHECK on pgsql) + `payment_receipts` (N:1 with payments, status uploaded|rejected CHECK, size>0); idempotent backfill inserts one Payment.pending per existing appointment; `PaymentStatus` enum (6 cases); `BookingService` creates Payment.pending in the existing booking transaction.
- **T2 — `PaymentService` (TDD):** uploadReceipt (mimes JPG/PNG/PDF, ≤5MB, private `local` disk under `receipts/{payment_id}/`); verify/reject(reason)/markRefundPending/markRefunded(reference); `InvalidPaymentTransitionException` extends \RuntimeException with Arabic default; reject mirrors reason to the latest `PaymentReceipt` row with rejected_by/at.
- **T3 — `AppointmentObserver` (auto refund):** registered in AppServiceProvider; when an Appointment transitions to `Cancelled` or `Rejected` AND its Payment is `paid`, auto-marks the Payment as `refund_pending` inside the transition's transaction. Completed/NoShow/Rescheduled deliberately do NOT auto-trigger.
- **T4 — Portal payment page + receipt streaming:** `PaymentPolicy::view` (owner-or-staff); `Portal\PaymentController` (show + upload); `portal.appointments.payment` + `portal.appointments.payment.upload` routes; `Portal/Payments/Show.vue` (status banner + bank-info card with IBAN copy + upload gated by status + own-uploaded preview when submitted).
- **T5 — Admin payments + 7 routes + receipt preview UI:** `Admin\PaymentController` (index with q+status filter, show with eager loads, verify/reject/markRefundPending/markRefunded, receiptFile streams from private disk with ownership 404 guard); 7 admin route names locked (reads in outer staff group, mutations under role:manager); `Admin/Payments/Index.vue` (DataTable + filters + pagination); `Admin/Payments/Show.vue` (inline image/PDF receipt preview max-h-70vh + side summary card + action bar gated by status/role + receipt-history table).
- **T6 — Sidebar entry + submitted-count badge:** `HandleInertiaRequests` shares `adminCounts.submitted_payments` lazily (closure null for non-staff so the COUNT query never runs for guests/customers); `AdminShell` adds Receipt-icon leaf inside العيادة group with numeric badge when count > 0; nav data preserved verbatim (incl. double-space `حجز موعد  لعميل` label).
- **T7 — R12 bank settings (4 keys):** `config/clinic.php` adds `bank_name`/`bank_account_holder`/`bank_iban`/`bank_account_number` with env() empty defaults; `ClinicSettingController::saveBankInfo` persists via `SettingService` (DB-override → config fallback pattern); `admin.settings.bank` PUT route (manager only) locked; `Admin/Settings/Index.vue` gains a dedicated "بيانات الحساب البنكي" FormSection (separate useForm from the surcharge form).
- 10 new canonical route names locked by `RouteNamesTest` (2 portal + 7 admin payments + 1 admin settings bank); no `admin.admin.*`/`portal.portal.*` doubled prefixes.

## [P1] Services & Booking — 2026-05-19

**P1 complete:** full end-to-end booking — service catalog, doctor profiles + fixed 30-min slot-grid schedules, availability engine, bcmath pricing with home surcharge, transactional BookingService (double-booking guard), shared BookingWizard (portal self + admin on-behalf + quick-create), 7-state appointment lifecycle with server-side enforcement, admin and customer appointment management; **151 tests green**, 0 regressions; scratch-Postgres `migrate:fresh` builds the full schema clean.

- **Schedule redesign (P1 amendment, T12–T15) — fixed 30-min slot grid:** retired the legacy weekly-window model (`doctor_schedules` morning/evening start-end + `slot_interval_minutes`, `schedule_exceptions.custom_start/custom_end`, `DoctorSchedule` model). New: `config/clinic.php` grid keys + `SlotGrid` (canonical `'HH:MM'`, 28 half-hours 08:00–21:30); `doctor_schedule_slots` + `schedule_exception_slots` tables; `ScheduleException.type` now `closed|custom` (pgsql CHECK swapped); `Service.duration_minutes` constrained `{30,60}` (1–2 slots); `AvailabilityService` rewritten as a grid engine (signature/return shape unchanged → T7–T10 consumers untouched); `DoctorScheduleController` + `Schedule.vue` rebuilt as a weekday toggle-button grid with closed/custom-date exceptions; all suite fixtures migrated to `doctor_schedule_slots`; the prior `datetime:H:i` Carbon-cast debt is resolved. Spec: `docs/superpowers/specs/2026-05-19-jannahclinic-p1-schedule-redesign-design.md`.

- **T10 hardening — Block manual `rescheduled` status + lifecycle tests + doc debt:** `Admin\AppointmentController::transition` now rejects `status=rescheduled` (orphaned-terminal guard); `AppointmentTransitionService::transition` documented with no-lock MVP comment; +4 new tests (portal cancel-terminal friendly error, admin skip `requested→completed`, admin direct-rescheduled block, home-delivery reschedule carries ServiceAddress); ARCHITECTURE.md debt note on Vue `isTerminal` duplication.
- **T10 — Appointment lifecycle transitions, admin management, customer my-appointments:** `AppointmentTransitionService` (transition + reschedule in DB::transaction); `InvalidTransitionException`; `AppointmentPolicy` (Gate::policy, staff full-manage, customer own-only cancel/reschedule); `Admin\AppointmentController` (index with filters, transition POST); `Portal\AppointmentController` (own-only index, cancel, reschedule); 5 new routes (`admin.appointments.index/transition`, `portal.appointments.index/cancel/reschedule`); Vue pages `Admin/Appointments/Index.vue` + `Portal/Appointments/Index.vue` (RTL, foundation components, Arabic status labels, slot-picker mirrors BookingWizard); AdminShell nav adds المواعيد + الحجز leaves; ClientShell replaces 2 disabled tabs with real الحجز + مواعيدي; T9 success redirects repointed to `portal.appointments.index` / `admin.appointments.index`; 17 new tests (9 unit + 4+4 feature); 0 regressions.
- **T9 — Shared `BookingWizard` + Portal self-booking + Admin on-behalf booking:** 3-step `BookingWizard.vue` (delivery mode → doctor+service → date+slot); `Portal\BookingController` (customer self-serve, `createdByRole=Customer`); `Admin\BookingController` (staff on-behalf + quick-create customer via `AuthService::registerCustomer`); 4 new routes (`portal.booking.create/store`, `admin.booking.create/store`); Inertia error mapping (`withErrors(['booking' => …])`); success redirects repointed T10 to appointments index; Vitest wizard spec + feature tests.
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
- Real shell navigation (P1-NAV): `AdminShell` grouped nav (`الخدمات`: catalog; `العيادة`: doctors/appointments/booking) with per-doctor schedule reachable via `الجدول` action; `ClientShell` 4 real tabs; both with `aria-current` active state; resolves P0 placeholder-nav debt (persistent layout still deferred).
- Fix: Jannah clinic logo replaces default Laravel logo on auth/login screens.
- Fix: `AdminShell` sidebar routes grouped under `الخدمات` (catalog) and `العيادة` (doctors/appointments/booking) headings for improved navigation clarity.
- Fix: foundation `Modal` now uses `DialogScrollContent` so tall forms scroll and the footer/submit stays reachable (affected all admin CRUD modals).
- Fix (polish): proportionate Button padding/sizing — `Button` size variants retuned to a consistent height ladder (xs/sm/default/lg = 28/36/40/44px) with breathing-room horizontal padding and synced icon squares; resolves "button looks disproportionate / needs more padding". Token-driven (Tailwind spacing scale, existing radius tokens); no variant/API/behavior change, no `@theme` change.
- Fix (polish): AdminShell sidebar rebuilt on the vendored shadcn-vue Sidebar (reka-ui based: `SidebarProvider` + `Sidebar collapsible="offcanvas"` + mobile `Sheet` + `SidebarTrigger` + cookie persistence); replaces the hand-rolled drawer and fixes the desktop-RTL off-screen bug where `rtl:translate-x-full` out-specified `lg:translate-x-0`. Brand-mapped `--sidebar*` CSS vars in `resources/css/app.css` preserve the prior look; nav data/labels (incl. `حجز موعد  لعميل`) preserved verbatim. Also: pinned the `AvailabilityService` past-slot test clock with `Carbon::setTestNow()` so it is deterministic regardless of run time.
- Fix (polish): AdminShell sidebar set to `side="right"` (visual right under RTL — reka-ui's `side` is the visual side, doesn't auto-flip under `dir`) so desktop pins to the right edge and mobile Sheet slides in from the right; lucide-vue-next icons added to every leaf nav item (LayoutDashboard / Tags / Package / Users / CalendarDays / CalendarPlus / MapPin / Settings) rendered inline-start via flex+gap-2 (no physical classes); group headings stay text-only.
- Fix (polish): AdminShell sidebar refactored to sidebar-07 icon-collapsible pattern: collapses to icon rail with tooltips; groups become Collapsible parents with icons (Briefcase, Stethoscope); SidebarRail edge toggle added.
- Fix (polish): Customer admin page (Polish-D): list/search/show/update/toggle-active for Customer-role users; `users.is_active` column; inactive users blocked at login (uniform failure).

## [P0] Foundation — 2026-05-19
- Adopted methodology-kit v1.0.1 (governance, Golden Rules, Definition of Done, ADR-001/002).
- Laravel 13 + Inertia + Vue 3 + Tailwind v4 + shadcn-vue + PostgreSQL scaffold.
- building.app-derived design system (@theme clinic tokens, Cairo, RTL-first) + foundation component layer.
- Email-or-phone authentication, 4 roles, server-side admin/portal surface isolation.
- Two empty surfaces (AdminShell / ClientShell), unified Inertia error pages, customer avatar upload.
- Quality gate (Pint, Larastan L5, Pest, Vitest, RTL grep) wired into CI + PR template.
