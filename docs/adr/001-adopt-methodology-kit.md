# ADR-001: Adopt Methodology Kit

> Status: ACTIVE
> Scope: governance
> Owner: Product
> Decision Authority: Yes
> Date: 2026-05-19
> Supersedes: NONE
> Superseded By: NONE
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md

## Context

jannahclinic is a new project requiring a consistent governance framework,
quality standards, and architectural decision process from the outset. The
methodology kit (v1.0.1) provides a portable, interview-driven governance layer
that generates project-specific Golden Rules and Definition of Done gates.

## Decision

Adopt Methodology Kit v1.0.1 as the governance foundation for jannahclinic.
The derivation interview answers below drive the generated Layer 2 rules.

### Interview YAML

```yaml
domain: { handles_money: yes, handles_pii: no, multi_tenant: no, immutable_records: no, audit_required: no, has_allocation_or_split: no, has_payments: yes, concurrent_financial_writes: no, tenant_key_name: n/a }
stack: { language: PHP, framework: "Laravel + Inertia", database: relational, test_runner: pest, linter: pint, static_analysis: larastan }
ui: { has_ui: yes, server_rendered_templates: no, i18n: no, bidi_rtl: yes, has_design_system: yes, has_forms: yes, produces_documents: no, client_screen_state: yes }
api: { public_api: no }
sends_external_comms: no
quality: { coverage_threshold: 60, compliance: none, autodoc_targets: ["docs/ARCHITECTURE.md","docs/DOMAIN-MODEL.md"] }
runtime_paths: ["app/**","resources/js/**","routes/**","tests/**"]
```

## Alternatives Considered

- No governance framework: rejected; without explicit rules and a DoD, quality
  and architectural consistency erode quickly on a team project.
- Bespoke governance: rejected; the methodology kit is already tested and
  provides a reproducible, interview-driven derivation process.

## Consequences

- All PRs must satisfy DEFINITION-OF-DONE.md gates.
- GOLDEN-RULES.md is authoritative and non-negotiable.
- Re-running the bootstrap on the same YAML is idempotent.

## Compliance

Enforced by the PR template (`.github/PULL_REQUEST_TEMPLATE.md`) and the
CI quality gate (`.github/workflows/quality-gate.yml`). Governance owner
reviews any proposed changes to this ADR.
