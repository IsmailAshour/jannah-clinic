<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('color_variant', 16)->default('brand');
            $t->integer('display_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE service_categories ADD CONSTRAINT service_categories_color_check CHECK (color_variant IN ('brand','gold'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE service_categories DROP CONSTRAINT IF EXISTS service_categories_color_check');
        }
        Schema::dropIfExists('service_categories');
    }
};
