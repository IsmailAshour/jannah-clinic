<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t): void {
            $t->boolean('online_service_enabled')->default(false)->after('home_service_enabled');
        });

        Schema::table('appointments', function (Blueprint $t): void {
            $t->string('whatsapp_phone', 32)->nullable()->after('delivery_mode');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_mode_check');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_mode_check CHECK (delivery_mode IN ('center','home','online'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_mode_check');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_mode_check CHECK (delivery_mode IN ('center','home'))");
        }

        Schema::table('appointments', function (Blueprint $t): void {
            $t->dropColumn('whatsapp_phone');
        });

        Schema::table('services', function (Blueprint $t): void {
            $t->dropColumn('online_service_enabled');
        });
    }
};
