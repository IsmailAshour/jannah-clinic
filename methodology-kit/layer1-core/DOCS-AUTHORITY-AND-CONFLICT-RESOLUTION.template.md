# {{PROJECT_NAME}} — Documentation Authority and Conflict Resolution

> Status: ACTIVE-CANONICAL
> Scope: docs-governance
> Owner: {{GOVERNANCE_OWNER}}
> Decision Authority: Yes
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md

## 1) Principle

Documentation matters, but not all documents carry equal authority. A large
project accumulates many files — plans, analyses, historical records, AI
outputs — and treating them as interchangeable creates conflicting guidance
and architectural drift.

The governing rule is: **only explicitly registered canonical decision
documents may drive architecture.** Every other document is supporting,
explanatory, analytical, historical, or archival in nature. It may inform
and illustrate, but it cannot override or replace a registered canonical
decision.

## 2) Authority Ladder

Level 0 — Runtime truth: {{RUNTIME_PATHS}}
Level 1 — Canonical decisions: ADRs that are Accepted/Active, not superseded,
          and listed in the Canonical Decision Registry
Level 2 — Canonical implementation specs: a limited set, each listed in the registry with status ACTIVE-IMPLEMENTATION-SUPPORT
Level 3 — Advisory (plans, analysis, reports, logs, reviews, AI output)
Level 4 — Archive / history (reference only)

## 3) Mandatory Registry

Every project adopting this methodology must maintain a single registry file
at `docs/CANONICAL-DECISION-REGISTRY.md`. That file is the authoritative
source of record for all decision-authority documents. It must contain, for
each entry: decision, file path, status, owner, scope, supersedes, and
superseded by.

The rule: if a file is not listed in the registry as an ACTIVE-CANONICAL document, it has no authority to drive a decision, regardless of how it is titled or where it lives.

## 4) Status Vocabulary (mandatory, exhaustive)

ACTIVE-CANONICAL · ACTIVE-IMPLEMENTATION-SUPPORT · ADVISORY · SUPERSEDED ·
ARCHIVED · DRAFT
Prohibited: "Accepted" without active/superseded, "Final" without owner,
"Current" without registry reference.

## 5) Header Contract for Authoritative Docs

```md
Status: ACTIVE-CANONICAL
Scope: <scope>
Owner: <owner>
Decision Authority: Yes|No
Supersedes: ...
Superseded By: ...
Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
```

## 6) Hard Governance Rules

Rule 1: Files under prompt/instruction/agent/archive directories MUST NOT
        impose architecture direction.
Rule 2: An analytical file MUST NOT implicitly become a source of truth.
Rule 3: No two ACTIVE-CANONICAL docs may conflict within the same scope. An ACTIVE-IMPLEMENTATION-SUPPORT doc must not contradict an ACTIVE-CANONICAL doc in the same scope; ACTIVE-CANONICAL always takes precedence.
Rule 4: Any PR adding/changing a decision-authority doc MUST update the
        registry, list affected files, and state what becomes superseded.
Rule 5: If runtime conflicts with docs, diagnose from runtime, then correct
        docs — never the reverse blindly.

## 7) Review Gates

Gate A — Architecture: any change to architecture/structure/patterns must
         bind to exactly one ACTIVE-CANONICAL doc.
Gate B — Conflict: does another active doc say the opposite? Was its
         authority downgraded?
Gate C — Documentation: every new doc must be classified immediately using the §4 status vocabulary: ACTIVE-CANONICAL, ACTIVE-IMPLEMENTATION-SUPPORT, ADVISORY, or ARCHIVED.
