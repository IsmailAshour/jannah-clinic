<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_attachments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('medical_entry_id')->constrained()->cascadeOnDelete();
            $t->string('file_path');                   // path on the local (private) disk
            $t->string('original_filename');           // preserved for download UX
            $t->string('mime_type', 64);
            $t->integer('file_size');                  // bytes
            $t->string('title', 255)->nullable();      // optional admin label (e.g. "تحليل دم")
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();

            $t->index('medical_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_attachments');
    }
};
