<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert notifications.data from TEXT to JSONB on Postgres.
     *
     * The base `php artisan notifications:table` schema declares the column as
     * `text`. Laravel's query grammar compiles `where('data->category', X)` to
     * `data->>'category' = ?`, but the `->>` operator is only defined for json/jsonb
     * in Postgres — on a text column the request errors with
     * `operator does not exist: text ->> unknown` and the notification center
     * category filter returns a 500.
     *
     * SQLite (used in tests) implements `data->'category'` via `json_extract`
     * on any column, so the test suite passed without catching this. The migration
     * is a no-op on SQLite (driver guard below).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text');
    }
};
