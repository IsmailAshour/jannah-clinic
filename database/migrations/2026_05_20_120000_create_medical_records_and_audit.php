<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->text('visible_summary');
            $t->text('staff_notes')->nullable();
            $t->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medical_entry_id')->constrained()->cascadeOnDelete();
            $t->string('medication_name');
            $t->string('dosage');
            $t->string('frequency');
            $t->string('duration');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['medical_entry_id', 'created_at']);
        });

        Schema::create('medical_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id');
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->json('changed_fields')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['customer_id', 'created_at']);
            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['user_id', 'created_at']);
        });

        Schema::table('customer_profiles', function (Blueprint $t) {
            $t->text('chronic_conditions')->nullable();
            $t->text('allergies')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE medical_audit_logs ADD CONSTRAINT medical_audit_logs_action_check CHECK (action IN ('entry.created','entry.updated','entry.viewed','prescription.created','prescription.updated','prescription.deleted','profile_medical.viewed','profile_medical.updated'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medical_audit_logs DROP CONSTRAINT IF EXISTS medical_audit_logs_action_check');
        }
        Schema::table('customer_profiles', function (Blueprint $t) {
            $t->dropColumn(['chronic_conditions', 'allergies']);
        });
        Schema::dropIfExists('medical_audit_logs');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medical_entries');
    }
};
