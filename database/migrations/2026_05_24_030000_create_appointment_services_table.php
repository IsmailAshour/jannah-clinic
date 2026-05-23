<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the multi-service refactor: add the pivot table and backfill
 * from the existing appointments.service_id, but KEEP service_id intact.
 * The pivot is a "shadow" of service_id during the transition — application
 * code can start writing/reading the pivot without breaking anything that
 * still uses service_id. The column is dropped in a later migration once
 * every consumer has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->decimal('price_at_booking', 10, 2);
            $t->integer('duration_minutes');
            $t->integer('sort_order')->default(0);
            $t->timestamps();

            $t->unique(['appointment_id', 'service_id']);
            $t->index('appointment_id');
            $t->index('service_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointment_services ADD CONSTRAINT appointment_services_price_check CHECK (price_at_booking >= 0)');
            DB::statement('ALTER TABLE appointment_services ADD CONSTRAINT appointment_services_duration_check CHECK (duration_minutes > 0)');
        }

        // Backfill — one pivot row per existing appointment. Surcharge is
        // NOT replicated (it's per-visit, not per-line).
        $nowExpr = DB::getDriverName() === 'pgsql' ? 'NOW()' : "DATETIME('now')";
        DB::statement('
            INSERT INTO appointment_services
                (appointment_id, service_id, price_at_booking, duration_minutes, sort_order, created_at, updated_at)
            SELECT
                a.id,
                a.service_id,
                a.price_at_booking - a.home_surcharge_amount,
                s.duration_minutes,
                0,
                COALESCE(a.created_at, '.$nowExpr.'),
                COALESCE(a.updated_at, '.$nowExpr.')
            FROM appointments a
            JOIN services s ON s.id = a.service_id
        ');

        $apptCount = (int) DB::table('appointments')->count();
        $pivotCount = (int) DB::table('appointment_services')->count();
        if ($apptCount !== $pivotCount) {
            throw new \RuntimeException("Backfill mismatch: {$apptCount} appointments vs {$pivotCount} pivot rows.");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointment_services DROP CONSTRAINT IF EXISTS appointment_services_price_check');
            DB::statement('ALTER TABLE appointment_services DROP CONSTRAINT IF EXISTS appointment_services_duration_check');
        }
        Schema::dropIfExists('appointment_services');
    }
};
