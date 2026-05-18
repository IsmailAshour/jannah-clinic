# Derivation Guide

Generating Layer 2 = run this interview, then filter the Rule Library by the
triggers. Same answers always produce the same rule set.

## Part A — Interview

Record answers as a YAML block kept in the project's ADR-001.

```yaml
domain:
  handles_money: <yes|no>
  handles_pii: <yes|no>
  multi_tenant: <yes|no>
  tenant_key_name: <text, e.g. tenant_id>      # required only if multi_tenant: yes
  immutable_records: <yes|no>          # records that cannot change after a state
  audit_required: <yes|no>
  has_allocation_or_split: <yes|no>    # amounts split across entities
  has_payments: <yes|no>
  concurrent_financial_writes: <yes|no>
stack:
  language: <text>
  framework: <text|none>
  database: <relational|document|none>
  test_runner: <text>
  linter: <text>
  static_analysis: <text|none>
ui:
  has_ui: <yes|no>
  server_rendered_templates: <yes|no>
  i18n: <yes|no>
  bidi_rtl: <yes|no>
  has_design_system: <yes|no>
  has_forms: <yes|no>
  produces_documents: <yes|no>         # printable/PDF output
  client_screen_state: <yes|no>
api:
  public_api: <yes|no>
sends_external_comms: <yes|no>          # SMS/email/push/webhooks
quality:
  coverage_threshold: <number, e.g. 70>
  compliance: <list, e.g. [RFC7807, ISO27001] or none>
  autodoc_targets: <list of doc files/globs that must update when models/endpoints/config change, e.g. ["docs/ARCHITECTURE.md","docs/API.md"]>
runtime_paths: <glob list, e.g. ["src/**","tests/**"]>
```

## Part B — Rule Library

Each rule has a trigger. If the trigger is true, emit the rule into
`{{DOMAIN_RULES}}` / `{{STACK_RULES}}` of GOLDEN-RULES, and (where a Gate
column is given) a row into `{{DOMAIN_GATES}}` of DEFINITION-OF-DONE.

| ID | Rule (neutral, parameterized) | Trigger | Emits |
|----|-------------------------------|---------|-------|
| D01 | Money stored as exact decimal/integer-minor-units; never float | domain.handles_money | Rule + Gate + CI(grep float on money) |
| D02 | No double-counting: prior balances never folded into new totals | domain.handles_money | Rule |
| D03 | Financial records immutable after issue; corrections are new records | domain.immutable_records | Rule + Gate |
| D04 | Allocation/splits must balance to the source amount; rounding policy explicit | domain.has_allocation_or_split | Rule |
| D05 | Payment application validates amount>0, state, amount≤balance | domain.has_payments | Rule |
| D06 | Concurrent financial writes guarded in a locked transaction | domain.concurrent_financial_writes | Rule |
| D07 | Every query scoped by tenant key; no unscoped reads | domain.multi_tenant | Rule + Gate |
| D08 | Tenant-scope verified server-side per request | domain.multi_tenant | Rule + Gate |
| D09 | PII access minimized, encrypted at rest, never logged in clear | domain.handles_pii | Rule + Gate |
| D10 | Sensitive actions audit-logged: before/after/actor/time/reason/source | domain.audit_required | Rule + Gate |
| D11 | Destructive operations require explicit elevated role check | domain.audit_required OR domain.multi_tenant | Rule |
| S01 | DB invariants enforced with constraints/checks/unique indexes | stack.database == relational | Rule + Gate |
| S02 | No raw queries with unsanitized input | stack.database != none | Rule + Gate |
| S03 | Mass-assignment protection on models | stack.framework != none | Rule |
| S04 | Errors never leak internal messages to responses | api.public_api OR ui.has_ui | Rule |
| A01 | API errors use a standard problem format (RFC 7807) | api.public_api | Rule + Gate |
| A02 | HTTP status codes follow RFC 9110 semantics | api.public_api | Rule |
| A03 | Rate limiting on auth and API routes | api.public_api | Gate |
| U01 | Every data view handles 4 states: loading/empty/error/success | ui.has_ui | Rule + Gate |
| U02 | Visual system tokens only; no ad-hoc styling | ui.has_design_system | Rule + Gate |
| U03 | Consistent, premium visual rhythm via shared tokens | ui.has_design_system | Rule |
| U04 | Responsive: no dead space, no floating forms | ui.has_ui | Rule |
| U05 | All display text via translation function; zero hardcoded strings | ui.i18n | Rule + Gate |
| U06 | Translation parity across locales in the same commit | ui.i18n | Rule + Gate(CI parity) |
| U07 | Layout uses logical (start/end) properties only | ui.bidi_rtl | Rule + Gate(CI grep physical props) |
| U08 | No injection into script contexts; safe escaping helper | ui.server_rendered_templates | Rule + Gate |
| U09 | Forms auto-populate (validation recovery, edit pre-fill, contextual) | ui.has_forms | Rule |
| U10 | Print/PDF output: inline styles, hides chrome, no external CSS | ui.produces_documents | Rule |
| U11 | Client-only screen prefs in local storage; never server state/secrets | ui.client_screen_state | Rule |
| U12 | Overlays render at document root; no transformed ancestors | ui.has_ui AND ui.has_design_system | Rule |
| C01 | External comms via a service with a no-op dev driver; keys via config | sends_external_comms | Rule + Gate |
| Q90 | Compliance standards enforced and tracked | quality.compliance != none | Gate (one row per standard) |

> Total: 32 candidate rules. The neutral wording of each is finalized by
> reading the matching building.app rule and stripping stack/domain nouns.

## Part C — Generation Procedure

1. For each library row whose trigger evaluates true against the interview,
   render the rule text, substituting parameters from the interview (e.g.
   tenant_key_name for D07/D08, linter command for CI rows, runtime_paths
   for scope checks). Direct-substitution Layer-1 placeholders are filled verbatim from interview/context (not via triggers): {{PROJECT_NAME}}, {{GOVERNANCE_OWNER}}, {{QUALITY_OWNER}}, {{RUNTIME_PATHS}}, {{AUTODOC_TARGETS}} (from quality.autodoc_targets), {{COVERAGE_THRESHOLD}}, {{LINT_CMD}}, {{STATIC_CMD}}, and the START-HERE setup/test/stack tokens.
2. Bucket by ID prefix (deterministic): D* and C* rows → GOLDEN-RULES
   `{{DOMAIN_RULES}}`; S*, A*, U* rows → GOLDEN-RULES `{{STACK_RULES}}`;
   Q* rows emit NO rule text (gates only — see step 3). Within each bucket,
   keep Part B table order (top to bottom), skipping rows whose trigger is
   false. Renumbering is a single sequence continuing from the fixed core
   (after R8): number Domain Rules first (in Part B table order), then Stack
   Rules (in Part B table order), as R9, R10, R11, … with no gaps and no
   per-bucket restart.
3. Append rendered Gate rows to DEFINITION-OF-DONE `{{DOMAIN_GATES}}`,
   grouped (Architecture, UI/UX, i18n, Compliance), and the gate names into
   `{{DOMAIN_GATE_NAMES}}`. Gate group mapping is deterministic: D*/S*/A*/C*
   → Architecture (A* = API-contract gates are architectural, even though A* rule text lives in {{STACK_RULES}}); U05, U06 → i18n; U07 → i18n; remaining U* → UI/UX;
   Q90 → Compliance. Q90 expands to one gate per entry in
   quality.compliance, each id suffixed with the standard's short name
   (e.g. Q90-RFC7807), in the order listed in the interview. Within each group, gates appear in Part B table order (top to bottom); group emission order is Architecture, UI/UX, i18n, Compliance.
4. Emit CI rows into `{{CI_COMMANDS}}` only for library rows with a CI hint,
   using the interview's linter/static/test commands.
5. Determinism check: re-running steps 1–4 on the same interview YAML MUST
   produce byte-identical output.
**Determinism constraints (not a step):** process interview fields in the exact order they appear in Part A (not parsed-map order); A03 is intentionally Gate-only (rate limiting is enforced as a DoD/CI check, not a code rule).

## Part C.2 — Worked Example (building.app)

Interview answers and the resulting rule set for the source project live in
`../reference-example/BUILDING-APP-CASE-STUDY.md`. That file is also the
golden test (Task 10): feeding those answers through Part C must reproduce
building.app's known rule set.
