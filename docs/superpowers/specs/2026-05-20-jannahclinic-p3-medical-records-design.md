# P3 — Medical Records (Encrypted + Audited) — Design Spec

> Status: DRAFT
> Date: 2026-05-20
> Scope: clinical
> Decision Authority: No (ADR-003 governs)
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Authority Note: This spec is implementation-support. The security posture it implements is governed by ADR-003 (which supersedes ADR-002 on completion of P3).

---

## 1. Goal

Let doctors write per-appointment medical records (diagnosis + prescription + internal notes) for jannahclinic patients, store sensitive fields encrypted at rest, capture every read and write to those fields in an immutable audit log, and expose customer-visible content (diagnosis summary + prescriptions) on the customer portal. Completing P3 lifts the ADR-002 production block on real patient data.

## 2. Context & Supersession

ADR-002 (Basic Security Posture) explicitly forbids real patient data in production until at-rest encryption and medical-record audit logging are restored. P3 restores both. On merge of P3, ADR-003 is created in `ACCEPTED` status, ADR-002 moves to `SUPERSEDED`, and the canonical registry is updated. P3 cannot be tagged `p3-medical-records` until ADR-003 is in place.

## 3. Scope

**In scope:**
- New entities: `medical_entries` (1:1 with appointments), `prescriptions` (N:1 with medical_entries), `medical_audit_logs` (append-only).
- New columns on `customer_profiles`: `chronic_conditions`, `allergies` (encrypted text).
- Encryption of all PHI free-text fields via Laravel `encrypted` cast keyed by `APP_KEY`.
- Audit log capturing CREATE / UPDATE / VIEW events on medical_entries, prescriptions, and the encrypted profile fields.
- Doctor UI to write/update an entry on a completed appointment.
- Customer portal page showing each customer's medical entries (visible content only) + prescriptions.
- Manager admin view of any patient record.
- Authorization policies and route-level gates.
- ADR-003 + registry & index updates.
- CHANGELOG entry + tag `p3-medical-records`.

**Out of scope (deferred to a later phase):**
- File attachments (lab results, imaging).
- Structured PDF prescription export.
- Drug interaction checks.
- Vitals / structured exam fields.
- Patient-facing edit or correction requests.
- Multi-doctor co-signing.
- Audit log UI / report (data is captured but only inspected via DB / future tooling).
- APP_KEY rotation tooling (rotation policy documented in ADR-003, automation deferred).

## 4. Data Model

### 4.1 `medical_entries`

One per `Appointment` (1:1, FK is `appointments.id`, unique). Entry exists only after the appointment reaches a clinical state (status `completed`) — but the row is created **on demand** when the doctor first writes, not eagerly with the appointment.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| appointment_id | bigint FK → appointments.id, unique, cascade on delete | 1:1 constraint |
| author_id | bigint FK → users.id, restrict on delete | The user (must have role=Doctor) who created the entry |
| visible_summary | text (encrypted) | Diagnosis + recommendations the customer sees in the portal |
| staff_notes | text nullable (encrypted) | Internal notes; never shown to the customer |
| created_at, updated_at | timestamp | |

CHECK constraints (pgsql only):
- `visible_summary` is non-empty after decryption (Laravel form rule, not DB constraint — encrypted ciphertext is opaque to CHECK).

**Editability:** the author can update the entry indefinitely. Each UPDATE writes a row to `medical_audit_logs` with the changed-field set (field names only, not values).

### 4.2 `prescriptions`

N per `medical_entry`. Ordered by `created_at ASC` (the doctor adds items in writing order).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| medical_entry_id | bigint FK → medical_entries.id, cascade on delete | |
| medication_name | string(255) (encrypted) | |
| dosage | string(255) (encrypted) | e.g. "500mg" |
| frequency | string(255) (encrypted) | e.g. "twice daily" |
| duration | string(255) (encrypted) | e.g. "7 days" |
| notes | text nullable (encrypted) | optional administration notes |
| created_at, updated_at | timestamp | |

A prescription row is owned by exactly one entry; deleting the entry cascades. Editing a prescription is just UPDATE — audited like the entry.

### 4.3 `customer_profiles` additions

Two new encrypted text columns:

| Column | Type | Notes |
|---|---|---|
| chronic_conditions | text nullable (encrypted) | free-form list; staff edits, customer reads |
| allergies | text nullable (encrypted) | free-form; staff edits, customer reads |

The existing `notes` column on `customer_profiles` is **re-classified as PHI** and gets the `encrypted` cast added. Because ADR-002 forbade real patient data until now, existing `notes` rows are not real PHI; a data migration re-encrypts them in place using a one-shot artisan command (`php artisan medical:encrypt-customer-notes`). Idempotent: detects already-encrypted rows by attempting `Crypt::decryptString` and skipping when it succeeds.

### 4.4 `medical_audit_logs`

Append-only. No `update_at`. No DELETE allowed at the application layer (no model method exposes deletion; database role for the app user retains DELETE only to avoid migration friction — enforcement is at the model layer with a thrown exception on save-after-create).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK → users.id, restrict on delete | Actor (null if system / cron) |
| action | string(32) | One of: `entry.created`, `entry.updated`, `entry.viewed`, `prescription.created`, `prescription.updated`, `prescription.deleted`, `profile_medical.viewed`, `profile_medical.updated` |
| auditable_type | string(255) | Polymorphic class name (e.g. `App\Models\MedicalEntry`) |
| auditable_id | bigint | Polymorphic key |
| customer_id | bigint FK → users.id, restrict on delete | The patient whose data was touched (denormalized for fast "show me everything anyone did with patient X" queries) |
| changed_fields | json nullable | Field names only — never values. e.g. `["visible_summary","staff_notes"]` |
| ip_address | string(45) nullable | request IP (IPv4 or IPv6) |
| user_agent | string(255) nullable | truncated request UA |
| created_at | timestamp | indexed |

Indexes: `(customer_id, created_at)`, `(auditable_type, auditable_id)`, `(user_id, created_at)`.

CHECK constraint (pgsql): `action` is one of the allowed values.

## 5. Encryption Posture

### 5.1 What

Sensitive fields use Laravel's `encrypted` (or `encrypted:json` when applicable) Eloquent cast. Encryption key is `APP_KEY` (already provisioned). Algorithm: AES-256-CBC via Laravel's `Illuminate\Encryption\Encrypter`.

**Encrypted fields:**
- `medical_entries.visible_summary`
- `medical_entries.staff_notes`
- `prescriptions.medication_name`
- `prescriptions.dosage`
- `prescriptions.frequency`
- `prescriptions.duration`
- `prescriptions.notes`
- `customer_profiles.chronic_conditions`
- `customer_profiles.allergies`
- `customer_profiles.notes` (existing column, newly classified PHI)

**Not encrypted:**
- FK columns, timestamps, structural columns.
- `medical_audit_logs.*` (the log records metadata only, never PHI values).

### 5.2 Why this level

Column-level encryption is the right level for jannahclinic because:
- Anyone with raw DB read (a leaked backup, an exfiltrated read replica) cannot read PHI without `APP_KEY`.
- The application keeps full SQL functionality (writes, joins, eager loads) without DB-side encryption tooling.
- The cost is one `casts` array line per field — no migration of read paths.

What it does **not** protect against, by design:
- Application-level breach (an attacker with the running app + `APP_KEY` can decrypt). Mitigated by authz + audit.
- Searchability: encrypted columns cannot be indexed for partial-match search. Out-of-scope for MVP; if needed later we add a blind-index column.

### 5.3 Key management

`APP_KEY` is stored in `.env` and is **never** committed. `php artisan key:generate` already ran in prod (P0). ADR-003 documents a manual rotation cadence (quarterly) and the rotation runbook (out-of-scope for P3 implementation).

## 6. Audit Log

### 6.1 Events captured

| Event | Trigger | `changed_fields` populated? |
|---|---|---|
| `entry.created` | `MedicalEntryService::create` | yes (all fields) |
| `entry.updated` | `MedicalEntryService::update` | yes (dirty fields only) |
| `entry.viewed` | Reading a single entry in any of: Doctor `Admin\MedicalEntryController::edit`, Manager `Admin\MedicalRecordController::show`, Customer `Portal\MedicalRecordController::show`. Index/list pages are **not** audited (too noisy; only entity-level access is). | no |
| `prescription.created` | `PrescriptionService::create` | yes |
| `prescription.updated` | `PrescriptionService::update` | yes (dirty only) |
| `prescription.deleted` | `PrescriptionService::delete` | no |
| `profile_medical.viewed` | Reading customer's encrypted PHI fields (chronic/allergies) on a record page | no |
| `profile_medical.updated` | Updating chronic/allergies/notes on a customer | yes (dirty only) |

### 6.2 Implementation

A single service `App\Domain\MedicalRecord\Services\AuditLogger` exposes:

```php
public function record(string $action, Model $auditable, User $patient, ?array $changedFields = null): void
```

Called explicitly from the four service classes and the three controllers that read entities. **No silent Eloquent observer** for write events — the audit row creation is explicit and lives inside the same `DB::transaction` block as the entity change, so an audit failure aborts the write.

`ip_address` and `user_agent` come from `Request::ip()` and `Request::userAgent()` (truncated to 255 chars). Captured via constructor injection in `AuditLogger`.

### 6.3 Append-only enforcement

`MedicalAuditLog` model overrides `save()` to throw `\LogicException` if `exists` is true (the row was already persisted). `delete()` throws unconditionally. Tests assert both behaviors. No `update_at` column. CI grep gate (added to DoD): `grep -rEn 'MedicalAuditLog::.+->update\b|MedicalAuditLog::.+->delete\b' app` must return nothing.

## 7. Authorization

| Action | Manager | Doctor | Receptionist | Customer (self only) |
|---|---|---|---|---|
| View own customers' medical record (any entry) | yes | yes | **no** | yes (own) |
| Create medical entry (on an appointment) | no | yes (only on appointments where they are the assigned doctor) | no | no |
| Update existing medical entry | no | yes (own entries only) | no | no |
| Create/update/delete prescriptions | no | yes (within own entry) | no | no |
| Edit chronic/allergies on a customer | yes | yes | no | no |
| View customer portal medical record | — | — | — | yes (own only) |

Implemented via:
- `App\Policies\MedicalEntryPolicy` (view/create/update gated on role + ownership).
- `App\Policies\PrescriptionPolicy` (delegates to parent entry policy).
- Route middleware: `auth` + `verified` + a `staff` middleware on admin routes; customer portal routes use the existing `customer` middleware.
- Receptionists explicitly blocked: any 403 response from the policy with role=Receptionist is fine.

## 8. Workflows

### 8.1 Doctor writes an entry

1. Doctor opens `Admin/Appointments/Show.vue` for a `completed` appointment.
2. New section "السجل الطبي" shows either "إضافة سجل" button (no entry yet) or a summary card with edit button (entry exists).
3. Clicking either navigates to `Admin/MedicalEntries/Edit.vue` (the same component handles create + update; the controller resolves or creates an entry).
4. Doctor fills `visible_summary` (required), `staff_notes` (optional), and a dynamic list of prescriptions (each row: medication_name, dosage, frequency, duration, notes).
5. Submit calls `PUT /admin/medical-entries/{id}` (or `POST /admin/medical-entries` for create). Server:
   - Validates role and ownership.
   - Wraps the write in `DB::transaction`.
   - Persists the entry, diffs prescriptions (creates new, updates dirty, deletes removed), writes audit rows.
6. Redirects to `Admin/Appointments/Show.vue` with `flash.success`.

### 8.2 Customer views their record

1. Customer logs in, lands on `Portal/Dashboard.vue`.
2. New sidebar item "السجل الطبي" → `Portal/MedicalRecord/Index.vue`.
3. Page lists all the customer's `medical_entries` (newest first) with `visible_summary`, prescriptions list, and the appointment date. `staff_notes` is **never serialized to the response**.
4. Customer's chronic conditions + allergies show in a header card.
5. Each list-page render also records one `profile_medical.viewed` if the chronic/allergies fields are non-null (otherwise nothing to view). Per-entry "viewed" events are recorded when the customer expands a single entry detail.

### 8.3 Manager views any record

1. Manager opens `Admin/Customers/Show.vue` for any customer.
2. New "السجل الطبي" section lists all entries (across all appointments) for that customer with `visible_summary`, prescriptions, and a "staff notes" disclosure (collapsed by default; expanding triggers an `entry.viewed`).
3. Manager can edit chronic/allergies inline (separate small form posting to `PUT /admin/customers/{id}/profile/medical`).

## 9. UI Surfaces

| Page | Role | Purpose |
|---|---|---|
| `Admin/MedicalEntries/Edit.vue` | Doctor | Create or update an entry for a specific appointment (dynamic prescriptions list — inline editable, NOT a Data Table) |
| `Admin/Customers/Show.vue` (extended) | Manager + Doctor | Read full record + edit chronic/allergies; entries-per-customer rendered with the new **DataTable** component |
| `Admin/Appointments/Show.vue` (extended) | Doctor | Entry section with create/edit CTA (1:1, no list) |
| `Portal/MedicalRecord/Index.vue` | Customer | Own record (filtered fields) — plain list, not a Data Table (customer surface, no admin features) |
| Admin sidebar | Staff | No standalone "Medical Records" item; records are accessed via Customer or Appointment context. Sidebar unchanged. |

All pages use the existing `AdminShell` / `PortalShell` layouts, foundation form components, and shadcn-vue primitives. RTL Arabic, logical CSS properties only.

### 9.1 Admin Data Table standard (new in P3)

Per the binding rule **all admin list surfaces use shadcn-vue Data Table** (https://www.shadcn-vue.com/docs/components/data-table) with the full feature set: row actions, pagination, sorting, filtering, column visibility, row selection.

P3 introduces the reusable Data Table components — they don't yet exist in the project — and uses them on the one new admin list surface in scope (entries-per-customer on `Admin/Customers/Show.vue`). The reusable components are:

- `resources/js/Components/foundation/DataTable.vue` — top-level table wrapper consuming `columns` + `data` props and a `meta` prop for server-side pagination. Built on `@tanstack/vue-table` (already installed) and the existing shadcn-vue `Table` primitives.
- `resources/js/Components/foundation/DataTablePagination.vue` — page size selector + page nav, wired to Inertia for server-side pagination via Laravel's paginator (`meta.current_page`, `meta.last_page`, `meta.per_page`, `meta.total`).
- `resources/js/Components/foundation/DataTableColumnHeader.vue` — sortable column header with the sort icon and aria-sort.
- `resources/js/Components/foundation/DataTableViewOptions.vue` — column-visibility dropdown.
- `resources/js/Components/foundation/DataTableRowActions.vue` — per-row dropdown for row actions (edit / delete / custom slot).

Server-side mode is the default for jannahclinic — never ship thousands of rows client-side. Columns are TypeScript-free here (the codebase is JS, not TS) so column defs are documented as JSDoc-typed objects.

### 9.2 Migration debt for existing admin pages

The following admin list pages were authored before this rule and **do not yet use Data Table**. They are recorded as deferred tech debt in `docs/ARCHITECTURE.md` and tracked for a dedicated migration phase (`R-DataTable Migration`) after P3 ships. P3 does not migrate them:

- `Admin/Catalog/Services.vue`
- `Admin/Catalog/Categories.vue`
- `Admin/Appointments/Index.vue`
- `Admin/Doctors/Index.vue`
- `Admin/Customers/Index.vue`
- `Admin/Payments/Index.vue`
- `Admin/Coverage/Index.vue`
- `Admin/Doctors/Schedule.vue` (this one is calendar/grid — possibly out of scope for Data Table; flag for review during R-DataTable)

Going forward, every new admin list surface MUST use Data Table from day one.

## 10. Routes

```
# Admin (staff middleware)
POST   /admin/appointments/{appointment}/medical-entry        Admin\MedicalEntryController@store    name: admin.medical-entries.store
GET    /admin/medical-entries/{entry}/edit                    Admin\MedicalEntryController@edit     name: admin.medical-entries.edit
PUT    /admin/medical-entries/{entry}                         Admin\MedicalEntryController@update   name: admin.medical-entries.update
PUT    /admin/customers/{customer}/profile/medical            Admin\CustomerController@updateMedicalProfile  name: admin.customers.profile.medical.update

# Portal (customer middleware)
GET    /portal/medical-record                                 Portal\MedicalRecordController@index  name: portal.medical-record.index
GET    /portal/medical-record/entries/{entry}                 Portal\MedicalRecordController@show   name: portal.medical-record.show
```

All names are added to `tests/Feature/RouteNamesTest.php`.

## 11. Error Handling

| Condition | Response |
|---|---|
| Doctor tries to create an entry on an appointment they're not assigned to | 403 via policy |
| Receptionist hits any P3 admin route | 403 |
| Customer tries to view another customer's entry | 403 |
| Doctor submits empty `visible_summary` | 422 with field error |
| Audit log write fails mid-transaction | Whole transaction rolls back; user sees `flash.error` |
| Encryption fails on read (corrupted ciphertext) | Caught at the model accessor; logs to `daily` channel; returns a placeholder string "—" rather than 500 so the page renders. Tests assert this fallback path. |

## 12. Testing Strategy

- **Unit tests** (`tests/Unit/Domain/MedicalRecord/`):
  - `MedicalEntryServiceTest` — create / update / authorization / transaction rollback on audit failure.
  - `PrescriptionServiceTest` — create / update / delete / diffing.
  - `AuditLoggerTest` — fields captured; `changed_fields` content correct; IP/UA truncation.
  - `MedicalAuditLogModelTest` — append-only enforcement (save-after-exists throws; delete throws).
- **Feature tests** (`tests/Feature/`):
  - `Admin/MedicalEntryControllerTest` — doctor happy path, ownership 403, receptionist 403, customer 403.
  - `Portal/MedicalRecordControllerTest` — customer sees own entries; staff_notes never in payload; another customer's record returns 404 (not 403, to avoid existence-leak).
  - `Encryption/PhiAtRestTest` — fetches the raw row via DB and asserts the column does not contain the cleartext value.
  - `Encryption/CustomerProfileBackfillCommandTest` — running the backfill command is idempotent.
- **Route lock**: `RouteNamesTest` extended with the six new names.

CI command set unchanged: `pint --test`, `phpstan analyse`, `pest --coverage --min=60`.

Additional CI gate (P3-specific): `grep -rEn 'MedicalAuditLog::.+->(update|delete)\b' app` must return nothing.

## 13. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Encrypted columns lose searchability | Out of scope; if needed later, add a per-field blind index column |
| `APP_KEY` rotation breaks decryption | ADR-003 documents key rotation runbook; implementation deferred |
| Audit log table growth | Append-only; no purge in P3. Future phase: partitioning by `created_at` |
| Doctor accidentally pastes PHI into a non-encrypted column elsewhere | Code review + CI grep gate for `encrypted` cast on every PHI field |
| Customer's portal request leaks `staff_notes` | Feature test asserts the field is absent from the Inertia response payload |
| ADR-002 left as `ACTIVE` after merge | P3 tag gate blocks unless ADR-003 is `ACCEPTED` and ADR-002 is `SUPERSEDED` in the registry |

## 14. Definition of Done

All of:

- [ ] Migrations applied: `medical_entries`, `prescriptions`, `medical_audit_logs`, `customer_profiles` add-columns. Scratch-DB `migrate:fresh` clean.
- [ ] `php artisan medical:encrypt-customer-notes` runs idempotently on a freshly seeded DB.
- [ ] All new models have correct `casts` and `fillable`.
- [ ] Six policies / 4 service classes / 6 routes wired with the names above.
- [ ] All twelve test files green; total suite ≥ previous test count + 30 new tests.
- [ ] Coverage ≥ 60% on changed files.
- [ ] `pint --test` clean.
- [ ] `phpstan analyse` clean.
- [ ] Money float CI grep unchanged (no new violations).
- [ ] RTL CI grep unchanged.
- [ ] P3-specific grep gate clean (no `MedicalAuditLog->update|delete`).
- [ ] CHANGELOG entry added.
- [ ] `docs/DOMAIN-MODEL.md` updated with new entities.
- [ ] `docs/ARCHITECTURE.md` updated where appropriate (security posture section).
- [ ] ADR-003 file created with status `ACCEPTED`; ADR-002 file updated to `SUPERSEDED` with `Superseded By: 003`; both rows updated in `docs/CANONICAL-DECISION-REGISTRY.md`; `docs/adr/README.md` index updated.
- [ ] `git tag p3-medical-records` applied to the merge commit.

## 15. Task Sequencing (input to writing-plans)

1. **Migrations + Models** — `medical_entries`, `prescriptions`, `medical_audit_logs` tables; add `chronic_conditions`/`allergies` to `customer_profiles`; cast `notes` as encrypted on `CustomerProfile`; models with `encrypted` casts and relations.
2. **`MedicalAuditLog` append-only model + AuditLogger service** — TDD: model rejects update/delete; AuditLogger writes correct rows; fields & truncation tests.
3. **Backfill command** — `medical:encrypt-customer-notes` idempotent re-encryption of existing `customer_profiles.notes`.
4. **MedicalEntryService + PrescriptionService** — TDD: create/update flows, transaction rollback on audit failure, prescription diff (create/update/delete).
5. **Reusable Data Table components** — `DataTable.vue`, `DataTablePagination.vue`, `DataTableColumnHeader.vue`, `DataTableViewOptions.vue`, `DataTableRowActions.vue` under `resources/js/Components/foundation/`; Vitest covers sort / filter / visibility / row-selection state plumbing.
6. **Policies + Routes + Controllers** — Doctor write paths; Manager + Doctor read on Admin/Customers/Show.vue; receptionist 403.
7. **Portal controller + page** — `Portal/MedicalRecordController`, `Portal/MedicalRecord/Index.vue`, `staff_notes` absence test.
8. **UI integration** — Extend `Admin/Appointments/Show.vue` and `Admin/Customers/Show.vue` (the latter uses the new DataTable for the entries list); build `Admin/MedicalEntries/Edit.vue` (form, not table); portal sidebar item.
9. **PHI-at-rest test + grep gate + RouteNamesTest** — assertion on raw DB row; CI grep `MedicalAuditLog->(update|delete)`; route name lock.
10. **ADR-003 + ADR-002 supersession + registry/index + docs sync (DOMAIN-MODEL, ARCHITECTURE)** — ARCHITECTURE.md gains the R-DataTable migration debt list (§9.2).
11. **Full DoD gate run + CHANGELOG + tag `p3-medical-records`**.

---

End of spec.
