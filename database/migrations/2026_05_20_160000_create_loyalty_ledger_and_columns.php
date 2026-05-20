<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->integer('points_delta');
            $table->integer('balance_after');
            $table->string('reason', 32);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
            $table->index(['reason', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE loyalty_ledger ADD CONSTRAINT loyalty_ledger_reason_check
                CHECK (reason IN ('earned_from_payment','redeemed_for_appointment','clawback_from_refund','refund_reversal','adjustment_by_manager'))");
        }

        Schema::table('services', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(true);
            $table->unsignedInteger('loyalty_redemption_points')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_loyalty_points_positive
                CHECK (loyalty_redemption_points IS NULL OR loyalty_redemption_points > 0)');
        }

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->integer('loyalty_balance')->default(0);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_method', 16)->default('cash');
            $table->unsignedInteger('loyalty_points_spent')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_payment_method_check
                CHECK (payment_method IN ('cash','loyalty_points'))");
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_loyalty_points_consistency
                CHECK ((payment_method = \'loyalty_points\') = (loyalty_points_spent IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'loyalty_points_spent']);
        });
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('loyalty_balance');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['loyalty_enabled', 'loyalty_redemption_points']);
        });
        Schema::dropIfExists('loyalty_ledger');
    }
};
