# {{PROJECT_NAME}} — Definition of Done (Canonical)

> Status: ACTIVE-CANONICAL
> Scope: quality
> Owner: {{QUALITY_OWNER}}
> Single Source of Truth for all "Done" criteria.

## How to Use
1. Every PR includes this checklist in its description (via the project's pull-request template, e.g. `.github/PULL_REQUEST_TEMPLATE.md`).
2. Mark `[x]` when verified; `N/A` requires a one-line reason.
3. Reviewers MUST NOT merge until all applicable gates pass.
4. CI enforces machine-checkable gates automatically.

## Gate Q — Code Quality, Testing & Traceability (neutral, always applies)

| # | Check |
|---|-------|
| Q.1 | Unit test for every logic unit touched |
| Q.2 | Integration test for every entry point touched |
| Q.3 | Coverage ≥ {{COVERAGE_THRESHOLD}} for changed files |
| Q.4 | Linter passing: `{{LINT_CMD}}` |
| Q.5 | Static analysis passing: `{{STATIC_CMD}}` |
| Q.6 | No unused imports or dead code introduced by this PR |
| Q.7 | Single working language across code/comments/commits |
| Q.8 | Relevant docs updated (new model, endpoint, config key, env var, schema change, or CLI flag) |
| Q.9 | CHANGELOG entry added |
| Q.10 | Values config-driven; no magic numbers |
| Q.11 | SSoT respected — info defined in exactly one place |

## Domain & Stack Gates (Layer 2 — generated)

{{DOMAIN_GATES}}

## Applicability Matrix

| PR Type | Gate Q | {{DOMAIN_GATE_NAMES}} |
|---------|--------|------------------------|
| New Feature | ✅ All | ✅ All applicable |
| Bug Fix | ✅ All | ✅ Affected |
| Refactor | ✅ All | ✅ Affected |
| Docs-Only | Q.7–Q.9, Q.11 | ❌ |
| Migration/Infra | Q.1,Q.4,Q.5,Q.8,Q.9 | ✅ Affected |

## CI Enforcement

| Check | Command | Hard Fail? |
|-------|---------|------------|
{{CI_COMMANDS}}
