# jannahclinic — P2: Payments (bank-transfer receipt model) — Design

> **Status:** APPROVED — full new phase after P1 + post-P1 polish.
> **Date:** 2026-05-20
> **Builds on:** P1 (Appointment, AppointmentTransitionService, BookingService, AdminShell sidebar-07, Inertia flash share).
> **Out of P2:** card processing; partial/deposit payments; multi-currency; SMS/email notifications (P5); medical-record audit gate (P3 / ADR-002).

## 1. Motivation & decisions locked with the user

The clinic accepts only **bank-transfer payments** with the customer uploading
a transfer receipt for staff verification. Three architecture-shaping decisions
were locked in the brainstorm:

1. **Hybrid lifecycle:** Payment is **decoupled** from `AppointmentStatus`.
   Staff can confirm appointments without payment (trusted customers, pay-in-clinic
   cases); the UI surfaces a clear "awaiting payment" prompt on both surfaces but
   the appointment state machine is **unchanged**. No new states added to
   `AppointmentStatus`.
2. **Refunds in scope:** Payment has `refund_pending` and `refunded` states.
   Appointment lifecycle transitions to `Cancelled` or `Rejected` while
   `Payment.status === 'paid'` auto-mark the payment as `refund_pending` (via an
   Eloquent listener inside the same DB transaction). Manager records the bank
   transfer-back execution to mark `refunded`.
3. **Receipt uploaded later from the appointment page** (not inside the booking
   wizard): booking creates `Appointment` + `Payment.pending`; customer opens
   the appointment in the portal and uploads from there. The `BookingWizard`
   stays 3 steps — no payment step.

## 2. Domain model

### 2.1 `payments` (1:1 with `appointments`)

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `appointment_id` | bigint unsigned | NOT NULL, FK → `appointments.id` CASCADE DELETE, **UNIQUE** |
| `amount` | decimal(10,2) | NOT NULL |
| `status` | varchar(16) | NOT NULL, default `pending` |
| `verified_at` | timestamp | nullable |
| `verified_by` | bigint unsigned | nullable, FK → `users.id`, restrictOnDelete |
| `refunded_at` | timestamp | nullable |
| `refunded_by` | bigint unsigned | nullable, FK → `users.id`, restrictOnDelete |
| `refund_reference` | varchar(255) | nullable (bank transfer ref string) |
| `rejection_reason` | text | nullable (last rejection's reason mirrored here for quick read) |
| `notes` | text | nullable (manager free text) |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres CHECK constraints** (skipped on SQLite tests; CI Postgres is the gate):

```sql
CONSTRAINT payments_status_check
  CHECK (status IN ('pending','submitted','paid','rejected','refund_pending','refunded'))

CONSTRAINT payments_amount_check
  CHECK (amount >= 0)
```

**`#[Fillable]`:** `appointment_id`, `amount`, `status`, `verified_at`,
`verified_by`, `refunded_at`, `refunded_by`, `refund_reference`,
`rejection_reason`, `notes`.

**Casts:** `amount → decimal:2` (bcmath string), `status → PaymentStatus` (enum),
`verified_at`/`refunded_at` → `datetime`.

**Relations:**

- `appointment(): BelongsTo` → `Appointment`
- `verifier(): BelongsTo` → `User` (FK `verified_by`)
- `refunder(): BelongsTo` → `User` (FK `refunded_by`)
- `receipts(): HasMany` → `PaymentReceipt` (latest first)
- `currentReceipt()`: `HasOne` (latest non-rejected receipt, ordered by id desc)

**Model path:** `app/Models/Payment.php`.

### 2.2 `payment_receipts` (N:1 with `payments`)

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint unsigned | PK |
| `payment_id` | bigint unsigned | NOT NULL, FK → `payments.id` CASCADE DELETE |
| `uploaded_by` | bigint unsigned | NOT NULL, FK → `users.id` (customer or staff who uploaded), restrictOnDelete |
| `file_path` | varchar(255) | NOT NULL (relative to `storage/app/receipts/`) |
| `file_size` | integer | NOT NULL (bytes) |
| `mime_type` | varchar(64) | NOT NULL |
| `status` | varchar(16) | NOT NULL, default `uploaded` |
| `rejection_reason` | text | nullable (this receipt's rejection reason if `status='rejected'`) |
| `rejected_at` | timestamp | nullable |
| `rejected_by` | bigint unsigned | nullable, FK → `users.id`, restrictOnDelete |
| `created_at` / `updated_at` | timestamp | nullable |

**Postgres CHECK:**

```sql
CONSTRAINT payment_receipts_status_check
  CHECK (status IN ('uploaded','rejected'))

CONSTRAINT payment_receipts_size_check
  CHECK (file_size > 0)
```

**Index:** `(payment_id, created_at DESC)` for the "latest" lookups.

**`#[Fillable]`:** `payment_id`, `uploaded_by`, `file_path`, `file_size`,
`mime_type`, `status`, `rejection_reason`, `rejected_at`, `rejected_by`.

**Relations:**

- `payment(): BelongsTo` → `Payment`
- `uploader(): BelongsTo` → `User` (FK `uploaded_by`)
- `rejector(): BelongsTo` → `User` (FK `rejected_by`)

**Model path:** `app/Models/PaymentReceipt.php`.

### 2.3 Bank-account info (R12 — `clinic.settings`)

New `SettingService` keys (defaults in `config/clinic.php`, runtime override via
the admin settings page already in P1):

- `bank_name` (string, e.g., `'بنك القاهرة عمّان'`)
- `bank_account_holder` (string)
- `bank_iban` (string, IBAN — displayed LTR)
- `bank_account_number` (string)

These are shown to customers on the portal payment page as the transfer target.

### 2.4 `PaymentStatus` enum

```php
namespace App\Enums;

enum PaymentStatus: string {
    case Pending        = 'pending';
    case Submitted      = 'submitted';
    case Paid           = 'paid';
    case Rejected       = 'rejected';
    case RefundPending  = 'refund_pending';
    case Refunded       = 'refunded';

    public function isTerminal(): bool { /* Refunded only */ }
    public function isPaid(): bool      { /* Paid */ }
    public function awaitsAction(): bool { /* Submitted (manager) | Pending/Rejected (customer) */ }
}
```

(No `canTransitionTo()` method on the enum — transitions are enforced inside
`PaymentService` for clarity, since some transitions need side effects.)

## 3. State machine

| From | To | Trigger | Side effect |
|---|---|---|---|
| `pending` | `submitted` | Customer uploads a receipt | New `PaymentReceipt` row (status `uploaded`) |
| `submitted` | `paid` | Manager verifies | Set `verified_at`, `verified_by`; clear `rejection_reason` |
| `submitted` | `rejected` | Manager rejects with reason | Mark current `PaymentReceipt.status='rejected'` with reason; mirror reason to `payments.rejection_reason` |
| `rejected` | `submitted` | Customer re-uploads | New `PaymentReceipt` row (status `uploaded`); clear `payments.rejection_reason` |
| `paid` | `refund_pending` | Automatic on `Appointment` → `Cancelled` or `Rejected` (or manual button on a paid payment for special cases like Rescheduled) | None |
| `refund_pending` | `refunded` | Manager records refund execution | Set `refunded_at`, `refunded_by`, `refund_reference` |

**Terminal:** `refunded` only. `paid` is **effectively terminal from the manager's
perspective** — they cannot "unverify"; correction goes through the refund flow.

**Rescheduled handling:** No auto-action on Reschedule (the old appointment becomes
`Rescheduled`, a new appointment is created with its own `Payment.pending`). The
manager decides per-case whether to refund the old payment using the manual
"mark refund pending" button on the old payment's show page. The new appointment's
portal page displays a small notice: «هذا الموعد ناتج عن إعادة جدولة — راجع سياسة
الاسترداد للموعد السابق».

## 4. Backend

### 4.1 `App\Domain\Payment\Services\PaymentService` (R7)

Single domain service. All transitions go through it; controllers stay thin.

```php
public function createForAppointment(Appointment $appt): Payment;

public function uploadReceipt(
    Payment $payment,
    UploadedFile $file,
    User $uploader,
): PaymentReceipt;

public function verify(Payment $payment, User $manager): Payment;

public function reject(
    Payment $payment,
    User $manager,
    string $reason,
): Payment;

public function markRefundPending(
    Payment $payment,
    ?string $manualReason = null,
): Payment;

public function markRefunded(
    Payment $payment,
    User $manager,
    ?string $reference = null,
): Payment;
```

- Each transition validates the source state and throws
  `InvalidPaymentTransitionException` (extends `\RuntimeException`) on illegal
  transition. Controllers catch and translate to `back()->withErrors`.
- `uploadReceipt` validates file: MIME ∈ {`image/jpeg`,`image/png`,`application/pdf`},
  size ≤ `5MB`. Stores at `storage/app/receipts/{payment_id}/{uuid}.{ext}` using
  the `local` (private) disk. **Never the `public` disk.**
- `BookingService::book` calls `createForAppointment` in its existing transaction.

### 4.2 Listener for auto refund-pending

`App\Listeners\MarkPaymentForRefundOnAppointmentTermination`

Listens to Eloquent `Appointment::updated`. If `status` was just changed to
`AppointmentStatus::Cancelled` or `AppointmentStatus::Rejected` AND the
associated `Payment` exists with `status === PaymentStatus::Paid`, calls
`PaymentService::markRefundPending($payment)`. Runs inside the same DB
transaction the transition already opens.

Registered in `app/Providers/AppServiceProvider::boot` via
`Appointment::updated(...)` model event hook, OR via the
`booted()` static method on the `Appointment` model — implementation chooses
whichever matches existing patterns in the codebase.

### 4.3 Routes

All names UNPREFIXED inside their groups; locked by `RouteNamesTest`.

**Portal (customer group):**

| Method | Path | Name |
|---|---|---|
| GET  | `/portal/appointments/{appointment}/payment`        | `portal.appointments.payment`        |
| POST | `/portal/appointments/{appointment}/payment/upload` | `portal.appointments.payment.upload` |

`AppointmentPolicy::view($user, $appointment)` (already exists from P1) guards
both — customer can only access their own appointment's payment.

**Admin (staff/manager groups):**

| Method | Path | Name | Auth |
|---|---|---|---|
| GET  | `/admin/payments`                              | `admin.payments.index`                  | all staff |
| GET  | `/admin/payments/{payment}`                    | `admin.payments.show`                   | all staff |
| GET  | `/admin/payments/{payment}/receipts/{receipt}/file` | `admin.payments.receipt-file`      | all staff |
| POST | `/admin/payments/{payment}/verify`              | `admin.payments.verify`                 | manager only |
| POST | `/admin/payments/{payment}/reject`              | `admin.payments.reject`                 | manager only |
| POST | `/admin/payments/{payment}/mark-refund-pending` | `admin.payments.mark-refund-pending`    | manager only |
| POST | `/admin/payments/{payment}/mark-refunded`       | `admin.payments.mark-refunded`          | manager only |

Final canonical names exactly as above; `RouteNamesTest` updated with all 9.

### 4.4 `PaymentPolicy`

- `view(User $user, Payment $payment): bool` — staff OR (customer && owns the appointment).
- `manage(User $user): bool` — `$user->isStaff()` for routes that only need staff (none currently, but defined for symmetry).
- Manager-only mutations are guarded at the **route level** (nested `role:manager` group), consistent with the established codebase pattern. The policy provides view-only authorization for the show and receipt-file routes; the mutation routes don't need additional `$this->authorize(...)` calls because their route group already restricts them.

### 4.5 Receipt file serving

`AdminPaymentController::receiptFile(Payment $payment, PaymentReceipt $receipt): StreamedResponse`

- `abort_unless($receipt->payment_id === $payment->id, 404)` (ownership).
- `Storage::disk('local')->download($receipt->file_path, basename: 'receipt-…')` for browsers that prefer download, OR stream inline with appropriate `Content-Type` for inline preview in the admin Show page. Decide per file MIME: image → inline; PDF → inline iframe via the same endpoint with `Content-Disposition: inline`.

The file_path is **never** exposed as a public URL or written under
`storage/app/public/`. All access goes through this authz-checked controller.

### 4.6 Migrations

Single new migration with timestamp sorting last:
`{timestamp}_create_payments_and_receipts_with_backfill.php`.

- Create `payments` table + CHECK constraints (pgsql) + index on `(status, created_at)` for the admin index "submitted-first" filter.
- Create `payment_receipts` table + CHECK constraints + composite index `(payment_id, id desc)`.
- **Backfill:** insert one `Payment` row per existing `appointments` row with
  `status='pending'`, `amount = appointments.price_at_booking`. Single SQL
  `INSERT ... SELECT` for efficiency, idempotent via `INSERT ... WHERE NOT
  EXISTS`. Safe on a live DB (no destructive ops). pgsql + SQLite branches if
  syntax differs.

Migration also adds the 4 default `clinic.bank_*` keys to
`config/clinic.php` (read-only defaults — the real values are managed at
runtime via SettingService through the existing admin settings page; no
seeder/migration writes into the DB-backed `settings` table).

## 5. Frontend

### 5.1 `resources/js/Pages/Portal/Payments/Show.vue`

(May be merged into the existing appointment-detail flow — implementation choice.
For clarity in the spec it is described as a dedicated page reachable from the
appointment row's "الدفع" action and from a banner on the appointment detail.)

Contents (RTL, foundation components, Arabic):

- **Bank-info card** — shows `bank_name`, `account_holder`, `iban` (LTR), `account_number`. "نسخ IBAN" button.
- **Amount due** — large reading of `payment.amount` with ₪.
- **Current state region** — switch on `payment.status`:
  - `pending` / `rejected`: drag-drop + file input. If `rejected`: yellow banner showing `rejection_reason` + "أعد الرفع".
  - `submitted`: blue banner "إيصالك قيد المراجعة" + inline preview of the customer's own uploaded file (the customer must be able to see what they uploaded).
  - `paid`: green badge "مدفوع" + `verified_at` date.
  - `refund_pending` / `refunded`: status info + `refund_reference` if present.
- File upload constraints client-side: JPG/PNG/PDF, ≤ 5MB; server is authoritative.

### 5.2 `resources/js/Pages/Admin/Payments/Index.vue`

- `AdminShell` + `PageHeader title="المدفوعات"`.
- Filter bar (Inertia GET preserving filters): status select (default `submitted`), date range, customer search (name/email/phone), doctor.
- `DataTable` columns: العميل، الطبيب، الموعد (date + service name), المبلغ، حالة الدفع (`StatusBadge` with Arabic labels per `PaymentStatus`), تاريخ آخر إيصال، إجراءات («عرض»).
- Pagination same shape as `Admin/Customers/Index.vue`.

### 5.3 `resources/js/Pages/Admin/Payments/Show.vue` — **where the manager sees the receipt**

- Top region: **Receipt preview** prominent.
  - Image MIME (`image/*`): inline `<img>` with max-height ~70vh + a "تكبير" button opening a foundation `Modal` for full-screen view.
  - PDF: inline `<iframe>` sized similarly + "فتح في تبويب جديد".
  - File metadata under the preview: uploader (with link to their customer page), upload time, file size, MIME.
- Right column (RTL: visually left): **Appointment + customer summary** card — name + phone + service + doctor + date + appointment status badge + the appointment-detail link.
- Action bar (manager-only, server enforces): big ✅ "تحقّق" button (POST verify), big ❌ "رفض" button (opens Modal for rejection reason; max 500 chars).
- If `paid`: shows "حُقِّق بواسطة X في T" + (if appointment status is terminal-no-service) "وَسِم للاسترداد" button → POST mark-refund-pending.
- If `refund_pending`: "سجّل تنفيذ الاسترداد" button → Modal for optional `refund_reference`.
- If `refunded`: shows the `refund_reference` and "سُجِّل بواسطة X في T".
- **Receipt history table** at bottom: every prior `PaymentReceipt` row — uploader, upload time, status (`uploaded` / `rejected`), `rejection_reason` if rejected, "عرض" link (opens its file via the receipt-file route).

### 5.4 `AdminShell` sidebar entry

Inside the `العيادة` group `children` array, new leaf (alongside `العملاء`):

```js
{ label: 'المدفوعات', href: '/admin/payments', icon: Receipt }
```

(`Receipt` from `lucide-vue-next`.) Place it after `المواعيد` and before `حجز موعد`.

**Submitted-count badge:** the entry shows a small numeric badge with the count
of `submitted` payments needing review. Source: a global Inertia-shared prop
`adminCounts.submittedPayments` computed in `HandleInertiaRequests::share()`
**only when** the user is staff (cheap COUNT query gated by role; not run for
guests/customers).

### 5.5 Portal — appointment detail banner

The portal appointment detail page (Polish-D-era surface) gets a payment
status banner at the top showing the current payment status with a CTA
linking to the payment page (rendering varies by status as listed in §5.1).
No changes to existing actions (cancel/reschedule) — payment is parallel.

## 6. Tests

### 6.1 Unit

`tests/Unit/Domain/Payment/PaymentServiceTest.php`:

- Happy-path transitions: pending → submitted, submitted → paid, submitted → rejected, rejected → submitted, paid → refund_pending, refund_pending → refunded.
- Illegal transitions throw `InvalidPaymentTransitionException`: paid → pending, refunded → anything, pending → paid (skip submitted).
- `uploadReceipt`: file too large rejected; wrong MIME rejected; happy path creates `PaymentReceipt` + transitions Payment.
- Receipt history: after multiple uploads, `currentReceipt()` is the latest non-rejected.

### 6.2 Feature

`tests/Feature/Payments/PortalPaymentTest.php`:

- Customer GETs `/portal/appointments/{id}/payment` for own appointment → 200; for another customer's → 403.
- Uploads a valid receipt → `payment.status === 'submitted'`; file stored under `storage/app/receipts/{payment_id}/`.
- Uploads after rejection → new `PaymentReceipt` row; status → `submitted`.

`tests/Feature/Payments/AdminPaymentTest.php`:

- Staff (receptionist) GETs `/admin/payments/{id}` → 200; POST verify → 403 (manager only).
- Manager verifies → status `paid`, `verified_at`/`verified_by` set.
- Manager rejects with reason → status `rejected`, latest receipt status `rejected`, reason stored on both Payment and Receipt.
- Manager marks refund-pending manually on a paid payment → status `refund_pending`.
- Manager marks refunded with reference → status `refunded`, fields set.
- `admin.payments.receipt-file` streams the file for staff; for a customer attempting access → 403.
- Customer accessing `/admin/payments` → 403 (surface isolation).

`tests/Feature/Payments/AutoRefundPendingTest.php`:

- Book an appointment → upload + verify → `paid`.
- Transition the appointment to `Cancelled` via `AppointmentTransitionService::transition` → payment now `refund_pending` (proves the listener ran inside the transition's transaction).
- Same for `Rejected`.
- `Rescheduled` does NOT auto-trigger refund_pending (old payment stays `paid`, new appointment gets a fresh `pending` payment).

`tests/Feature/RouteNamesTest.php`:

- Add all 9 new canonical route names; assert no `admin.admin.*`/`portal.portal.*` doubled prefixes.

### 6.3 Gate

- Pest 100% green (currently 176 + ~25 new ≈ 200+ tests).
- `pint --test` clean, `phpstan analyse` 0 errors at level 5, no new `@phpstan-ignore`.
- Money grep `\b(float|double)\b` × price/amount/fee/total in `app/database` empty.
- RTL grep on authored Vue dirs empty.
- Vitest unchanged (no new Vue specs required — existing wizard/shell specs continue passing).
- `npm run build` succeeds; scratch-Postgres `migrate:fresh` builds the full schema clean (incl. payments + payment_receipts + new CHECK constraints) and the backfill runs cleanly when there are no existing appointments (no-op).

## 7. Documentation (R6 / Q.9)

- `docs/DOMAIN-MODEL.md`: add `Payment`, `PaymentReceipt`, `PaymentStatus` enum, and a brief note on the Appointment ↔ Payment 1:1 relation + the listener.
- `docs/ARCHITECTURE.md`: add a "P2 — Payments" section: domain service, listener, 9 new routes (admin + portal), private receipt storage, R12 bank settings; bump "Last updated".
- `CHANGELOG.md`: open a new `## [P2] Payments — (in progress)` heading; close when the phase is done.

## 8. Out of scope (YAGNI / future phases)

- **Card / online payment** — bank transfer only.
- **Partial payments / deposits / installments** — full amount per appointment.
- **Multi-currency** — single currency (ILS / ₪) inherited from P1.
- **Email/SMS/WhatsApp notifications** — P5.
- **Audit log of who-changed-what beyond the explicit `verified_by`/`refunded_by`** — P3 territory under ADR-002.
- **Bulk verify / CSV export** — future polish if needed.
- **Customer-initiated refund request** — only manager initiates refund_pending (manual button or auto via appointment cancellation).
- **Receipt OCR / automatic amount/IBAN matching** — out of scope; pure manual review.

## 9. Sequencing

A single multi-task plan under "writing-plans" will execute:

1. Migration + models + `PaymentStatus` enum + booking-flow integration (`BookingService` creates `Payment.pending`).
2. `PaymentService` with TDD on all transitions + `InvalidPaymentTransitionException`.
3. Listener for auto refund-pending + tests.
4. Portal payment page (view + upload) + tests + receipt streaming.
5. Admin payments index + show (receipt preview + action bar) + tests.
6. Sidebar entry + submitted-count badge.
7. R12 bank-account settings: 4 keys + admin settings page update.
8. Full DoD gate, scratch-Postgres `migrate:fresh`, docs finalize, tag `p2-payments`.

Each task green at completion; same subagent-driven discipline as P1 (or
inline-direct execution per the user's lean-mode preference, decided at
plan-handoff time).
