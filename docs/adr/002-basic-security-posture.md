# ADR-002: Basic Security Posture for MVP

> Status: SUPERSEDED
> Scope: security
> Owner: Product
> Decision Authority: Yes
> Date: 2026-05-19
> Supersedes: NONE
> Superseded By: ADR-003 (docs/adr/003-encrypted-medical-records.md), 2026-05-20
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md

## Context
jannahclinic stores personal and medical data (diagnoses, prescriptions). For the
MVP the team deliberately chose a "Basic" security posture: authentication +
role-based authorization only, with `handles_pii: no` and `audit_required: no` in
the methodology-kit derivation interview — so field-level encryption and
medical-record access/modification audit logging are NOT generated as rules/gates.

## Decision
Ship the MVP with authentication + 4-role authorization and server-side surface
isolation, without medical-record audit logging or at-rest field encryption.

## Alternatives Considered
- Strict posture (encryption + full audit): rejected for MVP scope/speed; the
  kit interview would generate D09/D10-class rules that are out of MVP scope.
- Medium posture (audit on sensitive actions only): deferred to a later phase.

## Consequences
Faster MVP. Real patient data MUST NOT go to production under this posture.

## Compliance
Enforced by a blocking review gate: before any production deployment carrying
real patient data, this ADR MUST be superseded by an ADR that restores
`audit_required` and at-rest encryption for medical records, with the kit
re-bootstrapped to regenerate the corresponding rules and gates.
