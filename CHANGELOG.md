# Changelog

All notable changes to jannahclinic are documented here. Per Definition of Done Q.9,
every PR adds an entry. Format: Keep a Changelog; project uses phase tags (P0–P5).

## [P0] Foundation — 2026-05-19
- Adopted methodology-kit v1.0.1 (governance, Golden Rules, Definition of Done, ADR-001/002).
- Laravel 13 + Inertia + Vue 3 + Tailwind v4 + shadcn-vue + PostgreSQL scaffold.
- building.app-derived design system (@theme clinic tokens, Cairo, RTL-first) + foundation component layer.
- Email-or-phone authentication, 4 roles, server-side admin/portal surface isolation.
- Two empty surfaces (AdminShell / ClientShell), unified Inertia error pages, customer avatar upload.
- Quality gate (Pint, Larastan L5, Pest, Vitest, RTL grep) wired into CI + PR template.
