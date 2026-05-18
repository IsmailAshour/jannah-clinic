# Methodology Kit

A portable, technology-neutral governance system extracted from a mature
production project. Transplants decision authority, quality gates,
dual-layer engineering rules, and onboarding structure into any new project.

## Philosophy: Two Layers

- **Layer 1 — Core (`layer1-core/`)**: Domain-neutral governance. Never edited.
  Authority ladder, canonical decision registry, status vocabulary, ADR
  process, quality-gate framework, onboarding skeleton.
- **Layer 2 — Derived (`layer2-derivation/`)**: Project-specific rules
  generated at adoption time from a short interview against a parameterized
  rule library. Same answers always produce the same rules.

## How to Use

1. Copy this kit (or reference it) from the new project's root.
2. Open `00-BOOTSTRAP.md` and follow the 8 steps in order (a human or an
   AI agent can run it). It is idempotent — safe to re-run.
3. The bootstrap installs Layer 1 into the project's `docs/`, runs the
   derivation interview, generates Layer 2, seeds the registry with ADR-001,
   wires example CI + PR template, and classifies existing docs by authority.

## Anti-Rot Rule

`layer1-core/` MUST contain zero technology or domain references. Concrete
examples live only in `reference-example/`.

## Quick Start

New to the kit? See `QUICK-START.md` for the ~5-minute adoption path.
`00-BOOTSTRAP.md` remains the authoritative detailed runbook.

## Reference

`reference-example/BUILDING-APP-CASE-STUDY.md` shows the kit applied to the
project it was extracted from.

See `KIT-VERSION.md` for the kit's own version history.
