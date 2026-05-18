# jannahclinic — Golden Rules

> Version: 1.0.1 · Adopted: 2026-05-19
> These rules are absolute and non-negotiable. Every developer, AI assistant,
> and code review MUST enforce them.

## Core Rules (Layer 1 — neutral, never edited)

### R0: Single Working Language

All code, comments, variable names, commit messages, and documentation files
MUST be written in a single agreed working language. No mixing of natural
languages in identifiers or developer-facing text.

✅ All identifiers, comments, and commit messages in the agreed language.

❌ Variable named in one language while its comment is in another; or a commit message written in a language different from the agreed working language.

---

### R1: Single Source of Truth

Every constant, label, rule, formula, and configuration value has exactly one
canonical home. Any other location that needs the value references the canonical
source — it never re-declares it.

✅ `config.get('billing.grace_days')` — single canonical definition consumed
   everywhere it is needed.

❌ The same timeout value defined separately in three different modules.

---

### R2: No Duplication (DRY)

If the same logic appears in more than one place it MUST be extracted into a
shared unit: a service, module, helper, shared component, or config entry.

✅ `formatCurrency(amount)` — one shared helper called by every view and report.

❌ The same currency-formatting expression copy-pasted into four different
   templates.

---

### R3: Trusted-Layer Authorization

Authorization MUST be enforced in the trusted layer (server side or equivalent
policy engine). Hiding a UI element is not authorization — it is decoration.

✅ Route/endpoint checks the caller's permission before returning data or
   executing a mutation.

❌ A destructive action is gated only by whether a button is visible in the UI;
   the endpoint itself performs no permission check.

---

### R4: Config-Driven, Not Hardcoded

All configurable or environment-specific values flow through a defined cascade:
persistent setting → config file → hardcoded default. Magic literals embedded
in logic are forbidden.

✅ `config.get('billing.due_days', default=30)` — value is overridable at each
   layer without touching code.

❌ `dueDays = 30` — magic literal buried in business logic.

---

### R5: Tests Before Merge

No logic merges to the main branch without:
- Unit tests covering the new or changed behaviour.
- All existing tests passing.
- Linter / static-analysis checks passing.

✅ New validation function shipped with tests covering valid, invalid, and edge-case inputs; the full suite and static analysis run green before merge.

❌ Logic merged because "it worked in manual testing" — no automated tests added, existing suite not re-run.

---

### R6: Documentation Auto-Updates

When models, endpoints, or configuration keys are created or changed, the
designated documentation files MUST be updated in the same change set. The
authoritative list of files that require updating is:

- docs/ARCHITECTURE.md
- docs/DOMAIN-MODEL.md

✅ New endpoint added → API contract doc updated in the same commit.

❌ New config key shipped with no corresponding update to the config-reference
   doc.

---

### R7: Business Logic in a Service Layer

Business rules, calculations, and domain decisions MUST live in a dedicated
service layer. Controllers and views/templates are responsible only for
request handling and presentation; they MUST NOT contain business logic.

✅ `orderService.calculateTotal(order)` — controller calls the service, renders
   the result.

❌ Total/tax calculation inlined inside a request handler; or pricing/business rules embedded directly in a view/template instead of delegated to a service.

---

### R8: Completion Quality Gate

Every change — bug fix, feature, or refactor — MUST be validated against all
applicable Definition of Done gates before it is considered done and MUST be
passed before the change is merged. "Works on my machine" or "feature appears
to function" is not sufficient. The full gate list is maintained in
`docs/DEFINITION-OF-DONE.md`.

✅ Developer walks through every applicable DoD dimension, fixes any gaps, then
   merges.

❌ Committed as soon as the happy path works without walking the Definition of Done checklist — even one unapplied gate (e.g. tests, documentation, accessibility) is a violation.

---

## Domain Rules (Layer 2 — generated, project-specific)

### R9: Money Stored as Exact Decimal / Integer Minor-Units; Never Float

All monetary amounts MUST be stored and computed as exact decimal or
integer minor-units (e.g. cents). Floating-point types MUST NOT be used for
any field that represents a monetary amount.

✅ Amount stored as `DECIMAL(19,4)` or as an integer number of minor-units.

❌ Amount stored as `FLOAT` or `DOUBLE`.

---

### R10: No Double-Counting

Prior balances MUST NOT be folded into new totals. Each total is derived fresh
from its source transactions; running totals are read-only projections, never
inputs to further calculations.

✅ Balance computed by summing all transactions; no balance field is used as an
   addend in a new calculation.

❌ A "current balance" field is directly incremented by a new transaction
   amount, making it impossible to verify without replaying history.

---

### R11: Payment Application Validates Amount, State, and Balance

Before applying a payment, the code MUST assert all three: amount > 0,
the record is in a valid state to receive the payment, and amount ≤ remaining
balance. Any failure MUST abort the operation with a clear domain error.

✅ Payment handler checks amount > 0, record status, and amount ≤ balance
   before writing.

❌ Payment applied without checking whether the record has already been
   settled or whether the amount exceeds the outstanding balance.

---

## Stack Rules (Layer 2 — generated, project-specific)

### R12: DB Invariants Enforced with Constraints, Checks, and Unique Indexes

Every database-level invariant (uniqueness, non-nullability, referential
integrity, range checks) MUST be declared in the schema as a constraint,
check, or unique index — not only in application code.

✅ Unique constraint on `(user_id, resource_id)`; NOT NULL on required columns;
   FK constraint for every foreign key.

❌ Uniqueness enforced only by a query in the service layer with no DB-level
   constraint.

---

### R13: No Raw Queries with Unsanitized Input

All database queries MUST use parameterized queries or the ORM's query
builder. String interpolation or concatenation of user-supplied values into
query strings is prohibited.

✅ `DB::select('SELECT * FROM items WHERE id = ?', [$id])` or ORM fluent API.

❌ `DB::select("SELECT * FROM items WHERE id = $id")` — direct string
   interpolation.

---

### R14: Mass-Assignment Protection on Models

Every model MUST declare either a `$fillable` allowlist or a `$guarded`
denylist. Unguarded models (`$guarded = []`) are forbidden outside of
explicitly reviewed migration/seed contexts.

✅ `protected $fillable = ['name', 'email'];` on the model.

❌ Model with no `$fillable` / `$guarded` declaration, accepting any input key.

---

### R15: Errors Never Leak Internal Messages to Responses

Unhandled exceptions, stack traces, and internal error details MUST NOT be
surfaced in UI responses. All error presentation paths MUST use a sanitized,
user-facing message; raw exception details go to server-side logs only.

✅ Exception handler returns a generic user message; stack trace written to
   the application log.

❌ A stack trace or raw SQL error message is rendered directly in the UI.

---

### R16: Every Data View Handles 4 States: Loading / Empty / Error / Success

Every component or page that fetches or presents data MUST explicitly handle
all four lifecycle states: loading, empty (no data), error, and success.
Omitting any state is a defect.

✅ Component renders a spinner while loading, a "no results" message when the
   list is empty, a user-friendly error panel on failure, and the data table
   on success.

❌ Component renders nothing (blank) while loading, or crashes on empty data.

---

### R17: Visual System Tokens Only; No Ad-Hoc Styling

All colors, spacing, typography sizes, radii, and shadow values MUST come from
the project's design-system token set. Hardcoded hex values, magic pixel sizes,
or one-off inline style overrides are prohibited.

✅ `color: var(--color-primary-600); padding: var(--spacing-4);`

❌ `color: #3b82f6; padding: 16px;` — raw values not tied to the token system.

---

### R18: Consistent, Premium Visual Rhythm via Shared Tokens

Spacing, sizing, and type scale across all screens MUST use the same shared
token set, producing a visually consistent rhythm. Divergent spacing or type
sizes that are not sourced from tokens are a violation.

✅ All page sections use `--spacing-*` tokens; heading hierarchy is always
   `--font-size-xl / lg / md`.

❌ One page uses `mt-4` from the token while another uses an inline `margin-top: 18px`.

---

### R19: Responsive: No Dead Space, No Floating Forms

Every layout MUST be tested at all defined breakpoints. Forms, cards, and
panels MUST fill available width appropriately — no layouts that leave large
dead-space columns or that render a narrow form floating in a wide viewport.

✅ Form uses `max-w-prose` or equivalent with centered, bounded width on
   desktop; fills available width on mobile.

❌ A data-entry form renders at 300px fixed width in a 1440px viewport, leaving
   >1000px of blank space on each side.

---

### R20: Layout Uses Logical (Start/End) Properties Only

All CSS margin, padding, border, text-align, float, position, and sizing
properties MUST use CSS logical properties (`inline-start`, `inline-end`,
`block-start`, `block-end`). Physical directional properties (`left`, `right`,
`margin-left`, `padding-right`, etc.) are prohibited.

✅ `margin-inline-start: var(--spacing-4); text-align: start;`

❌ `margin-left: 16px; text-align: right;` — physical directional properties
   that break RTL layouts.

---

### R21: Forms Auto-Populate

All forms MUST auto-populate from the relevant data source in three scenarios:
validation recovery (re-populate on failed submission), edit mode (pre-fill
with existing record values), and contextual defaults (pre-fill from
navigation context or URL parameters where appropriate).

✅ Edit form pre-filled with current record values; on validation failure the
   submitted values are repopulated so the user does not re-enter data.

❌ Edit form always starts empty; user must re-enter all values after a
   validation error.

---

### R22: Client-Only Screen Preferences in Local Storage; Never Server State or Secrets

Ephemeral client-side UI preferences (theme, column visibility, sidebar state,
etc.) MUST be stored in local storage and MUST NOT be persisted to the server
or mixed with authoritative application state. Secrets MUST NOT be stored in
local storage.

✅ Sidebar collapse preference written to `localStorage`; server state is
   authoritative for data.

❌ UI display preference persisted to a user record in the database alongside
   business data; or a token stored in local storage.

---

### R23: Overlays Render at Document Root; No Transformed Ancestors

Modals, drawers, dropdowns, and tooltips MUST be rendered at the document root
(e.g. via a portal / teleport mechanism). They MUST NOT be nested inside a
CSS-transformed, filtered, or will-change ancestor, which would break
fixed/absolute positioning.

✅ Modal rendered via `<Teleport to="body">` or equivalent portal; no
   `transform` on any ancestor.

❌ Modal rendered inside a `<div style="transform: translateX(0)">`, causing
   it to be clipped or mis-positioned.

---

## Summary Checklist

Before any PR, verify:
- [ ] R0 single working language
- [ ] R1 single source of truth
- [ ] R2 no duplicated logic
- [ ] R3 trusted-layer authorization
- [ ] R4 no magic values
- [ ] R5 tests written and passing
- [ ] R6 documentation updated
- [ ] R7 business logic in service layer
- [ ] R8 Completion Quality Gate passed (all applicable DoD gates)
- [ ] All generated Domain Rules verified
- [ ] All generated Stack Rules verified
