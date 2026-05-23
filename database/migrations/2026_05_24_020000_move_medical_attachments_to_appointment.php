<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move medical_attachments from belonging to MedicalEntry to belonging
 * to Appointment directly. Rationale: clinicians need to attach lab PDFs
 * and imaging BEFORE the structured medical entry is written (often the
 * lab results arrive first, the doctor reviews them, THEN writes the
 * entry). Tying the FK to Appointment removes that dependency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_attachments', function (Blueprint $t): void {
            $t->foreignId('appointment_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $t->index('appointment_id', 'medical_attachments_appointment_id_index_new');
        });

        // Backfill: copy appointment_id from medical_entries for existing rows
        // (defensive — most local installs will have zero rows here).
        DB::statement('UPDATE medical_attachments SET appointment_id = (SELECT appointment_id FROM medical_entries WHERE medical_entries.id = medical_attachments.medical_entry_id)');

        Schema::table('medical_attachments', function (Blueprint $t): void {
            $t->dropForeign(['medical_entry_id']);
            $t->dropIndex(['medical_entry_id']);
            $t->dropColumn('medical_entry_id');
            // Now that backfill is complete, enforce NOT NULL.
        });

        // SQLite (used in tests) can't ALTER column NOT NULL portably, so we
        // use a doctrine-free raw statement only on PostgreSQL; the SQLite
        // path leaves nullable=true which is fine for tests because all
        // fresh inserts always supply appointment_id.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE medical_attachments ALTER COLUMN appointment_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('medical_attachments', function (Blueprint $t): void {
            $t->foreignId('medical_entry_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
        DB::statement('UPDATE medical_attachments SET medical_entry_id = (SELECT id FROM medical_entries WHERE medical_entries.appointment_id = medical_attachments.appointment_id LIMIT 1)');
        Schema::table('medical_attachments', function (Blueprint $t): void {
            $t->dropForeign(['appointment_id']);
            $t->dropIndex('medical_attachments_appointment_id_index_new');
            $t->dropColumn('appointment_id');
        });
    }
};
