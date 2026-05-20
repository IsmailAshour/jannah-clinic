<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('status', 16)->default('pending');
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('refunded_at')->nullable();
            $t->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('refund_reference')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('file_path');
            $t->unsignedInteger('file_size');
            $t->string('mime_type', 64);
            $t->string('status', 16)->default('uploaded');
            $t->text('rejection_reason')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['payment_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending','submitted','paid','rejected','refund_pending','refunded'))");
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount >= 0)');
            DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_status_check CHECK (status IN ('uploaded','rejected'))");
            DB::statement('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_size_check CHECK (file_size > 0)');
        }

        // Idempotent backfill: every existing appointment gets a Payment.pending.
        // Driver-portable timestamp (works on both pgsql and sqlite).
        DB::statement(
            'INSERT INTO payments (appointment_id, amount, status, created_at, updated_at) '.
            "SELECT a.id, a.price_at_booking, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM appointments a ".
            'WHERE NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['status', 'amount'] as $c) {
                DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_{$c}_check");
            }
            foreach (['status', 'size'] as $c) {
                DB::statement("ALTER TABLE payment_receipts DROP CONSTRAINT IF EXISTS payment_receipts_{$c}_check");
            }
        }
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payments');
    }
};
