# {{PROJECT_NAME}} — Golden Rules

> Version: {{KIT_VERSION}} · Adopted: {{YYYY-MM-DD}}
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

{{AUTODOC_TARGETS}}

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

{{DOMAIN_RULES}}

## Stack Rules (Layer 2 — generated, project-specific)

{{STACK_RULES}}

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
