# 00 — Bootstrap Runbook

Adopt the methodology kit in a target project. A human or an AI agent runs
these 8 steps in order. The procedure is **idempotent**: re-running updates
values and never duplicates registry rows or ADRs.

Inputs: target project root `T`. Scope: default = all four pillars; a partial
scope may be requested (e.g. governance only).
When a pillar is out of scope, skip the steps that produce only its artifacts and mark the corresponding Step 8 checklist items `N/A` with a one-line reason.

## Step 1 — Detect Context
Read `T`'s manifests, source tree, and existing docs. Infer language,
framework, database, test/lint/static commands, runtime paths. Record what
was inferred vs. unknown.
**Output:** context summary written to `T/.bootstrap-context.yml` (project root, not `docs/`; removed after Step 4).

## Step 2 — Derivation Interview
Open `layer2-derivation/DERIVATION-GUIDE.md` Part A. Ask only the questions
Step 1 could not infer. Produce the interview YAML.
**Output:** completed interview YAML.

## Step 3 — Install Layer 1
Copy each `layer1-core/*` template into `T/docs/` (onboarding files into
`T/docs/`), removing the `.template` segment. Replace every `{{PLACEHOLDER}}`
with values from Steps 1–2. Interview/context-resolved placeholders filled here include {{PROJECT_NAME}}, {{GOVERNANCE_OWNER}}, {{QUALITY_OWNER}}, {{RUNTIME_PATHS}}, {{AUTODOC_TARGETS}}, {{COVERAGE_THRESHOLD}}, {{LINT_CMD}}, {{STATIC_CMD}}, and onboarding setup/test/stack tokens.
Leave `{{DOMAIN_RULES}}`, `{{STACK_RULES}}`,
`{{DOMAIN_GATES}}`, `{{DOMAIN_GATE_NAMES}}`, `{{CI_COMMANDS}}` for Step 4.
On re-run, only (re)write placeholder regions; never overwrite a file's locally-added content — if a target file already exists, replace resolved-placeholder regions only, leaving project edits intact.
**Output:** `T/docs/` populated; only Layer-2 hooks remain.

## Step 4 — Generate Layer 2
Run `DERIVATION-GUIDE.md` Part C against the interview YAML. Fill the five
Layer-2 hooks in `GOLDEN-RULES.md` and `DEFINITION-OF-DONE.md`.
**Output:** `T/docs/GOLDEN-RULES.md` and `T/docs/DEFINITION-OF-DONE.md` contain zero `{{...}}` tokens. Then delete `T/.bootstrap-context.yml`.

## Step 5 — Seed the Registry
Create `T/docs/adr/001-adopt-methodology-kit.md` from `ADR.template`
(Status: ACTIVE, embeds the interview YAML and kit version). Add its row to
`CANONICAL-DECISION-REGISTRY.md` and `ADR-README.md`. On re-run, update the existing row in both `CANONICAL-DECISION-REGISTRY.md` and `ADR-README.md` instead of adding a new one.
**Output:** ADR-001 registered.

## Step 6 — Wire Enforcement
Generate `T/.github/PULL_REQUEST_TEMPLATE.md` from the DoD checklist and an
example CI workflow `T/.github/workflows/quality-gate.yml` from
`{{CI_COMMANDS}}`. These are examples; the project owns activation.
**Output:** `T/.github/PULL_REQUEST_TEMPLATE.md` + `T/.github/workflows/quality-gate.yml` (present but inert until the project activates them).

## Step 7 — Classify Existing Docs
For every pre-existing doc in `T/docs/`, assign one authority class and add
it to `DOCUMENTATION-INDEX.md`. Conflicting/older docs are downgraded to
`SUPERSEDED`/`ARCHIVED` (status header edited) — never deleted.
On re-run, update the existing index row instead of adding a new one.
**Output:** every doc classified and indexed.

## Step 8 — Verify
Run the verification checklist below; report pass/fail per item.

### Verification Checklist
- [ ] No `{{` token remains in `T/docs/`
- [ ] No `(?i)laravel|php|blade|rtl|cairo|tenant|invoice` inside any file
      that originated from `layer1-core/` (abstraction preserved upstream;
      Layer-2 generated content may legitimately contain stack nouns)
- [ ] `CANONICAL-DECISION-REGISTRY.md` contains ADR-001
- [ ] Every doc in `T/docs/` carries one of the 6 status values
- [ ] Each interview `yes` maps to a Golden Rule + DoD gate (per Rule Library)
- [ ] PR template + example CI exist and reference the DoD
- [ ] Golden test: tracing the interview YAML in `reference-example/BUILDING-APP-CASE-STUDY.md` through `DERIVATION-GUIDE.md` Part B/C reproduces that file's stated rule set (all 32 rows trigger true)
