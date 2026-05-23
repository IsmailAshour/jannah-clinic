<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16);
            $t->dateTime('sent_at');
            $t->string('recipient_email');
            $t->timestamps();

            // UNIQUE — the idempotency guard. A duplicate dispatch trying to
            // insert the same (appointment_id, kind) is rejected at the DB
            // level even if two workers race past the application check.
            $t->unique(['appointment_id', 'kind']);
            $t->index('sent_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointment_reminders ADD CONSTRAINT appointment_reminders_kind_check CHECK (kind IN ('before_24h','before_2h'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointment_reminders DROP CONSTRAINT IF EXISTS appointment_reminders_kind_check');
        }
        Schema::dropIfExists('appointment_reminders');
    }
};
