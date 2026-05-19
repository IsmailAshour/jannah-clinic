# jannahclinic P0 (Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the `jannahclinic` greenfield repo: governed by the methodology kit, running on Laravel 13 + Inertia + Vue 3 + shadcn-vue + Postgres, with a building.app-derived design system, email-or-phone auth + 4 roles, and two empty surfaces (admin control panel + customer portal) — the inheritable foundation for phases P1–P5.

**Architecture:** Laravel domain-service architecture (no logic in controllers/views), Inertia bridging Laravel ↔ Vue 3 SPA, shadcn-vue as the base UI primitive layer, a thin `foundation/` Vue component layer re-expressing building.app's design patterns (tokens, RTL-first, 4-states, tables, forms, overlays). Two route files + role middleware isolate the admin and portal surfaces.

**Tech Stack:** PHP 8.4, Laravel 13, Laravel Breeze (Inertia+Vue 3 starter), Postgres, Inertia.js, Vue 3, Vite, Tailwind CSS, shadcn-vue (reka-ui), Pest (PHP tests), Pint, Larastan.

**Project root:** `C:\~projects\jannahclinic` (already created, `git init` done, holds `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md`). Windows; use PowerShell. All paths below are absolute or relative to this root.

**Spec:** `docs/superpowers/specs/2026-05-19-jannahclinic-p0-foundation-design.md` (read it; the interview YAML in §3.1 is the source of truth for the kit bootstrap).

**Environment dependencies (verify before Task 2):** `php -v` (8.4), `composer -V`, `node -v` (≥20), `npm -v`, and a running Postgres with a superuser. If Postgres is unavailable, STOP and report — do not silently fall back to SQLite (the spec mandates Postgres; the kit interview says `database: relational` → Postgres).

**Git note:** `jannahclinic` is its own git repo. Commit per task. End commit messages with the Co-Authored-By trailer:
```
Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```

---

## File Structure (decomposition)

| Path | Responsibility |
|------|----------------|
| `methodology-kit/` | Copied kit v1.0.1 (self-contained governance source) |
| `docs/` | Kit-installed governance (GOLDEN-RULES.md, DEFINITION-OF-DONE.md, adr/, registry, onboarding) |
| `docs/adr/001-adopt-methodology-kit.md`, `002-basic-security-posture.md` | Canonical decisions |
| `app/Domain/Auth/Services/AuthService.php` | Account creation + email-or-phone credential resolution |
| `app/Enums/UserRole.php` | The 4 roles enum |
| `app/Models/User.php`, `app/Models/CustomerProfile.php` | Identity + customer metadata |
| `app/Http/Middleware/EnsureUserHasRole.php` | Surface isolation by role |
| `routes/auth.php`, `routes/admin.php`, `routes/portal.php` | Separated route surfaces |
| `database/migrations/*` | users(role,phone), customer_profiles |
| `resources/css/app.css` | Design tokens (CSS vars) + Tailwind theme + Cairo + RTL base |
| `resources/js/Components/foundation/*` | building.app-derived layer (PageStates, DataTable, FormGroup/Section/Actions, Modal, Drawer, ConfirmModal, Badge, StatCard, EmptyState, Skeleton, ErrorState, PageHeader) |
| `resources/js/Layouts/AdminShell.vue`, `ClientShell.vue` | The two surfaces' chrome |
| `resources/js/Pages/Admin/Dashboard.vue`, `Pages/Portal/Home.vue`, `Pages/Auth/*`, `Pages/Errors/*` | Empty/placeholder pages + error pages |
| `tests/Feature/Auth/*`, `tests/Unit/Domain/Auth/*` | Pest tests |
| `.github/PULL_REQUEST_TEMPLATE.md`, `.github/workflows/quality-gate.yml` | DoD enforcement (kit-generated) |

---

## Task 1: Copy kit + run methodology bootstrap + record ADRs

**Files:**
- Create: `C:\~projects\jannahclinic\methodology-kit\` (copy)
- Create (by bootstrap): `docs/GOLDEN-RULES.md`, `docs/DEFINITION-OF-DONE.md`, `docs/DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md`, `docs/CANONICAL-DECISION-REGISTRY.md`, `docs/adr/001-adopt-methodology-kit.md`, `docs/adr/README.md`, `docs/START-HERE.md`, `docs/DOCUMENTATION-INDEX.md`
- Create: `docs/adr/002-basic-security-posture.md`

- [ ] **Step 1: Copy the kit into the project**

```powershell
Copy-Item -Recurse -Force "C:\~projects\methodology-kit" "C:\~projects\jannahclinic\methodology-kit"
Remove-Item -Recurse -Force "C:\~projects\jannahclinic\methodology-kit\.git" -ErrorAction SilentlyContinue
Test-Path "C:\~projects\jannahclinic\methodology-kit\00-BOOTSTRAP.md"
```
Expected: `True`.

- [ ] **Step 2: Execute the bootstrap runbook (Steps 1–7) with the spec's interview answers**

Open `C:\~projects\jannahclinic\methodology-kit\00-BOOTSTRAP.md` and follow Steps 1–7 in order, for target project `T = C:\~projects\jannahclinic`. Use the interview YAML EXACTLY as written in the spec §3.1 (do not re-interview; it is decided):

```yaml
domain: { handles_money: yes, handles_pii: no, multi_tenant: no, immutable_records: no, audit_required: no, has_allocation_or_split: no, has_payments: yes, concurrent_financial_writes: no, tenant_key_name: n/a }
stack: { language: PHP, framework: "Laravel + Inertia", database: relational, test_runner: pest, linter: pint, static_analysis: larastan }
ui: { has_ui: yes, server_rendered_templates: no, i18n: no, bidi_rtl: yes, has_design_system: yes, has_forms: yes, produces_documents: no, client_screen_state: yes }
api: { public_api: no }
sends_external_comms: no
quality: { coverage_threshold: 60, compliance: none, autodoc_targets: ["docs/ARCHITECTURE.md","docs/DOMAIN-MODEL.md"] }
runtime_paths: ["app/**","resources/js/**","routes/**","tests/**"]
```

Concretely this produces, under `C:\~projects\jannahclinic\docs\`: `GOLDEN-RULES.md` (neutral R0–R8 spine + generated domain/stack rules for: money-as-decimal, payment-validation, relational DB constraints, no-unsanitized-queries, mass-assignment protection, errors-don't-leak, 4-UI-states, design-system-tokens, premium-consistency, responsive-no-dead-space, logical-RTL-properties, forms-auto-populate, client-prefs-local-only, overlays-at-root), `DEFINITION-OF-DONE.md` (Gate Q + generated domain/stack gates), `DOCS-AUTHORITY-AND-CONFLICT-RESOLUTION.md`, `CANONICAL-DECISION-REGISTRY.md`, `adr/001-adopt-methodology-kit.md` (Status ACTIVE, embeds the YAML + kit version 1.0.1), `adr/README.md`, `START-HERE.md`, `DOCUMENTATION-INDEX.md`, and `.github/PULL_REQUEST_TEMPLATE.md` + `.github/workflows/quality-gate.yml` (example, inert until activated). Delete `T/.bootstrap-context.yml` at end of bootstrap Step 4 as the runbook instructs.

- [ ] **Step 3: Author ADR-002 (the documented security trade-off)**

Create `C:\~projects\jannahclinic\docs\adr\002-basic-security-posture.md` from `methodology-kit/layer1-core/ADR.template.md`, filled exactly:

```markdown
# ADR-002: Basic Security Posture for MVP

> Status: ACTIVE
> Scope: security
> Owner: Product
> Decision Authority: Yes
> Date: 2026-05-19
> Supersedes: NONE
> Superseded By: NONE
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
```

Add its row to `docs/CANONICAL-DECISION-REGISTRY.md` and `docs/adr/README.md` (mirror the ADR-001 row format already present).

- [ ] **Step 4: Verify bootstrap Step 8 checklist**

Run the verification checklist in `methodology-kit/00-BOOTSTRAP.md` Step 8:
```powershell
Select-String -Path "C:\~projects\jannahclinic\docs\*.md","C:\~projects\jannahclinic\docs\adr\*.md" -Pattern "\{\{[A-Z0-9_-]+\}\}"
Test-Path "C:\~projects\jannahclinic\docs\GOLDEN-RULES.md","C:\~projects\jannahclinic\docs\DEFINITION-OF-DONE.md","C:\~projects\jannahclinic\docs\adr\001-adopt-methodology-kit.md","C:\~projects\jannahclinic\docs\adr\002-basic-security-posture.md"
Select-String -Path "C:\~projects\jannahclinic\docs\CANONICAL-DECISION-REGISTRY.md" -Pattern "001-adopt-methodology-kit|002-basic-security-posture"
```
Expected: first command returns NOTHING (no unresolved `{{...}}` tokens in installed docs); all `Test-Path` results `True`; registry shows both ADR rows.

- [ ] **Step 5: Commit**

```powershell
cd C:\~projects\jannahclinic
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "chore: adopt methodology-kit v1.0.1 (governance, DoD, ADR-001/002)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Scaffold Laravel + Breeze (Inertia + Vue 3) + Postgres

**Files:** generated by scaffolding into the repo root (Laravel skeleton, `composer.json`, `package.json`, `resources/js/app.js`, etc.)

- [ ] **Step 1: Create the Laravel app into the existing repo**

Laravel's installer refuses a non-empty dir; create in a temp dir then move app files in (preserving `docs/`, `methodology-kit/`, `.git`):
```powershell
cd C:\~projects
composer create-project laravel/laravel _jc_tmp
robocopy "C:\~projects\_jc_tmp" "C:\~projects\jannahclinic" /E /XF .gitignore /NFL /NDL /NJH /NJS
Remove-Item -Recurse -Force "C:\~projects\_jc_tmp"
cd C:\~projects\jannahclinic
php artisan --version
```
Expected: `Laravel Framework 12.x`.

- [ ] **Step 2: Append Laravel's standard ignores to the existing .gitignore**

Append (do not overwrite the kit `.gitignore` lines already present):
```
/vendor
/node_modules
/public/build
/public/hot
/storage/*.key
.env
.phpunit.result.cache
```

- [ ] **Step 3: Configure Postgres in `.env`**

Create the database and set env:
```powershell
psql -U postgres -c "CREATE DATABASE jannahclinic;"
```
In `C:\~projects\jannahclinic\.env` set:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jannahclinic
DB_USERNAME=postgres
DB_PASSWORD=postgres
APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
```
(Adjust `DB_PASSWORD` to the local Postgres superuser password; if it differs, that is the only value to change.)

- [ ] **Step 4: Install Breeze with the Inertia + Vue stack**

```powershell
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install
npm run build
php artisan migrate
```
Expected: `breeze:install vue` completes; `npm run build` succeeds; `migrate` creates the default tables in Postgres (users, sessions, cache, jobs) with no error.

- [ ] **Step 5: Smoke-run and verify**

```powershell
php artisan test
```
Expected: Breeze's bundled tests PASS (auth scaffolding tests green) — confirms Inertia+Vue+Postgres wiring works.

- [ ] **Step 6: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat: scaffold Laravel 13 + Breeze (Inertia+Vue3) on Postgres

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Install + configure quality tooling (Pest, Pint, Larastan) and wire the DoD gate

**Files:**
- Create: `phpstan.neon`, `pint.json`
- Modify: `composer.json` (dev deps + scripts)
- Modify: `.github/workflows/quality-gate.yml` (activate the kit's example)

- [ ] **Step 1: Install Pest, Pint, Larastan**

```powershell
composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
php artisan pest:install
composer require laravel/pint --dev
composer require larastan/larastan --dev
```

- [ ] **Step 2: Create `pint.json`** (exact content)

```json
{
    "preset": "laravel"
}
```

- [ ] **Step 3: Create `phpstan.neon`** (exact content)

```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths:
        - app
    level: 5
```

- [ ] **Step 4: Add composer scripts** — in `composer.json` `"scripts"` add:

```json
"quality": [
    "./vendor/bin/pint --test",
    "./vendor/bin/phpstan analyse --no-progress",
    "@php artisan test"
]
```

- [ ] **Step 5: Activate the kit's quality gate workflow**

Replace `C:\~projects\jannahclinic\.github\workflows\quality-gate.yml` with a runnable workflow derived from `docs/DEFINITION-OF-DONE.md` CI section:

```yaml
name: quality-gate
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env: { POSTGRES_PASSWORD: postgres, POSTGRES_DB: jannahclinic }
        ports: ["5432:5432"]
        options: >-
          --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4' }
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: composer install --no-interaction --prefer-dist
      - run: npm ci && npm run build
      - run: cp .env.example .env && php artisan key:generate
      - run: ./vendor/bin/pint --test
      - run: ./vendor/bin/phpstan analyse --no-progress
      - name: RTL logical-properties check (kit rule)
        run: "! grep -rEn 'class=\"[^\"]*\\b(pl-|pr-|ml-|mr-)' resources/js || (echo 'Physical RTL props found' && exit 1)"
      - run: php artisan test
        env: { DB_CONNECTION: pgsql, DB_HOST: 127.0.0.1, DB_USERNAME: postgres, DB_PASSWORD: postgres, DB_DATABASE: jannahclinic }
```

- [ ] **Step 6: Verify the gate runs locally**

```powershell
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
php artisan test
```
Expected: Pint clean (or auto-fixable — run `./vendor/bin/pint` then re-check), PHPStan level 5 passes on the fresh skeleton, tests green.

- [ ] **Step 7: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "chore: wire Pest+Pint+Larastan and activate DoD quality gate

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Design tokens + Cairo font + RTL-first base

**Files:**
- Modify: `resources/css/app.css`
- Modify: `tailwind.config.js`
- Modify: `resources/views/app.blade.php` (the Inertia root template)
- Create: `resources/fonts/Cairo.woff2` (downloaded)

- [ ] **Step 1: Download the Cairo font**

```powershell
New-Item -ItemType Directory -Force resources\fonts
Invoke-WebRequest -Uri "https://github.com/google/fonts/raw/main/ofl/cairo/Cairo%5Bslnt%2Cwght%5D.ttf" -OutFile "resources\fonts\Cairo.ttf"
Test-Path resources\fonts\Cairo.ttf
```
Expected: `True`. (Variable TTF is acceptable; Vite will serve it. If the URL fails, download Cairo from fonts.google.com and place at `resources/fonts/Cairo.ttf`.)

- [ ] **Step 2: Set the Inertia root template to RTL Arabic** — in `resources/views/app.blade.php` change the opening `<html>` tag to:

```blade
<html lang="ar" dir="rtl" class="h-full">
```

- [ ] **Step 3: Replace `resources/css/app.css`** with tokens + Cairo + Tailwind (exact content)

```css
@import 'tailwindcss';

@font-face {
  font-family: 'Cairo';
  src: url('/resources/fonts/Cairo.ttf') format('truetype');
  font-weight: 200 1000;
  font-display: swap;
}

@theme {
  --font-sans: 'Cairo', ui-sans-serif, system-ui, sans-serif;

  /* Clinic-semantic palette (re-expressed from building.app DNA, NOT building/financial) */
  --color-brand: #0B4F2F;          /* clinic green */
  --color-brand-hover: #094327;
  --color-brand-active: #073521;
  --color-accent: #C9A227;         /* gold accent */
  --color-surface-page: #f6f7f5;
  --color-surface-card: #ffffff;
  --color-surface-sunken: #eceee9;
  --color-text-primary: #111827;
  --color-text-secondary: #475569;
  --color-text-tertiary: #64748b;
  --color-border-default: rgb(0 0 0 / 0.06);
  --color-border-strong: rgb(0 0 0 / 0.10);
  --color-success: #059669;
  --color-warning: #d97706;
  --color-danger: #dc2626;
  --color-info: #0284c7;
  --color-amount: #0B4F2F;         /* single neutral financial role for invoices */

  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;

  --shadow-xs: 0 1px 2px rgb(0 0 0 / 0.04);
  --shadow-sm: 0 1px 3px rgb(0 0 0 / 0.08);
  --shadow-md: 0 4px 12px rgb(0 0 0 / 0.10);
  --shadow-lg: 0 12px 32px rgb(0 0 0 / 0.14);

  --duration-fast: 100ms;
  --duration-normal: 200ms;
  --duration-slow: 300ms;
}

:root { color-scheme: light; }
html, body { height: 100%; }
body {
  font-family: var(--font-sans);
  background: var(--color-surface-page);
  color: var(--color-text-primary);
}

/* z-index scale (kit overlay rule: overlays render at document root) */
.z-dropdown { z-index: 10; }
.z-sticky   { z-index: 20; }
.z-shell    { z-index: 30; }
.z-overlay  { z-index: 40; }
.z-modal    { z-index: 50; }
.z-toast    { z-index: 60; }
```

- [ ] **Step 4: Ensure Tailwind scans Vue files** — confirm `tailwind.config.js` `content` includes `./resources/js/**/*.vue`. If absent, set:

```js
export default {
  content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{js,vue}'],
  theme: { extend: {} },
  plugins: [],
}
```

- [ ] **Step 5: Build and verify**

```powershell
npm run build
```
Expected: build succeeds; no CSS errors. (Visual check deferred to Task 8 smoke run.)

- [ ] **Step 6: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(ui): design tokens, Cairo font, RTL-first base

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Initialize shadcn-vue + add base primitives

**Files:**
- Create: `components.json`, `resources/js/lib/utils.ts`, `resources/js/Components/ui/*` (generated)
- Modify: `vite.config.js`, `jsconfig.json`/`tsconfig.json` (path alias `@`)

- [ ] **Step 1: Add the `@` path alias for Vite** — in `vite.config.js`, add to the config `resolve`:

```js
import { fileURLToPath, URL } from 'node:url'
// inside defineConfig({ ... }):
resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
```

- [ ] **Step 2: Initialize shadcn-vue**

```powershell
npx shadcn-vue@latest init
```
Answer prompts: style = default; base color = slate; CSS file = `resources/css/app.css`; Tailwind config = `tailwind.config.js`; components alias = `@/Components/ui`; utils alias = `@/lib/utils`; framework = Vite. Expected: `components.json` created, `resources/js/lib/utils.ts` created.

- [ ] **Step 3: Add the primitives the foundation layer needs**

```powershell
npx shadcn-vue@latest add button input label dialog sheet badge table skeleton dropdown-menu sonner
```
Expected: components appear under `resources/js/Components/ui/`. (`sheet` = drawer primitive; `sonner` = toast; `dialog` = modal.)

- [ ] **Step 4: Verify build still green**

```powershell
npm run build
```
Expected: success.

- [ ] **Step 5: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(ui): init shadcn-vue + base primitives

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Foundation component layer (building.app-derived)

**Files (Create):**
- `resources/js/Components/foundation/PageStates.vue`
- `resources/js/Components/foundation/PageHeader.vue`
- `resources/js/Components/foundation/DataTable.vue`
- `resources/js/Components/foundation/FormGroup.vue`
- `resources/js/Components/foundation/FormSection.vue`
- `resources/js/Components/foundation/FormActions.vue`
- `resources/js/Components/foundation/Modal.vue`
- `resources/js/Components/foundation/Drawer.vue`
- `resources/js/Components/foundation/ConfirmModal.vue`
- `resources/js/Components/foundation/StatCard.vue`
- `resources/js/Components/foundation/EmptyState.vue`
- `resources/js/Components/foundation/ErrorState.vue`
- `resources/js/Components/foundation/index.js`
- Test: `resources/js/Components/foundation/__tests__/PageStates.spec.js`

- [ ] **Step 1: Install the Vue test runner**

```powershell
npm install -D vitest @vue/test-utils @vitejs/plugin-vue jsdom
```
Add to `package.json` `"scripts"`: `"test:js": "vitest run --environment jsdom"`.

- [ ] **Step 2: Write the failing test for `PageStates`**

Create `resources/js/Components/foundation/__tests__/PageStates.spec.js`:

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import PageStates from '../PageStates.vue'

const slots = {
  loading: '<div>L</div>', error: '<div>E</div>',
  empty: '<div>M</div>', default: '<div>S</div>',
}

describe('PageStates', () => {
  it('shows loading first when loading=true', () => {
    const w = mount(PageStates, { props: { loading: true, error: null, isEmpty: true }, slots })
    expect(w.text()).toBe('L')
  })
  it('shows error when not loading and error set', () => {
    const w = mount(PageStates, { props: { loading: false, error: 'x', isEmpty: true }, slots })
    expect(w.text()).toBe('E')
  })
  it('shows empty when not loading, no error, isEmpty', () => {
    const w = mount(PageStates, { props: { loading: false, error: null, isEmpty: true }, slots })
    expect(w.text()).toBe('M')
  })
  it('shows success otherwise', () => {
    const w = mount(PageStates, { props: { loading: false, error: null, isEmpty: false }, slots })
    expect(w.text()).toBe('S')
  })
})
```

- [ ] **Step 3: Run the test to confirm it fails**

```powershell
npm run test:js
```
Expected: FAIL (`PageStates.vue` does not exist).

- [ ] **Step 4: Implement `PageStates.vue`** (exact content — enforces R10 four-state priority loading→error→empty→success)

```vue
<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  error: { type: [String, null], default: null },
  isEmpty: { type: Boolean, default: false },
})
</script>

<template>
  <div role="region" aria-live="polite">
    <slot v-if="loading" name="loading"><div class="animate-pulse text-text-tertiary p-6">جارٍ التحميل…</div></slot>
    <slot v-else-if="error" name="error" :message="error"><div class="text-danger p-6">{{ error }}</div></slot>
    <slot v-else-if="isEmpty" name="empty"><div class="text-text-secondary p-6">لا توجد بيانات.</div></slot>
    <slot v-else />
  </div>
</template>
```

- [ ] **Step 5: Run the test to confirm it passes**

```powershell
npm run test:js
```
Expected: PASS (4 tests).

- [ ] **Step 6: Implement the remaining foundation components** (each its own file, exact content)

`PageHeader.vue`:
```vue
<script setup>
defineProps({ title: String, eyebrow: { type: String, default: '' }, description: { type: String, default: '' } })
</script>
<template>
  <header class="flex items-start justify-between gap-4 pb-6">
    <div class="min-w-0">
      <p v-if="eyebrow" class="text-xs font-medium text-text-tertiary">{{ eyebrow }}</p>
      <h1 class="text-2xl font-bold text-text-primary">{{ title }}</h1>
      <p v-if="description" class="text-sm text-text-secondary mt-1">{{ description }}</p>
    </div>
    <div class="shrink-0"><slot name="action" /></div>
  </header>
</template>
```

`EmptyState.vue`:
```vue
<script setup>
defineProps({ title: String, description: { type: String, default: '' } })
</script>
<template>
  <div role="status" class="flex flex-col items-center justify-center text-center py-16 px-6">
    <h3 class="text-lg font-semibold text-text-primary">{{ title }}</h3>
    <p v-if="description" class="text-sm text-text-secondary mt-1">{{ description }}</p>
    <div class="mt-4"><slot name="action" /></div>
  </div>
</template>
```

`ErrorState.vue`:
```vue
<script setup>
defineProps({ message: { type: String, default: 'حدث خطأ غير متوقع.' } })
</script>
<template>
  <div role="alert" class="flex flex-col items-center justify-center text-center py-16 px-6">
    <p class="text-danger font-medium">{{ message }}</p>
    <div class="mt-4"><slot name="action" /></div>
  </div>
</template>
```

`StatCard.vue`:
```vue
<script setup>
defineProps({ title: String, value: [String, Number], trend: { type: String, default: '' } })
</script>
<template>
  <div class="bg-surface-card rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] p-6">
    <p class="text-sm text-text-secondary">{{ title }}</p>
    <p class="text-3xl font-bold text-text-primary mt-2">{{ value }}</p>
    <p v-if="trend" class="text-xs text-success mt-1">{{ trend }}</p>
  </div>
</template>
```

`Badge.vue` (semantic wrapper over shadcn Badge):
```vue
<script setup>
const map = { success: 'bg-success/10 text-success', warning: 'bg-warning/10 text-warning', danger: 'bg-danger/10 text-danger', info: 'bg-info/10 text-info' }
defineProps({ type: { type: String, default: 'info' }, label: String })
</script>
<template>
  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', map[type] || map.info]">{{ label }}<slot /></span>
</template>
```

`DataTable.vue` (density + sticky header + empty handling):
```vue
<script setup>
defineProps({
  columns: { type: Array, required: true }, // [{ key, label, align }]
  rows: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'لا توجد سجلات.' },
})
</script>
<template>
  <div class="overflow-x-auto rounded-[var(--radius-lg)] border border-border-default bg-surface-card">
    <table class="w-full text-sm">
      <thead class="sticky top-0 bg-surface-sunken">
        <tr>
          <th v-for="c in columns" :key="c.key" class="px-4 py-3 font-medium text-text-secondary"
              :class="c.align === 'end' ? 'text-end' : 'text-start'">{{ c.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="rows.length === 0"><td :colspan="columns.length" class="px-4 py-10 text-center text-text-tertiary">{{ emptyText }}</td></tr>
        <tr v-for="(r, i) in rows" :key="i" class="border-t border-border-default hover:bg-surface-sunken/60">
          <td v-for="c in columns" :key="c.key" class="px-4 py-3"
              :class="c.align === 'end' ? 'text-end' : 'text-start'">
            <slot :name="`cell-${c.key}`" :row="r">{{ r[c.key] }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

`FormGroup.vue`:
```vue
<script setup>
defineProps({ label: String, name: String, error: { type: String, default: '' }, hint: { type: String, default: '' }, required: Boolean })
</script>
<template>
  <div class="space-y-2">
    <label :for="name" class="block text-sm font-medium text-text-primary">{{ label }}<span v-if="required" class="text-danger"> *</span></label>
    <slot />
    <p v-if="hint && !error" class="text-xs text-text-tertiary">{{ hint }}</p>
    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
```

`FormSection.vue`:
```vue
<script setup>
defineProps({ title: String, description: { type: String, default: '' } })
</script>
<template>
  <section class="bg-surface-card rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] p-6 space-y-4">
    <div><h2 class="text-lg font-semibold text-text-primary">{{ title }}</h2>
      <p v-if="description" class="text-sm text-text-secondary">{{ description }}</p></div>
    <div class="space-y-4"><slot /></div>
  </section>
</template>
```

`FormActions.vue`:
```vue
<template>
  <div class="flex items-center justify-end gap-2 pt-4 sticky bottom-0 bg-surface-page/80 backdrop-blur"><slot /></div>
</template>
```

`Modal.vue` (uses shadcn Dialog → reka-ui portals to body, satisfies kit overlay rule):
```vue
<script setup>
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
defineProps({ open: Boolean, title: String })
defineEmits(['update:open'])
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="z-modal">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <slot />
      <div class="mt-4 flex justify-end gap-2"><slot name="footer" /></div>
    </DialogContent>
  </Dialog>
</template>
```

`Drawer.vue` (uses shadcn Sheet):
```vue
<script setup>
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet'
defineProps({ open: Boolean, title: String })
defineEmits(['update:open'])
</script>
<template>
  <Sheet :open="open" @update:open="$emit('update:open', $event)">
    <SheetContent side="left" class="z-modal w-full max-w-md">
      <SheetHeader><SheetTitle>{{ title }}</SheetTitle></SheetHeader>
      <slot />
    </SheetContent>
  </Sheet>
</template>
```

`ConfirmModal.vue`:
```vue
<script setup>
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
defineProps({ open: Boolean, title: { type: String, default: 'تأكيد' }, message: String, confirmText: { type: String, default: 'تأكيد' }, cancelText: { type: String, default: 'إلغاء' } })
const emit = defineEmits(['update:open', 'confirm'])
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="z-modal" role="alertdialog">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <p class="text-sm text-text-secondary">{{ message }}</p>
      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" @click="emit('update:open', false)">{{ cancelText }}</Button>
        <Button class="bg-danger text-white" @click="emit('confirm'); emit('update:open', false)">{{ confirmText }}</Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
```

`index.js` (barrel — single import surface):
```js
export { default as PageStates } from './PageStates.vue'
export { default as PageHeader } from './PageHeader.vue'
export { default as DataTable } from './DataTable.vue'
export { default as FormGroup } from './FormGroup.vue'
export { default as FormSection } from './FormSection.vue'
export { default as FormActions } from './FormActions.vue'
export { default as Modal } from './Modal.vue'
export { default as Drawer } from './Drawer.vue'
export { default as ConfirmModal } from './ConfirmModal.vue'
export { default as StatCard } from './StatCard.vue'
export { default as Badge } from './Badge.vue'
export { default as EmptyState } from './EmptyState.vue'
export { default as ErrorState } from './ErrorState.vue'
```

- [ ] **Step 7: Run JS tests + build**

```powershell
npm run test:js
npm run build
```
Expected: PageStates tests PASS; build succeeds.

- [ ] **Step 8: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(ui): foundation component layer (4-states, table, forms, overlays)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: User role enum, phone, CustomerProfile model + migrations

**Files:**
- Create: `app/Enums/UserRole.php`
- Create: `database/migrations/2026_05_19_000001_add_role_phone_to_users.php`
- Create: `database/migrations/2026_05_19_000002_create_customer_profiles.php`
- Create: `app/Models/CustomerProfile.php`
- Modify: `app/Models/User.php`
- Test: `tests/Unit/UserRoleTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/UserRoleTest.php`:
```php
<?php
use App\Enums\UserRole;

it('classifies staff vs customer', function () {
    expect(UserRole::Manager->isStaff())->toBeTrue();
    expect(UserRole::Doctor->isStaff())->toBeTrue();
    expect(UserRole::Receptionist->isStaff())->toBeTrue();
    expect(UserRole::Customer->isStaff())->toBeFalse();
});
```

- [ ] **Step 2: Run it — confirm fail**

```powershell
php artisan test --filter=UserRoleTest
```
Expected: FAIL (`App\Enums\UserRole` not found).

- [ ] **Step 3: Create the enum** `app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Doctor = 'doctor';
    case Receptionist = 'receptionist';
    case Customer = 'customer';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
```

- [ ] **Step 4: Run it — confirm pass**

```powershell
php artisan test --filter=UserRoleTest
```
Expected: PASS.

- [ ] **Step 5: Migration — add `role` + `phone` to users** `database/migrations/2026_05_19_000001_add_role_phone_to_users.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('customer')->index();
            $t->string('phone', 32)->nullable()->unique();
            $t->string('email')->nullable()->change();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('manager','doctor','receptionist','customer'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_email_or_phone CHECK (email IS NOT NULL OR phone IS NOT NULL)");
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone']));
    }
};
```
(Add `use Illuminate\Support\Facades\DB;` at top.)

- [ ] **Step 6: Migration — `customer_profiles`** `database/migrations/2026_05_19_000002_create_customer_profiles.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date_of_birth')->nullable();
            $t->string('gender', 16)->nullable();
            $t->text('notes')->nullable();
            $t->string('avatar_path')->nullable();
            $t->timestamp('profile_completed_at')->nullable();
            $t->timestamps();
            $t->unique('user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('customer_profiles'); }
};
```

- [ ] **Step 7: `CustomerProfile` model** `app/Models/CustomerProfile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = ['user_id', 'date_of_birth', 'gender', 'notes', 'avatar_path', 'profile_completed_at'];
    protected $casts = ['date_of_birth' => 'date', 'profile_completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 8: Update `User` model** — add to `app/Models/User.php`: cast `role`, add `phone` to `$fillable`, add relation + helper:

```php
// in $fillable add 'phone', 'role'
// in casts() add: 'role' => \App\Enums\UserRole::class,

public function customerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\CustomerProfile::class);
}

public function isStaff(): bool
{
    return $this->role->isStaff();
}
```

- [ ] **Step 9: Migrate + test**

```powershell
php artisan migrate
php artisan test --filter=UserRoleTest
```
Expected: migration OK; test PASS.

- [ ] **Step 10: Commit**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(auth): UserRole enum, phone, CustomerProfile + DB checks

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: AuthService (email-or-phone) + role middleware + route/surface separation

**Files:**
- Create: `app/Domain/Auth/Services/AuthService.php`
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Create: `routes/admin.php`, `routes/portal.php`
- Modify: `routes/auth.php` (Breeze) — login resolver; `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `bootstrap/app.php` (register middleware alias + route files)
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Create: `resources/js/Pages/Admin/Dashboard.vue`, `resources/js/Pages/Portal/Home.vue`
- Test: `tests/Unit/Domain/Auth/AuthServiceTest.php`, `tests/Feature/Auth/SurfaceIsolationTest.php`, `tests/Feature/Auth/LoginIdentifierTest.php`

- [ ] **Step 1: Write the failing unit test** `tests/Unit/Domain/Auth/AuthServiceTest.php`:

```php
<?php
use App\Domain\Auth\Services\AuthService;
use App\Models\User;
use App\Enums\UserRole;

it('resolves a user by email or phone', function () {
    $u = User::factory()->create(['email' => 'a@b.com', 'phone' => '0599000111', 'role' => UserRole::Customer]);
    $svc = app(AuthService::class);
    expect($svc->resolveByIdentifier('a@b.com')->id)->toBe($u->id);
    expect($svc->resolveByIdentifier('0599000111')->id)->toBe($u->id);
    expect($svc->resolveByIdentifier('missing'))->toBeNull();
});

it('registers a customer with a profile', function () {
    $svc = app(AuthService::class);
    $u = $svc->registerCustomer(['name' => 'X', 'email' => 'x@y.com', 'phone' => null, 'password' => 'secret12']);
    expect($u->role)->toBe(UserRole::Customer);
    expect($u->customerProfile)->not->toBeNull();
});
```

- [ ] **Step 2: Run — confirm fail**

```powershell
php artisan test --filter=AuthServiceTest
```
Expected: FAIL (class missing).

- [ ] **Step 3: Implement `AuthService`** `app/Domain/Auth/Services/AuthService.php`:

```php
<?php

namespace App\Domain\Auth\Services;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function resolveByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    public function registerCustomer(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => UserRole::Customer,
        ]);
        CustomerProfile::create(['user_id' => $user->id]);

        return $user->fresh('customerProfile');
    }
}
```

- [ ] **Step 4: Run — confirm pass**

```powershell
php artisan test --filter=AuthServiceTest
```
Expected: PASS.

- [ ] **Step 5: Role middleware** `app/Http/Middleware/EnsureUserHasRole.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
```

- [ ] **Step 6: Register middleware alias + route files** — in `bootstrap/app.php` `->withMiddleware` add:
```php
$middleware->alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class]);
```
and in `->withRouting(...)` add `then:` callback (or `web:` additional) loading the two files:
```php
Route::middleware('web')->group(base_path('routes/admin.php'));
Route::middleware('web')->group(base_path('routes/portal.php'));
```

- [ ] **Step 7: Create the two surface route files**

`routes/admin.php`:
```php
<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:manager,doctor,receptionist'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
    });
```

`routes/portal.php`:
```php
<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:customer'])
    ->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', fn () => Inertia::render('Portal/Home'))->name('home');
    });
```

- [ ] **Step 8: Make login accept email OR phone** — in `app/Http/Requests/Auth/LoginRequest.php` replace the `email` rule with an `identifier` field and update `authenticate()` to resolve via `AuthService`:

```php
// rules():
return [
    'identifier' => ['required', 'string'],
    'password' => ['required', 'string'],
];

// authenticate():
$user = app(\App\Domain\Auth\Services\AuthService::class)->resolveByIdentifier($this->input('identifier'));
if (! $user || ! \Illuminate\Support\Facades\Hash::check($this->input('password'), $user->password)) {
    \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey());
    throw \Illuminate\Validation\ValidationException::withMessages(['identifier' => trans('auth.failed')]);
}
\Illuminate\Support\Facades\Auth::login($user, $this->boolean('remember'));
\Illuminate\Support\Facades\RateLimiter::clear($this->throttleKey());
```
Update `throttleKey()` to use `identifier` instead of `email`. In `resources/js/Pages/Auth/Login.vue` rename the email field/model to `identifier` with Arabic label «البريد الإلكتروني أو رقم الجوال».

- [ ] **Step 9: Registration creates customer via AuthService + redirects by role** — in `RegisteredUserController@store`, replace user creation with `app(AuthService::class)->registerCustomer($request->validated())` and redirect customers to `route('portal.home')`. Update the authenticated redirect (Breeze `AuthenticatedSessionController@store`) to: `return redirect($user->isStaff() ? route('admin.dashboard') : route('portal.home'));`. Adjust `RegisterRequest`/validation to require name+password and at least one of email/phone.

- [ ] **Step 10: Placeholder pages**

`resources/js/Pages/Admin/Dashboard.vue`:
```vue
<script setup>
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, StatCard } from '@/Components/foundation'
</script>
<template>
  <AdminShell>
    <PageHeader title="لوحة التحكم" eyebrow="عيادة جنّة" description="نظرة عامة (سيتم تفعيل المؤشرات في المراحل القادمة)" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard title="المواعيد اليوم" value="—" />
      <StatCard title="بانتظار مراجعة الدفع" value="—" />
      <StatCard title="عملاء جدد" value="—" />
      <StatCard title="الخدمات النشطة" value="—" />
    </div>
  </AdminShell>
</template>
```

`resources/js/Pages/Portal/Home.vue`:
```vue
<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
</script>
<template>
  <ClientShell>
    <PageHeader title="أهلاً بك في عيادة جنّة" description="الحجز والخدمات ستتوفر قريبًا." />
  </ClientShell>
</template>
```

- [ ] **Step 11: Write + run the surface-isolation feature test** `tests/Feature/Auth/SurfaceIsolationTest.php`:

```php
<?php
use App\Models\User;
use App\Enums\UserRole;

it('blocks a customer from the admin surface', function () {
    $c = User::factory()->create(['role' => UserRole::Customer]);
    $this->actingAs($c)->get('/admin')->assertForbidden();
});

it('blocks staff from the customer portal', function () {
    $d = User::factory()->create(['role' => UserRole::Doctor]);
    $this->actingAs($d)->get('/portal')->assertForbidden();
});

it('allows manager into admin', function () {
    $m = User::factory()->create(['role' => UserRole::Manager]);
    $this->actingAs($m)->get('/admin')->assertOk();
});
```

```powershell
php artisan test --filter=SurfaceIsolationTest
```
Expected: PASS (after Steps 5–10). If `User::factory()` lacks `role`, add `'role' => UserRole::Customer` default + `phone` to `database/factories/UserFactory.php`.

- [ ] **Step 12: Write + run the login-identifier feature test** `tests/Feature/Auth/LoginIdentifierTest.php`:

```php
<?php
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

it('logs in by phone and lands on portal', function () {
    User::factory()->create(['phone' => '0599123456', 'email' => null, 'role' => UserRole::Customer, 'password' => Hash::make('secret12')]);
    $this->post('/login', ['identifier' => '0599123456', 'password' => 'secret12'])
        ->assertRedirect(route('portal.home'));
});
```

```powershell
php artisan test --filter=LoginIdentifierTest
```
Expected: PASS.

- [ ] **Step 13: Full gate + commit**

```powershell
./vendor/bin/pint
./vendor/bin/phpstan analyse --no-progress
php artisan test
npm run test:js
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(auth): email-or-phone auth, role middleware, admin/portal surfaces

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: The two shells + unified error pages + profile/avatar

**Files:**
- Create: `resources/js/Layouts/AdminShell.vue`, `resources/js/Layouts/ClientShell.vue`
- Create: `resources/js/Pages/Errors/403.vue`, `404.vue`, `500.vue`
- Modify: `app/Http/Controllers/ProfileController.php` (Breeze) — avatar upload to CustomerProfile
- Test: `tests/Feature/ProfileAvatarTest.php`

- [ ] **Step 1: `AdminShell.vue`** (sidebar + topbar; RTL logical props only):

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
const nav = [
  { label: 'لوحة التحكم', href: '/admin' },
]
</script>
<template>
  <div class="min-h-full flex">
    <aside class="z-shell w-64 shrink-0 bg-brand text-white p-4 space-y-2">
      <div class="text-lg font-bold pb-4">عيادة جنّة</div>
      <Link v-for="n in nav" :key="n.href" :href="n.href"
            class="block rounded-[var(--radius-md)] px-3 py-2 hover:bg-white/10">{{ n.label }}</Link>
    </aside>
    <div class="flex-1 min-w-0">
      <header class="z-sticky h-16 bg-surface-card border-b border-border-default flex items-center px-6">
        <div class="ms-auto"><Link href="/logout" method="post" as="button" class="text-sm text-text-secondary">تسجيل الخروج</Link></div>
      </header>
      <main class="p-6"><slot /></main>
    </div>
  </div>
</template>
```

- [ ] **Step 2: `ClientShell.vue`** (mobile-first + bottom nav, mirrors `clinic`):

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
const tabs = [
  { label: 'الرئيسية', href: '/portal' },
  { label: 'الحجز', href: '/portal' },
  { label: 'الإشعارات', href: '/portal' },
  { label: 'حسابي', href: '/portal' },
]
</script>
<template>
  <div class="min-h-full mx-auto max-w-md flex flex-col">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card">
      <span class="font-bold text-brand">عيادة جنّة</span>
    </header>
    <main class="flex-1 p-4 pb-20"><slot /></main>
    <nav class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid grid-cols-4">
      <Link v-for="t in tabs" :key="t.label" :href="t.href" class="py-3 text-center text-xs text-text-secondary">{{ t.label }}</Link>
    </nav>
  </div>
</template>
```

- [ ] **Step 3: Error pages** — `resources/js/Pages/Errors/403.vue` (repeat pattern for 404.vue with «الصفحة غير موجودة» and 500.vue with «خطأ في الخادم»):

```vue
<script setup>
import { ErrorState } from '@/Components/foundation'
</script>
<template>
  <div class="min-h-full grid place-items-center">
    <ErrorState message="ليس لديك صلاحية للوصول إلى هذه الصفحة." />
  </div>
</template>
```
Wire Inertia error rendering: in `app/Exceptions/Handler.php` (or `bootstrap/app.php` `->withExceptions`) render `Inertia::render("Errors/{$status}")` for 403/404/500 in production-like responses.

- [ ] **Step 4: Avatar upload — write failing test** `tests/Feature/ProfileAvatarTest.php`:

```php
<?php
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores a customer avatar', function () {
    Storage::fake('public');
    $u = User::factory()->create(['role' => UserRole::Customer]);
    $u->customerProfile()->create([]);
    $this->actingAs($u)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')])
        ->assertRedirect();
    expect($u->customerProfile->fresh()->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($u->customerProfile->fresh()->avatar_path);
});
```

- [ ] **Step 5: Run — confirm fail**, then implement the route + controller method:

`routes/portal.php` add inside the customer group:
```php
Route::post('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
```
Add to `ProfileController`:
```php
public function updateAvatar(\Illuminate\Http\Request $request)
{
    $request->validate(['avatar' => ['required', 'image', 'max:2048']]);
    $path = $request->file('avatar')->store('avatars', 'public');
    $request->user()->customerProfile()->update(['avatar_path' => $path]);
    return back();
}
```
The avatar test posts to `/profile/avatar`; ensure the route is reachable for `customer` (it's inside the portal group but `/profile/avatar` not `/portal/profile/avatar` — place the route OUTSIDE the `prefix('portal')` group but still under `auth`+`role:customer` so the path matches the test). Concretely add a second group in `routes/portal.php`:
```php
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::post('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
});
```

```powershell
php artisan storage:link
php artisan test --filter=ProfileAvatarTest
```
Expected: PASS.

- [ ] **Step 6: Full gate + commit**

```powershell
./vendor/bin/pint
./vendor/bin/phpstan analyse --no-progress
php artisan test
npm run build
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "feat(ui): admin/client shells, unified error pages, avatar upload

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: P0 acceptance verification + ARCHITECTURE/DOMAIN docs + tag

**Files:**
- Create: `docs/ARCHITECTURE.md`, `docs/DOMAIN-MODEL.md` (the `autodoc_targets` from the interview)

- [ ] **Step 1: Author `docs/ARCHITECTURE.md`** — concise: stack (Laravel12/Inertia/Vue3/shadcn-vue/Postgres), domain-service rule (R7), the two surfaces + route files + role middleware, the foundation component layer + design tokens origin (building.app DNA, re-expressed), where Layer-1 governance lives, link to ADR-001/002. Keep ≤1 page.

- [ ] **Step 2: Author `docs/DOMAIN-MODEL.md`** — P0 entities only: `User(role,phone,email,password)`, `CustomerProfile(user_id, dob, gender, notes, avatar_path, profile_completed_at)`; note P1–P5 entities are out of scope and tracked in the spec roadmap §2.

- [ ] **Step 3: Full quality gate (the DoD)**

```powershell
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --no-progress
php artisan test
npm run test:js
npm run build
"! grep -rEn 'class=\"[^\"]*(pl-|pr-|ml-|mr-)' resources/js"
```
Expected: Pint clean; PHPStan level 5 clean; all Pest tests green (incl. UserRole, AuthService, SurfaceIsolation, LoginIdentifier, ProfileAvatar); Vitest PageStates green; Vite build OK; RTL grep finds nothing.

- [ ] **Step 4: P0 acceptance checklist (must all pass)**

- [ ] `docs/` has kit governance with zero `{{...}}`; ADR-001 + ADR-002 registered.
- [ ] `php artisan migrate:fresh` then app boots; `/login` reachable, RTL Arabic.
- [ ] Customer registers → lands on `/portal`; staff login → lands on `/admin`; cross-surface access → 403 page.
- [ ] Foundation layer importable from `@/Components/foundation`; PageStates enforces 4-state priority (tested).
- [ ] Quality gate (pint+phpstan+pest+vitest+build+RTL grep) green.
- [ ] No P1+ business logic present (services/booking/payments/records/loyalty/notifications absent) — YAGNI boundary held.

- [ ] **Step 5: Commit + tag P0**

```powershell
git add -A
git -c user.email=admin@istoria.app -c user.name=claude commit -m "docs: ARCHITECTURE + DOMAIN-MODEL; P0 foundation acceptance

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git tag p0-foundation
```

---

## Plan Self-Review

**Spec coverage:** spec §3.1 kit bootstrap+interview → Task 1 (exact YAML). §3.1 ADR-002 / §3.6 documented risk → Task 1 Step 3. §3.2 app structure/domain-services/route separation → Tasks 2, 8. §3.3 design tokens/Cairo/RTL/foundation layer → Tasks 4, 5, 6. §3.4 email-or-phone auth + 4 roles + server-side isolation → Tasks 7, 8. §3.5 two empty shells + error pages → Tasks 8, 9. §5 error states (4-states/old()) → Task 6 (PageStates) + Task 9 (error pages). §6 testing + DoD gate (pint/larastan/pest/RTL/build) → Tasks 3, 8, 9, 10. §7 YAGNI boundary → Task 10 Step 4 checklist. §8 git/docs location → repo already initialized; per-task commits. autodoc_targets (ARCHITECTURE.md, DOMAIN-MODEL.md) → Task 10. No gaps.

**Placeholder scan:** No "TBD/TODO". Framework-scaffold steps use exact commands + expected output (acceptable: scaffolding is not TDD-able, verification is the command result). Logic-bearing tasks (UserRole, AuthService, role middleware, avatar) have real failing tests written before implementation with complete code. Component code given in full; no "similar to".

**Type/name consistency:** `UserRole` enum cases (Manager/Doctor/Receptionist/Customer) consistent across Tasks 7, 8, 9 tests. `AuthService::resolveByIdentifier`/`registerCustomer` signatures consistent between Task 8 Step 1 test and Step 3 impl. Route names `admin.dashboard` / `portal.home` consistent across routes (Task 8 Step 7) and redirects/tests (Steps 9, 11, 12) and Task 9. `role` middleware alias consistent (Task 8 Steps 6–7, routes). Foundation `index.js` exports match component filenames (Task 6). `customerProfile()` relation name consistent (Task 7 Step 8, Task 9 avatar).

One adjustment applied inline: Task 9 avatar route placed in a non-prefixed `role:customer` group so the test's `/profile/avatar` path matches (not `/portal/profile/avatar`).

No remaining issues.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-19-jannahclinic-p0-foundation.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, spec + quality review between tasks, fast iteration.

**2. Inline Execution** — execute tasks in this session via executing-plans with checkpoints.

Which approach?
