<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_service', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->decimal('price_override', 10, 2)->nullable();
            $t->timestamps();
            $t->unique(['doctor_profile_id', 'service_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_service ADD CONSTRAINT doctor_service_price_check CHECK (price_override IS NULL OR price_override >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE doctor_service DROP CONSTRAINT IF EXISTS doctor_service_price_check');
        }
        Schema::dropIfExists('doctor_service');
    }
};
