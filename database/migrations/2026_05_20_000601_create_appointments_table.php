<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('doctor_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->dateTime('start_at');
            $t->dateTime('end_at');
            $t->string('status', 16)->default('requested');
            $t->decimal('price_at_booking', 10, 2);
            $t->string('delivery_mode', 8);
            $t->decimal('home_surcharge_amount', 10, 2)->default(0);
            $t->string('created_by_role', 20);
            $t->string('cancellation_reason')->nullable();
            $t->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $t->timestamps();
            $t->index(['doctor_profile_id', 'start_at']);
            $t->index(['customer_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('requested','confirmed','rejected','completed','cancelled','no_show','rescheduled'))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_mode_check CHECK (delivery_mode IN ('center','home'))");
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_price_check CHECK (price_at_booking >= 0)');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_time_check CHECK (end_at > start_at)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['status', 'mode', 'price', 'time'] as $c) {
                DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_{$c}_check");
            }
        }
        Schema::dropIfExists('appointments');
    }
};
