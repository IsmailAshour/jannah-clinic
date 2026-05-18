# jannahclinic — Definition of Done (Canonical)

> Status: ACTIVE-CANONICAL
> Scope: quality
> Owner: Product
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
| Q.3 | Coverage ≥ 60 for changed files |
| Q.4 | Linter passing: `./vendor/bin/pint --test` |
| Q.5 | Static analysis passing: `./vendor/bin/phpstan analyse` |
| Q.6 | No unused imports or dead code introduced by this PR |
| Q.7 | Single working language across code/comments/commits |
| Q.8 | Relevant docs updated (new model, endpoint, config key, env var, schema change, or CLI flag) |
| Q.9 | CHANGELOG entry added |
| Q.10 | Values config-driven; no magic numbers |
| Q.11 | SSoT respected — info defined in exactly one place |

## Domain & Stack Gates (Layer 2 — generated)

### Architecture

| # | Check |
|---|-------|
| D01 | All monetary amounts stored as DECIMAL or integer minor-units; no FLOAT/DOUBLE on money fields |
| S01 | Schema migration includes required constraints, checks, and unique indexes for all new/changed invariants |
| S02 | No raw SQL with string-interpolated user input; all queries use parameterized bindings or query builder |

### UI/UX

| # | Check |
|---|-------|
| U01 | Every new/changed data view handles loading, empty, error, and success states |
| U02 | No hardcoded colors, spacing, or type values; all values sourced from design-system tokens |

### i18n / Bidirectionality

| # | Check |
|---|-------|
| U07 | No physical CSS directional properties (`left`, `right`, `margin-left`, `padding-right`, etc.); logical properties only |

## Applicability Matrix

| PR Type | Gate Q | Architecture | UI/UX | i18n |
|---------|--------|--------------|-------|------|
| New Feature | ✅ All | ✅ All applicable | ✅ All applicable | ✅ All applicable |
| Bug Fix | ✅ All | ✅ Affected | ✅ Affected | ✅ Affected |
| Refactor | ✅ All | ✅ Affected | ✅ Affected | ✅ Affected |
| Docs-Only | Q.7–Q.9, Q.11 | ❌ | ❌ | ❌ |
| Migration/Infra | Q.1,Q.4,Q.5,Q.8,Q.9 | ✅ Affected | ❌ | ❌ |

## CI Enforcement

| Check | Command | Hard Fail? |
|-------|---------|------------|
| Linter | `./vendor/bin/pint --test` | Yes |
| Static Analysis | `./vendor/bin/phpstan analyse` | Yes |
| Tests | `./vendor/bin/pest --coverage --min=60` | Yes |
| Money float check | `grep -rEn 'decimal|float|double' app database \| grep -i 'price\|amount\|fee\|total'` | Yes |
| Logical CSS check | `grep -rn --include="*.{css,vue,jsx,tsx}" -E "(margin-left\|margin-right\|padding-left\|padding-right\|text-align:\s*(left\|right)\|float:\s*(left\|right))" resources/js/**` | Yes |
