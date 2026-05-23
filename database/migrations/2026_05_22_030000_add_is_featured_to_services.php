<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $t): void {
            $t->boolean('is_featured')->default(false)->after('online_service_enabled');
        });

        // Backfill: mark the first 4 active services (preferring those with an
        // image, then by display_order) as featured to preserve the home page
        // after deploy — admins can later toggle from the service form.
        $ids = DB::table('services')
            ->where('is_active', true)
            ->orderByRaw('image_path IS NULL')
            ->orderBy('display_order')
            ->orderBy('id')
            ->limit(4)
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('services')->whereIn('id', $ids)->update(['is_featured' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $t): void {
            $t->dropColumn('is_featured');
        });
    }
};
