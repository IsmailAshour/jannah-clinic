<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the legacy weekly-window schedule model. The slot-grid model
     * (doctor_schedule_slots / schedule_exception_slots, added in the
     * 000700 migration) fully supersedes `doctor_schedules` and the
     * `schedule_exceptions.custom_start/custom_end` columns. Also swaps the
     * pgsql `type` CHECK from the legacy `closed|custom_hours` to the new
     * `closed|custom` (the value T13/T14 actually write).
     */
    public function up(): void
    {
        Schema::dropIfExists('doctor_schedules');

        if (Schema::hasColumn('schedule_exceptions', 'custom_start')) {
            Schema::table('schedule_exceptions', function (Blueprint $t) {
                $t->dropColumn(['custom_start', 'custom_end']);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check');
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom'))");
        }
    }

    /**
     * One-way retirement of the legacy weekly-window model. Rollback
     * recreates the minimal legacy shape (empty) so the migration is
     * reversible for tooling, without restoring lost data.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check');
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom_hours'))");
        }

        Schema::table('schedule_exceptions', function (Blueprint $t) {
            $t->time('custom_start')->nullable();
            $t->time('custom_end')->nullable();
        });

        Schema::create('doctor_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->boolean('morning_enabled')->default(false);
            $t->time('morning_start')->nullable();
            $t->time('morning_end')->nullable();
            $t->boolean('evening_enabled')->default(false);
            $t->time('evening_start')->nullable();
            $t->time('evening_end')->nullable();
            $t->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'weekday']);
        });
    }
};
