<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doctor_schedule_slots')) {
            Schema::create('doctor_schedule_slots', function (Blueprint $t) {
                $t->id();
                $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
                $t->unsignedTinyInteger('weekday');
                $t->string('slot_start', 5);
                $t->timestamps();
                $t->unique(['doctor_profile_id', 'weekday', 'slot_start']);
                $t->index(['doctor_profile_id', 'weekday']);
            });
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE doctor_schedule_slots ADD CONSTRAINT dss_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
            }
        }
        if (! Schema::hasTable('schedule_exception_slots')) {
            Schema::create('schedule_exception_slots', function (Blueprint $t) {
                $t->id();
                $t->foreignId('schedule_exception_id')->constrained()->cascadeOnDelete();
                $t->string('slot_start', 5);
                $t->timestamps();
                $t->unique(['schedule_exception_id', 'slot_start']);
            });
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE services SET duration_minutes = CASE WHEN duration_minutes <= 30 THEN 30 ELSE 60 END WHERE duration_minutes NOT IN (30,60)');
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes IN (30,60))');
        } else {
            DB::table('services')->whereNotIn('duration_minutes', [30, 60])
                ->update(['duration_minutes' => DB::raw('CASE WHEN duration_minutes <= 30 THEN 30 ELSE 60 END')]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes > 0)');
        }
        Schema::dropIfExists('schedule_exception_slots');
        Schema::dropIfExists('doctor_schedule_slots');
    }
};
