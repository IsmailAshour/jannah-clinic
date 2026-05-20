# jannahclinic — P4a: Loyalty Points — Design

> **Status:** DRAFT — pending user review
> **Date:** 2026-05-20
> **Scope of P4 split:** Original P0 roadmap row `P4 — الولاء والعضويات` bundles two sub-projects:
>
> - **P4a (this spec):** Loyalty points — earn 1₪=1 point on every paid appointment, redeem points for services at a per-service points cost.
> - **P4b (later spec):** Memberships — pre-paid session bundles (purchase plan → consume sessions per appointment).
>
> Same split rationale as P5a/P5b: each half is independently shippable; loyalty works without memberships and vice versa.
>
> **Builds on:** P1 (Appointment + BookingService), P2 (Payment + PaymentService), P5a (NotificationService).
>
> **Out of P4a (explicitly):** SMS/email loyalty notifications (P5a deferred-items rule still applies — in-app only), points expiry, VIP/tier bonus earning, promotional 2x campaigns, point transfers between customers, redemption discounts (partial points + cash). All would re-open after P4a ships.

---

## 1. Motivation & decisions locked

The clinic wants a simple retention mechanic: customers earn points proportionally to what they pay, and can spend those points on future services. The manager must be able to:
- Enable/disable loyalty per service (some services are "out of program").
- Set the redemption cost per service.
- Adjust a customer's balance by hand (with reason and audit).

Five decisions lock the shape of this work:

1. **1 ₪ paid = 1 point earned** (`floor(amount)`), awarded the moment a cash payment transitions to `Paid` via `PaymentService::verify`. No half-points.
2. **One flag per service** (`loyalty_enabled`) controls both directions — disabled services never earn and never redeem. A service is "in the program" or it isn't.
3. **Redemption replaces the payment, not adds to it.** A loyalty-redeemed appointment has `payment_method = 'loyalty_points'` and NO `Payment` row. The ledger entry is the proof of payment.
4. **Refunds claw back points symmetrically.** When `PaymentService::markRefunded` fires, the previously-awarded points are subtracted (reason: `clawback_from_refund`). If the customer no longer has enough balance, the ledger still goes negative (recorded honestly) and the customer must earn back to positive before redeeming again.
5. **Append-only ledger.** Mirrors `medical_audit_logs` from P3: override `save()` to throw on `$exists`, `delete()` to throw unconditionally. The denormalized `customer_profiles.loyalty_balance` is a cache rebuilt from the ledger sum on every write.

---

## 2. Domain model

### 2.1 `loyalty_ledger` (new, append-only)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `customer_id` | bigint | NOT NULL, FK→`users.id` `restrictOnDelete` (history retention) |
| `points_delta` | integer | signed; positive=earn, negative=spend/clawback |
| `balance_after` | integer | snapshot of balance after this entry |
| `reason` | varchar(32) | enum value (§2.2) |
| `reference_type` | varchar | morph: `App\Models\Payment` / `App\Models\Appointment` (nullable for manager adjustments) |
| `reference_id` | bigint | morph (nullable) |
| `notes` | text | nullable; manager-adjustment reason |
| `actor_id` | bigint | nullable FK→`users.id` `restrictOnDelete`; manager who triggered the entry (for adjust + reverse), null for system-generated |
| `created_at` | timestamp | (no `updated_at` — append-only) |

Indexes:
- `(customer_id, created_at DESC)` for per-customer feed
- `(reason, created_at)` for reporting/audit

PostgreSQL CHECK constraint on `reason` (mirrors `medical_audit_logs` pattern).

### 2.2 `LoyaltyReason` enum (`App\Enums`)

| Value | Trigger |
|---|---|
| `earned_from_payment` | `PaymentService::verify` (cash → Paid) |
| `redeemed_for_appointment` | `BookingService::book` with `payment_method=loyalty_points` |
| `clawback_from_refund` | `PaymentService::markRefunded` |
| `refund_reversal` | `AppointmentTransitionService::transition` (Cancelled) when `payment_method=loyalty_points` |
| `adjustment_by_manager` | manual `LoyaltyService::adjust` |

### 2.3 `services` column additions

| Column | Type | Default |
|---|---|---|
| `loyalty_enabled` | bool | `true` |
| `loyalty_redemption_points` | integer unsigned, nullable | `null` |

CHECK constraint (pgsql): `loyalty_redemption_points IS NULL OR loyalty_redemption_points > 0`.

When `loyalty_enabled = false`: no earn, no redeem.
When `loyalty_enabled = true` and `loyalty_redemption_points` is `null` or `0`: earn only, no redemption.
When `loyalty_enabled = true` and `loyalty_redemption_points > 0`: both earn and redeem.

### 2.4 `customer_profiles` column addition

| Column | Type | Default |
|---|---|---|
| `loyalty_balance` | integer | `0` |

Denormalized cache. The ledger is authoritative — if a discrepancy ever appears between the ledger sum and the cached balance, the ledger wins; an artisan command `loyalty:rebuild-balances` rebuilds the cache from the ledger (one-time migration command, kept for ops).

### 2.5 `appointments` column additions

| Column | Type | Default |
|---|---|---|
| `payment_method` | varchar(16) | `'cash'` |
| `loyalty_points_spent` | integer unsigned, nullable | `null` |

CHECK constraint (pgsql): `payment_method IN ('cash','loyalty_points')` AND `(payment_method = 'loyalty_points') = (loyalty_points_spent IS NOT NULL)`.

When `payment_method = 'loyalty_points'`:
- `Payment` row is NOT created in `BookingService::book`.
- The ledger entry with `reference_type=Appointment, reference_id={id}, reason=redeemed_for_appointment` is the proof.

### 2.6 `BookingData` DTO addition

`App\Domain\Booking\Data\BookingData` gains a new field `paymentMethod: 'cash' | 'loyalty_points'` (default `'cash'`). The booking wizard sets it from the customer's choice.

---

## 3. `LoyaltyService` (new)

Located at `app/Domain/Loyalty/Services/LoyaltyService.php`. Mirrors `AuditLogger` and `NotificationService` patterns: explicit, transactional, called inline by originating services.

```php
final class LoyaltyService
{
    public function __construct(private NotificationService $notifications) {}

    /** Award points for a successful cash payment. Called after the payment transaction commits. */
    public function awardForPayment(Payment $payment): void;

    /** Subtract previously-awarded points when a payment is refunded. */
    public function clawbackForRefund(Payment $payment): void;

    /** Deduct points for a loyalty-redeemed booking. Called inside the BookingService transaction. */
    public function redeemForAppointment(Appointment $appointment, User $customer): int;

    /** Return points to the customer when a loyalty-redeemed appointment is cancelled. */
    public function reverseRedemption(Appointment $cancelled): void;

    /** Manager-initiated balance adjustment. Audited; notifies the customer. */
    public function adjust(User $customer, int $delta, string $note, User $manager): void;

    /** Current balance from the cache; tested against ledger sum in CI. */
    public function balance(User $customer): int;
}
```

**Inside-transaction invariant** (same as P5a/AuditLogger): every method that writes to the ledger ALSO updates `customer_profiles.loyalty_balance` within the same transaction. Both writes succeed or neither does. The notification dispatch happens AFTER the transaction (P5a's lesson: don't roll back domain on a notification failure).

**Insufficient balance**: `redeemForAppointment` throws `InsufficientLoyaltyBalanceException` if `customer.balance < service.loyalty_redemption_points`. Caller renders a friendly 422 / form error; no partial deduction.

---

## 4. Service integration points

| Service | Method | New line(s) |
|---|---|---|
| `PaymentService::verify` | After transaction commits: `if ($p->appointment->service->loyalty_enabled) { $loyalty->awardForPayment($p->fresh()); }` |
| `PaymentService::markRefunded` | After commit: `if (loyalty entries exist for this payment) { $loyalty->clawbackForRefund($p->fresh()); }` |
| `BookingService::book` | Inside the existing `DB::transaction`: branch on `$d->paymentMethod`. If `loyalty_points`: validate service + balance; create appointment with `payment_method='loyalty_points'`; **skip** `Payment::create`; call `$loyalty->redeemForAppointment($appt, $customer)`. |
| `AppointmentTransitionService::transition` | After commit: if status went to `Cancelled` AND `appt.payment_method === 'loyalty_points'`: `$loyalty->reverseRedemption($appt)`. |

The `awardForPayment` is **idempotent** by ledger lookup: if a `earned_from_payment` row already exists for this `payment_id`, skip. (Prevents double-award if the post-transaction notification path is retried.) Same idempotency for `clawback_from_refund` and `reverseRedemption`.

---

## 5. Routes & controllers

### 5.1 New routes (admin)

| Method | Path | Name | Auth |
|---|---|---|---|
| GET | `/admin/customers/{customer}/loyalty` | `admin.customers.loyalty.show` | all staff |
| POST | `/admin/customers/{customer}/loyalty/adjust` | `admin.customers.loyalty.adjust` | **manager only** |

The "show" endpoint returns ledger paginated 20/page + current balance + customer header. The `adjust` endpoint accepts `delta` (int, can be negative), `note` (string, required, max 500) and dispatches `LoyaltyService::adjust`.

Service-config endpoints already exist (`admin.catalog.services.update`); add `loyalty_enabled` and `loyalty_redemption_points` to the validation rules and `#[Fillable]`.

### 5.2 New routes (portal)

| Method | Path | Name | Auth |
|---|---|---|---|
| GET | `/portal/loyalty` | `portal.loyalty.index` | customer |

Returns own balance + own ledger paginated 20/page + summary (total earned lifetime, total redeemed lifetime).

### 5.3 New routes (booking)

No new routes. `portal.booking.store` and `admin.booking.store` accept the existing `BookingData` payload, with `payment_method` as an optional field defaulting to `cash`.

### 5.4 Route names locked

All 3 names added to `tests/Feature/RouteNamesTest.php`.

---

## 6. UI

### 6.1 Admin — Catalog / Services edit form

Inside the existing service edit modal (or page), add a "Loyalty" section after pricing:

- Toggle: «تفعيل الولاء على هذه الخدمة»
- Conditional (visible only when toggle on): Input «نقاط الاستبدال» (number, min 1, optional)
- Help text: «اتركه فارغًا إن أردت كسب النقاط فقط دون السماح بالاستبدال.»

Validation rules in `ServiceController::store`/`update`:
- `loyalty_enabled`: boolean
- `loyalty_redemption_points`: nullable integer min:1, required_with no, but only meaningful when `loyalty_enabled` is true.

### 6.2 Admin — Customer detail page

`/admin/customers/{id}` gains a new section above "السجل الطبي للزيارات":

```
┌──── الولاء ────────────────────────────────────────┐
│ الرصيد الحالي:  1 247 نقطة                          │
│ [الكسب الإجمالي: 3 200] [الاستبدال: 1 953]          │
│                                                     │
│ [+ تعديل يدوي]   ← manager only                    │
│                                                     │
│  جدول الـ ledger (10 آخر إدخالات + رابط للمزيد):    │
│  +200  كسب من زيارة #45        منذ يومين            │
│  −500  استبدال للحجز #51       منذ أسبوع            │
│  ...                                                 │
└────────────────────────────────────────────────────┘
```

The "+ تعديل يدوي" button opens a modal with `delta` (signed int) + `note` (textarea) + confirm. Only renders for managers.

### 6.3 Admin — Customers Index

New StatCard variant on `/admin/customers`:
- «إجمالي النقاط القائمة» = `SUM(loyalty_balance)` across all customers.

Slots into the existing 4-card layout from the Customers reference page (replacing or alongside one of the existing cards — keep total/active/inactive/loyalty-total).

### 6.4 Portal — `/portal/loyalty` (new page)

```
┌─── الرصيد ────────────┐  ┌─── منذ البداية ──────┐
│       1 247           │  │  كسبت: 3 200          │
│       نقطة            │  │  استبدلت: 1 953       │
└──────────────────────┘  └──────────────────────┘

[الكل] [كسب] [استبدال]  ← tabs (filter the table below)

┌──────────────────────────────────────────────────┐
│ +200  كسب من زيارة د. أحمد       2 مايو          │
│ −500  استبدال للحجز              25 أبريل         │
│ +50   تعديل من الإدارة (شكر)     20 أبريل         │
│ ...                                                │
└──────────────────────────────────────────────────┘
                              [‹] صفحة 1 من 4 [›]
```

### 6.5 Portal — Services browse

`/portal/services` shows a small badge on every loyalty-enabled-and-redeemable service:
- «استبدل بـ 500 نقطة» — appears below the price
- If the customer's balance >= cost: badge has primary color, clickable
- If balance < cost: badge dimmed, tooltip «رصيدك الحالي: X — تحتاج Y أكثر»

### 6.6 BookingWizard — payment method picker

Step 3 (review/confirm) gains a payment-method picker BEFORE the confirm button:

```
طريقة الدفع:
○ نقدًا (تحويل بنكي)                            ← default
○ بنقاط الولاء — يكلّف 500 نقطة، رصيدك 1 247  ← only if eligible
```

The second option is rendered only when:
- The selected service has `loyalty_enabled = true` AND `loyalty_redemption_points > 0`
- The customer's `loyalty_balance >= loyalty_redemption_points`

When picked, the wizard submits with `payment_method=loyalty_points` and no upload step follows (the appointment is "paid" instantly).

### 6.7 ClientShell — bottom nav

Extend from 5 to 6 tabs (`grid-cols-5` → `grid-cols-6`): add «نقاطي» as the 6th tab linking to `/portal/loyalty`.

---

## 7. Authorization

| Action | Allowed |
|---|---|
| View own balance + ledger | the customer |
| View any customer's balance + ledger | any staff (Manager / Doctor / Receptionist) |
| Adjust balance | **Manager only** |
| Configure service loyalty flags | Manager only (already gated on `role:manager` for the services update route) |
| Redeem at booking (own appointment) | customer (or staff booking on their behalf) |

Enforcement:
- Routes already gated by middleware (`role:customer` for portal, `role:manager,doctor,receptionist` for admin).
- `admin.customers.loyalty.adjust` further gated by `role:manager`.
- `LoyaltyService::adjust` re-checks `$manager->role === Manager` and throws `AuthorizationException` otherwise (defense-in-depth).
- A simple `LoyaltyLedgerPolicy::view($user, $entry)` ensures customer can only see entries with `customer_id === $user->id`.

---

## 8. Notifications (P5a integration)

`NotificationCategory::Loyalty` added to the enum. UI gets a chip «الولاء» in the notification center filter set.

New `LoyaltyChanged` notification class (mirrors `PaymentChanged` / `MedicalRecordChanged`).

New generators on `NotificationService`:

| Method | Trigger | Recipient | Title |
|---|---|---|---|
| `loyaltyPointsEarned(User $c, int $delta, Appointment $a)` | from `awardForPayment` | customer | «+X نقطة من زيارتك» |
| `loyaltyPointsRedeemed(User $c, int $delta, Appointment $a)` | from `redeemForAppointment` | customer | «X نقطة استُبدلت بحجز» |
| `loyaltyPointsAdjusted(User $c, int $delta, string $note, User $manager)` | from `adjust` | customer | «عدّل الطاقم رصيدك» (body shows manager name + note) |
| `loyaltyPointsReversed(User $c, int $delta, Appointment $a)` | from `clawback_from_refund` AND `reverseRedemption` | customer | «أُعيدت/سُحبت X نقطة بسبب إلغاء/استرداد» |

All include `category=loyalty`, `action_url=/portal/loyalty`, `subject_type=loyalty_ledger`, `subject_id=ledger_entry_id`.

No PHI implications (loyalty data is not under ADR-003).

---

## 9. Testing

### Pest unit (`tests/Unit/Loyalty/LoyaltyServiceTest.php`)
- `awardForPayment` creates a `earned_from_payment` row + updates cache.
- `awardForPayment` is idempotent (second call with same payment = no-op).
- `clawbackForRefund` mirrors the earned amount; balance can go negative (recorded honestly).
- `redeemForAppointment` throws when balance < cost; throws when service not eligible; happy path deducts + writes ledger.
- `reverseRedemption` returns the exact `loyalty_points_spent` from the appointment.
- `adjust` writes manager name + note; rejects non-manager actor.
- Transaction rollback test: throw mid-transaction → no ledger row + no balance change.
- Append-only invariant: `LoyaltyLedger::save()` on existing throws; `delete()` throws.

### Pest feature
- **EarnFlowTest**: cash payment verify → ledger entry exists; service with `loyalty_enabled=false` → no entry.
- **RedeemFlowTest**: booking with `payment_method=loyalty_points` → no Payment row, ledger entry exists, balance decremented; insufficient balance → 422.
- **RefundFlowTest**: full cash → verify → refund → clawback entry exists, balance net zero.
- **CancellationFlowTest**: loyalty-redeemed appointment → cancelled → reverse entry, balance restored.
- **AdjustFlowTest**: manager adjusts + customer sees ledger entry + notification fires.
- **AuthorizationTest**: customer A cannot see customer B's ledger; receptionist cannot adjust; doctor cannot adjust; customer cannot adjust self.
- **ServiceConfigTest**: manager toggles `loyalty_enabled` + sets `loyalty_redemption_points`; validation rejects negative cost; receptionist 403.

### Vitest
- BookingWizard payment-method picker: only renders when eligible; submits correct payload.
- Portal /loyalty: renders balance, ledger rows, tabs filter correctly.

Target coverage: **+30 Pest tests**, **+3 Vitest specs**.

---

## 10. Out of scope (deferred — `loyalty-deferred-items` for future ADRs)

| Item | Reactivation trigger |
|---|---|
| Points expiry (e.g., 12 months unused) | retention policy decision |
| VIP tiers (e.g., gold earns 1.5x) | clinic asks for tiering |
| Promotional campaigns (2x weekends, double-points launch) | marketing request |
| Point transfers between customers | family-account feature |
| Mixed redemption (partial points + cash) | UX research showing demand |
| SMS/email notification of loyalty events | broader notification-channel ADR (P5a deferred) |
| Memberships (P4b — independent sub-project) | next sprint |

---

## 11. Implementation sequencing (informs the plan)

Plan should follow this order; every step ends green (Pest + Vitest + Pint + PHPStan + Vite):

1. **Migration + `LoyaltyReason` enum + LoyaltyLedger model + LoyaltyService skeleton + unit tests.** No integration yet.
2. **Routes + admin/portal controllers + auth tests (cross-user 403 matrix).** Stub Inertia pages.
3. **PaymentService integration** — award + clawback + EarnFlow/RefundFlow tests.
4. **BookingService integration** — redeem + AppointmentTransition reverse + RedeemFlow/CancellationFlow tests.
5. **Admin — Service config UI + ServiceConfigTest.**
6. **Admin — Customer detail loyalty section + adjust modal + AdjustFlowTest.**
7. **Portal — `/portal/loyalty` page + ClientShell 6-tab nav.**
8. **Portal — Services browse badges + BookingWizard payment-method picker (Vitest).**
9. **Notifications integration + LoyaltyChanged class + NotificationCategory enum update.**
10. **DoD gate + ARCHITECTURE + CHANGELOG entry + tag `p4a-loyalty`.**

---

## 12. Definition of Done

- All 10 sequencing tasks merged.
- Pest green (~280 + ~30 new = ~310).
- Vitest green (26 + 3 new = 29).
- Pint clean, PHPStan L5 clean, `npm run build` clean.
- All new routes locked in `RouteNamesTest`.
- No existing test regressions.
- `CHANGELOG.md` entry under "[P4a] Loyalty Points — YYYY-MM-DD".
- `docs/ARCHITECTURE.md` updated with the loyalty model + deferred-items pointer.
- `loyalty:rebuild-balances` artisan command exists and is idempotent (manual ops use).
- Tag `p4a-loyalty` applied and pushed.
- One manual smoke pass: customer earns on paid visit → sees badge → opens portal/loyalty → sees ledger → books another service redeeming points → ledger updated; manager adjusts +50 with note → customer sees notification → ledger entry rendered.
