<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete()->unique();
            $t->foreignId('coverage_area_id')->constrained('home_service_coverage_areas')->restrictOnDelete();
            $t->string('address_text');
            $t->string('location_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addresses');
    }
};
