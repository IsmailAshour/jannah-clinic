# Pull Request

## Summary
<!-- Describe what this PR does and why. -->

## Definition of Done Checklist

> Mark `[x]` when verified. `N/A` requires a one-line reason.
> Reviewers MUST NOT merge until all applicable gates pass.

### Gate Q — Code Quality, Testing & Traceability

- [ ] Q.1 Unit test for every logic unit touched
- [ ] Q.2 Integration test for every entry point touched
- [ ] Q.3 Coverage ≥ 60 for changed files
- [ ] Q.4 Linter passing: `./vendor/bin/pint --test`
- [ ] Q.5 Static analysis passing: `./vendor/bin/phpstan analyse`
- [ ] Q.6 No unused imports or dead code introduced by this PR
- [ ] Q.7 Single working language across code/comments/commits
- [ ] Q.8 Relevant docs updated (new model, endpoint, config key, env var, schema change, or CLI flag)
- [ ] Q.9 CHANGELOG entry added
- [ ] Q.10 Values config-driven; no magic numbers
- [ ] Q.11 SSoT respected — info defined in exactly one place

### Architecture Gates

- [ ] D01 All monetary amounts stored as DECIMAL or integer minor-units; no FLOAT/DOUBLE on money fields
- [ ] D05 Payment handler asserts: amount > 0, record in valid state, amount ≤ remaining balance
- [ ] S01 Schema migration includes required constraints, checks, and unique indexes for all new/changed invariants
- [ ] S02 No raw SQL with string-interpolated user input; all queries use parameterized bindings or query builder

### UI/UX Gates

- [ ] U01 Every new/changed data view handles loading, empty, error, and success states
- [ ] U02 No hardcoded colors, spacing, or type values; all values sourced from design-system tokens
- [ ] U04 Layout tested at all defined breakpoints; no dead space or floating forms

### i18n / Bidirectionality Gates

- [ ] U07 No physical CSS directional properties (`left`, `right`, `margin-left`, `padding-right`, etc.); logical properties only

## Notes
<!-- Any additional context, screenshots, or caveats. -->
