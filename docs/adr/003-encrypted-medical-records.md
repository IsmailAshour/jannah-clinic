# ADR-003: Encrypted Medical Records + Audit Log (Strict Posture)

> Status: ACCEPTED
> Scope: security
> Owner: Product
> Decision Authority: Yes
> Date: 2026-05-20
> Supersedes: ADR-002 (Basic Security Posture for MVP)
> Superseded By: NONE
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md

## Context
ADR-002 deliberately deferred at-rest encryption and medical-record audit logging
for MVP speed, and made it a hard rule that real patient data MUST NOT reach
production while that posture stood. With the P3 — Medical Records feature
landing (see `docs/superpowers/specs/2026-05-20-jannahclinic-p3-medical-records-design.md`),
the application now needs to store clinical free-text (diagnosis, prescriptions,
chronic conditions, allergies, internal staff notes) for real patients. That
material cannot ship under ADR-002.

## Decision
Adopt a Strict security posture for PHI free-text fields:

1. **At-rest encryption** via Laravel's `encrypted` Eloquent cast, keyed by
   `APP_KEY`. Applied to:
   - `medical_entries.visible_summary`, `medical_entries.staff_notes`
   - `prescriptions.medication_name`, `dosage`, `frequency`, `duration`, `notes`
   - `customer_profiles.notes`, `chronic_conditions`, `allergies`

2. **Append-only audit log** (`medical_audit_logs`) capturing CREATE / UPDATE /
   VIEW events on every PHI surface, with actor user, IP, user-agent, the
   patient identifier, and the *names* (never the values) of changed fields.
   Write events live inside the same DB transaction as the entity change so
   audit failure aborts the write. The model rejects post-create updates and
   all deletes.

3. **Authorization** unchanged at the role layer (Manager / Doctor /
   Receptionist / Customer) but Receptionists are explicitly excluded from
   every PHI surface.

## Alternatives Considered
- **Continue with ADR-002 + run anyway** — rejected; explicit ADR-002 hard rule.
- **Disk-level / TDE encryption only** — does not protect against a leaked DB
  snapshot or an exfiltrated read replica; column encryption gives a stronger
  guarantee at near-zero ergonomic cost.
- **End-to-end encryption (client-side keys)** — overkill for a single-clinic
  product; would block staff search and prescribing workflows.

## Consequences

**Unlocked:**
- Production may now carry real patient data, subject to this ADR remaining
  `ACCEPTED`/`ACTIVE` and listed in the canonical registry.

**Accepted trade-offs:**
- Encrypted columns are not partial-match searchable. If/when search is
  required, add per-field blind indexes.
- Application-level breach (attacker with running app + `APP_KEY`) can still
  decrypt PHI. Mitigation rests on authz + audit visibility; out-of-scope
  hardening (key rotation, HSM-backed keys) is deferred.

**Operational obligations:**
- `APP_KEY` rotation: quarterly, via `docs/runbooks/app-key-rotation.md`.
  The runbook uses Laravel's `APP_PREVIOUS_KEYS` two-key window so the app
  stays up during re-encryption. A dedicated `medical:rotate-encryption`
  artisan command is deferred; the manual `tinker` loop in the runbook
  covers the current data volume.
- The append-only invariant is enforced both in code (`MedicalAuditLog`
  rejects `save` after `exists` and rejects `delete`) and by a CI grep gate
  that fails on any code path attempting `MedicalAuditLog::*->update()` or
  `->delete()`.
- Receptionist exclusion is enforced by route-level policy denials with
  feature-test coverage.

## Compliance
- Methodology-kit interview must be re-bootstrapped with `handles_pii: yes`
  and `audit_required: yes` so the corresponding D-class rules and gates are
  regenerated (kit owner action; tracked separately from P3 implementation).
- CHANGELOG entry on P3 merge cites this ADR.
- Tag `p3-medical-records` may not be applied until this ADR is `ACCEPTED`,
  ADR-002 has moved to `SUPERSEDED`, and both registry rows are updated.
