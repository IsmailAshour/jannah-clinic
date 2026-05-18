# Quick Start

Adopt the methodology kit in a new or existing project in ~5 minutes. This is
the condensed path; `00-BOOTSTRAP.md` is the authoritative, detailed runbook.

## Prerequisites

- A target project directory `T` (new or existing).
- This kit available locally (copied into `T/methodology-kit/` or referenced
  from a sibling path). If you reference it from a sibling path instead of copying it in, adjust the path in the Mode A prompt accordingly.

## 60-Second Path

1. Make the kit reachable from `T` (copy it in, or note its path).
2. Run the bootstrap. Choose one mode below.
3. Confirm the Step 8 verification checklist passes.

### Mode A — AI agent (recommended)

Open the project with an AI coding agent and give it this instruction:

> Follow `methodology-kit/00-BOOTSTRAP.md` exactly, in order, for this
> project. Ask me only the interview questions you cannot infer from the
> codebase. Stop and report the Step 8 verification result.

The agent runs Steps 1–8: detect context → interview → install Layer 1 →
generate Layer 2 → seed the registry (ADR-001) → wire enforcement → classify
existing docs → verify.

### Mode B — Human

Work through `00-BOOTSTRAP.md` Steps 1–8 yourself:

1. Inventory the stack, runtime paths, and existing docs (record what is inferred vs. unknown).
2. Fill the interview YAML from `layer2-derivation/DERIVATION-GUIDE.md` Part A.
3. Copy `layer1-core/*` into `T/docs/` (drop `.template`) and fill the
   interview/context placeholders.
4. Generate Layer 2 by running `DERIVATION-GUIDE.md` Part C against the
   interview answers (fills the five Layer-2 hooks).
5. Create `T/docs/adr/001-adopt-methodology-kit.md`; register it.
6. Generate the PR template + example CI (example only — the project activates it).
7. Classify any pre-existing docs by authority.
8. Run the verification checklist.

## What "Done" Looks Like

- `T/docs/` has GOLDEN-RULES, DEFINITION-OF-DONE, the authority + registry
  docs, ADR-001, START-HERE, DOCUMENTATION-INDEX — with no `{{...}}` tokens
  left.
- Each interview `yes` produced a matching rule + gate.
- The Step 8 checklist passes (including the golden test).

## Tiny Example

Interview excerpt:

```yaml
domain: { handles_money: yes, multi_tenant: yes }
ui: { has_ui: yes, i18n: yes }
```

Generates (per the rule library): money-as-exact-decimal, tenant-scoped
queries + server-side scope check, 4-state data views, translation parity —
each as a Golden Rule plus its Definition-of-Done gate.

See `reference-example/BUILDING-APP-CASE-STUDY.md` for a full worked example
(all 32 rules) that doubles as the kit's golden test.

## Re-running

The bootstrap is idempotent — re-run it after a scope change or kit upgrade;
it updates values and never duplicates registry rows or ADRs.

## More

- `README.md` — what the kit is and its dual-layer philosophy.
- `00-BOOTSTRAP.md` — the authoritative 8-step runbook (source of truth).
- `layer2-derivation/DERIVATION-GUIDE.md` — interview + rule library.
