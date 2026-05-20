<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Partial unique index: prevents two concurrent inserts for the same
        // (reason, reference_type, reference_id) triple — Postgres rejects the
        // second insert atomically, turning idempotency into a hard guarantee.
        // Manager adjustments have reference_type=NULL so are NOT covered (intentional —
        // a manager can legitimately add 50 points twice with different notes).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX loyalty_ledger_idempotency_idx
                ON loyalty_ledger (reason, reference_type, reference_id)
                WHERE reference_type IS NOT NULL');
        } else {
            // SQLite supports partial indexes since 3.8; use the same syntax.
            DB::statement('CREATE UNIQUE INDEX loyalty_ledger_idempotency_idx
                ON loyalty_ledger (reason, reference_type, reference_id)
                WHERE reference_type IS NOT NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS loyalty_ledger_idempotency_idx');
    }
};
