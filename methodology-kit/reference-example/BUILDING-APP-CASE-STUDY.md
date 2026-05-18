# Reference Example — building.app (extraction source)

This kit was extracted from building.app. This file is illustrative, not a
dependency: it links to the live project, it does not copy it. If the
project is absent, the kit is still fully functional.

## Interview Answers (used as the Golden Test)

```yaml
domain: { handles_money: yes, handles_pii: yes, multi_tenant: yes,
          immutable_records: yes, audit_required: yes,
          has_allocation_or_split: yes, has_payments: yes,
          concurrent_financial_writes: yes }
stack:  { language: PHP, framework: Laravel, database: relational,
          test_runner: "php artisan test", linter: "php artisan pint",
          static_analysis: Larastan }
ui:     { has_ui: yes, server_rendered_templates: yes, i18n: yes,
          bidi_rtl: yes, has_design_system: yes, has_forms: yes,
          produces_documents: yes, client_screen_state: yes }
api:    { public_api: yes }
sends_external_comms: yes
quality: { coverage_threshold: 70, compliance: [RFC7807, RFC9110, WCAG2.2AA, ISO27001] }
runtime_paths: ["app/**","routes/**","resources/views/**","tests/**"]
```

## Expected Generated Rule Set

All 32 library rows trigger true → Layer 2 reproduces the building.app rule
family (multi-tenancy, financial immutability, allocation balance, audit,
i18n parity, logical CSS, problem-details API, 4 UI states, etc.).

## Live Source Pointers (read, do not copy)

- Golden Rules: `building.app/docs/GOLDEN-RULES.md`
- Authority: `building.app/docs/governance/DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md`
- SSOT map: `building.app/docs/SSOT-AUTHORITY-MAP.md`
- Definition of Done: `building.app/docs/DEFINITION-OF-DONE.md`
- ADRs: `building.app/docs/adr/`

## Optional, Not Ported

building.app's "inspiration → implementation" UI pipeline
(`docs/inspiration/METHODOLOGY-FROM-INSPIRATION-TO-BLADE.md`) is highly
domain-specific and intentionally excluded from the kit. Mentioned here only
as a pointer for teams that want a similar UI-derivation discipline. This exclusion is intentional (YAGNI) — the kit stays portable without it; teams that want UI-derivation discipline can follow the pointer.
