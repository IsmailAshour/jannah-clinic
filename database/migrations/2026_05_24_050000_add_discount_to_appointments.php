<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-applied discount on admin-side bookings. discount_type is the
 * lever (percent | fixed), discount_value is the raw user input (10 for
 * "10%" or 50 for "50₪"), discount_amount is the resolved ₪ figure
 * computed at booking time. discount_reason is an optional note for
 * audit. All four are NULL when no discount was applied.
 *
 * Postgres CHECK enforces the invariant: if any discount field is set,
 * all of (type, value, amount) are set; the resolved amount is positive;
 * and the resolved amount cannot exceed the appointment's gross total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $t): void {
            $t->string('discount_type', 16)->nullable()->after('price_at_booking');
            $t->decimal('discount_value', 8, 2)->nullable()->after('discount_type');
            $t->decimal('discount_amount', 10, 2)->nullable()->after('discount_value');
            $t->string('discount_reason', 500)->nullable()->after('discount_amount');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
                ALTER TABLE appointments
                ADD CONSTRAINT appointments_discount_shape_check CHECK (
                    discount_type IS NULL
                    OR (
                        discount_type IN ('percent', 'fixed')
                        AND discount_value IS NOT NULL AND discount_value > 0
                        AND discount_amount IS NOT NULL AND discount_amount > 0
                        AND discount_amount <= price_at_booking
                    )
                )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_discount_shape_check');
        }
        Schema::table('appointments', function (Blueprint $t): void {
            $t->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'discount_reason']);
        });
    }
};
