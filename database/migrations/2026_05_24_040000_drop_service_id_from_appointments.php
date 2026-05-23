<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 of the multi-service refactor: drop appointments.service_id.
 * The pivot table appointment_services is now the single source of
 * truth for which services were rendered at an appointment.
 *
 * Safe to run because phase 1's migration already backfilled every
 * appointment into the pivot, and every booking/reschedule path written
 * since then dual-wrote the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sanity check: every appointment must have at least one pivot row
        // before we let the column go. Phase-1 backfill guarantees this for
        // pre-existing rows; phase-2 dual-write guarantees it for anything
        // booked since.
        $orphaned = DB::table('appointments as a')
            ->leftJoin('appointment_services as svc', 'svc.appointment_id', '=', 'a.id')
            ->whereNull('svc.id')
            ->count();
        if ($orphaned > 0) {
            throw new \RuntimeException("{$orphaned} appointment(s) have no pivot row — refusing to drop service_id. Backfill them first.");
        }

        Schema::table('appointments', function (Blueprint $t): void {
            $t->dropForeign(['service_id']);
            $t->dropColumn('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $t): void {
            $t->foreignId('service_id')->nullable()->after('doctor_profile_id')->constrained()->restrictOnDelete();
        });
        // Repopulate from the pivot's first row per appointment.
        DB::statement('
            UPDATE appointments a SET service_id = (
                SELECT service_id
                FROM appointment_services
                WHERE appointment_id = a.id
                ORDER BY sort_order, id
                LIMIT 1
            )
        ');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments ALTER COLUMN service_id SET NOT NULL');
        }
    }
};
