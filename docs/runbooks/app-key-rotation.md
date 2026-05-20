# Runbook — Rotating `APP_KEY` (PHI Re-Encryption)

> Status: ACTIVE-IMPLEMENTATION-SUPPORT
> Scope: operations / security
> Owner: Engineering
> Canonical Registry Ref: docs/CANONICAL-DECISION-REGISTRY.md
> Authority: ADR-003 (`docs/adr/003-encrypted-medical-records.md`) mandates this rotation; this file is the operational procedure.

## Why this runbook exists

ADR-003 mandates quarterly rotation of `APP_KEY`. Every column listed below is
encrypted at rest via Laravel's `encrypted` Eloquent cast keyed by `APP_KEY`.
If `APP_KEY` is replaced without re-encrypting these rows, **every existing
row becomes unreadable** — Laravel cannot decrypt ciphertext that was written
with the previous key.

This runbook walks through the **two-key window** rotation procedure,
where the application holds the new key as `APP_KEY` and the previous key
as `APP_PREVIOUS_KEYS` for the duration of the re-encryption pass. Laravel
will transparently try `APP_KEY` first then fall back to `APP_PREVIOUS_KEYS`
for any read, which lets the application stay up while rotation runs.

## Encrypted columns covered by this runbook

| Table | Columns |
|-------|---------|
| `medical_entries` | `visible_summary`, `staff_notes` |
| `prescriptions` | `medication_name`, `dosage`, `frequency`, `duration`, `notes` |
| `customer_profiles` | `notes`, `chronic_conditions`, `allergies` |

If a future PR adds a new `'encrypted'` cast, the new column MUST be added
to this table AND to the artisan command in Step 4 below.

## Pre-flight checklist

Before starting rotation:

- [ ] A recent **logical backup** of `jannahclinic` (pg_dump) is available
      and verified-restorable. Without it, a botched rotation is unrecoverable.
- [ ] No active deploys are in flight; CI is green.
- [ ] `php artisan down` is **not** required if you follow the two-key window
      below — the app stays up. Skip maintenance mode unless re-encryption
      is expected to lock rows for an unacceptable window (the current data
      volume is small enough that this should be sub-second per row).
- [ ] You have shell access to the production host with permission to edit
      `.env` and run artisan commands.
- [ ] The new key is generated locally first (see Step 1) and stored in
      a password manager BEFORE being placed on the production host.

## Procedure

### Step 1 — Generate the new key locally (do NOT commit)

```bash
# On a developer machine — generates a fresh base64 32-byte key
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copy the output (e.g. `base64:eK7…`). Store it in the team password manager
labeled `jannahclinic APP_KEY @ YYYY-MM-DD`. This is the value you will set
on the production host in Step 3.

### Step 2 — Note the current key

On the production host:

```bash
grep '^APP_KEY=' .env
```

This is the **previous** key. You will need it in Step 3 for
`APP_PREVIOUS_KEYS`. Copy it.

### Step 3 — Update `.env` to hold both keys

Edit `.env`:

```env
APP_KEY=base64:<new-key-from-step-1>
APP_PREVIOUS_KEYS=base64:<previous-key-from-step-2>
```

`APP_PREVIOUS_KEYS` is a comma-separated list of additional decryption keys
that Laravel will try if the active `APP_KEY` cannot decrypt a payload.
Reads stay fully functional during the re-encryption pass.

Reload the application's config cache:

```bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart   # so any running workers reload .env
```

Sanity check that reads still work — open the customer portal in a browser,
verify a medical record renders correctly. **If you see "The payload is
invalid." anywhere, STOP** and restore the previous `APP_KEY` (revert .env).

### Step 4 — Run the re-encryption pass

The command below decrypts every value in every encrypted column using
whichever key Laravel resolves (i.e. the previous key for old rows, the new
key for any row already written under the new key), then re-saves the row
through Eloquent — which encrypts with the active `APP_KEY` (the new one).
The command is **idempotent**: running it a second time is a no-op because
every value is already under the new key.

```bash
php artisan medical:rotate-encryption
```

> **Note:** This command does not exist yet — it is part of the ADR-003
> follow-on operations work. Until then, run the manual re-encryption loop
> below from `tinker`:
>
> ```php
> php artisan tinker
> >>> App\Models\MedicalEntry::chunkById(500, fn ($c) => $c->each->save());
> >>> App\Models\Prescription::chunkById(500, fn ($c) => $c->each->save());
> >>> App\Models\CustomerProfile::chunkById(500, fn ($c) => $c->each->save());
> ```
>
> `$model->save()` on each row re-encrypts every `encrypted`-cast column
> using the active `APP_KEY`. The chunk loop avoids loading the entire
> table into memory.

### Step 5 — Verify

Run the PHI-at-rest test suite against production (read-only — does not write):

```bash
php artisan tinker
>>> use Illuminate\Support\Facades\DB;
>>> use Illuminate\Support\Facades\Crypt;
>>> $row = DB::table('medical_entries')->first();
>>> $row?->visible_summary ? Crypt::decryptString($row->visible_summary) : null;
```

If `decryptString` succeeds for a row created BEFORE rotation, the
re-encryption pass completed for that row. Spot-check a few more rows
across the three tables. (You can sample by `orderBy('id','desc')->skip(N)`
to pick rows from different eras.)

### Step 6 — Drop `APP_PREVIOUS_KEYS`

Once Step 5 confirms every row is readable without the previous key, edit
`.env` and **remove** the `APP_PREVIOUS_KEYS` line.

```bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

Verify the portal still renders. The rotation is complete.

### Step 7 — Decommission the previous key

In your password manager, mark the previous key entry as `RETIRED YYYY-MM-DD`
and reference the new key entry. Do not delete the previous key entry for at
least one rotation cycle (3 months) in case a backup taken before Step 4
needs to be restored — the previous key would be required to decrypt rows
in that backup.

## Failure modes

| Symptom | Cause | Action |
|---------|-------|--------|
| `Illuminate\Encryption\DecryptException: The MAC is invalid` on portal read after Step 3 | Wrong previous key in `APP_PREVIOUS_KEYS` | Revert `.env`, double-check the key from Step 2 |
| Re-encryption command/loop dies mid-way | Transient DB error, OOM, etc. | Re-run — idempotent. Re-validation in Step 5 will catch any missed rows |
| After Step 6, some rows can't be decrypted | Re-encryption pass missed them | Add `APP_PREVIOUS_KEYS` back, rerun re-encryption, restart from Step 5 |
| Pre-flight backup is missing | Process violation | Take a backup before doing anything else; do NOT proceed without one |

## Logging the rotation

Append a one-line entry to `docs/runbooks/rotation-log.md` (create the file
if absent):

```
- 2026-MM-DD — APP_KEY rotated. Previous key retired YYYY-MM-DD. Runbook
  followed without incident. — <your-name>
```

This log is the official record that the ADR-003 quarterly rotation cadence
was honored.
