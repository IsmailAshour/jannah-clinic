<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            // 'before' = pre-treatment, 'after' = post-treatment. CHECK constraint
            // enforced via Postgres; SQLite tests will silently ignore the check.
            $table->string('kind', 16);
            $table->string('file_path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size')->nullable();
            $table->string('caption', 500)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index(['appointment_id', 'kind']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \DB::statement("ALTER TABLE appointment_photos ADD CONSTRAINT appointment_photos_kind_check CHECK (kind IN ('before','after'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_photos');
    }
};
