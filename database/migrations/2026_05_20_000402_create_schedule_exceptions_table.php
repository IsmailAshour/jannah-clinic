<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('type', 16);
            $t->time('custom_start')->nullable();
            $t->time('custom_end')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'date']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE schedule_exceptions ADD CONSTRAINT schedule_exceptions_type_check CHECK (type IN ('closed','custom_hours'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedule_exceptions DROP CONSTRAINT IF EXISTS schedule_exceptions_type_check');
        }
        Schema::dropIfExists('schedule_exceptions');
    }
};
