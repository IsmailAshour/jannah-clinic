<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('service_categories')->restrictOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('base_price', 10, 2);
            $t->integer('duration_minutes');
            $t->boolean('home_service_enabled')->default(false);
            $t->string('icon_key')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('display_order')->default(0);
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_base_price_check CHECK (base_price >= 0)');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_base_price_check');
            DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_duration_check');
        }
        Schema::dropIfExists('services');
    }
};
