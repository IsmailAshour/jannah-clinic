<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->smallInteger('weekday');
            $t->boolean('morning_enabled')->default(false);
            $t->time('morning_start')->nullable();
            $t->time('morning_end')->nullable();
            $t->boolean('evening_enabled')->default(false);
            $t->time('evening_start')->nullable();
            $t->time('evening_end')->nullable();
            $t->integer('slot_interval_minutes')->default(30);
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'weekday']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
            DB::statement('ALTER TABLE doctor_schedules ADD CONSTRAINT doctor_schedules_interval_check CHECK (slot_interval_minutes > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_schedules DROP CONSTRAINT IF EXISTS doctor_schedules_weekday_check');
            DB::statement('ALTER TABLE doctor_schedules DROP CONSTRAINT IF EXISTS doctor_schedules_interval_check');
        }
        Schema::dropIfExists('doctor_schedules');
    }
};
