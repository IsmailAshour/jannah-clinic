<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_addresses', function (Blueprint $t): void {
            $t->decimal('lat', 10, 7)->nullable()->after('address_text');
            $t->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_addresses ADD CONSTRAINT service_addresses_lat_check CHECK (lat IS NULL OR (lat BETWEEN -90 AND 90))');
            DB::statement('ALTER TABLE service_addresses ADD CONSTRAINT service_addresses_lng_check CHECK (lng IS NULL OR (lng BETWEEN -180 AND 180))');
            DB::statement('ALTER TABLE service_addresses ADD CONSTRAINT service_addresses_geo_pair_check CHECK ((lat IS NULL) = (lng IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_addresses DROP CONSTRAINT IF EXISTS service_addresses_geo_pair_check');
            DB::statement('ALTER TABLE service_addresses DROP CONSTRAINT IF EXISTS service_addresses_lng_check');
            DB::statement('ALTER TABLE service_addresses DROP CONSTRAINT IF EXISTS service_addresses_lat_check');
        }

        Schema::table('service_addresses', function (Blueprint $t): void {
            $t->dropColumn(['lat', 'lng']);
        });
    }
};
