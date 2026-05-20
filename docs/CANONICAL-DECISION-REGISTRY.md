# jannahclinic — Canonical Decision Registry

> Status: ACTIVE-CANONICAL
> Scope: docs-governance
> Owner: Product
> Decision Authority: Yes

Only documents listed here as `ACTIVE-CANONICAL` or
`ACTIVE-IMPLEMENTATION-SUPPORT` may govern decisions. See
`DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md`.

The registry file itself is the root of the reference graph and is therefore exempt from the `Canonical Registry Ref` header field defined in the authority document.

| Decision | File Path | Status | Owner | Scope | Supersedes | Superseded By |
|----------|-----------|--------|-------|-------|------------|---------------|
| Adopt Methodology Kit v1.0.1 | docs/adr/001-adopt-methodology-kit.md | ACTIVE-CANONICAL | Product | governance | — | — |
| Basic Security Posture for MVP | docs/adr/002-basic-security-posture.md | SUPERSEDED | Product | security | — | ADR-003 |
| Encrypted Medical Records + Audit Log | docs/adr/003-encrypted-medical-records.md | ACTIVE-CANONICAL | Product | security | ADR-002 | — |
| P0 Architecture Reference | docs/ARCHITECTURE.md | ACTIVE-IMPLEMENTATION-SUPPORT | Engineering | architecture | — | — |
| P0 Domain Model Reference | docs/DOMAIN-MODEL.md | ACTIVE-IMPLEMENTATION-SUPPORT | Engineering | domain | — | — |
