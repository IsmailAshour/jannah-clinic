# jannahclinic — Start Here

Clinic management system built with Laravel + Inertia.

**Stack:** PHP · Laravel + Inertia · Relational DB · Pest · Pint · Larastan

## Choose Your Path

### Path 1 — Develop Locally
1. Read `docs/ARCHITECTURE.md` (when created)
2. `composer install && npm install`
3. `php artisan serve` → http://localhost:8000

### Path 2 — Understand the Architecture
1. Read `docs/adr/` (canonical decisions)
2. Read `docs/CANONICAL-DECISION-REGISTRY.md` (what governs)
3. Read `docs/GOLDEN-RULES.md`

### Path 3 — Ship a Change
1. Read `docs/DEFINITION-OF-DONE.md`
2. Run the applicable gates
3. `./vendor/bin/pest --coverage --min=60`

## Documentation Map
See `docs/DOCUMENTATION-INDEX.md` — every doc is classified by authority.

## Getting-Started Checklist
- [ ] `composer install && npm install`
- [ ] `./vendor/bin/pest --coverage --min=60` pass
- [ ] Read Golden Rules + Definition of Done
- [ ] Understand the authority ladder

## FAQ / Troubleshooting
- How do decisions get made? → A new ADR, registered in the registry.
- Which doc wins on conflict? → The authority ladder; runtime over docs. See `docs/DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md` §2–3.
